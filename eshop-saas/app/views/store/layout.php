<?php
/** Storefront layout shared by all 10 templates. Variants come from the template manifest. */
$S = store(); $tpl = $S['tpl']; $sid = (int) $shop['id'];
$favicon = media_by_id((int) shop_setting($sid, 'favicon_id', 0), $sid);
$ga = (string) ($S['seo']['ga4_id'] ?? '');
$popup = popup_active($sid);
$cartCount = array_sum(cart_raw($sid));
?><!doctype html>
<html lang="<?= e($shop['locale']) ?>" class="tpl-<?= e($tpl['key']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= seo_head($meta) ?>
<meta name="theme-color" content="<?= e(shop_tokens($shop, $tpl['key'])['background']) ?>">
<?php if ($favicon): ?><link rel="icon" href="<?= e(media_url($favicon, 400)) ?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="' . shop_tokens($shop, $tpl['key'])['primary'] . '"/></svg>') ?>"><?php endif ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($shop['name']) ?>" href="<?= e(su('/feed/rss.xml')) ?>">
<link rel="stylesheet" href="/assets/css/store.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="/assets/templates/<?= e($tpl['key']) ?>.css?v=<?= APP_VERSION ?>">
<style><?= tokens_css($shop, $tpl['key']) ?></style>
<?= jsonld($graph) ?>
<script src="/assets/js/store.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body class="<?= e(trim('hdr-' . $tpl['header'] . ' card-' . $tpl['card'] . ' ' . $bodyClass)) ?>" data-base="<?= e($S['base']) ?>" data-csrf="<?= e(csrf_token()) ?>" data-ga="<?= e($ga) ?>" data-locale="<?= e($shop['locale']) ?>">
<a class="skip" href="#main"><?= e(t('skip')) ?></a>
<?php if ($S['preview_tpl'] || $S['preview_locked']): ?>
<div class="preview-bar" role="status">
  <?php if ($S['preview_locked']): ?><strong>Super Admin preview:</strong> this shop is <?= e($shop['status']) ?> — visitors see the lock page.<?php endif ?>
  <?php if ($S['preview_tpl']): ?><strong>Template preview:</strong> <?= e($tpl['name']) ?> (visitors still see <?= e(template_get($shop['template_key'])['name']) ?>). <a href="?preview_template=off">Exit preview</a><?php endif ?>
</div>
<?php endif ?>
<?php partial('store/partials/header', ['shop' => $shop, 'tpl' => $tpl, 'cartCount' => $cartCount]) ?>
<main id="main" tabindex="-1">
<?php foreach (flashes() as [$type, $msg]): ?><div class="wrap"><div class="notice notice-<?= e($type) ?>" role="status"><?= e($msg) ?></div></div><?php endforeach ?>
<?= $content ?>
</main>
<?php partial('store/partials/footer', ['shop' => $shop, 'tpl' => $tpl]) ?>
<?php partial('store/partials/drawer', ['shop' => $shop]) ?>
<?php if ($popup) partial('store/partials/popup', ['shop' => $shop, 'popup' => $popup]) ?>
<?php if ($ga !== ''): ?>
<div class="cookie" id="cookie" hidden role="dialog" aria-live="polite" aria-label="Cookies">
  <p><?= e(t('cookie_text')) ?> <a href="<?= e(su('/cookies/')) ?>">Cookies</a></p>
  <div><button class="btn btn-sm" data-consent="yes"><?= e(t('accept')) ?></button> <button class="btn btn-sm btn-ghost" data-consent="no"><?= e(t('decline')) ?></button></div>
</div>
<?php endif ?>
</body>
</html>
