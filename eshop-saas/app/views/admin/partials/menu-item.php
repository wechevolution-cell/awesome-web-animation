<div class="menu-item">
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="rename"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><label class="sr" for="mi<?= (int) $it['id'] ?>">Label</label><input id="mi<?= (int) $it['id'] ?>" name="label" value="<?= e($it['label']) ?>" maxlength="80"><button class="btn btn-xs btn-ghost" type="submit">Rename</button></form>
  <span class="badge badge-muted"><?= e($it['type']) ?></span>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="move"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button class="act" name="dir" value="up" aria-label="Move up">↑</button><button class="act" name="dir" value="down" aria-label="Move down">↓</button></form>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button class="act" aria-label="Remove" data-confirm="Remove this menu item?"><?= icon('trash', 16) ?></button></form>
</div>
