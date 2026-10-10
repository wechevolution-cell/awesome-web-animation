<?php
declare(strict_types=1);

/** Storefront URL helper relative to the current shop base (custom domain: '', platform: /s/slug). */
function su(string $path = '/'): string { return ($GLOBALS['STORE']['base'] ?? '') . '/' . ltrim($path, '/'); }
function store(): array { return $GLOBALS['STORE']; }
function store_abs(string $path = '/'): string { return origin() . su($path); }

function store_lock_page(array $shop): never {
    $GLOBALS['LOCALE'] = $shop['locale'];
    header('Retry-After: 86400');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    render('store/lock', ['shop' => $shop], 503);
}

function store_dispatch(array $shop, string $base, string $path): void {
    $GLOBALS['LOCALE'] = $shop['locale'];
    // Server-side access check #1: shop status. Anything but ACTIVE/EXPIRING → lock page (no data, no endpoints).
    $previewLocked = false;
    if (!shop_is_open($shop)) {
        if (is_super() && ($shop['status'] !== 'DELETED')) $previewLocked = true;
        else store_lock_page($shop);
    }
    $sid = (int) $shop['id'];
    $tplKey = $shop['template_key'];
    $canPreview = is_super() || (current_user() && (int) current_user()['shop_id'] === $sid);
    if ($canPreview && isset($_GET['preview_template']) && isset(template_registry()[$_GET['preview_template']])) $_SESSION['preview_tpl'][$sid] = $_GET['preview_template'];
    if ($canPreview && isset($_GET['preview_template']) && $_GET['preview_template'] === 'off') unset($_SESSION['preview_tpl'][$sid]);
    $previewTpl = $canPreview ? ($_SESSION['preview_tpl'][$sid] ?? null) : null;
    if ($previewTpl) $tplKey = $previewTpl;
    $GLOBALS['STORE'] = ['shop' => $shop, 'base' => $base, 'tpl' => template_get($tplKey), 'preview_tpl' => $previewTpl, 'preview_locked' => $previewLocked, 'seo' => seo_settings($sid)];
    $GLOBALS['ERROR_RENDERER'] = static function (int $code, Throwable $t) use ($shop): void {
        if ($code === 404) not_found_log((int) $shop['id'], request_path());
        store_render('store/error', ['code' => $code, 'message' => $code === 403 ? 'Access denied.' : ''], ['title' => $code === 404 ? t('page_not_found') : 'Error', 'robots' => 'noindex'], $code);
    };
    security_headers();
    if ($previewTpl || $previewLocked) header('X-Robots-Tag: noindex');

    $routes = [
        ['GET', '/', static fn() => store_home($shop)],
        ['GET', '/products/', static fn() => store_listing($shop, null)],
        ['GET', '/search/', static fn() => store_listing($shop, null, true)],
        ['GET', '/product/(?<slug>[a-z0-9\-]+)/', static fn($p) => store_product($shop, $p['slug'])],
        ['GET', '/cart/', static fn() => store_cart($shop)],
        ['POST', '/cart/add/', static fn() => store_cart_add($shop)],
        ['POST', '/cart/update/', static fn() => store_cart_update($shop)],
        ['GET', '/api/cart/', static fn() => json_out(store_cart_json($shop))],
        ['GET', '/checkout/', static fn() => store_checkout($shop)],
        ['POST', '/checkout/', static fn() => store_checkout_post($shop)],
        ['GET', '/order/(?<num>\d+)/', static fn($p) => store_order_success($shop, $p['num'])],
        ['GET', '/wishlist/', static fn() => store_wishlist($shop)],
        ['POST', '/wishlist/toggle/', static fn() => store_wishlist_toggle($shop)],
        ['ANY', '/login/', static fn() => store_login($shop)],
        ['ANY', '/register/', static fn() => store_register($shop)],
        ['POST', '/logout/', static function () use ($shop) { unset($_SESSION['customer'][$shop['id']]); redirect(su('/')); }],
        ['ANY', '/forgot-password/', static fn() => store_forgot($shop)],
        ['ANY', '/reset-password/', static fn() => store_reset($shop)],
        ['GET', '/account/', static fn() => store_account($shop)],
        ['GET', '/account/orders/(?<num>\d+)/', static fn($p) => store_account_order($shop, $p['num'])],
        ['POST', '/contact/send/', static fn() => store_contact_post($shop)],
        ['POST', '/popup/subscribe/', static fn() => store_subscribe($shop)],
        ['GET', '/unsubscribe/(?<tok>[A-Za-z0-9_\-]{10,40})/', static fn($p) => store_unsubscribe($shop, $p['tok'])],
        ['GET', '/sitemap.xml', static fn() => store_sitemap($shop)],
        ['GET', '/robots.txt', static function () use ($shop) { $s = store()['seo']; header('Content-Type: text/plain; charset=utf-8'); echo robots_txt(store_abs('/sitemap.xml'), (string) $s['robots_extra'], (bool) $s['noindex_site'], store()['base']); exit; }],
        ['GET', '/llms.txt', static fn() => store_llms($shop)],
        ['GET', '/feed/rss.xml', static function () use ($shop) { header('Content-Type: application/rss+xml; charset=utf-8'); echo rss_products($shop, store_abs('/')); exit; }],
        ['GET', '/feed/products.xml', static fn() => store_feed($shop, 'products')],
        ['GET', '/feed/(?<slug>[a-z0-9\-]+)\.xml', static fn($p) => store_feed($shop, $p['slug'])],
        ['GET', '/img/ph/(?<seed>[a-z0-9\-]{1,60})\.svg', static function ($p) { header('Content-Type: image/svg+xml'); header('Cache-Control: public, max-age=31536000, immutable'); echo placeholder_svg($p['seed'], (string) ($_GET['l'] ?? '')); exit; }],
    ];
    if (dispatch($routes, $path)) return;

    // CMS page or category path (/category/ or /category/subcategory/).
    if (preg_match('#^/([a-z0-9\-]+(?:/[a-z0-9\-]+)*)/$#', $path, $m)) {
        $slug = $m[1];
        if (!str_contains($slug, '/')) {
            $page = row("SELECT * FROM pages WHERE shop_id = ? AND slug = ? AND status = 'published' AND deleted_at IS NULL", [$sid, $slug]);
            if ($page) { store_page($shop, $page); return; }
        }
        $cat = row('SELECT * FROM categories WHERE shop_id = ? AND path = ?', [$sid, $slug]);
        if ($cat) { store_listing($shop, $cat); return; }
    }
    $r = redirect_match($sid, $path);
    if ($r) {
        [$code, $target] = $r;
        if (in_array($code, [410, 451], true)) store_render('store/error', ['code' => $code, 'message' => t('gone')], ['title' => t('gone'), 'robots' => 'noindex'], $code);
        redirect(preg_match('#^https?://#', (string) $target) ? $target : su((string) $target), $code);
    }
    abort(404);
}

