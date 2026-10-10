<?php
declare(strict_types=1);

/**
 * Provision a shop: client details -> domain -> package -> duration -> template -> admin account.
 * Returns ['shop_id', 'user_id', 'invite_url'].
 */
function shop_provision(array $in): array {
    $name = clean_text($in['name'] ?? '', 120);
    $email = mb_strtolower(clean_text($in['email'] ?? '', 190));
    $adminEmail = mb_strtolower(clean_text($in['admin_email'] ?? $email, 190));
    $slug = slugify($in['slug'] ?? '' ?: $name, 40);
    $domain = domain_normalize((string) ($in['domain'] ?? ''));
    $planId = (int) ($in['plan_id'] ?? 0);
    $months = (int) ($in['months'] ?? 1);
    $tpl = (string) ($in['template_key'] ?? 'aurora');
    $errors = [];
    if ($name === '') $errors['name'] = 'Shop name is required.';
    if (!valid_email($email)) $errors['email'] = 'A valid contact email is required.';
    if (!valid_email($adminEmail)) $errors['admin_email'] = 'A valid admin email is required.';
    elseif (val('SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL', [$adminEmail])) $errors['admin_email'] = 'This email already has a back-office account.';
    if ($slug === '') $errors['slug'] = 'Slug is required.';
    elseif (val('SELECT 1 FROM shops WHERE slug = ?', [$slug])) $errors['slug'] = 'This slug is taken.';
    if ($domain !== '') {
        if (!valid_domain($domain)) $errors['domain'] = 'Enter a domain like example.gr (no http://).';
        elseif (val('SELECT 1 FROM shop_domains WHERE domain = ?', [$domain])) $errors['domain'] = 'This domain is already mapped to a shop.';
        elseif (platform_is_host($domain)) $errors['domain'] = 'This is the platform domain.';
    }
    $plan = plan_by_id($planId);
    if (!$plan || $plan['status'] !== 'active') $errors['plan_id'] = 'Choose a package.';
    elseif (!in_array($months, array_map('intval', explode(',', $plan['duration_options'])), true)) $errors['months'] = 'Duration not offered for this package.';
    if (!val("SELECT 1 FROM templates WHERE key = ? AND status = 'active'", [$tpl])) $errors['template_key'] = 'Choose a template.';
    $pw = (string) ($in['admin_password'] ?? '');
    if ($pw !== '' && ($pe = password_policy($pw))) $errors['admin_password'] = $pe;
    if ($errors) throw new ValidationError($errors);

    $status = in_array($in['status'] ?? 'ACTIVE', ['ACTIVE', 'PENDING'], true) ? ($in['status'] ?? 'ACTIVE') : 'ACTIVE';
    $start = !empty($in['start_date']) && strtotime($in['start_date']) ? date('Y-m-d H:i:s', strtotime($in['start_date'])) : now();

    return tx(static function () use ($in, $name, $email, $adminEmail, $slug, $domain, $plan, $months, $tpl, $pw, $status, $start) {
        $sid = insert('shops', [
            'name' => $name, 'slug' => $slug, 'company' => clean_text($in['company'] ?? '', 160), 'vat_number' => clean_text($in['vat_number'] ?? '', 40),
            'owner_name' => clean_text($in['owner_name'] ?? '', 120), 'email' => $email, 'phone' => clean_text($in['phone'] ?? '', 40),
            'plan_id' => $plan['id'], 'template_key' => $tpl, 'status' => 'PENDING', 'hosting_notes' => clean_multiline($in['hosting_notes'] ?? '', 2000),
            'locale' => in_array($in['locale'] ?? 'el', ['el', 'en'], true) ? ($in['locale'] ?? 'el') : 'el', 'is_demo' => !empty($in['is_demo']) ? 1 : 0, 'created_at' => now(),
        ]);
        if ($domain !== '') insert('shop_domains', ['shop_id' => $sid, 'domain' => $domain, 'is_primary' => 1, 'created_at' => now()]);
        insert('shop_templates', ['shop_id' => $sid, 'template_key' => $tpl, 'applied_by' => current_user()['id'] ?? null, 'applied_at' => now()]);
        insert('seo_settings', ['shop_id' => $sid, 'site_title' => $name, 'organization_type' => 'OnlineStore', 'updated_at' => now()]);
        $uid = insert('users', ['shop_id' => $sid, 'email' => $adminEmail, 'name' => clean_text($in['owner_name'] ?? '', 120) ?: $name, 'password_hash' => $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : null,
            'role' => 'owner', 'status' => 'active', 'created_by' => current_user()['id'] ?? null, 'created_at' => now()]);
        shop_seed_content($sid, $name, $in['locale'] ?? 'el');
        // First subscription period (history action "creation").
        $exp = add_months($start, $months);
        $subId = insert('subscriptions', ['shop_id' => $sid, 'plan_id' => $plan['id'], 'status' => 'active', 'months' => $months, 'starts_at' => $start, 'expires_at' => $exp, 'price_cents' => (int) $plan['price_cents'] * $months, 'created_at' => now()]);
        if (!empty($in['payment_provider'])) {
            payment_record($sid, $in['payment_provider'], (int) $plan['setup_fee_cents'], 'setup', null, 'Setup fee', !empty($in['payment_paid']));
            $pid = payment_record($sid, $in['payment_provider'], (int) $plan['price_cents'] * $months, 'subscription', $subId, 'Initial period', !empty($in['payment_paid']));
            update('subscriptions', ['payment_id' => $pid], 'id = ?', [$subId]);
        }
        $u = current_user();
        insert('subscription_history', ['shop_id' => $sid, 'action' => 'creation', 'plan_id' => $plan['id'], 'plan_name' => $plan['name'], 'months' => $months, 'starts_at' => $start, 'expires_at' => $exp, 'note' => 'Shop provisioned', 'actor_id' => $u['id'] ?? null, 'actor_email' => $u['email'] ?? 'system', 'created_at' => now()]);
        $shop = shop_by_id($sid);
        $final = $status === 'PENDING' ? 'PENDING' : subscription_status_for(array_merge($shop, ['subscription_expires' => $exp, 'status' => 'ACTIVE']));
        update('shops', ['subscription_start' => $start, 'subscription_expires' => $exp, 'subscription_months' => $months, 'status' => $final, 'updated_at' => now()], 'id = ?', [$sid]);
        $invite = null;
        if ($pw === '') {
            $tok = password_token_create('user', $uid, 'invite', 24 * 7);
            $invite = '/admin/set-password/?token=' . urlencode($tok);
            if (empty($in['is_demo'])) send_mail($adminEmail, 'Your online store is ready', "Hello,\n\nYour store \"$name\" has been created.\nSet your password and sign in here (link valid for 7 days):\n" . abs_url($invite) . "\n\nAdmin panel: " . abs_url('/admin/') . "\n");
        }
        audit('shop.provisioned', 'shop', $sid, $sid, 'success', ['plan' => $plan['slug'], 'months' => $months, 'template' => $tpl, 'domain' => $domain]);
        if (empty($in['is_demo'])) notify('shop.created', "New shop: $name", "Package {$plan['name']}, $months month(s), template $tpl.", $sid, 'super', 'ok');
        return ['shop_id' => $sid, 'user_id' => $uid, 'invite_url' => $invite];
    }, true);
}

