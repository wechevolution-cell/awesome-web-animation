<?php
$tpl = store()['tpl'];
$loc = $shop['locale'];
$price = product_effective_price($p);
$sale = product_on_sale($p);
$stock = product_in_stock($p);
$imgs = $p['images'];
$vdata = array_map(static fn($v) => ['id' => (int) $v['id'], 'label' => variant_label($v['opts']), 'price' => money($v['price_cents'] !== null ? (int) $v['price_cents'] : $price, $loc), 'stock' => (int) $p['manage_stock'] ? (int) $v['stock'] : 999], $variants);
?>
<div class="wrap pdp pdp--<?= e($tpl['product']) ?>">
  <?php partial('store/partials/crumbs', ['crumbs' => $crumbs]) ?>
  <div class="pdp-grid">
    <div class="gallery" data-gallery>
      <div class="gallery-main">
        <?php if ($imgs): foreach ($imgs as $i => $m): ?>
          <figure class="gallery-slide<?= $i === 0 ? ' is-on' : '' ?>" data-slide="<?= $i ?>"><a href="<?= e(media_url($m)) ?>" data-zoom aria-label="Zoom"><?= img_tag($m, $i === 0 ? $p['name'] : $p['name'] . ' ' . ($i + 1), '(max-width: 900px) 100vw, 55vw', ['loading' => $i === 0 ? 'eager' : 'lazy']) ?></a></figure>
        <?php endforeach; else: ?>
          <figure class="gallery-slide is-on"><img src="<?= e(su('/img/ph/' . $p['slug'] . '.svg') . '?l=' . rawurlencode(mb_substr($p['name'], 0, 1))) ?>" alt="<?= e($p['name']) ?>" width="800" height="800"></figure>
        <?php endif ?>
      </div>
      <?php if (count($imgs) > 1): ?>
      <ul class="thumbs" role="list"><?php foreach ($imgs as $i => $m): ?><li><button type="button" class="thumb<?= $i === 0 ? ' is-on' : '' ?>" data-thumb="<?= $i ?>" aria-label="<?= e($p['name']) ?> <?= $i + 1 ?>"><?= img_tag($m, '', '96px') ?></button></li><?php endforeach ?></ul>
      <?php endif ?>
    </div>
    <div class="buybox">
      <?php if ($p['brand']): ?><p class="pdp-brand"><a href="<?= e(su('/products/') . '?brand[]=' . rawurlencode($p['brand'])) ?>"><?= e($p['brand']) ?></a></p><?php endif ?>
      <h1 class="pdp-title"><?= e($p['name']) ?></h1>
      <p class="price price-lg"><span class="price-now" data-price><?= e(money($price, $loc, $shop['currency'])) ?></span><?php if ($sale): ?> <s class="price-was"><span class="sr"><?= e(t('compare_at')) ?></span><?= e(money((int) $p['price_cents'], $loc)) ?></s> <span class="badge-sale">−<?= (int) round((1 - $price / max(1, $p['price_cents'])) * 100) ?>%</span><?php endif ?></p>
      <p class="stock <?= $stock ? 'in' : 'out' ?>" data-stock><?= icon($stock ? 'check' : 'close', 16) ?> <?= e($stock ? ((int) $p['manage_stock'] && (int) $p['stock'] <= 3 && !$variants ? t('low_stock') : t('in_stock')) : t('out_of_stock')) ?></p>
      <?php if ($p['short_description']): ?><p class="pdp-short"><?= nl2br(e($p['short_description'])) ?></p><?php endif ?>
      <form method="post" action="<?= e(su('/cart/add/')) ?>" class="buy-form" data-add-to-cart data-variants="<?= e(jenc($vdata)) ?>">
        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <?php if ($variants): ?>
        <div class="field"><label for="variant"><?= e(t('variant')) ?></label>
          <select id="variant" name="variant_id" required data-variant-select>
            <option value=""><?= e(t('choose_option')) ?>…</option>
            <?php foreach ($variants as $v): $vs = !(int) $p['manage_stock'] || (int) $v['stock'] > 0; ?><option value="<?= (int) $v['id'] ?>" <?= $vs ? '' : 'disabled' ?>><?= e(variant_label($v['opts'])) ?><?= $v['price_cents'] !== null ? ' — ' . e(money((int) $v['price_cents'], $loc)) : '' ?><?= $vs ? '' : ' (' . e(t('out_of_stock')) . ')' ?></option><?php endforeach ?>
          </select>
        </div>
        <?php endif ?>
        <div class="buy-row">
          <div class="qty" data-qty><button type="button" class="act" data-qty-dec aria-label="−"><?= icon('minus', 16) ?></button><label class="sr" for="qty"><?= e(t('quantity')) ?></label><input id="qty" type="number" name="qty" value="1" min="1" max="99" inputmode="numeric"><button type="button" class="act" data-qty-inc aria-label="+"><?= icon('plus', 16) ?></button></div>
          <button class="btn btn-lg btn-buy" type="submit" <?= $stock ? '' : 'disabled' ?>><?= icon('cart', 18) ?> <?= e(t('add_to_cart')) ?></button>
        </div>
      </form>
      <form method="post" action="<?= e(su('/wishlist/toggle/')) ?>" data-wishlist class="wish-form">
        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <button class="btn btn-ghost btn-sm<?= $inWish ? ' is-on' : '' ?>" type="submit" aria-pressed="<?= $inWish ? 'true' : 'false' ?>"><?= icon('heart', 16) ?> <span><?= e($inWish ? t('remove_wishlist') : t('add_wishlist')) ?></span></button>
      </form>
      <ul class="pdp-meta">
        <?php if ($p['sku']): ?><li><span><?= e(t('sku')) ?></span> <span><?= e($p['sku']) ?></span></li><?php endif ?>
        <?php if ($cat): ?><li><span><?= e(t('categories')) ?></span> <a href="<?= e(su('/' . $cat['path'] . '/')) ?>"><?= e($cat['name']) ?></a></li><?php endif ?>
      </ul>
      <ul class="pdp-assure">
        <li><?= icon('truck', 18) ?> <a href="<?= e(su('/shipping/')) ?>"><?= e(t('shipping')) ?></a></li>
        <li><?= icon('return', 18) ?> <a href="<?= e(su('/returns/')) ?>"><?= e($loc === 'el' ? 'Επιστροφές' : 'Returns') ?></a></li>
        <li><?= icon('shield', 18) ?> <?= e(t('secure_checkout')) ?></li>
      </ul>
    </div>
  </div>
  <div class="pdp-details">
    <?php if ($p['description']): ?><section class="pdp-sec"><h2><?= e(t('description')) ?></h2><div class="prose"><?= $p['description'] ?></div></section><?php endif ?>
    <?php if ($attrs || $p['weight_g'] || $p['length_mm']): ?>
    <section class="pdp-sec"><h2><?= e(t('specifications')) ?></h2>
      <table class="specs"><tbody>
        <?php foreach ($attrs as $a): ?><tr><th scope="row"><?= e($a['name']) ?></th><td><?= e($a['value']) ?></td></tr><?php endforeach ?>
        <?php if ($p['weight_g']): ?><tr><th scope="row"><?= $loc === 'el' ? 'Βάρος' : 'Weight' ?></th><td><?= (int) $p['weight_g'] ?> g</td></tr><?php endif ?>
        <?php if ($p['length_mm'] && $p['width_mm'] && $p['height_mm']): ?><tr><th scope="row"><?= $loc === 'el' ? 'Διαστάσεις' : 'Dimensions' ?></th><td><?= (int) $p['length_mm'] ?> × <?= (int) $p['width_mm'] ?> × <?= (int) $p['height_mm'] ?> mm</td></tr><?php endif ?>
      </tbody></table>
    </section>
    <?php endif ?>
    <?php partial('store/partials/faq', ['faq' => $faq]) ?>
  </div>
  <?php if ($related): ?>
  <section class="sec related" aria-labelledby="rel-h"><h2 id="rel-h"><?= e(t('related')) ?></h2><div class="pgrid cols-4"><?php foreach ($related as $r) partial('store/partials/card', ['p' => $r]); ?></div></section>
  <?php endif ?>
</div>
<div class="sticky-buy" data-sticky-buy hidden><div class="wrap"><span class="sticky-name"><?= e($p['name']) ?></span><span class="price-now"><?= e(money($price, $loc)) ?></span><a class="btn btn-sm" href="#main" data-scroll-buy><?= e(t('add_to_cart')) ?></a></div></div>
