<dialog class="drawer" id="drawer" closedby="any" aria-labelledby="drawer-title">
  <div class="drawer-panel">
    <div class="drawer-head"><h2 id="drawer-title"><?= e(t('cart')) ?></h2><button class="act" type="button" data-dialog-close aria-label="<?= e(t('close')) ?>"><?= icon('close', 22) ?></button></div>
    <div class="drawer-body" data-drawer-body><p class="muted"><?= e(t('empty_cart')) ?></p></div>
    <div class="drawer-foot">
      <p class="drawer-sub"><span><?= e(t('subtotal')) ?></span><strong data-drawer-subtotal></strong></p>
      <a class="btn btn-block" href="<?= e(su('/checkout/')) ?>"><?= e(t('checkout')) ?></a>
      <a class="btn btn-ghost btn-block" href="<?= e(su('/cart/')) ?>"><?= e(t('view_cart')) ?></a>
    </div>
  </div>
</dialog>
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
