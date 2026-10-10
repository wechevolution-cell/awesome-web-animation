<?php $ret = request_path() . query_string(); ?>
<div class="page-head">
  <div><h1>Products</h1><p class="muted"><?= number_format($pg['total']) ?> products · showing <?= $pg['total'] ? $pg['offset'] + 1 : 0 ?>–<?= min($pg['total'], $pg['offset'] + $pg['limit']) ?> of <?= number_format($pg['total']) ?></p></div>
  <div class="page-actions"><a class="btn" href="/admin/products/new/"><?= icon('plus', 16) ?> New product</a></div>
</div>
<?= quota_bar($q) ?>
<?php if ($q['available'] === 0): ?><div class="notice notice-warn" role="status">You have reached your package limit for active products (<?= $q['active'] ?>/<?= $q['limit'] ?>). Deactivate a product to free a slot, or ask your provider for a bigger package.</div><?php endif ?>
<form class="filters-bar" method="get">
  <label class="sr" for="fq">Search</label><input id="fq" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, SKU, EAN, brand…">
  <label class="sr" for="fs">Status</label><select id="fs" name="status"><option value="">All statuses</option><option value="active" <?= get('status') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= get('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
  <label class="sr" for="fc">Category</label><select id="fc" name="category_id"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= int_in('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?></option><?php endforeach ?></select>
  <label class="sr" for="fst">Stock</label><select id="fst" name="stock"><option value="">Any stock</option><option value="out" <?= get('stock') === 'out' ? 'selected' : '' ?>>Out of stock</option></select>
  <label class="sr" for="fp">Per page</label><select id="fp" name="per"><?php foreach ([25, 50, 100, 200] as $n): ?><option value="<?= $n ?>" <?= $pg['limit'] === $n ? 'selected' : '' ?>><?= $n ?> / page</option><?php endforeach ?></select>
  <button class="btn btn-sm" type="submit">Filter</button><a class="btn btn-sm btn-link" href="/admin/products/">Reset</a>
</form>
<form method="post" action="/admin/products/bulk/" data-bulk>
  <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>">
  <div class="bulk-bar" data-bulk-bar>
    <span data-bulk-count>0 selected</span>
    <label class="sr" for="ba">Bulk action</label>
    <select id="ba" name="action" data-bulk-action><option value="">Bulk action…</option><option value="activate">Activate</option><option value="deactivate">Deactivate</option><option value="assign_category">Assign category</option><option value="price">Update prices</option><option value="export">Export CSV</option><option value="delete">Delete</option></select>
    <span data-bulk-extra="assign_category" hidden><label class="sr" for="bc">Category</label><select id="bc" name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?></option><?php endforeach ?></select></span>
    <span data-bulk-extra="price" hidden><label class="sr" for="bpm">Mode</label><select id="bpm" name="price_mode"><option value="percent">± %</option><option value="amount">± €</option></select><label class="sr" for="bpv">Value</label><input id="bpv" name="price_value" size="6" placeholder="e.g. -10"></span>
    <button class="btn btn-sm" type="submit" data-confirm="Apply this bulk action to the selected products?">Apply</button>
  </div>
  <div class="table-wrap">
  <table class="table table-products">
    <thead><tr><th class="w-check"><input type="checkbox" data-check-all aria-label="Select all on this page"></th><th></th><th><?= sort_link('name', 'Name') ?></th><th>SKU</th><th><?= sort_link('price', 'Price') ?></th><th><?= sort_link('stock', 'Stock') ?></th><th>Category</th><th><?= sort_link('status', 'Status') ?></th><th><?= sort_link('updated', 'Updated') ?></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-check aria-label="Select <?= e($p['name']) ?>"></td>
        <td class="thumb-cell"><?php if ($p['image']): ?><img src="<?= e(media_url($p['image'], 400)) ?>" alt="" width="40" height="40" loading="lazy"><?php else: ?><span class="thumb-ph"><?= icon('image', 16) ?></span><?php endif ?></td>
        <td><a class="strong" href="/admin/products/<?= (int) $p['id'] ?>/"><?= e($p['name']) ?></a><?php if ($p['featured']): ?> <span class="badge badge-muted">Featured</span><?php endif ?></td>
        <td class="mono"><?= e($p['sku']) ?></td>
        <td><?= e(money(product_effective_price($p))) ?><?php if (product_on_sale($p)): ?> <s class="muted small"><?= e(money((int) $p['price_cents'])) ?></s><?php endif ?></td>
        <td class="<?= (int) $p['manage_stock'] && (int) $p['stock'] <= 0 ? 'txt-bad' : '' ?>"><?= (int) $p['manage_stock'] ? (int) $p['stock'] : '∞' ?></td>
        <td><?= e($p['cat_name']) ?></td>
        <td><button class="toggle<?= $p['status'] === 'active' ? ' is-on' : '' ?>" type="submit" form="st-<?= (int) $p['id'] ?>" data-status-toggle="<?= (int) $p['id'] ?>" aria-pressed="<?= $p['status'] === 'active' ? 'true' : 'false' ?>" aria-label="<?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?> <?= e($p['name']) ?>"><span></span><em><?= $p['status'] === 'active' ? 'Active' : 'Inactive' ?></em></button></td>
        <td class="small muted"><?= e(fmt_date($p['updated_at'] ?: $p['created_at'])) ?></td>
      </tr>
    <?php endforeach ?>
    <?php if (!$products): ?><tr><td colspan="9" class="empty-cell">No products match. <a href="/admin/products/new/">Add a product</a> or <a href="/admin/import/">import a CSV/XML</a>.</td></tr><?php endif ?>
    </tbody>
  </table>
  </div>
</form>
<?php foreach ($products as $p): ?><form id="st-<?= (int) $p['id'] ?>" method="post" action="/admin/products/<?= (int) $p['id'] ?>/status/" hidden><?= csrf_field() ?><input type="hidden" name="to" value="<?= $p['status'] === 'active' ? 'inactive' : 'active' ?>"></form><?php endforeach ?>
<?= pager_html($pg, '/admin/products/') ?>
