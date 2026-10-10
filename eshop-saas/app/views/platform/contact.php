<section class="sec page-top"><div class="wrap contact">
  <div><p class="eyebrow">Contact</p><h1 class="h1">Let's set up your store</h1><p class="lead muted">Tell us what you sell and which package interests you. We reply within one business day. <span class="tmpl-note">Template text — verify before going live.</span></p>
    <ul class="contact-list"><?php if ($e = setting('contact_email')): ?><li><a href="mailto:<?= e($e) ?>"><?= e($e) ?></a></li><?php endif ?><?php if ($p = setting('contact_phone')): ?><li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $p)) ?>"><?= e($p) ?></a></li><?php endif ?></ul></div>
  <form method="post" action="/contact/" class="form">
    <?= csrf_field() ?><?= form_guard_fields() ?>
    <div class="row2"><div class="field"><label for="c-n">Name *</label><input id="c-n" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div><div class="field"><label for="c-e">Email *</label><input id="c-e" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div></div>
    <div class="row2"><div class="field"><label for="c-p">Phone</label><input id="c-p" type="tel" name="phone" autocomplete="tel" value="<?= e(old('phone')) ?>"></div>
    <div class="field"><label for="c-s">Package</label><select id="c-s" name="subject"><option value="">Not sure yet</option><?php foreach ($plans as $p): ?><option value="<?= e($p['name']) ?>" <?= get('plan') === $p['slug'] || old('subject') === $p['name'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach ?></select></div></div>
    <div class="field"><label for="c-m">Message *</label><textarea id="c-m" name="message" rows="5" required><?= e(old('message')) ?></textarea></div>
    <label class="check"><input type="checkbox" name="consent" value="1" required> <span>I agree to the processing of my data according to the <a href="/privacy/">privacy policy</a>.</span></label>
    <button class="btn btn-lg" type="submit">Send message</button>
  </form>
</div></section>
