<?php $pg = $page ?? []; ?>
<div class="page-head"><div><a class="back" href="/admin/pages/">← Pages</a><h1><?= $page ? e($page['title']) : 'New page' ?></h1></div></div>
<form method="post" class="edit-grid" data-analyze="page" data-id="<?= (int) ($pg['id'] ?? 0) ?>">
  <?= csrf_field() ?>
  <div class="edit-main">
    <section class="card">
      <?= f_text('title', 'Title', $pg['title'] ?? '', ['required' => true, 'data' => ['slug-source' => 'slug']]) ?>
      <div class="field"><label for="f-content">Content</label>
        <div class="rte" data-rte><div class="rte-bar" role="toolbar" aria-label="Formatting"><button type="button" data-cmd="h2">H2</button><button type="button" data-cmd="h3">H3</button><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="ul">• List</button><button type="button" data-cmd="ol">1. List</button><button type="button" data-cmd="link">Link</button><button type="button" data-cmd="html">HTML</button></div>
        <textarea id="f-content" name="content" rows="14" data-rte-source><?= e(old('content', $pg['content'] ?? '')) ?></textarea></div>
      </div>
      <details class="card-sub"><summary>Import from Markdown</summary>
        <p class="f-help">Paste Markdown (headings, lists, tables, quotes, code, links). A <code>## FAQ</code> section with <code>### Question</code> headings becomes the FAQ block. This replaces the content above when you save.</p>
        <textarea name="markdown" rows="8" placeholder="# Title&#10;&#10;Intro paragraph…&#10;&#10;## FAQ&#10;### How long is delivery?&#10;2–4 working days."></textarea>
      </details>
    </section>
    <section class="card"><h2 class="card-h">SEO & AI search</h2>
      <?= f_text('focus_keyword', 'Focus keyword', $pg['focus_keyword'] ?? '') ?>
      <?= f_text('seo_title', 'SEO title', $pg['seo_title'] ?? '', ['maxlength' => 120]) ?>
      <?= f_textarea('seo_description', 'Meta description', $pg['seo_description'] ?? '', ['rows' => 2, 'maxlength' => 320]) ?>
      <div class="row2"><?= f_text('slug', 'Slug', $pg['slug'] ?? '', ['help' => 'Changing it creates a 301 redirect.']) ?><?= f_select('robots', 'Indexing', ['' => 'Index', 'noindex' => 'noindex', 'noindex,nofollow' => 'noindex, nofollow'], $pg['robots'] ?? '') ?></div>
      <?= f_textarea('faq', 'FAQ', faq_to_text(jdec($pg['faq'] ?? null)), ['rows' => 5, 'help' => '<code>Q:</code> / <code>A:</code> pairs → FAQPage structured data.']) ?>
    </section>
  </div>
  <aside class="edit-side"><section class="card sticky">
    <?= f_select('status', 'Status', ['published' => 'Published', 'draft' => 'Draft'], $pg['status'] ?? 'published') ?>
    <?php if (!$page): ?><div class="field field-check"><label class="check"><input type="checkbox" name="kind" value="contact"> <span>Include contact form</span></label></div><?php endif ?>
    <button class="btn btn-block" type="submit">Save page</button>
    <?php if ($page): ?><p><a class="small" href="<?= e(rtrim(shop_public_url($shop), '/') . '/' . $page['slug'] . '/') ?>" target="_blank" rel="noopener">View page →</a></p><?php endif ?>
    <div class="analyzer" data-analyzer aria-live="polite"><div class="scores"><div class="score" data-score="seo"><strong>–</strong><span>SEO</span></div><div class="score" data-score="aeo"><strong>–</strong><span>AEO</span></div></div><ul class="checks" data-checks></ul><div data-cannibal></div></div>
    <?php if ($page): ?><button class="btn btn-danger btn-sm" type="submit" name="act" value="delete" formnovalidate data-confirm="Delete this page? Menu links to it are removed.">Delete page</button><?php endif ?>
  </section></aside>
</form>
