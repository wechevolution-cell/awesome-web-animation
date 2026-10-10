<?php $admin = is_super(); ?><!doctype html>
<html lang="<?= e($GLOBALS['LOCALE'] ?? 'el') ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e(setting('platform_name', '')) ?></title>
<style>
html,body{height:100%;margin:0}body{background:#fff;color:#141414;font:400 1rem/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.hint{position:fixed;left:50%;bottom:1.5rem;transform:translateX(-50%);max-width:calc(100% - 2rem);background:#141414;color:#fff;border-radius:12px;padding:.85rem 1.1rem;font-size:.9rem;box-shadow:0 20px 40px -20px rgba(0,0,0,.5)}
.hint a{color:#c8f560;font-weight:600}
</style>
</head>
<body>
<main id="main" aria-label="<?= e(setting('platform_name', 'Site')) ?>"></main>
<?php if ($admin): ?>
<p class="hint" role="status">The main website is blank. <a href="/superadmin/#main-site">Activate a storefront</a> (choose a shop and one of the 10 templates). Only you see this note.</p>
<?php endif ?>
</body>
</html>
