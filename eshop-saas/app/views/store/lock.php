<?php
// Standalone, data-free lock page. No storefront data, no admin forms, no endpoints exposed.
$sid = (int) $shop['id'];
$logo = media_by_id((int) shop_setting($sid, 'logo_id', 0), $sid);
$tok = template_get($shop['template_key'])['tokens'];
?><!doctype html>
<html lang="<?= e($shop['locale']) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($shop['name']) ?> — <?= e(t('lock_title')) ?></title>
<link rel="stylesheet" href="/assets/css/lock.css?v=<?= APP_VERSION ?>">
<style>:root{--bg:<?= e($tok['background']) ?>;--fg:<?= e($tok['text']) ?>;--ac:<?= e($tok['accent']) ?>;--mu:<?= e($tok['muted']) ?>}</style>
</head>
<body>
<main class="lock">
  <div class="lock-mark" aria-hidden="true"><span></span><span></span><span></span></div>
  <?php if ($logo): ?><img class="lock-logo" src="<?= e(media_url($logo, 400)) ?>" alt="<?= e($shop['name']) ?>" width="160" height="60"><?php else: ?><p class="lock-name"><?= e($shop['name']) ?></p><?php endif ?>
  <h1><?= e(t('lock_title')) ?></h1>
  <p><?= e(t('lock_text')) ?></p>
  <?php if ($shop['locale'] === 'el'): ?><p class="lock-alt" lang="en">This online store is temporarily unavailable. We will be back with you shortly.</p><?php endif ?>
</main>
</body>
</html>
