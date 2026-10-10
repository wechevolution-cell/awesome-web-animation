<?php $x = $plan ?? []; $dur = array_map('intval', explode(',', $x['duration_options'] ?? '1,3,6,12')); ?>
<div class="page-head"><div><a class="back" href="/superadmin/plans/">← Packages</a><h1><?= $plan ? e($plan['name']) : 'New package' ?></h1></div></div>
<form method="post" class="edit-grid"><?= csrf_field() ?>
  <div class="edit-main">
    <section class="card"><div class="row2"><?= f_text('name', 'Name', $x['name'] ?? '', ['required' => true]) ?><?= f_text('slug', 'Slug', $x['slug'] ?? '') ?></div><?= f_text('description', 'Description', $x['description'] ?? '') ?></section>
    <section class="card"><h2 class="card-h">Pricing</h2><div class="row3">
      <?= f_text('price', 'Price per period (€)', cents_input(isset($x['price_cents']) ? (int) $x['price_cents'] : null), ['required' => true]) ?>
      <?= f_text('billing_period_months', 'Billing period (months)', (string) ($x['billing_period_months'] ?? 1), ['type' => 'number', 'min' => 1]) ?>
      <?= f_text('setup_fee', 'Setup fee (€)', cents_input(isset($x['setup_fee_cents']) ? (int) $x['setup_fee_cents'] : 15000)) ?>
    </div>
    <fieldset class="card-sub"><legend>Durations offered</legend><?= f_err('durations') ?><div class="check-grid"><?php foreach ([1, 3, 6, 12, 24] as $d): ?><label class="check"><input type="checkbox" name="durations[]" value="<?= $d ?>" <?= in_array($d, $dur, true) ? 'checked' : '' ?>> <?= $d ?> month<?= $d > 1 ? 's' : '' ?></label><?php endforeach ?></div></fieldset></section>
    <section class="card"><h2 class="card-h">Limits</h2><div class="row4">
      <?= f_text('active_product_limit', 'Active products', (string) ($x['active_product_limit'] ?? 20), ['type' => 'number', 'min' => 1, 'required' => true]) ?>
      <?= f_text('stored_product_limit', 'Stored products', (string) ($x['stored_product_limit'] ?? 10000), ['type' => 'number', 'min' => 1]) ?>
      <?= f_text('staff_limit', 'Users', (string) ($x['staff_limit'] ?? 2), ['type' => 'number', 'min' => 1]) ?>
      <?= f_text('storage_mb', 'Media storage (MB)', (string) ($x['storage_mb'] ?? 1024), ['type' => 'number', 'min' => 50]) ?>
    </div><p class="f-help">Lowering the active limit flags shops that exceed it; their admins choose which products stay active. Nothing is deleted.</p></section>
    <section class="card"><h2 class="card-h">Feature flags</h2><div class="check-grid">
      <?php foreach (['import' => 'CSV/XML import', 'feeds' => 'Product XML feed', 'marketplace_feeds' => 'Skroutz / BestPrice feeds', 'custom_feeds' => 'Custom XML feeds', 'popups' => 'Popups', 'seo_tools' => 'SEO analyzer', 'typography' => 'Custom typography'] as $k => $l): ?><?= f_check('f_' . $k, $l, ($features[$k] ?? '0') === '1') ?><?php endforeach ?>
    </div></section>
  </div>
  <aside class="edit-side"><section class="card sticky"><?= f_select('status', 'Status', ['active' => 'Active (offered)', 'archived' => 'Archived (existing shops keep it)'], $x['status'] ?? 'active') ?><?= f_text('position', 'Order', (string) ($x['position'] ?? 0), ['type' => 'number']) ?><button class="btn btn-block" type="submit">Save package</button></section></aside>
</form>
