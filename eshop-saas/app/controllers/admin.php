<?php
declare(strict_types=1);

function admin_render(string $view, array $vars = [], string $title = ''): never {
    $vars['shop'] ??= shop_by_id(admin_shop_id());
    $vars['title'] = $title;
    render_admin_like('layouts/admin', 'admin/' . $view, $vars);
}
function admin_need(string $perm): array {
    $shop = shop_by_id(admin_shop_id());
    if (!$shop) abort(403, 'No shop selected.');
    if (!can($perm)) { audit('permission.denied', 'module', null, (int) $shop['id'], 'denied', ['perm' => $perm, 'path' => request_path()]); abort(403, 'You do not have permission to access this section.'); }
    return $shop;
}

function admin_dispatch(string $path): void {
    security_headers(true);
    $GLOBALS['LOCALE'] = 'en';
    $public = [
        ['ANY', '/admin/login/', 'admin_login'],
        ['ANY', '/admin/set-password/', 'admin_set_password'],
        ['ANY', '/admin/forgot-password/', 'admin_forgot'],
    ];
    foreach ($public as [$m, $re, $fn]) if ($path === $re) { $fn(); return; }
    $u = current_user();
    if (!$u) redirect('/admin/login/');
    if ($u['role'] === 'super_admin' && !impersonated_shop_id()) redirect('/superadmin/');
    $shop = shop_by_id(admin_shop_id());
    if (!$shop || $shop['deleted_at']) { logout(); redirect('/admin/login/'); }
    if (!is_super() && !shop_is_open($shop)) { logout(); flash('error', 'Access to this shop is suspended. Contact your provider.'); redirect('/admin/login/'); }
    if (!empty($GLOBALS['HOST_SHOP_ID']) && (int) $GLOBALS['HOST_SHOP_ID'] !== (int) $shop['id']) abort(403, 'Access denied.');
    $sid = (int) $shop['id'];

    $routes = [
        ['POST', '/admin/logout/', static function () { logout(); redirect('/admin/login/'); }],
        ['GET', '/admin/', static fn() => admin_dashboard()],
        ['GET', '/admin/products/', static fn() => admin_products()],
        ['ANY', '/admin/products/(?<id>new|\d+)/', static fn($p) => admin_product_edit($p['id'] === 'new' ? null : (int) $p['id'])],
        ['POST', '/admin/products/(?<id>\d+)/status/', static fn($p) => admin_product_status((int) $p['id'])],
        ['POST', '/admin/products/bulk/', static fn() => admin_products_bulk()],
        ['GET', '/admin/products/export/', static function () { $shop = admin_need('feeds'); export_products_csv($shop, ['status' => get('status'), 'category_id' => int_in('category_id'), 'ids' => array_filter(array_map('intval', explode(',', (string) get('ids'))))]); }],
        ['GET', '/admin/categories/', static fn() => admin_categories(null)],
        ['ANY', '/admin/categories/(?<id>new|\d+)/', static fn($p) => admin_categories($p['id'] === 'new' ? 0 : (int) $p['id'])],
        ['POST', '/admin/categories/(?<id>\d+)/delete/', static function ($p) { $shop = admin_need('categories'); handle(static function () use ($shop, $p) { category_delete((int) $shop['id'], (int) $p['id']); flash('ok', 'Category deleted. Products were kept.'); }, '/admin/categories/'); }],
        ['ANY', '/admin/menus/', static fn() => admin_menus()],
        ['GET', '/admin/orders/', static fn() => admin_orders()],
        ['ANY', '/admin/orders/(?<id>\d+)/', static fn($p) => admin_order((int) $p['id'])],
        ['GET', '/admin/customers/', static fn() => admin_customers()],
        ['GET', '/admin/customers/(?<id>\d+)/', static fn($p) => admin_customer((int) $p['id'])],
        ['ANY', '/admin/messages/', static fn() => admin_messages()],
        ['ANY', '/admin/subscribers/', static fn() => admin_subscribers()],
        ['GET', '/admin/pages/', static fn() => admin_pages()],
        ['ANY', '/admin/pages/(?<id>new|\d+)/', static fn($p) => admin_page_edit($p['id'] === 'new' ? null : (int) $p['id'])],
        ['ANY', '/admin/homepage/', static fn() => admin_homepage()],
        ['GET', '/admin/media/', static fn() => admin_media()],
        ['POST', '/admin/media/upload/', static fn() => admin_media_upload()],
        ['POST', '/admin/media/(?<id>\d+)/', static fn($p) => admin_media_action((int) $p['id'])],
        ['GET', '/admin/api/media/', static fn() => admin_media_json()],
        ['ANY', '/admin/appearance/', static fn() => admin_appearance()],
        ['GET', '/admin/popups/', static fn() => admin_popups()],
        ['ANY', '/admin/popups/(?<id>new|\d+)/', static fn($p) => admin_popup_edit($p['id'] === 'new' ? null : (int) $p['id'])],
        ['GET', '/admin/feeds/', static fn() => admin_feeds()],
        ['ANY', '/admin/feeds/(?<id>new|\d+)/', static fn($p) => admin_feed_edit($p['id'] === 'new' ? null : (int) $p['id'])],
        ['ANY', '/admin/import/', static fn() => admin_import()],
        ['ANY', '/admin/import/(?<id>\d+)/', static fn($p) => admin_import_step((int) $p['id'])],
        ['POST', '/admin/import/(?<id>\d+)/process/', static fn($p) => admin_import_process((int) $p['id'])],
        ['ANY', '/admin/seo/', static fn() => admin_seo()],
        ['ANY', '/admin/seo/redirects/', static fn() => admin_redirects()],
        ['ANY', '/admin/seo/404/', static fn() => admin_404()],
        ['GET', '/admin/users/', static fn() => admin_users()],
        ['ANY', '/admin/users/(?<id>new|\d+)/', static fn($p) => admin_user_edit($p['id'] === 'new' ? null : (int) $p['id'])],
        ['ANY', '/admin/settings/', static fn() => admin_settings()],
        ['ANY', '/admin/notifications/', static fn() => admin_notifications()],
        ['POST', '/admin/api/analyze/', static fn() => admin_analyze()],
    ];
    if (!dispatch($routes, $path)) abort(404);
}

