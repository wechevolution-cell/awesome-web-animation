<?php
declare(strict_types=1);

const REDIRECT_TYPES = [301, 302, 307, 308, 410, 451];

function redirect_normalize(string $p): string {
    $p = trim($p);
    if ($p === '') return '';
    if (preg_match('#^https?://#i', $p)) { $u = parse_url($p); $p = ($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : ''); }
    return '/' . ltrim($p, '/');
}

/** Validates and stores a redirect: no self-loops, no chains (existing redirects to the new source are flattened). */
function redirect_save(int $shopId, ?int $id, array $in): int {
    $type = (int) ($in['type'] ?? 301);
    if (!in_array($type, REDIRECT_TYPES, true)) throw new UserError('Invalid redirect type.');
    $match = ($in['match_type'] ?? 'exact') === 'regex' ? 'regex' : 'exact';
    $source = $match === 'regex' ? trim((string) ($in['source'] ?? '')) : redirect_normalize((string) ($in['source'] ?? ''));
    $target = in_array($type, [410, 451], true) ? null : trim((string) ($in['target'] ?? ''));
    if ($source === '') throw new UserError('Source path is required.');
    if ($match === 'regex' && @preg_match('#' . str_replace('#', '\#', $source) . '#', '') === false) throw new UserError('Invalid regular expression.');
    if ($target !== null) {
        if ($target === '') throw new UserError('Target is required for this redirect type.');
        if (!preg_match('#^https?://#i', $target)) $target = redirect_normalize($target);
        elseif (!safe_url($target)) throw new UserError('Invalid target URL.');
        if ($match === 'exact' && rtrim($target, '/') === rtrim($source, '/')) throw new UserError('Source and destination cannot be the same.');
        if ($match === 'exact') {
            $loop = val('SELECT target FROM redirects WHERE shop_id = ? AND active = 1 AND match_type = ? AND source = ?' . ($id ? ' AND id <> ?' : ''), array_merge([$shopId, 'exact', $target], $id ? [$id] : []));
            if ($loop !== null) {
                if (rtrim((string) $loop, '/') === rtrim($source, '/')) throw new UserError('This would create an infinite redirect loop.');
                $target = (string) $loop; // flatten chain A -> B -> C into A -> C
            }
        }
    }
    $data = ['source' => $source, 'target' => $target, 'type' => $type, 'match_type' => $match, 'active' => empty($in['active']) && isset($in['active_present']) ? 0 : 1, 'note' => clean_text($in['note'] ?? '', 200)];
    return tx(static function () use ($shopId, $id, $data, $match, $source, $target) {
        if ($id) { update('redirects', $data, 'id = ? AND shop_id = ?', [$id, $shopId]); $rid = $id; }
        else {
            $dup = val('SELECT id FROM redirects WHERE shop_id = ? AND match_type = ? AND source = ?', [$shopId, $match, $source]);
            if ($dup) { update('redirects', $data, 'id = ?', [$dup]); $rid = (int) $dup; }
            else $rid = insert('redirects', $data + ['shop_id' => $shopId, 'created_at' => now()]);
        }
        if ($match === 'exact' && $target !== null) q('UPDATE redirects SET target = ? WHERE shop_id = ? AND match_type = ? AND target = ? AND id <> ?', [$target, $shopId, 'exact', $source, $rid]);
        q("UPDATE not_found_log SET ignored = 1 WHERE shop_id = ? AND path = ?", [$shopId, $source]);
        return $rid;
    });
}