class ValidationError extends UserError {
    public function __construct(public array $errors) { parent::__construct(implode(' ', $errors)); }
}

function domain_normalize(string $d): string {
    $d = mb_strtolower(trim($d));
    $d = preg_replace('#^https?://#', '', $d) ?? '';
    $d = preg_replace('#[/?\#].*$#', '', $d) ?? '';
    $d = preg_replace('/:\d+$/', '', $d) ?? '';
    if ($d !== '' && function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $d)) $d = idn_to_ascii($d) ?: $d;
    return rtrim($d, '.');
}
function platform_is_host(string $host): bool {
    $hosts = array_filter(array_map('trim', explode(',', (string) setting('platform_hosts', ''))));
    $canon = (string) setting('canonical_host', '');
    if ($canon) { $hosts[] = $canon; $hosts[] = 'www.' . $canon; }
    return in_array($host, $hosts, true);
}

/** Starter content so a new shop is never empty: pages, menus, homepage sections. All flagged as template text. */
function shop_seed_content(int $sid, string $name, string $locale = 'el'): void {
    $el = $locale === 'el';
    $tmpl = $el ? '<p><em>Κείμενο προτύπου — ελέγξτε πριν τη δημοσίευση.</em></p>' : '<p><em>Template text — verify before going live.</em></p>';
    $pages = $el ? [
        ['Σχετικά με εμάς', 'about', "<h2>Ποιοι είμαστε</h2><p>Το $name είναι ένα ηλεκτρονικό κατάστημα. Γράψτε εδώ την ιστορία της επιχείρησής σας.</p>"],
        ['Επικοινωνία', 'contact', '<p>Επικοινωνήστε μαζί μας με τη φόρμα ή στα στοιχεία επικοινωνίας.</p>'],
        ['Όροι χρήσης', 'terms', '<h2>Όροι χρήσης</h2><p>Συμπληρώστε τους όρους χρήσης του καταστήματος.</p>'],
        ['Πολιτική απορρήτου', 'privacy', '<h2>Πολιτική απορρήτου</h2><p>Περιγράψτε ποια δεδομένα συλλέγετε και πώς τα επεξεργάζεστε (GDPR).</p>'],
        ['Αποστολές', 'shipping', '<h2>Αποστολές</h2><p>Περιγράψτε τρόπους, χρόνους και κόστος αποστολής.</p>'],
        ['Επιστροφές', 'returns', '<h2>Επιστροφές</h2><p>Περιγράψτε την πολιτική επιστροφών.</p>'],
        ['Cookies', 'cookies', '<h2>Πολιτική cookies</h2><p>Περιγράψτε τα cookies που χρησιμοποιεί το κατάστημα.</p>'],
    ] : [
        ['About us', 'about', "<h2>Who we are</h2><p>$name is an online store. Tell your story here.</p>"],
        ['Contact', 'contact', '<p>Contact us using the form or the details below.</p>'],
        ['Terms of use', 'terms', '<h2>Terms of use</h2><p>Add your store terms.</p>'],
        ['Privacy policy', 'privacy', '<h2>Privacy policy</h2><p>Describe what data you collect and how you process it (GDPR).</p>'],
        ['Shipping', 'shipping', '<h2>Shipping</h2><p>Describe shipping methods, times and costs.</p>'],
        ['Returns', 'returns', '<h2>Returns</h2><p>Describe your returns policy.</p>'],
        ['Cookies', 'cookies', '<h2>Cookie policy</h2><p>Describe the cookies the store uses.</p>'],
    ];
    $pageIds = [];
    foreach ($pages as [$t, $s, $c]) $pageIds[$s] = insert('pages', ['shop_id' => $sid, 'title' => $t, 'slug' => $s, 'content' => $tmpl . $c, 'status' => 'published', 'kind' => $s === 'contact' ? 'contact' : 'page', 'created_at' => now()]);
    $main = insert('menus', ['shop_id' => $sid, 'location' => 'header', 'name' => 'Header']);
    $foot = insert('menus', ['shop_id' => $sid, 'location' => 'footer', 'name' => 'Footer']);
    insert('menu_items', ['shop_id' => $sid, 'menu_id' => $main, 'label' => $el ? 'Όλα τα προϊόντα' : 'All products', 'type' => 'products', 'position' => 1]);
    insert('menu_items', ['shop_id' => $sid, 'menu_id' => $main, 'label' => $el ? 'Σχετικά' : 'About', 'type' => 'page', 'ref_id' => $pageIds['about'], 'position' => 90]);
    insert('menu_items', ['shop_id' => $sid, 'menu_id' => $main, 'label' => $el ? 'Επικοινωνία' : 'Contact', 'type' => 'page', 'ref_id' => $pageIds['contact'], 'position' => 91]);
    $i = 0;
    foreach (['shipping', 'returns', 'terms', 'privacy', 'cookies'] as $s) insert('menu_items', ['shop_id' => $sid, 'menu_id' => $foot, 'label' => $pages[array_search($s, array_column($pages, 1), true)][0], 'type' => 'page', 'ref_id' => $pageIds[$s], 'position' => ++$i]);
    $sections = [
        ['hero', ['title' => $name, 'subtitle' => $el ? 'Κείμενο προτύπου — γράψτε εδώ το βασικό σας μήνυμα.' : 'Template text — write your main message here.', 'cta_text' => $el ? 'Δείτε τα προϊόντα' : 'Shop now', 'cta_link' => 'products', 'image_id' => null]],
        ['categories', ['title' => '', 'limit' => 6]],
        ['featured_products', ['title' => '', 'source' => 'featured', 'limit' => 8]],
        ['banner', ['title' => $el ? 'Κείμενο προτύπου προωθητικού banner' : 'Template promo banner text', 'text' => '', 'cta_text' => '', 'cta_link' => '', 'image_id' => null]],
        ['benefits', ['items' => $el ? [['icon' => 'truck', 'title' => 'Αποστολές', 'text' => 'Περιγράψτε την πολιτική αποστολών.'], ['icon' => 'return', 'title' => 'Επιστροφές', 'text' => 'Περιγράψτε την πολιτική επιστροφών.'], ['icon' => 'shield', 'title' => 'Ασφαλείς αγορές', 'text' => 'Περιγράψτε τους τρόπους πληρωμής.']]
            : [['icon' => 'truck', 'title' => 'Shipping', 'text' => 'Describe your shipping policy.'], ['icon' => 'return', 'title' => 'Returns', 'text' => 'Describe your returns policy.'], ['icon' => 'shield', 'title' => 'Secure shopping', 'text' => 'Describe your payment methods.']]]],
        ['brands', ['title' => '', 'items' => []]],
        ['newsletter', ['title' => $el ? 'Εγγραφείτε στο newsletter' : 'Join our newsletter', 'text' => '']],
    ];
    foreach ($sections as $pos => [$type, $data]) insert('homepage_sections', ['shop_id' => $sid, 'type' => $type, 'position' => $pos + 1, 'enabled' => $type === 'brands' ? 0 : 1, 'data' => jenc($data)]);
    foreach (['shipping_flat_cents' => '400', 'free_shipping_over_cents' => '5000', 'payment_cod' => '1', 'cod_fee_cents' => '200', 'payment_bank' => '1', 'pickup_enabled' => '0'] as $k => $v) shop_setting_set($sid, $k, $v);
}

