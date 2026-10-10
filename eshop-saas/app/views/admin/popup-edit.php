<?php $x = $p ?? []; ?>
<div class="page-head"><div><a class="back" href="/admin/popups/">← Popups</a><h1><?= $p ? e($p['title']) : 'New popup' ?></h1></div></div>
<form method="post" class="edit-grid"><?= csrf_field() ?>
  <div class="edit-main"><section class="card">
    <?= f_text('title', 'Title', $x['title'] ?? '', ['required' => true]) ?>
    <?= f_textarea('body', 'Body text', $x['body'] ?? '', ['rows' => 3]) ?>
    <div class="row2"><?= f_text('button_text', 'Button text', $x['button_text'] ?? '') ?><?= f_text('cta_link', 'CTA link (when not collecting email)', $x['cta_link'] ?? '') ?></div>
    <?= f_media('image_id', 'Image (optional)', isset($x['image_id']) ? (int) $x['image_id'] : null, (int) $shop['id']) ?>
    <?= f_check('collect_email', 'Collect email address', (bool) ($x['collect_email'] ?? 1)) ?>
    <?= f_check('consent_required', 'Require privacy consent checkbox (recommended for GDPR)', (bool) ($x['consent_required'] ?? 1)) ?>
    <?= f_text('consent_text', 'Consent text', $x['consent_text'] ?? '', ['placeholder' => t('consent_privacy')]) ?>
  </section></div>
  <aside class="edit-side"><section class="card sticky">
    <?= f_text('delay_seconds', 'Show after (seconds)', (string) ($x['delay_seconds'] ?? 5), ['type' => 'number', 'min' => 0, 'max' => 120]) ?>
    <?= f_check('active', 'Active on homepage', (bool) ($x['active'] ?? 0), ['help' => 'Shown once per visitor session.']) ?>
    <button class="btn btn-block" type="submit">Save popup</button>
    <?php if ($p): ?><button class="btn btn-danger btn-sm" name="act" value="delete" formnovalidate data-confirm="Delete this popup? Collected subscribers are kept.">Delete</button><?php endif ?>
  </section></aside>
</form>
