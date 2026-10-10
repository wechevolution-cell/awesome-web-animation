<?php
declare(strict_types=1);

class QuotaError extends UserError {}

/**
 * SQL fragment for products visible on the public storefront.
 * Active + not deleted + within the package's active limit (ordered by activation time),
 * so even direct DB tampering or a pending downgrade can never expose more than the quota.
 */
function visible_where(array $shop, string $alias = 'p'): array {
    $sid = (int) $shop['id'];
    $limit = shop_active_limit($shop);
    $sql = "$alias.shop_id = ? AND $alias.status = 'active' AND $alias.deleted_at IS NULL AND $alias.id IN (SELECT id FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL ORDER BY activated_at, id LIMIT ?)";
    return [$sql, [$sid, $sid, $limit]];
}

function quota_message(int $limit, int $active, int $requested = 1): string {
    if ($requested <= 1) return "Cannot activate product. Your package permits up to $limit active products and you currently have $active active.";
    $avail = max(0, $limit - $active);
    return "Cannot activate $requested products. Your package permits up to $limit active products, you currently have $active active, so only $avail more can be activated.";
}

/** Activate/deactivate with strict server-side quota enforcement (all-or-nothing for bulk). */
function product_set_status(array $shop, array $ids, string $status): int {
    $sid = (int) $shop['id'];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids || !in_array($status, ['active', 'inactive'], true)) return 0;
    return tx(static function () use ($shop, $sid, $ids, $status) {
        $owned = col('SELECT id FROM products WHERE shop_id = ? AND deleted_at IS NULL AND id IN (' . in_ph($ids) . ')', array_merge([$sid], $ids));
        if (count($owned) !== count($ids)) {
            audit('tenant.cross_access_denied', 'products', null, $sid, 'denied', ['ids' => array_values(array_diff($ids, array_map('intval', $owned)))]);
            abort(403, 'Access denied.');
        }
        if ($status === 'active') {
            $toActivate = col("SELECT id FROM products WHERE shop_id = ? AND status <> 'active' AND deleted_at IS NULL AND id IN (" . in_ph($ids) . ')', array_merge([$sid], $ids));
            if (!$toActivate) return 0;
            $limit = shop_active_limit($shop);
            $active = product_active_count($sid);
            if ($active + count($toActivate) > $limit) {
                audit('product.activate_blocked', 'product', count($toActivate) === 1 ? (int) $toActivate[0] : null, $sid, 'denied', ['limit' => $limit, 'active' => $active, 'requested' => count($toActivate)]);
                throw new QuotaError(quota_message($limit, $active, count($toActivate)));
            }
            q("UPDATE products SET status = 'active', activated_at = ?, updated_at = ? WHERE shop_id = ? AND id IN (" . in_ph($toActivate) . ')', array_merge([now(), now(), $sid], $toActivate));
            audit('product.activated', 'product', count($toActivate) === 1 ? (int) $toActivate[0] : null, $sid, 'success', ['count' => count($toActivate)]);
            return count($toActivate);
        }
        $n = q("UPDATE products SET status = 'inactive', activated_at = NULL, updated_at = ? WHERE shop_id = ? AND status = 'active' AND id IN (" . in_ph($ids) . ')', array_merge([now(), $sid], $ids))->rowCount();
        if ($n) audit('product.deactivated', 'product', count($ids) === 1 ? $ids[0] : null, $sid, 'success', ['count' => $n]);
        if ((int) $shop['quota_over'] && product_active_count($sid) <= shop_active_limit($shop)) update('shops', ['quota_over' => 0], 'id = ?', [$sid]);
        return $n;
    }, true);
}

function unique_slug(string $table, int $shopId, string $slug, ?int $exceptId = null, string $col = 'slug'): string {
    $base = $slug !== '' ? $slug : 'item';
    $soft = in_array($table, ['products', 'pages'], true) ? ' AND deleted_at IS NULL' : '';
    $s = $base; $i = 2;
    while (val("SELECT 1 FROM $table WHERE shop_id = ? AND $col = ?$soft" . ($exceptId ? ' AND id <> ?' : ''), array_merge([$shopId, $s], $exceptId ? [$exceptId] : []))) $s = $base . '-' . $i++;
    return $s;
}

/**
 * Create / update a product. $in is raw form input. Returns product id.
 * Status requests go through product_set_status so the quota is always respected.
 */
