<?php $done = count(array_filter($checklist, static fn($c) => $c[1])); $max = max(1, ...array_map(static fn($d) => (int) $d['t'], $daily ?: [['t' => 1]])); ?>
<div class="grid-dash">
  <section class="card card-quota"><h2 class="card-h">Package quota</h2><?= quota_bar($q, true) ?>
    <p class="small muted">Your package limits <strong>active</strong> products, not storage. Inactive products stay saved and can be activated whenever a slot is free.</p>
    <p><a class="btn btn-sm" href="/admin/products/new/"><?= icon('plus', 16) ?> Add product</a> <a class="btn btn-sm btn-ghost" href="/admin/import/"><?= icon('upload', 16) ?> Import</a></p>
  </section>
  <section class="card kpis">
    <div class="kpi"><span>Orders (30d)</span><strong><?= $stats['orders_30'] ?></strong></div>
    <div class="kpi"><span>Revenue (30d)</span><strong><?= e(money($stats['revenue_30'])) ?></strong></div>
    <div class="kpi"><span>Pending orders</span><strong><?= $stats['pending'] ?></strong></div>
    <div class="kpi"><span>Customers</span><strong><?= $stats['customers'] ?></strong></div>
    <div class="kpi"><span>Subscribers</span><strong><?= $stats['subscribers'] ?></strong></div>
    <div class="kpi"><span>Unread messages</span><strong><?= $stats['messages'] ?></strong></div>
  </section>
  <section class="card card-chart"><h2 class="card-h">Orders, last 14 days</h2>
    <?php if ($daily): ?>
    <div class="bars" role="img" aria-label="Revenue per day for the last 14 days"><?php foreach ($daily as $d): ?><div class="bar" style="--h:<?= max(4, (int) round($d['t'] / $max * 100)) ?>%" title="<?= e($d['d']) ?>: <?= (int) $d['n'] ?> orders, <?= e(money((int) $d['t'])) ?>"><span><?= e(substr($d['d'], 8)) ?></span></div><?php endforeach ?></div>
    <?php else: ?><p class="muted">No orders yet.</p><?php endif ?>
  </section>
  <section class="card"><h2 class="card-h">Getting started <span class="muted small"><?= $done ?>/<?= count($checklist) ?></span></h2>
    <div class="progress"><span style="width:<?= (int) round($done / count($checklist) * 100) ?>%"></span></div>
    <ul class="checklist"><?php foreach ($checklist as [$label, $ok, $href]): ?><li class="<?= $ok ? 'is-done' : '' ?>"><?= icon($ok ? 'check' : 'arrow', 16) ?> <a href="<?= e($href) ?>"><?= e($label) ?></a></li><?php endforeach ?></ul>
  </section>
  <section class="card"><h2 class="card-h">Health</h2>
    <ul class="health">
      <li class="<?= $stats['missing_meta'] ? 'warn' : 'ok' ?>"><?= $stats['missing_meta'] ?> active products without meta description</li>
      <li class="<?= $stats['no_image'] ? 'warn' : 'ok' ?>"><?= $stats['no_image'] ?> active products without images</li>
      <li class="<?= $stats['n404'] ? 'warn' : 'ok' ?>"><a href="/admin/seo/404/"><?= $stats['n404'] ?> URLs returning 404</a></li>
    </ul>
  </section>
  <section class="card card-wide"><h2 class="card-h">Recent orders</h2>
    <?php if ($recent): ?><table class="table"><thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($recent as $o): ?><tr><td><a href="/admin/orders/<?= (int) $o['id'] ?>/">#<?= e($o['number']) ?></a></td><td><?= e($o['name']) ?></td><td><?= e(money((int) $o['total_cents'])) ?></td><td><span class="badge badge-<?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span></td><td><?= e(fmt_date($o['created_at'], true)) ?></td></tr><?php endforeach ?>
    </tbody></table><?php else: ?><p class="muted">No orders yet.</p><?php endif ?>
  </section>
</div>
