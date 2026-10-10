# SaaS E-Shop Rental Platform (PHP + SQLite)

A multi-tenant platform for renting ready-made e-shops. **One installation on one hosting account and one domain** serves:

| URL on your platform domain (e.g. `shoprent.gr`) | What it is |
|---|---|
| `/` (and `/products/`, `/product/…`, `/cart/` …) | **Main website** — blank until the Super Admin activates a storefront here (any shop + any of the 10 templates). Then the root serves that shop with clean URLs. |
| `/superadmin/` | **Super Admin**: shops, packages, subscriptions, templates, domains, payments, audit log, backups |
| `/admin/` | **Shop Admin** for every client (each user only ever sees their own shop) |
| `/s/<shop-slug>/` | Any shop's storefront, before (or instead of) a custom domain |
| `https://client-domain.gr/` | The shop's storefront on its own domain (same installation, resolved by hostname). `/admin/` also works there. |

No Composer, no npm, no build step. PHP 8.1+, SQLite via PDO. Local run: `php -S localhost:8000 index.php`.

---

## 1. Requirements

- PHP **8.1+** (tested on 8.3) with extensions: `pdo_sqlite`, `gd` (with WebP), `mbstring`, `fileinfo`, `zip`, `json`, `xmlreader`/`xmlwriter`/`simplexml`, `openssl`. Recommended: `intl` (best Greek slug transliteration — a fallback exists).
- Apache or LiteSpeed with `mod_rewrite` (Hostinger shared hosting is fine).
- The setup page shows a green/red check for each requirement.

## 2. Deploy on Hostinger (File Manager)

> **Never upload over a live site without a backup.** If a site already runs on the domain, back it up (hPanel → Files → Backups) and keep it until the new platform is verified.

