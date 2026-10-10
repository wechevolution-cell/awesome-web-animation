<?php $bill = jdec($o['billing']); $ship = jdec($o['shipping']); ?>
<div class="page-head"><div><a class="back" href="/admin/orders/">← Orders</a><h1>Order #<?= e($o['number']) ?></h1><p class="muted"><?= e(fmt_date($o['created_at'], true)) ?></p></div></div>
<div class="edit-grid">
  <div class="edit-main">
    <section class="card"><h2 class="card-h">Items</h2>
      <table class="table"><thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($items as $i): $live = $i['product_id'] ? row('SELECT id, status, deleted_at FROM products WHERE id = ?', [$i['product_id']]) : null; ?>
      <tr><td><?= e($i['name']) ?><?php if ($i['options']): ?><br><span class="small muted"><?= e($i['options']) ?></span><?php endif ?><?php if ($live && !$live['deleted_at']): ?> <a class="small" href="/admin/products/<?= (int) $live['id'] ?>/">(<?= e($live['status']) ?>)</a><?php elseif ($i['product_id']): ?> <span class="small muted">(product deleted)</span><?php endif ?></td><td class="mono"><?= e($i['sku']) ?></td><td><?= e(money((int) $i['price_cents'])) ?></td><td><?= (int) $i['quantity'] ?></td><td><?= e(money((int) $i['total_cents'])) ?></td></tr>
      <?php endforeach ?></tbody>
      <tfoot><tr><td colspan="4">Subtotal</td><td><?= e(money((int) $o['subtotal_cents'])) ?></td></tr><tr><td colspan="4">Shipping (<?= e($o['shipping_method']) ?>)</td><td><?= e(money((int) $o['shipping_cents'])) ?></td></tr><?php if ((int) $o['fee_cents']): ?><tr><td colspan="4">Payment fee</td><td><?= e(money((int) $o['fee_cents'])) ?></td></tr><?php endif ?><tr><td colspan="4"><strong>Total</strong></td><td><strong><?= e(money((int) $o['total_cents'])) ?></strong></td></tr></tfoot></table>
    </section>
    <section class="card split-2 inner">
      <div><h2 class="card-h">Customer</h2><p><?= e($o['name']) ?><br><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a><br><?= e($o['phone']) ?></p><?php if ($o['customer_id']): ?><a class="small" href="/admin/customers/<?= (int) $o['customer_id'] ?>/">Customer profile →</a><?php endif ?></div>
      <div><h2 class="card-h">Shipping address</h2><p><?= e($ship['address'] ?? '—') ?><br><?= e(($ship['postcode'] ?? '') . ' ' . ($ship['city'] ?? '')) ?><br><?= e($ship['country'] ?? '') ?></p></div>
      <?php if ($o['notes']): ?><div><h2 class="card-h">Customer notes</h2><p><?= nl2br(e($o['notes'])) ?></p></div><?php endif ?>
    </section>
    <section class="card"><h2 class="card-h">History</h2><ul class="timeline"><?php foreach ($history as $h): ?><li><strong><?= e(ORDER_STATUSES[$h['status']] ?? $h['status']) ?></strong> <span class="muted small"><?= e(fmt_date($h['created_at'], true)) ?> · <?= e($h['actor']) ?></span><?php if ($h['note']): ?><br><span class="small"><?= e($h['note']) ?></span><?php endif ?></li><?php endforeach ?></ul></section>
  </div>
  <aside class="edit-side"><section class="card sticky"><h2 class="card-h">Update</h2>
    <form method="post" class="stack"><?= csrf_field() ?>
      <?= f_select('status', 'Order status', ORDER_STATUSES, $o['status']) ?>
      <?= f_select('payment_status', 'Payment status', PAYMENT_STATUSES, $o['payment_status']) ?>
      <?= f_select('shipping_status', 'Shipping status', SHIPPING_STATUSES, $o['shipping_status']) ?>
      <?= f_text('tracking_number', 'Tracking number', $o['tracking_number']) ?>
      <?= f_textarea('admin_notes', 'Private notes', $o['admin_notes'], ['rows' => 3]) ?>
      <?= f_text('history_note', 'Add history note', '') ?>
      <button class="btn btn-block" type="submit">Save</button>
    </form></section></aside>
</div>
