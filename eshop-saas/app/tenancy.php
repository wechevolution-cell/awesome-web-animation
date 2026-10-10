<?php
declare(strict_types=1);

/* ---------- Shop lookup ---------- */
function shop_by_id(int $id): ?array { return $id ? row('SELECT * FROM shops WHERE id = ?', [$id]) : null; }
function shop_by_slug(string $slug): ?array { return row('SELECT * FROM shops WHERE slug = ? AND deleted_at IS NULL', [$slug]); }
function shop_by_host(string $host): ?array {
    $host = host_no_port($host);
    $r = row('SELECT s.*, d.domain AS matched_domain, d.is_primary AS matched_primary FROM shop_domains d JOIN shops s ON s.id = d.shop_id WHERE d.domain = ?', [$host]);
    if ($r) return $r;
    // www <-> apex fallback so both forms resolve (then 301 to the primary one).
    $alt = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
    $r = row('SELECT s.*, d.domain AS matched_domain, 0 AS matched_primary FROM shop_domains d JOIN shops s ON s.id = d.shop_id WHERE d.domain = ?', [$alt]);
    if ($r) $r['matched_domain'] = $host;
    return $r;
}
function shop_primary_domain(int $shopId): ?string {
    return val('SELECT domain FROM shop_domains WHERE shop_id = ? ORDER BY is_primary DESC, id LIMIT 1', [$shopId]);
}
/** The shop whose storefront is served at the root of the main (platform) domain, if the Super Admin activated one. */
function main_shop(): ?array {
    $id = (int) setting('main_shop_id', '0');
    if (!$id) return null;
    $s = shop_by_id($id);
    return ($s && !$s['deleted_at']) ? $s : null;
}
function is_main_shop(array $shop): bool { return (int) setting('main_shop_id', '0') === (int) $shop['id']; }
function shop_public_url(array $shop): string {
    if (is_main_shop($shop)) return '/';
    $d = shop_primary_domain((int) $shop['id']);
    if ($d && !str_ends_with($d, '.test') && $d !== host_no_port()) return (is_https() || setting('force_https') === '1' ? 'https://' : 'http://') . $d . '/';
    return '/s/' . $shop['slug'] . '/';
}

