<p class="eyebrow">Pricing</p><h2 class="h2">Simple packages</h2>
<p class="lead muted">Every package includes all templates, your own domain, hosting, SSL, orders, customers and SEO tools. Prices exclude VAT unless stated otherwise. <span class="tmpl-note">Template text — verify before going live.</span></p>
<div class="plans">
<?php foreach ($plans as $i => $p): $f = $p['features']; ?>
  <article class="plan<?= $i === 1 ? ' plan-pop' : '' ?>">
    <h3><?= e($p['name']) ?></h3>
    <p class="plan-price"><strong><?= e(money((int) $p['price_cents'])) ?></strong><span>/<?= (int) $p['billing_period_months'] === 1 ? 'month' : $p['billing_period_months'] . ' months' ?></span></p>
    <p class="plan-limit"><?= (int) $p['active_product_limit'] ?> active products</p>
    <ul>
      <li>Store up to <?= number_format((int) $p['stored_product_limit']) ?> products</li>
      <li><?= (int) $p['staff_limit'] ?> admin user<?= (int) $p['staff_limit'] > 1 ? 's' : '' ?></li>
      <li><?= (int) round($p['storage_mb'] / 1024, 1) ?> GB media storage</li>
      <li class="<?= ($f['import'] ?? '0') === '1' ? '' : 'off' ?>">CSV / XML import</li>
      <li class="<?= ($f['marketplace_feeds'] ?? '0') === '1' ? '' : 'off' ?>">Skroutz & BestPrice feeds</li>
      <li class="<?= ($f['custom_feeds'] ?? '0') === '1' ? '' : 'off' ?>">Custom XML feeds</li>
      <li class="<?= ($f['typography'] ?? '0') === '1' ? '' : 'off' ?>">Custom typography</li>
    </ul>
    <p class="small muted">One-time setup: <?= e(money((int) $p['setup_fee_cents'])) ?> · <?= e(str_replace(',', ', ', $p['duration_options'])) ?> month terms</p>
    <a class="btn btn-block<?= $i === 1 ? '' : ' btn-ghost' ?>" href="/contact/?plan=<?= e($p['slug']) ?>">Choose <?= e($p['name']) ?></a>
  </article>
<?php endforeach ?>
</div>
