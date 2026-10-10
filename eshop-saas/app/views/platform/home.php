<?php $min = $plans ? min(array_column($plans, 'price_cents')) : 0; $tpls = array_slice($templates, 0, 10); ?>
<section class="hero">
  <div class="wrap hero-grid">
    <div class="hero-copy">
      <p class="kicker"><span class="dot"></span> E-shop rental platform</p>
      <h1><?= e(setting('hero_title')) ?></h1>
      <p class="lead"><?= e(setting('hero_subtitle')) ?></p>
      <div class="hero-cta"><a class="btn btn-lg" href="/contact/">Request your store</a><a class="btn btn-lg btn-ghost" href="/templates/">See the 10 templates</a></div>
      <?php if ($plans): ?><p class="small muted">Packages from <?= e(money($min)) ?>/month · one-time setup fee · your own domain</p><?php endif ?>
    </div>
    <div class="hero-stack" aria-hidden="true">
      <?php foreach (array_slice($tpls, 0, 5) as $i => $t): ?><figure class="stack-card s<?= $i ?>"><img src="/assets/img/templates/<?= e($t['key']) ?>.webp" alt="" width="640" height="400" <?= $i < 2 ? 'fetchpriority="high"' : 'loading="lazy"' ?>><figcaption><?= e($t['name']) ?></figcaption></figure><?php endforeach ?>
    </div>
  </div>
</section>

<section class="strip" aria-label="Highlights"><div class="wrap strip-in">
  <p><strong>10</strong> templates</p><p><strong>10,000</strong> products stored per shop</p><p><strong>CSV & XML</strong> import</p><p><strong>Skroutz · BestPrice</strong> feeds</p><p><strong>SEO + AI search</strong> ready</p>
</div></section>

<section class="sec" id="how"><div class="wrap">
  <p class="eyebrow">How it works</p><h2 class="h2">Live in four steps</h2>
  <ol class="steps">
    <li><span>01</span><h3>Choose a template</h3><p>Ten storefront designs, each built for a kind of business. Switch at any time without losing data.</p></li>
    <li><span>02</span><h3>Connect your domain</h3><p>Use a domain you own or get a new one. Both www and non-www work, with HTTPS.</p></li>
    <li><span>03</span><h3>Add your products</h3><p>Create them by hand or import thousands from CSV/XML. Activate the ones you want to sell.</p></li>
    <li><span>04</span><h3>Start selling</h3><p>Cart, checkout, orders, customers, popups and marketplace feeds are ready from day one.</p></li>
  </ol>
</div></section>

<section class="sec sec-alt" id="features"><div class="wrap">
  <p class="eyebrow">Everything included</p><h2 class="h2">A complete store, not a page builder</h2>
  <div class="bento">
    <article class="b b-quota"><h3>Pay for what's visible</h3><p>Packages limit <em>active</em> products, not storage. Keep up to 10,000 products saved and rotate which ones are live.</p>
      <div class="q-demo" aria-hidden="true"><div class="q-top"><span>Active products</span><strong>47 / 50</strong></div><div class="q-track"><i style="width:94%"></i></div><div class="q-sub"><span>3,453 inactive</span><span>3,500 stored</span></div></div></article>
    <article class="b b-tpl"><h3>10 templates</h3><p>From Swiss-minimal to neo-brutalist. Your branding (logo, colours, contact details) applies to all of them safely.</p></article>
    <article class="b b-import"><h3>Bulk import & export</h3><p>CSV/XML with column mapping, preview, SKU matching and background processing for big catalogues.</p></article>
    <article class="b b-feeds"><h3>Marketplace feeds</h3><p>Skroutz, BestPrice and custom XML feeds that update automatically.</p></article>
    <article class="b b-seo"><h3>SEO & AI search</h3><p>Clean URLs, sitemap, structured data (Product, Offer, Breadcrumb, FAQ), llms.txt and a live SEO/AEO analyzer.</p></article>
    <article class="b b-team"><h3>Your team</h3><p>Staff accounts with per-module permissions: editors, product managers, administrators.</p></article>
  </div>
</div></section>

<section class="sec" id="templates"><div class="wrap">
  <div class="sec-head"><div><p class="eyebrow">Designs</p><h2 class="h2">Pick a look. Keep your data.</h2></div><a class="link" href="/templates/">All templates →</a></div>
  <div class="tpl-rail">
    <?php foreach ($tpls as $t): ?><a class="tpl" href="<?= $t['demo'] ? '/s/' . e($t['demo']) . '/' : '/templates/' ?>"<?= $t['demo'] ? ' target="_blank" rel="noopener"' : '' ?>><img src="/assets/img/templates/<?= e($t['key']) ?>.webp" alt="<?= e($t['name']) ?> template preview" width="640" height="400" loading="lazy"><span class="tpl-meta"><strong><?= e($t['name']) ?></strong><span><?= e($t['style']) ?></span></span></a><?php endforeach ?>
  </div>
</div></section>

<section class="sec sec-alt" id="pricing"><div class="wrap"><?php partial('platform/partials/plans', ['plans' => $plans]) ?></div></section>

<section class="sec" id="faq"><div class="wrap narrow">
  <p class="eyebrow">FAQ</p><h2 class="h2">Questions, answered</h2>
  <?php foreach (platform_faq() as $f): ?><details class="qa"><summary><?= e($f['q']) ?></summary><p><?= e($f['a']) ?></p></details><?php endforeach ?>
</div></section>

<section class="cta"><div class="wrap cta-in"><h2>Ready to open your store?</h2><p>Tell us about your business and we'll set it up for you.</p><a class="btn btn-lg btn-light" href="/contact/">Contact us</a></div></section>
