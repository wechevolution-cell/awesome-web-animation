<?php
declare(strict_types=1);

/** First-run setup wizard. No default credentials exist; the first visitor creates the Super Admin. */
function setup_controller(): void {
    $errors = [];
    if (is_post()) {
        $email = mb_strtolower(clean_text(post('email'), 190));
        $pw = (string) ($_POST['password'] ?? '');
        if (!valid_email($email)) $errors['email'] = 'Enter a valid email.';
        if ($pe = password_policy($pw)) $errors['password'] = $pe;
        if ($pw !== (string) ($_POST['password2'] ?? '')) $errors['password2'] = 'Passwords do not match.';
        if (!$errors) {
            tx(static function () use ($email, $pw) {
                if (val("SELECT 1 FROM users WHERE role = 'super_admin'")) throw new UserError('Setup already completed.');
                $uid = insert('users', ['shop_id' => null, 'email' => $email, 'name' => clean_text(post('name'), 120), 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => 'super_admin', 'status' => 'active', 'created_at' => now()]);
                setting_set('platform_name', clean_text(post('platform_name'), 80) ?: 'ShopRent');
                setting_set('canonical_host', preg_replace('/^www\./', '', host_no_port()));
                setting_set('notify_email', $email);
                setting_set('installed_at', now());
                audit('setup.completed', 'user', $uid, null, 'success', [], ['id' => $uid, 'email' => $email, 'role' => 'super_admin']);
            }, true);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) val('SELECT id FROM users WHERE email = ?', [$email]);
            if (post('demo')) { @set_time_limit(180); try { demo_create_all(); } catch (Throwable $t) { log_error('demo: ' . $t->getMessage()); flash('warn', 'Demo shops could not be created: ' . $t->getMessage()); } }
            flash('ok', 'Setup complete. Welcome!');
            redirect('/superadmin/');
        }
    }
    render('platform/setup', ['errors' => $errors, 'checks' => setup_checks()]);
}
function setup_checks(): array {
    return [
        ['PHP ≥ 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
        ['PDO SQLite', extension_loaded('pdo_sqlite'), ''],
        ['GD with WebP', function_exists('imagewebp'), ''],
        ['mbstring', extension_loaded('mbstring'), ''],
        ['intl (Greek slugs)', extension_loaded('intl'), 'optional'],
        ['zip (backups)', class_exists('ZipArchive'), ''],
        ['fileinfo', extension_loaded('fileinfo'), ''],
        ['data/ writable', is_writable(DATA), ''],
        ['uploads/ writable', is_writable(UPLOADS), ''],
    ];
}

/**
 * Main domain with no activated storefront: an intentionally blank page.
 * The Super Admin activates a shop + template for the main domain in Super Admin → Main website.
 */
function platform_blank(string $path): void {
    security_headers();
    $GLOBALS['LOCALE'] = setting('default_locale', 'el') === 'en' ? 'en' : 'el';
    header('X-Robots-Tag: noindex, nofollow');
    if ($path === '/robots.txt') { header('Content-Type: text/plain; charset=utf-8'); echo "User-agent: *\nDisallow: /\n"; exit; }
    if ($path !== '/') {
        http_response_code(404);
        echo view('platform/blank', ['code' => 404]);
        exit;
    }
    echo view('platform/blank', ['code' => 200]);
    exit;
}
