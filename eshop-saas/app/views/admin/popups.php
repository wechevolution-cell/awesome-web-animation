<div class="page-head"><div><h1>Popups</h1><p class="muted">One homepage popup can be active at a time. Email captures are stored with consent records under Subscribers.</p></div><div class="page-actions"><?php if ($enabled): ?><a class="btn" href="/admin/popups/new/"><?= icon('plus', 16) ?> New popup</a><?php endif ?></div></div>
<?php if (!$enabled): ?><div class="notice notice-info">Popups are not included in your package.</div><?php endif ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Title</th><th>Type</th><th>Delay</th><th>Subscribers</th><th>Status</th></tr></thead><tbody>
<?php foreach ($popups as $p): ?><tr><td><a class="strong" href="/admin/popups/<?= (int) $p['id'] ?>/"><?= e($p['title']) ?></a></td><td><?= $p['collect_email'] ? 'Email capture' : 'Promotion' ?></td><td><?= (int) $p['delay_seconds'] ?>s</td><td><?= (int) $p['subs'] ?></td><td><?= $p['active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-muted">Off</span>' ?></td></tr><?php endforeach ?>
<?php if (!$popups): ?><tr><td colspan="5" class="empty-cell">No popups yet.</td></tr><?php endif ?>
</tbody></table></div>