1. **PHP version**: hPanel → Advanced → PHP Configuration → select **PHP 8.2 or 8.3**. On the *PHP extensions* tab make sure `pdo_sqlite`, `gd`, `intl`, `zip`, `fileinfo` are enabled.
2. **Upload**: hPanel → Files → File Manager → open `public_html` of your platform domain → upload `eshop-saas-site-php.zip` → right-click → **Extract** into `public_html` (so that `index.php` sits directly in `public_html`).
3. **Permissions**: `data/` and `uploads/` must be writable (755 is enough on Hostinger; use 775 if the setup check is red).
4. **SSL**: hPanel → Security → SSL → install the free SSL for the domain (and later for each client domain).
5. **Setup wizard**: open `https://your-domain/` → you are redirected to `/setup/`. Create the Super Admin (there are **no default credentials**). Optionally tick *Create 10 demo shops* — they power the live template previews and are `noindex`.
6. **Activate the main website**: the domain root is a **blank page** (noindex) until you activate a front end. Create your shop (*New shop*), then in **Super Admin → Overview → Main website** pick the shop and one of the 10 templates and click **Activate on main website** (or use *Show on main website* on a shop's page). *Preview selection* shows it first. You can switch template or deactivate at any time — no data changes. The shop's own admin stays at `/admin/` on the same domain.
7. In **Super Admin → Settings**: set the *Platform canonical host* (e.g. `shoprent.gr`, which also 301-redirects `www.`), the *Server IP* (hPanel → Hosting → Details) for DNS checks, notification email, *From* address (create a mailbox in hPanel → Emails for good deliverability), then tick **Force HTTPS** once SSL works.
8. **Cron** (expiry warnings 10/3/1 days, auto-lock, background imports): hPanel → Advanced → **Cron Jobs** → *Custom*, every 15 minutes:
   ```
   php /home/USERNAME/domains/YOUR-DOMAIN/public_html/cron.php
   ```
   (the exact path is shown in Super Admin → Settings → Cron). If only URL cron is available, use the `/cron/?token=…` URL shown there. A light check also runs on normal traffic every 10 minutes, so locks happen even without cron.
9. **LiteSpeed Cache**: if the LiteSpeed Cache / Cache Manager is enabled in hPanel, **exclude** `/admin/`, `/superadmin/`, `/cart/`, `/checkout/`, `/account/`, `/s/*/cart/`, `/s/*/checkout/` or disable full-page cache for this site — pages are dynamic (sessions, CSRF tokens). Static assets are cached via `.htaccess`.

### Custom domains for client shops (same hosting, same folder)

Each client domain must point to **the same `public_html`** as the platform:

1. Super Admin → Shops → *the shop* → **Domains** → add `client.gr` (primary). `www.client.gr` resolves automatically and is 301-redirected to the primary.
2. In hPanel, add the domain as a **parked domain / alias** of the platform website (Domains section). If your plan only offers *addon domains*, set the addon domain's document root to the platform's `public_html` folder. *(hPanel menu names change — verify the current wording in hPanel.)*
3. DNS at the registrar: `A @ → server IP`, `CNAME www → client.gr` (or Hostinger nameservers).
4. Install SSL for the domain in hPanel → Security → SSL.
5. Click **Check DNS/SSL** on the shop page to record `dns_status` / `ssl_status`.

The app resolves the shop by hostname, so no extra configuration is needed per domain.

## 3. Daily operations

**Create a shop** — Super Admin → *New shop* (wizard: client → domain → package → duration → template → admin → review). The owner receives a one-time *set password* link (also shown on screen; no passwords are emailed).

**Renew / reset timer** — shop page → *Renew*: choose package, months (only durations allowed by the package) and *extend from current expiry* or *reset from today*; optionally record a payment. Renewal only touches subscription fields and history — never products, orders, users or settings.

**Expiry** — ACTIVE → EXPIRING (≤ 10 days, configurable) → EXPIRED → LOCKED (after the configurable grace period, default 0). A locked shop serves only the lock page (HTTP 503) on every URL including feeds, API and `/admin/` of its domain. Super Admin can still manage it, impersonate it, renew, or delete it (typing the domain to confirm). Deleted shops can be restored; **Purge** permanently removes data after taking a full backup.

**Change package** — upgrades apply instantly. On downgrade below the current active count choose: *let the shop admin choose* (store shows only the first N by activation date meanwhile, admin sees a banner) or *deactivate the most recently activated*. Products are never deleted.

**Packages** — Super Admin → Packages: price, setup fee, active-product limit, stored limit (default 10,000), users, storage, durations and feature flags (import, feeds, marketplace feeds, custom feeds, popups, typography). Nothing is hard-coded.

**Impersonation** — shop page → *Login as Shop Admin*. A yellow banner is shown and every action is audit-logged with the impersonator.

**Imports** (Shop Admin → Import/Export) — Upload CSV/XML → validate → preview with column mapping → confirm → processed in batches (browser + cron) → report. SKU matching prevents duplicates; activation always respects the quota. Excel CSV in Greek: choose *Windows-1253*. Export CSV uses the same columns, so it round-trips.

**Feeds** — `/feed/products.xml`, `/feed/rss.xml`, and per-shop Skroutz / BestPrice / custom XML feeds at `/feed/<name>.xml` (cached, regenerated per frequency). Only active products are ever published.

**404 Monitor & Redirects** — Shop Admin → SEO → *404 monitor* lists missing URLs (scanner noise like `wp-login`, `.env` is ignored) with a suggested destination; one click creates a 301/302/410. *Redirects* supports 301/302/307/308/410/451, exact or regex with `$1`, hit counters; loops and chains are prevented; slug changes add 301s automatically.

**Search Console** — per shop: SEO → paste the verification code → submit `https://client.gr/sitemap.xml`. Main website: once a shop is activated there, use that shop's SEO settings and submit `https://your-domain/sitemap.xml`.

**GA4** — set the Measurement ID (per shop in SEO). It loads **only after** the visitor accepts the cookie banner.

## 4. Backups

- Super Admin → **Backups → Create backup now**: ZIP with a consistent SQLite snapshot (`VACUUM INTO`) + all uploads. Download weekly and keep off-server copies; Hostinger's own backups are a second layer.
- Restore: replace `data/app.sqlite` (+ `data/.secret`) and `uploads/` from the ZIP, delete `data/app.sqlite-wal`/`-shm`.
- A backup is created automatically before every permanent shop purge.

## 5. Security notes

Prepared statements everywhere; tenant scoping on every query (`WHERE shop_id = ?`, 403 + audit log on cross-tenant attempts); CSRF on every POST; `password_hash`; 5 failed logins → 15-minute lock; IP rate limits; HttpOnly/SameSite session cookies (Secure on HTTPS); allow-list HTML sanitizer (no scripts, no `on*`, no `javascript:`/`data:` URLs, iframes only from YouTube/Vimeo/Google Maps); image uploads validated with `getimagesize` + MIME, max 10 MB, resized to 2000 px, re-encoded; `uploads/` cannot execute PHP; `app/`, `data/`, `tests/` are blocked; CSP and security headers; back office is `noindex`.

## 6. Development & tests

```bash
php -S localhost:8000 index.php            # local dev (also enforces the .htaccess protections)
php tests/acceptance.php                    # 60 engine checks incl. spec §74–§80 and a 10,000-product XML import
tests/run-e2e.sh /tmp/e2e                   # browser E2E on a fresh install (Playwright): 54 checks + screenshots
php tests/jsonld-check.php http://localhost:8000 / /s/demo-aurora/   # JSON-LD validation
tests/package.sh                            # builds dist/eshop-saas-site-php.zip (clean: no data, no uploads, no tests)
```
Set `APP_DEBUG=1` (or create `data/.debug`) to show error details; errors are always logged to `data/logs/error.log`.

### Structure
```
index.php            front controller (+ router for php -S)
cron.php             CLI cron entry
.htaccess            rewrites, protections, caching
app/                 (blocked from the web)
  bootstrap.php db.php helpers.php auth.php tenancy.php sanitize.php markdown.php i18n.php router.php seed.php
  migrations/        schema (auto-applied on first request)
  engines/           shops, subscriptions, payments, products, categories, cms, media, orders, importer, feeds, seo, redirects, notifications/audit, backup, cron, templates, demo
  controllers/       platform (setup + blank main page), superadmin, admin, store, ui helpers
  views/             platform (setup, blank), superadmin, admin, store (shared storefront + 8 hero compositions), layouts, errors
assets/css|js        store.css (shared storefront), admin.css, lock.css; store.js, admin.js
assets/templates/    the 10 template stylesheets (aurora, maison, noir, pulse, vogue, bazaar, terra, atelier, fresh, volt)
data/  uploads/      runtime (database, logs, sessions, backups, feeds cache / media)
```

---

## BEFORE GOING LIVE

Everything below is placeholder or needs a real decision. Template text is marked in the UI with **"Template text — verify before going live"** (Greek: *"Κείμενο προτύπου — ελέγξτε πριν τη δημοσίευση"*).

**Platform**
- [ ] Platform name shown in the back office (default **ShopRent** is a placeholder), company legal name (Super Admin → Settings).
- [ ] **Activate the main website** (Overview → Main website) — until then the domain root is intentionally blank.
- [ ] Package prices/limits are seeded from the brief (Starter €20/20, Business €45/50, Pro €80/100, setup €150) — confirm, and confirm feature flags per package (which package gets Skroutz/BestPrice, custom feeds, typography, staff/storage limits — chosen by me as defaults).
- [ ] Notification email, mail *From* address on your domain (SPF/DKIM via Hostinger email).
- [ ] Canonical host, server IP, Force HTTPS after SSL is active.
- [ ] Cron job created and "Last cron" updating on the Super Admin sidebar.
- [ ] First backup created and downloaded.
- [ ] Decide whether to keep the 10 demo shops (they are `noindex` and excluded from revenue stats); they power the template previews in Super Admin.

**Per client shop**
- [ ] Logo, favicon, colours (contrast warnings shown), business & contact info, social links.
- [ ] Legal pages: Terms, Privacy, Shipping, Returns, Cookies (auto-created as template text).
- [ ] Homepage hero/banner/benefits texts and images (template text by default).
- [ ] Shipping cost, free-shipping threshold, COD fee, bank details (defaults €4 / €50 / €2 are placeholders).
- [ ] SEO: site title, meta description, sameAs, Search Console verification, GA4.
- [ ] Custom domain DNS + SSL, then *Check DNS/SSL*.
- [ ] Test order end-to-end on the live domain; check order emails arrive.

**Not included in V1 (prepared in the architecture)**
- Online card payments (Stripe / Viva Wallet providers exist as disabled stubs), automatic renewals, invoices, coupons, courier APIs, multi-language per shop (each shop is Greek **or** English), AI content, customer-facing expiry emails, product reviews, WordPress/Rank Math import, platform-level redirect manager UI.
