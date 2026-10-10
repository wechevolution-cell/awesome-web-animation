<div class="kpis kpis-top">
  <a class="kpi card" href="/superadmin/shops/"><span>Total e-shops</span><strong><?= $stats['total'] ?></strong></a>
  <a class="kpi card kpi-ok" href="/superadmin/shops/?status=ACTIVE"><span>Active</span><strong><?= $stats['active'] ?></strong></a>
  <a class="kpi card kpi-bad" href="/superadmin/shops/?status=LOCKED"><span>Locked / expired</span><strong><?= $stats['locked'] ?></strong></a>
  <a class="kpi card kpi-warn" href="/superadmin/shops/?status=expiring_soon"><span>Expiring ≤ <?= (int) setting('expiring_window_days', '10') ?> days</span><strong><?= $stats['expiring'] ?></strong></a>
  <div class="kpi card"><span>Active products (network)</span><strong><?= number_format($stats['active_products']) ?></strong></div>
  <div class="kpi card"><span>Stored products</span><strong><?= number_format($stats['stored_products']) ?></strong></div>
  <div class="kpi card"><span>Monthly recurring (excl. demo)</span><strong><?= e(money($stats['mrr'])) ?></strong></div>
  <div class="kpi card"><span>Orders across shops (30d)</span><strong><?= number_format($stats['orders_30']) ?></strong></div>
</div>
<div class="grid-dash">
  <section class="card card-wide"><h2 class="card-h">Subscriptions by expiry</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Shop</th><th>Package</th><th>Status</th><th>Expires</th><th>Days left</th><th></th></tr></thead><tbody>
    <?php foreach ($expiring as $s): $d = days_until($s['subscription_expires']); ?>
      <tr><td><a class="strong" href="/superadmin/shops/<?= (int) $s['id'] ?>/"><?= e($s['name']) ?></a><?= $s['is_demo'] ? ' <span class="badge badge-muted">demo</span>' : '' ?></td><td><?= e($s['plan_name']) ?></td><td><?= status_badge($s['status']) ?></td><td><?= e(fmt_date($s['subscription_expires'])) ?></td><td class="<?= $d !== null && $d <= 3 ? 'txt-bad' : ($d !== null && $d <= 10 ? 'txt-warn' : '') ?>"><?= $d ?? '—' ?></td><td><a class="btn btn-xs btn-ghost" href="/superadmin/shops/<?= (int) $s['id'] ?>/#renew">Renew</a></td></tr>
    <?php endforeach ?>
    <?php if (!$expiring): ?><tr><td colspan="6" class="empty-cell">No shops yet. <a href="/superadmin/shops/new/">Create the first one</a>.</td></tr><?php endif ?>
    </tbody></table></div>
  </section>
  <section class="card"><h2 class="card-h">Notifications</h2><ul class="notif-mini"><?php foreach ($notifs as $n): ?><li class="lvl-<?= e($n['level']) ?><?= $n['read_at'] ? '' : ' is-unread' ?>"><strong><?= e($n['title']) ?></strong><span class="small muted"><?= e(fmt_date($n['created_at'], true)) ?></span></li><?php endforeach ?><?php if (!$notifs): ?><li class="muted">Nothing yet.</li><?php endif ?></ul><a class="small" href="/superadmin/notifications/">All notifications →</a></section>
  <section class="card"><h2 class="card-h">Shops per package</h2><ul class="bars-h"><?php $mx = max(1, ...array_map('intval', array_column($byPlan, 'n') ?: [1])); foreach ($byPlan as $b): ?><li><span><?= e($b['name']) ?></span><span class="bar-h"><i style="width:<?= (int) round($b['n'] / $mx * 100) ?>%"></i></span><strong><?= (int) $b['n'] ?></strong></li><?php endforeach ?></ul></section>
  <section class="card"><h2 class="card-h">System health</h2><ul class="health">
    <li class="<?= $health['cron'] && time() - strtotime($health['cron']) < 3600 * 2 ? 'ok' : 'warn' ?>">Cron last run: <?= e(fmt_date($health['cron'], true)) ?></li>
    <li class="<?= $health['backup'] && time() - $health['backup'] < 86400 * 8 ? 'ok' : 'warn' ?>">Last backup: <?= $health['backup'] ? e(date('d M Y H:i', $health['backup'])) : 'never' ?></li>
    <li class="<?= $health['writable'] ? 'ok' : 'bad' ?>">data/ and uploads/ writable</li>
    <li class="ok">PHP <?= e($health['php']) ?></li>
    <li class="<?= $health['gd_webp'] ? 'ok' : 'warn' ?>">GD WebP support</li>
    <li class="<?= $health['zip'] ? 'ok' : 'warn' ?>">Zip (backups)</li>
  </ul></section>
</div>
