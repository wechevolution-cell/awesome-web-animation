<div class="page-head"><div><h1>Packages</h1><p class="muted">Data-driven plans: price, active-product limit, durations and feature flags live here — never in code.</p></div><div class="page-actions"><a class="btn" href="/superadmin/plans/new/"><?= icon('plus', 16) ?> New package</a></div></div>
<div class="plan-cards plan-cards-admin">
<?php foreach ($plans as $p): ?>
  <a class="plan-card card" href="/superadmin/plans/<?= (int) $p['id'] ?>/">
    <span class="plan-name"><?= e($p['name']) ?> <?= $p['status'] !== 'active' ? '<span class="badge badge-muted">archived</span>' : '' ?></span>
    <span class="plan-price"><?= e(money((int) $p['price_cents'])) ?><small>/<?= (int) $p['billing_period_months'] === 1 ? 'mo' : $p['billing_period_months'] . ' mo' ?></small></span>
    <span class="plan-limit"><?= (int) $p['active_product_limit'] ?> active products</span>
    <span class="small muted">Stored ≤ <?= number_format((int) $p['stored_product_limit']) ?> · <?= (int) $p['staff_limit'] ?> users · <?= (int) $p['storage_mb'] ?> MB · setup <?= e(money((int) $p['setup_fee_cents'])) ?></span>
    <span class="small">Durations: <?= e(str_replace(',', ', ', $p['duration_options'])) ?> months</span>
    <span class="small">Features: <?= e(implode(', ', array_keys(array_filter($p['features'], static fn($v) => $v === '1')))) ?></span>
    <span class="small muted"><?= (int) $p['shops'] ?> shop(s)</span>
  </a>
<?php endforeach ?>
</div>
