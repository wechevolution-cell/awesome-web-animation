<div class="wrap narrow auth">
<?php if ($mode === 'login'): ?>
  <h1><?= e(t('login')) ?></h1>
  <form method="post" action="<?= e(su('/login/')) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label for="l-email"><?= e(t('email')) ?></label><input id="l-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
    <div class="field"><label for="l-pw"><?= e(t('password')) ?></label><input id="l-pw" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn btn-block" type="submit"><?= e(t('login')) ?></button>
  </form>
  <p><a href="<?= e(su('/forgot-password/')) ?>"><?= e(t('forgot_password')) ?></a> · <a href="<?= e(su('/register/')) ?>"><?= e(t('register')) ?></a></p>
<?php elseif ($mode === 'register'): ?>
  <h1><?= e(t('register')) ?></h1>
  <form method="post" action="<?= e(su('/register/')) ?>" class="stack">
    <?= csrf_field() ?><?= form_guard_fields() ?>
    <div class="field"><label for="r-name"><?= e(t('name')) ?></label><input id="r-name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div>
    <div class="field"><label for="r-email"><?= e(t('email')) ?></label><input id="r-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
    <div class="field"><label for="r-pw"><?= e(t('password')) ?></label><input id="r-pw" type="password" name="password" required minlength="10" autocomplete="new-password" aria-describedby="r-pw-h"><small id="r-pw-h" class="muted">10+ / a-z / 0-9</small></div>
    <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(t('consent_privacy')) ?></span></label>
    <button class="btn btn-block" type="submit"><?= e(t('register')) ?></button>
  </form>
  <p><a href="<?= e(su('/login/')) ?>"><?= e(t('login')) ?></a></p>
<?php elseif ($mode === 'forgot'): ?>
  <h1><?= e(t('reset_password')) ?></h1>
  <form method="post" action="<?= e(su('/forgot-password/')) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label for="f-email"><?= e(t('email')) ?></label><input id="f-email" type="email" name="email" required autocomplete="email"></div>
    <button class="btn btn-block" type="submit"><?= e(t('send')) ?></button>
  </form>
<?php else: ?>
  <h1><?= e(t('reset_password')) ?></h1>
  <form method="post" action="<?= e(su('/reset-password/')) ?>" class="stack">
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="field"><label for="n-pw"><?= e(t('new_password')) ?></label><input id="n-pw" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
    <button class="btn btn-block" type="submit"><?= e(t('update')) ?></button>
  </form>
<?php endif ?>
</div>
