<div class="page-head"><div><h1>Menus</h1><p class="muted">Link menu items to categories, pages, products or external URLs. Hidden or inactive targets are skipped automatically.</p></div></div>
<div class="split-2">
<?php foreach ($menus as $loc => $m): ?>
  <section class="card"><h2 class="card-h"><?= e($m['label']) ?></h2>
    <ol class="menu-list">
      <?php foreach ($m['items'] as $it): ?>
      <li><?php partial('admin/partials/menu-item', ['it' => $it]) ?>
        <?php if ($it['children']): ?><ol><?php foreach ($it['children'] as $ch): ?><li><?php partial('admin/partials/menu-item', ['it' => $ch]) ?></li><?php endforeach ?></ol><?php endif ?>
      </li>
      <?php endforeach ?>
      <?php if (!$m['items']): ?><li class="muted">Empty.</li><?php endif ?>
    </ol>
    <details class="card-sub"><summary><?= icon('plus', 14) ?> Add item</summary>
      <form method="post" class="stack" data-menu-form>
        <?= csrf_field() ?><input type="hidden" name="act" value="add"><input type="hidden" name="location" value="<?= e($loc) ?>">
        <div class="row2">
          <div class="field"><label for="mt-<?= $loc ?>">Type</label><select id="mt-<?= $loc ?>" name="type" data-menu-type><option value="category">Category</option><option value="page">Page</option><option value="product">Product</option><option value="products">All products</option><option value="home">Home</option><option value="url">External URL</option></select></div>
          <div class="field"><label for="ml-<?= $loc ?>">Label (optional)</label><input id="ml-<?= $loc ?>" name="label" maxlength="80"></div>
        </div>
        <div class="field" data-menu-ref="category"><label for="mc-<?= $loc ?>">Category</label><select id="mc-<?= $loc ?>" name="ref_category"><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?></option><?php endforeach ?></select></div>
        <div class="field" data-menu-ref="page" hidden><label for="mp-<?= $loc ?>">Page</label><select id="mp-<?= $loc ?>" name="ref_page"><?php foreach ($pages as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach ?></select></div>
        <div class="field" data-menu-ref="product" hidden><label for="mpr-<?= $loc ?>">Product (active)</label><select id="mpr-<?= $loc ?>" name="ref_product"><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach ?></select></div>
        <div class="field" data-menu-ref="url" hidden><label for="mu-<?= $loc ?>">URL</label><input id="mu-<?= $loc ?>" name="url" type="url" placeholder="https://"></div>
        <div class="field"><label for="mpa-<?= $loc ?>">Parent</label><select id="mpa-<?= $loc ?>" name="parent_id"><option value="">— Top level —</option><?php foreach ($m['items'] as $it): ?><option value="<?= (int) $it['id'] ?>"><?= e($it['label']) ?></option><?php endforeach ?></select></div>
        <button class="btn btn-sm" type="submit">Add to menu</button>
      </form>
    </details>
  </section>
<?php endforeach ?>
</div>
