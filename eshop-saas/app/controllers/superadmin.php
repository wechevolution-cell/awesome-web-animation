<?php
declare(strict_types=1);

function sa_render(string $view, array $vars = [], string $title = ''): never {
    $vars['title'] = $title;
    render_admin_like('layouts/superadmin', 'superadmin/' . $view, $vars);
}

function superadmin_dispatch(string $path): void {
    security_headers(true);
    $GLOBALS['LOCALE'] = 'en';
    if ($path === '/superadmin/login/') { sa_login(); return; }
    if (!is_super()) redirect('/superadmin/login/');
    $routes = [
        ['POST', '/superadmin/logout/', static function () { logout(); redirect('/superadmin/login/'); }],
        ['GET', '/superadmin/', static fn() => sa_dashboard()],
        ['GET', '/superadmin/shops/', static fn() => sa_shops()],
        ['ANY', '/superadmin/shops/new/', static fn() => sa_shop_new()],
        ['ANY', '/superadmin/shops/(?<id>\d+)/', static fn($p) => sa_shop((int) $p['id'])],
        ['POST', '/superadmin/impersonate/(?<id>\d+)/', static fn($p) => sa_impersonate((int) $p['id'])],
        ['POST', '/superadmin/impersonate/exit/', static function () { $sid = impersonated_shop_id(); unset($_SESSION['impersonate_shop']); audit('impersonation.ended', 'shop', $sid, $sid); redirect($sid ? "/superadmin/shops/$sid/" : '/superadmin/'); }],
        ['GET', '/superadmin/plans/', static fn() => sa_plans()],
        ['ANY', '/superadmin/plans/(?<id>new|\d+)/', static fn($p) => sa_plan_edit($p['id'] === 'new' ? null : (int) $p['id'])],
        ['ANY', '/superadmin/templates/', static fn() => sa_templates()],
        ['ANY', '/superadmin/users/', static fn() => sa_users()],
        ['GET', '/superadmin/domains/', static fn() => sa_domains()],
        ['GET', '/superadmin/audit/', static fn() => sa_audit()],
        ['ANY', '/superadmin/notifications/', static fn() => sa_notifications()],
        ['GET', '/superadmin/payments/', static fn() => sa_payments()],
        ['ANY', '/superadmin/backups/', static fn() => sa_backups()],
        ['ANY', '/superadmin/settings/', static fn() => sa_settings()],
        ['ANY', '/superadmin/messages/', static fn() => sa_messages()],
        ['POST', '/superadmin/cron/', static function () { $r = cron_run(20.0); flash('ok', 'Cron ran: ' . $r['subscriptions']['checked'] . ' shops checked, ' . $r['subscriptions']['transitions'] . ' status changes, ' . $r['imports'] . ' imports advanced.'); back('/superadmin/'); }],
    ];
    if (!dispatch($routes, $path)) abort(404);
}

function sa_login(): void {
    if (is_super()) redirect('/superadmin/');
    if (is_post()) {
        [$ok, $msg] = login_attempt((string) post('email'), (string) ($_POST['password'] ?? ''), 'super');
        if ($ok) redirect('/superadmin/');
        flash('error', $msg); remember_input();
        redirect('/superadmin/login/');
    }
    render('admin/login', ['area' => 'super']);
}

