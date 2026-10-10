<?php
declare(strict_types=1);

/** All periodic work. Safe to run every few minutes (Hostinger cron) or opportunistically. */
function cron_run(float $budget = 25.0): array {
    $t0 = microtime(true);
    $out = ['subscriptions' => subscription_check_all()];
    // Continue queued/processing imports in the background.
    $out['imports'] = 0;
    foreach (rows("SELECT id FROM imports WHERE status IN ('queued','processing') ORDER BY id LIMIT 5") as $imp) {
        $left = $budget - (microtime(true) - $t0);
        if ($left < 3) break;
        import_process((int) $imp['id'], min(12.0, $left - 2));
        $out['imports']++;
    }
    q('DELETE FROM rate_limits WHERE reset_at < ?', [time() - 3600]);
    q('DELETE FROM password_tokens WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 30)]);
    q("DELETE FROM import_rows WHERE import_id IN (SELECT id FROM imports WHERE status = 'completed' AND finished_at < ?) AND status = 'done'", [date('Y-m-d H:i:s', time() - 86400 * 14)]);
    setting_set('cron_last_run', now());
    return $out;
}

/** Poor-man's cron: at most every 10 minutes, only the cheap subscription check, on normal traffic. */
function cron_maybe_run_lightweight(): void {
    $last = (string) setting('cron_light_last', '');
    if ($last !== '' && time() - strtotime($last) < 600) return;
    setting_set('cron_light_last', now());
    try { subscription_check_all(); } catch (Throwable $t) { log_error('light cron: ' . $t->getMessage()); }
}

/** /cron/?token=... for hosts where only URL-based cron is available. */
function cron_web(): void {
    $tok = (string) ($_GET['token'] ?? '');
    if ($tok === '' || !hash_equals((string) setting('cron_token', ''), $tok)) abort(403, 'Invalid cron token.');
    header('Content-Type: application/json');
    echo jenc(['ok' => true, 'result' => cron_run(20.0)]);
    exit;
}
