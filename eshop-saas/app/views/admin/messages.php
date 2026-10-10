<div class="page-head"><div><h1>Messages</h1><p class="muted">Contact form submissions (spam-protected with honeypot, timing check and rate limiting).</p></div></div>
<div class="msg-list">
<?php foreach ($messages as $m): ?>
  <article class="card msg<?= $m['read_at'] ? '' : ' is-unread' ?>">
    <header><strong><?= e($m['name']) ?></strong> <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a> <?= $m['phone'] ? '· ' . e($m['phone']) : '' ?> <span class="muted small"><?= e(fmt_date($m['created_at'], true)) ?></span></header>
    <p><?= nl2br(e($m['message'])) ?></p>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><?php if (!$m['read_at']): ?><button class="btn btn-xs btn-ghost" name="act" value="read">Mark read</button><?php endif ?> <button class="btn btn-xs btn-link" name="act" value="delete" data-confirm="Delete this message?">Delete</button></form>
  </article>
<?php endforeach ?>
<?php if (!$messages): ?><p class="muted">No messages yet.</p><?php endif ?>
</div><?= pager_html($pg, '/admin/messages/') ?>
