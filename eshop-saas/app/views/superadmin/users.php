<div class="page-head"><div><h1>System users</h1><p class="muted">Super admins. Shop staff (<?= $shopUsers ?> accounts) are managed inside each shop.</p></div></div>
<?php if ($invite): ?><div class="notice notice-ok">Invitation link (valid 7 days, also emailed): <code data-copy><?= e($invite) ?></code></div><?php endif ?>
<div class="split-2">
  <section class="card"><table class="table"><thead><tr><th>Email</th><th>Name</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($users as $u): ?><tr><td><?= e($u['email']) ?></td><td><?= e($u['name']) ?></td><td class="small"><?= e(fmt_date($u['last_login_at'], true)) ?></td><td><?= $u['status'] === 'active' ? ($u['password_hash'] ? 'Active' : 'Invited') : 'Disabled' ?></td><td><?php if ((int) $u['id'] !== (int) current_user()['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn-xs btn-ghost" name="act" value="<?= $u['status'] === 'active' ? 'disable' : 'enable' ?>"><?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form><?php endif ?></td></tr><?php endforeach ?>
  </tbody></table></section>
  <div>
  <section class="card"><h2 class="card-h">Invite super admin</h2><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="act" value="create"><?= f_text('email', 'Email', '', ['type' => 'email', 'required' => true]) ?><?= f_text('name', 'Name', '') ?><button class="btn btn-sm">Invite</button></form></section>
  <section class="card"><h2 class="card-h">Change my password</h2><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="act" value="password"><div class="field"><label for="cur">Current password</label><input id="cur" type="password" name="current" required autocomplete="current-password"></div><div class="field"><label for="npw">New password</label><input id="npw" type="password" name="password" required minlength="10" autocomplete="new-password"></div><button class="btn btn-sm">Change password</button></form></section>
  </div>
</div>
