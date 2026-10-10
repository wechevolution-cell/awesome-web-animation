<?php
declare(strict_types=1);

/** User-facing error that is safe to display. */
class UserError extends RuntimeException {}
class HttpError extends RuntimeException {}

function e(mixed $v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function input(string $k, mixed $d = null): mixed { return $_POST[$k] ?? $_GET[$k] ?? $d; }
function post(string $k, mixed $d = ''): mixed { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function get(string $k, mixed $d = ''): mixed { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function int_in(string $k, int $d = 0): int { $v = input($k); return is_numeric($v) ? (int) $v : $d; }
function arr(mixed $v): array { return is_array($v) ? $v : []; }
function jdec(?string $s): array { if (!$s) return []; $d = json_decode($s, true); return is_array($d) ? $d : []; }
function jenc(mixed $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}
function request_host(): string {
    $h = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $h = preg_replace('/[^a-z0-9.\-:\[\]]/', '', $h) ?? 'localhost';
    return $h;
}
function host_no_port(?string $h = null): string { return preg_replace('/:\d+$/', '', $h ?? request_host()) ?? ''; }
function request_path(): string {
    $p = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $p = '/' . ltrim(preg_replace('#/+#', '/', $p) ?? '/', '/');
    return $p;
}
function origin(): string { return (is_https() ? 'https' : 'http') . '://' . request_host(); }
function abs_url(string $path): string { return preg_match('#^https?://#', $path) ? $path : origin() . $path; }
function query_string(array $merge = [], array $drop = []): string {
    $q = array_merge($_GET, $merge);
    foreach ($drop as $d) unset($q[$d]);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
}

function redirect(string $to, int $code = 302): never {
    header('Location: ' . $to, true, $code);
    exit;
}
function back(string $fallback = '/'): never {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === host_no_port()) redirect($ref);
    redirect($fallback);
}
function json_out(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo jenc($data);
    exit;
}
function abort(int $code, string $msg = ''): never { throw new HttpError($msg, $code); }
function wants_json(): bool {
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/* ---------- Session-scoped helpers ---------- */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valid(): bool {
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($t) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }
function old(string $k, mixed $d = ''): mixed { return $_SESSION['_old'][$k] ?? $d; }
function remember_input(): void { $_SESSION['_old'] = array_map(static fn($v) => is_string($v) ? mb_substr($v, 0, 5000) : $v, array_diff_key($_POST, ['password' => 1, 'password2' => 1, '_csrf' => 1])); }
function clear_old(): void { unset($_SESSION['_old']); }

/* ---------- Strings ---------- */
function strip_accents(string $s): string {
    if (class_exists('Normalizer')) {
        $s = Normalizer::normalize($s, Normalizer::FORM_D) ?: $s;
        return preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
    }
    // Fallback without intl: Greek tonos/dialytika + common Latin accents.
    return strtr($s, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ϊ' => 'ι', 'ΐ' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ώ' => 'ω',
        'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ϊ' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ϋ' => 'Υ', 'Ώ' => 'Ω',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ï' => 'i', 'ó' => 'o', 'ö' => 'o', 'ô' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c']);
}
function normalize_text(string $s): string {
    $s = mb_strtolower(strip_accents($s));
    $s = str_replace('ς', 'σ', $s);
    return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '');
}
function slugify(string $s, int $max = 80): string {
    $s = trim($s);
    if ($s === '') return '';
    static $tr = null;
    if ($tr === null) $tr = class_exists('Transliterator') ? Transliterator::create('Greek-Latin/UNGEGN; Any-Latin; Latin-ASCII; Lower()') : false;
    if ($tr) {
        $out = $tr->transliterate($s);
        if ($out !== false) $s = $out;
    } else {
        $map = ['α'=>'a','β'=>'v','γ'=>'g','δ'=>'d','ε'=>'e','ζ'=>'z','η'=>'i','θ'=>'th','ι'=>'i','κ'=>'k','λ'=>'l','μ'=>'m','ν'=>'n','ξ'=>'x','ο'=>'o','π'=>'p','ρ'=>'r','σ'=>'s','ς'=>'s','τ'=>'t','υ'=>'y','φ'=>'f','χ'=>'ch','ψ'=>'ps','ω'=>'o'];
        $s = strtr(mb_strtolower(strip_accents($s)), $map);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim(substr($s, 0, $max), '-');
    return $s;
}
function str_limit(string $s, int $n): string { $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? ''); return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s; }
function random_token(int $bytes = 24): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }
function valid_email(string $e): bool { return (bool) filter_var($e, FILTER_VALIDATE_EMAIL) && mb_strlen($e) <= 190; }
function valid_hex(string $c): bool { return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c); }
function valid_domain(string $d): bool { return (bool) preg_match('/^(?=.{3,253}$)([a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $d) || $d === 'localhost' || preg_match('/^[a-z0-9\-]+\.(test|localhost)$/', $d); }

/** Parse "12,50" / "12.50" / "1.234,50" into integer cents. */
function to_cents(mixed $v): ?int {
    if ($v === null) return null;
    $s = trim((string) $v);
    if ($s === '') return null;
    $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '';
    if ($s === '' || $s === '-') return null;
    $lastComma = strrpos($s, ','); $lastDot = strrpos($s, '.');
    if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
        $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace(',', '', $s);
    }
    if (!is_numeric($s)) return null;
    return (int) round(((float) $s) * 100);
}
function cents_input(?int $c): string { return $c === null ? '' : number_format($c / 100, 2, '.', ''); }
function money(?int $cents, string $locale = 'en', string $currency = 'EUR'): string {
    if ($cents === null) return '';
    $sym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'][$currency] ?? $currency . ' ';
    $n = $cents / 100;
    return $locale === 'el' ? number_format($n, 2, ',', '.') . ' ' . $sym : $sym . number_format($n, 2, '.', ',');
}
function fmt_date(?string $d, bool $time = false): string { if (!$d) return '—'; $t = strtotime($d); return $t ? date($time ? 'd M Y H:i' : 'd M Y', $t) : '—'; }
function days_until(?string $d): ?int { if (!$d) return null; return (int) ceil((strtotime($d) - time()) / 86400); }
function bytes_h(int $b): string { foreach (['B', 'KB', 'MB', 'GB'] as $u) { if ($b < 1024) return round($b, 1) . " $u"; $b /= 1024; } return round($b, 1) . ' TB'; }

/* ---------- Requests / security ---------- */
function client_ip(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'); }
function ip_hash(): string { return substr(hash('sha256', client_ip() . '|' . platform_secret()), 0, 32); }
function platform_secret(): string {
    $f = DATA . '/.secret';
    static $s = null;
    if ($s) return $s;
    if (!is_file($f)) { @file_put_contents($f, bin2hex(random_bytes(32))); @chmod($f, 0600); }
    return $s = trim((string) @file_get_contents($f)) ?: 'fallback-secret';
}
/** Fixed-window rate limiter stored in SQLite. Returns true when allowed. */
function rate_limit(string $key, int $max, int $window): bool {
    $k = substr(hash('sha256', $key . '|' . client_ip()), 0, 40);
    $now = time();
    $row = row('SELECT hits, reset_at FROM rate_limits WHERE key = ?', [$k]);
    if (!$row || (int) $row['reset_at'] < $now) {
        q('INSERT OR REPLACE INTO rate_limits(key, hits, reset_at) VALUES (?,?,?)', [$k, 1, $now + $window]);
        return true;
    }
    if ((int) $row['hits'] >= $max) return false;
    q('UPDATE rate_limits SET hits = hits + 1 WHERE key = ?', [$k]);
    return true;
}
/** Contact-form style anti-spam: honeypot + minimum fill time. */
function spam_check(): ?string {
    if (!empty($_POST['website'])) return 'spam';
    $t = (int) ($_POST['_ts'] ?? 0);
    if ($t && (time() - $t) < 3) return 'too_fast';
    return null;
}
function form_guard_fields(): string { return '<div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><input type="hidden" name="_ts" value="' . time() . '">'; }

function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool {
    if (!valid_email($to)) return false;
    $from = setting('mail_from', '') ?: ('no-reply@' . (host_no_port() ?: 'localhost'));
    $headers = [
        'From: ' . mb_encode_mimeheader(setting('platform_name', 'Platform')) . " <$from>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($replyTo && valid_email($replyTo)) $headers[] = "Reply-To: $replyTo";
    $logLine = date('c') . " to=$to subject=" . str_replace(["\r", "\n"], ' ', $subject) . "\n";
    @file_put_contents(DATA . '/logs/mail.log', $logLine, FILE_APPEND);
    if (getenv('APP_NO_MAIL') === '1') return true;
    try { return @mail($to, mb_encode_mimeheader($subject), $body, implode("\r\n", $headers)); } catch (Throwable) { return false; }
}

function log_error(string $msg): void {
    if (!is_dir(DATA . '/logs')) @mkdir(DATA . '/logs', 0775, true);
    @file_put_contents(DATA . '/logs/error.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

/* ---------- Views ---------- */
function view(string $name, array $vars = []): string {
    $file = VIEWS . '/' . $name . '.php';
    if (!is_file($file)) throw new RuntimeException("View not found: $name");
    extract($vars, EXTR_SKIP);
    ob_start();
    try { include $file; } catch (Throwable $t) { ob_end_clean(); throw $t; }
    return (string) ob_get_clean();
}
function partial(string $name, array $vars = []): void { echo view($name, $vars); }
function render(string $name, array $vars = [], int $code = 200): never {
    http_response_code($code);
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo view($name, $vars);
    exit;
}
function icon(string $name, int $size = 18): string {
    static $p = [
        'cart' => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/>',
        'heart' => '<path d="M12 20s-7-4.4-9.2-9A5 5 0 0 1 12 6a5 5 0 0 1 9.2 5c-2.2 4.6-9.2 9-9.2 9z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
        'return' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'chat' => '<path d="M4 5h16v11H8l-4 4z"/>',
        'box' => '<path d="m12 3 9 5v8l-9 5-9-5V8z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'grid' => '<rect x="4" y="4" width="7" height="7"/><rect x="13" y="4" width="7" height="7"/><rect x="4" y="13" width="7" height="7"/><rect x="13" y="13" width="7" height="7"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'store' => '<path d="M4 9h16l-1-5H5zM5 9v11h14V9M9 20v-6h6v6"/>',
        'home' => '<path d="m3 11 9-8 9 8M5 9v11h14V9"/>',
        'tag' => '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'folder' => '<path d="M3 6h6l2 2h10v11H3z"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0M16 4a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 7 9 6 9-6"/>',
        'file' => '<path d="M6 3h8l4 4v14H6zM14 3v4h4"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="1"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-9 8"/>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1 0 1.5-.8 1.5-1.5 0-1.5-1.5-1.5-1.5-3 0-1 1-1.5 2-1.5h2a5 5 0 0 0 5-5C21 6 17 3 12 3z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'rss' => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1.5"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="1"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>',
        'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 21h4"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'logout' => '<path d="M15 4h4v16h-4M10 17l5-5-5-5M15 12H3"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5zM3 13l9 5 9-5"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'leaf' => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15zM5 19l7-7"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 1v-7z"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="1"/><path d="M16 8V4H4v12h4"/>',
    ];
    $d = $p[$name] ?? $p['box'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** Simple pager. Returns [offset, limit, page, pages]. */
function paginate(int $total, int $per = 25, string $param = 'page'): array {
    $pages = max(1, (int) ceil($total / max(1, $per)));
    $page = min($pages, max(1, (int) ($_GET[$param] ?? 1)));
    return ['offset' => ($page - 1) * $per, 'limit' => $per, 'page' => $page, 'pages' => $pages, 'total' => $total];
}
function pager_html(array $pg, string $base = ''): string {
    if ($pg['pages'] <= 1) return '';
    $h = '<nav class="pager" aria-label="Pagination"><ul>';
    $start = max(1, $pg['page'] - 2); $end = min($pg['pages'], $pg['page'] + 2);
    if ($pg['page'] > 1) $h .= '<li><a href="' . e($base . query_string(['page' => $pg['page'] - 1])) . '" rel="prev">‹ Prev</a></li>';
    if ($start > 1) $h .= '<li><a href="' . e($base . query_string(['page' => 1])) . '">1</a></li>' . ($start > 2 ? '<li><span>…</span></li>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $h .= $i === $pg['page'] ? '<li><span aria-current="page">' . $i . '</span></li>' : '<li><a href="' . e($base . query_string(['page' => $i])) . '">' . $i . '</a></li>';
    }
    if ($end < $pg['pages']) $h .= ($end < $pg['pages'] - 1 ? '<li><span>…</span></li>' : '') . '<li><a href="' . e($base . query_string(['page' => $pg['pages']])) . '">' . $pg['pages'] . '</a></li>';
    if ($pg['page'] < $pg['pages']) $h .= '<li><a href="' . e($base . query_string(['page' => $pg['page'] + 1])) . '" rel="next">Next ›</a></li>';
    return $h . '</ul></nav>';
}

function setting(string $key, mixed $default = null): mixed {
    static $cache = null;
    if ($key === '__flush') { $cache = null; return null; }
    if ($cache === null) {
        try { $cache = []; foreach (rows('SELECT key, value FROM platform_settings') as $r) $cache[$r['key']] = $r['value']; } catch (Throwable) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}
function setting_set(string $key, ?string $value): void {
    q('INSERT INTO platform_settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, $value]);
    setting('__flush');
}