function product_save(array $shop, ?int $id, array $in): int {
    $sid = (int) $shop['id'];
    $existing = $id ? owned('products', $id, $sid) : null;
    $errors = [];
    $name = clean_text($in['name'] ?? '', 200);
    if ($name === '') $errors['name'] = 'Product name is required.';
    $price = to_cents($in['price'] ?? '');
    if ($price === null || $price < 0) $errors['price'] = 'Enter a valid price.';
    $sale = to_cents($in['sale_price'] ?? '');
    if ($sale !== null && $price !== null && $sale >= $price) $errors['sale_price'] = 'Sale price must be lower than the price.';
    $sku = clean_text($in['sku'] ?? '', 64);
    if ($sku !== '' && val('SELECT 1 FROM products WHERE shop_id = ? AND sku = ? AND deleted_at IS NULL' . ($id ? ' AND id <> ?' : ''), array_merge([$sid, $sku], $id ? [$id] : []))) $errors['sku'] = 'Another product already uses this SKU.';
    if (!$existing && product_stored_count($sid) >= shop_stored_limit($shop)) $errors['name'] = 'Storage limit reached (' . shop_stored_limit($shop) . ' products). Delete unused products first.';
    if ($errors) throw new ValidationError($errors);

    $slug = slugify((string) ($in['slug'] ?? '') ?: $name);
    $slug = unique_slug('products', $sid, $slug, $id);
    $catIds = array_values(array_unique(array_filter(array_map('intval', arr($in['categories'] ?? [])))));
    if ($catIds) $catIds = array_map('intval', col('SELECT id FROM categories WHERE shop_id = ? AND id IN (' . in_ph($catIds) . ')', array_merge([$sid], $catIds)));
    $primaryCat = (int) ($in['primary_category_id'] ?? 0);
    if (!in_array($primaryCat, $catIds, true)) $primaryCat = $catIds[0] ?? 0;
    $data = [
        'name' => $name, 'slug' => $slug, 'sku' => $sku ?: null, 'barcode' => clean_text($in['barcode'] ?? '', 64) ?: null,
        'short_description' => clean_multiline($in['short_description'] ?? '', 1000), 'description' => sanitize_html($in['description'] ?? ''),
        'price_cents' => $price, 'sale_price_cents' => $sale, 'cost_price_cents' => to_cents($in['cost_price'] ?? ''),
        'stock' => max(0, (int) ($in['stock'] ?? 0)), 'manage_stock' => empty($in['manage_stock']) ? 0 : 1,
        'weight_g' => ($in['weight_g'] ?? '') !== '' ? max(0, (int) $in['weight_g']) : null,
        'length_mm' => ($in['length_mm'] ?? '') !== '' ? max(0, (int) $in['length_mm']) : null,
        'width_mm' => ($in['width_mm'] ?? '') !== '' ? max(0, (int) $in['width_mm']) : null,
        'height_mm' => ($in['height_mm'] ?? '') !== '' ? max(0, (int) $in['height_mm']) : null,
        'brand' => clean_text($in['brand'] ?? '', 120) ?: null, 'primary_category_id' => $primaryCat ?: null, 'featured' => empty($in['featured']) ? 0 : 1,
        'seo_title' => clean_text($in['seo_title'] ?? '', 120) ?: null, 'seo_description' => clean_text($in['seo_description'] ?? '', 320) ?: null,
        'focus_keyword' => clean_text($in['focus_keyword'] ?? '', 120) ?: null, 'canonical' => clean_url($in['canonical'] ?? '') ?: null,
        'robots' => in_array($in['robots'] ?? '', ['noindex', 'noindex,nofollow'], true) ? $in['robots'] : null,
        'faq' => jenc(faq_parse((string) ($in['faq'] ?? ''))), 'updated_at' => now(),
    ];
    $wantActive = ($in['status'] ?? 'inactive') === 'active';
    $pid = tx(static function () use ($sid, $id, $existing, $data, $catIds, $in, $shop) {
        if ($existing) {
            update('products', $data, 'id = ? AND shop_id = ?', [$id, $sid]);
            if ($existing['slug'] !== $data['slug']) redirect_auto($sid, '/product/' . $existing['slug'] . '/', '/product/' . $data['slug'] . '/');
            $pid = $id;
        } else {
            $pid = insert('products', $data + ['shop_id' => $sid, 'status' => 'inactive', 'created_at' => now()]);
        }
        q('DELETE FROM category_relationships WHERE product_id = ? AND shop_id = ?', [$pid, $sid]);
        foreach ($catIds as $c) insert('category_relationships', ['shop_id' => $sid, 'product_id' => $pid, 'category_id' => $c]);
        if (isset($in['images'])) {
            q('DELETE FROM product_images WHERE product_id = ? AND shop_id = ?', [$pid, $sid]);
            $mids = array_values(array_unique(array_filter(array_map('intval', arr($in['images'])))));
            $valid = $mids ? array_map('intval', col('SELECT id FROM media WHERE shop_id = ? AND id IN (' . in_ph($mids) . ')', array_merge([$sid], $mids))) : [];
            $pos = 0;
            foreach ($mids as $m) if (in_array($m, $valid, true)) insert('product_images', ['shop_id' => $sid, 'product_id' => $pid, 'media_id' => $m, 'position' => $pos++]);
        }
        if (isset($in['variant_options'])) {
            $keep = [];
            foreach (arr($in['variant_options']) as $i => $opt) {
                $opts = [];
                foreach (preg_split('/\s*[,;]\s*/u', clean_text($opt, 300)) as $pair) {
                    if (str_contains($pair, ':')) { [$k, $v] = array_map('trim', explode(':', $pair, 2)); if ($k !== '' && $v !== '') $opts[$k] = $v; }
                }
                if (!$opts) continue;
                $vid = (int) ($in['variant_id'][$i] ?? 0);
                $vd = ['options' => jenc($opts), 'sku' => clean_text($in['variant_sku'][$i] ?? '', 64) ?: null, 'price_cents' => to_cents($in['variant_price'][$i] ?? ''), 'stock' => max(0, (int) ($in['variant_stock'][$i] ?? 0)), 'position' => (int) $i];
                if ($vid && val('SELECT 1 FROM product_variants WHERE id = ? AND product_id = ? AND shop_id = ?', [$vid, $pid, $sid])) { update('product_variants', $vd, 'id = ?', [$vid]); $keep[] = $vid; }
                else $keep[] = insert('product_variants', $vd + ['shop_id' => $sid, 'product_id' => $pid]);
            }
            q('DELETE FROM product_variants WHERE product_id = ? AND shop_id = ?' . ($keep ? ' AND id NOT IN (' . in_ph($keep) . ')' : ''), array_merge([$pid, $sid], $keep));
        }
        if (isset($in['attr_name'])) {
            q('DELETE FROM product_attributes WHERE product_id = ? AND shop_id = ?', [$pid, $sid]);
            foreach (arr($in['attr_name']) as $i => $n) {
                $n = clean_text($n, 80); $v = clean_text($in['attr_value'][$i] ?? '', 255);
                if ($n !== '' && $v !== '') insert('product_attributes', ['shop_id' => $sid, 'product_id' => $pid, 'name' => $n, 'value' => $v, 'position' => (int) $i]);
            }
        }
        audit($existing ? 'product.updated' : 'product.created', 'product', $pid, $sid);
        return $pid;
    }, true);
    $current = val('SELECT status FROM products WHERE id = ?', [$pid]);
    $GLOBALS['QUOTA_WARNING'] = null;
    try {
        if ($wantActive && $current !== 'active') product_set_status($shop, [$pid], 'active');
        if (!$wantActive && $current === 'active') product_set_status($shop, [$pid], 'inactive');
    } catch (QuotaError $e) {
        $GLOBALS['QUOTA_WARNING'] = $e->getMessage(); // product saved, kept inactive
    }
    return $pid;
}

