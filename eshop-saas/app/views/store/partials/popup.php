<?php $img = media_by_id($popup['image_id'] ? (int) $popup['image_id'] : null, (int) $shop['id']); ?>
<dialog class="popup" id="popup" closedby="any" aria-labelledby="popup-title" data-delay="<?= (int) $popup['delay_seconds'] ?>" data-popup-id="<?= (int) $popup['id'] ?>">
  <div class="popup-card">
    <button class="act popup-x" type="button" data-dialog-close aria-label="<?= e(t('close')) ?>"><?= icon('close', 20) ?></button>
    <?php if ($img): ?><div class="popup-media"><?= img_tag($img, '', '400px') ?></div><?php endif ?>
    <div class="popup-body">
      <h2 id="popup-title"><?= e($popup['title']) ?></h2>
      <?php if ($popup['body']): ?><p><?= nl2br(e($popup['body'])) ?></p><?php endif ?>
      <?php if ($popup['collect_email']): ?>
      <form method="post" action="<?= e(su('/popup/subscribe/')) ?>" data-ajax-subscribe>
        <?= csrf_field() ?><?= form_guard_fields() ?><input type="hidden" name="popup_id" value="<?= (int) $popup['id'] ?>">
        <label class="sr" for="pp-email"><?= e(t('email')) ?></label>
        <div class="inline-form"><input id="pp-email" type="email" name="email" required placeholder="<?= e(t('email')) ?>" autocomplete="email"><button class="btn" type="submit"><?= e($popup['button_text'] ?: t('subscribe')) ?></button></div>
        <?php if ($popup['consent_required']): ?><label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e($popup['consent_text'] ?: t('consent_privacy')) ?></span></label><?php endif ?>
        <p class="form-msg" data-form-msg></p>
      </form>
      <?php elseif ($popup['cta_link'] && safe_url($popup['cta_link'])): ?>
      <a class="btn" href="<?= e(str_starts_with($popup['cta_link'], 'http') ? $popup['cta_link'] : su($popup['cta_link'])) ?>"><?= e($popup['button_text'] ?: t('view_all')) ?></a>
      <?php endif ?>
    </div>
  </div>
</dialog>
