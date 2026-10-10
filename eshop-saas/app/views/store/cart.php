<?php $loc = $shop['locale']; $shipCost = $ship ? min(array_column($ship, 'cost')) : 0; ?>
<div class="wrap narrow-l cart-page">
  <h1><?= e(t('cart')) ?></h1>
  <?php if (!$cart['lines']): ?>
    <div class="empty"><p><?= e(t('empty_cart')) ?></p><a class="btn" href="<?= e(su('/products/')) ?>"><?= e(t('continue_shopping')) ?></a></div>
  <?php else: ?>
  <form method="post" action="<?= e(su('/cart/update/')) ?>" class="cart-grid">
    <?= csrf_field() ?>
    <div class="cart-lines">
      <table class="cart-table">
        <thead><tr><th scope="col"><?= e(t('items')) ?></th><th scope="col"><?= e(t('price')) ?></th><th scope="col"><?= e(t('quantity')) ?></th><th scope="col"><?= e(t('total')) ?></th><th><span class="sr"><?= e(t('remove')) ?></span></th></tr></thead>
        <tbody>
        <?php foreach ($cart['lines'] as $l): $p = $l['product']; ?>
          <tr>
            <td class="cl-item"><a class="cl-img" href="<?= e(su('/product/' . $p['slug'] . '/')) ?>"><?php if ($p['image']): ?><?= img_tag($p['image'], '', '80px') ?><?php else: ?><img src="<?= e(su('/img/ph/' . $p['slug'] . '.svg')) ?>" alt="" width="80" height="80"><?php endif ?></a>
              <div><a href="<?= e(su('/product/' . $p['slug'] . '/')) ?>"><?= e($p['name']) ?></a><?php if ($l['variant']): ?><small class="muted"><?= e(variant_label(jdec($l['variant']['options']))) ?></small><?php endif ?></div></td>
            <td data-label="<?= e(t('price')) ?>"><?= e(money($l['price'], $loc)) ?></td>
            <td data-label="<?= e(t('quantity')) ?>"><label class="sr" for="q<?= e($l['key']) ?>"><?= e(t('quantity')) ?></label><input class="qty-input" id="q<?= e($l['key']) ?>" type="number" name="qty[<?= e($l['key']) ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="<?= (int) ($l['max'] ?? 99) ?>"></td>
            <td data-label="<?= e(t('total')) ?>"><strong><?= e(money($l['total'], $loc)) ?></strong></td>
            <td><button class="act" type="submit" name="remove" value="<?= e($l['key']) ?>" aria-label="<?= e(t('remove') . ': ' . $p['name']) ?>"><?= icon('trash', 18) ?></button></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
      <div class="cart-actions"><button class="btn btn-ghost btn-sm" type="submit"><?= e(t('update')) ?></button> <a class="link-arrow" href="<?= e(su('/products/')) ?>"><?= e(t('continue_shopping')) ?></a></div>
    </div>
    <aside class="summary">
      <h2><?= e(t('order_summary')) ?></h2>
      <dl><div><dt><?= e(t('subtotal')) ?></dt><dd><?= e(money($cart['subtotal'], $loc)) ?></dd></div>
      <div><dt><?= e(t('shipping')) ?></dt><dd><?= $shipCost === 0 ? e(t('free')) : e(money($shipCost, $loc)) ?></dd></div>
      <?php $free = (int) shop_setting((int) $shop['id'], 'free_shipping_over_cents', '0'); if ($free > 0 && $cart['subtotal'] < $free): ?><div class="free-hint"><dd><?= $loc === 'el' ? 'Δωρεάν μεταφορικά από ' : 'Free shipping from ' ?><?= e(money($free, $loc)) ?></dd></div><?php endif ?>
      <div class="sum-total"><dt><?= e(t('total')) ?></dt><dd><?= e(money($cart['subtotal'] + $shipCost, $loc)) ?></dd></div></dl>
      <a class="btn btn-lg btn-block" href="<?= e(su('/checkout/')) ?>"><?= e(t('checkout')) ?> <?= icon('arrow', 18) ?></a>
      <p class="muted small"><?= icon('lock', 14) ?> <?= e(t('secure_checkout')) ?> · <?= e(t('tax_included')) ?></p>
    </aside>
  </form>
  <?php endif ?>
</div>
