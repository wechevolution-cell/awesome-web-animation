<?php
$tpl = store()['tpl'];
$loc = $shop['locale'];
$heading = $cat ? $cat['name'] : ($isSearch ? ($q !== '' ? t('results_for', ['q' => $q]) : t('search')) : t('all_products'));
$sidebar = $tpl['listing'] === 'sidebar';
$catMedia = $cat ? media_by_id($cat['image_id'] ? (int) $cat['image_id'] : null, (int) $shop['id']) : null;
?>
<div class="wrap listing listing--<?= e($tpl['listing']) ?>">
  <?php partial('store/partials/crumbs', ['crumbs' => $crumbs]) ?>
  <header class="list-head<?= $catMedia ? ' has-media' : '' ?>">
    <div>
      <h1><?= e($heading) ?></h1>
      <p class="muted" data-count><?= e(t('products_count', ['n' => $total])) ?></p>
      <?php if ($cat && $cat['description'] && $pg['page'] === 1): ?><div class="prose cat-intro"><?= $cat['description'] ?></div><?php endif ?>
    </div>
    <?php if ($catMedia): ?><figure class="list-media"><?= img_tag($catMedia, '', '40vw', ['loading' => 'eager']) ?></figure><?php endif ?>
  </header>
  <?php if ($subcats): ?>
  <nav class="chips" aria-label="<?= e(t('categories')) ?>"><ul><?php foreach ($subcats as $c): ?><li><a href="<?= e(su('/' . $c['path'] . '/')) ?>"><?= e($c['name']) ?></a></li><?php endforeach ?></ul></nav>
  <?php endif ?>
  <div class="list-body">
    <aside class="filters" id="filters" aria-label="<?= e(t('filters')) ?>">
      <form method="get" action="<?= e(su($relPath)) ?>" class="filter-form">
        <?php if ($isSearch): ?><div class="field"><label for="f-q"><?= e(t('search')) ?></label><input id="f-q" type="search" name="q" value="<?= e($q) ?>"></div><?php endif ?>
        <fieldset><legend><?= e(t('price')) ?> (€)</legend>
          <div class="row2"><label><span class="sr"><?= e(t('min')) ?></span><input type="number" min="0" step="1" inputmode="decimal" name="min" value="<?= e(get('min')) ?>" placeholder="<?= e(t('min')) ?>"></label>
          <label><span class="sr"><?= e(t('max')) ?></span><input type="number" min="0" step="1" inputmode="decimal" name="max" value="<?= e(get('max')) ?>" placeholder="<?= e(t('max')) ?>"></label></div>
        </fieldset>
        <label class="check"><input type="checkbox" name="stock" value="1" <?= get('stock') === '1' ? 'checked' : '' ?>> <?= e(t('only_in_stock')) ?></label>
        <label class="check"><input type="checkbox" name="sale" value="1" <?= get('sale') === '1' ? 'checked' : '' ?>> <?= e(t('sale')) ?></label>
        <?php if ($brandList): ?><fieldset><legend><?= e(t('brand')) ?></legend><?php foreach ($brandList as $b): ?><label class="check"><input type="checkbox" name="brand[]" value="<?= e($b) ?>" <?= in_array($b, $brands, true) ? 'checked' : '' ?>> <?= e($b) ?></label><?php endforeach ?></fieldset><?php endif ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <div class="filter-actions"><button class="btn btn-sm" type="submit"><?= e(t('apply')) ?></button> <a class="btn btn-sm btn-ghost" href="<?= e(su($relPath) . ($isSearch && $q !== '' ? '?q=' . rawurlencode($q) : '')) ?>"><?= e(t('clear')) ?></a></div>
      </form>
    </aside>
    <div class="list-main">
      <div class="toolbar">
        <button class="btn btn-sm btn-ghost filter-toggle" type="button" data-toggle="#filters" aria-expanded="false" aria-controls="filters"><?= icon('filter', 16) ?> <?= e(t('filters')) ?></button>
        <form method="get" class="sort-form" data-autosubmit>
          <?php foreach (['q', 'min', 'max', 'stock', 'sale'] as $k) if (get($k) !== '') echo '<input type="hidden" name="' . $k . '" value="' . e(get($k)) . '">'; foreach ($brands as $b) echo '<input type="hidden" name="brand[]" value="' . e($b) . '">'; ?>
          <label for="sort"><?= e(t('sort')) ?></label>
          <select id="sort" name="sort"><?php foreach (['newest' => t('sort_newest'), 'price_asc' => t('sort_price_asc'), 'price_desc' => t('sort_price_desc'), 'name' => t('sort_name')] as $k => $l): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
          <noscript><button class="btn btn-sm" type="submit"><?= e(t('apply')) ?></button></noscript>
        </form>
        <div class="view-toggle" role="group" aria-label="View"><button type="button" class="act is-on" data-view="grid" aria-pressed="true" aria-label="Grid"><?= icon('grid', 18) ?></button><button type="button" class="act" data-view="list" aria-pressed="false" aria-label="List"><?= icon('list', 18) ?></button></div>
      </div>
      <?php if ($products): ?>
      <div class="pgrid cols-<?= (int) ($sidebar ? max(2, $tpl['cols'] - 1) : $tpl['cols']) ?>" data-grid>
        <?php foreach ($products as $p) partial('store/partials/card', ['p' => $p]); ?>
      </div>
      <?= pager_html($pg, su($relPath)) ?>
      <?php else: ?>
      <div class="empty"><p><?= e(t('no_products')) ?></p><a class="btn" href="<?= e(su('/products/')) ?>"><?= e(t('all_products')) ?></a></div>
      <?php endif ?>
    </div>
  </div>
  <?php partial('store/partials/faq', ['faq' => $faq]) ?>
</div>
