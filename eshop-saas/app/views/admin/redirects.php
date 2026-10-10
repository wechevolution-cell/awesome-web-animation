<?php $x = $edit ?? []; ?>
<div class="page-head"><div><a class="back" href="/admin/seo/">← SEO</a><h1>Redirects</h1><p class="muted">301/302/307/308 redirects and 410/451 responses. Loops and chains are prevented automatically; slug changes add 301s by themselves.</p></div></div>
<section class="card"><h2 class="card-h"><?= $edit ? 'Edit redirect' : 'Add redirect' ?></h2>
<form method="post" class="redirect-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($x['id'] ?? 0) ?>">
  <?= f_text('source', 'From (path or regex)', $x['source'] ?? $prefill, ['required' => true, 'placeholder' => '/old-page/']) ?>
  <?= f_text('target', 'To', $x['target'] ?? '', ['placeholder' => '/new-page/ or https://…']) ?>
  <?= f_select('type', 'Type', array_combine(array_map('strval', REDIRECT_TYPES), ['301 Permanent', '302 Temporary', '307 Temporary (keep method)', '308 Permanent (keep method)', '410 Gone', '451 Unavailable for legal reasons']), (string) ($x['type'] ?? 301)) ?>
  <?= f_select('match_type', 'Match', ['exact' => 'Exact path', 'regex' => 'Regex ($1 = captured group)'], $x['match_type'] ?? 'exact') ?>
  <?= f_check('active', 'Active', (bool) ($x['active'] ?? 1)) ?>
  <button class="btn btn-sm" type="submit">Save</button>
</form></section>
<div class="table-wrap"><table class="table"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th>Last hit</th><th>Active</th><th></th></tr></thead><tbody>
<?php foreach ($redirects as $r): ?><tr><td class="mono small"><?= e($r['source']) ?><?= $r['match_type'] === 'regex' ? ' <span class="badge badge-muted">regex</span>' : '' ?></td><td class="mono small"><?= e($r['target'] ?? '—') ?></td><td><?= (int) $r['type'] ?></td><td><?= (int) $r['hits'] ?></td><td class="small"><?= e(fmt_date($r['last_hit_at'], true)) ?></td>
<td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="toggle<?= $r['active'] ? ' is-on' : '' ?>" name="act" value="toggle" aria-pressed="<?= $r['active'] ? 'true' : 'false' ?>" aria-label="Toggle"><span></span></button></form></td>
<td><a class="btn btn-xs btn-ghost" href="?edit=<?= (int) $r['id'] ?>">Edit</a> <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-xs btn-link" name="act" value="delete" data-confirm="Delete this redirect?">Delete</button></form></td></tr><?php endforeach ?>
<?php if (!$redirects): ?><tr><td colspan="7" class="empty-cell">No redirects.</td></tr><?php endif ?>
</tbody></table></div><?= pager_html($pg, '/admin/seo/redirects/') ?>
