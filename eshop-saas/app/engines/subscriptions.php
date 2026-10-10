<?php
declare(strict_types=1);

function add_months(string $from, int $months): string {
    $d = new DateTimeImmutable($from);
    $day = (int) $d->format('j');
    $t = $d->modify('first day of this month')->modify("+$months months");
    $last = (int) $t->format('t');
    return $t->setDate((int) $t->format('Y'), (int) $t->format('n'), min($day, $last))->setTime((int) $d->format('H'), (int) $d->format('i'), (int) $d->format('s'))->format('Y-m-d H:i:s');
}

/** Pure function: what status a subscription-tracked shop should be in right now. */
function subscription_status_for(array $shop, ?int $now = null): string {
    $now ??= time();
    $exp = strtotime((string) $shop['subscription_expires']);
    if (!$exp) return $shop['status'];
    if ($exp <= $now) {
        $grace = (int) setting('lock_grace_days', '0');
        return ($now - $exp) >= $grace * 86400 ? 'LOCKED' : 'EXPIRED';
    }
    $window = (int) setting('expiring_window_days', '10');
    return ($exp - $now) <= $window * 86400 ? 'EXPIRING' : 'ACTIVE';
}

/** Apply a status transition with history, audit and notifications. Data is never touched. */
function subscription_transition(array $shop, string $new): void {
    $old = $shop['status'];
    if ($old === $new) return;
    $sid = (int) $shop['id'];
    $chain = ($new === 'LOCKED' && in_array($old, ['ACTIVE', 'EXPIRING'], true)) ? ['EXPIRED', 'LOCKED'] : [$new];
    foreach ($chain as $st) {
        update('shops', ['status' => $st, 'updated_at' => now(), 'locked_at' => $st === 'LOCKED' ? now() : null], 'id = ?', [$sid]);
        insert('subscription_history', ['shop_id' => $sid, 'action' => 'status:' . strtolower($st), 'plan_id' => $shop['plan_id'], 'expires_at' => $shop['subscription_expires'], 'note' => "Automatic transition $old → $st", 'actor_email' => 'system', 'created_at' => now()]);
        audit('subscription.status_' . strtolower($st), 'shop', $sid, $sid, 'success', ['from' => $old], ['id' => null, 'email' => 'system', 'role' => 'system']);
        if ($st === 'EXPIRED') notify('subscription.expired', "Subscription expired: {$shop['name']}", "The subscription of {$shop['name']} expired on " . fmt_date($shop['subscription_expires'], true) . '.', $sid, 'super', 'bad', "expired:$sid:{$shop['subscription_expires']}");
        if ($st === 'LOCKED') notify('subscription.locked', "Shop locked: {$shop['name']}", "{$shop['name']} is now LOCKED. The storefront shows the lock page; all data is retained. Renew to reactivate.", $sid, 'super', 'bad', "locked:$sid:{$shop['subscription_expires']}");
        $old = $st;
    }
}

/** Warnings at the configured thresholds (default 10/3/1 days). Sends only the nearest threshold. */
function subscription_warnings(array $shop): void {
    if (!in_array($shop['status'], ['ACTIVE', 'EXPIRING'], true) || !$shop['subscription_expires']) return;
    $left = days_until($shop['subscription_expires']);
    if ($left === null || $left < 0) return;
    $th = array_values(array_filter(array_map('intval', explode(',', (string) setting('expiry_warning_days', '10,3,1'))), static fn($n) => $n > 0));
    sort($th);
    foreach ($th as $n) {
        if ($left <= $n) {
            notify('subscription.expiring', "{$shop['name']} expires in $left day" . ($left === 1 ? '' : 's'), "Threshold: $n days. Expiry: " . fmt_date($shop['subscription_expires'], true) . '. Use Renew to extend.', (int) $shop['id'], 'super', $n <= 3 ? 'bad' : 'warn', "warn:{$shop['id']}:{$shop['subscription_expires']}:$n");
            return;
        }
    }
}

function subscription_check_all(): array {
    $stats = ['checked' => 0, 'transitions' => 0];
    foreach (rows("SELECT * FROM shops WHERE deleted_at IS NULL AND status IN ('ACTIVE','EXPIRING','EXPIRED') AND subscription_expires IS NOT NULL") as $shop) {
        $stats['checked']++;
        $new = subscription_status_for($shop);
        if ($new !== $shop['status']) { subscription_transition($shop, $new); $stats['transitions']++; $shop = shop_by_id((int) $shop['id']); }
        subscription_warnings($shop);
    }
    return $stats;
}

