<?php if (!empty($faq)): ?>
<section class="faq" aria-labelledby="faq-h">
  <h2 id="faq-h"><?= e(t('faq')) ?></h2>
  <?php foreach ($faq as $f): ?>
  <details><summary><?= e($f['q']) ?></summary><div class="faq-a"><?= nl2br(e($f['a'])) ?></div></details>
  <?php endforeach ?>
</section>
<?php endif ?>