/** Change package. On downgrade below current active count the caller chooses a strategy. Never deletes products. */
function shop_change_plan(int $shopId, int $planId, string $strategy = 'admin_choose', string $note = ''): array {
    $shop = shop_by_id($shopId);
    $plan = plan_by_id($planId);
    if (!$shop || !$plan) throw new UserError('Invalid shop or package.');
    $oldPlan = plan_by_id((int) $shop['plan_id']);
    return tx(static function () use ($shop, $plan, $oldPlan, $strategy, $note) {
        $sid = (int) $shop['id'];
        update('shops', ['plan_id' => $plan['id'], 'updated_at' => now()], 'id = ?', [$sid]);
        $shop = shop_by_id($sid);
        $limit = shop_active_limit($shop);
        $active = product_active_count($sid);
        $deactivated = 0;
        if ($active > $limit) {
            if ($strategy === 'deactivate_newest') {
                $ids = col("SELECT id FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL ORDER BY activated_at DESC, id DESC LIMIT ?", [$sid, $active - $limit]);
                q("UPDATE products SET status = 'inactive', activated_at = NULL, updated_at = ? WHERE shop_id = ? AND id IN (" . in_ph($ids) . ')', array_merge([now(), $sid], $ids));
                $deactivated = count($ids);
                update('shops', ['quota_over' => 0], 'id = ?', [$sid]);
            } else {
                // Shop admin must pick which stay active. Until then the storefront shows only the first $limit.
                update('shops', ['quota_over' => 1], 'id = ?', [$sid]);
                notify('quota.over', 'Choose which products stay active', "Your package now allows $limit active products and you have $active. Deactivate " . ($active - $limit) . ' product(s). Until then only the first ' . $limit . ' are shown.', $sid, 'shop', 'warn');
            }
        } else {
            update('shops', ['quota_over' => 0], 'id = ?', [$sid]);
        }
        $u = current_user();
        $dir = $oldPlan && (int) $plan['active_product_limit'] < (int) $oldPlan['active_product_limit'] ? 'downgrade' : 'upgrade';
        insert('subscription_history', ['shop_id' => $sid, 'action' => $dir, 'plan_id' => $plan['id'], 'plan_name' => $plan['name'], 'expires_at' => $shop['subscription_expires'], 'note' => trim(($oldPlan['name'] ?? '?') . ' → ' . $plan['name'] . ($deactivated ? " ($deactivated deactivated)" : '') . ' ' . $note), 'actor_id' => $u['id'] ?? null, 'actor_email' => $u['email'] ?? 'system', 'created_at' => now()]);
        audit('shop.plan_' . $dir, 'shop', $sid, $sid, 'success', ['from' => $oldPlan['slug'] ?? null, 'to' => $plan['slug'], 'deactivated' => $deactivated, 'strategy' => $strategy]);
        return ['active' => $active, 'limit' => $limit, 'deactivated' => $deactivated, 'direction' => $dir];
    }, true);
}

