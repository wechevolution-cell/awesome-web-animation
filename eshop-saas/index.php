<?php
/**
 * Front controller. Every request that is not a real static asset ends up here.
 * Local: php -S localhost:8000 index.php
 */
declare(strict_types=1);

define('ROOT', __DIR__);

if (PHP_SAPI === 'cli-server') {
    $p = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    // Mirror the .htaccess protections for the built-in server.
    if (preg_match('#^/(app|data|tests)(/|$)#', $p) || preg_match('#^/uploads/.*\.(php\d?|phtml|phar|pht)$#i', $p) || preg_match('#/\.#', $p)) {
        http_response_code(403);
        exit('Forbidden');
    }
    if ($p !== '/' && is_file(ROOT . $p) && !preg_match('/\.php$/i', $p)) {
        return false;
    }
}

require ROOT . '/app/bootstrap.php';
app_run();
