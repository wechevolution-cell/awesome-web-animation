<?php $name = (string) setting('platform_name', 'Platform'); $ga = (string) setting('ga4_id', ''); $page = $page ?? ''; ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?= seo_head($meta + ['site_name' => $name, 'locale' => 'en', 'image' => abs_url('/assets/img/og-platform.webp')]) ?>
<meta name="theme-color" content="#f4f2ec">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#141414"/><path d="M9 21V11h6a4 4 0 0 1 0 8h-6" stroke="#c8f560" stroke-width="3" fill="none"/></svg>') ?>">
<link rel="stylesheet" href="/assets/css/platform.css?v=<?= APP_VERSION ?>">
<?= jsonld($graph) ?>
<script src="/assets/js/platform.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body data-ga="<?= e($ga) ?>" class="pg-<?= e($page) ?>">
<a class="skip" href="#main">Skip to content</a>
<header class="nav" data-nav>
  <div class="wrap nav-in">
    <a class="logo" href="/"><span class="logo-mark" aria-hidden="true"></span><?= e($name) ?></a>
    <nav aria-label="Main"><ul class="nav-links" id="nav-links">
      <li><a href="/templates/"<?= $page === 'templates' ? ' aria-current="page"' : '' ?>>Templates</a></li>
      <li><a href="/pricing/"<?= $page === 'pricing' ? ' aria-current="page"' : '' ?>>Pricing</a></li>
      <li><a href="/#how">How it works</a></li>
      <li><a href="/#faq">FAQ</a></li>
      <li class="nav-sep"><a href="/admin/">Shop login</a></li>
    </ul></nav>
    <a class="btn btn-sm" href="/contact/">Get your store</a>
    <button class="nav-burger" type="button" data-burger aria-controls="nav-links" aria-expanded="false" aria-label="Menu"><span></span><span></span></button>
  </div>
</header>
<main id="main" tabindex="-1">
<?php foreach (flashes() as [$type, $msg]): ?><div class="wrap"><div class="notice notice-<?= e($type) ?>" role="status"><?= e($msg) ?></div></div><?php endforeach ?>
<?= $content ?>
</main>
<footer class="foot">
  <div class="wrap foot-grid">
    <div><a class="logo" href="/"><span class="logo-mark" aria-hidden="true"></span><?= e($name) ?></a><p class="muted">Rented e-shops on your own domain.</p>
      <?php if ($c = setting('company_name')): ?><p class="small muted"><?= e($c) ?><?= ($a = setting('company_address')) ? ' · ' . e($a) : '' ?></p><?php endif ?></div>
    <nav aria-label="Product"><p class="foot-h">Product</p><ul><li><a href="/templates/">Templates</a></li><li><a href="/pricing/">Pricing</a></li><li><a href="/#features">Features</a></li></ul></nav>
    <nav aria-label="Company"><p class="foot-h">Company</p><ul><li><a href="/contact/">Contact</a></li><li><a href="/admin/">Shop login</a></li></ul></nav>
    <nav aria-label="Legal"><p class="foot-h">Legal</p><ul><li><a href="/privacy/">Privacy</a></li><li><a href="/terms/">Terms</a></li><li><a href="/cookies/">Cookies</a></li></ul></nav>
  </div>
  <div class="wrap foot-base small muted">© <?= date('Y') ?> <?= e(setting('company_name') ?: $name) ?></div>
</footer>
<?php if ($ga !== ''): ?><div class="cookie" id="cookie" hidden role="dialog" aria-label="Cookies"><p>We use essential cookies and, with your consent, analytics cookies. <a href="/cookies/">Cookie policy</a></p><div><button class="btn btn-sm" data-consent="yes">Accept</button> <button class="btn btn-sm btn-ghost" data-consent="no">Decline</button></div></div><?php endif ?>
</body>
</html>