/** Presentation-only switch. No data rows other than shops.template_key change. */
function shop_change_template(int $shopId, string $key): void {
    if (!val("SELECT 1 FROM templates WHERE key = ? AND status = 'active'", [$key])) throw new UserError('Template not available.');
    $shop = shop_by_id($shopId);
    update('shops', ['template_key' => $key, 'updated_at' => now()], 'id = ?', [$shopId]);
    insert('shop_templates', ['shop_id' => $shopId, 'template_key' => $key, 'applied_by' => current_user()['id'] ?? null, 'applied_at' => now()]);
    audit('shop.template_changed', 'shop', $shopId, $shopId, 'success', ['from' => $shop['template_key'], 'to' => $key]);
}

function shop_soft_delete(int $shopId): void {
    $shop = shop_by_id($shopId);
    if (!$shop) throw new UserError('Shop not found.');
    update('shops', ['status' => 'DELETED', 'deleted_at' => now(), 'updated_at' => now()], 'id = ?', [$shopId]);
    audit('shop.deleted', 'shop', $shopId, $shopId, 'success', ['soft' => true]);
}
function shop_restore(int $shopId): void {
    update('shops', ['deleted_at' => null, 'status' => 'LOCKED', 'updated_at' => now()], 'id = ?', [$shopId]);
    audit('shop.restored', 'shop', $shopId, $shopId);
}

