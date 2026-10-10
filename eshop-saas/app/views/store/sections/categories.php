<?php if (!empty($s['categories'])): ?>
<section class="sec sec-cats" aria-labelledby="sc-<?= (int) $s['id'] ?>">
  <div class="wrap">
    <div class="sec-head"><h2 id="sc-<?= (int) $s['id'] ?>"><?= e($d['title'] ?: t('shop_by_category')) ?></h2><a class="link-arrow" href="<?= e(su('/products/')) ?>"><?= e(t('view_all')) ?> <?= icon('arrow', 16) ?></a></div>
    <ul class="cat-grid">
      <?php foreach ($s['categories'] as $i => $c): ?>
      <li class="cat-card">
        <a href="<?= e(su('/' . $c['path'] . '/')) ?>">
          <span class="cat-media"><?php if ($c['media']): ?><?= img_tag($c['media'], '', '(max-width:600px) 50vw, 20vw') ?><?php else: ?><img src="<?= e(su('/img/ph/' . $c['slug'] . '.svg') . '?l=' . rawurlencode(mb_substr($c['name'], 0, 1))) ?>" alt="" width="800" height="800" loading="lazy"><?php endif ?></span>
          <span class="cat-name"><?= e($c['name']) ?></span>
          <span class="cat-count"><?= e(t('products_count', ['n' => $c['count']])) ?></span>
        </a>
      </li>
      <?php endforeach ?>
    </ul>
  </div>
</section>
<?php endif ?>
