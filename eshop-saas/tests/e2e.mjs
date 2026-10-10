// Browser end-to-end test on a FRESH install. Usage: node tests/e2e.mjs http://127.0.0.1:8001 <screenshotDir>
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PW_PATH || 'playwright');
const fs = require('fs');
const BASE = process.argv[2] || 'http://127.0.0.1:8001';
const SHOTS = process.argv[3] || 'shots';
const PORT = new URL(BASE).port;
fs.mkdirSync(SHOTS, { recursive: true });
let pass = 0, fail = 0; const errors = [];
const ok = (c, l) => { c ? pass++ : fail++; console.log(`${c ? '  ✓' : '  ✗'} ${l}`); };
const step = (t) => console.log(`\n${t}`);

const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP shop-a.test 127.0.0.1, MAP www.shop-a.test 127.0.0.1'] });
const newCtx = async (vp = { width: 1280, height: 860 }) => {
  const ctx = await browser.newContext({ viewport: vp, reducedMotion: 'reduce' });
  ctx.on('page', (p) => { p.on('pageerror', (e) => errors.push(`${p.url()} ${e.message}`)); p.on('console', (m) => { if (m.type() === 'error' && !/favicon|ERR_NAME|googletagmanager|status of 503/.test(m.text())) errors.push(`${p.url()} ${m.text()}`); }); });
  return ctx;
};
const hreq = (ctx, method, host, path, opts = {}) => ctx.request[method](BASE + path, { ...opts, headers: { ...(opts.headers || {}), Host: `${host}:${PORT}` } });
const shot = (page, name, full = true) => page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: full });
const fill = async (page, sel, v) => page.fill(sel, v);

/* 1. Setup wizard */
step('Setup wizard (no default credentials)');
const sa = await newCtx();
let p = await sa.newPage();
await p.goto(BASE + '/');
ok(p.url().endsWith('/setup/'), 'Fresh install redirects to /setup/');
await fill(p, '#pn', 'TestRent'); await fill(p, '#em', 'root@e2e.test'); await fill(p, '#pw', 'Secret12345'); await fill(p, '#pw2', 'Secret12345');
await p.uncheck('input[name=demo]');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
ok(p.url().endsWith('/superadmin/'), 'Setup creates the Super Admin and signs in');
await shot(p, '01-superadmin-dashboard');
const again = await p.request.get(BASE + '/setup/', { maxRedirects: 0 });
ok(again.status() === 404, 'Setup is unreachable after install (404)');

/* 2. Shop creation wizard */
step('Shop creation wizard');
const createShop = async (name, slug, domain, plan, tpl, adminEmail) => {
  await p.goto(BASE + '/superadmin/shops/new/');
  await fill(p, '#f-name', name); await fill(p, '#f-email', `${slug}@e2e.test`);
  await p.click('[data-wiz-next]');
  if (domain) await fill(p, '#f-domain', domain);
  await fill(p, '#f-slug', slug);
  await p.click('[data-wiz-next]');
  await p.check(`.plan-card:has-text("${plan}") input`); await p.click('[data-wiz-next]');
  await p.click('[data-wiz-next]');
  await p.check(`input[name=template_key][value=${tpl}]`); await p.click('[data-wiz-next]');
  await fill(p, '#f-admin-email', adminEmail); await p.click('[data-wiz-next]');
  if (name === 'Shop A') await shot(p, '02-wizard-review', false);
  await Promise.all([p.waitForNavigation(), p.click('[data-wiz-submit]')]);
  const invite = await p.locator('code[data-copy]').first().textContent();
  return { id: p.url().match(/shops\/(\d+)/)[1], invite: invite.trim() };
};
const A = await createShop('Shop A', 'shop-a', 'shop-a.test', 'Starter', 'aurora', 'owner@shop-a.test');
ok(/set-password\/\?token=/.test(A.invite), 'Provisioned shop A with a one-time "set password" link (no plaintext password)');
await shot(p, '03-superadmin-shop');
const B = await createShop('Shop B', 'shop-b', '', 'Business', 'volt', 'owner@shop-b.test');
ok(!!B.id, 'Provisioned shop B (no domain → /s/shop-b/)');

