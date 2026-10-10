<?php if (!empty($d['items'])): ?>
<section class="sec sec-benefits" aria-label="<?= e(t('info')) ?>">
  <div class="wrap"><ul class="benefits">
    <?php foreach ($d['items'] as $b): ?><li><span class="benefit-ic"><?= icon($b['icon'] ?? 'star', 26) ?></span><h3><?= e($b['title']) ?></h3><?php if (!empty($b['text'])): ?><p><?= e($b['text']) ?></p><?php endif ?></li><?php endforeach ?>
  </ul></div>
</section>
<?php endif ?>