/* ---------- Auth ---------- */
function admin_login(): void {
    if (current_user() && !is_super()) redirect('/admin/');
    if (is_post()) {
        [$ok, $msg] = login_attempt((string) post('email'), (string) ($_POST['password'] ?? ''), 'admin');
        if ($ok) redirect('/admin/');
        flash('error', $msg); remember_input();
        redirect('/admin/login/');
    }
    security_headers(true);
    render('admin/login', ['area' => 'admin']);
}
function admin_forgot(): void {
    if (is_post()) {
        if (rate_limit('admin_forgot', 5, 900)) {
            $u = row("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL AND role <> 'super_admin'", [mb_strtolower(clean_text(post('email'), 190))]);
            if ($u) { $tok = password_token_create('user', (int) $u['id']); send_mail($u['email'], 'Reset your password', 'Reset your password (valid 48h): ' . abs_url('/admin/set-password/?token=' . urlencode($tok))); audit('auth.reset_requested', 'user', (int) $u['id'], (int) $u['shop_id']); }
        }
        flash('ok', 'If an account exists for that email, a reset link has been sent.');
        redirect('/admin/login/');
    }
    render('admin/login', ['area' => 'forgot']);
}
function admin_set_password(): void {
    $tok = (string) input('token', '');
    $t = $tok !== '' ? password_token_find('user', $tok) : null;
    $u = $t ? row('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$t['subject_id']]) : null;
    if (!$u) render('errors/generic', ['code' => 404, 'title' => 'Link expired', 'text' => 'This password link is invalid or has expired. Ask your administrator for a new one.'], 404);
    if (is_post()) {
        $pw = (string) ($_POST['password'] ?? '');
        if ($pe = password_policy($pw)) { flash('error', $pe); redirect('/admin/set-password/?token=' . urlencode($tok)); }
        if ($pw !== (string) ($_POST['password2'] ?? '')) { flash('error', 'Passwords do not match.'); redirect('/admin/set-password/?token=' . urlencode($tok)); }
        update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'failed_attempts' => 0, 'locked_until' => null, 'updated_at' => now()], 'id = ?', [$u['id']]);
        update('password_tokens', ['used_at' => now()], 'id = ?', [$t['id']]);
        audit('auth.password_set', 'user', (int) $u['id'], $u['shop_id'] ? (int) $u['shop_id'] : null, 'success', [], $u);
        flash('ok', 'Password saved. You can sign in now.');
        redirect($u['role'] === 'super_admin' ? '/superadmin/login/' : '/admin/login/');
    }
    render('admin/login', ['area' => 'set', 'token' => $tok, 'email' => $u['email']]);
}

/* ---------- Dashboard ---------- */
function admin_dashboard(): never {
    $shop = admin_need('dashboard');
    $sid = (int) $shop['id'];
    $q = shop_quota($shop);
    $stats = [
        'orders_30' => (int) val('SELECT COUNT(*) FROM orders WHERE shop_id = ? AND created_at >= ?', [$sid, date('Y-m-d', strtotime('-30 days'))]),
        'revenue_30' => (int) val("SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE shop_id = ? AND created_at >= ? AND status NOT IN ('cancelled','refunded')", [$sid, date('Y-m-d', strtotime('-30 days'))]),
        'pending' => (int) val("SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status = 'pending'", [$sid]),
        'customers' => (int) val('SELECT COUNT(*) FROM customers WHERE shop_id = ?', [$sid]),
        'subscribers' => (int) val('SELECT COUNT(*) FROM popup_submissions WHERE shop_id = ? AND unsubscribed_at IS NULL', [$sid]),
        'messages' => (int) val('SELECT COUNT(*) FROM contact_messages WHERE shop_id = ? AND read_at IS NULL', [$sid]),
        'categories' => (int) val('SELECT COUNT(*) FROM categories WHERE shop_id = ?', [$sid]),
        'missing_meta' => (int) val("SELECT COUNT(*) FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL AND (seo_description IS NULL OR seo_description = '')", [$sid]),
        'no_image' => (int) val("SELECT COUNT(*) FROM products p WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM product_images WHERE product_id = p.id)", [$sid]),
        'n404' => (int) val('SELECT COUNT(*) FROM not_found_log WHERE shop_id = ? AND ignored = 0', [$sid]),
    ];
    $daily = rows("SELECT substr(created_at,1,10) d, COUNT(*) n, SUM(total_cents) t FROM orders WHERE shop_id = ? AND created_at >= ? GROUP BY d ORDER BY d", [$sid, date('Y-m-d', strtotime('-13 days'))]);
    $recent = rows('SELECT * FROM orders WHERE shop_id = ? ORDER BY id DESC LIMIT 6', [$sid]);
    $logo = shop_setting($sid, 'logo_id');
    $checklist = [
        ['Upload your logo & set brand colours', (bool) $logo, '/admin/appearance/'],
        ['Fill in contact details', (bool) shop_setting($sid, 'contact_email'), '/admin/appearance/#contact'],
        ['Create your categories', $stats['categories'] > 0, '/admin/categories/'],
        ['Add or import products', $q['stored'] > 0, '/admin/import/'],
        ['Activate products (within your quota)', $q['active'] > 0, '/admin/products/'],
        ['Edit the homepage hero', (bool) val("SELECT 1 FROM homepage_sections WHERE shop_id = ? AND type = 'hero' AND json_extract(data, '$.image_id') IS NOT NULL", [$sid]), '/admin/homepage/'],
        ['Review legal pages (terms, privacy, shipping, returns)', (bool) val("SELECT 1 FROM pages WHERE shop_id = ? AND slug = 'terms' AND content NOT LIKE '%verify before going live%' AND content NOT LIKE '%ελέγξτε πριν τη δημοσίευση%'", [$sid]), '/admin/pages/'],
        ['Set up shipping & payment methods', (bool) shop_setting($sid, 'settings_reviewed'), '/admin/settings/'],
        ['Configure SEO basics (title, description)', (bool) seo_settings($sid)['meta_description'], '/admin/seo/'],
        ['Set up a marketplace feed (Skroutz / BestPrice)', (bool) val('SELECT 1 FROM feeds WHERE shop_id = ?', [$sid]), '/admin/feeds/'],
    ];
    admin_render('dashboard', compact('q', 'stats', 'daily', 'recent', 'checklist'), 'Dashboard');
}

/* ---------- Products ---------- */
function admin_products(): never {
    $shop = admin_need('products');
    $sid = (int) $shop['id'];
    $where = 'p.shop_id = ? AND p.deleted_at IS NULL'; $params = [$sid];
    $q = clean_text(get('q'), 100);
    if ($q !== '') { $like = '%' . $q . '%'; $where .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.brand LIKE ?)'; array_push($params, $like, $like, $like, $like); }
    if (in_array(get('status'), ['active', 'inactive'], true)) { $where .= ' AND p.status = ?'; $params[] = get('status'); }
    if ($cid = int_in('category_id')) { $ids = category_descendant_ids($sid, $cid) ?: [0]; $where .= ' AND p.id IN (SELECT product_id FROM category_relationships WHERE category_id IN (' . in_ph($ids) . '))'; $params = array_merge($params, $ids); }
    if (get('stock') === 'out') $where .= ' AND p.manage_stock = 1 AND p.stock <= 0';
    $sortCols = ['name' => 'p.name COLLATE NOCASE', 'price' => 'p.price_cents', 'stock' => 'p.stock', 'updated' => 'p.updated_at', 'status' => 'p.status', 'id' => 'p.id'];
    $sort = $sortCols[get('sort')] ?? 'p.id';
    $dir = get('dir') === 'asc' ? 'ASC' : 'DESC';
    $total = (int) val("SELECT COUNT(*) FROM products p WHERE $where", $params);
    $per = in_array(int_in('per', 50), [25, 50, 100, 200], true) ? int_in('per', 50) : 50;
    $pg = paginate($total, $per);
    $products = products_attach_images(rows("SELECT p.*, (SELECT name FROM categories WHERE id = p.primary_category_id) AS cat_name FROM products p WHERE $where ORDER BY $sort $dir, p.id DESC LIMIT ? OFFSET ?", array_merge($params, [$pg['limit'], $pg['offset']])));
    admin_render('products', ['products' => $products, 'pg' => $pg, 'q' => shop_quota($shop), 'cats' => categories_flat($sid), 'search' => $q], 'Products');
}
function admin_product_edit(?int $id): never {
    $shop = admin_need('products');
    $sid = (int) $shop['id'];
    $p = $id ? owned('products', $id, $sid) : null;
    if (is_post()) {
        handle(static function () use ($shop, $id) {
            $pid = product_save($shop, $id, $_POST);
            if (!empty($GLOBALS['QUOTA_WARNING'])) flash('warn', 'Saved as inactive. ' . $GLOBALS['QUOTA_WARNING']);
            else flash('ok', $id ? 'Product saved.' : 'Product created.');
            return '/admin/products/' . $pid . '/';
        }, $id ? "/admin/products/$id/" : '/admin/products/new/');
    }
    $images = $p ? rows('SELECT m.* FROM product_images pi JOIN media m ON m.id = pi.media_id WHERE pi.product_id = ? ORDER BY pi.position', [$id]) : [];
    $variants = $p ? product_variants($id) : [];
    $attrs = $p ? rows('SELECT * FROM product_attributes WHERE product_id = ? ORDER BY position', [$id]) : [];
    $pcats = $p ? array_map('intval', col('SELECT category_id FROM category_relationships WHERE product_id = ?', [$id])) : [];
    admin_render('product-edit', ['p' => $p, 'images' => $images, 'variants' => $variants, 'attrs' => $attrs, 'pcats' => $pcats, 'cats' => categories_flat($sid), 'q' => shop_quota($shop)], $p ? 'Edit: ' . $p['name'] : 'New product');
}
function admin_product_status(int $id): never {
    $shop = admin_need('products');
    owned('products', $id, (int) $shop['id']);
    $to = post('to') === 'active' ? 'active' : 'inactive';
    try {
        product_set_status($shop, [$id], $to);
        $msg = $to === 'active' ? 'Product activated.' : 'Product deactivated.';
        if (wants_json()) json_out(['ok' => true, 'message' => $msg, 'quota' => shop_quota(shop_by_id((int) $shop['id']))]);
        flash('ok', $msg);
    } catch (QuotaError $e) {
        if (wants_json()) json_out(['ok' => false, 'error' => $e->getMessage(), 'quota' => shop_quota($shop)], 409);
        flash('error', $e->getMessage());
    }
    back('/admin/products/');
}
function admin_products_bulk(): never {
    $shop = admin_need('products');
    $ids = array_map('intval', arr($_POST['ids'] ?? []));
    $action = (string) post('action');
    if ($action === 'export') redirect('/admin/products/export/?ids=' . implode(',', $ids));
    handle(static function () use ($shop, $action, $ids) { flash('ok', product_bulk($shop, $action, $ids, $_POST)); }, (string) (post('return') ?: '/admin/products/'));
}

