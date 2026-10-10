<div class="page-head"><div><h1>Media library</h1><p class="muted">Storage: <?= e(bytes_h($used)) ?> of <?= e(bytes_h($limit)) ?> · Images are validated, resized to max 2000px and converted to responsive WebP automatically.</p></div></div>
<section class="card"><form method="post" action="/admin/media/upload/" enctype="multipart/form-data" class="dropzone" data-dropzone><?= csrf_field() ?>
  <?= icon('upload', 28) ?><p><strong>Drop images here</strong> or <label class="link">browse<input type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden data-autoupload></label></p><p class="small muted">JPG, PNG, WebP, GIF · up to 10 MB each</p>
  <noscript><button class="btn btn-sm">Upload</button></noscript>
</form></section>
<div class="media-grid">
<?php foreach ($media as $m): ?>
  <figure class="card media-card">
    <img src="<?= e(media_url($m, 400)) ?>" alt="<?= e($m['alt']) ?>" loading="lazy" width="200" height="200">
    <figcaption><span class="small" title="<?= e($m['filename']) ?>"><?= e(str_limit($m['filename'], 26)) ?></span><span class="small muted"><?= $m['external_url'] ? 'Remote' : (int) $m['width'] . '×' . (int) $m['height'] . ' · ' . e(bytes_h((int) $m['size_bytes'])) ?></span></figcaption>
    <details><summary class="small">Edit</summary>
      <form method="post" action="/admin/media/<?= (int) $m['id'] ?>/" class="stack-sm"><?= csrf_field() ?><input type="hidden" name="act" value="alt"><label class="sr" for="alt<?= (int) $m['id'] ?>">Alt text</label><input id="alt<?= (int) $m['id'] ?>" name="alt" value="<?= e($m['alt']) ?>" placeholder="Alt text"><button class="btn btn-xs">Save alt</button></form>
      <?php if (!$m['external_url']): ?><form method="post" action="/admin/media/<?= (int) $m['id'] ?>/" enctype="multipart/form-data" class="stack-sm"><?= csrf_field() ?><input type="hidden" name="act" value="replace"><label class="small">Replace file <input type="file" name="file" accept="image/*" required></label><button class="btn btn-xs btn-ghost">Replace</button></form><?php endif ?>
      <form method="post" action="/admin/media/<?= (int) $m['id'] ?>/"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><button class="btn btn-xs btn-link" data-confirm="Delete this image? It will disappear from any product or page that uses it.">Delete</button></form>
    </details>
  </figure>
<?php endforeach ?>
</div>
<?php if (!$media): ?><p class="muted">No images yet.</p><?php endif ?>
<?= pager_html($pg, '/admin/media/') ?>
