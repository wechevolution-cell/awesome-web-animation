<?php if (!empty($s['products'])): ?>
<section class="sec sec-products" aria-labelledby="sp-<?= (int) $s['id'] ?>">
  <div class="wrap">
    <div class="sec-head"><h2 id="sp-<?= (int) $s['id'] ?>"><?= e($d['title'] ?: t('featured')) ?></h2><a class="link-arrow" href="<?= e(su('/products/')) ?>"><?= e(t('view_all')) ?> <?= icon('arrow', 16) ?></a></div>
    <div class="pgrid cols-<?= (int) store()['tpl']['cols'] ?>">
      <?php foreach ($s['products'] as $p) partial('store/partials/card', ['p' => $p]); ?>
    </div>
  </div>
</section>
<?php endif ?>
