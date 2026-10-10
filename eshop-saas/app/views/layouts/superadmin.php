<?php
$u = current_user();
$nav = [['/superadmin/', 'Overview', 'home'], ['/superadmin/shops/', 'Shops', 'store'], ['/superadmin/plans/', 'Packages', 'tag'], ['/superadmin/templates/', 'Templates', 'layers'], ['/superadmin/domains/', 'Domains', 'globe'], ['/superadmin/payments/', 'Payments', 'receipt'], ['/superadmin/notifications/', 'Notifications', 'bell'], ['/superadmin/audit/', 'Audit log', 'shield'], ['/superadmin/messages/', 'Messages', 'chat'], ['/superadmin/users/', 'System users', 'users'], ['/superadmin/backups/', 'Backups', 'download'], ['/superadmin/settings/', 'Settings', 'settings']];
$path = request_path();
$unread = (int) val("SELECT COUNT(*) FROM notifications WHERE audience = 'super' AND read_at IS NULL");
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Super Admin') ?> — <?= e(setting('platform_name', 'Platform')) ?></title>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= APP_VERSION ?>">
<script src="/assets/js/admin.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body class="admin is-super" data-csrf="<?= e(csrf_token()) ?>">
<a class="skip" href="#main">Skip to content</a>
<div class="shell">
  <aside class="side" id="side">
    <a class="side-brand" href="/superadmin/"><span class="side-logo"><?= icon('bolt', 18) ?></span><span><?= e(setting('platform_name', 'Platform')) ?><small>Super Admin</small></span></a>
    <nav aria-label="Super admin"><ul>
      <?php foreach ($nav as [$href, $label, $ic]): $on = $href === '/superadmin/' ? $path === $href : str_starts_with($path, $href); ?>
      <li><a href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= icon($ic, 18) ?> <?= e($label) ?><?php if ($href === '/superadmin/notifications/' && $unread): ?> <span class="pill"><?= $unread ?></span><?php endif ?></a></li>
      <?php endforeach ?>
    </ul></nav>
    <form method="post" action="/superadmin/cron/" class="side-cron"><?= csrf_field() ?><button class="btn btn-xs btn-ghost" type="submit"><?= icon('clock', 14) ?> Run checks now</button><span class="small muted">Last cron: <?= e(fmt_date(setting('cron_last_run'), true)) ?></span></form>
  </aside>
  <div class="main-col">
    <header class="top">
      <button class="act side-toggle" type="button" data-toggle="#side" aria-label="Menu"><?= icon('menu', 20) ?></button>
      <div class="top-title"><?= e($title ?? '') ?></div>
      <a class="btn btn-sm" href="/superadmin/shops/new/"><?= icon('plus', 16) ?> New shop</a>
      <a class="act" href="/" target="_blank" rel="noopener" aria-label="Platform site"><?= icon('external', 18) ?></a>
      <form method="post" action="/superadmin/logout/"><?= csrf_field() ?><button class="btn btn-sm btn-link" title="<?= e($u['email']) ?>"><?= icon('logout', 16) ?> <span class="hide-sm">Sign out</span></button></form>
    </header>
    <main id="main" class="content" tabindex="-1">
      <?php foreach (flashes() as [$type, $msg]): ?><div class="notice notice-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div><?php endforeach ?>
      <?= $content ?>
    </main>
  </div>
</div>
</body>
</html>