/** Automatic 301 on slug change. $kind 'regex_prefix' keeps sub-paths (categories). */
function redirect_auto(int $shopId, string $from, string $to, string $kind = 'exact'): void {
    if ($from === $to) return;
    try {
        if ($kind === 'regex_prefix') {
            redirect_save($shopId, null, ['source' => '^' . preg_quote($from, '#') . '(.*)$', 'target' => $to . '$1', 'type' => 301, 'match_type' => 'regex', 'note' => 'Auto: category moved']);
        } else {
            q('DELETE FROM redirects WHERE shop_id = ? AND match_type = ? AND source = ?', [$shopId, 'exact', $to]); // the new URL is live again
            redirect_save($shopId, null, ['source' => $from, 'target' => $to, 'type' => 301, 'note' => 'Auto: slug changed']);
        }
    } catch (UserError $e) { log_error('auto redirect skipped: ' . $e->getMessage()); }
}

/** Returns [code, target|null] or null. $path is relative to the shop base. */
function redirect_match(int $shopId, string $path): ?array {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $cands = [$path, rtrim($path, '/'), $path . ($qs ? '?' . $qs : '')];
    $r = row('SELECT * FROM redirects WHERE shop_id = ? AND active = 1 AND match_type = ? AND source IN (?,?,?) LIMIT 1', array_merge([$shopId, 'exact'], $cands));
    $target = $r['target'] ?? null;
    if (!$r) {
        foreach (rows('SELECT * FROM redirects WHERE shop_id = ? AND active = 1 AND match_type = ? ORDER BY id', [$shopId, 'regex']) as $rx) {
            $re = '#' . str_replace('#', '\#', $rx['source']) . '#u';
            if (@preg_match($re, $path, $m)) {
                $r = $rx;
                $target = $rx['target'] !== null ? preg_replace_callback('/\$(\d+)/', static fn($mm) => $m[(int) $mm[1]] ?? '', $rx['target']) : null;
                break;
            }
        }
    }
    if (!$r) return null;
    q('UPDATE redirects SET hits = hits + 1, last_hit_at = ? WHERE id = ?', [now(), $r['id']]);
    return [(int) $r['type'], $target];
}

function not_found_log(int $shopId, string $path): void {
    if (preg_match('#(wp-login|wp-admin|wp-content|wp-includes|xmlrpc|\.env|\.git|phpmyadmin|pma|cgi-bin|\.php\d?$|\.aspx?$|\.jsp$|/vendor/|\.sql$|\.bak$|\.zip$|config\.|/\.well-known/(?!security))#i', $path)) return;
    $path = mb_substr($path, 0, 300);
    $ref = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 300);
    try {
        q('INSERT INTO not_found_log(shop_id, path, referrer, first_hit_at, last_hit_at) VALUES (?,?,?,?,?) ON CONFLICT(shop_id, path) DO UPDATE SET hits = hits + 1, last_hit_at = excluded.last_hit_at, referrer = COALESCE(NULLIF(excluded.referrer, \'\'), referrer)', [$shopId, $path, $ref, now(), now()]);
    } catch (Throwable $t) { log_error('404 log: ' . $t->getMessage()); }
}

/** Suggest a destination for a 404 path: closest product/category/page slug. */
function not_found_suggest(int $shopId, string $path): ?string {
    $needle = slugify(basename(trim($path, '/')));
    if ($needle === '') return null;
    $best = null; $bestScore = 0;
    $cands = [];
    foreach (rows('SELECT slug FROM products WHERE shop_id = ? AND deleted_at IS NULL AND status = ? LIMIT 2000', [$shopId, 'active']) as $r) $cands['/product/' . $r['slug'] . '/'] = $r['slug'];
    foreach (rows('SELECT path FROM categories WHERE shop_id = ?', [$shopId]) as $r) $cands['/' . $r['path'] . '/'] = basename($r['path']);
    foreach (rows('SELECT slug FROM pages WHERE shop_id = ? AND deleted_at IS NULL', [$shopId]) as $r) $cands['/' . $r['slug'] . '/'] = $r['slug'];
    foreach ($cands as $url => $slug) { similar_text($needle, $slug, $pct); if ($pct > $bestScore) { $bestScore = $pct; $best = $url; } }
    return $bestScore >= 55 ? $best : null;
}
