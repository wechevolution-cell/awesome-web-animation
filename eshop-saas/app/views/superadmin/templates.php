<div class="page-head"><div><h1>Templates</h1><p class="muted">10 built-in storefront templates. A template is presentation only: switching never touches shop data.</p></div></div>
<div class="tpl-cards tpl-cards-admin">
<?php foreach ($templates as $t): $m = template_get($t['key']); ?>
  <article class="tpl-card card">
    <span class="tpl-thumb"><img src="/assets/img/templates/<?= e($t['key']) ?>.webp" alt="<?= e($t['name']) ?> preview" width="320" height="200" loading="lazy"></span>
    <h2 class="tpl-name"><?= e($t['name']) ?> <?= $t['status'] !== 'active' ? '<span class="badge badge-muted">disabled</span>' : '' ?></h2>
    <p class="small muted"><?= e($t['style']) ?> — <?= e($t['description']) ?></p>
    <p class="small">Header: <?= e($m['header']) ?> · Hero: <?= e($m['hero']) ?> · Cards: <?= e($m['card']) ?> · Product page: <?= e($m['product']) ?></p>
    <p class="tpl-swatches"><?php foreach (['primary', 'accent', 'background', 'text'] as $k): ?><span style="background:<?= e($m['tokens'][$k]) ?>" title="<?= $k ?> <?= e($m['tokens'][$k]) ?>"></span><?php endforeach ?></p>
    <p class="small"><?= (int) $t['shops'] ?> shop(s)<?php if ($t['demo']): ?> · <a href="/s/<?= e($t['demo']) ?>/" target="_blank" rel="noopener">Live demo ↗</a><?php endif ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($t['key']) ?>"><button class="btn btn-xs btn-ghost" name="status" value="<?= $t['status'] === 'active' ? 'disabled' : 'active' ?>"><?= $t['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form>
  </article>
<?php endforeach ?>
</div>
