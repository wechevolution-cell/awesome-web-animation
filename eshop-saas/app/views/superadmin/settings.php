<?php $s = static fn($k, $d = '') => (string) setting($k, $d); $cronUrl = abs_url('/cron/?token=' . $s('cron_token')); ?>
<div class="page-head"><div><h1>Platform settings</h1></div></div>
<form method="post" class="edit-grid"><?= csrf_field() ?>
  <div class="edit-main">
    <section class="card"><h2 class="card-h">Brand & public site</h2>
      <div class="row2"><?= f_text('platform_name', 'Platform name', $s('platform_name')) ?><?= f_text('company_name', 'Company legal name', $s('company_name')) ?></div>
      <?= f_text('hero_title', 'Homepage headline', $s('hero_title')) ?><?= f_textarea('hero_subtitle', 'Homepage subtitle', $s('hero_subtitle'), ['rows' => 2]) ?>
      <div class="row3"><?= f_text('contact_email', 'Public contact email', $s('contact_email'), ['type' => 'email']) ?><?= f_text('contact_phone', 'Public phone', $s('contact_phone')) ?><?= f_text('company_address', 'Address', $s('company_address')) ?></div>
      <div class="row2"><?= f_text('ga4_id', 'GA4 ID (platform site)', $s('ga4_id'), ['placeholder' => 'G-XXXXXXX']) ?><?= f_text('gsc_verification', 'Search Console verification', $s('gsc_verification')) ?></div>
    </section>
    <section class="card"><h2 class="card-h">Domains & security</h2>
      <div class="row2"><?= f_text('canonical_host', 'Platform canonical host', $s('canonical_host'), ['placeholder' => 'yourplatform.gr', 'help' => 'www.<em>host</em> is 301-redirected to it (non-www enforcement).']) ?><?= f_text('platform_hosts', 'Other platform hosts (comma separated)', $s('platform_hosts')) ?></div>
      <div class="row2"><?= f_text('server_ip', 'Server IP (for DNS checks)', $s('server_ip')) ?><?= f_check('force_https', 'Force HTTPS (enable after SSL is active)', $s('force_https') === '1') ?></div>
      <?= f_check('avif_enabled', 'Also generate AVIF images (slower uploads; needs GD with AVIF)', $s('avif_enabled') === '1', ['help' => function_exists('imageavif') ? 'AVIF is supported on this server.' : 'AVIF is not supported by this server\'s GD — WebP only.']) ?>
    </section>
    <section class="card"><h2 class="card-h">Subscriptions</h2><div class="row3">
      <?= f_text('expiry_warning_days', 'Warning days before expiry', $s('expiry_warning_days', '10,3,1'), ['help' => 'Comma separated, e.g. 10,3,1']) ?>
      <?= f_text('expiring_window_days', '"EXPIRING" status window (days)', $s('expiring_window_days', '10'), ['type' => 'number', 'min' => 1]) ?>
      <?= f_text('lock_grace_days', 'Grace days before LOCK after expiry', $s('lock_grace_days', '0'), ['type' => 'number', 'min' => 0, 'help' => '0 = lock immediately on expiry.']) ?>
    </div></section>
    <section class="card"><h2 class="card-h">Email</h2><div class="row2"><?= f_text('notify_email', 'Super Admin notification email', $s('notify_email'), ['type' => 'email']) ?><?= f_text('mail_from', 'From address', $s('mail_from'), ['type' => 'email', 'help' => 'Use a mailbox on your domain (Hostinger Email) for deliverability.']) ?></div></section>
    <section class="card"><h2 class="card-h">Cron</h2>
      <p class="small">Hostinger hPanel → Advanced → Cron Jobs, every 15 minutes:</p>
      <pre class="code small">php <?= e(ROOT) ?>/cron.php</pre>
      <p class="small">or, if only URL cron is available:</p><pre class="code small" data-copy><?= e($cronUrl) ?></pre>
      <?= f_check('regen_cron', 'Regenerate cron token on save', false) ?>
    </section>
  </div>
  <aside class="edit-side"><section class="card sticky"><button class="btn btn-block" type="submit">Save settings</button></section></aside>
</form>