/** Permanent deletion. Always takes a full backup first. */
function shop_purge(int $shopId): string {
    $shop = shop_by_id($shopId);
    if (!$shop) throw new UserError('Shop not found.');
    $backup = backup_create('before-purge-shop-' . $shopId);
    tx(static function () use ($shopId) {
        foreach (['import_rows', 'import_logs', 'feed_logs', 'redirects', 'not_found_log', 'contact_messages', 'notifications', 'order_items', 'order_history', 'category_relationships', 'product_images', 'product_variants', 'product_attributes', 'menu_items'] as $t) q("DELETE FROM $t WHERE shop_id = ?", [$shopId]);
        q('DELETE FROM shops WHERE id = ?', [$shopId]); // cascades the remaining tenant tables
    }, true);
    $dir = UPLOADS . '/' . $shopId;
    if (is_dir($dir)) rrmdir($dir);
    foreach ([DATA . '/feeds/' . $shopId, DATA . '/imports/' . $shopId] as $d) if (is_dir($d)) rrmdir($d);
    audit('shop.purged', 'shop', $shopId, null, 'success', ['name' => $shop['name'], 'backup' => basename($backup)]);
    return $backup;
}
function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($dir);
}

/* ---------- Domains ---------- */
function domain_add(int $shopId, string $domain, bool $primary = false): int {
    $domain = domain_normalize($domain);
    if (!valid_domain($domain)) throw new UserError('Enter a valid domain like example.gr.');
    if (platform_is_host($domain)) throw new UserError('That is the platform domain.');
    if (val('SELECT 1 FROM shop_domains WHERE domain = ?', [$domain])) throw new UserError('This domain is already mapped.');
    return tx(static function () use ($shopId, $domain, $primary) {
        $first = !val('SELECT 1 FROM shop_domains WHERE shop_id = ?', [$shopId]);
        if ($primary) q('UPDATE shop_domains SET is_primary = 0 WHERE shop_id = ?', [$shopId]);
        $id = insert('shop_domains', ['shop_id' => $shopId, 'domain' => $domain, 'is_primary' => ($primary || $first) ? 1 : 0, 'created_at' => now()]);
        audit('domain.added', 'shop', $shopId, $shopId, 'success', ['domain' => $domain]);
        return $id;
    });
}
/** DNS check: does the domain resolve to this server? (Expected IP from settings, else this server's address.) */
function domain_check(int $domainId): array {
    $d = row('SELECT * FROM shop_domains WHERE id = ?', [$domainId]);
    if (!$d) throw new UserError('Domain not found.');
    $expected = (string) setting('server_ip', '') ?: (string) ($_SERVER['SERVER_ADDR'] ?? '');
    $ips = @gethostbynamel($d['domain']) ?: [];
    $dns = $ips ? ($expected === '' || in_array($expected, $ips, true) ? 'ok' : 'mismatch') : 'not_resolving';
    $ssl = 'unknown';
    if ($dns === 'ok' && function_exists('stream_socket_client')) {
        $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'SNI_enabled' => true, 'peer_name' => $d['domain']]]);
        $c = @stream_socket_client('ssl://' . $d['domain'] . ':443', $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $ctx);
        $ssl = $c ? 'ok' : 'missing';
        if ($c) fclose($c);
    }
    update('shop_domains', ['dns_status' => $dns, 'ssl_status' => $ssl, 'hosting_status' => $dns === 'ok' ? 'ok' : $d['hosting_status'], 'last_checked_at' => now(), 'verified_at' => $dns === 'ok' ? now() : $d['verified_at']], 'id = ?', [$domainId]);
    return ['dns' => $dns, 'ssl' => $ssl, 'ips' => $ips, 'expected' => $expected];
}
