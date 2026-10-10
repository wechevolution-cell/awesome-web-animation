<div class="wrap narrow err">
  <p class="err-code"><?= (int) $code ?></p>
  <h1><?= e($code === 404 ? t('page_not_found') : ($code === 410 || $code === 451 ? t('gone') : ($message ?: 'Error'))) ?></h1>
  <p class="lead"><?= e($code === 404 ? t('page_not_found_text') : $message) ?></p>
  <form class="search search-lg" action="<?= e(su('/search/')) ?>" method="get" role="search"><label class="sr" for="eq"><?= e(t('search')) ?></label><input id="eq" type="search" name="q" placeholder="<?= e(t('search_placeholder')) ?>"><button type="submit" aria-label="<?= e(t('search')) ?>"><?= icon('search') ?></button></form>
  <p><a class="btn" href="<?= e(su('/')) ?>"><?= e(t('back_home')) ?></a></p>
</div>
