<?php
declare(strict_types=1);

const FEED_TYPES = ['generic' => 'Generic product XML', 'skroutz' => 'Skroutz', 'bestprice' => 'BestPrice', 'custom' => 'Custom XML'];
const FEED_CUSTOM_FIELDS = ['id', 'sku', 'name', 'url', 'image', 'images', 'price', 'sale_price', 'final_price', 'currency', 'availability', 'stock', 'brand', 'barcode', 'category', 'description', 'weight'];

function feed_feature_ok(array $shop, string $type): bool {
    if (!shop_feature($shop, 'feeds')) return false;
    if (in_array($type, ['skroutz', 'bestprice'], true)) return shop_feature($shop, 'marketplace_feeds');
    if ($type === 'custom') return shop_feature($shop, 'custom_feeds');
    return true;
}

/** Iterate visible products (public feeds only ever expose active, in-quota products). */
function feed_products(array $shop, array $filters, callable $each): int {
    [$w, $pp] = visible_where($shop);
    $sid = (int) $shop['id'];
    $where = $w; $params = $pp;
    if (!empty($filters['category_ids'])) {
        $ids = [];
        foreach (arr($filters['category_ids']) as $c) $ids = array_merge($ids, category_descendant_ids($sid, (int) $c));
        $ids = $ids ?: [0];
        $where .= ' AND p.id IN (SELECT product_id FROM category_relationships WHERE category_id IN (' . in_ph($ids) . '))';
        $params = array_merge($params, $ids);
    }
    if (!empty($filters['in_stock'])) $where .= ' AND (p.manage_stock = 0 OR p.stock > 0)';
    if (!empty($filters['min_price'])) { $where .= ' AND p.price_cents >= ?'; $params[] = (int) $filters['min_price']; }
    $cats = []; foreach (rows('SELECT id, name, path, parent_id FROM categories WHERE shop_id = ?', [$sid]) as $c) $cats[$c['id']] = $c;
    $n = 0; $last = 0;
    do {
        $chunk = rows("SELECT p.* FROM products p WHERE $where AND p.id > ? ORDER BY p.id LIMIT 300", array_merge($params, [$last]));
        $chunk = products_attach_images($chunk);
        foreach ($chunk as $p) {
            $p['category_path'] = feed_category_path($cats, $p['primary_category_id'] ? (int) $p['primary_category_id'] : null);
            $each($p); $n++; $last = (int) $p['id'];
        }
    } while (count($chunk) === 300);
    return $n;
}
function feed_category_path(array $cats, ?int $id): string {
    $names = []; $guard = 0;
    while ($id && isset($cats[$id]) && $guard++ < 10) { array_unshift($names, $cats[$id]['name']); $id = $cats[$id]['parent_id'] ? (int) $cats[$id]['parent_id'] : null; }
    return implode(' > ', $names);
}