/* 2b. Main website: blank until the Super Admin activates a storefront */
step('Main website (blank → activated storefront)');
const visitor = await newCtx();
const pv = await visitor.newPage();
let rb = await pv.goto(BASE + '/');
ok(rb.status() === 200 && (await pv.locator('main').innerHTML()).trim() === '' && await pv.locator('a').count() === 0, 'Main domain is a blank page (no promotional content)');
ok((await visitor.request.get(BASE + '/pricing/')).status() === 404, 'No marketing pages exist');
ok((await (await visitor.request.get(BASE + '/robots.txt')).text()).includes('Disallow: /'), 'Blank main site is not indexed');
await p.goto(BASE + '/superadmin/');
ok(await p.locator('#main-site .badge:has-text("Blank")').count() === 1, 'Super Admin overview shows "Main website: Blank"');
await p.selectOption('#ms-shop', String(B.id));
await p.check('#main-site input[name=template_key][value=maison]');
await shot(p, '02b-main-site-panel', false);
await Promise.all([p.waitForNavigation(), p.click('#main-site button[type=submit]')]);
ok(await p.locator('#main-site .badge:has-text("Active")').count() === 1, 'Activated Shop B on the main website with the Maison template');
rb = await pv.goto(BASE + '/');
ok(rb.status() === 200 && (await pv.title()).includes('Shop B') && await pv.locator('html.tpl-maison').count() === 1, 'Root now serves the Shop B storefront in the chosen template');
ok((await visitor.request.get(BASE + '/products/')).status() === 200 && (await visitor.request.get(BASE + '/cart/')).status() === 200, 'Clean storefront URLs at the root (/products/, /cart/)');
const canon = await visitor.request.get(BASE + '/s/shop-b/products/', { maxRedirects: 0 });
ok(canon.status() === 301 && canon.headers().location === '/products/', '/s/shop-b/… 301 → root (one canonical URL)');
ok((await visitor.request.get(BASE + '/admin/login/')).status() === 200 && (await visitor.request.get(BASE + '/superadmin/login/')).status() === 200, 'Admin panels stay on the same domain');
await shot(pv, '02c-main-site-active', false);
await p.goto(BASE + '/superadmin/');
p.once('dialog', (d) => d.accept());
await Promise.all([p.waitForNavigation(), p.click('#main-site button[value=deactivate]')]);
ok((await pv.goto(BASE + '/')).status() === 200 && (await pv.locator('main').innerHTML()).trim() === '', 'Deactivate → main domain blank again');
await p.goto(BASE + `/superadmin/shops/${B.id}/`);
p.once('dialog', (d) => d.accept());
await Promise.all([p.waitForNavigation(), p.click('button:has-text("Show on main website")')]);
ok(await p.locator('.badge:has-text("On main website")').count() === 1, '"Show on main website" from the shop page');

/* 3. Owner sets password & logs in */
step('Shop admin onboarding');
const adminA = await newCtx();
const pa = await adminA.newPage();
const setPw = async (page, link) => { await page.goto(link.replace(/^https?:\/\/[^/]+/, BASE)); await fill(page, '#password', 'OwnerPass123'); await fill(page, '#password2', 'OwnerPass123'); await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]); };
const login = async (page, email, pw = 'OwnerPass123') => { await page.goto(BASE + '/admin/login/'); await fill(page, '#email', email); await fill(page, '#password', pw); await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]); };
await setPw(pa, A.invite);
await login(pa, 'owner@shop-a.test');
ok(pa.url().endsWith('/admin/'), 'Owner signs in to /admin/ on the platform domain');
ok(await pa.locator('.quota-big strong').textContent() === '0 / 20', 'Dashboard quota indicator shows 0 / 20 (Starter, from DB)');
await shot(pa, '04-admin-dashboard');

