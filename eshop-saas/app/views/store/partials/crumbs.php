<nav class="crumbs" aria-label="<?= e(t('breadcrumb')) ?>"><ol>
<?php $n = count($crumbs); foreach ($crumbs as $i => [$name, $url]): $rel = preg_replace('#^https?://[^/]+#', '', $url); ?>
  <li><?php if ($i < $n - 1): ?><a href="<?= e($rel) ?>"><?= e($name) ?></a><?php else: ?><span aria-current="page"><?= e($name) ?></span><?php endif ?></li>
<?php endforeach ?>
</ol></nav>
