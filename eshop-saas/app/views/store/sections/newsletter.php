<section class="sec sec-news" aria-labelledby="sn-<?= (int) $s['id'] ?>">
  <div class="wrap news">
    <div><h2 id="sn-<?= (int) $s['id'] ?>"><?= e($d['title'] ?: t('newsletter')) ?></h2><?php if (!empty($d['text'])): ?><p><?= e($d['text']) ?></p><?php endif ?></div>
    <form method="post" action="<?= e(su('/popup/subscribe/')) ?>" data-ajax-subscribe>
      <?= csrf_field() ?><?= form_guard_fields() ?><input type="hidden" name="source" value="homepage">
      <label class="sr" for="nl-email"><?= e(t('email')) ?></label>
      <div class="inline-form"><input id="nl-email" type="email" name="email" required placeholder="<?= e(t('email')) ?>" autocomplete="email"><button class="btn" type="submit"><?= e(t('subscribe')) ?></button></div>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(t('consent_privacy')) ?></span></label>
      <p class="form-msg" data-form-msg></p>
    </form>
  </div>
</section>