/* 4. Category, product, analyzer, image upload, slug 301 */
step('Catalogue management');
await pa.goto(BASE + '/admin/categories/new/');
await fill(pa, '#f-name', 'Πουκάμισα'); await Promise.all([pa.waitForNavigation(), pa.click('button:has-text("Save category")')]);
ok(await pa.locator('.tree-row a:has-text("Πουκάμισα")').count() === 1, 'Category created');
await pa.goto(BASE + '/admin/products/new/');
await fill(pa, '#f-name', 'Linen Shirt'); await fill(pa, '#f-sku', 'LS-1'); await fill(pa, '#f-price', '49,90'); await fill(pa, '#f-stock', '10');
await fill(pa, '#f-short-description', 'A breathable linen shirt for hot summer days, cut from European linen.');
await fill(pa, '#f-focus-keyword', 'linen shirt');
await pa.check('.cat-checks input'); 
await fill(pa, 'input[name="variant_options[]"]', 'Size: M, Color: White');
await pa.locator('input[name="variant_stock[]"]').first().fill('4');
await pa.check('input[name=status][value=active]');
await pa.waitForFunction(() => document.querySelector('[data-score=seo] strong')?.textContent !== '–', null, { timeout: 5000 });
ok(/^\d+$/.test(await pa.locator('[data-score=seo] strong').textContent()), 'Live AJAX SEO/AEO analyzer returns scores');
await pa.click('[data-media-open][data-media-multi]');
await pa.setInputFiles('[data-media-upload] input[type=file]', 'tests/fixtures/sample.jpg');
await pa.waitForSelector('.mm-item.is-sel', { timeout: 8000 });
await shot(pa, '05-media-picker', false);
await pa.click('[data-media-confirm]');
ok(await pa.locator('.img-item img').count() === 1, 'Image uploaded through the media library and attached');
await shot(pa, '06-product-edit');
await Promise.all([pa.waitForNavigation(), pa.click('button:has-text("Save product")')]);
const pid = pa.url().match(/products\/(\d+)/)[1];
ok(await pa.locator('.notice-ok').count() === 1, 'Product saved & activated');
await fill(pa, '#f-slug', 'linen-shirt-white');
await Promise.all([pa.waitForNavigation(), pa.click('button:has-text("Save product")')]);
const r301 = await pa.request.get(BASE + '/s/shop-a/product/linen-shirt/', { maxRedirects: 0 });
ok(r301.status() === 301 && r301.headers().location.endsWith('/product/linen-shirt-white/'), 'Slug change → automatic 301 redirect');
await pa.goto(BASE + '/admin/products/');
await shot(pa, '07-admin-products');

/* 5. Storefront journey on the CUSTOM DOMAIN */
step('Storefront on custom domain (shop-a.test) — product → variant → cart → wishlist → checkout');
const shopper = await newCtx();
const ps = await shopper.newPage();
const D = `http://shop-a.test:${PORT}`;
await ps.goto(D + '/');
ok(await ps.title().then((t) => t.includes('Shop A')), 'Custom domain resolves to the tenant storefront');
const www = await hreq(ps, 'get', 'www.shop-a.test', '/products/', { maxRedirects: 0 });
ok(www.status() === 301 && www.headers().location.startsWith(`http://shop-a.test`), 'www → primary domain 301');
await ps.goto(D + '/product/linen-shirt-white/');
const ld = await ps.$$eval('script[type="application/ld+json"]', (s) => s.map((x) => JSON.parse(x.textContent)));
const types = ld.flatMap((g) => g['@graph'].map((n) => n['@type']));
ok(['Product', 'BreadcrumbList', 'OnlineStore', 'WebSite', 'ItemPage'].every((t) => types.includes(t)), 'JSON-LD @graph: Product, Offer, Breadcrumb, Organization, WebSite');
ok(!JSON.stringify(ld).includes('aggregateRating'), 'No fake ratings/reviews in structured data');
await ps.selectOption('#variant', { index: 1 });
await ps.click('.btn-buy');
await ps.waitForSelector('#drawer[open]');
ok(await ps.locator('.dl-line').count() === 1, 'Add to cart (AJAX) opens the cart drawer with the variant line');
await shot(ps, '08-cart-drawer', false);
await ps.click('#drawer [data-dialog-close]');
await ps.click('.wish-form button');
await ps.waitForSelector('.wish-form button.is-on');
await ps.goto(D + '/wishlist/');
ok(await ps.locator('.pcard').count() === 1, 'Wishlist toggle works');
await ps.goto(D + '/cart/'); await shot(ps, '09-cart');
await ps.goto(D + '/checkout/');
await fill(ps, '#co-email', 'buyer@e2e.test'); await fill(ps, '#co-name', 'Μαρία Π.'); await fill(ps, '#co-phone', '2101234567');
await fill(ps, '#co-address', 'Ερμού 1'); await fill(ps, '#co-city', 'Αθήνα'); await fill(ps, '#co-pc', '10563');
await ps.check('input[name=terms]');
await shot(ps, '10-checkout');
await Promise.all([ps.waitForNavigation(), ps.click('button:has-text("Ολοκλήρωση παραγγελίας")')]);
ok(/\/order\/1001\/\?k=/.test(ps.url()), 'Checkout → order success (#1001)');
await shot(ps, '11-order-success');
const stockLeft = await pa.request.get(BASE + `/admin/products/${pid}/`).then((r) => r.text());
ok(/name="variant_stock\[\]" value="3"/.test(stockLeft), 'Variant stock decremented (4 → 3)');

