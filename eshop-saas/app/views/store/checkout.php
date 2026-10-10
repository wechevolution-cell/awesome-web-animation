<?php
$loc = $shop['locale'];
$addr = jdec($customer['address'] ?? null);
$val = static fn(string $k, string $d = '') => e(old($k, $d));
$err = static fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$firstShip = array_key_first($ship); $firstPay = array_key_first($pay);
$selShip = old('shipping_method', $firstShip); $selPay = old('payment_method', $firstPay);
?>
<div class="wrap checkout">
  <h1><?= e(t('checkout')) ?></h1>
  <form method="post" action="<?= e(su('/checkout/')) ?>" class="co-grid" novalidate data-checkout>
    <?= csrf_field() ?>
    <div class="co-main">
      <fieldset class="co-step"><legend><span>1</span> <?= e(t('contact_details')) ?></legend>
        <div class="field"><label for="co-email"><?= e(t('email')) ?> *</label><input id="co-email" type="email" name="email" required autocomplete="email" value="<?= $val('email', $customer['email'] ?? '') ?>"<?= $err('email') ?>></div>
        <div class="row2">
          <div class="field"><label for="co-name"><?= e(t('name')) ?> *</label><input id="co-name" name="name" required autocomplete="name" value="<?= $val('name', $customer['name'] ?? '') ?>"<?= $err('name') ?>></div>
          <div class="field"><label for="co-phone"><?= e(t('phone')) ?> *</label><input id="co-phone" type="tel" name="phone" required autocomplete="tel" value="<?= $val('phone', $customer['phone'] ?? '') ?>"<?= $err('phone') ?>></div>
        </div>
      </fieldset>
      <fieldset class="co-step"><legend><span>2</span> <?= e(t('shipping')) ?></legend>
        <div class="radio-cards"><?php foreach ($ship as $k => $o): ?><label class="radio-card"><input type="radio" name="shipping_method" value="<?= e($k) ?>" <?= $selShip === $k ? 'checked' : '' ?> data-ship-cost="<?= (int) $o['cost'] ?>"><span><?= e($o['label']) ?></span><strong><?= $o['cost'] ? e(money($o['cost'], $loc)) : e(t('free')) ?></strong></label><?php endforeach ?></div>
        <div class="addr" data-address>
          <div class="field"><label for="co-address"><?= e(t('address')) ?> *</label><input id="co-address" name="address" autocomplete="street-address" value="<?= $val('address', $addr['address'] ?? '') ?>"<?= $err('address') ?>></div>
          <div class="row3">
            <div class="field"><label for="co-city"><?= e(t('city')) ?> *</label><input id="co-city" name="city" autocomplete="address-level2" value="<?= $val('city', $addr['city'] ?? '') ?>"<?= $err('city') ?>></div>
            <div class="field"><label for="co-pc"><?= e(t('postcode')) ?> *</label><input id="co-pc" name="postcode" autocomplete="postal-code" inputmode="numeric" value="<?= $val('postcode', $addr['postcode'] ?? '') ?>"<?= $err('postcode') ?>></div>
            <div class="field"><label for="co-country"><?= e(t('country')) ?></label><select id="co-country" name="country" autocomplete="country"><?php foreach (['GR' => $loc === 'el' ? 'Ελλάδα' : 'Greece', 'CY' => $loc === 'el' ? 'Κύπρος' : 'Cyprus'] as $c => $n): ?><option value="<?= $c ?>" <?= old('country', $addr['country'] ?? 'GR') === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select></div>
          </div>
        </div>
      </fieldset>
      <fieldset class="co-step"><legend><span>3</span> <?= e(t('payment_method')) ?></legend>
        <div class="radio-cards"><?php foreach ($pay as $k => $o): ?><label class="radio-card"><input type="radio" name="payment_method" value="<?= e($k) ?>" <?= $selPay === $k ? 'checked' : '' ?> data-pay-fee="<?= (int) $o['fee'] ?>"><span><?= e($o['label']) ?></span><strong><?= $o['fee'] ? '+' . e(money($o['fee'], $loc)) : '' ?></strong></label><?php endforeach ?></div>
        <div class="field"><label for="co-notes"><?= e(t('notes')) ?></label><textarea id="co-notes" name="notes" rows="2"><?= $val('notes') ?></textarea></div>
      </fieldset>
    </div>
    <aside class="summary co-summary">
      <h2><?= e(t('order_summary')) ?></h2>
      <ul class="co-lines"><?php foreach ($cart['lines'] as $l): ?><li><span><?= e($l['product']['name']) ?><?php if ($l['variant']): ?> <small>(<?= e(variant_label(jdec($l['variant']['options']))) ?>)</small><?php endif ?> × <?= (int) $l['qty'] ?></span><strong><?= e(money($l['total'], $loc)) ?></strong></li><?php endforeach ?></ul>
      <dl data-totals data-subtotal="<?= (int) $cart['subtotal'] ?>" data-locale="<?= e($loc) ?>">
        <div><dt><?= e(t('subtotal')) ?></dt><dd><?= e(money($cart['subtotal'], $loc)) ?></dd></div>
        <div><dt><?= e(t('shipping')) ?></dt><dd data-ship-out><?= $ship[$selShip]['cost'] ?? 0 ? e(money($ship[$selShip]['cost'], $loc)) : e(t('free')) ?></dd></div>
        <div data-fee-row<?= ($pay[$selPay]['fee'] ?? 0) ? '' : ' hidden' ?>><dt data-fee-label><?= e($pay[$selPay]['label'] ?? '') ?></dt><dd data-fee-out><?= e(money($pay[$selPay]['fee'] ?? 0, $loc)) ?></dd></div>
        <div class="sum-total"><dt><?= e(t('total')) ?></dt><dd data-total-out><?= e(money($cart['subtotal'] + ($ship[$selShip]['cost'] ?? 0) + ($pay[$selPay]['fee'] ?? 0), $loc)) ?></dd></div>
      </dl>
      <label class="check<?= isset($errors['terms']) ? ' has-error' : '' ?>"><input type="checkbox" name="terms" value="1" required> <span><?= e(t('accept_terms')) ?> (<a href="<?= e(su('/terms/')) ?>" target="_blank"><?= $loc === 'el' ? 'Όροι' : 'Terms' ?></a>, <a href="<?= e(su('/privacy/')) ?>" target="_blank"><?= $loc === 'el' ? 'Απόρρητο' : 'Privacy' ?></a>)</span></label>
      <button class="btn btn-lg btn-block" type="submit"><?= icon('lock', 18) ?> <?= e(t('place_order')) ?></button>
      <p class="muted small"><?= e(t('tax_included')) ?></p>
    </aside>
  </form>
</div>
