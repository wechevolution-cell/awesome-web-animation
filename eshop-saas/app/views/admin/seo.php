<div class="page-head"><div><h1>SEO</h1><p class="muted">Search engine & AI search settings for your store.</p></div><div class="page-actions"><a class="btn btn-ghost btn-sm" href="/admin/seo/redirects/">Redirects (<?= $diag['redirects'] ?>)</a> <a class="btn btn-ghost btn-sm" href="/admin/seo/404/">404 monitor (<?= $diag['n404'] ?>)</a></div></div>
<div class="edit-grid">
<form method="post" class="edit-main"><?= csrf_field() ?>
  <section class="card"><h2 class="card-h">Basics</h2>
    <div class="row2"><?= f_text('site_title', 'Site title', $s['site_title'] ?? '') ?><?= f_select('title_separator', 'Title separator', [' | ' => '|', ' — ' => '—', ' - ' => '-', ' · ' => '·'], $s['title_separator'] ?? ' | ') ?></div>
    <?= f_textarea('meta_description', 'Default meta description', $s['meta_description'] ?? '', ['rows' => 2, 'maxlength' => 320]) ?>
    <?= f_media('og_image_id', 'Default social share image (1200×630)', isset($s['og_image_id']) ? (int) $s['og_image_id'] : null, (int) $shop['id']) ?>
  </section>
  <section class="card"><h2 class="card-h">Entity & verification</h2>
    <?= f_select('organization_type', 'Business type (schema.org)', ['OnlineStore' => 'Online store', 'Store' => 'Store', 'LocalBusiness' => 'Local business', 'Organization' => 'Organization'], $s['organization_type'] ?? 'OnlineStore') ?>
    <?= f_textarea('same_as', 'Official profiles (sameAs, one https:// URL per line)', $s['same_as'] ?? '', ['rows' => 3]) ?>
    <div class="row2"><?= f_text('gsc_verification', 'Google Search Console verification code', $s['gsc_verification'] ?? '', ['help' => 'Only the content value of the meta tag.']) ?><?= f_text('ga4_id', 'GA4 Measurement ID', $s['ga4_id'] ?? '', ['placeholder' => 'G-XXXXXXX', 'help' => 'Loaded only after the visitor accepts cookies.']) ?></div>
  </section>
  <section class="card"><h2 class="card-h">Crawling</h2>
    <?= f_check('noindex_site', 'Hide the whole store from search engines (noindex)', (bool) ($s['noindex_site'] ?? 0)) ?>
    <?= f_textarea('robots_extra', 'Extra robots.txt rules', $s['robots_extra'] ?? '', ['rows' => 3]) ?>
    <?= f_textarea('llms_extra', 'Extra llms.txt content (facts about your business for AI assistants)', $s['llms_extra'] ?? '', ['rows' => 4]) ?>
    <button class="btn" type="submit">Save SEO settings</button>
  </section>
</form>
<aside class="edit-side">
  <section class="card"><h2 class="card-h">Diagnostics</h2><ul class="health">
    <li class="<?= $s['meta_description'] ? 'ok' : 'warn' ?>">Default meta description <?= $s['meta_description'] ? 'set' : 'missing' ?></li>
    <li class="<?= $s['gsc_verification'] ? 'ok' : 'warn' ?>">Search Console <?= $s['gsc_verification'] ? 'verified tag set' : 'not configured' ?></li>
    <li class="<?= $s['same_as'] ? 'ok' : 'warn' ?>">sameAs profiles <?= $s['same_as'] ? 'set' : 'missing' ?></li>
    <li class="<?= $diag['products_no_desc'] ? 'warn' : 'ok' ?>"><?= $diag['products_no_desc'] ?> active products without meta description</li>
    <li class="<?= $diag['products_no_kw'] ? 'warn' : 'ok' ?>"><?= $diag['products_no_kw'] ?> active products without focus keyword</li>
    <li class="<?= $diag['cats_no_desc'] ? 'warn' : 'ok' ?>"><?= $diag['cats_no_desc'] ?> categories without description</li>
  </ul></section>
  <section class="card"><h2 class="card-h">Live files</h2><ul class="link-list small">
    <li><a href="<?= e($base) ?>/sitemap.xml" target="_blank" rel="noopener">sitemap.xml</a></li><li><a href="<?= e($base) ?>/robots.txt" target="_blank" rel="noopener">robots.txt</a></li><li><a href="<?= e($base) ?>/llms.txt" target="_blank" rel="noopener">llms.txt</a></li><li><a href="<?= e($base) ?>/feed/rss.xml" target="_blank" rel="noopener">RSS</a></li>
  </ul><p class="small muted">Submit the sitemap in Google Search Console after connecting your domain.</p></section>
</aside>
</div>
