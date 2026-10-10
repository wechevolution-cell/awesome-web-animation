<?php $sid = (int) $shop['id']; $ss = static fn($k) => (string) shop_setting($sid, $k, ''); ?>
<div class="page-head"><div><h1>Appearance</h1><p class="muted">Template: <strong><?= e($tpl['name']) ?></strong> — <?= e($tpl['style']) ?>. The template is chosen by your provider; you control branding safely through design tokens.</p></div>
<div class="page-actions"><a class="btn btn-ghost btn-sm" href="<?= e(shop_public_url($shop)) ?>" target="_blank" rel="noopener"><?= icon('external', 16) ?> View store</a></div></div>
<form method="post" class="edit-grid"><?= csrf_field() ?>
  <div class="edit-main">
    <section class="card"><h2 class="card-h">Logo & favicon</h2><div class="row2">
      <?= f_media('logo_id', 'Logo', (int) $ss('logo_id') ?: null, $sid, ['help' => 'SVG not allowed for security; use PNG/WebP with transparent background.']) ?>
      <?= f_media('favicon_id', 'Favicon', (int) $ss('favicon_id') ?: null, $sid, ['help' => 'Square image, at least 192×192.']) ?>
    </div></section>
    <section class="card"><h2 class="card-h">Colours</h2>
      <?php foreach ($warnings as $w): ?><div class="notice notice-warn"><?= e($w) ?></div><?php endforeach ?>
      <div class="swatches">
      <?php foreach (token_keys() as $k => $label): ?>
        <div class="swatch"><label for="c_<?= $k ?>"><?= e($label) ?></label><div class="swatch-in"><input type="color" id="c_<?= $k ?>" name="c_<?= $k ?>" value="<?= e($tokens[$k]) ?>" data-color-sync><input type="text" value="<?= e($tokens[$k]) ?>" pattern="#[0-9a-fA-F]{6}" aria-label="<?= e($label) ?> hex" data-color-text maxlength="7"></div><small class="muted">Default <?= e($tpl['tokens'][$k]) ?></small></div>
      <?php endforeach ?>
      </div>
      <div class="token-preview" data-token-preview style="--p:<?= e($tokens['primary']) ?>;--bg:<?= e($tokens['background']) ?>;--tx:<?= e($tokens['text']) ?>;--bt:<?= e($tokens['button_text']) ?>;--ac:<?= e($tokens['accent']) ?>;--lk:<?= e($tokens['link']) ?>;--sf:<?= e($tokens['surface']) ?>;--mu:<?= e($tokens['muted']) ?>">
        <p class="tp-h">Preview heading</p><p>Body text with a <span class="tp-lk">link</span> and <span class="tp-mu">muted text</span>.</p><span class="tp-btn">Add to cart</span> <span class="tp-badge">Sale</span>
      </div>
      <button class="btn btn-xs btn-link" type="submit" name="act" value="reset_colors" formnovalidate data-confirm="Reset all colours to the template defaults?">Reset to template defaults</button>
    </section>
    <section class="card"><h2 class="card-h">Typography</h2>
      <?php if ($typo): ?><div class="row2"><?= f_select('font_heading', 'Headings', font_choices(), $ss('font_heading') ?: 'template') ?><?= f_select('font_body', 'Body', font_choices(), $ss('font_body') ?: 'template') ?></div><p class="f-help">System font stacks only — fast and no third-party font requests (GDPR friendly).</p>
      <?php else: ?><p class="muted">Custom typography is available on higher packages. Your template fonts are used.</p><?php endif ?>
    </section>
    <section class="card" id="contact"><h2 class="card-h">Business & contact information</h2>
      <div class="row2"><?= f_text('company_name', 'Company name', $ss('company_name')) ?><?= f_text('tagline', 'Tagline', $ss('tagline'), ['help' => 'One factual sentence about the shop — used in meta description and llms.txt.']) ?></div>
      <?= f_text('contact_address', 'Address', $ss('contact_address')) ?>
      <div class="row3"><?= f_text('contact_phone', 'Phone', $ss('contact_phone')) ?><?= f_text('contact_email', 'Email', $ss('contact_email'), ['type' => 'email']) ?><?= f_text('contact_hours', 'Opening hours', $ss('contact_hours')) ?></div>
      <?= f_text('announcement', 'Announcement bar (optional)', $ss('announcement'), ['maxlength' => 200]) ?>
    </section>
    <section class="card"><h2 class="card-h">Social links</h2><div class="row3">
      <?php foreach (['social_facebook' => 'Facebook', 'social_instagram' => 'Instagram', 'social_tiktok' => 'TikTok', 'social_youtube' => 'YouTube', 'social_linkedin' => 'LinkedIn', 'social_x' => 'X'] as $k => $l): ?><?= f_text($k, $l, $ss($k), ['type' => 'url', 'placeholder' => 'https://']) ?><?php endforeach ?>
    </div></section>
  </div>
  <aside class="edit-side"><section class="card sticky"><button class="btn btn-block" type="submit">Save appearance</button><p class="small muted">Changes are live immediately. Templates are presentation-only: switching template never touches your products, orders or settings.</p></section></aside>
</form>
