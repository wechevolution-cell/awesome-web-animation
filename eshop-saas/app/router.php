<?php
declare(strict_types=1);

function security_headers(bool $admin = false): void {
    if (headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    $csp = "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' https://www.googletagmanager.com; "
         . "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com; "
         . "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://www.google.com https://maps.google.com; "
         . "font-src 'self' data:; form-action 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'";
    header('Content-Security-Policy: ' . $csp);
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
    if ($admin) { header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); }
}

function is_installed(): bool { return (bool) val("SELECT 1 FROM users WHERE role = 'super_admin' AND deleted_at IS NULL LIMIT 1"); }

/** Route table matcher. Routes: [METHOD, regex, callable(array $params)]. Returns true when matched. */
function dispatch(array $routes, string $path): bool {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'HEAD') $method = 'GET';
    $allowed = false;
    foreach ($routes as [$m, $re, $fn]) {
        if (!preg_match('#^' . $re . '$#u', $path, $mm)) continue;
        if ($m !== 'ANY' && $m !== $method) { $allowed = true; continue; }
        $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
        $fn($params);
        return true;
    }
    if ($allowed) abort(405);
    return false;
}

function app_run(): void {
    session_boot();
    $path = request_path();
    db();
    if (!is_installed()) {
        security_headers(true);
        if ($path === '/setup/' || $path === '/setup') { setup_controller(); return; }
        if (preg_match('#^/(assets|uploads)/#', $path)) abort(404);
        redirect('/setup/');
    }
    if (is_post() && !csrf_valid()) {
        security_headers(true);
        render('errors/generic', ['code' => 419, 'title' => 'Session expired', 'text' => 'Your form session expired (security token mismatch). Please go back, refresh the page and try again.'], 419);
    }
    if (setting('force_https') === '1' && !is_https() && PHP_SAPI !== 'cli-server') redirect('https://' . request_host() . ($_SERVER['REQUEST_URI'] ?? '/'), 301);

    // Trailing-slash consistency for HTML routes (files like sitemap.xml keep no slash).
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) && $path !== '/' && !str_ends_with($path, '/') && !preg_match('#\.[a-z0-9]{2,5}$#i', $path)) {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        redirect($path . '/' . ($qs !== '' ? '?' . $qs : ''), 301);
    }

    cron_maybe_run_lightweight();

    $host = host_no_port();
    $shop = shop_by_host($host);
    if ($shop) {
        $GLOBALS['HOST_SHOP_ID'] = (int) $shop['id'];
        // 301 www/non-www (and secondary domains) to the primary domain.
        $primary = shop_primary_domain((int) $shop['id']);
        if ($primary && $primary !== $host && !str_starts_with($path, '/admin')) {
            redirect((is_https() ? 'https://' : 'http://') . $primary . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
        }
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            if (!shop_is_open($shop) && !is_super()) { store_lock_page($shop); }
            admin_dispatch($path);
            return;
        }
        store_dispatch($shop, '', $path);
        return;
    }

    // Platform domain: canonical host (e.g. non-www) enforcement.
    $canon = (string) setting('canonical_host', '');
    if ($canon !== '' && $host !== $canon && $host === 'www.' . $canon) redirect((is_https() ? 'https://' : 'http://') . $canon . ($_SERVER['REQUEST_URI'] ?? '/'), 301);

    if ($path === '/setup/') abort(404);
    if ($path === '/superadmin/' || str_starts_with($path, '/superadmin/')) { superadmin_dispatch($path); return; }
    if ($path === '/admin/' || str_starts_with($path, '/admin/')) { admin_dispatch($path); return; }
    if ($path === '/cron/') { cron_web(); return; }
    $main = main_shop();
    if (preg_match('#^/s/([a-z0-9\-]+)(/.*)?$#', $path, $m)) {
        $s = shop_by_slug($m[1]);
        if (!$s) abort(404);
        // The main-domain shop lives at the root: keep one canonical URL.
        if ($main && (int) $main['id'] === (int) $s['id']) { $qs = $_SERVER['QUERY_STRING'] ?? ''; redirect(($m[2] ?? '/') . ($qs !== '' ? '?' . $qs : ''), 301); }
        store_dispatch($s, '/s/' . $m[1], $m[2] ?? '/');
        return;
    }
    // Main domain: the storefront the Super Admin activated, or a blank page.
    if ($main) { store_dispatch($main, '', $path); return; }
    platform_blank($path);
}

function app_exception_handler(Throwable $t): void {
    while (ob_get_level() > 1) ob_end_clean();
    $code = $t instanceof HttpError ? (int) $t->getCode() : 500;
    if ($code < 400 || $code > 599) $code = 500;
    if ($code === 500) log_error(get_class($t) . ': ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine() . "\n" . $t->getTraceAsString());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $t->getMessage() . "\n" . $t->getTraceAsString() . "\n"); exit(1); }
    if (wants_json()) json_out(['ok' => false, 'error' => $code === 500 ? 'Something went wrong. Please try again.' : ($t->getMessage() ?: 'Error ' . $code)], $code);
    try {
        if (!empty($GLOBALS['ERROR_RENDERER']) && is_callable($GLOBALS['ERROR_RENDERER'])) { ($GLOBALS['ERROR_RENDERER'])($code, $t); return; }
        $titles = [403 => 'Access denied', 404 => 'Page not found', 405 => 'Method not allowed', 410 => 'Gone', 429 => 'Too many requests', 500 => 'Something went wrong', 503 => 'Temporarily unavailable'];
        $text = $code === 500 ? 'An unexpected error occurred. It has been logged; please try again in a moment.' : ($t->getMessage() ?: 'The requested page could not be shown.');
        if (APP_DEBUG && $code === 500) $text .= ' [' . $t->getMessage() . ' @ ' . basename($t->getFile()) . ':' . $t->getLine() . ']';
        http_response_code($code);
        echo view('errors/generic', ['code' => $code, 'title' => $titles[$code] ?? 'Error', 'text' => $text]);
    } catch (Throwable $inner) {
        log_error('Error page failed: ' . $inner->getMessage());
        http_response_code($code);
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>Error ' . $code . '</p>';
    }
}
