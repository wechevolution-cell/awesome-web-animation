<?php $types = homepage_section_types(); $sid = (int) $shop['id']; ?>
<div class="page-head"><div><h1>Homepage</h1><p class="muted">Edit the sections of your homepage. The layout of each section comes from your template (<?= e(template_get($shop['template_key'])['name']) ?>), so it always looks right.</p></div>
<div class="page-actions"><a class="btn btn-ghost btn-sm" href="<?= e(shop_public_url($shop)) ?>" target="_blank" rel="noopener"><?= icon('external', 16) ?> Preview</a></div></div>
<?php foreach ($sections as $i => $s): $d = $s['d']; ?>
<section class="card hs" id="s<?= (int) $s['id'] ?>">
  <header class="hs-head">
    <h2 class="card-h"><?= e($types[$s['type']] ?? $s['type']) ?> <?= $s['enabled'] ? '' : '<span class="badge badge-muted">Hidden</span>' ?></h2>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="act" value="move"><button class="act" name="dir" value="up" aria-label="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button><button class="act" name="dir" value="down" aria-label="Move down" <?= $i === count($sections) - 1 ? 'disabled' : '' ?>>↓</button></form>
  </header>
  <details <?= $s['type'] === 'hero' ? 'open' : '' ?>><summary>Edit</summary>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="act" value="save">
    <?= f_check('enabled', 'Show this section', (bool) $s['enabled']) ?>
    <?php switch ($s['type']): case 'hero': ?>
      <?= f_text('eyebrow', 'Eyebrow (small text above title)', $d['eyebrow'] ?? '') ?>
      <?= f_text('title', 'Headline', $d['title'] ?? '', ['maxlength' => 160]) ?>
      <?= f_textarea('subtitle', 'Subtitle', $d['subtitle'] ?? '', ['rows' => 2, 'maxlength' => 400]) ?>
      <div class="row2"><?= f_text('cta_text', 'Button text', $d['cta_text'] ?? '') ?><?= f_text('cta_link', 'Button link', $d['cta_link'] ?? 'products', ['help' => 'e.g. <code>products</code>, a category path like <code>clothing/shirts</code>, or a full https:// URL.']) ?></div>
      <div class="row2"><?= f_media('image_id', 'Hero image', isset($d['image_id']) ? (int) $d['image_id'] : null, $sid, ['help' => 'Recommended 1600×1000 or larger.']) ?><?= f_media('image2_id', 'Secondary image (editorial templates)', isset($d['image2_id']) ? (int) $d['image2_id'] : null, $sid) ?></div>
    <?php break; case 'banner': ?>
      <?= f_text('title', 'Title', $d['title'] ?? '') ?><?= f_text('text', 'Text', $d['text'] ?? '') ?>
      <div class="row2"><?= f_text('cta_text', 'Button text', $d['cta_text'] ?? '') ?><?= f_text('cta_link', 'Button link', $d['cta_link'] ?? '') ?></div>
      <?= f_media('image_id', 'Banner image', isset($d['image_id']) ? (int) $d['image_id'] : null, $sid) ?>
    <?php break; case 'categories': ?>
      <div class="row2"><?= f_text('title', 'Title (optional)', $d['title'] ?? '') ?><?= f_text('limit', 'How many', (string) ($d['limit'] ?? 6), ['type' => 'number', 'min' => 1, 'max' => 24]) ?></div>
    <?php break; case 'featured_products': ?>
      <div class="row2"><?= f_text('title', 'Title (optional)', $d['title'] ?? '') ?><?= f_text('limit', 'How many', (string) ($d['limit'] ?? 8), ['type' => 'number', 'min' => 1, 'max' => 24]) ?></div>
      <div class="row2"><?= f_select('source', 'Products', ['featured' => 'Featured products', 'newest' => 'Newest', 'sale' => 'On sale', 'category' => 'From a category'], $d['source'] ?? 'featured') ?>
      <?= f_select('category_id', 'Category', array_column(array_map(static fn($c) => ['id' => $c['id'], 'n' => str_repeat('— ', (int) $c['depth']) . $c['name']], $cats), 'n', 'id'), (string) ($d['category_id'] ?? ''), ['empty' => '—']) ?></div>
    <?php break; case 'brands': ?>
      <?= f_text('title', 'Title', $d['title'] ?? '') ?><?= f_textarea('items', 'Brands (one per line)', implode("\n", $d['items'] ?? []), ['rows' => 4, 'help' => 'Only list brands you actually sell.']) ?>
    <?php break; case 'benefits': $items = array_pad($d['items'] ?? [], 4, ['icon' => 'star', 'title' => '', 'text' => '']); ?>
      <?php foreach ($items as $bi => $b): ?><div class="row3">
        <div class="field"><label for="bi<?= $s['id'] . $bi ?>">Icon</label><select id="bi<?= $s['id'] . $bi ?>" name="b_icon[]"><?php foreach (['truck', 'return', 'shield', 'chat', 'leaf', 'star', 'clock', 'box', 'sparkle'] as $ic): ?><option <?= ($b['icon'] ?? '') === $ic ? 'selected' : '' ?>><?= $ic ?></option><?php endforeach ?></select></div>
        <div class="field"><label for="bt<?= $s['id'] . $bi ?>">Title</label><input id="bt<?= $s['id'] . $bi ?>" name="b_title[]" value="<?= e($b['title'] ?? '') ?>"></div>
        <div class="field"><label for="bx<?= $s['id'] . $bi ?>">Text</label><input id="bx<?= $s['id'] . $bi ?>" name="b_text[]" value="<?= e($b['text'] ?? '') ?>"></div>
      </div><?php endforeach ?>
    <?php break; case 'newsletter': ?>
      <?= f_text('title', 'Title', $d['title'] ?? '') ?><?= f_text('text', 'Text', $d['text'] ?? '') ?>
    <?php break; case 'rich_text': ?>
      <?= f_text('title', 'Title', $d['title'] ?? '') ?><?= f_textarea('html', 'Content (HTML allowed)', $d['html'] ?? '', ['rows' => 6]) ?>
    <?php break; endswitch ?>
    <div class="form-actions"><button class="btn btn-sm" type="submit">Save section</button></div>
  </form>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-xs btn-link" name="act" value="delete" data-confirm="Remove this section?">Remove section</button></form>
  </details>
</section>
<?php endforeach ?>
<section class="card"><form method="post" class="inline-form-row"><?= csrf_field() ?><input type="hidden" name="act" value="add"><label for="addsec">Add a section</label><select id="addsec" name="type"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach ?></select><button class="btn btn-sm">Add</button></form></section>
