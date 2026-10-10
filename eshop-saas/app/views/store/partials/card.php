<?php
/** Product card. Template card variant via body class `card-*`; a few structural options below. */
$card = store()['tpl']['card'];
$url = su('/product/' . $p['slug'] . '/');
$price = product_effective_price($p);
$sale = product_on_sale($p);
$stock = product_in_stock($p);
$loc = store()['shop']['locale'];
$img = $p['image'] ?? null;
$second = $p['images'][1] ?? null;
$isNew = $p['activated_at'] && strtotime($p['activated_at']) > time() - 86400 * 21;
$hasVariants = $p['_has_variants'] ?? (bool) val('SELECT 1 FROM product_variants WHERE product_id = ? LIMIT 1', [$p['id']]);
?>
<article class="pcard">
  <a class="pcard-media" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($img): ?><?= img_tag($img, $p['name'], '(max-width: 600px) 50vw, 25vw', ['class' => 'pcard-img']) ?>
      <?php if ($second && in_array($card, ['minimal', 'editorial', 'line', 'overlay'], true)): ?><?= img_tag($second, '', '(max-width: 600px) 50vw, 25vw', ['class' => 'pcard-img2']) ?><?php endif ?>
    <?php else: ?><img class="pcard-img" src="<?= e(su('/img/ph/' . $p['slug'] . '.svg') . '?l=' . rawurlencode(mb_substr($p['name'], 0, 1))) ?>" alt="" width="800" height="800" loading="lazy" decoding="async"><?php endif ?>
    <span class="badges"><?php if ($sale): ?><span class="badge-sale"><?= e(t('sale')) ?> −<?= (int) round((1 - $price / max(1, $p['price_cents'])) * 100) ?>%</span><?php elseif ($isNew): ?><span class="badge-new"><?= e(t('new')) ?></span><?php endif ?><?php if (!$stock): ?><span class="badge-out"><?= e(t('out_of_stock')) ?></span><?php endif ?></span>
  </a>
  <div class="pcard-body">
    <?php if ($p['brand'] && in_array($card, ['spec', 'compact', 'editorial', 'line', 'brutal'], true)): ?><p class="pcard-brand"><?= e($p['brand']) ?></p><?php endif ?>
    <h3 class="pcard-title"><a href="<?= e($url) ?>"><?= e($p['name']) ?></a></h3>
    <?php if ($card === 'spec' && $p['short_description']): ?><p class="pcard-spec"><?= e(str_limit($p['short_description'], 80)) ?></p><?php endif ?>
    <p class="price"><span class="price-now"><?= e(money($price, $loc, store()['shop']['currency'])) ?></span><?php if ($sale): ?> <s class="price-was"><span class="sr"><?= e(t('compare_at')) ?></span><?= e(money((int) $p['price_cents'], $loc)) ?></s><?php endif ?></p>
  </div>
  <div class="pcard-actions">
    <?php if ($stock && !$hasVariants): ?>
    <form method="post" action="<?= e(su('/cart/add/')) ?>" data-add-to-cart>
      <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="qty" value="1">
      <button class="btn btn-sm pcard-add" type="submit" aria-label="<?= e(t('add_to_cart') . ': ' . $p['name']) ?>"><?= icon('plus', 16) ?><span><?= e(in_array($card, ['quick', 'compact'], true) ? t('quick_add') : t('add_to_cart')) ?></span></button>
    </form>
    <?php else: ?>
    <a class="btn btn-sm btn-ghost pcard-add" href="<?= e($url) ?>"><span><?= e(t('details')) ?></span></a>
    <?php endif ?>
    <form method="post" action="<?= e(su('/wishlist/toggle/')) ?>" data-wishlist>
      <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
      <?php $inW = in_array((int) $p['id'], arr($_SESSION['wishlist'][store()['shop']['id']] ?? []), true); ?>
      <button class="act pcard-wish<?= $inW ? ' is-on' : '' ?>" type="submit" aria-pressed="<?= $inW ? 'true' : 'false' ?>" aria-label="<?= e(t('add_wishlist') . ': ' . $p['name']) ?>"><?= icon('heart', 18) ?></button>
    </form>
  </div>
</article>
