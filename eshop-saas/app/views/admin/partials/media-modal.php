<dialog class="modal" id="media-modal" closedby="any" aria-labelledby="mm-title">
  <div class="modal-head"><h2 id="mm-title">Media library</h2><button class="act" type="button" data-modal-close aria-label="Close"><?= icon('close', 20) ?></button></div>
  <form class="mm-upload" method="post" action="/admin/media/upload/" enctype="multipart/form-data" data-media-upload>
    <?= csrf_field() ?><label class="btn btn-sm"><?= icon('upload', 16) ?> Upload images<input type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden></label>
    <span class="muted small">JPG, PNG, WebP, GIF · max 10 MB · auto-resized to 2000px + WebP</span>
  </form>
  <div class="mm-grid" data-media-grid><p class="muted">Loading…</p></div>
  <div class="modal-foot"><button class="btn" type="button" data-media-confirm>Use selected</button></div>
</dialog>