/** Soft delete (frees an active slot, keeps order history intact). */
function product_delete(array $shop, array $ids): int {
    $sid = (int) $shop['id'];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return 0;
    $n = q("UPDATE products SET deleted_at = ?, status = 'inactive', activated_at = NULL, sku = CASE WHEN sku IS NULL THEN NULL ELSE sku || '~del' || id END WHERE shop_id = ? AND deleted_at IS NULL AND id IN (" . in_ph($ids) . ')', array_merge([now(), $sid], $ids))->rowCount();
    audit('product.deleted', 'product', count($ids) === 1 ? $ids[0] : null, $sid, 'success', ['count' => $n]);
    return $n;
}

function product_bulk(array $shop, string $action, array $ids, array $in = []): string {
    $sid = (int) $shop['id'];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) throw new UserError('Select at least one product.');
    $owned = col('SELECT id FROM products WHERE shop_id = ? AND deleted_at IS NULL AND id IN (' . in_ph($ids) . ')', array_merge([$sid], $ids));
    if (count($owned) !== count($ids)) abort(403, 'Access denied.');
    switch ($action) {
        case 'activate': $n = product_set_status($shop, $ids, 'active'); return "$n product(s) activated.";
        case 'deactivate': $n = product_set_status($shop, $ids, 'inactive'); return "$n product(s) deactivated.";
        case 'delete': $n = product_delete($shop, $ids); return "$n product(s) deleted.";
        case 'assign_category':
            $cid = (int) ($in['category_id'] ?? 0);
            if (!val('SELECT 1 FROM categories WHERE id = ? AND shop_id = ?', [$cid, $sid])) throw new UserError('Choose a category.');
            tx(static function () use ($ids, $cid, $sid) {
                foreach ($ids as $pid) q('INSERT OR IGNORE INTO category_relationships(shop_id, product_id, category_id) VALUES (?,?,?)', [$sid, $pid, $cid]);
                q('UPDATE products SET primary_category_id = COALESCE(primary_category_id, ?), updated_at = ? WHERE shop_id = ? AND id IN (' . in_ph($ids) . ')', array_merge([$cid, now(), $sid], $ids));
            });
            audit('product.bulk_category', 'category', $cid, $sid, 'success', ['count' => count($ids)]);
            return count($ids) . ' product(s) assigned to the category.';
        case 'price':
            $mode = $in['price_mode'] ?? 'percent';
            $amount = (float) str_replace(',', '.', (string) ($in['price_value'] ?? '0'));
            if ($amount == 0.0) throw new UserError('Enter a price change.');
            tx(static function () use ($ids, $sid, $mode, $amount) {
                foreach (rows('SELECT id, price_cents FROM products WHERE shop_id = ? AND id IN (' . in_ph($ids) . ')', array_merge([$sid], $ids)) as $p) {
                    $new = $mode === 'percent' ? (int) round($p['price_cents'] * (1 + $amount / 100)) : (int) round($p['price_cents'] + $amount * 100);
                    q('UPDATE products SET price_cents = ?, sale_price_cents = CASE WHEN sale_price_cents >= ? THEN NULL ELSE sale_price_cents END, updated_at = ? WHERE id = ? AND shop_id = ?', [max(0, $new), max(0, $new), now(), $p['id'], $sid]);
                }
            });
            audit('product.bulk_price', 'product', null, $sid, 'success', ['count' => count($ids), 'mode' => $mode, 'value' => $amount]);
            return count($ids) . ' price(s) updated.';
    }
    throw new UserError('Unknown bulk action.');
}

