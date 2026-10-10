<?php
declare(strict_types=1);

const IMPORT_FIELDS = [
    'sku' => 'SKU', 'name' => 'Name', 'slug' => 'Slug', 'description' => 'Description', 'short_description' => 'Short description',
    'price' => 'Price', 'sale_price' => 'Sale price', 'cost_price' => 'Cost price', 'stock' => 'Stock', 'barcode' => 'Barcode (EAN)',
    'brand' => 'Brand', 'category' => 'Category path (A > B)', 'images' => 'Image URLs (| separated)', 'weight_g' => 'Weight (g)',
    'status' => 'Status (active/inactive)', 'seo_title' => 'SEO title', 'seo_description' => 'SEO description',
];

function import_guess_field(string $header): string {
    $h = normalize_text($header);
    $map = [
        'sku' => ['sku', 'code', 'κωδικος', 'product code', 'mpn', 'id', 'κωδ'], 'name' => ['name', 'title', 'product name', 'ονομα', 'τιτλος', 'περιγραφη ειδους'],
        'slug' => ['slug', 'url key'], 'description' => ['description', 'περιγραφη', 'long description', 'body'], 'short_description' => ['short description', 'συντομη περιγραφη', 'summary'],
        'price' => ['price', 'τιμη', 'retail price', 'price with vat', 'τιμη λιανικης'], 'sale_price' => ['sale price', 'special price', 'τιμη προσφορας', 'προσφορα'],
        'cost_price' => ['cost', 'cost price', 'κοστος'], 'stock' => ['stock', 'qty', 'quantity', 'αποθεμα', 'ποσοτητα', 'stock qty'], 'barcode' => ['barcode', 'ean', 'upc', 'gtin'],
        'brand' => ['brand', 'manufacturer', 'μαρκα', 'κατασκευαστης'], 'category' => ['category', 'categories', 'κατηγορια', 'category path'],
        'images' => ['image', 'images', 'image url', 'εικονα', 'εικονες', 'image link', 'imageurl'], 'weight_g' => ['weight', 'βαρος'], 'status' => ['status', 'active', 'enabled', 'κατασταση'],
        'seo_title' => ['seo title', 'meta title'], 'seo_description' => ['seo description', 'meta description'],
    ];
    foreach ($map as $f => $syn) if (in_array($h, $syn, true)) return $f;
    foreach ($map as $f => $syn) foreach ($syn as $s) if (strlen($s) > 3 && str_contains($h, $s)) return $f;
    return '';
}

/** Step 1+2: upload & validate/parse into import_rows (streaming; safe for 10,000+ rows). */
function import_upload(array $shop, array $file, string $encoding = 'UTF-8', string $xmlItem = ''): int {
    $sid = (int) $shop['id'];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new UserError('Upload failed (max ' . ini_get('upload_max_filesize') . ').');
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xml', 'txt'], true)) throw new UserError('Upload a .csv or .xml file.');
    if ((int) $file['size'] > 40 * 1048576) throw new UserError('File too large (max 40 MB).');
    $type = $ext === 'xml' ? 'xml' : 'csv';
    $dir = DATA . '/imports/' . $sid;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $id = insert('imports', ['shop_id' => $sid, 'type' => $type, 'filename' => clean_text($file['name'], 200), 'file_path' => '', 'status' => 'uploaded', 'created_by' => current_user()['id'] ?? null, 'created_at' => now(), 'options' => jenc(['encoding' => $encoding, 'xml_item' => $xmlItem])]);
    $path = "$dir/$id.$type";
    if (!move_uploaded_file_safe((string) $file['tmp_name'], $path)) throw new UserError('Could not store the uploaded file.');
    update('imports', ['file_path' => $path], 'id = ?', [$id]);
    try {
        [$headers, $count] = $type === 'csv' ? import_parse_csv($sid, $id, $path, $encoding) : import_parse_xml($sid, $id, $path, $xmlItem);
    } catch (UserError $e) {
        update('imports', ['status' => 'failed'], 'id = ?', [$id]);
        import_log($id, $sid, 'error', $e->getMessage());
        throw $e;
    }
    $mapping = [];
    foreach ($headers as $h) $mapping[$h] = import_guess_field($h);
    update('imports', ['status' => 'validated', 'headers' => jenc($headers), 'mapping' => jenc($mapping), 'total_rows' => $count], 'id = ?', [$id]);
    import_log($id, $sid, 'info', "Parsed $count rows with " . count($headers) . ' columns.');
    audit('import.uploaded', 'import', $id, $sid, 'success', ['rows' => $count, 'type' => $type]);
    return $id;
}

