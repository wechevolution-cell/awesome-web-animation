<?php $img = media_by_id(!empty($d['image_id']) ? (int) $d['image_id'] : null, (int) $shop['id']); if (($d['title'] ?? '') === '' && !$img) return; $link = (string) ($d['cta_link'] ?? ''); ?>
<section class="sec sec-banner">
  <div class="wrap">
    <div class="promo<?= $img ? ' has-img' : '' ?>">
      <?php if ($img): ?><div class="promo-img"><?= img_tag($img, '', '100vw') ?></div><?php endif ?>
      <div class="promo-copy">
        <h2><?= e($d['title']) ?></h2>
        <?php if (!empty($d['text'])): ?><p><?= e($d['text']) ?></p><?php endif ?>
        <?php if (!empty($d['cta_text']) && $link !== ''): ?><a class="btn" href="<?= e(preg_match('#^https?://#', $link) ? $link : su('/' . trim($link, '/') . '/')) ?>"><?= e($d['cta_text']) ?></a><?php endif ?>
      </div>
    </div>
  </div>
</section>
