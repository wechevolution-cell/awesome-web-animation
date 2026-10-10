<?php
declare(strict_types=1);

/** Who / what / when / which shop / IP / outcome. */
function audit(string $action, ?string $entityType = null, ?int $entityId = null, ?int $shopId = null, string $outcome = 'success', array $details = [], ?array $actor = null): void {
    try {
        $u = $actor ?? (PHP_SAPI === 'cli' ? null : current_user());
        $imp = ($u && $u['role'] === 'super_admin' && impersonated_shop_id()) ? (int) $u['id'] : null;
        insert('audit_logs', [
            'actor_id' => $u['id'] ?? null, 'actor_email' => $u['email'] ?? (PHP_SAPI === 'cli' ? 'system' : 'guest'), 'impersonator_id' => $imp,
            'shop_id' => $shopId, 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId,
            'ip' => PHP_SAPI === 'cli' ? 'cli' : client_ip(), 'outcome' => $outcome, 'details' => $details ? jenc($details) : null, 'created_at' => now(),
        ]);
    } catch (Throwable $t) { log_error('audit failed: ' . $t->getMessage()); }
}

/** Notification engine. dedup_key makes repeated cron runs idempotent. */
function notify(string $type, string $title, string $body = '', ?int $shopId = null, string $audience = 'super', string $level = 'info', ?string $dedup = null): bool {
    try {
        insert('notifications', ['shop_id' => $shopId, 'audience' => $audience, 'type' => $type, 'level' => $level, 'title' => $title, 'body' => $body, 'dedup_key' => $dedup, 'created_at' => now()]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) return false;
        throw $e;
    }
    if ($audience === 'super' && ($to = (string) setting('notify_email', '')) !== '') send_mail($to, '[' . setting('platform_name', 'Platform') . '] ' . $title, $body . "\n");
    return true;
}