/* ---------- Read helpers ---------- */
function product_effective_price(array $p): int { return ($p['sale_price_cents'] !== null && $p['sale_price_cents'] !== '' && (int) $p['sale_price_cents'] < (int) $p['price_cents']) ? (int) $p['sale_price_cents'] : (int) $p['price_cents']; }
function product_on_sale(array $p): bool { return product_effective_price($p) < (int) $p['price_cents']; }
function product_in_stock(array $p): bool { return !(int) $p['manage_stock'] || (int) $p['stock'] > 0; }

/** Attach first image to a list of products (one query). */
function products_attach_images(array $products): array {
    if (!$products) return $products;
    $ids = array_column($products, 'id');
    $imgs = [];
    foreach (rows('SELECT pi.product_id, m.* FROM product_images pi JOIN media m ON m.id = pi.media_id WHERE pi.product_id IN (' . in_ph($ids) . ') ORDER BY pi.position', $ids) as $r) $imgs[$r['product_id']][] = $r;
    $withVar = array_flip(array_map('intval', col('SELECT DISTINCT product_id FROM product_variants WHERE product_id IN (' . in_ph($ids) . ')', $ids)));
    foreach ($products as &$p) { $p['images'] = $imgs[$p['id']] ?? []; $p['image'] = $p['images'][0] ?? null; $p['_has_variants'] = isset($withVar[(int) $p['id']]); }
    return $products;
}
function product_variants(int $pid): array { return array_map(static fn($v) => $v + ['opts' => jdec($v['options'])], rows('SELECT * FROM product_variants WHERE product_id = ? ORDER BY position, id', [$pid])); }
function product_attributes(int $pid): array { return rows('SELECT name, value FROM product_attributes WHERE product_id = ? ORDER BY position, id', [$pid]); }
function variant_label(array $opts): string { return implode(' / ', array_map(static fn($k, $v) => "$k: $v", array_keys($opts), $opts)); }