/* ---------- Categories & menus ---------- */
function admin_categories(?int $editId): never {
    $shop = admin_need('categories');
    $sid = (int) $shop['id'];
    $cat = $editId ? owned('categories', $editId, $sid) : null;
    if (is_post()) handle(static function () use ($sid, $editId) { $id = category_save($sid, $editId ?: null, $_POST); flash('ok', 'Category saved.'); return "/admin/categories/$id/"; }, $editId ? "/admin/categories/$editId/" : '/admin/categories/new/');
    $counts = [];
    foreach (rows('SELECT cr.category_id, COUNT(*) n, SUM(p.status = \'active\') a FROM category_relationships cr JOIN products p ON p.id = cr.product_id AND p.deleted_at IS NULL WHERE cr.shop_id = ? GROUP BY cr.category_id', [$sid]) as $r) $counts[$r['category_id']] = $r;
    admin_render('categories', ['tree' => categories_tree($sid), 'flat' => categories_flat($sid), 'cat' => $cat, 'editing' => $editId !== null, 'counts' => $counts], 'Categories');
}
function admin_menus(): never {
    $shop = admin_need('pages');
    $sid = (int) $shop['id'];
    if (is_post()) {
        handle(static function () use ($sid) {
            $act = post('act');
            if ($act === 'add') {
                $menu = row('SELECT * FROM menus WHERE shop_id = ? AND location = ?', [$sid, post('location') === 'footer' ? 'footer' : 'header']);
                if (!$menu) throw new UserError('Menu not found.');
                $type = in_array(post('type'), ['category', 'page', 'product', 'url', 'products', 'home'], true) ? post('type') : 'url';
                $ref = (int) post('ref_' . $type);
                if (in_array($type, ['category', 'page'], true) && !val("SELECT 1 FROM " . ($type === 'category' ? 'categories' : 'pages') . " WHERE id = ? AND shop_id = ?", [$ref, $sid])) throw new UserError('Choose a target.');
                if ($type === 'product' && !val('SELECT 1 FROM products WHERE id = ? AND shop_id = ?', [$ref, $sid])) throw new UserError('Choose a product.');
                $label = clean_text(post('label'), 80);
                if ($label === '') $label = match ($type) { 'category' => (string) val('SELECT name FROM categories WHERE id = ?', [$ref]), 'page' => (string) val('SELECT title FROM pages WHERE id = ?', [$ref]), 'product' => (string) val('SELECT name FROM products WHERE id = ?', [$ref]), 'products' => 'All products', 'home' => 'Home', default => '' };
                if ($label === '') throw new UserError('Label is required.');
                $parent = (int) post('parent_id');
                if ($parent && !val('SELECT 1 FROM menu_items WHERE id = ? AND menu_id = ? AND parent_id IS NULL', [$parent, $menu['id']])) $parent = 0;
                insert('menu_items', ['shop_id' => $sid, 'menu_id' => $menu['id'], 'parent_id' => $parent ?: null, 'label' => $label, 'type' => $type, 'ref_id' => in_array($type, ['category', 'page', 'product'], true) ? $ref : null,
                    'url' => $type === 'url' ? clean_url(post('url')) : null, 'position' => (int) val('SELECT COALESCE(MAX(position),0)+1 FROM menu_items WHERE menu_id = ?', [$menu['id']])]);
                flash('ok', 'Menu item added.');
            } elseif ($act === 'delete') {
                $it = owned('menu_items', (int) post('id'), $sid);
                q('DELETE FROM menu_items WHERE id = ? AND shop_id = ?', [$it['id'], $sid]);
                flash('ok', 'Menu item removed.');
            } elseif ($act === 'move') {
                $it = owned('menu_items', (int) post('id'), $sid);
                $sib = rows('SELECT id, position FROM menu_items WHERE menu_id = ? AND ' . ($it['parent_id'] ? 'parent_id = ' . (int) $it['parent_id'] : 'parent_id IS NULL') . ' ORDER BY position, id', [$it['menu_id']]);
                $ids = array_column($sib, 'id'); $i = array_search($it['id'], $ids);
                $j = post('dir') === 'up' ? $i - 1 : $i + 1;
                if ($i !== false && isset($ids[$j])) { [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; foreach ($ids as $pos => $mid) q('UPDATE menu_items SET position = ? WHERE id = ?', [$pos, $mid]); }
            } elseif ($act === 'rename') {
                $it = owned('menu_items', (int) post('id'), $sid);
                update('menu_items', ['label' => clean_text(post('label'), 80) ?: $it['label']], 'id = ?', [$it['id']]);
            }
            audit('menu.updated', 'menu', null, $sid, 'success', ['act' => post('act')]);
        }, '/admin/menus/');
    }
    $menus = [];
    foreach (['header' => 'Header menu', 'footer' => 'Footer menu'] as $loc => $label) $menus[$loc] = ['label' => $label, 'items' => menu_items($sid, $loc)];
    admin_render('menus', ['menus' => $menus, 'cats' => categories_flat($sid), 'pages' => rows('SELECT id, title FROM pages WHERE shop_id = ? AND deleted_at IS NULL ORDER BY title', [$sid]), 'products' => rows("SELECT id, name FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL ORDER BY name LIMIT 300", [$sid])], 'Menus');
}

/* ---------- Orders & customers ---------- */
function admin_orders(): never {
    $shop = admin_need('orders');
    $sid = (int) $shop['id'];
    $where = 'shop_id = ?'; $params = [$sid];
    if (isset(ORDER_STATUSES[get('status')])) { $where .= ' AND status = ?'; $params[] = get('status'); }
    if (($qq = clean_text(get('q'), 100)) !== '') { $where .= ' AND (number LIKE ? OR email LIKE ? OR name LIKE ?)'; array_push($params, "%$qq%", "%$qq%", "%$qq%"); }
    $pg = paginate((int) val("SELECT COUNT(*) FROM orders WHERE $where", $params), 30);
    $orders = rows("SELECT * FROM orders WHERE $where ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$pg['limit'], $pg['offset']]));
    admin_render('orders', compact('orders', 'pg'), 'Orders');
}
function admin_order(int $id): never {
    $shop = admin_need('orders');
    $sid = (int) $shop['id'];
    $o = owned('orders', $id, $sid);
    if (is_post()) handle(static function () use ($sid, $id) { order_update($sid, $id, $_POST); flash('ok', 'Order updated.'); }, "/admin/orders/$id/");
    admin_render('order', ['o' => $o, 'items' => rows('SELECT * FROM order_items WHERE order_id = ?', [$id]), 'history' => rows('SELECT * FROM order_history WHERE order_id = ? ORDER BY id DESC', [$id])], 'Order #' . $o['number']);
}
function admin_customers(): never {
    $shop = admin_need('customers');
    $sid = (int) $shop['id'];
    $where = 'c.shop_id = ? AND c.deleted_at IS NULL'; $params = [$sid];
    if (($qq = clean_text(get('q'), 100)) !== '') { $where .= ' AND (c.email LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)'; array_push($params, "%$qq%", "%$qq%", "%$qq%"); }
    $pg = paginate((int) val("SELECT COUNT(*) FROM customers c WHERE $where", $params), 40);
    $customers = rows("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_count, (SELECT COALESCE(SUM(total_cents),0) FROM orders o WHERE o.customer_id = c.id AND o.status NOT IN ('cancelled','refunded')) AS spent FROM customers c WHERE $where ORDER BY c.id DESC LIMIT ? OFFSET ?", array_merge($params, [$pg['limit'], $pg['offset']]));
    admin_render('customers', compact('customers', 'pg'), 'Customers');
}
function admin_customer(int $id): never {
    $shop = admin_need('customers');
    $c = owned('customers', $id, (int) $shop['id']);
    admin_render('customer', ['c' => $c, 'orders' => rows('SELECT * FROM orders WHERE shop_id = ? AND customer_id = ? ORDER BY id DESC', [$shop['id'], $id])], $c['name'] ?: $c['email']);
}
function admin_messages(): never {
    $shop = admin_need('customers');
    $sid = (int) $shop['id'];
    if (is_post()) handle(static function () use ($sid) {
        $m = owned('contact_messages', (int) post('id'), $sid);
        if (post('act') === 'delete') { q('DELETE FROM contact_messages WHERE id = ? AND shop_id = ?', [$m['id'], $sid]); flash('ok', 'Message deleted.'); }
        else update('contact_messages', ['read_at' => now()], 'id = ?', [$m['id']]);
    }, '/admin/messages/');
    $pg = paginate((int) val('SELECT COUNT(*) FROM contact_messages WHERE shop_id = ?', [$sid]), 30);
    admin_render('messages', ['messages' => rows('SELECT * FROM contact_messages WHERE shop_id = ? ORDER BY id DESC LIMIT ? OFFSET ?', [$sid, $pg['limit'], $pg['offset']]), 'pg' => $pg], 'Messages');
}
function admin_subscribers(): never {
    $shop = admin_need('popups');
    $sid = (int) $shop['id'];
    if (is_post()) handle(static function () use ($sid) {
        $s = owned('popup_submissions', (int) post('id'), $sid);
        if (post('act') === 'delete') q('DELETE FROM popup_submissions WHERE id = ? AND shop_id = ?', [$s['id'], $sid]);
        else update('popup_submissions', ['unsubscribed_at' => now()], 'id = ?', [$s['id']]);
        audit('subscriber.' . post('act'), 'subscriber', (int) $s['id'], $sid);
        flash('ok', 'Subscriber updated.');
    }, '/admin/subscribers/');
    if (get('export') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'wb'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['email', 'consent', 'consent_text', 'source', 'subscribed_at', 'unsubscribed_at'], ',', '"', '');
        foreach (rows('SELECT * FROM popup_submissions WHERE shop_id = ? ORDER BY id', [$sid]) as $s) fputcsv($out, [$s['email'], $s['consent'], $s['consent_text'], $s['source'], $s['created_at'], $s['unsubscribed_at']], ',', '"', '');
        audit('subscriber.exported', 'subscriber', null, $sid);
        exit;
    }
    $pg = paginate((int) val('SELECT COUNT(*) FROM popup_submissions WHERE shop_id = ?', [$sid]), 50);
    admin_render('subscribers', ['subs' => rows('SELECT * FROM popup_submissions WHERE shop_id = ? ORDER BY id DESC LIMIT ? OFFSET ?', [$sid, $pg['limit'], $pg['offset']]), 'pg' => $pg], 'Subscribers');
}

/* ---------- Pages & homepage ---------- */
function admin_pages(): never {
    $shop = admin_need('pages');
    admin_render('pages', ['pages' => rows('SELECT * FROM pages WHERE shop_id = ? AND deleted_at IS NULL ORDER BY title', [$shop['id']])], 'Pages');
}
function admin_page_edit(?int $id): never {
    $shop = admin_need('pages');
    $sid = (int) $shop['id'];
    $page = $id ? owned('pages', $id, $sid) : null;
    if (is_post()) {
        if (post('act') === 'delete' && $page) handle(static function () use ($sid, $page) {
            update('pages', ['deleted_at' => now()], 'id = ? AND shop_id = ?', [$page['id'], $sid]);
            q('DELETE FROM menu_items WHERE shop_id = ? AND type = ? AND ref_id = ?', [$sid, 'page', $page['id']]);
            audit('page.deleted', 'page', (int) $page['id'], $sid);
            flash('ok', 'Page deleted.'); return '/admin/pages/';
        }, "/admin/pages/$id/");
        handle(static function () use ($sid, $id) { $pid = page_save($sid, $id, $_POST); flash('ok', 'Page saved.'); return "/admin/pages/$pid/"; }, $id ? "/admin/pages/$id/" : '/admin/pages/new/');
    }
    admin_render('page-edit', ['page' => $page], $page ? 'Edit page: ' . $page['title'] : 'New page');
}
function admin_homepage(): never {
    $shop = admin_need('appearance');
    $sid = (int) $shop['id'];
    if (is_post()) handle(static function () use ($sid) {
        $act = post('act');
        if ($act === 'save') { homepage_section_save($sid, (int) post('id'), $_POST); flash('ok', 'Section saved.'); }
        elseif ($act === 'add') {
            $type = (string) post('type');
            if (!isset(homepage_section_types()[$type])) throw new UserError('Unknown section type.');
            insert('homepage_sections', ['shop_id' => $sid, 'type' => $type, 'position' => (int) val('SELECT COALESCE(MAX(position),0)+1 FROM homepage_sections WHERE shop_id = ?', [$sid]), 'enabled' => 1, 'data' => '{}']);
            flash('ok', 'Section added.');
        } elseif ($act === 'delete') { $s = owned('homepage_sections', (int) post('id'), $sid); q('DELETE FROM homepage_sections WHERE id = ? AND shop_id = ?', [$s['id'], $sid]); flash('ok', 'Section removed.'); }
        elseif ($act === 'move') {
            $ids = col('SELECT id FROM homepage_sections WHERE shop_id = ? ORDER BY position, id', [$sid]);
            $i = array_search((int) post('id'), array_map('intval', $ids), true); $j = post('dir') === 'up' ? $i - 1 : $i + 1;
            if ($i !== false && isset($ids[$j])) { [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; foreach ($ids as $pos => $x) q('UPDATE homepage_sections SET position = ? WHERE id = ? AND shop_id = ?', [$pos + 1, $x, $sid]); }
        }
        audit('homepage.' . $act, 'homepage', null, $sid);
    }, '/admin/homepage/' . (post('id') ? '#s' . (int) post('id') : ''));
    admin_render('homepage', ['sections' => homepage_sections($sid, false), 'cats' => categories_flat($sid)], 'Homepage');
}

/* ---------- Media ---------- */
function admin_media(): never {
    $shop = admin_need('media');
    $sid = (int) $shop['id'];
    $pg = paginate((int) val('SELECT COUNT(*) FROM media WHERE shop_id = ?', [$sid]), 48);
    admin_render('media', ['media' => rows('SELECT * FROM media WHERE shop_id = ? ORDER BY id DESC LIMIT ? OFFSET ?', [$sid, $pg['limit'], $pg['offset']]), 'pg' => $pg, 'used' => media_storage_used($sid), 'limit' => shop_storage_limit_bytes($shop)], 'Media library');
}
function admin_media_upload(): never {
    $shop = admin_need('media');
    $files = $_FILES['files'] ?? $_FILES['file'] ?? null;
    $ids = []; $errors = [];
    if ($files && is_array($files['name'])) {
        foreach ($files['name'] as $i => $n) {
            try { $ids[] = media_upload($shop, ['name' => $n, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]]); }
            catch (UserError $e) { $errors[] = $n . ': ' . $e->getMessage(); }
        }
    } elseif ($files) {
        try { $ids[] = media_upload($shop, $files); } catch (UserError $e) { $errors[] = $e->getMessage(); }
    }
    if (wants_json()) json_out(['ok' => (bool) $ids, 'ids' => $ids, 'errors' => $errors, 'items' => array_map(static fn($id) => admin_media_item(media_by_id($id)), $ids)], $ids ? 200 : 422);
    if ($ids) flash('ok', count($ids) . ' image(s) uploaded.');
    foreach ($errors as $e) flash('error', $e);
    redirect('/admin/media/');
}
function admin_media_item(array $m): array { return ['id' => (int) $m['id'], 'url' => media_url($m, 400), 'name' => $m['filename'], 'w' => (int) $m['width'], 'h' => (int) $m['height']]; }
function admin_media_json(): never {
    $shop = admin_need('media');
    $items = rows('SELECT * FROM media WHERE shop_id = ? ORDER BY id DESC LIMIT 120', [$shop['id']]);
    json_out(['ok' => true, 'items' => array_map('admin_media_item', $items)]);
}
function admin_media_action(int $id): never {
    $shop = admin_need('media');
    $sid = (int) $shop['id'];
    handle(static function () use ($shop, $sid, $id) {
        $act = post('act');
        if ($act === 'delete') { media_delete($sid, $id); flash('ok', 'Image deleted.'); }
        elseif ($act === 'alt') { owned('media', $id, $sid); update('media', ['alt' => clean_text(post('alt'), 200) ?: null], 'id = ?', [$id]); flash('ok', 'Alt text saved.'); }
        elseif ($act === 'replace') { media_upload($shop, $_FILES['file'] ?? [], $id); flash('ok', 'Image replaced everywhere it is used.'); }
    }, '/admin/media/' . (get('page') ? '?page=' . (int) get('page') : ''));
}

/* ---------- Appearance (safe design tokens) ---------- */
function admin_appearance(): never {
    $shop = admin_need('appearance');
    $sid = (int) $shop['id'];
    $tpl = template_get($shop['template_key']);
    if (is_post()) handle(static function () use ($shop, $sid, $tpl) {
        if (post('act') === 'reset_colors') { shop_setting_set($sid, 'colors', null); audit('appearance.colors_reset', 'shop', $sid, $sid); flash('ok', 'Colours reset to the template defaults.'); return; }
        $colors = ['_template' => $tpl['key']];
        foreach (array_keys(token_keys()) as $k) { $v = strtolower(clean_text(post('c_' . $k), 7)); if ($v !== '' && valid_hex($v) && $v !== $tpl['tokens'][$k]) $colors[$k] = $v; }
        shop_setting_set($sid, 'colors', count($colors) > 1 ? jenc($colors) : null);
        foreach (['logo_id', 'favicon_id'] as $k) { $m = (int) post($k); shop_setting_set($sid, $k, $m && val('SELECT 1 FROM media WHERE id = ? AND shop_id = ?', [$m, $sid]) ? (string) $m : null); }
        if (shop_feature($shop, 'typography')) foreach (['font_heading', 'font_body'] as $k) shop_setting_set($sid, $k, isset(font_choices()[post($k)]) && post($k) !== 'template' ? post($k) : null);
        foreach (['company_name' => 160, 'tagline' => 200, 'contact_address' => 255, 'contact_phone' => 40, 'contact_email' => 190, 'contact_hours' => 160, 'announcement' => 200] as $k => $max) shop_setting_set($sid, $k, clean_text(post($k), $max) ?: null);
        foreach (['social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube', 'social_linkedin', 'social_x'] as $k) { $u = clean_url(post($k)); shop_setting_set($sid, $k, preg_match('#^https://#', $u) ? $u : null); }
        if (post('contact_email') !== '' && !valid_email((string) post('contact_email'))) throw new ValidationError(['contact_email' => 'Invalid email.']);
        audit('appearance.updated', 'shop', $sid, $sid);
        flash('ok', 'Appearance saved.');
    }, '/admin/appearance/');
    $tokens = shop_tokens($shop);
    $warnings = [];
    if (contrast_ratio($tokens['text'], $tokens['background']) < 4.5) $warnings[] = 'Text on background contrast is ' . contrast_ratio($tokens['text'], $tokens['background']) . ':1 (needs 4.5:1).';
    if (contrast_ratio($tokens['button_text'], $tokens['primary']) < 4.5) $warnings[] = 'Button text on primary contrast is ' . contrast_ratio($tokens['button_text'], $tokens['primary']) . ':1 (needs 4.5:1).';
    if (contrast_ratio($tokens['muted'], $tokens['background']) < 3) $warnings[] = 'Muted text contrast is low (' . contrast_ratio($tokens['muted'], $tokens['background']) . ':1).';
    admin_render('appearance', ['tpl' => $tpl, 'tokens' => $tokens, 'warnings' => $warnings, 'typo' => shop_feature($shop, 'typography')], 'Appearance');
}

/* ---------- Popups ---------- */
function admin_popups(): never {
    $shop = admin_need('popups');
    admin_render('popups', ['popups' => rows('SELECT p.*, (SELECT COUNT(*) FROM popup_submissions s WHERE s.popup_id = p.id) AS subs FROM popups p WHERE p.shop_id = ? ORDER BY p.id DESC', [$shop['id']]), 'enabled' => shop_feature($shop, 'popups')], 'Popups');
}
function admin_popup_edit(?int $id): never {
    $shop = admin_need('popups');
    $sid = (int) $shop['id'];
    if (!shop_feature($shop, 'popups')) { flash('error', 'Popups are not included in your package.'); redirect('/admin/popups/'); }
    $p = $id ? owned('popups', $id, $sid) : null;
    if (is_post()) {
        if (post('act') === 'delete' && $p) handle(static function () use ($sid, $p) { q('DELETE FROM popups WHERE id = ? AND shop_id = ?', [$p['id'], $sid]); flash('ok', 'Popup deleted (subscribers kept).'); return '/admin/popups/'; }, "/admin/popups/$id/");
        handle(static function () use ($sid, $id) {
            $title = clean_text(post('title'), 160);
            if ($title === '') throw new ValidationError(['title' => 'Title is required.']);
            $data = ['title' => $title, 'body' => clean_multiline(post('body'), 1000), 'button_text' => clean_text(post('button_text'), 60), 'cta_link' => clean_url(post('cta_link')),
                'collect_email' => post('collect_email') ? 1 : 0, 'consent_required' => post('consent_required') ? 1 : 0, 'consent_text' => clean_text(post('consent_text'), 500),
                'image_id' => ($m = (int) post('image_id')) && val('SELECT 1 FROM media WHERE id = ? AND shop_id = ?', [$m, $sid]) ? $m : null,
                'delay_seconds' => max(0, min(120, (int) post('delay_seconds'))), 'active' => post('active') ? 1 : 0, 'updated_at' => now()];
            if ($data['active']) q('UPDATE popups SET active = 0 WHERE shop_id = ?', [$sid]); // one active homepage popup
            if ($id) update('popups', $data, 'id = ? AND shop_id = ?', [$id, $sid]); else $id = insert('popups', $data + ['shop_id' => $sid, 'created_at' => now()]);
            audit('popup.saved', 'popup', $id, $sid);
            flash('ok', 'Popup saved.');
            return "/admin/popups/$id/";
        }, $id ? "/admin/popups/$id/" : '/admin/popups/new/');
    }
    admin_render('popup-edit', ['p' => $p], $p ? 'Edit popup' : 'New popup');
}

/* ---------- Feeds ---------- */
function admin_feeds(): never {
    $shop = admin_need('feeds');
    admin_render('feeds', ['feeds' => rows('SELECT * FROM feeds WHERE shop_id = ? ORDER BY id', [$shop['id']]), 'logs' => rows('SELECT l.*, f.name FROM feed_logs l LEFT JOIN feeds f ON f.id = l.feed_id WHERE l.shop_id = ? ORDER BY l.id DESC LIMIT 10', [$shop['id']]), 'cats' => categories_flat((int) $shop['id'])], 'Feeds & export');
}
function admin_feed_edit(?int $id): never {
    $shop = admin_need('feeds');
    $sid = (int) $shop['id'];
    $f = $id ? owned('feeds', $id, $sid) : null;
    if (is_post()) {
        if (post('act') === 'delete' && $f) handle(static function () use ($sid, $f) { q('DELETE FROM feeds WHERE id = ? AND shop_id = ?', [$f['id'], $sid]); flash('ok', 'Feed deleted.'); return '/admin/feeds/'; }, "/admin/feeds/$id/");
        if (post('act') === 'regenerate' && $f) handle(static function () use ($shop, $f) { feed_build($shop, $f + ['_force' => true], shop_public_url($shop)); flash('ok', 'Feed regenerated.'); }, "/admin/feeds/$id/");
        handle(static function () use ($shop, $sid, $id) {
            $type = (string) post('type');
            if (!isset(FEED_TYPES[$type])) throw new ValidationError(['type' => 'Choose a feed type.']);
            if (!feed_feature_ok($shop, $type)) throw new UserError('This feed type is not included in your package.');
            $name = clean_text(post('name'), 120) ?: FEED_TYPES[$type];
            $slug = slugify((string) (post('slug') ?: $name)) ?: 'feed';
            if ($slug === 'products' || $slug === 'rss') $slug .= '-feed';
            $slug = unique_slug('feeds', $sid, $slug, $id);
            $data = ['name' => $name, 'type' => $type, 'slug' => $slug,
                'filters' => jenc(['category_ids' => array_values(array_filter(array_map('intval', arr($_POST['category_ids'] ?? [])))), 'in_stock' => post('in_stock') ? 1 : 0, 'min_price' => to_cents(post('min_price'))]),
                'fields' => jenc(array_values(array_intersect(arr($_POST['fields'] ?? []), FEED_CUSTOM_FIELDS))), 'frequency_minutes' => max(5, min(1440, (int) post('frequency_minutes', 60))), 'active' => post('active') ? 1 : 0];
            if ($id) update('feeds', $data, 'id = ? AND shop_id = ?', [$id, $sid]); else $id = insert('feeds', $data + ['shop_id' => $sid, 'created_at' => now()]);
            audit('feed.saved', 'feed', $id, $sid);
            flash('ok', 'Feed saved.');
            return "/admin/feeds/$id/";
        }, $id ? "/admin/feeds/$id/" : '/admin/feeds/new/');
    }
    admin_render('feed-edit', ['f' => $f, 'cats' => categories_flat($sid)], $f ? 'Edit feed' : 'New feed');
}

/* ---------- Import ---------- */
function admin_import(): never {
    $shop = admin_need('products');
    if (is_post()) {
        if (!shop_feature($shop, 'import')) { flash('error', 'Imports are not included in your package.'); redirect('/admin/import/'); }
        handle(static function () use ($shop) {
            $enc = in_array(post('encoding'), ['UTF-8', 'Windows-1253', 'ISO-8859-7', 'Windows-1252'], true) ? post('encoding') : 'UTF-8';
            $id = import_upload($shop, $_FILES['file'] ?? [], $enc, (string) post('xml_item'));
            return "/admin/import/$id/";
        }, '/admin/import/');
    }
    admin_render('import', ['imports' => rows('SELECT * FROM imports WHERE shop_id = ? ORDER BY id DESC LIMIT 20', [$shop['id']]), 'cats' => categories_flat((int) $shop['id']), 'q' => shop_quota($shop), 'enabled' => shop_feature($shop, 'import')], 'Import / Export');
}
function admin_import_step(int $id): never {
    $shop = admin_need('products');
    $sid = (int) $shop['id'];
    $imp = owned('imports', $id, $sid);
    if (is_post()) {
        if (post('act') === 'cancel') handle(static function () use ($id, $sid) { q("UPDATE imports SET status = 'cancelled', finished_at = ? WHERE id = ? AND shop_id = ? AND status NOT IN ('completed')", [now(), $id, $sid]); import_log($id, $sid, 'warn', 'Cancelled by user.'); flash('ok', 'Import cancelled. Already processed rows are kept.'); }, "/admin/import/$id/");
        handle(static function () use ($shop, $id) { import_queue($shop, $id, arr($_POST['map'] ?? []), $_POST); flash('ok', 'Import queued — processing starts now.'); }, "/admin/import/$id/");
    }
    $preview = $imp['status'] === 'validated' ? import_preview($imp) : null;
    $errors = rows("SELECT row_num, message FROM import_rows WHERE import_id = ? AND status = 'error' ORDER BY id LIMIT 50", [$id]);
    $logs = rows('SELECT * FROM import_logs WHERE import_id = ? ORDER BY id DESC LIMIT 30', [$id]);
    admin_render('import-step', ['imp' => $imp, 'preview' => $preview, 'errors' => $errors, 'logs' => $logs, 'q' => shop_quota($shop)], 'Import: ' . $imp['filename']);
}
function admin_import_process(int $id): never {
    $shop = admin_need('products');
    owned('imports', $id, (int) $shop['id']);
    @set_time_limit(60);
    json_out(['ok' => true] + import_process($id, 8.0) + ['quota' => shop_quota(shop_by_id((int) $shop['id']))]);
}

/* ---------- SEO ---------- */
function admin_seo(): never {
    $shop = admin_need('seo');
    $sid = (int) $shop['id'];
    if (is_post()) handle(static function () use ($sid) {
        $data = ['site_title' => clean_text(post('site_title'), 120) ?: null, 'title_separator' => in_array(post('title_separator'), [' | ', ' — ', ' - ', ' · '], true) ? post('title_separator') : ' | ',
            'meta_description' => clean_text(post('meta_description'), 320) ?: null, 'og_image_id' => ($m = (int) post('og_image_id')) && val('SELECT 1 FROM media WHERE id = ? AND shop_id = ?', [$m, $sid]) ? $m : null,
            'organization_type' => in_array(post('organization_type'), ['Organization', 'OnlineStore', 'Store', 'LocalBusiness'], true) ? post('organization_type') : 'OnlineStore',
            'same_as' => implode("\n", array_filter(array_map('clean_url', preg_split('/\R/u', (string) post('same_as'))), static fn($u) => str_starts_with($u, 'https://'))),
            'gsc_verification' => preg_replace('/[^A-Za-z0-9_\-]/', '', (string) post('gsc_verification')) ?: null, 'ga4_id' => preg_match('/^G-[A-Z0-9]{4,15}$/', (string) post('ga4_id')) ? post('ga4_id') : null,
            'robots_extra' => clean_multiline(post('robots_extra'), 2000) ?: null, 'llms_extra' => clean_multiline(post('llms_extra'), 4000) ?: null, 'noindex_site' => post('noindex_site') ? 1 : 0, 'updated_at' => now()];
        if (val('SELECT 1 FROM seo_settings WHERE shop_id = ?', [$sid])) update('seo_settings', $data, 'shop_id = ?', [$sid]); else insert('seo_settings', $data + ['shop_id' => $sid]);
        audit('seo.settings_updated', 'shop', $sid, $sid);
        flash('ok', 'SEO settings saved.');
    }, '/admin/seo/');
    $diag = [
        'products_no_desc' => (int) val("SELECT COUNT(*) FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL AND (seo_description IS NULL OR seo_description = '')", [$sid]),
        'products_no_kw' => (int) val("SELECT COUNT(*) FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL AND (focus_keyword IS NULL OR focus_keyword = '')", [$sid]),
        'cats_no_desc' => (int) val("SELECT COUNT(*) FROM categories WHERE shop_id = ? AND (description IS NULL OR description = '')", [$sid]),
        'redirects' => (int) val('SELECT COUNT(*) FROM redirects WHERE shop_id = ? AND active = 1', [$sid]),
        'n404' => (int) val('SELECT COUNT(*) FROM not_found_log WHERE shop_id = ? AND ignored = 0', [$sid]),
    ];
    admin_render('seo', ['s' => seo_settings($sid), 'diag' => $diag, 'base' => rtrim(shop_public_url($shop), '/')], 'SEO');
}
function admin_redirects(): never {
    $shop = admin_need('seo');
    $sid = (int) $shop['id'];
    if (is_post()) handle(static function () use ($sid) {
        if (post('act') === 'delete') { $r = owned('redirects', (int) post('id'), $sid); q('DELETE FROM redirects WHERE id = ? AND shop_id = ?', [$r['id'], $sid]); flash('ok', 'Redirect deleted.'); return; }
        if (post('act') === 'toggle') { $r = owned('redirects', (int) post('id'), $sid); update('redirects', ['active' => $r['active'] ? 0 : 1], 'id = ?', [$r['id']]); return; }
        $id = (int) post('id') ?: null;
        if ($id) owned('redirects', $id, $sid);
        redirect_save($sid, $id, $_POST + ['active_present' => 1]);
        audit('redirect.saved', 'redirect', $id, $sid);
        flash('ok', 'Redirect saved.');
    }, '/admin/seo/redirects/');
    $pg = paginate((int) val('SELECT COUNT(*) FROM redirects WHERE shop_id = ?', [$sid]), 50);
    admin_render('redirects', ['redirects' => rows('SELECT * FROM redirects WHERE shop_id = ? ORDER BY id DESC LIMIT ? OFFSET ?', [$sid, $pg['limit'], $pg['offset']]), 'pg' => $pg, 'edit' => int_in('edit') ? owned('redirects', int_in('edit'), $sid) : null, 'prefill' => get('source')], 'Redirects');
}
function admin_404(): never {
    $shop = admin_need('seo');
    $sid = (int) $shop['id'];
    if (is_post()) handle(static function () use ($sid) {
        $act = post('act');
        if ($act === 'cleanup') { $n = q('DELETE FROM not_found_log WHERE shop_id = ? AND (ignored = 1 OR last_hit_at < ?)', [$sid, date('Y-m-d', strtotime('-90 days'))])->rowCount(); flash('ok', "$n entries removed."); return; }
        $row = owned('not_found_log', (int) post('id'), $sid);
        if ($act === 'ignore') { update('not_found_log', ['ignored' => 1], 'id = ?', [$row['id']]); flash('ok', 'Ignored.'); return; }
        $type = (int) post('type', 301);
        redirect_save($sid, null, ['source' => $row['path'], 'target' => post('target'), 'type' => $type]);
        flash('ok', "Redirect ($type) created for " . $row['path']);
    }, '/admin/seo/404/');
    $pg = paginate((int) val('SELECT COUNT(*) FROM not_found_log WHERE shop_id = ? AND ignored = 0', [$sid]), 50);
    $rows = rows('SELECT * FROM not_found_log WHERE shop_id = ? AND ignored = 0 ORDER BY hits DESC, last_hit_at DESC LIMIT ? OFFSET ?', [$sid, $pg['limit'], $pg['offset']]);
    foreach ($rows as &$r) $r['suggest'] = not_found_suggest($sid, $r['path']);
    admin_render('notfound', ['rows' => $rows, 'pg' => $pg], '404 monitor');
}
function admin_analyze(): never {
    $shop = admin_need('dashboard');
    $in = $_POST;
    $res = seo_analyze($in);
    $res['cannibal'] = seo_cannibalization((int) $shop['id'], (string) ($in['focus_keyword'] ?? ''), (string) ($in['type'] ?? 'product'), (int) ($in['id'] ?? 0));
    json_out(['ok' => true] + $res);
}

/* ---------- Users (staff) ---------- */
function admin_users(): never {
    $shop = admin_need('users');
    $users = rows('SELECT * FROM users WHERE shop_id = ? AND deleted_at IS NULL ORDER BY id', [$shop['id']]);
    foreach ($users as &$u) $u['perms'] = user_permissions($u);
    admin_render('users', ['users' => $users, 'limit' => shop_staff_limit($shop)], 'Users');
}
function admin_user_edit(?int $id): never {
    $shop = admin_need('users');
    $sid = (int) $shop['id'];
    $me = current_user();
    $myTier = $me['role'] === 'super_admin' ? 100 : role_tier($me['role']);
    $myPerms = $me['role'] === 'super_admin' ? array_keys(shop_permissions()) : user_permissions($me);
    $u = $id ? owned('users', $id, $sid) : null;
    if ($u && role_tier($u['role']) > $myTier) abort(403, 'You cannot edit a user with a higher role.');
    $roles = array_filter(['owner' => 'Owner', 'administrator' => 'Administrator', 'product_manager' => 'Product Manager', 'editor' => 'Editor'], static fn($r) => role_tier($r) <= $myTier && ($r !== 'owner' || in_array($me['role'], ['owner', 'super_admin'], true)), ARRAY_FILTER_USE_KEY);
    if (is_post()) {
        if (post('act') === 'delete' && $u) handle(static function () use ($u, $me, $sid) {
            if ((int) $u['id'] === (int) $me['id']) throw new UserError('You cannot delete yourself.');
            if ($u['role'] === 'owner' && (int) val("SELECT COUNT(*) FROM users WHERE shop_id = ? AND role = 'owner' AND deleted_at IS NULL", [$sid]) <= 1) throw new UserError('A shop needs at least one owner.');
            update('users', ['deleted_at' => now(), 'status' => 'disabled', 'email' => $u['email'] . '#deleted' . $u['id']], 'id = ? AND shop_id = ?', [$u['id'], $sid]);
            audit('user.deleted', 'user', (int) $u['id'], $sid);
            flash('ok', 'User removed.'); return '/admin/users/';
        }, "/admin/users/$id/");
        if (post('act') === 'invite' && $u) handle(static function () use ($u) {
            $tok = password_token_create('user', (int) $u['id'], 'invite', 24 * 7);
            $link = abs_url('/admin/set-password/?token=' . urlencode($tok));
            send_mail($u['email'], 'Your store admin access', "Set your password (valid 7 days): $link");
            $_SESSION['invite_link'] = $link;
            flash('ok', 'Invitation link generated and emailed.');
        }, "/admin/users/$id/");
        handle(static function () use ($shop, $sid, $id, $u, $roles, $myPerms, $me) {
            $email = mb_strtolower(clean_text(post('email'), 190));
            $role = (string) post('role');
            $err = [];
            if (!valid_email($email)) $err['email'] = 'Valid email required.';
            elseif (val('SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL' . ($id ? ' AND id <> ?' : ''), array_merge([$email], $id ? [$id] : []))) $err['email'] = 'This email already has an account.';
            if (!isset($roles[$role])) $err['role'] = 'You cannot assign this role.';
            if (!$id && (int) val('SELECT COUNT(*) FROM users WHERE shop_id = ? AND deleted_at IS NULL', [$sid]) >= shop_staff_limit($shop)) $err['email'] = 'Your package allows up to ' . shop_staff_limit($shop) . ' users.';
            if ($u && (int) $u['id'] === (int) $me['id'] && $role !== $u['role']) $err['role'] = 'You cannot change your own role.';
            if ($err) throw new ValidationError($err);
            // Staff permissions can never exceed the creator's own permissions.
            $perms = in_array($role, ['owner'], true) ? [] : array_values(array_intersect(arr($_POST['perms'] ?? []), $myPerms, array_keys(shop_permissions())));
            if (!in_array($role, ['owner'], true) && !$perms) $perms = array_values(array_intersect(col('SELECT permission_key FROM role_permissions WHERE role_key = ?', [$role]), $myPerms));
            $data = ['email' => $email, 'name' => clean_text(post('name'), 120), 'role' => $role, 'status' => post('status') === 'disabled' && (!$u || (int) $u['id'] !== (int) $me['id']) ? 'disabled' : 'active', 'updated_at' => now()];
            $id = tx(static function () use ($id, $data, $sid, $perms, $me) {
                if ($id) update('users', $data, 'id = ? AND shop_id = ?', [$id, $sid]);
                else $id = insert('users', $data + ['shop_id' => $sid, 'created_by' => $me['id'], 'created_at' => now()]);
                q('DELETE FROM user_permissions WHERE user_id = ?', [$id]);
                foreach ($perms as $p) insert('user_permissions', ['user_id' => $id, 'permission_key' => $p]);
                return $id;
            });
            audit($u ? 'user.updated' : 'user.created', 'user', $id, $sid, 'success', ['role' => $role, 'perms' => $perms]);
            if (!$u) {
                $tok = password_token_create('user', $id, 'invite', 24 * 7);
                $link = abs_url('/admin/set-password/?token=' . urlencode($tok));
                send_mail($email, 'Your store admin access', "You have been given access to {$shop['name']}.\nSet your password (valid 7 days): $link");
                $_SESSION['invite_link'] = $link;
            }
            flash('ok', 'User saved.');
            return "/admin/users/$id/";
        }, $id ? "/admin/users/$id/" : '/admin/users/new/');
    }
    $presets = [];
    foreach (array_keys($roles) as $r) $presets[$r] = col('SELECT permission_key FROM role_permissions WHERE role_key = ?', [$r]);
    $invite = $_SESSION['invite_link'] ?? null; unset($_SESSION['invite_link']);
    admin_render('user-edit', ['u' => $u, 'roles' => $roles, 'myPerms' => $myPerms, 'uperms' => $u ? user_permissions($u) : [], 'presets' => $presets, 'invite' => $invite], $u ? 'Edit user' : 'New user');
}

/* ---------- Settings ---------- */
function admin_settings(): never {
    $shop = admin_need('settings');
    $sid = (int) $shop['id'];
    if (is_post()) handle(static function () use ($sid) {
        foreach (['shipping_flat_cents', 'free_shipping_over_cents', 'cod_fee_cents'] as $k) shop_setting_set($sid, $k, (string) max(0, (int) to_cents(post(str_replace('_cents', '', $k)))));
        foreach (['payment_cod', 'payment_bank', 'pickup_enabled'] as $k) shop_setting_set($sid, $k, post($k) ? '1' : '0');
        shop_setting_set($sid, 'bank_details', clean_multiline(post('bank_details'), 1000) ?: null);
        $oe = mb_strtolower(clean_text(post('order_email'), 190));
        if ($oe !== '' && !valid_email($oe)) throw new ValidationError(['order_email' => 'Invalid email.']);
        shop_setting_set($sid, 'order_email', $oe ?: null);
        shop_setting_set($sid, 'settings_reviewed', '1');
        if (in_array(post('locale'), ['el', 'en'], true)) update('shops', ['locale' => post('locale')], 'id = ?', [$sid]);
        audit('settings.updated', 'shop', $sid, $sid);
        flash('ok', 'Settings saved.');
    }, '/admin/settings/');
    $plan = plan_by_id((int) $shop['plan_id']);
    admin_render('settings', ['plan' => $plan, 'features' => plan_features((int) $shop['plan_id']), 'q' => shop_quota($shop)], 'Settings');
}
function admin_notifications(): never {
    $shop = admin_need('dashboard');
    if (is_post()) { q("UPDATE notifications SET read_at = ? WHERE shop_id = ? AND audience = 'shop' AND read_at IS NULL", [now(), $shop['id']]); redirect('/admin/notifications/'); }
    admin_render('notifications', ['items' => rows("SELECT * FROM notifications WHERE shop_id = ? AND audience = 'shop' ORDER BY id DESC LIMIT 100", [$shop['id']])], 'Notifications');
}
