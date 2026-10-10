<?php $x = $u ?? []; ?>
<div class="page-head"><div><a class="back" href="/admin/users/">← Users</a><h1><?= $u ? e($u['email']) : 'Invite user' ?></h1></div></div>
<?php if ($invite): ?><div class="notice notice-ok">Invitation link (also emailed, valid 7 days): <code data-copy><?= e($invite) ?></code></div><?php endif ?>
<form method="post" class="edit-grid" data-role-presets="<?= e(jenc($presets)) ?>"><?= csrf_field() ?>
  <div class="edit-main"><section class="card">
    <div class="row2"><?= f_text('name', 'Name', $x['name'] ?? '') ?><?= f_text('email', 'Email', $x['email'] ?? '', ['type' => 'email', 'required' => true]) ?></div>
    <div class="row2"><?= f_select('role', 'Role', $roles, $x['role'] ?? 'editor', ['data' => ['role-select' => '1']]) ?><?= f_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $x['status'] ?? 'active') ?></div>
    <fieldset class="card-sub" data-perms><legend>Permissions</legend>
      <p class="f-help">Owners always have full access. Selecting a role pre-fills its default permissions; you can fine-tune them. Options you don't have yourself are disabled.</p>
      <div class="check-grid"><?php foreach (shop_permissions() as $k => $l): $mine = in_array($k, $myPerms, true); ?><label class="check<?= $mine ? '' : ' is-disabled' ?>"><input type="checkbox" name="perms[]" value="<?= $k ?>" <?= in_array($k, $uperms, true) || (!$u && in_array($k, $presets['editor'] ?? [], true)) ? 'checked' : '' ?> <?= $mine ? '' : 'disabled' ?>> <?= e($l) ?></label><?php endforeach ?></div>
    </fieldset>
  </section></div>
  <aside class="edit-side"><section class="card sticky">
    <button class="btn btn-block" type="submit"><?= $u ? 'Save user' : 'Create & send invitation' ?></button>
    <?php if ($u): ?>
      <button class="btn btn-sm btn-ghost" name="act" value="invite" formnovalidate>Send new password link</button>
      <?php if ((int) $u['id'] !== (int) current_user()['id']): ?><button class="btn btn-sm btn-danger" name="act" value="delete" formnovalidate data-confirm="Remove this user's access?">Remove user</button><?php endif ?>
    <?php endif ?>
  </section></aside>
</form>
