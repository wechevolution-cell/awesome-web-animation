<?php
declare(strict_types=1);

function session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (PHP_SAPI === 'cli') { $_SESSION = $_SESSION ?? []; return; }
    session_name('sid');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    $dir = DATA . '/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_writable($dir)) session_save_path($dir);
    session_start();
    // Idle timeout for back-office sessions.
    if (!empty($_SESSION['uid']) && !empty($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Your session expired. Please sign in again.');
    }
    $_SESSION['last_seen'] = time();
}

function current_user(): ?array {
    static $cache = [];
    $id = (int) ($_SESSION['uid'] ?? 0);
    if (!$id) return null;
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = row('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND status = ?', [$id, 'active']);
    }
    return $cache[$id];
}
function is_super(): bool { $u = current_user(); return $u !== null && $u['role'] === 'super_admin'; }
function impersonated_shop_id(): int { return is_super() ? (int) ($_SESSION['impersonate_shop'] ?? 0) : 0; }

/**
 * The shop the back-office user is operating on. NEVER taken from request input:
 * shop users are bound to their own shop_id; super admins only via impersonation.
 */
function admin_shop_id(): int {
    $u = current_user();
    if (!$u) return 0;
    if ($u['role'] === 'super_admin') return impersonated_shop_id();
    return (int) $u['shop_id'];
}

function login_attempt(string $email, string $password, string $area): array {
    $email = mb_strtolower(trim($email));
    if (!rate_limit('login', 20, 900)) return [false, 'Too many attempts from your network. Try again in 15 minutes.'];
    $u = row('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
    $generic = 'Invalid email or password.';
    if (!$u) { password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); return [false, $generic]; }
    if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
        audit('auth.login_locked', 'user', (int) $u['id'], $u['shop_id'] ? (int) $u['shop_id'] : null, 'denied', [], $u);
        return [false, 'This account is temporarily locked after 5 failed attempts. Try again after ' . date('H:i', strtotime($u['locked_until'])) . '.'];
    }
    if (!$u['password_hash'] || !password_verify($password, $u['password_hash'])) {
        $fails = (int) $u['failed_attempts'] + 1;
        $lock = $fails >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
        update('users', ['failed_attempts' => $lock ? 0 : $fails, 'locked_until' => $lock], 'id = ?', [$u['id']]);
        audit('auth.login_failed', 'user', (int) $u['id'], $u['shop_id'] ? (int) $u['shop_id'] : null, 'failure', ['locked' => (bool) $lock], $u);
        return [false, $lock ? 'Too many failed attempts. The account is locked for 15 minutes.' : $generic];
    }
    if ($u['status'] !== 'active') return [false, 'This account is disabled.'];
    if ($area === 'super' && $u['role'] !== 'super_admin') return [false, $generic];
    if ($area === 'admin') {
        if ($u['role'] === 'super_admin') return [false, 'Super admins sign in at /superadmin/.'];
        $shop = row('SELECT * FROM shops WHERE id = ?', [$u['shop_id']]);
        if (!$shop || $shop['deleted_at']) return [false, $generic];
        $st = shop_effective_status($shop);
        if (!in_array($st, ['ACTIVE', 'EXPIRING'], true)) {
            audit('auth.login_shop_locked', 'shop', (int) $shop['id'], (int) $shop['id'], 'denied', ['status' => $st], $u);
            return [false, 'Access to this shop is suspended (' . strtolower($st) . '). Please contact your provider to renew.'];
        }
        $hostShop = $GLOBALS['HOST_SHOP_ID'] ?? null;
        if ($hostShop && (int) $hostShop !== (int) $u['shop_id']) return [false, $generic];
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
    update('users', ['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()], 'id = ?', [$u['id']]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    unset($_SESSION['impersonate_shop']);
    audit('auth.login', 'user', (int) $u['id'], $u['shop_id'] ? (int) $u['shop_id'] : null, 'success', [], $u);
    return [true, ''];
}

function logout(): void {
    $u = current_user();
    if ($u) audit('auth.logout', 'user', (int) $u['id'], $u['shop_id'] ? (int) $u['shop_id'] : null);
    $_SESSION = [];
    if (PHP_SAPI !== 'cli') session_regenerate_id(true);
}

/** Effective permissions of a back-office user on their shop. */
function user_permissions(array $u): array {
    if (in_array($u['role'], ['super_admin', 'owner'], true)) return array_keys(shop_permissions());
    return col('SELECT permission_key FROM user_permissions WHERE user_id = ?', [$u['id']]);
}
function can(string $perm): bool {
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'super_admin') return impersonated_shop_id() > 0;
    static $cache = [];
    $cache[$u['id']] ??= user_permissions($u);
    return in_array($perm, $cache[$u['id']], true);
}
function role_tier(string $role): int { return (int) (val('SELECT tier FROM roles WHERE key = ?', [$role]) ?? 0); }

/** Password tokens for invitations / resets. Returns the raw token (only ever shown/mailed once). */
function password_token_create(string $type, int $subjectId, string $purpose = 'reset', int $ttlHours = 48): string {
    $raw = random_token(32);
    q('UPDATE password_tokens SET used_at = ? WHERE subject_type = ? AND subject_id = ? AND used_at IS NULL', [now(), $type, $subjectId]);
    insert('password_tokens', ['subject_type' => $type, 'subject_id' => $subjectId, 'token_hash' => hash('sha256', $raw), 'purpose' => $purpose, 'expires_at' => date('Y-m-d H:i:s', time() + $ttlHours * 3600), 'created_at' => now()]);
    return $raw;
}
function password_token_find(string $type, string $raw): ?array {
    return row('SELECT * FROM password_tokens WHERE subject_type = ? AND token_hash = ? AND used_at IS NULL AND expires_at > ?', [$type, hash('sha256', $raw), now()]);
}
function password_policy(string $p): ?string {
    if (mb_strlen($p) < 10) return 'Password must be at least 10 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    return null;
}

function shop_permissions(): array {
    return [
        'dashboard' => 'Dashboard', 'products' => 'Products & import', 'categories' => 'Categories', 'orders' => 'Orders',
        'customers' => 'Customers & messages', 'pages' => 'Pages & menus', 'media' => 'Media library', 'appearance' => 'Appearance & homepage',
        'popups' => 'Popups & subscribers', 'feeds' => 'Feeds & export', 'seo' => 'SEO, redirects & 404s', 'settings' => 'Store settings', 'users' => 'Users',
    ];
}
