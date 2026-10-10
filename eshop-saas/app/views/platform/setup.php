<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Setup</title><link rel="stylesheet" href="/assets/css/admin.css?v=<?= APP_VERSION ?>"></head>
<body class="auth-body is-super">
<main class="auth-card auth-wide" id="main">
  <p class="eyebrow">First-run setup</p>
  <h1>Create the Super Admin</h1>
  <p class="muted">There are no default credentials. The account you create here owns the platform.</p>
  <ul class="setup-checks"><?php foreach ($checks as [$l, $ok, $note]): ?><li class="<?= $ok ? 'ok' : ($note === 'optional' ? 'warn' : 'bad') ?>"><?= icon($ok ? 'check' : 'close', 14) ?> <?= e($l) ?> <?= $note && $note !== 'optional' ? '<span class="muted">' . e($note) . '</span>' : '' ?></li><?php endforeach ?></ul>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label for="pn">Platform name</label><input id="pn" name="platform_name" value="<?= e(post('platform_name', 'ShopRent')) ?>" required></div>
    <div class="field"><label for="nm">Your name</label><input id="nm" name="name" value="<?= e(post('name')) ?>" autocomplete="name"></div>
    <div class="field"><label for="em">Email</label><input id="em" type="email" name="email" value="<?= e(post('email')) ?>" required autocomplete="username"<?= isset($errors['email']) ? ' aria-invalid="true"' : '' ?>><?php if (isset($errors['email'])): ?><p class="f-error"><?= e($errors['email']) ?></p><?php endif ?></div>
    <div class="field"><label for="pw">Password</label><input id="pw" type="password" name="password" required minlength="10" autocomplete="new-password"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>><p class="f-help">At least 10 characters with letters and numbers.</p><?php if (isset($errors['password'])): ?><p class="f-error"><?= e($errors['password']) ?></p><?php endif ?></div>
    <div class="field"><label for="pw2">Repeat password</label><input id="pw2" type="password" name="password2" required minlength="10" autocomplete="new-password"><?php if (isset($errors['password2'])): ?><p class="f-error"><?= e($errors['password2']) ?></p><?php endif ?></div>
    <label class="check"><input type="checkbox" name="demo" value="1" checked> <span>Create 10 demo shops (one per template, noindex) for previews</span></label>
    <button class="btn btn-block" type="submit">Complete setup</button>
  </form>
</main>
</body>
</html>
