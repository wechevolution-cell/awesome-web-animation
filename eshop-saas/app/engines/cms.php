<?php
declare(strict_types=1);

function store_reserved_slugs(): array {
    return ['products', 'product', 'cart', 'checkout', 'order', 'search', 'wishlist', 'account', 'login', 'register', 'logout', 'forgot-password', 'reset-password',
        'feed', 'admin', 'superadmin', 'api', 'contact', 'popup', 'unsubscribe', 'sitemap.xml', 'robots.txt', 'llms.txt', 'assets', 'uploads', 's', 'img', 'brand'];
}

function page_save(int $shopId, ?int $id, array $in): int {
    $existing = $id ? owned('pages', $id, $shopId) : null;
    $title = clean_text($in['title'] ?? '', 160);
    if ($title === '') throw new ValidationError(['title' => 'Title is required.']);
    $slug = slugify((string) ($in['slug'] ?? '') ?: $title) ?: 'page';
    if (in_array($slug, store_reserved_slugs(), true) && !($existing && $existing['slug'] === $slug)) $slug .= '-page';
    $slug = unique_slug('pages', $shopId, $slug, $id);
    if (val('SELECT 1 FROM categories WHERE shop_id = ? AND path = ?', [$shopId, $slug])) $slug = unique_slug('pages', $shopId, $slug . '-info', $id);
    $content = (string) ($in['content'] ?? '');
    $faqText = (string) ($in['faq'] ?? '');
    if (!empty($in['markdown'])) {
        $md = markdown_import((string) $in['markdown']);
        $content = $md['html'];
        if ($md['faq']) $faqText = faq_to_text($md['faq']);
        if ($md['title'] && empty($in['title_locked'])) $title = clean_text($md['title'], 160);
    }
    $data = ['title' => $title, 'slug' => $slug, 'content' => sanitize_html($content), 'status' => ($in['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        'seo_title' => clean_text($in['seo_title'] ?? '', 120) ?: null, 'seo_description' => clean_text($in['seo_description'] ?? '', 320) ?: null,
        'focus_keyword' => clean_text($in['focus_keyword'] ?? '', 120) ?: null, 'robots' => in_array($in['robots'] ?? '', ['noindex', 'noindex,nofollow'], true) ? $in['robots'] : null,
        'faq' => jenc(faq_parse($faqText)), 'updated_at' => now()];
    return tx(static function () use ($shopId, $id, $existing, $data, $in) {
        if ($existing) {
            update('pages', $data, 'id = ? AND shop_id = ?', [$id, $shopId]);
            if ($existing['slug'] !== $data['slug']) redirect_auto($shopId, '/' . $existing['slug'] . '/', '/' . $data['slug'] . '/');
            audit('page.updated', 'page', $id, $shopId);
            return $id;
        }
        $nid = insert('pages', $data + ['shop_id' => $shopId, 'kind' => ($in['kind'] ?? '') === 'contact' ? 'contact' : 'page', 'created_at' => now()]);
        audit('page.created', 'page', $nid, $shopId);
        return $nid;
    });
}

/* ---------- Menus ---------- */
function menu_items(int $shopId, string $location): array {
    $menu = row('SELECT id FROM menus WHERE shop_id = ? AND location = ?', [$shopId, $location]);
    if (!$menu) return [];
    $items = rows('SELECT * FROM menu_items WHERE menu_id = ? AND shop_id = ? ORDER BY position, id', [$menu['id'], $shopId]);
    $children = [];
    foreach ($items as $i) $children[(int) $i['parent_id']][] = $i;
    $tree = [];
    foreach ($children[0] ?? [] as $i) { $i['children'] = $children[(int) $i['id']] ?? []; $tree[] = $i; }
    return $tree;
}
/** Resolve a menu item to a storefront href (relative to the shop base). Returns null if target is hidden. */
function menu_href(array $shop, array $item, string $base): ?string {
    $sid = (int) $shop['id'];
    switch ($item['type']) {
        case 'products': return $base . '/products/';
        case 'home': return $base . '/';
        case 'category': $p = val('SELECT path FROM categories WHERE id = ? AND shop_id = ?', [$item['ref_id'], $sid]); return $p ? $base . '/' . $p . '/' : null;
        case 'page': $s = val("SELECT slug FROM pages WHERE id = ? AND shop_id = ? AND status = 'published' AND deleted_at IS NULL", [$item['ref_id'], $sid]); return $s ? $base . '/' . $s . '/' : null;
        case 'product':
            [$w, $pp] = visible_where($shop);
            $s = val("SELECT p.slug FROM products p WHERE p.id = ? AND $w", array_merge([$item['ref_id']], $pp));
            return $s ? $base . '/product/' . $s . '/' : null;
        case 'url': return safe_url((string) $item['url']) ? (string) $item['url'] : null;
    }
    return null;
}

/* ---------- Homepage sections ---------- */
function homepage_section_types(): array {
    return ['hero' => 'Hero', 'categories' => 'Categories', 'featured_products' => 'Featured products', 'banner' => 'Promotional banner', 'brands' => 'Brands', 'benefits' => 'Benefits / value propositions', 'newsletter' => 'Newsletter', 'rich_text' => 'Rich text'];
}
function homepage_sections(int $shopId, bool $onlyEnabled = true): array {
    return array_map(static fn($s) => $s + ['d' => jdec($s['data'])], rows('SELECT * FROM homepage_sections WHERE shop_id = ?' . ($onlyEnabled ? ' AND enabled = 1' : '') . ' ORDER BY position, id', [$shopId]));
}
function homepage_section_save(int $shopId, int $id, array $in): void {
    $s = owned('homepage_sections', $id, $shopId);
    $media = static fn($v) => ($m = (int) $v) && val('SELECT 1 FROM media WHERE id = ? AND shop_id = ?', [$m, $shopId]) ? $m : null;
    $d = match ($s['type']) {
        'hero' => ['title' => clean_text($in['title'] ?? '', 160), 'subtitle' => clean_text($in['subtitle'] ?? '', 400), 'eyebrow' => clean_text($in['eyebrow'] ?? '', 80), 'cta_text' => clean_text($in['cta_text'] ?? '', 60), 'cta_link' => clean_text($in['cta_link'] ?? '', 300), 'image_id' => $media($in['image_id'] ?? 0), 'image2_id' => $media($in['image2_id'] ?? 0)],
        'banner' => ['title' => clean_text($in['title'] ?? '', 160), 'text' => clean_text($in['text'] ?? '', 400), 'cta_text' => clean_text($in['cta_text'] ?? '', 60), 'cta_link' => clean_text($in['cta_link'] ?? '', 300), 'image_id' => $media($in['image_id'] ?? 0)],
        'categories' => ['title' => clean_text($in['title'] ?? '', 160), 'limit' => max(1, min(24, (int) ($in['limit'] ?? 6)))],
        'featured_products' => ['title' => clean_text($in['title'] ?? '', 160), 'source' => in_array($in['source'] ?? '', ['featured', 'newest', 'sale', 'category'], true) ? $in['source'] : 'featured', 'category_id' => (int) ($in['category_id'] ?? 0), 'limit' => max(1, min(24, (int) ($in['limit'] ?? 8)))],
        'brands' => ['title' => clean_text($in['title'] ?? '', 160), 'items' => array_values(array_filter(array_map(static fn($b) => clean_text($b, 60), preg_split('/\R|,/u', (string) ($in['items'] ?? '')))))],
        'benefits' => ['items' => array_values(array_filter(array_map(static fn($i) => ['icon' => in_array($in['b_icon'][$i] ?? '', ['truck', 'return', 'shield', 'chat', 'leaf', 'star', 'clock', 'box', 'sparkle'], true) ? $in['b_icon'][$i] : 'star', 'title' => clean_text($in['b_title'][$i] ?? '', 80), 'text' => clean_text($in['b_text'][$i] ?? '', 200)], array_keys(arr($in['b_title'] ?? []))), static fn($b) => $b['title'] !== ''))],
        'newsletter' => ['title' => clean_text($in['title'] ?? '', 160), 'text' => clean_text($in['text'] ?? '', 300)],
        'rich_text' => ['title' => clean_text($in['title'] ?? '', 160), 'html' => sanitize_html($in['html'] ?? '')],
        default => [],
    };
    update('homepage_sections', ['data' => jenc($d), 'enabled' => empty($in['enabled']) ? 0 : 1], 'id = ? AND shop_id = ?', [$id, $shopId]);
    audit('homepage.section_updated', 'homepage_section', $id, $shopId);
}

/* ---------- Popups ---------- */
function popup_active(int $shopId): ?array { return row('SELECT * FROM popups WHERE shop_id = ? AND active = 1 ORDER BY id DESC LIMIT 1', [$shopId]); }
function subscriber_add(int $shopId, string $email, bool $consent, string $consentText, string $source, ?int $popupId = null): void {
    $email = mb_strtolower(trim($email));
    $ex = row('SELECT * FROM popup_submissions WHERE shop_id = ? AND email = ?', [$shopId, $email]);
    if ($ex) {
        update('popup_submissions', ['unsubscribed_at' => null, 'consent' => $consent ? 1 : 0, 'consent_text' => $consentText, 'created_at' => now()], 'id = ?', [$ex['id']]);
        return;
    }
    insert('popup_submissions', ['shop_id' => $shopId, 'popup_id' => $popupId, 'email' => $email, 'consent' => $consent ? 1 : 0, 'consent_text' => mb_substr($consentText, 0, 500), 'source' => mb_substr($source, 0, 60), 'ip_hash' => ip_hash(), 'token' => random_token(18), 'created_at' => now()]);
}
