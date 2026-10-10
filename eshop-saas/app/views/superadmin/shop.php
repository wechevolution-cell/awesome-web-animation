<?php $sid = (int) $shop['id']; $primary = $domains[0]['domain'] ?? null; $days = days_until($shop['subscription_expires']); $deleted = (bool) $shop['deleted_at']; ?>
<div class="page-head">
  <div><a class="back" href="/superadmin/shops/">← Shops</a><h1><?= e($shop['name']) ?> <?= status_badge($deleted ? 'DELETED' : $shop['status']) ?></h1>
  <p class="muted"><?= e($primary ?: '/s/' . $shop['slug'] . '/') ?> · <?= e($plan['name'] ?? '—') ?> · <?= e(template_get($shop['template_key'])['name']) ?> · created <?= e(fmt_date($shop['created_at'])) ?></p></div>
  <div class="page-actions">
    <?php if (!$deleted): ?>
    <a class="btn btn-ghost btn-sm" href="/s/<?= e($shop['slug']) ?>/" target="_blank" rel="noopener"><?= icon('external', 16) ?> Storefront</a>
    <form method="post" action="/superadmin/impersonate/<?= $sid ?>/"><?= csrf_field() ?><button class="btn btn-sm"><?= icon('eye', 16) ?> Login as Shop Admin</button></form>
    <?php endif ?>
  </div>
</div>
<?php if ($invite): ?><div class="notice notice-ok">Owner "set password" link (valid 7 days, also emailed): <code data-copy><?= e($invite) ?></code></div><?php endif ?>
<div class="kpis kpis-top">
  <div class="kpi card"><span>Expires</span><strong><?= e(fmt_date($shop['subscription_expires'])) ?></strong><small class="<?= $days !== null && $days <= 3 ? 'txt-bad' : '' ?>"><?= $days === null ? '' : ($days >= 0 ? "$days days left" : abs($days) . ' days ago') ?></small></div>
  <div class="kpi card"><span>Active / limit</span><strong><?= $q['active'] ?> / <?= $q['limit'] ?></strong><small><?= number_format($q['inactive']) ?> inactive</small></div>
  <div class="kpi card"><span>Stored products</span><strong><?= number_format($q['stored']) ?></strong><small>of <?= number_format($q['stored_limit']) ?></small></div>
  <div class="kpi card"><span>Orders · customers</span><strong><?= $counts['orders'] ?> · <?= $counts['customers'] ?></strong></div>
