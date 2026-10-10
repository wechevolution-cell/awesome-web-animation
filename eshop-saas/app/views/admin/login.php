<?php $isSuper = ($area ?? '') === 'super'; ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= $isSuper ? 'Super Admin' : 'Shop admin' ?> — <?= e(setting('platform_name', 'Platform')) ?></title>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= APP_VERSION ?>">
</head>
<body class="auth-body<?= $isSuper ? ' is-super' : '' ?>">
<main class="auth-card" id="main">
  <p class="eyebrow"><?= e(setting('platform_name', 'Platform')) ?> · <?= $isSuper ? 'Super Admin' : 'Shop admin' ?></p>
  <?php foreach (flashes() as [$type, $msg]): ?><div class="notice notice-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div><?php endforeach ?>
  <?php if (in_array($area, ['admin', 'super'], true)): ?>
    <h1>Sign in</h1>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="username" autofocus value="<?= e(old('email')) ?>"></div>
      <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-block" type="submit">Sign in</button>
    </form>
    <?php if (!$isSuper): ?><p class="small"><a href="/admin/forgot-password/">Forgot your password?</a></p><?php endif ?>
    <p class="muted small">Accounts lock for 15 minutes after 5 failed attempts.</p>
  <?php elseif ($area === 'forgot'): ?>
    <h1>Reset password</h1>
    <form method="post" class="stack"><?= csrf_field() ?>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="email"></div>
      <button class="btn btn-block" type="submit">Send reset link</button>
    </form>
    <p class="small"><a href="/admin/login/">Back to sign in</a></p>
  <?php else: ?>
    <h1>Set your password</h1>
    <p class="muted"><?= e($email) ?></p>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field"><label for="password">New password</label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password" aria-describedby="pwh"><p class="f-help" id="pwh">At least 10 characters with letters and numbers.</p></div>
      <div class="field"><label for="password2">Repeat password</label><input id="password2" type="password" name="password2" required minlength="10" autocomplete="new-password"></div>
      <button class="btn btn-block" type="submit">Save password</button>
    </form>
  <?php endif ?>
</main>
</body>
</html>
