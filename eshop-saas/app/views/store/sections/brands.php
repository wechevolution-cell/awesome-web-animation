<?php if (!empty($d['items'])): ?>
<section class="sec sec-brands" aria-labelledby="sb-<?= (int) $s['id'] ?>">
  <div class="wrap"><h2 id="sb-<?= (int) $s['id'] ?>" class="sec-title-sm"><?= e($d['title'] ?: t('brand')) ?></h2>
  <ul class="brands"><?php foreach ($d['items'] as $b): ?><li><a href="<?= e(su('/products/') . '?brand[]=' . rawurlencode($b)) ?>"><?= e($b) ?></a></li><?php endforeach ?></ul></div>
</section>
<?php endif ?>
