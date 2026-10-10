<?php
/**
 * Engine-level acceptance tests (spec §74–§80 and more). Runs on a throwaway database:
 *   php tests/acceptance.php
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$tmp = sys_get_temp_dir() . '/eshop-acceptance-' . getmypid();
@mkdir($tmp);
putenv('APP_DB=' . $tmp . '/test.sqlite');
putenv('APP_NO_MAIL=1');
require ROOT . '/app/bootstrap.php';
session_boot();

$pass = 0; $fail = 0;
function ok(bool $c, string $label): void { global $pass, $fail; if ($c) { $pass++; echo "  \e[32m✓\e[0m $label\n"; } else { $fail++; echo "  \e[31m✗ $label\e[0m\n"; } }
function section(string $t): void { echo "\n\e[1m$t\e[0m\n"; }
function throws(callable $fn, string $class = UserError::class): ?string { try { $fn(); } catch (Throwable $t) { return $t instanceof $class ? $t->getMessage() : ('UNEXPECTED ' . get_class($t) . ': ' . $t->getMessage()); } return null; }

db();
$superId = insert('users', ['email' => 'root@test.local', 'name' => 'Root', 'password_hash' => password_hash('Secret12345', PASSWORD_DEFAULT), 'role' => 'super_admin', 'status' => 'active', 'created_at' => now()]);
$_SESSION['uid'] = $superId;
$starter = row("SELECT * FROM subscription_plans WHERE slug = 'starter'");
$business = row("SELECT * FROM subscription_plans WHERE slug = 'business'");

section('Plans are data, not code');
ok((int) $starter['active_product_limit'] === 20 && (int) $business['active_product_limit'] === 50, 'Seeded plan limits come from the subscription_plans table');
$src = implode("\n", array_map('file_get_contents', array_merge(glob(APP . '/engines/*.php'), glob(APP . '/controllers/*.php'))));
ok(!preg_match('/(active_product_limit|limit)\s*[=<>]+\s*(20|50|100)\b/', $src), 'No hard-coded 20/50/100 limits in engines/controllers');

section('§74 Product limit (package = 20, import 100)');
$a = shop_provision(['name' => 'Shop A', 'email' => 'a@test.local', 'admin_email' => 'owner-a@test.local', 'admin_password' => 'Secret12345', 'plan_id' => $starter['id'], 'months' => 1, 'template_key' => 'aurora', 'domain' => 'shop-a.test']);
$shopA = shop_by_id($a['shop_id']);
$csv = "sku,name,price,stock,category,status\n";
for ($i = 1; $i <= 100; $i++) $csv .= "A-$i,Product $i,1" . $i . ".50,5,Cat > Sub" . ($i % 3) . ",\n";
file_put_contents("$tmp/p.csv", $csv);
$iid = import_upload($shopA, ['name' => 'products.csv', 'tmp_name' => "$tmp/p.csv", 'error' => 0, 'size' => strlen($csv)]);
$imp = row('SELECT * FROM imports WHERE id = ?', [$iid]);
ok((int) $imp['total_rows'] === 100 && $imp['status'] === 'validated', 'Upload → validate: 100 rows parsed');
$prev = import_preview($imp);
ok(count($prev['rows']) === 10 && $prev['rows'][0]['data']['sku'] === 'A-1', 'Preview shows auto-mapped rows');
import_queue($shopA, $iid, jdec($imp['mapping']), ['mode' => 'create_update', 'activate' => 'quota', 'create_categories' => 1]);
do { $p = import_process($iid, 5.0); } while (!in_array($p['status'], ['completed', 'failed'], true));
$q = shop_quota(shop_by_id($shopA['id']));
ok($p['status'] === 'completed' && $p['created'] === 100, 'Import completed, 100 created');
ok($q['stored'] === 100 && $q['active'] === 20 && $q['inactive'] === 80, "100 stored / 20 active / 80 inactive (got {$q['stored']}/{$q['active']}/{$q['inactive']})");
ok((int) val('SELECT COUNT(*) FROM categories WHERE shop_id = ?', [$shopA['id']]) === 4, 'Category tree auto-created from paths');
$inactiveId = (int) val("SELECT id FROM products WHERE shop_id = ? AND status = 'inactive' LIMIT 1", [$shopA['id']]);
$msg = throws(fn() => product_set_status(shop_by_id($shopA['id']), [$inactiveId], 'active'), QuotaError::class);
ok($msg !== null && str_contains($msg, 'permits up to 20') && str_contains($msg, 'currently have 20'), 'Activating the 21st product is BLOCKED with a clear message');
$activeId = (int) val("SELECT id FROM products WHERE shop_id = ? AND status = 'active' LIMIT 1", [$shopA['id']]);
product_set_status(shop_by_id($shopA['id']), [$activeId], 'inactive');
ok(product_active_count($shopA['id']) === 19, 'Deactivate one → 19 active');
product_set_status(shop_by_id($shopA['id']), [$inactiveId], 'active');
ok(product_active_count($shopA['id']) === 20, 'Activate another → SUCCESS (20 active)');
$five = col("SELECT id FROM products WHERE shop_id = ? AND status = 'inactive' LIMIT 5", [$shopA['id']]);
ok(throws(fn() => product_bulk(shop_by_id($shopA['id']), 'activate', $five), QuotaError::class) !== null && product_active_count($shopA['id']) === 20, 'Bulk activation validated against quota (all-or-nothing)');
q("UPDATE products SET status = 'active', activated_at = ? WHERE shop_id = ? AND id IN (" . in_ph($five) . ')', array_merge([now(), $shopA['id']], $five)); // simulate DB tampering
[$w, $pp] = visible_where(shop_by_id($shopA['id']));
ok((int) val("SELECT COUNT(*) FROM products p WHERE $w", $pp) === 20, 'Storefront never shows more than the quota even if the DB is tampered with');
q("UPDATE products SET status = 'inactive', activated_at = NULL WHERE id IN (" . in_ph($five) . ')', $five);
$del = col("SELECT id FROM products WHERE shop_id = ? AND status = 'inactive' LIMIT 3", [$shopA['id']]);
product_delete(shop_by_id($shopA['id']), $del);
ok((int) val('SELECT COUNT(*) FROM products WHERE shop_id = ?', [$shopA['id']]) === 100, 'Deletes are soft (rows retained with deleted_at)');

section('§70 Downgrade / upgrade');
shop_change_plan($shopA['id'], (int) $business['id']);
ok(shop_active_limit(shop_by_id($shopA['id'])) === 50, 'Upgrade to Business → limit 50 (from plan row)');
product_set_status(shop_by_id($shopA['id']), col("SELECT id FROM products WHERE shop_id = ? AND status = 'inactive' AND deleted_at IS NULL LIMIT 10", [$shopA['id']]), 'active');
$r = shop_change_plan($shopA['id'], (int) $starter['id'], 'admin_choose');
$s = shop_by_id($shopA['id']);
[$w, $pp] = visible_where($s);
ok($r['active'] === 30 && (int) $s['quota_over'] === 1 && product_active_count($s['id']) === 30, 'Downgrade (admin chooses): nothing deactivated or deleted, shop flagged');
ok((int) val("SELECT COUNT(*) FROM products p WHERE $w", $pp) === 20, '…while over quota the storefront shows only the first 20');
$r = shop_change_plan($shopA['id'], (int) $starter['id'], 'deactivate_newest');
ok($r['deactivated'] === 10 && product_active_count($shopA['id']) === 20 && (int) val('SELECT COUNT(*) FROM products WHERE shop_id = ? AND deleted_at IS NULL', [$shopA['id']]) === 97, 'Downgrade (auto): newest 10 deactivated, none deleted');

section('§75 Expiration, lock & renewal');
$before = ['p' => product_stored_count($shopA['id']), 'c' => (int) val('SELECT COUNT(*) FROM categories WHERE shop_id = ?', [$shopA['id']]), 'u' => (int) val('SELECT COUNT(*) FROM users WHERE shop_id = ?', [$shopA['id']]), 't' => shop_by_id($shopA['id'])['template_key'], 'set' => (int) val('SELECT COUNT(*) FROM shop_settings WHERE shop_id = ?', [$shopA['id']])];
update('shops', ['subscription_expires' => date('Y-m-d H:i:s', time() + 86400 - 60), 'status' => 'ACTIVE'], 'id = ?', [$shopA['id']]);
subscription_check_all();
ok(shop_by_id($shopA['id'])['status'] === 'EXPIRING', '1 day left → status EXPIRING');
ok((bool) val("SELECT 1 FROM notifications WHERE shop_id = ? AND type = 'subscription.expiring' AND dedup_key LIKE ?", [$shopA['id'], '%:1']), 'Warning notification at the 1-day threshold');
subscription_check_all();
ok((int) val("SELECT COUNT(*) FROM notifications WHERE shop_id = ? AND type = 'subscription.expiring'", [$shopA['id']]) === 1, 'Warnings are de-duplicated across cron runs');
update('shops', ['subscription_expires' => date('Y-m-d H:i:s', time() + 9 * 86400), 'status' => 'ACTIVE'], 'id = ?', [$shopA['id']]);
subscription_check_all();
ok((bool) val("SELECT 1 FROM notifications WHERE shop_id = ? AND dedup_key LIKE ?", [$shopA['id'], '%:10']), '10-day threshold warning');
update('shops', ['subscription_expires' => date('Y-m-d H:i:s', time() - 60)], 'id = ?', [$shopA['id']]);
subscription_check_all();
$s = shop_by_id($shopA['id']);
ok($s['status'] === 'LOCKED', 'Expiry reached → LOCKED');
ok((bool) val("SELECT 1 FROM subscription_history WHERE shop_id = ? AND action = 'status:expired'", [$shopA['id']]) && (bool) val("SELECT 1 FROM subscription_history WHERE shop_id = ? AND action = 'status:locked'", [$shopA['id']]), 'History records ACTIVE → EXPIRED → LOCKED');
ok(!shop_is_open($s), 'Locked shop is not open');
subscription_renew($shopA['id'], 3, null, 'reset');
$s = shop_by_id($shopA['id']);
ok($s['status'] === 'ACTIVE' && days_until($s['subscription_expires']) >= 89, 'Renew (reset timer, 3 months) → ACTIVE, expiry recalculated');
$after = ['p' => product_stored_count($shopA['id']), 'c' => (int) val('SELECT COUNT(*) FROM categories WHERE shop_id = ?', [$shopA['id']]), 'u' => (int) val('SELECT COUNT(*) FROM users WHERE shop_id = ?', [$shopA['id']]), 't' => $s['template_key'], 'set' => (int) val('SELECT COUNT(*) FROM shop_settings WHERE shop_id = ?', [$shopA['id']])];
ok($before === $after, 'Lock + renewal did not modify products, categories, users, template or settings');
ok(throws(fn() => subscription_renew($shopA['id'], 5)) !== null, 'Durations not offered by the plan are rejected');
$prevExp = $s['subscription_expires'];
subscription_renew($shopA['id'], 1, null, 'extend');
ok(shop_by_id($shopA['id'])['subscription_expires'] === add_months($prevExp, 1), 'Renew (extend) adds to the current expiry');
ok(add_months('2026-01-31 10:00:00', 1) === '2026-02-28 10:00:00', 'Month arithmetic clamps end-of-month');

section('§77 Template switch keeps data');
$o = order_seed_for_test($shopA);
$snap = fn() => [product_stored_count($shopA['id']), (int) val('SELECT COUNT(*) FROM categories WHERE shop_id = ?', [$shopA['id']]), (int) val('SELECT COUNT(*) FROM orders WHERE shop_id = ?', [$shopA['id']]), (string) shop_setting($shopA['id'], 'colors', '')];
shop_setting_set($shopA['id'], 'colors', jenc(['_template' => '*', 'primary' => '#123456']));
$b1 = $snap();
shop_change_template($shopA['id'], 'atelier');
$b2 = $snap();
ok($b1 === $b2 && shop_by_id($shopA['id'])['template_key'] === 'atelier', 'Template 01 → 07: products, categories, orders and branding untouched');
ok(shop_tokens(shop_by_id($shopA['id']))['primary'] === '#123456', 'Branding tokens apply across templates');

section('§53 Orders survive deactivation');
$pid = (int) val('SELECT product_id FROM order_items WHERE order_id = ?', [$o['id']]);
product_set_status(shop_by_id($shopA['id']), [$pid], 'inactive');
product_delete(shop_by_id($shopA['id']), [$pid]);
ok((int) val('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$o['id']]) === 1 && val('SELECT name FROM order_items WHERE order_id = ?', [$o['id']]) !== null, 'Order line kept after the product was deactivated and deleted');

section('§76 Tenant isolation (engine level)');
$b = shop_provision(['name' => 'Shop B', 'email' => 'b@test.local', 'admin_email' => 'owner-b@test.local', 'admin_password' => 'Secret12345', 'plan_id' => $starter['id'], 'months' => 1, 'template_key' => 'noir']);
$bProduct = product_save(shop_by_id($b['shop_id']), null, ['name' => 'B secret', 'price' => '10']);
ok(throws(fn() => owned('products', $bProduct, $shopA['id']), HttpError::class) === 'Access denied.', 'owned(): Shop A cannot load Shop B product (403)');
ok(throws(fn() => product_set_status(shop_by_id($shopA['id']), [$bProduct], 'active'), HttpError::class) === 'Access denied.', 'Status change on foreign product → 403');
ok(throws(fn() => product_save(shop_by_id($shopA['id']), $bProduct, ['name' => 'hack', 'price' => '1']), HttpError::class) === 'Access denied.', 'Update of foreign product → 403');
ok(val('SELECT name FROM products WHERE id = ?', [$bProduct]) === 'B secret', 'Foreign product unchanged');
ok((bool) val("SELECT 1 FROM audit_logs WHERE action = 'tenant.cross_access_denied'"), 'Cross-tenant attempts are audit-logged');

section('§78 Import 10,000 products (XML)');
$x = new XMLWriter(); $x->openUri("$tmp/big.xml"); $x->startDocument('1.0', 'UTF-8'); $x->startElement('products');
for ($i = 1; $i <= 10000; $i++) { $x->startElement('product'); $x->writeElement('sku', "X-$i"); $x->writeElement('title', "Είδος $i"); $x->writeElement('price', number_format(5 + $i / 100, 2, ',', '')); $x->writeElement('quantity', (string) ($i % 7)); $x->writeElement('category', 'Μεγάλη > Ομάδα ' . ($i % 10)); $x->endElement(); }
$x->endElement(); $x->endDocument(); $x->flush();
$c = shop_provision(['name' => 'Shop C', 'email' => 'c@test.local', 'admin_email' => 'owner-c@test.local', 'admin_password' => 'Secret12345', 'plan_id' => $business['id'], 'months' => 12, 'template_key' => 'bazaar']);
$shopC = shop_by_id($c['shop_id']);
$t0 = microtime(true);
$iid = import_upload($shopC, ['name' => 'big.xml', 'tmp_name' => "$tmp/big.xml", 'error' => 0, 'size' => filesize("$tmp/big.xml")]);
$tParse = microtime(true) - $t0;
$imp = row('SELECT * FROM imports WHERE id = ?', [$iid]);
$map = jdec($imp['mapping']);
ok($map['sku'] === 'sku' && $map['title'] === 'name' && $map['price'] === 'price' && $map['quantity'] === 'stock', 'XML columns auto-mapped (sku, title→name, price, quantity→stock)');
import_queue($shopC, $iid, $map, ['mode' => 'create_update', 'activate' => 'quota', 'create_categories' => 1]);
$calls = 0; $maxCall = 0;
do { $tc = microtime(true); $p = import_process($iid, 8.0); $maxCall = max($maxCall, microtime(true) - $tc); $calls++; } while (!in_array($p['status'], ['completed', 'failed'], true) && $calls < 200);
$total = microtime(true) - $t0;
ok($p['status'] === 'completed' && $p['created'] === 10000 && $p['errors'] === 0, "10,000 created, 0 errors (parse " . round($tParse, 1) . "s, total " . round($total, 1) . "s, $calls batch calls)");
ok($maxCall < 15, 'Every batch call stays under the HTTP time budget (max ' . round($maxCall, 1) . 's)');
ok(product_active_count($shopC['id']) === 50 && product_stored_count($shopC['id']) === 10000, 'Quota respected: 50 active / 10,000 stored');
ok((int) val('SELECT COUNT(*) FROM import_logs WHERE import_id = ?', [$iid]) >= 2, 'Import logs written');
ok(abs((float) val('SELECT price_cents FROM products WHERE shop_id = ? AND sku = ?', [$shopC['id'], 'X-150']) - 650) < 1, 'Greek decimal comma parsed (6,50 → 650 cents)');
$iid2 = import_upload($shopC, ['name' => 'big.xml', 'tmp_name' => "$tmp/big.xml", 'error' => 0, 'size' => filesize("$tmp/big.xml")]);
import_queue($shopC, $iid2, $map, ['mode' => 'create_update', 'activate' => 'none']);
do { $p = import_process($iid2, 8.0); } while (!in_array($p['status'], ['completed', 'failed'], true));
ok($p['updated'] === 10000 && $p['created'] === 0 && product_stored_count($shopC['id']) === 10000, 'Re-import: 10,000 updated by SKU, no duplicates');
ok(throws(fn() => product_save(shop_by_id($shopC['id']), null, ['name' => 'one too many', 'price' => '1']), ValidationError::class) !== null || shop_stored_limit($shopC) > 10000, 'Stored-product limit (10,000, configurable) enforced on create');

section('Redirects & SEO');
$sid = $shopA['id'];
$pid = product_save(shop_by_id($sid), null, ['name' => 'Old name', 'price' => '9']);
product_save(shop_by_id($sid), $pid, ['name' => 'Old name', 'slug' => 'new-name', 'price' => '9']);
ok(redirect_match($sid, '/product/old-name/') === [301, '/product/new-name/'], 'Slug change → automatic 301');
product_save(shop_by_id($sid), $pid, ['name' => 'Old name', 'slug' => 'newest-name', 'price' => '9']);
ok(redirect_match($sid, '/product/old-name/') === [301, '/product/newest-name/'], 'Redirect chains are flattened (A→C)');
ok(throws(fn() => redirect_save($sid, null, ['source' => '/x/', 'target' => '/x/', 'type' => 301])) !== null, 'Source = destination rejected');
redirect_save($sid, null, ['source' => '/loop-a/', 'target' => '/loop-b/', 'type' => 301]);
ok(throws(fn() => redirect_save($sid, null, ['source' => '/loop-b/', 'target' => '/loop-a/', 'type' => 301])) !== null, 'Infinite loop rejected');
redirect_save($sid, null, ['source' => '^/old-cat/(.*)$', 'target' => '/new-cat/$1', 'type' => 302, 'match_type' => 'regex']);
ok(redirect_match($sid, '/old-cat/shoes/') === [302, '/new-cat/shoes/'], 'Regex redirect with $1');
redirect_save($sid, null, ['source' => '/gone/', 'type' => 410]);
ok(redirect_match($sid, '/gone/')[0] === 410, '410 Gone supported');
not_found_log($sid, '/wp-login.php'); not_found_log($sid, '/.env'); not_found_log($sid, '/missing-page/');
ok((int) val('SELECT COUNT(*) FROM not_found_log WHERE shop_id = ?', [$sid]) === 1, '404 monitor ignores scanner noise');
$an = seo_analyze(['title' => 'Linen shirt', 'seo_title' => 'Linen shirt for summer — breathable linen', 'seo_description' => str_repeat('Linen shirt description ', 5), 'focus_keyword' => 'linen shirt', 'slug' => 'linen-shirt', 'content' => '<p>Linen shirt made of 100% linen.</p><ul><li>a</li></ul>', 'short_description' => 'A linen shirt for hot days, cut from breathable European linen.', 'faq' => "Q: Is it washable?\nA: Yes.", 'type' => 'product', 'brand' => 'X', 'sku' => 'Y', 'images_count' => 1]);
ok($an['seo'] > 40 && $an['seo'] <= 100 && $an['aeo'] > 60, "Analyzer scores 0–100 (SEO {$an['seo']}, AEO {$an['aeo']})");
update('products', ['focus_keyword' => 'λινό πουκάμισο'], 'id = ?', [$pid]);
ok(count(seo_cannibalization($sid, 'Λινό Πουκάμισο', 'page', 0)) === 1, 'Cannibalization detection is accent/case-insensitive');

section('Security helpers');
$dirty = '<p onclick="x()">Hi<script>alert(1)</script><a href="javascript:alert(1)">x</a><img src="data:image/svg+xml;base64,AAA" onerror="x"><iframe src="https://evil.com"></iframe><iframe src="https://www.youtube.com/embed/abc"></iframe></p>';
$clean = sanitize_html($dirty);
ok(!preg_match('/script|onclick|onerror|javascript:|evil\.com|data:/i', $clean) && str_contains($clean, 'youtube.com/embed/abc'), 'Sanitizer strips script/on*/javascript:/data: and non-allow-listed iframes');
$md = markdown_import("# T\n\nIntro **bold** [l](/x/)\n\n| a | b |\n|---|---|\n| 1 | 2 |\n\n## FAQ\n### Q one?\nAnswer one.\n### Q two?\nAnswer two.");
ok($md['title'] === 'T' && count($md['faq']) === 2 && str_contains($md['html'], '<table>') && str_contains($md['html'], '<strong>bold</strong>'), 'Markdown import: H1, tables, inline, FAQ extraction');
ok(count(faq_parse("Q: Είναι αυτό πραγματικό προϊόν;\nA: Όχι, είναι δείγμα.")) === 1, 'Greek FAQ parsing (UTF-8 safe line splitting)');
ok(slugify('Λινό φόρεμα midi') === 'lino-forema-midi', 'Greek slugs transliterated');
ok(to_cents('1.234,56') === 123456 && to_cents('12.50') === 1250 && to_cents('') === null, 'Price parsing (EU and US formats)');
[$okLogin] = login_attempt('owner-a@test.local', 'wrong', 'admin');
for ($i = 0; $i < 4; $i++) login_attempt('owner-a@test.local', 'wrong', 'admin');
[$okLogin, $m] = login_attempt('owner-a@test.local', 'Secret12345', 'admin');
ok(!$okLogin && str_contains($m, 'locked'), 'Account locks after 5 failed attempts (correct password refused during lockout)');

echo "\n" . ($fail ? "\e[31m$fail failed\e[0m, " : '') . "\e[32m$pass passed\e[0m\n";
rrmdir($tmp);
exit($fail ? 1 : 0);

function order_seed_for_test(array $shop): array {
    $s = shop_by_id((int) $shop['id']);
    $pid = (int) val("SELECT id FROM products WHERE shop_id = ? AND status = 'active' AND deleted_at IS NULL ORDER BY activated_at, id LIMIT 1", [$s['id']]);
    $_SESSION['cart'] = [];
    cart_add($s, $pid, 0, 1);
    return order_place($s, ['email' => 'buyer@test.local', 'name' => 'Buyer', 'phone' => '2100000000', 'address' => 'Odos 1', 'city' => 'Athens', 'postcode' => '10000', 'country' => 'GR', 'terms' => 1], null);
}