/* ---------- Settings per shop ---------- */
function shop_setting(int $shopId, string $key, mixed $default = null): mixed {
    static $cache = [];
    if ($key === '__flush') { unset($cache[$shopId]); return null; }
    if (!isset($cache[$shopId])) {
        $cache[$shopId] = [];
        foreach (rows('SELECT key, value FROM shop_settings WHERE shop_id = ?', [$shopId]) as $r) $cache[$shopId][$r['key']] = $r['value'];
    }
    $v = $cache[$shopId][$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}
function shop_setting_set(int $shopId, string $key, ?string $value): void {
    q('INSERT INTO shop_settings(shop_id, key, value) VALUES (?,?,?) ON CONFLICT(shop_id, key) DO UPDATE SET value = excluded.value', [$shopId, $key, $value]);
    shop_setting($shopId, '__flush');
}

/* ---------- Plans, features & quotas (all data-driven) ---------- */
function plan_by_id(?int $id): ?array { return $id ? row('SELECT * FROM subscription_plans WHERE id = ?', [$id]) : null; }
function plan_features(int $planId): array {
    $out = [];
    foreach (rows('SELECT feature, value FROM plan_features WHERE plan_id = ?', [$planId]) as $r) $out[$r['feature']] = $r['value'];
    return $out;
}
function shop_feature(array $shop, string $feature): bool {
    if (empty($shop['plan_id'])) return false;
    static $cache = [];
    $cache[$shop['plan_id']] ??= plan_features((int) $shop['plan_id']);
    return ($cache[$shop['plan_id']][$feature] ?? '0') === '1';
}
function shop_active_limit(array $shop): int {
    if ($shop['active_limit_override'] !== null && $shop['active_limit_override'] !== '') return (int) $shop['active_limit_override'];
    $p = plan_by_id((int) $shop['plan_id']);
    return $p ? (int) $p['active_product_limit'] : 0;
}
function shop_stored_limit(array $shop): int {
    if ($shop['stored_limit_override'] !== null && $shop['stored_limit_override'] !== '') return (int) $shop['stored_limit_override'];
    $p = plan_by_id((int) $shop['plan_id']);
    return $p ? (int) $p['stored_product_limit'] : 10000;
}
function shop_staff_limit(array $shop): int { $p = plan_by_id((int) $shop['plan_id']); return $p ? (int) $p['staff_limit'] : 1; }
function shop_storage_limit_bytes(array $shop): int { $p = plan_by_id((int) $shop['plan_id']); return ($p ? (int) $p['storage_mb'] : 512) * 1048576; }
function product_active_count(int $shopId): int { return (int) val("SELECT COUNT(*) FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL", [$shopId]); }
function product_stored_count(int $shopId): int { return (int) val('SELECT COUNT(*) FROM products WHERE shop_id = ? AND deleted_at IS NULL', [$shopId]); }
function shop_quota(array $shop): array {
    $sid = (int) $shop['id'];
    $active = product_active_count($sid); $limit = shop_active_limit($shop); $stored = product_stored_count($sid);
    return ['active' => $active, 'limit' => $limit, 'inactive' => $stored - $active, 'stored' => $stored, 'stored_limit' => shop_stored_limit($shop),
        'available' => max(0, $limit - $active), 'over' => max(0, $active - $limit), 'pct' => $limit ? min(100, (int) round($active / $limit * 100)) : 100];
}

/* ---------- Status ---------- */
const SHOP_STATUSES = ['PENDING', 'ACTIVE', 'EXPIRING', 'EXPIRED', 'LOCKED', 'SUSPENDED', 'DELETED'];

/** Lazily applies expiry transitions so status is correct even if cron has not run yet. */
function shop_effective_status(array &$shop): string {
    if ($shop['deleted_at']) return 'DELETED';
    $st = $shop['status'];
    if (in_array($st, ['ACTIVE', 'EXPIRING', 'EXPIRED'], true) && $shop['subscription_expires']) {
        $new = subscription_status_for($shop);
        if ($new !== $st) {
            subscription_transition($shop, $new);
            $shop = shop_by_id((int) $shop['id']) ?? $shop;
            $st = $shop['status'];
        }
    }
    return $st;
}
function shop_is_open(array $shop): bool { return in_array(shop_effective_status($shop), ['ACTIVE', 'EXPIRING'], true); }
function status_badge(string $st): string {
    $cls = ['ACTIVE' => 'ok', 'EXPIRING' => 'warn', 'EXPIRED' => 'bad', 'LOCKED' => 'bad', 'SUSPENDED' => 'bad', 'PENDING' => 'muted', 'DELETED' => 'muted'][$st] ?? 'muted';
    return '<span class="badge badge-' . $cls . '">' . e($st) . '</span>';
}

/** Scoped single-row fetch: 404 if missing, 403 if it exists but belongs to another tenant. */
function owned(string $table, int $id, int $shopId, bool $withDeleted = false): array {
    static $allowed = ['products', 'categories', 'orders', 'customers', 'pages', 'media', 'popups', 'feeds', 'imports', 'users', 'menus', 'menu_items', 'redirects', 'not_found_log', 'homepage_sections', 'contact_messages', 'popup_submissions', 'product_variants'];
    if (!in_array($table, $allowed, true)) throw new LogicException('Table not allowed');
    $soft = in_array($table, ['products', 'pages', 'customers', 'users'], true) && !$withDeleted ? ' AND deleted_at IS NULL' : '';
    $r = row("SELECT * FROM $table WHERE id = ? AND shop_id = ?$soft", [$id, $shopId]);
    if ($r) return $r;
    if (val("SELECT shop_id FROM $table WHERE id = ?", [$id]) !== null) {
        audit('tenant.cross_access_denied', $table, $id, $shopId, 'denied');
        abort(403, 'Access denied.');
    }
    abort(404);
}
