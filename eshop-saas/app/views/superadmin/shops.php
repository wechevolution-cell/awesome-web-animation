<div class="page-head"><div><h1>Shops</h1><p class="muted"><?= number_format($pg['total']) ?> shops</p></div><div class="page-actions"><a class="btn" href="/superadmin/shops/new/"><?= icon('plus', 16) ?> New shop</a></div></div>
<form class="filters-bar" method="get">
  <label class="sr" for="sq">Search</label><input id="sq" type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Name, email, slug, domain">
  <label class="sr" for="ss">Status</label><select id="ss" name="status"><option value="">All (not deleted)</option><option value="expiring_soon" <?= get('status') === 'expiring_soon' ? 'selected' : '' ?>>Expiring soon</option><?php foreach (SHOP_STATUSES as $s): if ($s === 'DELETED') continue; ?><option value="<?= $s ?>" <?= get('status') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach ?><option value="deleted" <?= get('status') === 'deleted' ? 'selected' : '' ?>>Deleted</option></select>
  <label class="sr" for="sp">Package</label><select id="sp" name="plan_id"><option value="">All packages</option><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>" <?= int_in('plan_id') === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach ?></select>
  <label class="sr" for="so">Sort</label><select id="so" name="sort"><option value="">Newest</option><option value="expires" <?= get('sort') === 'expires' ? 'selected' : '' ?>>Expiry date</option><option value="name" <?= get('sort') === 'name' ? 'selected' : '' ?>>Name</option></select>
  <button class="btn btn-sm">Filter</button>
</form>
<div class="table-wrap"><table class="table"><thead><tr><th>Shop</th><th>Domain</th><th>Package</th><th>Active / limit</th><th>Stored</th><th>Template</th><th>Start</th><th>Expires</th><th>Status</th></tr></thead><tbody>
<?php foreach ($shops as $s): $lim = $s['active_limit_override'] !== null ? (int) $s['active_limit_override'] : (int) $s['active_product_limit']; ?>
<tr><td><a class="strong" href="/superadmin/shops/<?= (int) $s['id'] ?>/"><?= e($s['name']) ?></a><?= $s['is_demo'] ? ' <span class="badge badge-muted">demo</span>' : '' ?><br><span class="small muted"><?= e($s['email']) ?></span></td>
<td class="small"><?= $s['domain'] ? e($s['domain']) : '<span class="muted">/s/' . e($s['slug']) . '/</span>' ?></td><td><?= e($s['plan_name']) ?></td>
<td><span class="<?= (int) $s['active_count'] > $lim ? 'txt-bad' : ((int) $s['active_count'] >= $lim ? 'txt-warn' : '') ?>"><?= (int) $s['active_count'] ?> / <?= $lim ?></span></td><td><?= number_format((int) $s['stored_count']) ?></td><td><?= e(template_get($s['template_key'])['name']) ?></td>
<td class="small"><?= e(fmt_date($s['subscription_start'])) ?></td><td class="small"><?= e(fmt_date($s['subscription_expires'])) ?></td><td><?= status_badge($s['deleted_at'] ? 'DELETED' : $s['status']) ?></td></tr>
<?php endforeach ?>
<?php if (!$shops): ?><tr><td colspan="9" class="empty-cell">No shops found.</td></tr><?php endif ?>
</tbody></table></div><?= pager_html($pg, '/superadmin/shops/') ?>
