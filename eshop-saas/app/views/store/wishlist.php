<div class="wrap">
  <h1><?= e(t('wishlist')) ?></h1>
  <?php if ($products): ?><div class="pgrid cols-4"><?php foreach ($products as $p) partial('store/partials/card', ['p' => $p]); ?></div>
  <?php else: ?><div class="empty"><p><?= e(t('empty_wishlist')) ?></p><a class="btn" href="<?= e(su('/products/')) ?>"><?= e(t('continue_shopping')) ?></a></div><?php endif ?>
</div>
