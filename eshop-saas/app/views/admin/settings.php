<?php $sid = (int) $shop['id']; $ss = static fn($k, $d = '') => (string) shop_setting($sid, $k, $d); ?>
<div class="page-head"><div><h1>Store settings</h1></div></div>
<div class="edit-grid">
<form method="post" class="edit-main"><?= csrf_field() ?>
  <section class="card"><h2 class="card-h">Shipping</h2><div class="row3">
    <?= f_text('shipping_flat', 'Courier cost (€)', cents_input((int) $ss('shipping_flat_cents', '0')), ['inputmode' => 'decimal']) ?>
    <?= f_text('free_shipping_over', 'Free shipping from (€, 0 = never)', cents_input((int) $ss('free_shipping_over_cents', '0')), ['inputmode' => 'decimal']) ?>
    <?= f_check('pickup_enabled', 'Offer store pickup', $ss('pickup_enabled') === '1') ?>
  </div></section>
  <section class="card"><h2 class="card-h">Payment methods</h2>
    <div class="row3"><?= f_check('payment_cod', 'Cash on delivery', $ss('payment_cod') === '1') ?><?= f_text('cod_fee', 'COD fee (€)', cents_input((int) $ss('cod_fee_cents', '0')), ['inputmode' => 'decimal']) ?><?= f_check('payment_bank', 'Bank transfer', $ss('payment_bank') === '1') ?></div>
    <?= f_textarea('bank_details', 'Bank transfer details (shown after checkout)', $ss('bank_details'), ['rows' => 3, 'placeholder' => "Bank: …\nIBAN: GR…\nBeneficiary: …"]) ?>
    <p class="f-help">Card payments (Stripe / Viva Wallet) are prepared in the platform architecture and can be enabled by your provider in a future phase.</p>
  </section>
  <section class="card"><h2 class="card-h">Store</h2><div class="row2">
    <?= f_select('locale', 'Storefront language', ['el' => 'Ελληνικά', 'en' => 'English'], $shop['locale']) ?>
    <?= f_text('order_email', 'Order notification email', $ss('order_email', (string) $shop['email']), ['type' => 'email']) ?>
  </div>
  <button class="btn" type="submit">Save settings</button></section>
</form>
<aside class="edit-side"><section class="card"><h2 class="card-h">Your package</h2>
  <dl class="dl"><dt>Package</dt><dd><?= e($plan['name'] ?? '—') ?></dd><dt>Status</dt><dd><?= status_badge($shop['status']) ?></dd><dt>Expires</dt><dd><?= e(fmt_date($shop['subscription_expires'])) ?></dd><dt>Template</dt><dd><?= e(template_get($shop['template_key'])['name']) ?></dd></dl>
  <?= quota_bar($q, true) ?>
  <ul class="feat-list small"><?php foreach (['import' => 'CSV/XML import', 'feeds' => 'Product XML feed', 'marketplace_feeds' => 'Skroutz / BestPrice feeds', 'custom_feeds' => 'Custom XML feeds', 'popups' => 'Popups', 'typography' => 'Custom typography'] as $k => $l): ?><li class="<?= ($features[$k] ?? '0') === '1' ? 'ok' : 'off' ?>"><?= icon(($features[$k] ?? '0') === '1' ? 'check' : 'close', 14) ?> <?= e($l) ?></li><?php endforeach ?></ul>
  <p class="small muted">To change package or renew, contact your provider.</p>
</section></aside>
</div>
