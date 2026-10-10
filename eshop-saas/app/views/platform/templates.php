<section class="sec page-top"><div class="wrap">
  <p class="eyebrow">Templates</p><h1 class="h1">Ten storefront designs</h1>
  <p class="lead muted">Each template is a complete store: homepage, category pages with filters, product pages with variants, cart, checkout and account. Switching template keeps all your products, orders and settings.</p>
  <div class="tpl-grid">
  <?php foreach ($templates as $t): $m = template_get($t['key']); ?>
    <article class="tpl-card">
      <img src="/assets/img/templates/<?= e($t['key']) ?>.webp" alt="<?= e($t['name']) ?> template homepage" width="640" height="400" loading="lazy">
      <div class="tpl-body"><h2><?= e($t['name']) ?></h2><p class="tpl-style"><?= e($t['style']) ?></p><p><?= e($t['description']) ?></p>
        <p class="sw"><?php foreach (['primary', 'accent', 'background', 'text'] as $k): ?><span style="background:<?= e($m['tokens'][$k]) ?>"></span><?php endforeach ?></p>
        <?php if ($t['demo']): ?><a class="btn btn-sm btn-ghost" href="/s/<?= e($t['demo']) ?>/" target="_blank" rel="noopener">Open live demo ↗</a><?php endif ?></div>
    </article>
  <?php endforeach ?>
  </div>
</div></section>
