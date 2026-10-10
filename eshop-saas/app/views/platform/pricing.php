<section class="sec page-top"><div class="wrap"><?php partial('platform/partials/plans', ['plans' => $plans]) ?></div></section>
<section class="sec sec-alt"><div class="wrap narrow"><h2 class="h2">How the active-product limit works</h2>
<p>Your package sets how many products can be <strong>visible in the store at the same time</strong>. It does not limit storage: every shop can keep up to 10,000 products. Example on a 50-product package: 3,500 stored, 50 active, 3,450 inactive. When you deactivate one product, a slot frees up instantly for another.</p>
<?php foreach (array_slice(platform_faq(), 0, 3) as $f): ?><details class="qa"><summary><?= e($f['q']) ?></summary><p><?= e($f['a']) ?></p></details><?php endforeach ?>
</div></section>