/** Render a storefront view inside the template layout. */
function store_render(string $view, array $vars, array $meta, int $code = 200): never {
    $S = store();
    $shop = $S['shop'];
    $seo = $S['seo'];
    $siteTitle = $seo['site_title'] ?: $shop['name'];
    $meta['title'] = isset($meta['title']) && $meta['title'] !== $siteTitle ? $meta['title'] . ($seo['title_separator'] ?? ' | ') . $siteTitle : $siteTitle;
    if (!empty($meta['title_raw'])) $meta['title'] = $meta['title_raw'];
    $meta += ['description' => $seo['meta_description'] ?: shop_setting((int) $shop['id'], 'tagline', ''), 'canonical' => store_abs(request_path_rel()), 'locale' => $shop['locale'], 'site_name' => $siteTitle, 'gsc' => $seo['gsc_verification']];
    if (empty($meta['image']) && $seo['og_image_id']) $meta['image'] = abs_url(media_url(media_by_id((int) $seo['og_image_id'], (int) $shop['id']), 1200));
    if ($seo['noindex_site'] || $S['preview_tpl'] || $S['preview_locked']) $meta['robots'] = 'noindex,nofollow';
    $content = view($view, $vars + ['shop' => $shop]);
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo view('store/layout', ['content' => $content, 'meta' => $meta, 'shop' => $shop, 'graph' => $vars['graph'] ?? [], 'bodyClass' => $vars['bodyClass'] ?? '']);
    exit;
}
function request_path_rel(): string {
    $p = request_path();
    $b = store()['base'];
    return $b !== '' && str_starts_with($p, $b) ? (substr($p, strlen($b)) ?: '/') : $p;
}

