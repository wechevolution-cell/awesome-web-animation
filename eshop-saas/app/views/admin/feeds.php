<?php $base = rtrim(shop_public_url($shop), '/'); ?>
<div class="page-head"><div><h1>Feeds & export</h1><p class="muted">Public feeds only ever contain <strong>active</strong> products. CSV export can include inactive ones.</p></div><div class="page-actions"><a class="btn" href="/admin/feeds/new/"><?= icon('plus', 16) ?> New feed</a></div></div>
<div class="split-2">
  <section class="card"><h2 class="card-h">Built-in endpoints</h2>
    <ul class="link-list">
      <li><span>Product XML</span><code data-copy><?= e($base) ?>/feed/products.xml</code></li>
      <li><span>RSS (new products)</span><code data-copy><?= e($base) ?>/feed/rss.xml</code></li>
      <li><span>Sitemap</span><code data-copy><?= e($base) ?>/sitemap.xml</code></li>
      <li><span>llms.txt</span><code data-copy><?= e($base) ?>/llms.txt</code></li>
    </ul>
  </section>
  <section class="card"><h2 class="card-h">CSV export</h2>
    <form method="get" action="/admin/products/export/" class="stack">
      <div class="row2"><?= f_select('status', 'Products', ['' => 'All products', 'active' => 'Active only', 'inactive' => 'Inactive only'], '') ?>
      <?= f_select('category_id', 'Category', array_column(array_map(static fn($c) => ['id' => $c['id'], 'n' => str_repeat('— ', (int) $c['depth']) . $c['name']], $cats), 'n', 'id'), '', ['empty' => 'All categories']) ?></div>
      <button class="btn btn-sm" type="submit"><?= icon('download', 16) ?> Download CSV</button>
      <p class="f-help">The CSV uses the same columns as the importer, so you can edit it in Excel and import it back (matched by SKU). Selected products can be exported from the product list (bulk action).</p>
    </form>
  </section>
</div>
<section class="card"><h2 class="card-h">Marketplace & custom feeds</h2>
<table class="table"><thead><tr><th>Name</th><th>Type</th><th>URL</th><th>Refresh</th><th>Last build</th><th>Status</th></tr></thead><tbody>
<?php foreach ($feeds as $f): ?><tr><td><a class="strong" href="/admin/feeds/<?= (int) $f['id'] ?>/"><?= e($f['name']) ?></a></td><td><?= e(FEED_TYPES[$f['type']] ?? $f['type']) ?></td><td><code class="small" data-copy><?= e($base) ?>/feed/<?= e($f['slug']) ?>.xml</code></td><td><?= (int) $f['frequency_minutes'] ?> min</td><td class="small"><?= e(fmt_date($f['last_generated_at'], true)) ?><?= $f['last_count'] !== null ? ' · ' . (int) $f['last_count'] . ' items' : '' ?></td><td><?= $f['active'] ? '<span class="badge badge-ok">On</span>' : '<span class="badge badge-muted">Off</span>' ?><?= feed_feature_ok($shop, $f['type']) ? '' : ' <span class="badge badge-warn">Not in package</span>' ?></td></tr><?php endforeach ?>
<?php if (!$feeds): ?><tr><td colspan="6" class="empty-cell">No feeds yet. Create a Skroutz or BestPrice feed to list your products on price-comparison sites.</td></tr><?php endif ?>
</tbody></table></section>
<?php if ($logs): ?><section class="card"><h2 class="card-h">Recent builds</h2><table class="table"><thead><tr><th>Feed</th><th>Items</th><th>Time</th><th>When</th></tr></thead><tbody><?php foreach ($logs as $l): ?><tr><td><?= e($l['name']) ?></td><td><?= (int) $l['items'] ?></td><td><?= (int) $l['duration_ms'] ?> ms</td><td class="small"><?= e(fmt_date($l['created_at'], true)) ?></td></tr><?php endforeach ?></tbody></table></section><?php endif ?>