</div>
<div class="edit-grid">
<div class="edit-main">
  <?php if (!$deleted): ?>
  <section class="card" id="renew"><h2 class="card-h">Renew / reset timer</h2>
    <p class="small muted">Records a renewal in the history, recalculates the expiry date and sets the shop ACTIVE. It never modifies products, categories, templates, users, settings, orders or customers.</p>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="act" value="renew">
      <div class="row3">
        <?= f_select('plan_id', 'Package', array_column($plans, 'name', 'id'), (string) $shop['plan_id']) ?>
        <?= f_select('months', 'Duration', ['1' => '1 month', '3' => '3 months', '6' => '6 months', '12' => '12 months'], (string) ($shop['subscription_months'] ?: 1)) ?>
        <?= f_select('mode', 'Start from', ['extend' => 'Current expiry (extend)', 'reset' => 'Today (reset timer)'], $days !== null && $days > 0 ? 'extend' : 'reset') ?>
      </div>
      <div class="row3"><?= f_select('payment_provider', 'Record payment', ['' => '— none —'] + array_column($providers, 'name', 'key'), '') ?><?= f_text('payment_amount', 'Amount (€, empty = package price)', '') ?><?= f_text('payment_reference', 'Reference', '') ?></div>
      <?= f_check('payment_paid', 'Payment received', true) ?>
      <button class="btn" type="submit"><?= icon('clock', 16) ?> Renew</button>
    </form>
  </section>
  <section class="card split-2 inner">
    <div><h2 class="card-h">Change package</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="act" value="change_plan">
        <?= f_select('plan_id', 'New package', array_map(static fn($p) => $p['name'] . ' (' . $p['active_product_limit'] . ' active)', array_column($plans, null, 'id')), (string) $shop['plan_id']) ?>
        <?= f_select('strategy', 'If active products exceed the new limit', ['admin_choose' => 'Let the shop admin choose (store shows first N meanwhile)', 'deactivate_newest' => 'Deactivate most recently activated now'], 'admin_choose', ['help' => 'Products are never deleted.']) ?>
        <?= f_text('note', 'Note', '') ?>
        <button class="btn btn-sm" type="submit" data-confirm="Change the package of this shop?">Change package</button>
      </form>
    </div>
    <div><h2 class="card-h">Template</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="act" value="change_template">
        <?= f_select('template_key', 'Template', array_map(static fn($t) => $t['name'] . ' — ' . $t['style'], array_column($templates, null, 'key')), $shop['template_key']) ?>
        <p class="small"><a href="/s/<?= e($shop['slug']) ?>/?preview_template=<?= e($shop['template_key']) ?>" target="_blank" rel="noopener" data-preview-link>Preview with this shop's data ↗</a></p>
        <button class="btn btn-sm" type="submit">Switch template</button>
        <p class="f-help">Presentation only — no data changes.</p>
      </form>
    </div>
  </section>
  <section class="card"><h2 class="card-h">Domains</h2>
    <table class="table"><thead><tr><th>Domain</th><th>Primary</th><th>DNS</th><th>SSL</th><th>Hosting</th><th>Checked</th><th></th></tr></thead><tbody>
    <?php foreach ($domains as $d): ?><tr><td class="strong"><?= e($d['domain']) ?></td><td><?= $d['is_primary'] ? '★' : '' ?></td><td><span class="badge badge-<?= $d['dns_status'] === 'ok' ? 'ok' : 'warn' ?>"><?= e($d['dns_status']) ?></span></td><td><span class="badge badge-<?= $d['ssl_status'] === 'ok' ? 'ok' : 'warn' ?>"><?= e($d['ssl_status']) ?></span></td><td><?= e($d['hosting_status']) ?></td><td class="small"><?= e(fmt_date($d['last_checked_at'], true)) ?></td>
    <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="domain_id" value="<?= (int) $d['id'] ?>"><button class="btn btn-xs btn-ghost" name="act" value="check_domain">Check DNS/SSL</button><?php if (!$d['is_primary']): ?><button class="btn btn-xs btn-ghost" name="act" value="primary_domain">Make primary</button><?php endif ?><button class="btn btn-xs btn-link" name="act" value="remove_domain" data-confirm="Remove <?= e($d['domain']) ?>?">Remove</button></form></td></tr><?php endforeach ?>
    <?php if (!$domains): ?><tr><td colspan="7" class="muted">No custom domain. Fallback URL: /s/<?= e($shop['slug']) ?>/</td></tr><?php endif ?>
    </tbody></table>
    <form method="post" class="inline-form-row"><?= csrf_field() ?><input type="hidden" name="act" value="add_domain"><label for="nd">Add domain</label><input id="nd" name="domain" placeholder="example.gr" required><label class="check"><input type="checkbox" name="primary" value="1"> primary</label><button class="btn btn-sm">Add</button></form>
  </section>
  <?php endif ?>
  <section class="card"><h2 class="card-h">Subscription history</h2>
    <table class="table small"><thead><tr><th>Date</th><th>Action</th><th>Package</th><th>Months</th><th>Start</th><th>End</th><th>By</th><th>Note</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?><tr><td><?= e(fmt_date($h['created_at'], true)) ?></td><td><span class="badge badge-muted"><?= e($h['action']) ?></span></td><td><?= e($h['plan_name']) ?></td><td><?= e($h['months']) ?></td><td><?= e(fmt_date($h['starts_at'])) ?></td><td><?= e(fmt_date($h['expires_at'])) ?></td><td><?= e($h['actor_email']) ?></td><td><?= e($h['note']) ?></td></tr><?php endforeach ?>
    </tbody></table>
  </section>
  <?php if ($payments): ?><section class="card"><h2 class="card-h">Payments</h2><table class="table small"><thead><tr><th>Date</th><th>Provider</th><th>Purpose</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead><tbody><?php foreach ($payments as $p): ?><tr><td><?= e(fmt_date($p['created_at'])) ?></td><td><?= e($p['provider_key']) ?></td><td><?= e($p['purpose']) ?></td><td><?= e(money((int) $p['amount_cents'])) ?></td><td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td><td><?= e($p['reference']) ?></td></tr><?php endforeach ?></tbody></table></section><?php endif ?>
  <section class="card"><h2 class="card-h">Recent activity</h2><table class="table small"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Outcome</th><th>IP</th></tr></thead><tbody><?php foreach ($audit as $a): ?><tr><td><?= e(fmt_date($a['created_at'], true)) ?></td><td><?= e($a['actor_email']) ?><?= $a['impersonator_id'] ? ' <span class="badge badge-warn">impersonated</span>' : '' ?></td><td><?= e($a['action']) ?></td><td><span class="badge badge-<?= $a['outcome'] === 'success' ? 'ok' : 'bad' ?>"><?= e($a['outcome']) ?></span></td><td class="mono"><?= e($a['ip']) ?></td></tr><?php endforeach ?></tbody></table><a class="small" href="/superadmin/audit/?shop_id=<?= $sid ?>">Full audit log →</a></section>