/* 6. Forms: contact + CSRF */
step('Forms');
await ps.goto(D + '/contact/');
await fill(ps, '#c-name', 'Tester'); await fill(ps, '#c-email', 't@e2e.test'); await fill(ps, '#c-msg', 'Γεια σας, ερώτηση για αποστολή.');
await ps.check('.contact-form input[name=consent]');
await ps.waitForTimeout(3100); // timing check
await Promise.all([ps.waitForNavigation(), ps.click('.contact-form button')]);
ok(await ps.locator('.notice-ok').count() === 1, 'Contact form: stored + PRG success message');
const csrfBad = await hreq(ps, 'post', 'shop-a.test', '/contact/send/', { form: { _csrf: 'bad', name: 'x', email: 'x@x.io', message: 'hello', consent: '1' }, maxRedirects: 0 });
ok(csrfBad.status() === 419, 'Invalid CSRF token → 419');

/* 7. Tenant isolation over HTTP */
step('Tenant isolation (Shop A admin → Shop B resources)');
const adminB = await newCtx(); const pb = await adminB.newPage();
const bInvite = B.invite; await setPw(pb, bInvite); await login(pb, 'owner@shop-b.test');
await pb.goto(BASE + '/admin/products/new/'); await fill(pb, '#f-name', 'B only'); await fill(pb, '#f-price', '10');
await Promise.all([pb.waitForNavigation(), pb.click('button:has-text("Save product")')]);
const bPid = pb.url().match(/products\/(\d+)/)[1];
const r1 = await pa.request.get(BASE + `/admin/products/${bPid}/`); ok(r1.status() === 403, 'GET /admin/products/{B} as A → 403');
const tokA = await pa.evaluate(() => document.body.dataset.csrf);
const r2 = await pa.request.post(BASE + `/admin/products/${bPid}/status/`, { form: { _csrf: tokA, to: 'active' } }); ok(r2.status() === 403, 'POST status change on B product as A → 403');
const r3 = await pa.request.post(BASE + '/admin/products/bulk/', { form: { _csrf: tokA, action: 'delete', 'ids[]': bPid } }); ok(r3.status() === 403, 'Bulk delete with B id as A → 403');
const r4 = await pa.request.get(BASE + '/superadmin/', { maxRedirects: 0 }); ok(r4.status() === 302, 'Shop admin cannot open /superadmin/ (redirect to its login)');
const r5 = await hreq(pa, 'get', 'shop-a.test', '/superadmin/'); ok(r5.status() === 404, 'Super Admin is not exposed on tenant domains');

/* 8. Staff permissions */
step('Staff permissions');
await pa.goto(BASE + '/admin/users/new/');
await fill(pa, '#f-email', 'editor@shop-a.test'); await pa.selectOption('#f-role', 'editor');
await Promise.all([pa.waitForNavigation(), pa.click('button:has-text("Create & send invitation")')]);
const edInvite = (await pa.locator('code[data-copy]').first().textContent()).trim();
const ed = await newCtx(); const pe = await ed.newPage();
await setPw(pe, edInvite); await login(pe, 'editor@shop-a.test');
ok((await pe.request.get(BASE + '/admin/products/')).status() === 403, 'Editor → /admin/products/ 403');
ok((await pe.request.get(BASE + '/admin/orders/')).status() === 403, 'Editor → /admin/orders/ 403');
ok((await pe.request.get(BASE + '/admin/users/')).status() === 403, 'Editor → /admin/users/ 403');
ok((await pe.request.get(BASE + '/admin/pages/')).status() === 200, 'Editor → /admin/pages/ 200');
ok(await pe.locator('.side nav a[href="/admin/products/"]').count() === 0, 'Editor sidebar hides unauthorized modules');

/* 9. Impersonation */
step('Super Admin impersonation');
await p.goto(BASE + `/superadmin/shops/${A.id}/`);
await Promise.all([p.waitForNavigation(), p.click('button:has-text("Login as Shop Admin")')]);
ok(p.url().endsWith('/admin/') && await p.locator('.imp-bar').count() === 1, '"Login as Shop Admin" opens the shop admin with an impersonation banner');
await Promise.all([p.waitForNavigation(), p.click('.imp-bar button')]);

