<div class="page-head"><div><h1>Customers</h1><p class="muted"><?= number_format($pg['total']) ?> customers</p></div></div>
<form class="filters-bar" method="get"><label class="sr" for="cq">Search</label><input id="cq" type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Email, name, phone"><button class="btn btn-sm">Search</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Account</th><th>Orders</th><th>Spent</th><th>Since</th></tr></thead><tbody>
<?php foreach ($customers as $c): ?><tr><td><a class="strong" href="/admin/customers/<?= (int) $c['id'] ?>/"><?= e($c['name'] ?: '—') ?></a></td><td><?= e($c['email']) ?></td><td><?= e($c['phone']) ?></td><td><?= $c['password_hash'] ? 'Registered' : 'Guest' ?></td><td><?= (int) $c['orders_count'] ?></td><td><?= e(money((int) $c['spent'])) ?></td><td class="small"><?= e(fmt_date($c['created_at'])) ?></td></tr><?php endforeach ?>
<?php if (!$customers): ?><tr><td colspan="7" class="empty-cell">No customers yet.</td></tr><?php endif ?>
</tbody></table></div><?= pager_html($pg, '/admin/customers/') ?>
