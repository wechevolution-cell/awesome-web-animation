<?php
$sid = (int) $shop['id'];
$items = menu_items($sid, 'footer');
$cats = rows('SELECT name, path FROM categories WHERE shop_id = ? AND parent_id IS NULL ORDER BY position, name LIMIT 8', [$sid]);
$social = array_filter(['Facebook' => shop_setting($sid, 'social_facebook'), 'Instagram' => shop_setting($sid, 'social_instagram'), 'TikTok' => shop_setting($sid, 'social_tiktok'), 'YouTube' => shop_setting($sid, 'social_youtube'), 'LinkedIn' => shop_setting($sid, 'social_linkedin'), 'X' => shop_setting($sid, 'social_x')]);
$v = $tpl['footer'];
$company = shop_setting($sid, 'company_name', $shop['name']);
?>
<footer class="ftr ftr--<?= e($v) ?>">
  <div class="wrap ftr-grid">
    <div class="ftr-brand">
      <p class="ftr-name"><?= e($shop['name']) ?></p>
      <?php if ($tg = shop_setting($sid, 'tagline')): ?><p class="muted"><?= e($tg) ?></p><?php endif ?>
      <?php if ($social): ?><p class="ftr-social"><?php foreach ($social as $n => $u): if (!safe_url((string) $u)) continue; ?><a href="<?= e($u) ?>" rel="noopener me" target="_blank"><?= e($n) ?></a><?php endforeach ?></p><?php endif ?>
    </div>
    <?php if ($cats && $v !== 'minimal'): ?>
    <nav aria-label="<?= e(t('categories')) ?>"><p class="ftr-h"><?= e(t('shop')) ?></p><ul><?php foreach ($cats as $c): ?><li><a href="<?= e(su('/' . $c['path'] . '/')) ?>"><?= e($c['name']) ?></a></li><?php endforeach ?><li><a href="<?= e(su('/products/')) ?>"><?= e(t('all_products')) ?></a></li></ul></nav>
    <?php endif ?>
    <nav aria-label="<?= e(t('info')) ?>"><p class="ftr-h"><?= e(t('info')) ?></p><ul>
      <?php foreach ($items as $it): $h = menu_href($shop, $it, store()['base']); if (!$h) continue; ?><li><a href="<?= e($h) ?>"><?= e($it['label']) ?></a></li><?php endforeach ?>
    </ul></nav>
    <div class="ftr-contact">
      <p class="ftr-h"><?= e(t('contact')) ?></p>
      <address>
        <?php if ($v = shop_setting($sid, 'contact_address')): ?><span><?= e($v) ?></span><?php endif ?>
        <?php if ($v = shop_setting($sid, 'contact_phone')): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $v)) ?>"><?= e($v) ?></a><?php endif ?>
        <?php if ($v = shop_setting($sid, 'contact_email')): ?><a href="mailto:<?= e($v) ?>"><?= e($v) ?></a><?php endif ?>
        <?php if ($v = shop_setting($sid, 'contact_hours')): ?><span><?= e(t('hours')) ?>: <?= e($v) ?></span><?php endif ?>
      </address>
    </div>
  </div>
  <div class="wrap ftr-base"><p>© <?= date('Y') ?> <?= e($company) ?>. <?= e(t('tax_included')) ?></p></div>
</footer>