/* 10. Lock mode */
step('Lock mode (zero exposure) & renewal');
await p.goto(BASE + `/superadmin/shops/${A.id}/`);
p.once('dialog', (d) => d.accept());
await Promise.all([p.waitForNavigation(), p.click('button[value=lock]')]);
const lockedUrls = ['/', '/products/', '/product/linen-shirt-white/', '/cart/', '/checkout/', '/sitemap.xml', '/feed/products.xml', '/api/cart/', '/llms.txt', '/admin/login/'];
let allLocked = true, leaked = false;
for (const u of lockedUrls) {
  const r = await hreq(shopper, 'get', 'shop-a.test', u, { maxRedirects: 0 });
  const t = await r.text();
  if (r.status() !== 503) { allLocked = false; console.log('    not 503:', u, r.status()); }
  if (/Linen Shirt|buyer@e2e|<form/i.test(t)) { leaked = true; console.log('    leak on', u); }
}
ok(allLocked, 'All storefront, feed, API and admin URLs on the shop domain return 503 lock page');
ok(!leaked, 'Lock page exposes no products, customers or forms');
const lp = await shopper.newPage(); await lp.goto(D + '/'); await shot(lp, '12-lock-page', false);
const ownerLogin = await newCtx(); const po = await ownerLogin.newPage();
await login(po, 'owner@shop-a.test');
ok(po.url().endsWith('/admin/login/') && /suspended/i.test(await po.content()), 'Client admin access suspended while locked');
await p.goto(BASE + `/superadmin/shops/${A.id}/`);
await p.selectOption('#renew select[name=months]', '3');
await Promise.all([p.waitForNavigation(), p.click('#renew button[type=submit]')]);
ok((await hreq(shopper, 'get', 'shop-a.test', '/')).status() === 200, 'Renew → store restored immediately (200)');
ok(/renewal/.test(await p.content()), 'Renewal recorded in subscription history');

/* 11. 404 monitor + SEO files */
step('404 monitor & SEO files');
await hreq(shopper, 'get', 'shop-a.test', '/this-does-not-exist/');
await pa.goto(BASE + '/admin/seo/404/');
ok(await pa.locator('td:has-text("/this-does-not-exist/")').count() === 1, '404 logged in the monitor');
const robots = await (await hreq(shopper, 'get', 'shop-a.test', '/robots.txt')).text();
ok(['GPTBot', 'OAI-SearchBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended'].every((b) => robots.includes(b)) && robots.includes('Sitemap: http://shop-a.test'), 'robots.txt allows AI crawlers and points to the sitemap');
const sm = await (await hreq(shopper, 'get', 'shop-a.test', '/sitemap.xml')).text();
ok(sm.includes('/product/linen-shirt-white/') && sm.includes('image:image'), 'sitemap.xml with product + image entries');
ok((await (await hreq(shopper, 'get', 'shop-a.test', '/llms.txt')).text()).includes('Linen Shirt'), 'llms.txt lists active products');

/* 12. Screenshots of remaining admin screens */
step('Visual evidence');
for (const [u, n] of [['/admin/import/', '13-admin-import'], ['/admin/appearance/', '14-admin-appearance'], ['/admin/homepage/', '15-admin-homepage'], ['/admin/feeds/', '16-admin-feeds'], ['/admin/seo/', '17-admin-seo'], ['/admin/orders/1/', '18-admin-order']]) { await pa.goto(BASE + u); await shot(pa, n); }
for (const [u, n] of [['/superadmin/shops/', '19-sa-shops'], ['/superadmin/plans/', '20-sa-plans'], ['/superadmin/templates/', '21-sa-templates'], ['/superadmin/audit/', '22-sa-audit']]) { await p.goto(BASE + u); await shot(p, n); }
const mob = await newCtx({ width: 390, height: 844 }); const pm = await mob.newPage();
await pm.goto(D + '/'); await shot(pm, '23-mobile-home');
await pm.goto(D + '/product/linen-shirt-white/'); await shot(pm, '24-mobile-product');
ok(!(await pm.evaluate(() => document.documentElement.scrollWidth > innerWidth)), 'No horizontal overflow at 390px');
const am = await mob.newPage(); await am.goto(BASE + '/admin/login/'); await shot(am, '25-mobile-admin-login', false);

await browser.close();
console.log(errors.length ? '\nJS errors:\n' + errors.join('\n') : '\nNo JS errors.');
console.log(`\n${fail ? fail + ' failed, ' : ''}${pass} passed`);
process.exit(fail || errors.length ? 1 : 0);
