<?php
declare(strict_types=1);

/**
 * Payment abstraction: Provider -> Payment -> Subscription -> Shop.
 * V1 ships "manual" and "bank_transfer" (recorded by Super Admin). Stripe / Viva Wallet plug in by
 * implementing the same three callables and enabling the provider row; core code does not change.
 */
function payment_gateway(string $key): array {
    $manual = [
        'create' => static fn(array $p) => ['status' => $p['mark_paid'] ? 'paid' : 'pending', 'external_id' => null],
        'webhook' => static fn(array $payload) => null,
        'refund' => static fn(array $payment) => ['status' => 'refunded'],
    ];
    $notConfigured = [
        'create' => static fn(array $p) => throw new UserError('This payment provider is not configured yet.'),
        'webhook' => static fn(array $payload) => throw new UserError('Not configured.'),
        'refund' => static fn(array $payment) => throw new UserError('Not configured.'),
    ];
    return match ($key) { 'manual', 'bank_transfer' => $manual, default => $notConfigured };
}

function payment_record(int $shopId, string $provider, int $amountCents, string $purpose, ?int $subscriptionId, string $reference = '', bool $markPaid = true): int {
    $prov = row('SELECT * FROM payment_providers WHERE key = ? AND enabled = 1', [$provider]);
    if (!$prov) throw new UserError('Payment provider is not available.');
    $res = payment_gateway($provider)['create'](['amount' => $amountCents, 'mark_paid' => $markPaid]);
    $u = current_user();
    return insert('payments', [
        'shop_id' => $shopId, 'provider_key' => $provider, 'purpose' => $purpose, 'amount_cents' => $amountCents, 'currency' => 'EUR',
        'status' => $res['status'], 'reference' => mb_substr($reference, 0, 190), 'external_id' => $res['external_id'], 'subscription_id' => $subscriptionId,
        'created_by' => $u['id'] ?? null, 'created_at' => now(), 'paid_at' => $res['status'] === 'paid' ? now() : null,
    ]);
}
