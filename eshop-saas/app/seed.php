<?php
declare(strict_types=1);

/** Default data rows. Plans/prices are DATA here and editable in Super Admin > Plans. */
function seed_defaults(PDO $pdo): void {
    $now = date('Y-m-d H:i:s');
    $plans = [
        ['Starter', 'starter', 'For small catalogues getting online.', 2000, 20, 2, 1024, 1],
        ['Business', 'business', 'For growing shops with a wider range.', 4500, 50, 4, 2048, 2],
        ['Pro', 'pro', 'For established shops with a large active range.', 8000, 100, 8, 5120, 3],
    ];
    $st = $pdo->prepare('INSERT INTO subscription_plans(name, slug, description, price_cents, currency, billing_period_months, setup_fee_cents, active_product_limit, stored_product_limit, staff_limit, storage_mb, duration_options, status, position, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $features = [
        'starter' => ['import' => '1', 'feeds' => '1', 'marketplace_feeds' => '0', 'custom_feeds' => '0', 'popups' => '1', 'seo_tools' => '1', 'typography' => '0'],
        'business' => ['import' => '1', 'feeds' => '1', 'marketplace_feeds' => '1', 'custom_feeds' => '0', 'popups' => '1', 'seo_tools' => '1', 'typography' => '1'],
        'pro' => ['import' => '1', 'feeds' => '1', 'marketplace_feeds' => '1', 'custom_feeds' => '1', 'popups' => '1', 'seo_tools' => '1', 'typography' => '1'],
    ];
    $fs = $pdo->prepare('INSERT INTO plan_features(plan_id, feature, value) VALUES (?,?,?)');
    foreach ($plans as $p) {
        $st->execute([$p[0], $p[1], $p[2], $p[3], 'EUR', 1, 15000, $p[4], 10000, $p[5], $p[6], '1,3,6,12', 'active', $p[7], $now]);
        $id = (int) $pdo->lastInsertId();
        foreach ($features[$p[1]] as $k => $v) $fs->execute([$id, $k, $v]);
    }

    $tp = $pdo->prepare('INSERT INTO templates(key, name, style, description, status, position) VALUES (?,?,?,?,?,?)');
    $i = 0;
    foreach (template_registry() as $key => $t) $tp->execute([$key, $t['name'], $t['style'], $t['description'], 'active', ++$i]);

    $roles = [['super_admin', 'Super Admin', 'platform', 100], ['owner', 'Owner', 'shop', 40], ['administrator', 'Administrator', 'shop', 30], ['product_manager', 'Product Manager', 'shop', 20], ['editor', 'Editor', 'shop', 20]];
    $rs = $pdo->prepare('INSERT INTO roles(key, name, scope, tier) VALUES (?,?,?,?)');
    foreach ($roles as $r) $rs->execute($r);
    $ps = $pdo->prepare('INSERT INTO permissions(key, label, position) VALUES (?,?,?)');
    $i = 0;
    foreach (shop_permissions() as $k => $label) $ps->execute([$k, $label, ++$i]);
    $presets = [
        'owner' => array_keys(shop_permissions()),
        'administrator' => array_keys(shop_permissions()),
        'product_manager' => ['dashboard', 'products', 'categories', 'media', 'feeds', 'orders'],
        'editor' => ['dashboard', 'pages', 'media', 'seo', 'popups', 'appearance'],
    ];
    $rp = $pdo->prepare('INSERT INTO role_permissions(role_key, permission_key) VALUES (?,?)');
    foreach ($presets as $role => $perms) foreach ($perms as $perm) $rp->execute([$role, $perm]);

    $pp = $pdo->prepare('INSERT INTO payment_providers(key, name, enabled, position) VALUES (?,?,?,?)');
    foreach ([['manual', 'Manual (recorded by Super Admin)', 1], ['bank_transfer', 'Bank transfer', 1], ['stripe', 'Stripe (not configured)', 0], ['viva_wallet', 'Viva Wallet (not configured)', 0]] as $i => $p) $pp->execute([$p[0], $p[1], $p[2], $i]);

    $defaults = [
        'platform_name' => 'ShopRent', 'expiry_warning_days' => '10,3,1', 'lock_grace_days' => '0',
        'expiring_window_days' => '10', 'force_https' => '0', 'canonical_host' => '', 'platform_hosts' => '',
        'notify_email' => '', 'mail_from' => '', 'ga4_id' => '', 'cron_token' => bin2hex(random_bytes(16)),
        'hero_title' => 'Your own online store, ready in days — rented, not built.',
        'hero_subtitle' => 'Pick one of 10 store designs, bring your domain and your products. We host it, keep it secure and fast, and you sell.',
        'contact_email' => '', 'contact_phone' => '', 'company_name' => '', 'company_address' => '',
        'server_ip' => '', 'avif_enabled' => '0', 'default_locale' => 'el',
    ];
    $sd = $pdo->prepare('INSERT OR IGNORE INTO platform_settings(key, value) VALUES (?,?)');
    foreach ($defaults as $k => $v) $sd->execute([$k, $v]);
}
