<?php
/** Hero section — 8 structural compositions chosen by the template manifest. */
$sid = (int) $shop['id'];
$v = store()['tpl']['hero'];
$img = media_by_id(isset($d['image_id']) ? (int) $d['image_id'] : null, $sid);
$img2 = media_by_id(isset($d['image2_id']) ? (int) $d['image2_id'] : null, $sid);
$title = $d['title'] ?? $shop['name'];
$sub = $d['subtitle'] ?? '';
$eyebrow = $d['eyebrow'] ?? '';
$ctaText = $d['cta_text'] ?? t('view_all');
$link = (string) ($d['cta_link'] ?? 'products');
$ctaHref = preg_match('#^https?://#', $link) ? $link : su('/' . trim($link, '/') . ($link === '' ? '' : '/'));
[$w, $pp] = visible_where($shop);
$showcase = [];
if (true) $showcase = products_attach_images(rows("SELECT p.* FROM products p WHERE $w ORDER BY p.featured DESC, p.activated_at DESC LIMIT 4", $pp));
$visual = static function (?array $m, ?array $p, string $sizes = '50vw', string $loading = 'eager') {
    if ($m) return img_tag($m, '', $sizes, ['loading' => $loading]);
    if ($p && $p['image']) return img_tag($p['image'], $p['name'], $sizes, ['loading' => $loading]);
    if ($p) return '<img src="' . e(su('/img/ph/' . $p['slug'] . '.svg') . '?l=' . rawurlencode(mb_substr($p['name'], 0, 1))) . '" alt="" width="800" height="800" loading="' . $loading . '">';
    return '<div class="hero-ph" aria-hidden="true"></div>';
};
$cta = '<a class="btn btn-lg" href="' . e($ctaHref) . '">' . e($ctaText) . ' ' . icon('arrow', 18) . '</a>';
?>
<section class="hero hero--<?= e($v) ?>" aria-labelledby="hero-title">
<?php if ($v === 'fullbleed'): ?>
  <div class="hero-bg"><?= $visual($img, $showcase[0] ?? null, '100vw') ?></div>
  <div class="wrap hero-inner">
    <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
    <h1 id="hero-title"><?= e($title) ?></h1>
    <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
    <?= $cta ?>
  </div>
<?php elseif ($v === 'editorial'): ?>
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
      <h1 id="hero-title"><?= e($title) ?></h1>
      <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
      <?= $cta ?>
    </div>
    <figure class="hero-fig hero-fig-a"><?= $visual($img, $showcase[0] ?? null, '40vw') ?></figure>
    <figure class="hero-fig hero-fig-b"><?= $visual($img2, $showcase[1] ?? ($showcase[0] ?? null), '25vw', 'lazy') ?></figure>
  </div>
<?php elseif ($v === 'bento'): ?>
  <div class="wrap bento">
    <div class="bento-main">
      <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
      <h1 id="hero-title"><?= e($title) ?></h1>
      <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
      <?= $cta ?>
      <div class="bento-visual"><?= $visual($img, $showcase[0] ?? null, '40vw') ?></div>
    </div>
    <?php foreach (array_slice($showcase, $img ? 0 : 1, 3) as $i => $p): ?>
    <a class="bento-tile bento-<?= $i ?>" href="<?= e(su('/product/' . $p['slug'] . '/')) ?>">
      <span class="bento-name"><?= e($p['name']) ?></span><span class="bento-price"><?= e(money(product_effective_price($p), $shop['locale'])) ?></span>
      <span class="bento-img"><?= $visual(null, $p, '20vw', 'lazy') ?></span>
    </a>
    <?php endforeach ?>
  </div>
<?php elseif ($v === 'type'): ?>
  <div class="wrap hero-type">
    <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
    <h1 id="hero-title" class="display"><?= e($title) ?></h1>
    <div class="hero-type-row">
      <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
      <?= $cta ?>
    </div>
    <div class="hero-strip">
      <figure class="hero-strip-a"><?= $visual($img, $showcase[0] ?? null, '60vw') ?></figure>
      <?php foreach (array_slice($showcase, 1, 2) as $p): ?><figure><?= $visual(null, $p, '20vw', 'lazy') ?></figure><?php endforeach ?>
    </div>
  </div>
<?php elseif ($v === 'banners'): ?>
  <div class="wrap banners">
    <div class="banner-main">
      <div class="banner-copy">
        <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
        <h1 id="hero-title"><?= e($title) ?></h1>
        <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
        <?= $cta ?>
      </div>
      <div class="banner-visual"><?= $visual($img, $showcase[0] ?? null, '35vw') ?></div>
    </div>
    <div class="banner-side">
      <?php foreach (array_slice($showcase, 1, 2) as $p): ?>
      <a class="banner-mini" href="<?= e(su('/product/' . $p['slug'] . '/')) ?>"><span><strong><?= e($p['name']) ?></strong><em><?= e(money(product_effective_price($p), $shop['locale'])) ?></em></span><?= $visual(null, $p, '15vw', 'lazy') ?></a>
      <?php endforeach ?>
    </div>
  </div>
<?php elseif ($v === 'architect'): ?>
  <div class="wrap arch">
    <div class="arch-copy">
      <p class="arch-index">01</p>
      <h1 id="hero-title"><?= e($title) ?></h1>
      <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
      <?= $cta ?>
    </div>
    <figure class="arch-fig"><?= $visual($img, $showcase[0] ?? null, '60vw') ?></figure>
    <ul class="arch-index-list">
      <?php foreach (array_slice($showcase, 0, 3) as $i => $p): ?><li><span>0<?= $i + 2 ?></span><a href="<?= e(su('/product/' . $p['slug'] . '/')) ?>"><?= e($p['name']) ?></a></li><?php endforeach ?>
    </ul>
  </div>
<?php elseif ($v === 'brutal'): ?>
  <div class="wrap brutal">
    <div class="brutal-copy">
      <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
      <h1 id="hero-title"><?= e($title) ?></h1>
      <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
      <?= $cta ?>
    </div>
    <div class="brutal-visual"><?= $visual($img, $showcase[0] ?? null, '45vw') ?><span class="brutal-sticker"><?= e(t('new')) ?></span></div>
  </div>
  <div class="marquee" aria-hidden="true"><div class="marquee-track"><?php for ($i = 0; $i < 8; $i++): ?><span><?= e($shop['name']) ?> ✦ <?= e($ctaText) ?> ✦ </span><?php endfor ?></div></div>
<?php else: /* split */ ?>
  <div class="wrap split">
    <div class="split-copy">
      <?php if ($eyebrow): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
      <h1 id="hero-title"><?= e($title) ?></h1>
      <?php if ($sub): ?><p class="hero-sub"><?= e($sub) ?></p><?php endif ?>
      <?= $cta ?>
    </div>
    <figure class="split-fig"><?= $visual($img, $showcase[0] ?? null, '50vw') ?></figure>
  </div>
<?php endif ?>
</section>