function sa_dashboard(): never {
    $c = static fn(string $where, array $p = []) => (int) val("SELECT COUNT(*) FROM shops WHERE deleted_at IS NULL AND $where", $p);
    $win = (int) setting('expiring_window_days', '10');
    $stats = [
        'total' => $c('1'), 'active' => $c("status IN ('ACTIVE','EXPIRING')"), 'locked' => $c("status IN ('LOCKED','EXPIRED','SUSPENDED')"),
        'expiring' => $c("status IN ('ACTIVE','EXPIRING') AND subscription_expires <= ?", [date('Y-m-d H:i:s', time() + $win * 86400)]),
        'pending' => $c("status = 'PENDING'"),
        'active_products' => (int) val("SELECT COUNT(*) FROM products p JOIN shops s ON s.id = p.shop_id AND s.deleted_at IS NULL WHERE p.status = 'active' AND p.deleted_at IS NULL"),
        'stored_products' => (int) val('SELECT COUNT(*) FROM products p JOIN shops s ON s.id = p.shop_id AND s.deleted_at IS NULL WHERE p.deleted_at IS NULL'),
        'mrr' => (int) val("SELECT COALESCE(SUM(pl.price_cents / pl.billing_period_months), 0) FROM shops s JOIN subscription_plans pl ON pl.id = s.plan_id WHERE s.deleted_at IS NULL AND s.status IN ('ACTIVE','EXPIRING') AND s.is_demo = 0"),
        'orders_30' => (int) val('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [date('Y-m-d', strtotime('-30 days'))]),
    ];
    $expiring = rows("SELECT s.*, pl.name AS plan_name FROM shops s LEFT JOIN subscription_plans pl ON pl.id = s.plan_id WHERE s.deleted_at IS NULL AND s.status IN ('ACTIVE','EXPIRING','EXPIRED','LOCKED') ORDER BY s.subscription_expires LIMIT 8");
    $notifs = rows("SELECT * FROM notifications WHERE audience = 'super' ORDER BY id DESC LIMIT 8");
    $byPlan = rows("SELECT pl.name, COUNT(s.id) n FROM subscription_plans pl LEFT JOIN shops s ON s.plan_id = pl.id AND s.deleted_at IS NULL GROUP BY pl.id ORDER BY pl.position");
    $health = [
        'cron' => setting('cron_last_run'), 'backup' => backups_list()[0]['time'] ?? null,
        'writable' => is_writable(DATA) && is_writable(UPLOADS), 'php' => PHP_VERSION, 'gd_webp' => function_exists('imagewebp'), 'zip' => class_exists('ZipArchive'),
    ];
    sa_render('dashboard', compact('stats', 'expiring', 'notifs', 'byPlan', 'health'), 'Platform overview');
}

function sa_shops(): never {
    $where = '1'; $params = [];
    $st = get('status');
    if ($st === 'deleted') $where = 's.deleted_at IS NOT NULL';
    else {
        $where = 's.deleted_at IS NULL';
        if (in_array($st, SHOP_STATUSES, true)) { $where .= ' AND s.status = ?'; $params[] = $st; }
        if ($st === 'expiring_soon') { $where .= " AND s.status IN ('ACTIVE','EXPIRING') AND s.subscription_expires <= ?"; $params[] = date('Y-m-d H:i:s', time() + (int) setting('expiring_window_days', '10') * 86400); }
    }
    if (($q = clean_text(get('q'), 100)) !== '') { $where .= ' AND (s.name LIKE ? OR s.email LIKE ? OR s.slug LIKE ? OR EXISTS (SELECT 1 FROM shop_domains d WHERE d.shop_id = s.id AND d.domain LIKE ?))'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
    if ($pid = int_in('plan_id')) { $where .= ' AND s.plan_id = ?'; $params[] = $pid; }
    $pg = paginate((int) val("SELECT COUNT(*) FROM shops s WHERE $where", $params), 30);
    $order = match (get('sort')) { 'expires' => 's.subscription_expires ASC', 'name' => 's.name COLLATE NOCASE', default => 's.id DESC' };
    $shops = rows("SELECT s.*, pl.name AS plan_name, pl.active_product_limit, (SELECT domain FROM shop_domains WHERE shop_id = s.id ORDER BY is_primary DESC LIMIT 1) AS domain,
        (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND status = 'active' AND deleted_at IS NULL) AS active_count,
        (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND deleted_at IS NULL) AS stored_count
        FROM shops s LEFT JOIN subscription_plans pl ON pl.id = s.plan_id WHERE $where ORDER BY $order LIMIT ? OFFSET ?", array_merge($params, [$pg['limit'], $pg['offset']]));
    sa_render('shops', ['shops' => $shops, 'pg' => $pg, 'plans' => rows('SELECT id, name FROM subscription_plans ORDER BY position')], 'Shops');
}

function sa_shop_new(): never {
    if (is_post()) {
        try {
            $r = shop_provision($_POST);
            clear_old(); unset($_SESSION['_errors']);
            if ($r['invite_url']) $_SESSION['invite_link'] = abs_url($r['invite_url']);
            flash('ok', 'Shop provisioned.');
            redirect('/superadmin/shops/' . $r['shop_id'] . '/');
        } catch (ValidationError $e) {
            remember_input(); $_SESSION['_errors'] = $e->errors; flash('error', 'Please correct the highlighted fields.');
        } catch (UserError $e) { remember_input(); flash('error', $e->getMessage()); }
        redirect('/superadmin/shops/new/');
    }
    $plans = rows("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY position");
    $templates = rows("SELECT * FROM templates WHERE status = 'active' ORDER BY position");
    $demo = [];
    foreach (rows('SELECT slug, template_key FROM shops WHERE is_demo = 1 AND deleted_at IS NULL') as $d) $demo[$d['template_key']] = $d['slug'];
    sa_render('shop-new', compact('plans', 'templates', 'demo'), 'New shop');
}

function sa_shop(int $id): never {
    $shop = shop_by_id($id);
    if (!$shop) abort(404);
    if (is_post()) {
        $act = (string) post('act');
        handle(static function () use ($shop, $id, $act) {
            switch ($act) {
                case 'renew':
                    $pay = post('payment_provider') ? ['provider' => post('payment_provider'), 'amount' => to_cents(post('payment_amount')), 'reference' => clean_text(post('payment_reference'), 120), 'paid' => (bool) post('payment_paid')] : [];
                    $r = subscription_renew($id, (int) post('months'), (int) post('plan_id') ?: null, post('mode') === 'reset' ? 'reset' : 'extend', 'renewal', $pay);
                    flash('ok', 'Renewed. New expiry: ' . fmt_date($r['expires'], true) . '. Status: ' . $r['status'] . '. No shop data was modified.');
                    break;
                case 'change_plan':
                    $r = shop_change_plan($id, (int) post('plan_id'), post('strategy') === 'deactivate_newest' ? 'deactivate_newest' : 'admin_choose', clean_text(post('note'), 200));
                    flash('ok', 'Package changed (' . $r['direction'] . '). Active ' . $r['active'] . ' / limit ' . $r['limit'] . ($r['deactivated'] ? ", {$r['deactivated']} deactivated (not deleted)." : ($r['active'] > $r['limit'] ? '. The shop admin must choose which products stay active.' : '.')));
                    break;
                case 'change_template': shop_change_template($id, (string) post('template_key')); flash('ok', 'Template switched. Data untouched.'); break;
                case 'lock': shop_set_status($id, 'LOCKED', clean_text(post('note'), 200) ?: 'Locked manually'); flash('ok', 'Shop locked. Storefront shows the lock page; all data retained.'); break;
                case 'suspend': shop_set_status($id, 'SUSPENDED', clean_text(post('note'), 200) ?: 'Suspended manually'); flash('ok', 'Shop suspended.'); break;
                case 'activate':
                    if ($shop['subscription_expires'] && strtotime($shop['subscription_expires']) <= time()) throw new UserError('The subscription has expired — use Renew to reactivate (it recalculates the expiry date).');
                    shop_set_status($id, subscription_status_for(array_merge($shop, ['status' => 'ACTIVE'])), 'Reactivated manually'); flash('ok', 'Shop reactivated.'); break;
                case 'delete':
                    if (mb_strtolower(trim((string) post('confirm'))) !== mb_strtolower(shop_primary_domain($id) ?? $shop['slug'])) throw new UserError('Type the exact domain (or slug) to confirm deletion.');
                    shop_soft_delete($id); flash('ok', 'Shop deleted (soft). It can be restored, or purged permanently.'); break;
                case 'restore': shop_restore($id); flash('ok', 'Shop restored as LOCKED. Renew or activate it.'); break;
                case 'purge':
                    if (!$shop['deleted_at']) throw new UserError('Delete the shop first.');
                    if (trim((string) post('confirm')) !== 'PURGE ' . $shop['slug']) throw new UserError('Type PURGE ' . $shop['slug'] . ' to confirm permanent deletion.');
                    $b = shop_purge($id); flash('ok', 'Shop permanently deleted. Backup taken first: ' . basename($b)); return '/superadmin/shops/?status=deleted';
                case 'add_domain': domain_add($id, (string) post('domain'), (bool) post('primary')); flash('ok', 'Domain added. Point its DNS to this hosting and add it in hPanel (see README).'); break;
                case 'remove_domain': $d = row('SELECT * FROM shop_domains WHERE id = ? AND shop_id = ?', [(int) post('domain_id'), $id]); if ($d) { q('DELETE FROM shop_domains WHERE id = ?', [$d['id']]); audit('domain.removed', 'shop', $id, $id, 'success', ['domain' => $d['domain']]); } flash('ok', 'Domain removed.'); break;
                case 'primary_domain': if (val('SELECT 1 FROM shop_domains WHERE id = ? AND shop_id = ?', [(int) post('domain_id'), $id])) { q('UPDATE shop_domains SET is_primary = (id = ?) WHERE shop_id = ?', [(int) post('domain_id'), $id]); } flash('ok', 'Primary domain set.'); break;
                case 'check_domain': $d = row('SELECT * FROM shop_domains WHERE id = ? AND shop_id = ?', [(int) post('domain_id'), $id]); if ($d) { $r = domain_check((int) $d['id']); flash($r['dns'] === 'ok' ? 'ok' : 'warn', "{$d['domain']}: DNS {$r['dns']} (" . (implode(', ', $r['ips']) ?: 'no A record') . ($r['expected'] ? ", expected {$r['expected']}" : '') . "), SSL {$r['ssl']}."); } break;
                case 'update_details':
                    $email = mb_strtolower(clean_text(post('email'), 190));
                    if (!valid_email($email)) throw new ValidationError(['email' => 'Valid email required.']);
                    update('shops', ['name' => clean_text(post('name'), 120) ?: $shop['name'], 'company' => clean_text(post('company'), 160), 'vat_number' => clean_text(post('vat_number'), 40), 'owner_name' => clean_text(post('owner_name'), 120), 'email' => $email, 'phone' => clean_text(post('phone'), 40),
                        'hosting_notes' => clean_multiline(post('hosting_notes'), 2000), 'notes' => clean_multiline(post('notes'), 2000), 'updated_at' => now()], 'id = ?', [$id]);
                    audit('shop.details_updated', 'shop', $id, $id); flash('ok', 'Details saved.'); break;
                case 'limits':
                    $a = post('active_limit_override') === '' ? null : max(0, (int) post('active_limit_override'));
                    $s = post('stored_limit_override') === '' ? null : max(1, (int) post('stored_limit_override'));
                    update('shops', ['active_limit_override' => $a, 'stored_limit_override' => $s, 'updated_at' => now()], 'id = ?', [$id]);
                    $fresh = shop_by_id($id);
                    update('shops', ['quota_over' => product_active_count($id) > shop_active_limit($fresh) ? 1 : 0], 'id = ?', [$id]);
                    audit('shop.limits_override', 'shop', $id, $id, 'success', ['active' => $a, 'stored' => $s]); flash('ok', 'Limits saved.'); break;
                case 'invite':
                    $owner = row("SELECT * FROM users WHERE shop_id = ? AND role = 'owner' AND deleted_at IS NULL ORDER BY id LIMIT 1", [$id]);
                    if (!$owner) throw new UserError('No owner account.');
                    $tok = password_token_create('user', (int) $owner['id'], 'invite', 24 * 7);
                    $_SESSION['invite_link'] = abs_url('/admin/set-password/?token=' . urlencode($tok));
                    send_mail($owner['email'], 'Your store admin access', 'Set your password (valid 7 days): ' . $_SESSION['invite_link']);
                    audit('shop.owner_invite', 'user', (int) $owner['id'], $id); flash('ok', 'New password link created and emailed to ' . $owner['email'] . '.'); break;
                default: throw new UserError('Unknown action.');
            }
        }, "/superadmin/shops/$id/");
    }
    $plan = plan_by_id((int) $shop['plan_id']);
    $q = shop_quota($shop);
    $domains = rows('SELECT * FROM shop_domains WHERE shop_id = ? ORDER BY is_primary DESC, id', [$id]);
    $history = rows('SELECT * FROM subscription_history WHERE shop_id = ? ORDER BY id DESC LIMIT 50', [$id]);
    $payments = rows('SELECT * FROM payments WHERE shop_id = ? ORDER BY id DESC LIMIT 20', [$id]);
    $users = rows('SELECT * FROM users WHERE shop_id = ? AND deleted_at IS NULL ORDER BY id', [$id]);
    $audit = rows('SELECT * FROM audit_logs WHERE shop_id = ? ORDER BY id DESC LIMIT 25', [$id]);
    $plans = rows('SELECT * FROM subscription_plans ORDER BY position');
    $templates = rows("SELECT * FROM templates WHERE status = 'active' ORDER BY position");
    $providers = rows('SELECT * FROM payment_providers WHERE enabled = 1 ORDER BY position');
    $invite = $_SESSION['invite_link'] ?? null; unset($_SESSION['invite_link']);
    $counts = ['orders' => (int) val('SELECT COUNT(*) FROM orders WHERE shop_id = ?', [$id]), 'customers' => (int) val('SELECT COUNT(*) FROM customers WHERE shop_id = ?', [$id]), 'categories' => (int) val('SELECT COUNT(*) FROM categories WHERE shop_id = ?', [$id]), 'media' => (int) val('SELECT COUNT(*) FROM media WHERE shop_id = ?', [$id])];
    sa_render('shop', compact('shop', 'plan', 'q', 'domains', 'history', 'payments', 'users', 'audit', 'plans', 'templates', 'providers', 'invite', 'counts'), $shop['name']);
}

function sa_impersonate(int $id): never {
    $shop = shop_by_id($id);
    if (!$shop || $shop['deleted_at']) abort(404);
    $_SESSION['impersonate_shop'] = $id;
    audit('impersonation.started', 'shop', $id, $id);
    redirect('/admin/');
}

function sa_plans(): never {
    $plans = rows('SELECT pl.*, (SELECT COUNT(*) FROM shops s WHERE s.plan_id = pl.id AND s.deleted_at IS NULL) AS shops FROM subscription_plans pl ORDER BY position');
    foreach ($plans as &$p) $p['features'] = plan_features((int) $p['id']);
    sa_render('plans', ['plans' => $plans], 'Packages');
}
function sa_plan_edit(?int $id): never {
    $plan = $id ? plan_by_id($id) : null;
    if ($id && !$plan) abort(404);
    if (is_post()) handle(static function () use ($id, $plan) {
        $name = clean_text(post('name'), 80);
        $err = [];
        if ($name === '') $err['name'] = 'Name required.';
        $durations = array_values(array_unique(array_filter(array_map('intval', arr($_POST['durations'] ?? [])), static fn($d) => $d > 0 && $d <= 60)));
        sort($durations);
        if (!$durations) $err['durations'] = 'Choose at least one duration.';
        $limit = (int) post('active_product_limit');
        if ($limit < 1) $err['active_product_limit'] = 'Must be at least 1.';
        if ($err) throw new ValidationError($err);
        $slug = slugify((string) (post('slug') ?: $name));
        if (val('SELECT 1 FROM subscription_plans WHERE slug = ?' . ($id ? ' AND id <> ?' : ''), array_merge([$slug], $id ? [$id] : []))) $slug .= '-' . time();
        $data = ['name' => $name, 'slug' => $slug, 'description' => clean_text(post('description'), 300), 'price_cents' => (int) to_cents(post('price')), 'setup_fee_cents' => (int) to_cents(post('setup_fee')),
            'billing_period_months' => max(1, (int) post('billing_period_months', 1)), 'active_product_limit' => $limit, 'stored_product_limit' => max($limit, (int) post('stored_product_limit', 10000)),
            'staff_limit' => max(1, (int) post('staff_limit', 1)), 'storage_mb' => max(50, (int) post('storage_mb', 1024)), 'duration_options' => implode(',', $durations),
            'status' => post('status') === 'archived' ? 'archived' : 'active', 'position' => (int) post('position'), 'updated_at' => now()];
        $id = tx(static function () use ($id, $data) {
            if ($id) update('subscription_plans', $data, 'id = ?', [$id]); else $id = insert('subscription_plans', $data + ['currency' => 'EUR', 'created_at' => now()]);
            q('DELETE FROM plan_features WHERE plan_id = ?', [$id]);
            foreach (['import', 'feeds', 'marketplace_feeds', 'custom_feeds', 'popups', 'seo_tools', 'typography'] as $f) insert('plan_features', ['plan_id' => $id, 'feature' => $f, 'value' => post('f_' . $f) ? '1' : '0']);
            return $id;
        });
        audit($plan ? 'plan.updated' : 'plan.created', 'plan', $id, null, 'success', $data);
        if ($plan && (int) $plan['active_product_limit'] > $limit) {
            foreach (rows('SELECT * FROM shops WHERE plan_id = ? AND deleted_at IS NULL AND active_limit_override IS NULL', [$id]) as $s) {
                if (product_active_count((int) $s['id']) > $limit) { update('shops', ['quota_over' => 1], 'id = ?', [$s['id']]); notify('quota.over', 'Choose which products stay active', "Your package now allows $limit active products.", (int) $s['id'], 'shop', 'warn'); }
            }
        }
        flash('ok', 'Package saved. Changes apply to all shops on this package immediately.');
        return "/superadmin/plans/$id/";
    }, $id ? "/superadmin/plans/$id/" : '/superadmin/plans/new/');
    sa_render('plan-edit', ['plan' => $plan, 'features' => $plan ? plan_features((int) $plan['id']) : ['import' => '1', 'feeds' => '1', 'popups' => '1', 'seo_tools' => '1']], $plan ? 'Edit package' : 'New package');
}

function sa_templates(): never {
    if (is_post()) handle(static function () {
        $k = (string) post('key');
        if (!val('SELECT 1 FROM templates WHERE key = ?', [$k])) throw new UserError('Unknown template.');
        $st = post('status') === 'disabled' ? 'disabled' : 'active';
        if ($st === 'disabled' && val("SELECT COUNT(*) FROM shops WHERE template_key = ? AND deleted_at IS NULL", [$k])) throw new UserError('Template is in use by shops; switch them first.');
        update('templates', ['status' => $st], 'key = ?', [$k]);
        audit('template.' . $st, 'template', null, null, 'success', ['key' => $k]);
        flash('ok', 'Template updated.');
    }, '/superadmin/templates/');
    $templates = rows('SELECT t.*, (SELECT COUNT(*) FROM shops s WHERE s.template_key = t.key AND s.deleted_at IS NULL) AS shops, (SELECT slug FROM shops s WHERE s.template_key = t.key AND s.is_demo = 1 AND s.deleted_at IS NULL LIMIT 1) AS demo FROM templates t ORDER BY position');
    sa_render('templates', ['templates' => $templates], 'Templates');
}

function sa_users(): never {
    if (is_post()) handle(static function () {
        $act = post('act');
        if ($act === 'create') {
            $email = mb_strtolower(clean_text(post('email'), 190));
            if (!valid_email($email)) throw new ValidationError(['email' => 'Valid email required.']);
            if (val('SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL', [$email])) throw new ValidationError(['email' => 'Email already used.']);
            $uid = insert('users', ['shop_id' => null, 'email' => $email, 'name' => clean_text(post('name'), 120), 'role' => 'super_admin', 'status' => 'active', 'created_by' => current_user()['id'], 'created_at' => now()]);
            $tok = password_token_create('user', $uid, 'invite', 24 * 7);
            $_SESSION['invite_link'] = abs_url('/admin/set-password/?token=' . urlencode($tok));
            send_mail($email, 'Super Admin access', 'Set your password (valid 7 days): ' . $_SESSION['invite_link']);
            audit('superadmin.created', 'user', $uid);
            flash('ok', 'Super admin invited.');
        } elseif ($act === 'disable' || $act === 'enable') {
            $u = row("SELECT * FROM users WHERE id = ? AND role = 'super_admin'", [(int) post('id')]);
            if (!$u || (int) $u['id'] === (int) current_user()['id']) throw new UserError('Not allowed.');
            update('users', ['status' => $act === 'disable' ? 'disabled' : 'active'], 'id = ?', [$u['id']]);
            audit('superadmin.' . $act, 'user', (int) $u['id']);
        } elseif ($act === 'password') {
            $me = current_user();
            if (!password_verify((string) ($_POST['current'] ?? ''), (string) $me['password_hash'])) throw new UserError('Current password is wrong.');
            $pw = (string) ($_POST['password'] ?? '');
            if ($pe = password_policy($pw)) throw new UserError($pe);
            update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)], 'id = ?', [$me['id']]);
            audit('auth.password_changed', 'user', (int) $me['id']);
            flash('ok', 'Password changed.');
        }
    }, '/superadmin/users/');
    $invite = $_SESSION['invite_link'] ?? null; unset($_SESSION['invite_link']);
    sa_render('users', ['users' => rows("SELECT * FROM users WHERE role = 'super_admin' AND deleted_at IS NULL ORDER BY id"), 'invite' => $invite, 'shopUsers' => (int) val("SELECT COUNT(*) FROM users WHERE role <> 'super_admin' AND deleted_at IS NULL")], 'System users');
}

