<?php $titles = ['privacy' => 'Privacy policy', 'terms' => 'Terms of service', 'cookies' => 'Cookie policy']; ?>
<section class="sec page-top"><div class="wrap narrow prose">
  <h1 class="h1"><?= e($titles[$doc]) ?></h1>
  <p class="tmpl-note">Template text — verify before going live. Have this page reviewed by a legal professional.</p>
  <?php if ($doc === 'privacy'): ?>
    <p>This page explains which personal data <?= e(setting('company_name') ?: setting('platform_name')) ?> processes when you use this website and the contact form, why, and what your rights are under the GDPR.</p>
    <h2>Data we collect</h2><ul><li>Contact form: name, email, phone (optional), message, consent timestamp.</li><li>Technical data: a hashed IP address for rate limiting and spam prevention.</li><li>Analytics (only after consent): Google Analytics 4.</li></ul>
    <h2>Your rights</h2><p>You can request access, correction or deletion of your data by contacting us.</p>
  <?php elseif ($doc === 'terms'): ?>
    <p>These terms govern the rental of online stores provided through this platform: packages, setup fee, subscription periods, renewal, suspension on expiry (data retained) and termination.</p>
    <h2>Subscription & expiry</h2><p>When a subscription expires, the store is temporarily locked. Data is kept until the subscription is renewed or the account is permanently closed.</p>
  <?php else: ?>
    <p>We use strictly necessary cookies (session, security token). Analytics cookies are set only after you accept them in the cookie banner.</p>
    <table><thead><tr><th>Cookie</th><th>Purpose</th><th>Duration</th></tr></thead><tbody><tr><td>sid</td><td>Session & security</td><td>Session</td></tr><tr><td>_ga, _ga_*</td><td>Analytics (with consent)</td><td>Up to 2 years</td></tr></tbody></table>
  <?php endif ?>
</div></section>
