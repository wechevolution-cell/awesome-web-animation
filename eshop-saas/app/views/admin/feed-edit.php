<?php $x = $f ?? []; $fl = jdec($x['filters'] ?? null); $fields = jdec($x['fields'] ?? null); $types = array_filter(FEED_TYPES, static fn($t) => feed_feature_ok($shop, $t), ARRAY_FILTER_USE_KEY); ?>
<div class="page-head"><div><a class="back" href="/admin/feeds/">← Feeds</a><h1><?= $f ? e($f['name']) : 'New feed' ?></h1></div></div>
<?php if (count($types) < count(FEED_TYPES)): ?><div class="notice notice-info">Some feed types are available on higher packages: <?= e(implode(', ', array_diff_key(FEED_TYPES, $types))) ?>.</div><?php endif ?>
<form method="post" class="edit-grid"><?= csrf_field() ?>
  <div class="edit-main"><section class="card">
    <div class="row2"><?= f_select('type', 'Feed type', $types, $x['type'] ?? array_key_first($types), ['required' => true]) ?><?= f_text('name', 'Name', $x['name'] ?? '') ?></div>
    <div class="row2"><?= f_text('slug', 'URL name', $x['slug'] ?? '', ['help' => 'Feed URL: /feed/<em>name</em>.xml']) ?><?= f_text('frequency_minutes', 'Regenerate every (minutes)', (string) ($x['frequency_minutes'] ?? 60), ['type' => 'number', 'min' => 5, 'max' => 1440]) ?></div>
    <fieldset class="card-sub"><legend>Product filters</legend>
      <div class="cat-checks cat-checks-sm"><?php foreach ($cats as $c): ?><label class="check" style="--d:<?= (int) $c['depth'] ?>"><input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], array_map('intval', $fl['category_ids'] ?? []), true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach ?></div>
      <p class="f-help">No category selected = all active products.</p>
      <div class="row2"><?= f_check('in_stock', 'Only in-stock products', !empty($fl['in_stock'])) ?><?= f_text('min_price', 'Minimum price (€)', isset($fl['min_price']) && $fl['min_price'] ? cents_input((int) $fl['min_price']) : '') ?></div>
    </fieldset>
    <fieldset class="card-sub"><legend>Fields (Custom XML only)</legend><div class="check-grid"><?php foreach (FEED_CUSTOM_FIELDS as $fd): ?><label class="check"><input type="checkbox" name="fields[]" value="<?= $fd ?>" <?= in_array($fd, $fields ?: ['id', 'name', 'url', 'image', 'final_price', 'availability'], true) ? 'checked' : '' ?>> <?= $fd ?></label><?php endforeach ?></div></fieldset>
  </section></div>
  <aside class="edit-side"><section class="card sticky">
    <?= f_check('active', 'Feed is active', (bool) ($x['active'] ?? 1)) ?>
    <button class="btn btn-block" type="submit">Save feed</button>
    <?php if ($f): ?>
      <p class="small">URL: <code data-copy><?= e(rtrim(shop_public_url($shop), '/')) ?>/feed/<?= e($f['slug']) ?>.xml</code></p>
      <button class="btn btn-sm btn-ghost" name="act" value="regenerate" formnovalidate>Regenerate now</button>
      <button class="btn btn-sm btn-danger" name="act" value="delete" formnovalidate data-confirm="Delete this feed? Marketplaces using the URL will stop receiving updates.">Delete</button>
    <?php endif ?>
  </section></aside>
</form>
