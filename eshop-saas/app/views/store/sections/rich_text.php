<?php if (!empty($d['html']) || !empty($d['title'])): ?>
<section class="sec sec-rich"><div class="wrap prose"><?php if (!empty($d['title'])): ?><h2><?= e($d['title']) ?></h2><?php endif ?><?= $d['html'] ?? '' ?></div></section>
<?php endif ?>
