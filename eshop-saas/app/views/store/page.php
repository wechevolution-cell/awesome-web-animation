<?php $sid = (int) $shop['id']; ?>
<div class="wrap narrow-l page">
  <?php partial('store/partials/crumbs', ['crumbs' => $crumbs]) ?>
  <h1><?= e($page['title']) ?></h1>
  <div class="prose"><?= $page['content'] ?></div>
  <?php if ($page['kind'] === 'contact'): ?>
  <div class="contact-grid">
    <form method="post" action="<?= e(su('/contact/send/')) ?>" class="stack contact-form">
      <?= csrf_field() ?><?= form_guard_fields() ?>
      <div class="row2">
        <div class="field"><label for="c-name"><?= e(t('name')) ?> *</label><input id="c-name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div>
        <div class="field"><label for="c-email"><?= e(t('email')) ?> *</label><input id="c-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
      </div>
      <div class="field"><label for="c-phone"><?= e(t('phone')) ?></label><input id="c-phone" type="tel" name="phone" autocomplete="tel" value="<?= e(old('phone')) ?>"></div>
      <div class="field"><label for="c-msg"><?= e(t('message')) ?> *</label><textarea id="c-msg" name="message" rows="5" required><?= e(old('message')) ?></textarea></div>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(t('consent_privacy')) ?> <a href="<?= e(su('/privacy/')) ?>"><?= $shop['locale'] === 'el' ? 'Πολιτική απορρήτου' : 'Privacy policy' ?></a></span></label>
      <button class="btn" type="submit"><?= e(t('send')) ?></button>
    </form>
    <address class="contact-card">
      <?php foreach (['contact_address' => ['pin', t('address')], 'contact_phone' => ['phone', t('phone')], 'contact_email' => ['mail', t('email')], 'contact_hours' => ['clock', t('hours')]] as $k => [$ic, $lbl]): if ($v = shop_setting($sid, $k)): ?>
      <p><?= icon($ic, 18) ?> <span><strong><?= e($lbl) ?></strong><br><?= $k === 'contact_email' ? '<a href="mailto:' . e($v) . '">' . e($v) . '</a>' : ($k === 'contact_phone' ? '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $v)) . '">' . e($v) . '</a>' : e($v)) ?></span></p>
      <?php endif; endforeach ?>
    </address>
  </div>
  <?php endif ?>
  <?php partial('store/partials/faq', ['faq' => $faq]) ?>
</div>
