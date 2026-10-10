<?php
$sid = (int) $shop['id'];
$logo = media_by_id((int) shop_setting($sid, 'logo_id', 0), $sid);
$items = menu_items($sid, 'header');
$cats = rows('SELECT id, name, path, parent_id FROM categories WHERE shop_id = ? AND depth <= 1 ORDER BY depth, position, name LIMIT 60', [$sid]);
$topCats = array_values(array_filter($cats, static fn($c) => !$c['parent_id']));
$v = $tpl['header'];
$brand = '<a class="brand" href="' . e(su('/')) . '" aria-label="' . e($shop['name']) . ' — ' . e(t('home')) . '">' . ($logo ? img_tag($logo, $shop['name'], '200px', ['loading' => 'eager', 'class' => 'brand-logo']) : '<span class="brand-text">' . e($shop['name']) . '</span>') . '</a>';
$nav = static function (string $cls = 'nav') use ($items, $shop, $topCats, $cats, $v): string {
    $h = '<ul class="' . $cls . '">';
    $hasProductsItem = false;
    foreach ($items as $it) {
        $href = menu_href($shop, $it, store()['base']);
        if ($href === null) continue;
        if ($it['type'] === 'products') $hasProductsItem = true;
        $kids = '';
        if ($it['children']) { foreach ($it['children'] as $c) { $ch = menu_href($shop, $c, store()['base']); if ($ch) $kids .= '<li><a href="' . e($ch) . '">' . e($c['label']) . '</a></li>'; } }
        if ($it['type'] === 'products' && $v === 'mega' && $topCats) {
            $kids = '';
            foreach ($topCats as $tc) {
                $subs = array_filter($cats, static fn($c) => (int) $c['parent_id'] === (int) $tc['id']);
                $kids .= '<li class="mega-col"><a class="mega-h" href="' . e(su('/' . $tc['path'] . '/')) . '">' . e($tc['name']) . '</a>' . ($subs ? '<ul>' . implode('', array_map(static fn($s) => '<li><a href="' . e(su('/' . $s['path'] . '/')) . '">' . e($s['name']) . '</a></li>', $subs)) . '</ul>' : '') . '</li>';
            }
        }
        $h .= '<li' . ($kids ? ' class="has-sub"' : '') . '><a href="' . e($href) . '">' . e($it['label']) . '</a>' . ($kids ? '<ul class="sub' . ($v === 'mega' && $it['type'] === 'products' ? ' mega' : '') . '">' . $kids . '</ul>' : '') . '</li>';
    }
    if (!$hasProductsItem && !$items) $h .= '<li><a href="' . e(su('/products/')) . '">' . e(t('all_products')) . '</a></li>';
    return $h . '</ul>';
};
$search = '<form class="search" action="' . e(su('/search/')) . '" method="get" role="search"><label class="sr" for="q-' . $v . '">' . e(t('search')) . '</label><input id="q-' . $v . '" type="search" name="q" value="' . e(get('q')) . '" placeholder="' . e(t('search_placeholder')) . '" autocomplete="off"><button type="submit" aria-label="' . e(t('search')) . '">' . icon('search') . '</button></form>';
$actions = '<div class="actions">'
    . ($v !== 'search' ? '<a class="act act-search" href="' . e(su('/search/')) . '" aria-label="' . e(t('search')) . '">' . icon('search', 20) . '</a>' : '')
    . '<a class="act" href="' . e(su('/account/')) . '" aria-label="' . e(t('account')) . '">' . icon('user', 20) . '</a>'
    . '<a class="act" href="' . e(su('/wishlist/')) . '" aria-label="' . e(t('wishlist')) . '">' . icon('heart', 20) . '</a>'
    . '<a class="act act-cart" href="' . e(su('/cart/')) . '" data-drawer-open aria-label="' . e(t('cart')) . '">' . icon('cart', 20) . '<span class="count" data-cart-count' . ($cartCount ? '' : ' hidden') . '>' . (int) $cartCount . '</span></a>'
    . '<button class="act act-menu" type="button" data-menu-toggle aria-expanded="false" aria-controls="mnav" aria-label="' . e(t('menu')) . '">' . icon('menu', 22) . '</button>'
    . '</div>';
$announce = trim((string) shop_setting($sid, 'announcement', ''));
?>
<?php if ($announce !== ''): ?><div class="announce"><?= e($announce) ?></div><?php endif ?>
<header class="hdr hdr--<?= e($v) ?>">
<?php if ($v === 'centered'): ?>
  <div class="wrap hdr-top"><div class="hdr-side"><?= $search ?></div><?= $brand ?><?= $actions ?></div>
  <nav class="wrap hdr-nav" aria-label="Main"><?= $nav() ?></nav>
<?php elseif ($v === 'search'): ?>
  <div class="wrap hdr-top"><?= $brand ?><?= $search ?><?= $actions ?></div>
  <nav class="hdr-nav" aria-label="Main"><div class="wrap"><?= $nav() ?></div></nav>
<?php elseif ($v === 'minimal'): ?>
  <div class="wrap hdr-top"><button class="menu-word" type="button" data-menu-toggle aria-expanded="false" aria-controls="mnav"><?= e(t('menu')) ?></button><?= $brand ?><?= $actions ?></div>
<?php else: ?>
  <div class="wrap hdr-top"><?= $brand ?><nav class="hdr-nav" aria-label="Main"><?= $nav() ?></nav><?= $actions ?></div>
<?php endif ?>
</header>
<dialog class="mnav" id="mnav" closedby="any" aria-label="<?= e(t('menu')) ?>">
  <div class="mnav-in">
    <div class="mnav-head"><span class="brand-text"><?= e($shop['name']) ?></span><button class="act" type="button" data-dialog-close aria-label="<?= e(t('close')) ?>"><?= icon('close', 22) ?></button></div>
    <?= $search ?>
    <nav aria-label="Mobile"><?= $nav('mnav-list') ?>
      <?php if ($topCats): ?><p class="mnav-h"><?= e(t('categories')) ?></p><ul class="mnav-list"><?php foreach ($topCats as $c): ?><li><a href="<?= e(su('/' . $c['path'] . '/')) ?>"><?= e($c['name']) ?></a></li><?php endforeach ?></ul><?php endif ?>
    </nav>
  </div>
</dialog>
