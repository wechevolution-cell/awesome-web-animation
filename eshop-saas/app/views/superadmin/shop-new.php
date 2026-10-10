<div class="page-head"><div><a class="back" href="/superadmin/shops/">← Shops</a><h1>Create shop</h1><p class="muted">Client details → Domain → Package → Duration → Template → Admin account → Review → Provision</p></div></div>
<form method="post" class="wizard" data-wizard novalidate><?= csrf_field() ?>
  <ol class="wizard-steps" aria-hidden="true"><?php foreach (['Client', 'Domain', 'Package', 'Duration', 'Template', 'Admin', 'Review'] as $i => $s): ?><li data-step-dot="<?= $i ?>"><?= $i + 1 ?>. <?= $s ?></li><?php endforeach ?></ol>
  <fieldset class="card wizard-step" data-step="0"><legend>1. Client details</legend>
    <div class="row2"><?= f_text('name', 'Shop name', '', ['required' => true, 'data' => ['slug-source' => 'slug']]) ?><?= f_text('company', 'Company / legal name', '') ?></div>
    <div class="row3"><?= f_text('owner_name', 'Owner contact', '') ?><?= f_text('email', 'Email', '', ['type' => 'email', 'required' => true]) ?><?= f_text('phone', 'Phone', '') ?></div>
    <div class="row2"><?= f_text('vat_number', 'VAT number (ΑΦΜ)', '') ?><?= f_select('locale', 'Storefront language', ['el' => 'Ελληνικά', 'en' => 'English'], 'el') ?></div>
  </fieldset>
  <fieldset class="card wizard-step" data-step="1"><legend>2. Domain & hosting</legend>
    <div class="row2"><?= f_text('domain', 'Custom domain (optional now)', '', ['placeholder' => 'example.gr', 'help' => 'www and non-www both resolve; the one entered is primary. Without a domain the shop is reachable at /s/<em>slug</em>/ on the platform domain.']) ?>
    <?= f_text('slug', 'Slug', '', ['required' => true, 'help' => 'Internal identifier & fallback URL.']) ?></div>
    <?= f_textarea('hosting_notes', 'Hosting notes', '', ['rows' => 2, 'placeholder' => 'Hostinger account, addon domain, SSL…']) ?>
  </fieldset>
  <fieldset class="card wizard-step" data-step="2"><legend>3. Package</legend>
    <div class="plan-cards"><?php foreach ($plans as $i => $p): ?>
      <label class="plan-card"><input type="radio" name="plan_id" value="<?= (int) $p['id'] ?>" <?= (string) old('plan_id', $i === 0 ? $p['id'] : '') === (string) $p['id'] ? 'checked' : '' ?> data-durations="<?= e($p['duration_options']) ?>" data-price="<?= (int) $p['price_cents'] ?>" data-setup="<?= (int) $p['setup_fee_cents'] ?>" data-name="<?= e($p['name']) ?>">
        <span class="plan-name"><?= e($p['name']) ?></span><span class="plan-price"><?= e(money((int) $p['price_cents'])) ?><small>/<?= (int) $p['billing_period_months'] === 1 ? 'month' : $p['billing_period_months'] . ' months' ?></small></span>
        <span class="plan-limit"><?= (int) $p['active_product_limit'] ?> active products</span><span class="small muted">Store up to <?= number_format((int) $p['stored_product_limit']) ?> · <?= (int) $p['staff_limit'] ?> users · setup <?= e(money((int) $p['setup_fee_cents'])) ?></span>
      </label>
    <?php endforeach ?></div><?= f_err('plan_id') ?>
  </fieldset>
  <fieldset class="card wizard-step" data-step="3"><legend>4. Duration</legend>
    <div class="row3"><?= f_select('months', 'Subscription duration', ['1' => '1 month', '3' => '3 months', '6' => '6 months', '12' => '12 months'], '1', ['data' => ['duration-select' => '1']]) ?>
    <?= f_text('start_date', 'Start date', date('Y-m-d'), ['type' => 'date']) ?>
    <?= f_select('status', 'Initial status', ['ACTIVE' => 'ACTIVE (live)', 'PENDING' => 'PENDING (not live yet)'], 'ACTIVE') ?></div>
    <div class="row3"><?= f_select('payment_provider', 'Record payment (optional)', ['' => '— none —', 'manual' => 'Manual', 'bank_transfer' => 'Bank transfer'], '') ?><?= f_check('payment_paid', 'Mark as paid', true) ?></div>
  </fieldset>
  <fieldset class="card wizard-step" data-step="4"><legend>5. Template</legend>
    <div class="tpl-cards"><?php foreach ($templates as $i => $t): ?>
      <label class="tpl-card"><input type="radio" name="template_key" value="<?= e($t['key']) ?>" <?= old('template_key', $i === 0 ? $t['key'] : '') === $t['key'] ? 'checked' : '' ?>>
        <span class="tpl-thumb"><img src="/assets/img/templates/<?= e($t['key']) ?>.webp" alt="" width="320" height="200" loading="lazy"></span>
        <span class="tpl-name"><?= e($t['name']) ?></span><span class="small muted"><?= e($t['style']) ?></span>
        <?php if (isset($demo[$t['key']])): ?><a class="small" href="/s/<?= e($demo[$t['key']]) ?>/" target="_blank" rel="noopener">Live demo ↗</a><?php endif ?>
      </label>
    <?php endforeach ?></div>
  </fieldset>
  <fieldset class="card wizard-step" data-step="5"><legend>6. Shop admin account</legend>
    <div class="row2"><?= f_text('admin_email', 'Admin email (login)', '', ['type' => 'email', 'required' => true, 'help' => 'Must not already have an account.']) ?>
    <?= f_text('admin_password', 'Password (optional)', '', ['type' => 'password', 'autocomplete' => 'new-password', 'help' => 'Leave empty to email a secure "set your password" link instead (recommended — no passwords travel by email).']) ?></div>
  </fieldset>
  <fieldset class="card wizard-step" data-step="6"><legend>7. Review & provision</legend>
    <dl class="dl review" data-review></dl>
    <p class="small muted">Provisioning creates the shop, its owner account, starter pages, menus and homepage sections, the first subscription period and the history entry "creation".</p>
  </fieldset>
  <div class="wizard-nav"><button type="button" class="btn btn-ghost" data-wiz-prev>Back</button><button type="button" class="btn" data-wiz-next>Next</button><button type="submit" class="btn" data-wiz-submit><?= icon('bolt', 16) ?> Provision shop</button></div>
</form>