/**
 * Create/renew/reset a subscription period. Only subscription fields + status change;
 * products, categories, templates, users, settings, orders and customers are never touched.
 * $mode: 'extend' (from current expiry if still in the future) or 'reset' (from now).
 */
function subscription_renew(int $shopId, int $months, ?int $planId = null, string $mode = 'extend', string $action = 'renewal', array $payment = []): array {
    $shop = shop_by_id($shopId);
    if (!$shop || $shop['deleted_at']) throw new UserError('Shop not found.');
    $plan = plan_by_id($planId ?? (int) $shop['plan_id']);
    if (!$plan) throw new UserError('Choose a valid package.');
    $allowed = array_map('intval', explode(',', $plan['duration_options']));
    if (!in_array($months, $allowed, true)) throw new UserError('That duration is not offered for the ' . $plan['name'] . ' package.');
    return tx(static function () use ($shop, $plan, $months, $mode, $action, $payment) {
        $sid = (int) $shop['id'];
        $start = ($mode === 'extend' && $shop['subscription_expires'] && strtotime($shop['subscription_expires']) > time()) ? $shop['subscription_expires'] : now();
        $exp = add_months($start, $months);
        $price = (int) $plan['price_cents'] * (int) ceil($months / max(1, (int) $plan['billing_period_months']));
        $subId = insert('subscriptions', ['shop_id' => $sid, 'plan_id' => $plan['id'], 'status' => 'active', 'months' => $months, 'starts_at' => $start, 'expires_at' => $exp, 'price_cents' => $price, 'created_at' => now()]);
        if (!empty($payment['provider'])) {
            $pid = payment_record($sid, $payment['provider'], (int) ($payment['amount'] ?? $price), 'subscription', $subId, (string) ($payment['reference'] ?? ''), (bool) ($payment['paid'] ?? true));
            update('subscriptions', ['payment_id' => $pid], 'id = ?', [$subId]);
        }
        q("UPDATE subscriptions SET status = 'superseded' WHERE shop_id = ? AND id <> ? AND status = 'active'", [$sid, $subId]);
        $u = current_user();
        insert('subscription_history', ['shop_id' => $sid, 'action' => $action, 'plan_id' => $plan['id'], 'plan_name' => $plan['name'], 'months' => $months, 'starts_at' => $start, 'expires_at' => $exp,
            'previous_expires_at' => $shop['subscription_expires'], 'note' => $mode === 'reset' ? 'Timer reset from today' : 'Extended', 'actor_id' => $u['id'] ?? null, 'actor_email' => $u['email'] ?? 'system', 'created_at' => now()]);
        $tmp = array_merge($shop, ['subscription_expires' => $exp, 'status' => 'ACTIVE']);
        $status = $shop['status'] === 'SUSPENDED' ? 'SUSPENDED' : subscription_status_for($tmp);
        update('shops', ['plan_id' => $plan['id'], 'subscription_start' => $start, 'subscription_expires' => $exp, 'subscription_months' => $months, 'status' => $status, 'locked_at' => null, 'updated_at' => now()], 'id = ?', [$sid]);
        audit('subscription.' . $action, 'shop', $sid, $sid, 'success', ['months' => $months, 'plan' => $plan['slug'], 'expires' => $exp, 'mode' => $mode]);
        if ($action === 'renewal') notify('subscription.renewed', "Renewed: {$shop['name']}", "Renewed for $months month(s) on {$plan['name']}. New expiry: " . fmt_date($exp, true) . '.', $sid, 'super', 'ok');
        return ['expires' => $exp, 'status' => $status, 'subscription_id' => $subId];
    }, true);
}

function shop_set_status(int $shopId, string $status, string $note = ''): void {
    if (!in_array($status, SHOP_STATUSES, true)) throw new UserError('Invalid status.');
    $shop = shop_by_id($shopId);
    if (!$shop) throw new UserError('Shop not found.');
    $u = current_user();
    update('shops', ['status' => $status, 'locked_at' => $status === 'LOCKED' ? now() : null, 'updated_at' => now()], 'id = ?', [$shopId]);
    insert('subscription_history', ['shop_id' => $shopId, 'action' => 'status:' . strtolower($status), 'plan_id' => $shop['plan_id'], 'expires_at' => $shop['subscription_expires'], 'note' => $note ?: "Manual: {$shop['status']} → $status", 'actor_id' => $u['id'] ?? null, 'actor_email' => $u['email'] ?? 'system', 'created_at' => now()]);
    audit('shop.status_' . strtolower($status), 'shop', $shopId, $shopId, 'success', ['from' => $shop['status'], 'note' => $note]);
    if ($status === 'LOCKED') notify('shop.locked', "Shop locked manually: {$shop['name']}", $note, $shopId, 'super', 'warn');
}
