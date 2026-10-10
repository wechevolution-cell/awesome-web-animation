<?php $headers = jdec($imp['headers']); $map = jdec($imp['mapping']); $opts = jdec($imp['options']); ?>
<div class="page-head"><div><a class="back" href="/admin/import/">← Imports</a><h1><?= e($imp['filename']) ?></h1><p class="muted"><?= strtoupper(e($imp['type'])) ?> · <?= number_format((int) $imp['total_rows']) ?> rows · <span class="badge badge-<?= e($imp['status']) ?>"><?= e($imp['status']) ?></span></p></div></div>
<ol class="steps"><?php foreach (['Upload', 'Validate', 'Preview & map', 'Confirm', 'Process', 'Report'] as $i => $s): $cur = match ($imp['status']) { 'validated' => 2, 'queued', 'processing' => 4, 'completed', 'failed', 'cancelled' => 5, default => 1 }; ?><li class="<?= $i < $cur ? 'done' : ($i === $cur ? 'cur' : '') ?>"><?= e($s) ?></li><?php endforeach ?></ol>
<?php if ($imp['status'] === 'validated' && $preview): ?>
<form method="post" class="stack"><?= csrf_field() ?>
  <section class="card"><h2 class="card-h">Map columns</h2>
    <div class="map-grid"><?php foreach ($headers as $h): ?><div class="field"><label for="m-<?= e(md5($h)) ?>"><code><?= e($h) ?></code></label><select id="m-<?= e(md5($h)) ?>" name="map[<?= e($h) ?>]"><option value="">— ignore —</option><?php foreach (IMPORT_FIELDS as $k => $l): ?><option value="<?= $k ?>" <?= ($map[$h] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div><?php endforeach ?></div>
  </section>
  <section class="card"><h2 class="card-h">Preview (first <?= count($preview['rows']) ?> rows, auto-mapped)</h2>
    <p class="small muted"><?= number_format($preview['stats']['total']) ?> rows · <?= number_format($preview['stats']['existing_skus']) ?> match existing SKUs (will update) · <?= number_format($preview['stats']['dup_in_file']) ?> duplicate SKUs inside the file (later rows update earlier ones)</p>
    <div class="table-wrap"><table class="table small"><thead><tr><th>#</th><?php foreach (array_keys(IMPORT_FIELDS) as $f): if (!in_array($f, $map, true)) continue; ?><th><?= e($f) ?></th><?php endforeach ?><th>Check</th></tr></thead><tbody>
    <?php foreach ($preview['rows'] as $r): ?><tr><td><?= (int) $r['row'] ?></td><?php foreach (array_keys(IMPORT_FIELDS) as $f): if (!in_array($f, $map, true)) continue; ?><td><?= e(str_limit((string) ($r['data'][$f] ?? ''), 40)) ?></td><?php endforeach ?><td><?= $r['errors'] ? '<span class="txt-bad">' . e(implode('; ', $r['errors'])) . '</span>' : '<span class="txt-ok">OK</span>' ?></td></tr><?php endforeach ?>
    </tbody></table></div>
  </section>
  <section class="card"><h2 class="card-h">Options</h2>
    <div class="row3">
      <?= f_select('mode', 'Mode', ['create_update' => 'Create new & update existing', 'create_only' => 'Create new only (skip existing)', 'update_only' => 'Update existing only'], 'create_update') ?>
      <?= f_select('activate', 'Activation', ['none' => 'Keep new products inactive', 'quota' => 'Activate up to my free quota (' . $q['available'] . ' slots)'], 'none', ['help' => 'A "status" column overrides this per row, still within quota.']) ?>
      <?= f_check('create_categories', 'Create missing categories from the category column', true) ?>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Confirm & start import</button> <button class="btn btn-link" name="act" value="cancel" formnovalidate>Cancel</button></div>
  </section>
</form>
<?php else: ?>
<section class="card" data-import-progress="<?= in_array($imp['status'], ['queued', 'processing'], true) ? (int) $imp['id'] : '' ?>">
  <h2 class="card-h">Progress</h2>
  <?php $pct = $imp['total_rows'] ? (int) floor($imp['processed_rows'] / $imp['total_rows'] * 100) : 0; ?>
  <div class="progress progress-lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>" data-ip-bar><span style="width:<?= $pct ?>%"></span></div>
  <p data-ip-text><?= number_format((int) $imp['processed_rows']) ?> / <?= number_format((int) $imp['total_rows']) ?> rows (<?= $pct ?>%)</p>
  <div class="kpis kpis-sm">
    <div class="kpi"><span>Created</span><strong data-ip="created"><?= (int) $imp['created_count'] ?></strong></div>
    <div class="kpi"><span>Updated</span><strong data-ip="updated"><?= (int) $imp['updated_count'] ?></strong></div>
    <div class="kpi"><span>Activated</span><strong data-ip="activated"><?= (int) $imp['activated_count'] ?></strong></div>
    <div class="kpi"><span>Skipped</span><strong data-ip="skipped"><?= (int) $imp['skipped_count'] ?></strong></div>
    <div class="kpi"><span>Errors</span><strong data-ip="errors"><?= (int) $imp['error_count'] ?></strong></div>
  </div>
  <p class="small muted" data-ip-note><?= in_array($imp['status'], ['queued', 'processing'], true) ? 'Processing in batches. You can leave this page — the server cron continues in the background.' : 'Finished ' . e(fmt_date($imp['finished_at'], true)) . '.' ?></p>
  <?php if (in_array($imp['status'], ['queued', 'processing'], true)): ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-link" name="act" value="cancel" data-confirm="Stop this import? Rows already processed are kept.">Cancel import</button></form><?php endif ?>
  <?php if ($imp['status'] === 'completed'): ?><p><a class="btn btn-sm" href="/admin/products/">View products</a></p><?php endif ?>
</section>
<?php if ($errors): ?><section class="card"><h2 class="card-h">Row errors (first 50)</h2><table class="table small"><thead><tr><th>Row</th><th>Message</th></tr></thead><tbody><?php foreach ($errors as $er): ?><tr><td><?= (int) $er['row_num'] ?></td><td><?= e($er['message']) ?></td></tr><?php endforeach ?></tbody></table></section><?php endif ?>
<?php endif ?>
<section class="card"><h2 class="card-h">Log</h2><ul class="timeline small"><?php foreach ($logs as $l): ?><li class="lvl-<?= e($l['level']) ?>"><span class="muted"><?= e(fmt_date($l['created_at'], true)) ?></span> <?= e($l['message']) ?></li><?php endforeach ?></ul></section>