</div>
<aside class="edit-side">
  <section class="card"><h2 class="card-h">Status</h2>
    <?php if ($deleted): ?>
      <p class="small">Deleted <?= e(fmt_date($shop['deleted_at'], true)) ?>. Data retained until purged.</p>
      <form method="post"><?= csrf_field() ?><button class="btn btn-sm" name="act" value="restore">Restore shop</button></form>
      <form method="post" class="stack danger-zone"><?= csrf_field() ?><input type="hidden" name="act" value="purge"><label for="pc">Type <code>PURGE <?= e($shop['slug']) ?></code> to permanently delete all data (a full backup is taken first)</label><input id="pc" name="confirm" autocomplete="off"><button class="btn btn-danger btn-sm">Purge permanently</button></form>
    <?php else: ?>
      <div class="btn-col">
        <?php if (in_array($shop['status'], ['LOCKED', 'SUSPENDED', 'PENDING', 'EXPIRED'], true)): ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-block" name="act" value="activate"><?= icon('check', 14) ?> Reactivate</button></form><?php endif ?>
        <?php if ($shop['status'] !== 'LOCKED'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="note" value=""><button class="btn btn-sm btn-ghost btn-block" name="act" value="lock" data-confirm="Lock this shop? Visitors will see the lock page. No data is removed."><?= icon('lock', 14) ?> Lock now</button></form><?php endif ?>
        <?php if ($shop['status'] !== 'SUSPENDED'): ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-ghost btn-block" name="act" value="suspend" data-confirm="Suspend this shop?">Suspend</button></form><?php endif ?>
      </div>
      <form method="post" class="stack danger-zone"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><label for="dc">Type <code><?= e($primary ?: $shop['slug']) ?></code> to delete this shop</label><input id="dc" name="confirm" autocomplete="off"><button class="btn btn-danger btn-sm">Delete shop</button><p class="f-help">Soft delete — can be restored.</p></form>
    <?php endif ?>
  </section>
  <?php if (!$deleted): ?>
  <section class="card"><h2 class="card-h">Client details</h2>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="act" value="update_details">
      <?= f_text('name', 'Shop name', $shop['name']) ?><?= f_text('company', 'Company', $shop['company']) ?><?= f_text('vat_number', 'VAT number', $shop['vat_number']) ?><?= f_text('owner_name', 'Owner', $shop['owner_name']) ?><?= f_text('email', 'Email', $shop['email'], ['type' => 'email']) ?><?= f_text('phone', 'Phone', $shop['phone']) ?>
      <?= f_textarea('hosting_notes', 'Hosting notes', $shop['hosting_notes'], ['rows' => 2]) ?><?= f_textarea('notes', 'Internal notes', $shop['notes'], ['rows' => 2]) ?>
      <button class="btn btn-sm">Save details</button>
    </form>
  </section>
  <section class="card"><h2 class="card-h">Limit overrides</h2>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="act" value="limits">
      <?= f_text('active_limit_override', 'Active product limit (empty = package)', $shop['active_limit_override'] !== null ? (string) $shop['active_limit_override'] : '', ['type' => 'number', 'min' => 0]) ?>
      <?= f_text('stored_limit_override', 'Stored product limit (empty = package)', $shop['stored_limit_override'] !== null ? (string) $shop['stored_limit_override'] : '', ['type' => 'number', 'min' => 1]) ?>
      <button class="btn btn-sm btn-ghost">Save limits</button>
    </form>
  </section>
  <section class="card"><h2 class="card-h">Users</h2><ul class="small"><?php foreach ($users as $u): ?><li><?= e($u['email']) ?> — <?= e($u['role']) ?><?= $u['password_hash'] ? '' : ' <span class="badge badge-warn">invited</span>' ?></li><?php endforeach ?></ul>
    <form method="post"><?= csrf_field() ?><button class="btn btn-xs btn-ghost" name="act" value="invite">New owner password link</button></form></section>
  <?php endif ?>
</aside>
</div>