function import_parse_csv(int $sid, int $id, string $path, string $encoding): array {
    $fh = fopen($path, 'rb');
    if (!$fh) throw new UserError('Cannot read file.');
    $first = (string) fgets($fh);
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
    $delims = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t"), '|' => substr_count($first, '|')];
    arsort($delims);
    $delim = (string) array_key_first($delims);
    rewind($fh);
    $conv = static fn($s) => $encoding === 'UTF-8' ? $s : (mb_convert_encoding((string) $s, 'UTF-8', $encoding) ?: $s);
    $headers = fgetcsv($fh, 0, $delim, '"', '');
    if (!$headers) throw new UserError('The file has no header row.');
    $headers = array_map(static fn($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $conv($h))), $headers);
    $headers = import_unique_headers($headers);
    if (count(array_filter($headers)) < 1) throw new UserError('Could not detect columns.');
    $n = 0;
    $st = db()->prepare('INSERT INTO import_rows(import_id, shop_id, row_num, data) VALUES (?,?,?,?)');
    tx(static function () use ($fh, $delim, $headers, $conv, $encoding, $st, $id, $sid, &$n) {
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            if ($r === [null] || (count($r) === 1 && trim((string) $r[0]) === '')) continue;
            $row = [];
            foreach ($headers as $i => $h) $row[$h] = trim($conv($r[$i] ?? ''));
            if ($encoding === 'UTF-8' && !mb_check_encoding(implode('', $row), 'UTF-8')) { throw new UserError('The file is not valid UTF-8. Choose the correct encoding (e.g. Windows-1253 for Greek Excel exports).'); }
            $st->execute([$id, $sid, ++$n, jenc($row)]);
            if ($n > 60000) throw new UserError('Too many rows (max 60,000 per file).');
        }
    });
    fclose($fh);
    if (!$n) throw new UserError('The file contains no data rows.');
    return [$headers, $n];
}
function import_unique_headers(array $h): array {
    $seen = []; $out = [];
    foreach ($h as $i => $x) { $x = $x === '' ? 'column_' . ($i + 1) : mb_substr($x, 0, 80); $k = $x; $j = 2; while (isset($seen[$k])) $k = $x . '_' . $j++; $seen[$k] = 1; $out[] = $k; }
    return $out;
}

