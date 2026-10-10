<?php
declare(strict_types=1);

function categories_all(int $shopId): array { return rows('SELECT * FROM categories WHERE shop_id = ? ORDER BY depth, position, name', [$shopId]); }

/** Nested tree: each node gets 'children'. */
function categories_tree(int $shopId, ?array $all = null): array {
    $all ??= categories_all($shopId);
    $children = [];
    foreach ($all as $c) $children[(int) $c['parent_id']][] = $c;
    $build = static function (int $pid, int $guard = 0) use (&$build, $children): array {
        $out = [];
        if ($guard > 20) return $out;
        foreach ($children[$pid] ?? [] as $c) { $c['children'] = $build((int) $c['id'], $guard + 1); $out[] = $c; }
        return $out;
    };
    return $build(0);
}
/** Flat list in tree order with depth (for selects). */
function categories_flat(int $shopId): array {
    $out = [];
    $walk = static function (array $nodes) use (&$walk, &$out): void { foreach ($nodes as $n) { $ch = $n['children']; unset($n['children']); $out[] = $n; $walk($ch); } };
    $walk(categories_tree($shopId));
    return $out;
}
function category_descendant_ids(int $shopId, int $catId): array {
    $path = val('SELECT path FROM categories WHERE id = ? AND shop_id = ?', [$catId, $shopId]);
    if ($path === null) return [];
    return array_map('intval', col("SELECT id FROM categories WHERE shop_id = ? AND (path = ? OR path LIKE ?)", [$shopId, $path, $path . '/%']));
}
function category_ancestors(int $shopId, array $cat): array {
    $out = []; $guard = 0;
    while ($cat && $cat['parent_id'] && $guard++ < 20) {
        $cat = row('SELECT * FROM categories WHERE id = ? AND shop_id = ?', [$cat['parent_id'], $shopId]);
        if ($cat) array_unshift($out, $cat);
    }
    return $out;
}

function category_save(int $shopId, ?int $id, array $in): int {
    $existing = $id ? owned('categories', $id, $shopId) : null;
    $name = clean_text($in['name'] ?? '', 120);
    if ($name === '') throw new ValidationError(['name' => 'Category name is required.']);
    $parentId = (int) ($in['parent_id'] ?? 0) ?: null;
    $parent = null;
    if ($parentId) {
        $parent = row('SELECT * FROM categories WHERE id = ? AND shop_id = ?', [$parentId, $shopId]);
        if (!$parent) throw new ValidationError(['parent_id' => 'Invalid parent.']);
        if ($existing && ($parent['id'] == $existing['id'] || str_starts_with($parent['path'] . '/', $existing['path'] . '/'))) throw new ValidationError(['parent_id' => 'A category cannot be placed inside itself.']);
    }
    $slug = slugify((string) ($in['slug'] ?? '') ?: $name) ?: 'category';
    $reserved = store_reserved_slugs();
    if (!$parent && in_array($slug, $reserved, true)) $slug .= '-c';
    $path = ($parent ? $parent['path'] . '/' : '') . $slug;
    $i = 2; $base = $slug;
    while (val('SELECT 1 FROM categories WHERE shop_id = ? AND path = ?' . ($id ? ' AND id <> ?' : ''), array_merge([$shopId, $path], $id ? [$id] : []))
        || (!$parent && val("SELECT 1 FROM pages WHERE shop_id = ? AND slug = ? AND deleted_at IS NULL", [$shopId, $slug]))) {
        $slug = $base . '-' . $i++;
        $path = ($parent ? $parent['path'] . '/' : '') . $slug;
    }
    $data = [
        'name' => $name, 'slug' => $slug, 'path' => $path, 'parent_id' => $parentId, 'depth' => $parent ? (int) $parent['depth'] + 1 : 0,
        'description' => sanitize_html($in['description'] ?? ''), 'position' => (int) ($in['position'] ?? 0),
        'image_id' => ($mid = (int) ($in['image_id'] ?? 0)) && val('SELECT 1 FROM media WHERE id = ? AND shop_id = ?', [$mid, $shopId]) ? $mid : null,
        'seo_title' => clean_text($in['seo_title'] ?? '', 120) ?: null, 'seo_description' => clean_text($in['seo_description'] ?? '', 320) ?: null,
        'focus_keyword' => clean_text($in['focus_keyword'] ?? '', 120) ?: null, 'canonical' => clean_url($in['canonical'] ?? '') ?: null,
        'robots' => in_array($in['robots'] ?? '', ['noindex', 'noindex,nofollow'], true) ? $in['robots'] : null,
        'faq' => jenc(faq_parse((string) ($in['faq'] ?? ''))), 'updated_at' => now(),
    ];
    return tx(static function () use ($shopId, $id, $existing, $data) {
        if ($existing) {
            update('categories', $data, 'id = ? AND shop_id = ?', [$id, $shopId]);
            if ($existing['path'] !== $data['path']) {
                // Re-path descendants and keep old URLs alive with 301s.
                foreach (rows('SELECT id, path, depth FROM categories WHERE shop_id = ? AND path LIKE ?', [$shopId, $existing['path'] . '/%']) as $d) {
                    $np = $data['path'] . substr($d['path'], strlen($existing['path']));
                    update('categories', ['path' => $np, 'depth' => substr_count($np, '/')], 'id = ?', [$d['id']]);
                }
                redirect_auto($shopId, '/' . $existing['path'] . '/', '/' . $data['path'] . '/', 'regex_prefix');
            }
            audit('category.updated', 'category', $id, $shopId);
            return $id;
        }
        $nid = insert('categories', $data + ['shop_id' => $shopId, 'created_at' => now()]);
        audit('category.created', 'category', $nid, $shopId);
        return $nid;
    }, true);
}

function category_delete(int $shopId, int $id): void {
    $c = owned('categories', $id, $shopId);
    tx(static function () use ($shopId, $c) {
        // Children move up to the deleted category's parent; products stay (just lose this relation).
        foreach (rows('SELECT id FROM categories WHERE parent_id = ? AND shop_id = ?', [$c['id'], $shopId]) as $ch) {
            $ch = row('SELECT * FROM categories WHERE id = ?', [$ch['id']]);
            category_save($shopId, (int) $ch['id'], array_merge($ch, ['parent_id' => $c['parent_id'], 'faq' => faq_to_text(jdec($ch['faq']))]));
        }
        q('UPDATE products SET primary_category_id = NULL WHERE primary_category_id = ? AND shop_id = ?', [$c['id'], $shopId]);
        q('DELETE FROM categories WHERE id = ? AND shop_id = ?', [$c['id'], $shopId]);
        audit('category.deleted', 'category', (int) $c['id'], $shopId, 'success', ['name' => $c['name']]);
    }, true);
}

/** Find or create a category path like "Clothing > Shirts". Used by the importer. */
function category_ensure_path(int $shopId, string $path, array &$cache): ?int {
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s*(>|\/|\|)\s*/u', $path))));
    if (!$parts) return null;
    $parent = null; $key = '';
    foreach (array_slice($parts, 0, 6) as $name) {
        $key .= '>' . mb_strtolower($name);
        if (isset($cache[$key])) { $parent = $cache[$key]; continue; }
        $found = val('SELECT id FROM categories WHERE shop_id = ? AND ' . ($parent ? 'parent_id = ?' : 'parent_id IS NULL') . ' AND lower(name) = lower(?)', array_merge([$shopId], $parent ? [$parent] : [], [$name]));
        $parent = $found ? (int) $found : category_save($shopId, null, ['name' => $name, 'parent_id' => $parent]);
        $cache[$key] = $parent;
    }
    return $parent;
}
