<div class="page-head"><div><h1>Import / Export</h1><p class="muted">Upload → Validate → Preview → Confirm → Process (in background batches) → Report. Handles 10,000+ products without timeouts.</p></div></div>
<?= quota_bar($q) ?>
<?php if (!$enabled): ?><div class="notice notice-info">Imports are not included in your package.</div><?php else: ?>
<div class="split-2">
  <section class="card"><h2 class="card-h">1. Upload a file</h2>
    <form method="post" enctype="multipart/form-data" class="stack"><?= csrf_field() ?>
      <div class="field"><label for="imp-file">CSV or XML file</label><input id="imp-file" type="file" name="file" accept=".csv,.xml,.txt,text/csv,application/xml,text/xml" required></div>
      <div class="row2"><?= f_select('encoding', 'CSV encoding', ['UTF-8' => 'UTF-8 (recommended)', 'Windows-1253' => 'Windows-1253 (Greek Excel)', 'ISO-8859-7' => 'ISO-8859-7 (Greek)', 'Windows-1252' => 'Windows-1252 (Western)'], 'UTF-8') ?>
      <?= f_text('xml_item', 'XML item element (optional)', '', ['placeholder' => 'product', 'help' => 'Auto-detected: product, item, entry, offer.']) ?></div>
      <button class="btn" type="submit"><?= icon('upload', 16) ?> Upload & validate</button>
    </form>
  </section>
  <section class="card"><h2 class="card-h">Format</h2>
    <p class="small">First row = column names. Columns are mapped in the next step; common names in English and Greek are detected automatically.</p>
    <pre class="code small">sku,name,price,sale_price,stock,category,images,brand,status
TS-001,Linen shirt,49.90,,12,Clothing > Shirts,https://…/1.jpg|https://…/2.jpg,Acme,active</pre>
    <ul class="small"><li>Existing products are matched by <strong>SKU</strong> (or slug when there is no SKU) — no duplicates.</li><li>Activation always respects your quota: extra products are stored as inactive.</li><li>Image URLs are referenced remotely (not downloaded).</li></ul>
  </section>
</div>
<?php endif ?>
<section class="card"><h2 class="card-h">Import history</h2>
<table class="table"><thead><tr><th>File</th><th>Rows</th><th>Created</th><th>Updated</th><th>Activated</th><th>Errors</th><th>Status</th><th>Date</th></tr></thead><tbody>
<?php foreach ($imports as $i): ?><tr><td><a class="strong" href="/admin/import/<?= (int) $i['id'] ?>/"><?= e($i['filename']) ?></a></td><td><?= (int) $i['total_rows'] ?></td><td><?= (int) $i['created_count'] ?></td><td><?= (int) $i['updated_count'] ?></td><td><?= (int) $i['activated_count'] ?></td><td class="<?= $i['error_count'] ? 'txt-bad' : '' ?>"><?= (int) $i['error_count'] ?></td><td><span class="badge badge-<?= e($i['status']) ?>"><?= e($i['status']) ?></span></td><td class="small"><?= e(fmt_date($i['created_at'], true)) ?></td></tr><?php endforeach ?>
<?php if (!$imports): ?><tr><td colspan="8" class="empty-cell">No imports yet.</td></tr><?php endif ?>
</tbody></table></section>