/** XML: streaming XMLReader. Repeating item element auto-detected (product/item/entry/offer) unless given. */
function import_parse_xml(int $sid, int $id, string $path, string $item): array {
    $item = preg_replace('/[^A-Za-z0-9_\-:]/', '', $item) ?? '';
    $candidates = $item !== '' ? [$item] : ['product', 'item', 'entry', 'offer', 'Product', 'Item', 'PRODUCT', 'row'];
    $r = new XMLReader();
    if (!@$r->open($path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) throw new UserError('Invalid XML file.');
    $found = null;
    while (@$r->read()) { if ($r->nodeType === XMLReader::ELEMENT && in_array($r->localName, $candidates, true)) { $found = $r->localName; break; } }
    if (!$found) { $r->close(); throw new UserError('Could not find repeating product elements. Enter the item element name (e.g. "product").'); }
    $headers = []; $n = 0;
    $st = db()->prepare('INSERT INTO import_rows(import_id, shop_id, row_num, data) VALUES (?,?,?,?)');
    try {
        tx(static function () use ($r, $found, $st, $id, $sid, &$headers, &$n) {
        do {
            if ($r->nodeType !== XMLReader::ELEMENT || $r->localName !== $found) continue;
            $xml = @simplexml_load_string($r->readOuterXml(), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            if (!$xml) continue;
            $row = [];
            foreach ($xml->attributes() as $k => $v) $row['@' . $k] = trim((string) $v);
            foreach ($xml->children() as $child) {
                $k = $child->getName();
                if ($child->count() > 0) {
                    $vals = []; foreach ($child->children() as $g) $vals[] = trim((string) $g);
                    $v = implode('|', array_filter($vals));
                } else $v = trim((string) $child);
                $row[$k] = isset($row[$k]) && $row[$k] !== '' ? $row[$k] . '|' . $v : $v;
            }
            foreach (array_keys($row) as $k) $headers[$k] = true;
            $st->execute([$id, $sid, ++$n, jenc($row)]);
            if ($n > 60000) throw new UserError('Too many items (max 60,000 per file).');
        } while (@$r->next($found) || @$r->read());
        });
    } catch (Throwable $t) { $r->close(); throw $t; }
    $r->close();
    if (!$n) throw new UserError('No product elements found.');
    return [array_keys($headers), $n];
}

function import_log(int $id, int $sid, string $level, string $msg): void { insert('import_logs', ['import_id' => $id, 'shop_id' => $sid, 'level' => $level, 'message' => mb_substr($msg, 0, 1000), 'created_at' => now()]); }

function import_map_row(array $data, array $mapping): array {
    $out = [];
    foreach ($mapping as $col => $field) if ($field && isset(IMPORT_FIELDS[$field]) && array_key_exists($col, $data)) $out[$field] = isset($out[$field]) && $out[$field] !== '' ? $out[$field] : $data[$col];
    return $out;
}

/** Step 3: preview — first rows mapped + quick validation summary. */
function import_preview(array $import, int $limit = 10): array {
    $mapping = jdec($import['mapping']);
    $rows = rows('SELECT row_num, data FROM import_rows WHERE import_id = ? ORDER BY id LIMIT ?', [$import['id'], $limit]);
    $preview = [];
    foreach ($rows as $r) { $m = import_map_row(jdec($r['data']), $mapping); $preview[] = ['row' => $r['row_num'], 'data' => $m, 'errors' => import_validate($m)]; }
    $sid = (int) $import['shop_id'];
    $skuCol = array_search('sku', $mapping, true);
    $stats = ['total' => (int) $import['total_rows'], 'existing_skus' => 0, 'dup_in_file' => 0];
    if ($skuCol !== false) {
        $skuJson = '$."' . str_replace('"', '\\"', (string) $skuCol) . '"';
        $stats['dup_in_file'] = (int) val("SELECT COUNT(*) - COUNT(DISTINCT json_extract(data, ?)) FROM import_rows WHERE import_id = ? AND COALESCE(json_extract(data, ?), '') <> ''", [$skuJson, $import['id'], $skuJson]);
        $stats['existing_skus'] = (int) val("SELECT COUNT(DISTINCT p.id) FROM import_rows r JOIN products p ON p.shop_id = ? AND p.deleted_at IS NULL AND p.sku = json_extract(r.data, ?) WHERE r.import_id = ?", [$sid, $skuJson, $import['id']]);
    }
    return ['rows' => $preview, 'stats' => $stats];
}
function import_validate(array $m): array {
    $e = [];
    if (trim((string) ($m['name'] ?? '')) === '' && trim((string) ($m['sku'] ?? '')) === '') $e[] = 'Needs a name or SKU';
    if (isset($m['price']) && $m['price'] !== '' && to_cents($m['price']) === null) $e[] = 'Invalid price';
    if (isset($m['stock']) && $m['stock'] !== '' && !is_numeric(str_replace(',', '.', $m['stock']))) $e[] = 'Invalid stock';
    return $e;
}

/** Step 4: confirm → queue. */
function import_queue(array $shop, int $id, array $mapping, array $opts): void {
    $sid = (int) $shop['id'];
    $imp = owned('imports', $id, $sid);
    if (!in_array($imp['status'], ['validated', 'queued'], true)) throw new UserError('This import cannot be queued in its current state.');
    $headers = jdec($imp['headers']);
    $clean = [];
    foreach ($headers as $h) { $f = (string) ($mapping[$h] ?? ''); $clean[$h] = isset(IMPORT_FIELDS[$f]) ? $f : ''; }
    if (!in_array('name', $clean, true) && !in_array('sku', $clean, true)) throw new UserError('Map at least the Name or SKU column.');
    $options = array_merge(jdec($imp['options']), [
        'mode' => in_array($opts['mode'] ?? '', ['create_update', 'create_only', 'update_only'], true) ? $opts['mode'] : 'create_update',
        'activate' => in_array($opts['activate'] ?? '', ['none', 'quota'], true) ? $opts['activate'] : 'none',
        'create_categories' => !empty($opts['create_categories']),
    ]);
    if (!shop_feature($shop, 'import')) throw new UserError('Imports are not included in your package.');
    update('imports', ['mapping' => jenc($clean), 'options' => jenc($options), 'status' => 'queued'], 'id = ?', [$id]);
    import_log($id, $sid, 'info', 'Queued (' . $options['mode'] . ', activation: ' . $options['activate'] . ').');
    audit('import.queued', 'import', $id, $sid);
}

/**
 * Step 5: process the next batches within a time budget. Idempotent & resumable; a DB lock
 * prevents two workers (browser + cron) from processing the same import simultaneously.
 */
function import_process(int $id, float $budgetSeconds = 12.0, int $batch = 250): array {
    $imp = row('SELECT * FROM imports WHERE id = ?', [$id]);
    if (!$imp) throw new UserError('Import not found.');
    if (in_array($imp['status'], ['completed', 'failed', 'cancelled'], true)) return import_progress($imp);
    if (!in_array($imp['status'], ['queued', 'processing'], true)) throw new UserError('Confirm the import first.');
    $now = time();
    $got = q('UPDATE imports SET locked_until = ?, status = ?, started_at = COALESCE(started_at, ?) WHERE id = ? AND (locked_until IS NULL OR locked_until < ?)', [$now + (int) $budgetSeconds + 30, 'processing', now(), $id, $now])->rowCount();
    if (!$got) return import_progress($imp) + ['busy' => true];
    $shop = shop_by_id((int) $imp['shop_id']);
    $sid = (int) $shop['id'];
    $mapping = jdec($imp['mapping']); $opts = jdec($imp['options']);
    $start = microtime(true);
    $catCache = [];
    try {
        while (microtime(true) - $start < $budgetSeconds) {
            $rows = rows("SELECT * FROM import_rows WHERE import_id = ? AND status = 'pending' ORDER BY id LIMIT ?", [$id, $batch]);
            if (!$rows) break;
            tx(static function () use ($rows, $shop, $sid, $mapping, $opts, $id, &$catCache) {
                $limit = shop_active_limit($shop);
                $available = max(0, $limit - product_active_count($sid));
                $stored = product_stored_count($sid);
                $storedLimit = shop_stored_limit($shop);
                $c = ['created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'activated' => 0];
                foreach ($rows as $r) {
                    $m = import_map_row(jdec($r['data']), $mapping);
                    try {
                        $res = import_apply_row($shop, $m, $opts, $available, $stored, $storedLimit, $catCache);
                        $c[$res]++;
                        q("UPDATE import_rows SET status = ?, message = NULL WHERE id = ?", [$res === 'skipped' ? 'skipped' : 'done', $r['id']]);
                    } catch (UserError $e) {
                        $c['errors']++;
                        q("UPDATE import_rows SET status = 'error', message = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 300), $r['id']]);
                    }
                }
                $c['activated'] = $GLOBALS['IMPORT_ACTIVATED'] ?? 0; $GLOBALS['IMPORT_ACTIVATED'] = 0;
                q('UPDATE imports SET processed_rows = processed_rows + ?, created_count = created_count + ?, updated_count = updated_count + ?, error_count = error_count + ?, skipped_count = skipped_count + ?, activated_count = activated_count + ? WHERE id = ?',
                    [count($rows), $c['created'], $c['updated'], $c['errors'], $c['skipped'], $c['activated'], $id]);
            }, true);
        }
        $left = (int) val("SELECT COUNT(*) FROM import_rows WHERE import_id = ? AND status = 'pending'", [$id]);
        if ($left === 0) {
            update('imports', ['status' => 'completed', 'finished_at' => now(), 'locked_until' => null], 'id = ?', [$id]);
            $imp = row('SELECT * FROM imports WHERE id = ?', [$id]);
            $summary = "Created {$imp['created_count']}, updated {$imp['updated_count']}, activated {$imp['activated_count']}, skipped {$imp['skipped_count']}, errors {$imp['error_count']}.";
            import_log($id, $sid, $imp['error_count'] ? 'warn' : 'info', 'Completed. ' . $summary);
            notify('import.completed', 'Import completed: ' . $imp['filename'], $summary, $sid, 'shop', $imp['error_count'] ? 'warn' : 'ok', 'import:' . $id);
            audit('import.completed', 'import', $id, $sid, 'success', ['summary' => $summary]);
        } else {
            update('imports', ['locked_until' => null], 'id = ?', [$id]);
        }
    } catch (Throwable $t) {
        update('imports', ['status' => 'failed', 'locked_until' => null, 'finished_at' => now()], 'id = ?', [$id]);
        import_log($id, $sid, 'error', 'Failed: ' . $t->getMessage());
        notify('import.failed', 'Import failed: ' . $imp['filename'], $t->getMessage(), $sid, 'shop', 'bad', 'importfail:' . $id);
        notify('import.failed', "Import failed in {$shop['name']}", $imp['filename'] . ': ' . $t->getMessage(), $sid, 'super', 'bad', 'importfail-super:' . $id);
        log_error('import ' . $id . ': ' . $t->getMessage());
    }
    return import_progress(row('SELECT * FROM imports WHERE id = ?', [$id]));
}

function import_progress(array $imp): array {
    return ['id' => (int) $imp['id'], 'status' => $imp['status'], 'total' => (int) $imp['total_rows'], 'processed' => (int) $imp['processed_rows'],
        'created' => (int) $imp['created_count'], 'updated' => (int) $imp['updated_count'], 'activated' => (int) $imp['activated_count'], 'errors' => (int) $imp['error_count'], 'skipped' => (int) $imp['skipped_count'],
        'pct' => $imp['total_rows'] ? (int) floor($imp['processed_rows'] / $imp['total_rows'] * 100) : 0];
}

/** Apply one mapped row. Returns created|updated|skipped. */
function import_apply_row(array $shop, array $m, array $opts, int &$available, int &$stored, int $storedLimit, array &$catCache): string {
    $sid = (int) $shop['id'];
    $sku = clean_text($m['sku'] ?? '', 64);
    $name = clean_text($m['name'] ?? '', 200);
    $errs = import_validate($m);
    if ($errs) throw new UserError(implode('; ', $errs));
    $existing = null;
    if ($sku !== '') $existing = row('SELECT id, status, slug FROM products WHERE shop_id = ? AND sku = ? AND deleted_at IS NULL', [$sid, $sku]);
    elseif ($name !== '') $existing = row('SELECT id, status, slug FROM products WHERE shop_id = ? AND slug = ? AND deleted_at IS NULL', [$sid, slugify((string) ($m['slug'] ?? '') ?: $name)]);
    $mode = $opts['mode'] ?? 'create_update';
    if ($existing && $mode === 'create_only') return 'skipped';
    if (!$existing && $mode === 'update_only') return 'skipped';
    $d = [];
    if ($name !== '') $d['name'] = $name;
    foreach (['description' => 20000, 'short_description' => 1000] as $f => $max) if (isset($m[$f]) && $m[$f] !== '') $d[$f] = $f === 'description' ? sanitize_html(mb_substr($m[$f], 0, $max)) : clean_multiline($m[$f], $max);
    foreach (['price' => 'price_cents', 'sale_price' => 'sale_price_cents', 'cost_price' => 'cost_price_cents'] as $f => $col) if (isset($m[$f]) && $m[$f] !== '') $d[$col] = to_cents($m[$f]);
    if (isset($d['sale_price_cents'], $d['price_cents']) && $d['sale_price_cents'] >= $d['price_cents']) $d['sale_price_cents'] = null;
    if (isset($m['stock']) && $m['stock'] !== '') $d['stock'] = max(0, (int) round((float) str_replace(',', '.', $m['stock'])));
    foreach (['barcode' => 64, 'brand' => 120, 'seo_title' => 120, 'seo_description' => 320] as $f => $max) if (isset($m[$f]) && $m[$f] !== '') $d[$f] = clean_text($m[$f], $max);
    if (isset($m['weight_g']) && is_numeric(str_replace(',', '.', $m['weight_g']))) $d['weight_g'] = (int) round((float) str_replace(',', '.', $m['weight_g']));
    $catId = null;
    if (!empty($m['category']) && !empty($opts['create_categories'])) $catId = category_ensure_path($sid, (string) $m['category'], $catCache);
    elseif (!empty($m['category'])) { $segs = explode('>', (string) $m['category']); $last = trim((string) end($segs)); $catId = (int) val('SELECT id FROM categories WHERE shop_id = ? AND lower(name) = lower(?)', [$sid, $last]) ?: null; }
    if ($catId) $d['primary_category_id'] = $catId;
    $d['updated_at'] = now();
    if ($existing) {
        $pid = (int) $existing['id'];
        update('products', $d, 'id = ? AND shop_id = ?', [$pid, $sid]);
        $result = 'updated';
    } else {
        if ($name === '') throw new UserError('Name is required to create a product.');
        if ($stored >= $storedLimit) throw new UserError("Storage limit reached ($storedLimit products).");
        $d += ['price_cents' => 0];
        $d['slug'] = unique_slug('products', $sid, slugify((string) ($m['slug'] ?? '') ?: $name) ?: 'product');
        $d['sku'] = $sku ?: null;
        $pid = insert('products', $d + ['shop_id' => $sid, 'status' => 'inactive', 'created_at' => now()]);
        $stored++;
        $result = 'created';
    }
    if ($catId) q('INSERT OR IGNORE INTO category_relationships(shop_id, product_id, category_id) VALUES (?,?,?)', [$sid, $pid, $catId]);
    if (!empty($m['images'])) {
        $urls = array_slice(array_filter(array_map('trim', preg_split('/[|,\s]+/u', (string) $m['images']))), 0, 10);
        $mids = array_filter(array_map(static fn($u) => media_external($sid, $u, $name), $urls));
        if ($mids) {
            q('DELETE FROM product_images WHERE product_id = ? AND shop_id = ?', [$pid, $sid]);
            $pos = 0; foreach ($mids as $mid) insert('product_images', ['shop_id' => $sid, 'product_id' => $pid, 'media_id' => $mid, 'position' => $pos++]);
        }
    }
    // Activation always respects the active quota; never auto-deletes or exceeds the limit.
    $isActive = ($existing['status'] ?? 'inactive') === 'active';
    $statusCol = isset($m['status']) && $m['status'] !== '' ? normalize_text((string) $m['status']) : null;
    $want = $statusCol !== null ? in_array($statusCol, ['active', '1', 'yes', 'true', 'ενεργο', 'ναι', 'on', 'enabled', 'published'], true) : (($opts['activate'] ?? 'none') === 'quota' ? true : null);
    if ($want === true && !$isActive && $available > 0) {
        q("UPDATE products SET status = 'active', activated_at = ? WHERE id = ?", [now(), $pid]);
        $available--; $GLOBALS['IMPORT_ACTIVATED'] = ($GLOBALS['IMPORT_ACTIVATED'] ?? 0) + 1;
    } elseif ($want === false && $isActive && $statusCol !== null) {
        q("UPDATE products SET status = 'inactive', activated_at = NULL WHERE id = ?", [$pid]);
        $available++;
    }
    return $result;
}

/* ---------- CSV export (round-trips with the importer) ---------- */
function export_products_csv(array $shop, array $filter): void {
    $sid = (int) $shop['id'];
    $where = 'p.shop_id = ? AND p.deleted_at IS NULL'; $params = [$sid];
    if (($filter['status'] ?? '') === 'active') $where .= " AND p.status = 'active'";
    if (($filter['status'] ?? '') === 'inactive') $where .= " AND p.status = 'inactive'";
    if (!empty($filter['category_id'])) { $ids = category_descendant_ids($sid, (int) $filter['category_id']) ?: [0]; $where .= ' AND p.id IN (SELECT product_id FROM category_relationships WHERE category_id IN (' . in_ph($ids) . '))'; $params = array_merge($params, $ids); }
    if (!empty($filter['ids'])) { $ids = array_map('intval', $filter['ids']); $where .= ' AND p.id IN (' . in_ph($ids) . ')'; $params = array_merge($params, $ids); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products-' . $shop['slug'] . '-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['sku', 'name', 'slug', 'status', 'price', 'sale_price', 'cost_price', 'stock', 'barcode', 'brand', 'category', 'images', 'weight_g', 'short_description', 'description', 'seo_title', 'seo_description'], ',', '"', '');
    $paths = []; foreach (rows('SELECT id, path, name FROM categories WHERE shop_id = ?', [$sid]) as $c) $paths[$c['id']] = $c;
    $catPath = static function (?int $id) use ($paths): string {
        if (!$id || !isset($paths[$id])) return '';
        $names = []; $cur = $paths[$id]; $segs = explode('/', $cur['path']); $acc = '';
        foreach ($segs as $s) { $acc = ltrim($acc . '/' . $s, '/'); foreach ($paths as $p) if ($p['path'] === $acc) { $names[] = $p['name']; break; } }
        return implode(' > ', $names);
    };
    $last = 0;
    do {
        $chunk = rows("SELECT p.* FROM products p WHERE $where AND p.id > ? ORDER BY p.id LIMIT 500", array_merge($params, [$last]));
        if (!$chunk) break;
        $chunk = products_attach_images($chunk);
        foreach ($chunk as $p) {
            $imgs = implode('|', array_map(static fn($m) => abs_url(media_url($m)), $p['images']));
            fputcsv($out, [$p['sku'], $p['name'], $p['slug'], $p['status'], cents_input((int) $p['price_cents']), cents_input($p['sale_price_cents'] !== null ? (int) $p['sale_price_cents'] : null), cents_input($p['cost_price_cents'] !== null ? (int) $p['cost_price_cents'] : null),
                $p['stock'], $p['barcode'], $p['brand'], $catPath($p['primary_category_id'] ? (int) $p['primary_category_id'] : null), $imgs, $p['weight_g'], $p['short_description'], $p['description'], $p['seo_title'], $p['seo_description']], ',', '"', '');
            $last = (int) $p['id'];
        }
        flush();
    } while (count($chunk) === 500);
    fclose($out);
    audit('product.exported', 'product', null, $sid, 'success', $filter);
    exit;
}
