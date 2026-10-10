<div class="wrap narrow-l">
  <div class="account-head"><h1><?= e(t('account')) ?></h1>
  <form method="post" action="<?= e(su('/logout/')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(t('logout')) ?></button></form></div>
  <p class="lead"><?= e(t('welcome')) ?>, <?= e($customer['name'] ?: $customer['email']) ?></p>
  <h2><?= e(t('my_orders')) ?></h2>
  <?php if ($orders): ?>
  <table class="specs"><thead><tr><th scope="col"><?= e(t('order')) ?></th><th scope="col"><?= e(t('date')) ?></th><th scope="col"><?= e(t('status')) ?></th><th scope="col"><?= e(t('total')) ?></th><th></th></tr></thead><tbody>
  <?php foreach ($orders as $o): ?><tr><td>#<?= e($o['number']) ?></td><td><?= e(fmt_date($o['created_at'])) ?></td><td><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></td><td><?= e(money((int) $o['total_cents'], $shop['locale'])) ?></td><td><a href="<?= e(su('/account/orders/' . $o['number'] . '/')) ?>"><?= e(t('view')) ?></a></td></tr><?php endforeach ?>
  </tbody></table>
  <?php else: ?><p class="muted">—</p><?php endif ?>
</div>
