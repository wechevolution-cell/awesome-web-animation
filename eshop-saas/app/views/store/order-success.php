<?php $loc = $shop['locale']; $ship = jdec($order['shipping']); ?>
<div class="wrap narrow success">
  <?php if (empty($accountView)): ?><p class="success-ic" aria-hidden="true"><?= icon('check', 40) ?></p><?php endif ?>
  <h1><?= e(!empty($accountView) ? t('order') . ' #' . $order['number'] : t('order_received')) ?></h1>
  <?php if (empty($accountView)): ?><p class="lead"><?= e(t('order_number')) ?>: <strong>#<?= e($order['number']) ?></strong>. <?= e(t('order_email_note', ['email' => $order['email']])) ?></p><?php endif ?>
  <?php if ($order['payment_method'] === 'bank_transfer' && ($bank = shop_setting((int) $shop['id'], 'bank_details'))): ?>
  <div class="notice notice-info"><strong><?= e(t('bank_details')) ?></strong><br><?= nl2br(e($bank)) ?><br><?= e(t('order_number')) ?>: #<?= e($order['number']) ?></div>
  <?php endif ?>
  <table class="specs order-table">
    <thead><tr><th scope="col"><?= e(t('items')) ?></th><th scope="col"><?= e(t('quantity')) ?></th><th scope="col"><?= e(t('total')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?><?php if ($i['options']): ?> <small class="muted">(<?= e($i['options']) ?>)</small><?php endif ?></td><td><?= (int) $i['quantity'] ?></td><td><?= e(money((int) $i['total_cents'], $loc)) ?></td></tr><?php endforeach ?>
    </tbody>
    <tfoot>
      <tr><th scope="row" colspan="2"><?= e(t('subtotal')) ?></th><td><?= e(money((int) $order['subtotal_cents'], $loc)) ?></td></tr>
      <tr><th scope="row" colspan="2"><?= e(t('shipping')) ?> — <?= e($order['shipping_method']) ?></th><td><?= e(money((int) $order['shipping_cents'], $loc)) ?></td></tr>
      <?php if ((int) $order['fee_cents']): ?><tr><th scope="row" colspan="2"><?= e(payment_options($shop)[$order['payment_method']]['label'] ?? $order['payment_method']) ?></th><td><?= e(money((int) $order['fee_cents'], $loc)) ?></td></tr><?php endif ?>
      <tr><th scope="row" colspan="2"><?= e(t('total')) ?></th><td><strong><?= e(money((int) $order['total_cents'], $loc)) ?></strong></td></tr>
    </tfoot>
  </table>
  <?php if (!empty($ship['address'])): ?><p class="muted"><?= e(t('shipping_address')) ?>: <?= e($ship['name'] ?? '') ?>, <?= e($ship['address']) ?>, <?= e($ship['postcode'] ?? '') ?> <?= e($ship['city'] ?? '') ?></p><?php endif ?>
  <p><a class="btn" href="<?= e(su('/products/')) ?>"><?= e(t('continue_shopping')) ?></a></p>
</div>
