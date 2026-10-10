<?php
$renderTree = static function (array $nodes) use (&$renderTree, $counts): string {
    $h = '<ul class="tree">';
    foreach ($nodes as $n) {
        $c = $counts[$n['id']] ?? ['n' => 0, 'a' => 0];
        $h .= '<li><div class="tree-row"><a href="/admin/categories/' . (int) $n['id'] . '/">' . e($n['name']) . '</a><span class="muted small">/' . e($n['path']) . '/</span><span class="badge badge-muted">' . (int) $c['a'] . ' active · ' . (int) $c['n'] . ' total</span></div>' . ($n['children'] ? $renderTree($n['children']) : '') . '</li>';
    }
    return $h . '</ul>';
};
$c = $cat ?? [];
?>
<div class="page-head"><div><h1>Categories</h1><p class="muted">Unlimited categories & subcategories, any depth — not limited by your package.</p></div><div class="page-actions"><a class="btn" href="/admin/categories/new/"><?= icon('plus', 16) ?> New category</a></div></div>
<div class="split-2">
  <section class="card"><?php if ($tree): ?><?= $renderTree($tree) ?><?php else: ?><p class="muted">No categories yet.</p><?php endif ?></section>
  <?php if ($editing): ?>
  <section class="card">
    <h2 class="card-h"><?= $cat ? 'Edit: ' . e($cat['name']) : 'New category' ?></h2>
    <form method="post" class="stack" data-analyze="category" data-id="<?= (int) ($c['id'] ?? 0) ?>">
      <?= csrf_field() ?>
      <?= f_text('name', 'Name', $c['name'] ?? '', ['required' => true, 'data' => ['slug-source' => 'slug']]) ?>
      <div class="row2">
        <?= f_text('slug', 'Slug', $c['slug'] ?? '', ['help' => 'Changing it adds a 301 redirect for the old URL and all sub-URLs.']) ?>
        <?= f_select('parent_id', 'Parent', ['' => '— Top level —'] + array_column(array_map(static fn($x) => ['id' => $x['id'], 'n' => str_repeat('— ', (int) $x['depth']) . $x['name']], array_filter($flat, static fn($x) => !$cat || ($x['id'] != $cat['id'] && !str_starts_with($x['path'] . '/', $cat['path'] . '/')))), 'n', 'id'), (string) ($c['parent_id'] ?? '')) ?>
      </div>
      <?= f_text('position', 'Sort order', (string) ($c['position'] ?? 0), ['type' => 'number']) ?>
      <?= f_textarea('description', 'Description (shown above products)', $c['description'] ?? '', ['rows' => 4, 'help' => 'HTML allowed. A short intro that answers what this category contains helps SEO & AI search.']) ?>
      <?= f_media('image_id', 'Category image', isset($c['image_id']) ? (int) $c['image_id'] : null, (int) $shop['id']) ?>
      <details class="card-sub" <?= $cat ? '' : 'open' ?>><summary>SEO</summary>
        <?= f_text('focus_keyword', 'Focus keyword', $c['focus_keyword'] ?? '') ?>
        <?= f_text('seo_title', 'SEO title', $c['seo_title'] ?? '', ['maxlength' => 120]) ?>
        <?= f_textarea('seo_description', 'Meta description', $c['seo_description'] ?? '', ['rows' => 2, 'maxlength' => 320]) ?>
        <div class="row2"><?= f_text('canonical', 'Canonical URL', $c['canonical'] ?? '') ?><?= f_select('robots', 'Indexing', ['' => 'Index', 'noindex' => 'noindex', 'noindex,nofollow' => 'noindex, nofollow'], $c['robots'] ?? '') ?></div>
        <?= f_textarea('faq', 'FAQ', faq_to_text(jdec($c['faq'] ?? null)), ['rows' => 4, 'help' => '<code>Q:</code> / <code>A:</code> pairs.']) ?>
      </details>
      <div class="analyzer analyzer-inline" data-analyzer aria-live="polite"><div class="scores"><div class="score" data-score="seo"><strong>–</strong><span>SEO</span></div><div class="score" data-score="aeo"><strong>–</strong><span>AEO</span></div></div><ul class="checks" data-checks></ul><div data-cannibal></div></div>
      <div class="form-actions"><button class="btn" type="submit">Save category</button>
      <?php if ($cat): ?><button class="btn btn-danger btn-sm" type="submit" form="cat-del" data-confirm="Delete “<?= e($cat['name']) ?>”? Sub-categories move up one level. Products are NOT deleted.">Delete</button><?php endif ?></div>
    </form>
    <?php if ($cat): ?><form id="cat-del" method="post" action="/admin/categories/<?= (int) $cat['id'] ?>/delete/" hidden><?= csrf_field() ?></form><?php endif ?>
  </section>
  <?php else: ?>
  <section class="card"><h2 class="card-h">Tips</h2><ul class="small"><li>URLs follow the tree: <code>/parent/child/</code>.</li><li>Products can belong to several categories; the primary one drives breadcrumbs.</li><li>Use the <a href="/admin/menus/">menu builder</a> to show categories in the header.</li></ul></section>
  <?php endif ?>
</div>