/** Base JSON-LD nodes present on every storefront page. */
function store_graph_base(array $shop): array {
    $sid = (int) $shop['id'];
    $seo = store()['seo'];
    $home = store_abs('/');
    $orgId = $home . '#organization';
    $logo = media_by_id((int) shop_setting($sid, 'logo_id', 0), $sid);
    $same = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $seo['same_as']))));
    $org = ['@type' => in_array($seo['organization_type'], ['Organization', 'Store', 'OnlineStore', 'LocalBusiness'], true) ? $seo['organization_type'] : 'OnlineStore', '@id' => $orgId, 'name' => shop_setting($sid, 'company_name', $shop['name']), 'url' => $home];
    if ($logo) $org['logo'] = abs_url(media_url($logo));
    if ($v = shop_setting($sid, 'contact_email')) $org['email'] = $v;
    if ($v = shop_setting($sid, 'contact_phone')) $org['telephone'] = $v;
    if ($v = shop_setting($sid, 'contact_address')) $org['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $v];
    if ($same) $org['sameAs'] = $same;
    $site = ['@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => $seo['site_title'] ?: $shop['name'], 'inLanguage' => $shop['locale'], 'publisher' => ['@id' => $orgId],
        'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => store_abs('/search/') . '?q={search_term_string}'], 'query-input' => 'required name=search_term_string']];
    return [$org, $site];
}
function ld_webpage(string $url, string $name, string $type = 'WebPage', array $extra = []): array {
    $home = store_abs('/');
    return ['@type' => $type, '@id' => $url . '#webpage', 'url' => $url, 'name' => $name, 'isPartOf' => ['@id' => $home . '#website'], 'inLanguage' => store()['shop']['locale']] + $extra;
}

/* ---------- Pages ---------- */
function store_home(array $shop): never {
    $sid = (int) $shop['id'];
    $sections = homepage_sections($sid);
    [$w, $pp] = visible_where($shop);
    foreach ($sections as &$s) {
        if ($s['type'] === 'featured_products') {
            $d = $s['d']; $lim = max(1, min(24, (int) ($d['limit'] ?? 8)));
            $src = $d['source'] ?? 'featured';
            $sql = "SELECT p.* FROM products p WHERE $w"; $params = $pp;
            if ($src === 'featured') $sql .= ' AND p.featured = 1';
            if ($src === 'sale') $sql .= ' AND p.sale_price_cents IS NOT NULL AND p.sale_price_cents < p.price_cents';
            if ($src === 'category' && !empty($d['category_id'])) { $ids = category_descendant_ids($sid, (int) $d['category_id']) ?: [0]; $sql .= ' AND p.id IN (SELECT product_id FROM category_relationships WHERE category_id IN (' . in_ph($ids) . '))'; $params = array_merge($params, $ids); }
            $sql .= ' ORDER BY ' . ($src === 'newest' ? 'p.activated_at DESC, p.id DESC' : 'p.featured DESC, p.activated_at DESC, p.id DESC') . ' LIMIT ' . $lim;
            $s['products'] = products_attach_images(rows($sql, $params));
            if (!$s['products'] && $src === 'featured') $s['products'] = products_attach_images(rows("SELECT p.* FROM products p WHERE $w ORDER BY p.activated_at DESC, p.id DESC LIMIT $lim", $pp));
        }
        if ($s['type'] === 'categories') {
            $lim = max(1, min(24, (int) ($s['d']['limit'] ?? 6)));
            $s['categories'] = rows('SELECT * FROM categories WHERE shop_id = ? AND parent_id IS NULL ORDER BY position, name LIMIT ' . $lim, [$sid]);
            foreach ($s['categories'] as &$c) {
                $ids = category_descendant_ids($sid, (int) $c['id']) ?: [0];
                $c['count'] = (int) val("SELECT COUNT(DISTINCT p.id) FROM products p JOIN category_relationships cr ON cr.product_id = p.id WHERE $w AND cr.category_id IN (" . in_ph($ids) . ')', array_merge($pp, $ids));
                $c['media'] = media_by_id($c['image_id'] ? (int) $c['image_id'] : null, $sid);
                if (!$c['media']) {
                    $pi = row("SELECT m.* FROM products p JOIN category_relationships cr ON cr.product_id = p.id JOIN product_images pi ON pi.product_id = p.id AND pi.position = 0 JOIN media m ON m.id = pi.media_id WHERE $w AND cr.category_id IN (" . in_ph($ids) . ') LIMIT 1', array_merge($pp, $ids));
                    $c['media'] = $pi;
                }
            }
            unset($c);
            $s['categories'] = array_values(array_filter($s['categories'], static fn($c) => $c['count'] > 0));
        }
    }
    unset($s);
    $home = store_abs('/');
    $graph = array_merge(store_graph_base($shop), [ld_webpage($home, $shop['name'])]);
    $heroImg = null;
    foreach ($sections as $s) if ($s['type'] === 'hero' && !empty($s['d']['image_id'])) $heroImg = media_by_id((int) $s['d']['image_id'], $sid);
    store_render('store/home', ['sections' => $sections, 'graph' => $graph, 'bodyClass' => 'is-home'], ['title_raw' => (store()['seo']['site_title'] ?: $shop['name']) . (($tg = shop_setting($sid, 'tagline')) ? ' — ' . $tg : ''), 'canonical' => $home, 'image' => $heroImg ? abs_url(media_url($heroImg, 1200)) : null]);
}

function store_listing(array $shop, ?array $cat, bool $isSearch = false): never {
    $sid = (int) $shop['id'];
    [$w, $pp] = visible_where($shop);
    $where = $w; $params = $pp;
    $q = clean_text(get('q'), 100);
    if ($cat) {
        $ids = category_descendant_ids($sid, (int) $cat['id']) ?: [0];
        $where .= ' AND p.id IN (SELECT product_id FROM category_relationships WHERE category_id IN (' . in_ph($ids) . '))';
        $params = array_merge($params, $ids);
    }
    if ($q !== '') {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $where .= " AND (p.name LIKE ? ESCAPE '\\' OR p.sku LIKE ? ESCAPE '\\' OR p.brand LIKE ? ESCAPE '\\' OR p.short_description LIKE ? ESCAPE '\\')";
        array_push($params, $like, $like, $like, $like);
    }
    $baseWhere = $where; $baseParams = $params;
    $min = to_cents(get('min')); $max = to_cents(get('max'));
    if ($min !== null) { $where .= ' AND (CASE WHEN p.sale_price_cents IS NOT NULL AND p.sale_price_cents < p.price_cents THEN p.sale_price_cents ELSE p.price_cents END) >= ?'; $params[] = $min; }
    if ($max !== null) { $where .= ' AND (CASE WHEN p.sale_price_cents IS NOT NULL AND p.sale_price_cents < p.price_cents THEN p.sale_price_cents ELSE p.price_cents END) <= ?'; $params[] = $max; }
    if (get('stock') === '1') $where .= ' AND (p.manage_stock = 0 OR p.stock > 0)';
    $brands = array_values(array_filter(arr($_GET['brand'] ?? []), 'is_string'));
    if ($brands) { $where .= ' AND p.brand IN (' . in_ph($brands) . ')'; $params = array_merge($params, $brands); }
    if (get('sale') === '1') $where .= ' AND p.sale_price_cents IS NOT NULL AND p.sale_price_cents < p.price_cents';
    $sort = get('sort', 'newest');
    $order = match ($sort) {
        'price_asc' => '(CASE WHEN p.sale_price_cents IS NOT NULL AND p.sale_price_cents < p.price_cents THEN p.sale_price_cents ELSE p.price_cents END) ASC',
        'price_desc' => '(CASE WHEN p.sale_price_cents IS NOT NULL AND p.sale_price_cents < p.price_cents THEN p.sale_price_cents ELSE p.price_cents END) DESC',
        'name' => 'p.name COLLATE NOCASE ASC', default => 'p.featured DESC, p.activated_at DESC, p.id DESC',
    };
    $total = (int) val("SELECT COUNT(*) FROM products p WHERE $where", $params);
    $per = 24;
    $pg = paginate($total, $per);
    $products = products_attach_images(rows("SELECT p.* FROM products p WHERE $where ORDER BY $order LIMIT ? OFFSET ?", array_merge($params, [$pg['limit'], $pg['offset']])));
    $brandList = col("SELECT DISTINCT p.brand FROM products p WHERE $baseWhere AND p.brand IS NOT NULL AND p.brand <> '' ORDER BY p.brand LIMIT 40", $baseParams);
    $subcats = $cat ? rows('SELECT * FROM categories WHERE shop_id = ? AND parent_id = ? ORDER BY position, name', [$sid, $cat['id']]) : rows('SELECT * FROM categories WHERE shop_id = ? AND parent_id IS NULL ORDER BY position, name', [$sid]);
    $crumbs = [[t('home'), store_abs('/')]];
    if ($cat) { foreach (category_ancestors($sid, $cat) as $a) $crumbs[] = [$a['name'], store_abs('/' . $a['path'] . '/')]; $crumbs[] = [$cat['name'], store_abs('/' . $cat['path'] . '/')]; }
    else $crumbs[] = [$isSearch ? t('search') : t('all_products'), store_abs($isSearch ? '/search/' : '/products/')];
    $relPath = $cat ? '/' . $cat['path'] . '/' : ($isSearch ? '/search/' : '/products/');
    $canonical = store_abs($relPath) . ($pg['page'] > 1 ? '?page=' . $pg['page'] : '');
    $title = $cat ? ($cat['seo_title'] ?: $cat['name']) : ($isSearch ? ($q !== '' ? t('results_for', ['q' => $q]) : t('search')) : t('all_products'));
    $faq = $cat ? jdec($cat['faq']) : [];
    $graph = store_graph_base($shop);
    $graph[] = ld_webpage($canonical, $title, 'CollectionPage');
    $graph[] = ld_breadcrumbs($crumbs);
    if ($products) $graph[] = ['@type' => 'ItemList', '@id' => $canonical . '#itemlist', 'numberOfItems' => count($products), 'itemListElement' => array_map(static fn($p, $i) => ['@type' => 'ListItem', 'position' => $pg['offset'] + $i + 1, 'url' => store_abs('/product/' . $p['slug'] . '/'), 'name' => $p['name']], $products, array_keys($products))];
    if ($faq) $graph[] = ld_faq($faq, $canonical);
    $filtered = $min !== null || $max !== null || get('stock') === '1' || $brands || get('sale') === '1' || $sort !== 'newest';
    store_render('store/listing', compact('cat', 'products', 'pg', 'total', 'subcats', 'brandList', 'brands', 'sort', 'q', 'isSearch', 'crumbs', 'faq', 'graph', 'relPath') + ['bodyClass' => 'is-listing'], [
        'title' => $title . ($pg['page'] > 1 ? ' — ' . $pg['page'] : ''), 'description' => $cat ? ($cat['seo_description'] ?: str_limit((string) $cat['description'], 160)) : null,
        'canonical' => $canonical, 'robots' => ($isSearch || $filtered || ($cat['robots'] ?? null)) ? ($cat['robots'] ?? 'noindex,follow') : null,
    ]);
}

function store_product(array $shop, string $slug): never {
    $sid = (int) $shop['id'];
    [$w, $pp] = visible_where($shop);
    // Server-side access check #2: product must be ACTIVE (and within quota) or it is a 404.
    $p = row("SELECT p.* FROM products p WHERE p.slug = ? AND $w", array_merge([$slug], $pp));
    if (!$p) {
        $r = redirect_match($sid, '/product/' . $slug . '/');
        if ($r && $r[1]) redirect(su($r[1]), $r[0]);
        abort(404);
    }
    $p = products_attach_images([$p])[0];
    $variants = product_variants((int) $p['id']);
    $attrs = product_attributes((int) $p['id']);
    $faq = jdec($p['faq']);
    $cat = $p['primary_category_id'] ? row('SELECT * FROM categories WHERE id = ? AND shop_id = ?', [$p['primary_category_id'], $sid]) : null;
    $related = [];
    if ($cat) {
        $ids = category_descendant_ids($sid, (int) $cat['id']) ?: [0];
        $related = products_attach_images(rows("SELECT p.* FROM products p WHERE $w AND p.id <> ? AND p.id IN (SELECT product_id FROM category_relationships WHERE category_id IN (" . in_ph($ids) . ')) ORDER BY p.featured DESC, RANDOM() LIMIT 4', array_merge($pp, [$p['id']], $ids)));
    }
    if (count($related) < 4) {
        $have = array_merge([$p['id']], array_column($related, 'id'));
        $related = array_merge($related, products_attach_images(rows("SELECT p.* FROM products p WHERE $w AND p.id NOT IN (" . in_ph($have) . ') ORDER BY p.featured DESC, p.activated_at DESC LIMIT ' . (4 - count($related)), array_merge($pp, $have))));
    }
    $crumbs = [[t('home'), store_abs('/')]];
    if ($cat) { foreach (category_ancestors($sid, $cat) as $a) $crumbs[] = [$a['name'], store_abs('/' . $a['path'] . '/')]; $crumbs[] = [$cat['name'], store_abs('/' . $cat['path'] . '/')]; }
    $url = store_abs('/product/' . $p['slug'] . '/');
    $crumbs[] = [$p['name'], $url];
    $images = array_map(static fn($m) => abs_url(media_url($m, 1200)), $p['images']);
    $graph = store_graph_base($shop);
    $graph[] = ld_webpage($url, $p['name'], 'ItemPage', ['mainEntity' => ['@id' => $url . '#product']]);
    $graph[] = ld_breadcrumbs($crumbs);
    $graph[] = ld_product($shop, $p, $url, $images, store_abs('/') . '#organization', $variants);
    if ($faq) $graph[] = ld_faq($faq, $url);
    $recent = arr($_SESSION['recent'][$sid] ?? []);
    $recent = array_slice(array_values(array_unique(array_merge([(int) $p['id']], $recent))), 0, 8);
    $_SESSION['recent'][$sid] = $recent;
    $inWish = in_array((int) $p['id'], arr($_SESSION['wishlist'][$sid] ?? []), true);
    store_render('store/product', compact('p', 'variants', 'attrs', 'faq', 'cat', 'related', 'crumbs', 'graph', 'inWish') + ['bodyClass' => 'is-product'], [
        'title' => $p['seo_title'] ?: $p['name'], 'description' => $p['seo_description'] ?: str_limit((string) ($p['short_description'] ?: $p['description']), 160),
        'canonical' => $p['canonical'] ?: $url, 'robots' => $p['robots'] ?: null, 'type' => 'product', 'image' => $images[0] ?? null,
    ]);
}

function store_page(array $shop, array $page): never {
    $url = store_abs('/' . $page['slug'] . '/');
    $faq = jdec($page['faq']);
    $crumbs = [[t('home'), store_abs('/')], [$page['title'], $url]];
    $graph = store_graph_base($shop);
    $graph[] = ld_webpage($url, $page['title'], $page['kind'] === 'contact' ? 'ContactPage' : ($page['slug'] === 'about' ? 'AboutPage' : 'WebPage'));
    $graph[] = ld_breadcrumbs($crumbs);
    if ($faq) $graph[] = ld_faq($faq, $url);
    store_render('store/page', compact('page', 'faq', 'crumbs', 'graph'), ['title' => $page['seo_title'] ?: $page['title'], 'description' => $page['seo_description'] ?: str_limit((string) $page['content'], 160), 'canonical' => $url, 'robots' => $page['robots'] ?: null]);
}

/* ---------- Cart ---------- */
function store_cart_json(array $shop): array {
    $c = cart_summary($shop);
    return ['ok' => true, 'count' => $c['count'], 'subtotal' => money($c['subtotal'], $shop['locale']), 'lines' => array_map(static fn($l) => [
        'key' => $l['key'], 'name' => $l['product']['name'], 'variant' => $l['variant'] ? variant_label(jdec($l['variant']['options'])) : '', 'qty' => $l['qty'],
        'price' => money($l['price'], $shop['locale']), 'total' => money($l['total'], $shop['locale']), 'url' => su('/product/' . $l['product']['slug'] . '/'),
        'image' => $l['product']['image'] ? media_url($l['product']['image'], 400) : su('/img/ph/' . $l['product']['slug'] . '.svg?l=' . rawurlencode(mb_substr($l['product']['name'], 0, 1))),
    ], $c['lines'])];
}
function store_cart(array $shop): never {
    $cart = cart_summary($shop);
    if ($cart['removed']) flash('warn', t('out_of_stock_cart'));
    $ship = shipping_options($shop, $cart['subtotal']);
    store_render('store/cart', ['cart' => $cart, 'ship' => $ship, 'bodyClass' => 'is-cart'], ['title' => t('cart'), 'robots' => 'noindex,follow']);
}
function store_cart_add(array $shop): never {
    try {
        cart_add($shop, int_in('product_id'), int_in('variant_id'), max(1, int_in('qty', 1)));
        if (wants_json()) json_out(store_cart_json($shop) + ['message' => t('added_to_cart')]);
        flash('ok', t('added_to_cart'));
        redirect(su('/cart/'));
    } catch (UserError $e) {
        if (wants_json()) json_out(['ok' => false, 'error' => $e->getMessage()], 422);
        flash('error', $e->getMessage());
        back(su('/'));
    }
}
function store_cart_update(array $shop): never {
    foreach (arr($_POST['qty'] ?? []) as $k => $q) if (preg_match('/^\d+:\d+$/', (string) $k)) cart_update($shop, (string) $k, (int) $q);
    if ($rm = (string) post('remove')) cart_update($shop, $rm, 0);
    if (wants_json()) json_out(store_cart_json($shop));
    redirect(su('/cart/'));
}

function store_checkout(array $shop, array $errors = []): never {
    $cart = cart_summary($shop);
    if (!$cart['lines']) { flash('info', t('empty_cart')); redirect(su('/cart/')); }
    $customer = customer_current((int) $shop['id']);
    $ship = shipping_options($shop, $cart['subtotal']);
    $pay = payment_options($shop);
    $errors = $errors ?: arr($_SESSION['_errors'] ?? []); unset($_SESSION['_errors']);
    store_render('store/checkout', compact('cart', 'customer', 'ship', 'pay', 'errors') + ['bodyClass' => 'is-checkout'], ['title' => t('checkout'), 'robots' => 'noindex,nofollow']);
}
function store_checkout_post(array $shop): never {
    if (!rate_limit('checkout_' . $shop['id'], 20, 600)) { flash('error', 'Too many attempts. Please wait a few minutes.'); redirect(su('/checkout/')); }
    try {
        $order = order_place($shop, $_POST, customer_current((int) $shop['id']));
        clear_old();
        $_SESSION['last_order'][$shop['id']] = $order['access_token'];
        redirect(su('/order/' . $order['number'] . '/') . '?k=' . urlencode($order['access_token']));
    } catch (ValidationError $e) {
        remember_input();
        $_SESSION['_errors'] = $e->errors;
        flash('error', t('fix_errors'));
        redirect(su('/checkout/'));
    } catch (UserError $e) {
        remember_input();
        flash('error', $e->getMessage());
        redirect(su('/checkout/'));
    }
}
function store_order_success(array $shop, string $num): never {
    $o = row('SELECT * FROM orders WHERE shop_id = ? AND number = ?', [$shop['id'], $num]);
    if (!$o || !hash_equals($o['access_token'], (string) get('k'))) abort(404);
    $items = rows('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    store_render('store/order-success', ['order' => $o, 'items' => $items], ['title' => t('order_received'), 'robots' => 'noindex,nofollow']);
}

/* ---------- Wishlist (session; works without JS) ---------- */
function store_wishlist(array $shop): never {
    $sid = (int) $shop['id'];
    $ids = array_map('intval', arr($_SESSION['wishlist'][$sid] ?? []));
    $products = [];
    if ($ids) { [$w, $pp] = visible_where($shop); $products = products_attach_images(rows("SELECT p.* FROM products p WHERE $w AND p.id IN (" . in_ph($ids) . ')', array_merge($pp, $ids))); }
    store_render('store/wishlist', ['products' => $products], ['title' => t('wishlist'), 'robots' => 'noindex,follow']);
}
function store_wishlist_toggle(array $shop): never {
    $sid = (int) $shop['id'];
    $pid = int_in('product_id');
    $list = array_map('intval', arr($_SESSION['wishlist'][$sid] ?? []));
    $in = in_array($pid, $list, true);
    if ($in) $list = array_values(array_diff($list, [$pid]));
    elseif (cart_resolve_line($shop, $pid, 0)) $list[] = $pid;
    $_SESSION['wishlist'][$sid] = array_slice($list, -100);
    if (wants_json()) json_out(['ok' => true, 'in' => !$in, 'count' => count($_SESSION['wishlist'][$sid])]);
    back(su('/wishlist/'));
}

/* ---------- Accounts ---------- */
function store_login(array $shop): never {
    $sid = (int) $shop['id'];
    if (is_post()) {
        try {
            if (customer_login($sid, (string) post('email'), (string) ($_POST['password'] ?? ''))) redirect(su('/account/'));
            flash('error', t('invalid_login'));
        } catch (UserError $e) { flash('error', $e->getMessage()); }
        remember_input();
        redirect(su('/login/'));
    }
    store_render('store/auth', ['mode' => 'login'], ['title' => t('login'), 'robots' => 'noindex']);
}
function store_register(array $shop): never {
    $sid = (int) $shop['id'];
    if (is_post()) {
        $email = mb_strtolower(clean_text(post('email'), 190)); $pw = (string) ($_POST['password'] ?? '');
        $err = null;
        if (spam_check()) $err = t('fix_errors');
        elseif (!rate_limit('register_' . $sid, 10, 3600)) $err = 'Too many attempts.';
        elseif (!valid_email($email) || clean_text(post('name')) === '') $err = t('fix_errors');
        elseif ($pe = password_policy($pw)) $err = $pe;
        elseif (empty($_POST['consent'])) $err = t('fix_errors');
        if (!$err) {
            $ex = row('SELECT * FROM customers WHERE shop_id = ? AND email = ?', [$sid, $email]);
            if ($ex && $ex['password_hash']) $err = t('account_exists');
            else {
                $data = ['name' => clean_text(post('name'), 120), 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'updated_at' => now()];
                $cid = $ex ? (int) $ex['id'] : insert('customers', $data + ['shop_id' => $sid, 'email' => $email, 'created_at' => now()]);
                if ($ex) update('customers', $data, 'id = ?', [$cid]);
                session_regenerate_id(true);
                $_SESSION['customer'][$sid] = $cid;
                flash('ok', t('welcome'));
                redirect(su('/account/'));
            }
        }
        flash('error', $err); remember_input(); redirect(su('/register/'));
    }
    store_render('store/auth', ['mode' => 'register'], ['title' => t('register'), 'robots' => 'noindex']);
}
function store_forgot(array $shop): never {
    $sid = (int) $shop['id'];
    if (is_post()) {
        if (rate_limit('forgot_' . $sid, 5, 900)) {
            $c = row('SELECT * FROM customers WHERE shop_id = ? AND email = ? AND password_hash IS NOT NULL', [$sid, mb_strtolower(clean_text(post('email'), 190))]);
            if ($c) {
                $tok = password_token_create('customer', (int) $c['id']);
                send_mail($c['email'], $shop['name'] . ' — ' . t('reset_password'), store_abs('/reset-password/') . '?token=' . urlencode($tok));
            }
        }
        flash('ok', t('reset_sent'));
        redirect(su('/login/'));
    }
    store_render('store/auth', ['mode' => 'forgot'], ['title' => t('reset_password'), 'robots' => 'noindex']);
}
function store_reset(array $shop): never {
    $tok = (string) input('token', '');
    $t = $tok !== '' ? password_token_find('customer', $tok) : null;
    $c = $t ? row('SELECT * FROM customers WHERE id = ? AND shop_id = ?', [$t['subject_id'], $shop['id']]) : null;
    if (!$c) abort(404);
    if (is_post()) {
        $pw = (string) ($_POST['password'] ?? '');
        if ($pe = password_policy($pw)) { flash('error', $pe); redirect(su('/reset-password/') . '?token=' . urlencode($tok)); }
        update('customers', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'failed_attempts' => 0, 'locked_until' => null], 'id = ?', [$c['id']]);
        update('password_tokens', ['used_at' => now()], 'id = ?', [$t['id']]);
        flash('ok', t('saved'));
        redirect(su('/login/'));
    }
    store_render('store/auth', ['mode' => 'reset', 'token' => $tok], ['title' => t('reset_password'), 'robots' => 'noindex']);
}
function store_account(array $shop): never {
    $c = customer_current((int) $shop['id']);
    if (!$c) redirect(su('/login/'));
    $orders = rows('SELECT * FROM orders WHERE shop_id = ? AND customer_id = ? ORDER BY id DESC LIMIT 50', [$shop['id'], $c['id']]);
    store_render('store/account', ['customer' => $c, 'orders' => $orders], ['title' => t('account'), 'robots' => 'noindex']);
}
function store_account_order(array $shop, string $num): never {
    $c = customer_current((int) $shop['id']);
    if (!$c) redirect(su('/login/'));
    $o = row('SELECT * FROM orders WHERE shop_id = ? AND number = ? AND customer_id = ?', [$shop['id'], $num, $c['id']]);
    if (!$o) abort(404);
    store_render('store/order-success', ['order' => $o, 'items' => rows('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]), 'accountView' => true], ['title' => t('order') . ' #' . $o['number'], 'robots' => 'noindex']);
}

/* ---------- Forms ---------- */
function store_contact_post(array $shop): never {
    $sid = (int) $shop['id'];
    $back = su((string) val("SELECT slug FROM pages WHERE shop_id = ? AND kind = 'contact' AND deleted_at IS NULL LIMIT 1", [$sid]) ?: 'contact') . '/';
    $back = str_replace('//', '/', $back);
    $name = clean_text(post('name'), 120); $email = mb_strtolower(clean_text(post('email'), 190)); $msg = clean_multiline(post('message'), 5000);
    if (spam_check()) { flash('ok', t('message_sent')); redirect($back); } // silently drop bots
    if (!rate_limit('contact_' . $sid, 5, 3600)) { flash('error', 'Too many messages. Please try later.'); redirect($back); }
    if ($name === '' || !valid_email($email) || mb_strlen($msg) < 5 || empty($_POST['consent'])) { remember_input(); flash('error', t('fix_errors')); redirect($back); }
    insert('contact_messages', ['shop_id' => $sid, 'name' => $name, 'email' => $email, 'phone' => clean_text(post('phone'), 40), 'subject' => clean_text(post('subject'), 160), 'message' => $msg, 'consent' => 1, 'ip_hash' => ip_hash(), 'created_at' => now()]);
    $to = (string) shop_setting($sid, 'contact_email', $shop['email']);
    if ($to) send_mail($to, '[' . $shop['name'] . '] Contact: ' . $name, "$name <$email>\n\n$msg", $email);
    notify('message.new', "New message from $name", str_limit($msg, 200), $sid, 'shop');
    clear_old();
    flash('ok', t('message_sent'));
    redirect($back);
}
function store_subscribe(array $shop): never {
    $sid = (int) $shop['id'];
    $email = mb_strtolower(clean_text(post('email'), 190));
    $popup = ($pid = int_in('popup_id')) ? row('SELECT * FROM popups WHERE id = ? AND shop_id = ?', [$pid, $sid]) : null;
    $needConsent = $popup ? (bool) $popup['consent_required'] : true;
    $ok = !spam_check() && rate_limit('subscribe_' . $sid, 6, 3600) && valid_email($email) && (!$needConsent || !empty($_POST['consent']));
    if ($ok) subscriber_add($sid, $email, !empty($_POST['consent']), $popup['consent_text'] ?? t('consent_privacy'), $popup ? 'popup' : (string) (post('source') ?: 'newsletter'), $popup ? (int) $popup['id'] : null);
    if (wants_json()) json_out($ok ? ['ok' => true, 'message' => t('subscribed')] : ['ok' => false, 'error' => t('fix_errors')], $ok ? 200 : 422);
    flash($ok ? 'ok' : 'error', $ok ? t('subscribed') : t('fix_errors'));
    back(su('/'));
}
function store_unsubscribe(array $shop, string $tok): never {
    $s = row('SELECT * FROM popup_submissions WHERE shop_id = ? AND token = ?', [$shop['id'], $tok]);
    if (!$s) abort(404);
    update('popup_submissions', ['unsubscribed_at' => now()], 'id = ?', [$s['id']]);
    store_render('store/message', ['title' => t('unsubscribed'), 'text' => $s['email']], ['title' => t('unsubscribed'), 'robots' => 'noindex']);
}

/* ---------- Machine-readable ---------- */
function store_sitemap(array $shop): never {
    $sid = (int) $shop['id'];
    [$w, $pp] = visible_where($shop);
    header('Content-Type: application/xml; charset=utf-8');
    $x = new XMLWriter(); $x->openMemory(); $x->setIndent(true); $x->startDocument('1.0', 'UTF-8');
    $x->startElement('urlset'); $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9'); $x->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
    $url = static function (string $loc, ?string $mod = null, array $imgs = []) use ($x) {
        $x->startElement('url'); $x->writeElement('loc', $loc); if ($mod) $x->writeElement('lastmod', date('Y-m-d', strtotime($mod)));
        foreach (array_slice($imgs, 0, 10) as $im) { $x->startElement('image:image'); $x->writeElement('image:loc', $im); $x->endElement(); }
        $x->endElement();
    };
    $url(store_abs('/')); $url(store_abs('/products/'));
    foreach (rows('SELECT path, updated_at, robots FROM categories WHERE shop_id = ? ORDER BY path', [$sid]) as $c) if (!$c['robots']) $url(store_abs('/' . $c['path'] . '/'), $c['updated_at']);
    $last = 0;
    do {
        $chunk = products_attach_images(rows("SELECT p.* FROM products p WHERE $w AND p.id > ? AND (p.robots IS NULL OR p.robots = '') ORDER BY p.id LIMIT 500", array_merge($pp, [$last])));
        foreach ($chunk as $p) { $url(store_abs('/product/' . $p['slug'] . '/'), $p['updated_at'] ?: $p['created_at'], array_map(static fn($m) => abs_url(media_url($m)), $p['images'])); $last = (int) $p['id']; }
    } while (count($chunk) === 500);
    foreach (rows("SELECT slug, updated_at FROM pages WHERE shop_id = ? AND status = 'published' AND deleted_at IS NULL AND (robots IS NULL OR robots = '')", [$sid]) as $pg) $url(store_abs('/' . $pg['slug'] . '/'), $pg['updated_at']);
    $x->endElement(); $x->endDocument();
    echo $x->outputMemory();
    exit;
}
function store_llms(array $shop): never {
    $sid = (int) $shop['id'];
    [$w, $pp] = visible_where($shop);
    header('Content-Type: text/plain; charset=utf-8');
    $o = '# ' . $shop['name'] . "\n\n> " . (shop_setting($sid, 'tagline') ?: store()['seo']['meta_description'] ?: 'Online store.') . "\n\n";
    $o .= "## Key pages\n- [Home](" . store_abs('/') . ")\n- [All products](" . store_abs('/products/') . ")\n";
    foreach (rows("SELECT title, slug FROM pages WHERE shop_id = ? AND status = 'published' AND deleted_at IS NULL", [$sid]) as $p) $o .= '- [' . $p['title'] . '](' . store_abs('/' . $p['slug'] . '/') . ")\n";
    $o .= "\n## Categories\n";
    foreach (categories_flat($sid) as $c) $o .= str_repeat('  ', (int) $c['depth']) . '- [' . $c['name'] . '](' . store_abs('/' . $c['path'] . '/') . ")\n";
    $o .= "\n## Products\n";
    foreach (rows("SELECT p.* FROM products p WHERE $w ORDER BY p.featured DESC, p.activated_at DESC LIMIT 100", $pp) as $p) $o .= '- [' . $p['name'] . '](' . store_abs('/product/' . $p['slug'] . '/') . '): ' . money(product_effective_price($p), $shop['locale']) . ($p['short_description'] ? ' — ' . str_limit($p['short_description'], 140) : '') . "\n";
    $o .= "\n## Machine-readable\n- Sitemap: " . store_abs('/sitemap.xml') . "\n- Product feed: " . store_abs('/feed/products.xml') . "\n- RSS: " . store_abs('/feed/rss.xml') . "\n";
    if ($extra = trim((string) store()['seo']['llms_extra'])) $o .= "\n" . $extra . "\n";
    echo $o;
    exit;
}
function store_feed(array $shop, string $slug): never {
    if ($slug === 'products') {
        if (!shop_feature($shop, 'feeds')) abort(404);
        $feed = ['id' => null, 'slug' => 'products', 'type' => 'generic', 'frequency_minutes' => 30, 'filters' => null, 'fields' => null];
    } else {
        $feed = row('SELECT * FROM feeds WHERE shop_id = ? AND slug = ? AND active = 1', [$shop['id'], $slug]);
        if (!$feed || !feed_feature_ok($shop, $feed['type'])) abort(404);
    }
    $file = feed_build($shop, $feed, store_abs('/'));
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}