/** Build feed XML into a cache file (streamed with XMLWriter), regenerated per frequency. */
function feed_build(array $shop, array $feed, string $baseUrl): string {
    $sid = (int) $shop['id'];
    $dir = DATA . '/feeds/' . $sid;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/' . $feed['slug'] . '-' . substr(md5($baseUrl), 0, 8) . '.xml';
    $fresh = is_file($file) && (time() - filemtime($file)) < max(5, (int) $feed['frequency_minutes']) * 60;
    if ($fresh && empty($feed['_force'])) return $file;
    $t0 = microtime(true);
    $tmp = $file . '.tmp';
    $x = new XMLWriter();
    $x->openUri($tmp);
    $x->setIndent(true);
    $x->startDocument('1.0', 'UTF-8');
    $abs = static fn(string $u) => preg_match('#^https?://#', $u) ? $u : rtrim($baseUrl, '/') . $u;
    $prodUrl = static fn(array $p) => rtrim($baseUrl, '/') . '/product/' . $p['slug'] . '/';
    $avail = static fn(array $p, bool $el = true) => product_in_stock($p) ? ($el ? 'Σε απόθεμα' : 'In stock') : ($el ? 'Εξαντλημένο' : 'Out of stock');
    $filters = jdec($feed['filters'] ?? null);
    $type = $feed['type'];
    $count = 0;
    if ($type === 'skroutz') {
        $x->startElement('mywebstore'); $x->writeElement('created_at', date('Y-m-d H:i')); $x->startElement('products');
        $count = feed_products($shop, $filters, static function ($p) use ($x, $abs, $prodUrl, $avail) {
            $x->startElement('product');
            $x->writeElement('id', (string) $p['id']); $x->startElement('name'); $x->writeCdata($p['name']); $x->endElement();
            $x->writeElement('link', $prodUrl($p));
            $x->writeElement('image', $p['image'] ? $abs(media_url($p['image'])) : '');
            foreach (array_slice($p['images'], 1, 10) as $im) $x->writeElement('additional_imageurl', $abs(media_url($im)));
            $x->startElement('category'); $x->writeCdata($p['category_path']); $x->endElement();
            $x->writeElement('price_with_vat', number_format(product_effective_price($p) / 100, 2, '.', ''));
            $x->writeElement('vat', '24');
            $x->startElement('manufacturer'); $x->writeCdata((string) $p['brand']); $x->endElement();
            $x->writeElement('mpn', (string) $p['sku']); $x->writeElement('ean', (string) $p['barcode']);
            $x->writeElement('availability', $avail($p));
            $x->writeElement('quantity', (string) max(0, (int) $p['stock']));
            if ($p['weight_g']) $x->writeElement('weight', (string) $p['weight_g']);
            $x->startElement('description'); $x->writeCdata(str_limit((string) ($p['short_description'] ?: $p['description']), 2000)); $x->endElement();
            $x->endElement();
        });
        $x->endElement(); $x->endElement();
    } elseif ($type === 'bestprice') {
        $x->startElement('store'); $x->writeElement('date', date('Y-m-d H:i')); $x->startElement('products');
        $count = feed_products($shop, $filters, static function ($p) use ($x, $abs, $prodUrl, $avail) {
            $x->startElement('product');
            $x->writeElement('productId', (string) $p['id']); $x->startElement('title'); $x->writeCdata($p['name']); $x->endElement();
            $x->writeElement('productURL', $prodUrl($p)); $x->writeElement('imageURL', $p['image'] ? $abs(media_url($p['image'])) : '');
            $x->writeElement('price', number_format(product_effective_price($p) / 100, 2, '.', ''));
            $x->startElement('category_path'); $x->writeCdata($p['category_path']); $x->endElement();
            $x->startElement('brand'); $x->writeCdata((string) $p['brand']); $x->endElement();
            $x->writeElement('EAN', (string) $p['barcode']); $x->writeElement('MPN', (string) $p['sku']);
            $x->writeElement('availability', $avail($p)); $x->writeElement('stock', product_in_stock($p) ? 'Y' : 'N');
            $x->endElement();
        });
        $x->endElement(); $x->endElement();
    } else {
        $fields = $type === 'custom' ? array_values(array_intersect(arr(jdec($feed['fields'] ?? null)), FEED_CUSTOM_FIELDS)) : FEED_CUSTOM_FIELDS;
        if (!$fields) $fields = ['id', 'name', 'url', 'final_price', 'availability'];
        $x->startElement('products'); $x->writeAttribute('generated', date('c')); $x->writeAttribute('shop', $shop['name']);
        $count = feed_products($shop, $filters, static function ($p) use ($x, $abs, $prodUrl, $fields, $shop) {
            $x->startElement('product');
            foreach ($fields as $f) {
                $v = match ($f) {
                    'id' => (string) $p['id'], 'sku' => (string) $p['sku'], 'name' => $p['name'], 'url' => $prodUrl($p),
                    'image' => $p['image'] ? $abs(media_url($p['image'])) : '', 'images' => implode('|', array_map(static fn($m) => $abs(media_url($m)), $p['images'])),
                    'price' => number_format($p['price_cents'] / 100, 2, '.', ''), 'sale_price' => $p['sale_price_cents'] !== null ? number_format($p['sale_price_cents'] / 100, 2, '.', '') : '',
                    'final_price' => number_format(product_effective_price($p) / 100, 2, '.', ''), 'currency' => $shop['currency'],
                    'availability' => product_in_stock($p) ? 'in_stock' : 'out_of_stock', 'stock' => (string) $p['stock'], 'brand' => (string) $p['brand'],
                    'barcode' => (string) $p['barcode'], 'category' => $p['category_path'], 'description' => str_limit((string) ($p['short_description'] ?: $p['description']), 2000), 'weight' => (string) $p['weight_g'],
                    default => '',
                };
                if (in_array($f, ['name', 'description', 'category', 'brand'], true)) { $x->startElement($f); $x->writeCdata($v); $x->endElement(); }
                else $x->writeElement($f, $v);
            }
            $x->endElement();
        });
        $x->endElement();
    }
    $x->endDocument(); $x->flush(); unset($x);
    rename($tmp, $file);
    $ms = (int) ((microtime(true) - $t0) * 1000);
    if (!empty($feed['id'])) {
        update('feeds', ['last_generated_at' => now(), 'last_count' => $count], 'id = ?', [$feed['id']]);
        insert('feed_logs', ['shop_id' => $sid, 'feed_id' => $feed['id'], 'items' => $count, 'duration_ms' => $ms, 'status' => 'ok', 'created_at' => now()]);
    }
    return $file;
}

/** RSS of the newest visible products. */
function rss_products(array $shop, string $baseUrl): string {
    [$w, $pp] = visible_where($shop);
    $items = products_attach_images(rows("SELECT p.* FROM products p WHERE $w ORDER BY p.activated_at DESC, p.id DESC LIMIT 30", $pp));
    $x = new XMLWriter(); $x->openMemory(); $x->setIndent(true); $x->startDocument('1.0', 'UTF-8');
    $x->startElement('rss'); $x->writeAttribute('version', '2.0'); $x->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
    $x->startElement('channel');
    $x->writeElement('title', $shop['name']); $x->writeElement('link', rtrim($baseUrl, '/') . '/'); $x->writeElement('description', str_limit((string) shop_setting((int) $shop['id'], 'tagline', $shop['name']), 300));
    $x->writeElement('language', $shop['locale']);
    $x->startElement('atom:link'); $x->writeAttribute('href', rtrim($baseUrl, '/') . '/feed/rss.xml'); $x->writeAttribute('rel', 'self'); $x->writeAttribute('type', 'application/rss+xml'); $x->endElement();
    foreach ($items as $p) {
        $u = rtrim($baseUrl, '/') . '/product/' . $p['slug'] . '/';
        $x->startElement('item'); $x->writeElement('title', $p['name']); $x->writeElement('link', $u); $x->writeElement('guid', $u);
        $x->writeElement('pubDate', date(DATE_RSS, strtotime((string) ($p['activated_at'] ?: $p['created_at']))));
        $x->writeElement('description', str_limit((string) ($p['short_description'] ?: $p['description']), 400) . ' — ' . money(product_effective_price($p), $shop['locale']));
        $x->endElement();
    }
    $x->endElement(); $x->endElement(); $x->endDocument();
    return $x->outputMemory();
}