function sa_domains(): never {
    sa_render('domains', ['domains' => rows('SELECT d.*, s.name AS shop_name, s.status FROM shop_domains d JOIN shops s ON s.id = d.shop_id ORDER BY d.domain')], 'Domains');
}
function sa_audit(): never {
    $where = '1'; $params = [];
    if ($sid = int_in('shop_id')) { $where .= ' AND a.shop_id = ?'; $params[] = $sid; }
    if (($act = clean_text(get('action'), 60)) !== '') { $where .= ' AND a.action LIKE ?'; $params[] = "%$act%"; }
    if (in_array(get('outcome'), ['success', 'failure', 'denied'], true)) { $where .= ' AND a.outcome = ?'; $params[] = get('outcome'); }
    $pg = paginate((int) val("SELECT COUNT(*) FROM audit_logs a WHERE $where", $params), 60);
    $logs = rows("SELECT a.*, s.name AS shop_name FROM audit_logs a LEFT JOIN shops s ON s.id = a.shop_id WHERE $where ORDER BY a.id DESC LIMIT ? OFFSET ?", array_merge($params, [$pg['limit'], $pg['offset']]));
    sa_render('audit', ['logs' => $logs, 'pg' => $pg], 'Audit log');
}
function sa_notifications(): never {
    if (is_post()) { q("UPDATE notifications SET read_at = ? WHERE audience = 'super' AND read_at IS NULL", [now()]); redirect('/superadmin/notifications/'); }
    $pg = paginate((int) val("SELECT COUNT(*) FROM notifications WHERE audience = 'super'"), 50);
    sa_render('notifications', ['items' => rows("SELECT n.*, s.name AS shop_name FROM notifications n LEFT JOIN shops s ON s.id = n.shop_id WHERE audience = 'super' ORDER BY n.id DESC LIMIT ? OFFSET ?", [$pg['limit'], $pg['offset']]), 'pg' => $pg], 'Notifications');
}
function sa_payments(): never {
    $pg = paginate((int) val('SELECT COUNT(*) FROM payments'), 50);
    sa_render('payments', ['payments' => rows('SELECT p.*, s.name AS shop_name FROM payments p LEFT JOIN shops s ON s.id = p.shop_id ORDER BY p.id DESC LIMIT ? OFFSET ?', [$pg['limit'], $pg['offset']]), 'pg' => $pg, 'providers' => rows('SELECT * FROM payment_providers ORDER BY position')], 'Payments');
}
function sa_backups(): never {
    if (get('download')) {
        $p = backup_path((string) get('download'));
        if (!$p) abort(404);
        audit('backup.downloaded', 'backup', null, null, 'success', ['file' => basename($p)]);
        header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . basename($p) . '"'); header('Content-Length: ' . filesize($p));
        readfile($p); exit;
    }
    if (is_post()) handle(static function () {
        if (post('act') === 'create') { @set_time_limit(300); $f = backup_create('manual'); flash('ok', 'Backup created: ' . basename($f)); }
        if (post('act') === 'delete') { $p = backup_path((string) post('name')); if ($p) { unlink($p); audit('backup.deleted', 'backup', null, null, 'success', ['file' => basename($p)]); flash('ok', 'Backup deleted.'); } }
    }, '/superadmin/backups/');
    sa_render('backups', ['backups' => backups_list()], 'Backups');
}
function sa_settings(): never {
    if (is_post()) handle(static function () {
        foreach (['platform_name' => 80, 'company_name' => 160, 'company_address' => 255, 'contact_phone' => 40, 'hero_title' => 200, 'hero_subtitle' => 400] as $k => $max) setting_set($k, clean_text(post($k), $max));
        foreach (['notify_email', 'mail_from', 'contact_email'] as $k) { $v = mb_strtolower(clean_text(post($k), 190)); if ($v !== '' && !valid_email($v)) throw new ValidationError([$k => 'Invalid email.']); setting_set($k, $v); }
        setting_set('expiry_warning_days', implode(',', array_filter(array_map('intval', explode(',', (string) post('expiry_warning_days'))), static fn($n) => $n > 0 && $n < 90)) ?: '10,3,1');
        setting_set('expiring_window_days', (string) max(1, min(60, (int) post('expiring_window_days', 10))));
        setting_set('lock_grace_days', (string) max(0, min(60, (int) post('lock_grace_days'))));
        setting_set('force_https', post('force_https') ? '1' : '0');
        setting_set('avif_enabled', post('avif_enabled') ? '1' : '0');
        setting_set('canonical_host', domain_normalize((string) post('canonical_host')));
        setting_set('platform_hosts', implode(',', array_filter(array_map('domain_normalize', explode(',', (string) post('platform_hosts'))))));
        setting_set('server_ip', filter_var(post('server_ip'), FILTER_VALIDATE_IP) ? post('server_ip') : '');
        setting_set('ga4_id', preg_match('/^G-[A-Z0-9]{4,15}$/', (string) post('ga4_id')) ? post('ga4_id') : '');
        setting_set('gsc_verification', preg_replace('/[^A-Za-z0-9_\-]/', '', (string) post('gsc_verification')));
        if (post('regen_cron')) setting_set('cron_token', bin2hex(random_bytes(16)));
        audit('platform.settings_updated', 'platform');
        flash('ok', 'Platform settings saved.');
    }, '/superadmin/settings/');
    sa_render('settings', [], 'Platform settings');
}
function sa_messages(): never {
    if (is_post()) handle(static function () {
        $m = row('SELECT * FROM contact_messages WHERE id = ? AND shop_id = 0', [(int) post('id')]);
        if (!$m) throw new UserError('Not found.');
        if (post('act') === 'delete') q('DELETE FROM contact_messages WHERE id = ?', [$m['id']]); else update('contact_messages', ['read_at' => now()], 'id = ?', [$m['id']]);
    }, '/superadmin/messages/');
    sa_render('messages', ['messages' => rows('SELECT * FROM contact_messages WHERE shop_id = 0 ORDER BY id DESC LIMIT 100')], 'Platform messages');
}
