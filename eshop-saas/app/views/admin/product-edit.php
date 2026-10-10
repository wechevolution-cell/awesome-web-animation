<?php
$sid = (int) $shop['id'];
$v = static fn(string $k, mixed $d = '') => $p[$k] ?? $d;
$isActive = ($p['status'] ?? 'inactive') === 'active';
?>
<div class="page-head">
  <div><a class="back" href="/admin/products/">← Products</a><h1><?= $p ? e($p['name']) : 'New product' ?></h1></div>
  <div class="page-actions"><?php if ($p && $isActive): ?><a class="btn btn-ghost btn-sm" href="<?= e(rtrim(shop_public_url($shop), '/') . '/product/' . $p['slug'] . '/') ?>" target="_blank" rel="noopener"><?= icon('external', 16) ?> View</a><?php endif ?></div>
</div>
<form method="post" class="edit-grid" data-analyze="product" data-id="<?= (int) ($p['id'] ?? 0) ?>" id="product-form">
  <?= csrf_field() ?>
  <div class="edit-main">
    <section class="card">
      <?= f_text('name', 'Product name', $v('name'), ['required' => true, 'maxlength' => 200, 'data' => ['slug-source' => 'slug']]) ?>
      <div class="row2">
        <?= f_text('sku', 'SKU', $v('sku'), ['maxlength' => 64]) ?>
        <?= f_text('barcode', 'Barcode (EAN)', $v('barcode'), ['maxlength' => 64]) ?>
      </div>
      <?= f_textarea('short_description', 'Short description', $v('short_description'), ['rows' => 3, 'maxlength' => 1000, 'help' => 'Shown next to the price. Start with a direct answer: what it is and who it is for (good for AI search).']) ?>
      <div class="field"><label for="f-description">Description</label>
        <div class="rte" data-rte><div class="rte-bar" role="toolbar" aria-label="Formatting"><button type="button" data-cmd="h2">H2</button><button type="button" data-cmd="h3">H3</button><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="ul">• List</button><button type="button" data-cmd="ol">1. List</button><button type="button" data-cmd="link">Link</button><button type="button" data-cmd="html">HTML</button></div>
        <textarea id="f-description" name="description" rows="10" data-rte-source><?= e(old('description', $v('description'))) ?></textarea></div>
        <p class="f-help">Allowed: headings, lists, links, tables, images, YouTube/Vimeo/Maps embeds. Scripts are removed automatically.</p>
      </div>
    </section>
    <section class="card"><h2 class="card-h">Pricing & inventory</h2>
      <div class="row3">
        <?= f_text('price', 'Price (€)', cents_input(isset($p['price_cents']) ? (int) $p['price_cents'] : null), ['required' => true, 'inputmode' => 'decimal']) ?>
        <?= f_text('sale_price', 'Sale price (€)', cents_input(isset($p['sale_price_cents']) && $p['sale_price_cents'] !== null ? (int) $p['sale_price_cents'] : null), ['inputmode' => 'decimal']) ?>
        <?= f_text('cost_price', 'Cost price (€)', cents_input(isset($p['cost_price_cents']) && $p['cost_price_cents'] !== null ? (int) $p['cost_price_cents'] : null), ['inputmode' => 'decimal', 'help' => 'Private, never shown.']) ?>
      </div>
      <div class="row3">
        <?= f_text('stock', 'Stock', (string) $v('stock', 0), ['type' => 'number', 'min' => 0]) ?>
        <?= f_check('manage_stock', 'Track stock', (bool) $v('manage_stock', 1)) ?>
        <?= f_text('brand', 'Brand', $v('brand'), ['maxlength' => 120]) ?>
      </div>
      <div class="row4">
        <?= f_text('weight_g', 'Weight (g)', (string) $v('weight_g'), ['type' => 'number', 'min' => 0]) ?>
        <?= f_text('length_mm', 'Length (mm)', (string) $v('length_mm'), ['type' => 'number', 'min' => 0]) ?>
        <?= f_text('width_mm', 'Width (mm)', (string) $v('width_mm'), ['type' => 'number', 'min' => 0]) ?>
        <?= f_text('height_mm', 'Height (mm)', (string) $v('height_mm'), ['type' => 'number', 'min' => 0]) ?>
      </div>
    </section>
    <section class="card"><h2 class="card-h">Images</h2>
      <div class="img-list" data-image-list>
        <?php foreach ($images as $m): ?><div class="img-item" draggable="true"><img src="<?= e(media_url($m, 400)) ?>" alt=""><input type="hidden" name="images[]" value="<?= (int) $m['id'] ?>"><button type="button" class="img-x" data-image-remove aria-label="Remove image"><?= icon('close', 14) ?></button></div><?php endforeach ?>
        <input type="hidden" name="images[]" value="0">
      </div>
      <button type="button" class="btn btn-sm btn-ghost" data-media-open data-media-multi data-media-target="[data-image-list]"><?= icon('image', 16) ?> Add images</button>
      <p class="f-help">The first image is the main image. Drag to reorder.</p>
    </section>
    <section class="card"><h2 class="card-h">Variants</h2>
      <p class="f-help">One row per purchasable combination, e.g. <code>Size: M, Color: Black</code>. Leave price empty to use the product price.</p>
      <div class="repeat" data-repeat>
        <div class="repeat-head var-row"><span>Options</span><span>SKU</span><span>Price (€)</span><span>Stock</span><span></span></div>
        <?php foreach ($variants ?: [null] as $i => $vr): ?>
        <div class="var-row" data-repeat-row>
          <input type="hidden" name="variant_id[]" value="<?= (int) ($vr['id'] ?? 0) ?>">
          <label class="sr">Options</label><input name="variant_options[]" value="<?= e($vr ? implode(', ', array_map(static fn($k, $x) => "$k: $x", array_keys($vr['opts']), $vr['opts'])) : '') ?>" placeholder="Size: M, Color: Black">
          <label class="sr">SKU</label><input name="variant_sku[]" value="<?= e($vr['sku'] ?? '') ?>" placeholder="SKU">
          <label class="sr">Price</label><input name="variant_price[]" value="<?= e(isset($vr['price_cents']) && $vr['price_cents'] !== null ? cents_input((int) $vr['price_cents']) : '') ?>" placeholder="—" inputmode="decimal">
          <label class="sr">Stock</label><input name="variant_stock[]" value="<?= (int) ($vr['stock'] ?? 0) ?>" type="number" min="0">
          <button type="button" class="act" data-repeat-remove aria-label="Remove row"><?= icon('trash', 16) ?></button>
        </div>
        <?php endforeach ?>
        <button type="button" class="btn btn-sm btn-ghost" data-repeat-add><?= icon('plus', 14) ?> Add variant</button>
      </div>
    </section>
    <section class="card"><h2 class="card-h">Specifications</h2>
      <div class="repeat" data-repeat>
        <?php foreach ($attrs ?: [null, null] as $a): ?>
        <div class="attr-row" data-repeat-row><label class="sr">Name</label><input name="attr_name[]" value="<?= e($a['name'] ?? '') ?>" placeholder="e.g. Material"><label class="sr">Value</label><input name="attr_value[]" value="<?= e($a['value'] ?? '') ?>" placeholder="e.g. 100% cotton"><button type="button" class="act" data-repeat-remove aria-label="Remove row"><?= icon('trash', 16) ?></button></div>
        <?php endforeach ?>
        <button type="button" class="btn btn-sm btn-ghost" data-repeat-add><?= icon('plus', 14) ?> Add specification</button>
      </div>
    </section>
    <section class="card"><h2 class="card-h">SEO & AI search</h2>
      <div class="serp" aria-hidden="true"><span class="serp-url" data-serp-url><?= e(rtrim(shop_public_url($shop), '/')) ?>/product/<?= e($v('slug', 'product')) ?>/</span><span class="serp-title" data-serp-title><?= e($v('seo_title') ?: $v('name') ?: 'Product title') ?></span><span class="serp-desc" data-serp-desc><?= e($v('seo_description') ?: 'Meta description preview…') ?></span></div>
      <?= f_text('focus_keyword', 'Focus keyword', $v('focus_keyword'), ['maxlength' => 120]) ?>
      <?= f_text('seo_title', 'SEO title', $v('seo_title'), ['maxlength' => 120, 'help' => '30–60 characters. Defaults to the product name.']) ?>
      <?= f_textarea('seo_description', 'Meta description', $v('seo_description'), ['rows' => 2, 'maxlength' => 320, 'help' => '70–160 characters.']) ?>
      <div class="row2">
        <?= f_text('slug', 'URL slug', $v('slug'), ['maxlength' => 80, 'help' => 'Changing it creates an automatic 301 redirect.']) ?>
        <?= f_select('robots', 'Indexing', ['' => 'Index (default)', 'noindex' => 'noindex', 'noindex,nofollow' => 'noindex, nofollow'], $v('robots', '')) ?>
      </div>
      <?= f_text('canonical', 'Canonical URL (optional)', $v('canonical'), ['type' => 'url']) ?>
      <?= f_textarea('faq', 'FAQ', faq_to_text(jdec($p['faq'] ?? null)), ['rows' => 5, 'help' => 'Format: <code>Q: question</code> then <code>A: answer</code>, blank line between. Adds FAQPage structured data.']) ?>
    </section>
  </div>
  <aside class="edit-side">
    <section class="card sticky">
      <h2 class="card-h">Visibility</h2>
      <?= quota_bar($q) ?>
      <fieldset class="seg"><legend class="sr">Status</legend>
        <label><input type="radio" name="status" value="active" <?= old('status', $isActive ? 'active' : 'inactive') === 'active' ? 'checked' : '' ?> <?= !$isActive && $q['available'] === 0 ? 'disabled' : '' ?>> Active</label>
        <label><input type="radio" name="status" value="inactive" <?= old('status', $isActive ? 'active' : 'inactive') === 'inactive' ? 'checked' : '' ?>> Inactive</label>
      </fieldset>
      <?php if (!$isActive && $q['available'] === 0): ?><p class="f-help txt-bad">Quota full (<?= $q['active'] ?>/<?= $q['limit'] ?>). The product will be saved as inactive.</p><?php endif ?>
      <?= f_check('featured', 'Featured on homepage', (bool) $v('featured')) ?>
      <button class="btn btn-block" type="submit">Save product</button>
      <div class="analyzer" data-analyzer aria-live="polite">
        <div class="scores"><div class="score" data-score="seo"><strong>–</strong><span>SEO</span></div><div class="score" data-score="aeo"><strong>–</strong><span>AEO</span></div></div>
        <ul class="checks" data-checks></ul>
        <div data-cannibal></div>
      </div>
    </section>
    <section class="card"><h2 class="card-h">Categories</h2>
      <div class="cat-checks">
        <?php $sel = array_map('intval', arr(old('categories', $pcats))); foreach ($cats as $c): ?>
        <label class="check" style="--d:<?= (int) $c['depth'] ?>"><input type="checkbox" name="categories[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $sel, true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label>
        <?php endforeach ?>
        <?php if (!$cats): ?><p class="muted small">No categories yet. <a href="/admin/categories/new/">Create one</a>.</p><?php endif ?>
      </div>
      <?php if ($cats): ?><?= f_select('primary_category_id', 'Primary category (breadcrumbs)', array_column(array_map(static fn($c) => ['id' => $c['id'], 'n' => str_repeat('— ', (int) $c['depth']) . $c['name']], $cats), 'n', 'id'), (string) $v('primary_category_id'), ['empty' => 'First selected']) ?><?php endif ?>
    </section>
    <?php if ($p): ?>
    <section class="card danger"><h2 class="card-h">Delete</h2><p class="small muted">Soft delete: the product disappears and frees its active slot. Past orders keep their line items.</p>
      <button class="btn btn-danger btn-sm" type="submit" form="del-form" data-confirm="Delete “<?= e($p['name']) ?>”? Orders that contain it are not affected.">Delete product</button></section>
    <?php endif ?>
  </aside>
</form>
<?php if ($p): ?><form id="del-form" method="post" action="/admin/products/bulk/" hidden><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ids[]" value="<?= (int) $p['id'] ?>"><input type="hidden" name="return" value="/admin/products/"></form><?php endif ?>
