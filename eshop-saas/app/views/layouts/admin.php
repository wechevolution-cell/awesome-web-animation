<?php
$u = current_user();
$shop = $shop ?? shop_by_id(admin_shop_id());
$nav = [
    ['Overview', [['dashboard', '/admin/', 'Dashboard', 'home']]],
    ['Catalogue', [['products', '/admin/products/', 'Products', 'box'], ['products', '/admin/import/', 'Import / Export', 'upload'], ['categories', '/admin/categories/', 'Categories', 'folder']]],
    ['Sales', [['orders', '/admin/orders/', 'Orders', 'receipt'], ['customers', '/admin/customers/', 'Customers', 'users'], ['customers', '/admin/messages/', 'Messages', 'chat']]],
    ['Content', [['appearance', '/admin/homepage/', 'Homepage', 'layers'], ['pages', '/admin/pages/', 'Pages', 'file'], ['pages', '/admin/menus/', 'Menus', 'list'], ['media', '/admin/media/', 'Media', 'image']]],
    ['Marketing', [['popups', '/admin/popups/', 'Popups', 'sparkle'], ['popups', '/admin/subscribers/', 'Subscribers', 'mail'], ['feeds', '/admin/feeds/', 'Feeds', 'rss'], ['seo', '/admin/seo/', 'SEO', 'globe']]],
    ['Store', [['appearance', '/admin/appearance/', 'Appearance', 'palette'], ['users', '/admin/users/', 'Users', 'users'], ['settings', '/admin/settings/', 'Settings', 'settings']]],
];
$path = request_path();
$unread = $shop ? (int) val("SELECT COUNT(*) FROM notifications WHERE shop_id = ? AND audience = 'shop' AND read_at IS NULL", [$shop['id']]) : 0;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Admin') ?> — <?= e($shop['name'] ?? 'Admin') ?></title>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= APP_VERSION ?>">
<script src="/assets/js/admin.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body class="admin" data-csrf="<?= e(csrf_token()) ?>">
<a class="skip" href="#main">Skip to content</a>
<?php if (impersonated_shop_id()): ?>
<div class="imp-bar" role="status"><?= icon('eye', 16) ?> Impersonating <strong><?= e($shop['name'] ?? '') ?></strong> as Super Admin — every action is audit-logged.
  <form method="post" action="/superadmin/impersonate/exit/"><?= csrf_field() ?><button class="btn btn-sm">Exit impersonation</button></form></div>
<?php endif ?>
<div class="shell">
  <aside class="side" id="side">
    <a class="side-brand" href="/admin/"><span class="side-logo"><?= e(mb_strtoupper(mb_substr($shop['name'] ?? 'S', 0, 1))) ?></span><span><?= e($shop['name'] ?? '') ?><small>Shop admin</small></span></a>
    <nav aria-label="Admin">
      <?php foreach ($nav as [$group, $links]): $vis = array_filter($links, static fn($l) => can($l[0])); if (!$vis) continue; ?>
      <p class="side-h"><?= e($group) ?></p>
      <ul><?php foreach ($vis as [$perm, $href, $label, $ic]): $on = $href === '/admin/' ? $path === '/admin/' : str_starts_with($path, $href); ?>
        <li><a href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= icon($ic, 18) ?> <?= e($label) ?></a></li>
      <?php endforeach ?></ul>
      <?php endforeach ?>
    </nav>
  </aside>
  <div class="main-col">
    <header class="top">
      <button class="act side-toggle" type="button" data-toggle="#side" aria-label="Menu"><?= icon('menu', 20) ?></button>
      <div class="top-title"><?= e($title ?? '') ?></div>
      <?php if ($shop): $q = shop_quota($shop); ?><a class="top-quota" href="/admin/products/?status=active" title="Active products"><?= icon('box', 16) ?> <?= $q['active'] ?>/<?= $q['limit'] ?></a><?php endif ?>
      <a class="act" href="/admin/notifications/" aria-label="Notifications"><?= icon('bell', 18) ?><?php if ($unread): ?><span class="dot"><?= $unread ?></span><?php endif ?></a>
      <?php if ($shop): ?><a class="btn btn-sm btn-ghost" href="<?= e(shop_public_url($shop)) ?>" target="_blank" rel="noopener"><?= icon('external', 16) ?> View store</a><?php endif ?>
      <form method="post" action="/admin/logout/"><?= csrf_field() ?><button class="btn btn-sm btn-link" title="<?= e($u['email'] ?? '') ?>"><?= icon('logout', 16) ?> <span class="hide-sm">Sign out</span></button></form>
    </header>
    <main id="main" class="content" tabindex="-1">
      <?php if ($shop && (int) $shop['quota_over']): $q = shop_quota($shop); if ($q['over'] > 0): ?>
      <div class="notice notice-warn"><strong>Package changed:</strong> your package allows <?= $q['limit'] ?> active products and you have <?= $q['active'] ?>. Choose which to keep by deactivating <?= $q['over'] ?> product(s). Until then the store shows only the first <?= $q['limit'] ?> (by activation date). <a href="/admin/products/?status=active">Manage active products →</a></div>
      <?php endif; endif ?>
      <?php if ($shop && $shop['status'] === 'EXPIRING'): ?><div class="notice notice-warn">Your subscription expires on <strong><?= e(fmt_date($shop['subscription_expires'])) ?></strong> (<?= days_until($shop['subscription_expires']) ?> days). Contact your provider to renew — your data is never deleted.</div><?php endif ?>
      <?php foreach (flashes() as [$type, $msg]): ?><div class="notice notice-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div><?php endforeach ?>
      <?= $content ?>
    </main>
  </div>
</div>
<?php partial('admin/partials/media-modal') ?>
</body>
</html>
