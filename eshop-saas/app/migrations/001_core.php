<?php
// Core schema. Business limits (prices, quotas, durations) live in data rows, never in code.
return <<<'SQL'
CREATE TABLE platform_settings (key TEXT PRIMARY KEY, value TEXT);

CREATE TABLE subscription_plans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, description TEXT,
  price_cents INTEGER NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'EUR',
  billing_period_months INTEGER NOT NULL DEFAULT 1,
  setup_fee_cents INTEGER NOT NULL DEFAULT 0,
  active_product_limit INTEGER NOT NULL, stored_product_limit INTEGER NOT NULL DEFAULT 10000,
  staff_limit INTEGER NOT NULL DEFAULT 3, storage_mb INTEGER NOT NULL DEFAULT 1024,
  duration_options TEXT NOT NULL DEFAULT '1,3,6,12',
  status TEXT NOT NULL DEFAULT 'active', position INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL, updated_at TEXT
);
CREATE TABLE plan_features (
  plan_id INTEGER NOT NULL REFERENCES subscription_plans(id) ON DELETE CASCADE,
  feature TEXT NOT NULL, value TEXT NOT NULL DEFAULT '1',
  PRIMARY KEY (plan_id, feature)
);

CREATE TABLE templates (
  key TEXT PRIMARY KEY, name TEXT NOT NULL, style TEXT, description TEXT,
  status TEXT NOT NULL DEFAULT 'active', position INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE shops (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL, slug TEXT NOT NULL, company TEXT, vat_number TEXT,
  owner_name TEXT, email TEXT, phone TEXT,
  plan_id INTEGER REFERENCES subscription_plans(id),
  template_key TEXT NOT NULL DEFAULT 'aurora',
  status TEXT NOT NULL DEFAULT 'PENDING',
  subscription_start TEXT, subscription_expires TEXT, subscription_months INTEGER,
  active_limit_override INTEGER, stored_limit_override INTEGER,
  hosting_notes TEXT, notes TEXT, locale TEXT NOT NULL DEFAULT 'el', currency TEXT NOT NULL DEFAULT 'EUR',
  is_demo INTEGER NOT NULL DEFAULT 0, quota_over INTEGER NOT NULL DEFAULT 0,
  next_order_number INTEGER NOT NULL DEFAULT 1001,
  locked_at TEXT, created_at TEXT NOT NULL, updated_at TEXT, deleted_at TEXT
);
CREATE UNIQUE INDEX ux_shops_slug ON shops(slug);
CREATE INDEX ix_shops_status ON shops(status);

CREATE TABLE shop_domains (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  domain TEXT NOT NULL UNIQUE, is_primary INTEGER NOT NULL DEFAULT 0,
  ssl_status TEXT NOT NULL DEFAULT 'pending', dns_status TEXT NOT NULL DEFAULT 'pending', hosting_status TEXT NOT NULL DEFAULT 'pending',
  verified_at TEXT, last_checked_at TEXT, created_at TEXT NOT NULL
);
CREATE INDEX ix_domains_shop ON shop_domains(shop_id);

CREATE TABLE shop_settings (shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE, key TEXT NOT NULL, value TEXT, PRIMARY KEY (shop_id, key));
CREATE TABLE shop_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE, template_key TEXT NOT NULL, applied_by INTEGER, applied_at TEXT NOT NULL);

CREATE TABLE payment_providers (key TEXT PRIMARY KEY, name TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, config TEXT, position INTEGER NOT NULL DEFAULT 0);
CREATE TABLE payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  provider_key TEXT NOT NULL, purpose TEXT NOT NULL DEFAULT 'subscription',
  amount_cents INTEGER NOT NULL, currency TEXT NOT NULL DEFAULT 'EUR',
  status TEXT NOT NULL DEFAULT 'pending', reference TEXT, external_id TEXT, meta TEXT,
  subscription_id INTEGER, created_by INTEGER, created_at TEXT NOT NULL, paid_at TEXT
);
CREATE TABLE subscriptions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  plan_id INTEGER NOT NULL REFERENCES subscription_plans(id),
  status TEXT NOT NULL DEFAULT 'active', months INTEGER NOT NULL,
  starts_at TEXT NOT NULL, expires_at TEXT NOT NULL,
  price_cents INTEGER NOT NULL DEFAULT 0, payment_id INTEGER, auto_renew INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX ix_subs_shop ON subscriptions(shop_id);
CREATE TABLE subscription_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  action TEXT NOT NULL, plan_id INTEGER, plan_name TEXT, months INTEGER,
  starts_at TEXT, expires_at TEXT, previous_expires_at TEXT, note TEXT,
  actor_id INTEGER, actor_email TEXT, created_at TEXT NOT NULL
);

CREATE TABLE roles (key TEXT PRIMARY KEY, name TEXT NOT NULL, scope TEXT NOT NULL, tier INTEGER NOT NULL DEFAULT 0);
CREATE TABLE permissions (key TEXT PRIMARY KEY, label TEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0);
CREATE TABLE role_permissions (role_key TEXT NOT NULL, permission_key TEXT NOT NULL, PRIMARY KEY (role_key, permission_key));

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER REFERENCES shops(id) ON DELETE CASCADE,
  email TEXT NOT NULL, name TEXT, password_hash TEXT,
  role TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'active',
  failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until TEXT, last_login_at TEXT,
  created_by INTEGER, created_at TEXT NOT NULL, updated_at TEXT, deleted_at TEXT
);
CREATE UNIQUE INDEX ux_users_email ON users(email) WHERE deleted_at IS NULL;
CREATE INDEX ix_users_shop ON users(shop_id);
CREATE TABLE user_permissions (user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, permission_key TEXT NOT NULL, PRIMARY KEY (user_id, permission_key));
CREATE TABLE password_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL UNIQUE, purpose TEXT NOT NULL DEFAULT 'reset', expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT NOT NULL
);

CREATE TABLE media (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  filename TEXT NOT NULL, path TEXT, external_url TEXT, mime TEXT, width INTEGER, height INTEGER,
  size_bytes INTEGER NOT NULL DEFAULT 0, variants TEXT, alt TEXT, created_at TEXT NOT NULL
);
CREATE INDEX ix_media_shop ON media(shop_id, id);

CREATE TABLE categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  parent_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  name TEXT NOT NULL, slug TEXT NOT NULL, path TEXT NOT NULL, depth INTEGER NOT NULL DEFAULT 0,
  description TEXT, image_id INTEGER REFERENCES media(id) ON DELETE SET NULL, position INTEGER NOT NULL DEFAULT 0,
  seo_title TEXT, seo_description TEXT, focus_keyword TEXT, canonical TEXT, robots TEXT, faq TEXT,
  created_at TEXT NOT NULL, updated_at TEXT
);
CREATE UNIQUE INDEX ux_categories_path ON categories(shop_id, path);
CREATE INDEX ix_categories_parent ON categories(shop_id, parent_id, position);

CREATE TABLE products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  name TEXT NOT NULL, slug TEXT NOT NULL, sku TEXT, barcode TEXT,
  short_description TEXT, description TEXT,
  price_cents INTEGER NOT NULL DEFAULT 0, sale_price_cents INTEGER, cost_price_cents INTEGER,
  stock INTEGER NOT NULL DEFAULT 0, manage_stock INTEGER NOT NULL DEFAULT 1,
  weight_g INTEGER, length_mm INTEGER, width_mm INTEGER, height_mm INTEGER,
  brand TEXT, primary_category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  status TEXT NOT NULL DEFAULT 'inactive', activated_at TEXT, featured INTEGER NOT NULL DEFAULT 0,
  seo_title TEXT, seo_description TEXT, focus_keyword TEXT, canonical TEXT, robots TEXT, faq TEXT,
  created_at TEXT NOT NULL, updated_at TEXT, deleted_at TEXT
);
CREATE UNIQUE INDEX ux_products_slug ON products(shop_id, slug) WHERE deleted_at IS NULL;
CREATE INDEX ix_products_status ON products(shop_id, status, activated_at, id) WHERE deleted_at IS NULL;
CREATE INDEX ix_products_sku ON products(shop_id, sku);
CREATE INDEX ix_products_list ON products(shop_id, deleted_at, id);
CREATE INDEX ix_products_cat ON products(shop_id, primary_category_id);

CREATE TABLE category_relationships (
  shop_id INTEGER NOT NULL, product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
  PRIMARY KEY (product_id, category_id)
);
CREATE INDEX ix_catrel_cat ON category_relationships(category_id, product_id);
CREATE TABLE product_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  media_id INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  position INTEGER NOT NULL DEFAULT 0, alt TEXT
);
CREATE INDEX ix_pimg ON product_images(product_id, position);
CREATE TABLE product_variants (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  sku TEXT, options TEXT NOT NULL, price_cents INTEGER, sale_price_cents INTEGER, stock INTEGER NOT NULL DEFAULT 0, position INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX ix_pvar ON product_variants(product_id, position);
CREATE TABLE product_attributes (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name TEXT NOT NULL, value TEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX ix_pattr ON product_attributes(product_id, position);

CREATE TABLE menus (id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE, location TEXT NOT NULL, name TEXT NOT NULL);
CREATE UNIQUE INDEX ux_menus ON menus(shop_id, location);
CREATE TABLE menu_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL,
  menu_id INTEGER NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
  parent_id INTEGER REFERENCES menu_items(id) ON DELETE CASCADE,
  label TEXT NOT NULL, type TEXT NOT NULL, ref_id INTEGER, url TEXT, position INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX ix_menu_items ON menu_items(menu_id, parent_id, position);

CREATE TABLE pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  title TEXT NOT NULL, slug TEXT NOT NULL, content TEXT, status TEXT NOT NULL DEFAULT 'published', kind TEXT NOT NULL DEFAULT 'page',
  seo_title TEXT, seo_description TEXT, focus_keyword TEXT, robots TEXT, faq TEXT,
  created_at TEXT NOT NULL, updated_at TEXT, deleted_at TEXT
);
CREATE UNIQUE INDEX ux_pages ON pages(shop_id, slug) WHERE deleted_at IS NULL;

CREATE TABLE homepage_sections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  type TEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1, data TEXT
);
CREATE INDEX ix_hs ON homepage_sections(shop_id, position);

CREATE TABLE popups (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  title TEXT NOT NULL, body TEXT, button_text TEXT, cta_link TEXT,
  collect_email INTEGER NOT NULL DEFAULT 1, consent_required INTEGER NOT NULL DEFAULT 1, consent_text TEXT,
  image_id INTEGER REFERENCES media(id) ON DELETE SET NULL,
  delay_seconds INTEGER NOT NULL DEFAULT 5, active INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL, updated_at TEXT
);
CREATE TABLE popup_submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  popup_id INTEGER, email TEXT NOT NULL, consent INTEGER NOT NULL DEFAULT 0, consent_text TEXT,
  source TEXT, ip_hash TEXT, token TEXT NOT NULL, created_at TEXT NOT NULL, unsubscribed_at TEXT
);
CREATE UNIQUE INDEX ux_subs_email ON popup_submissions(shop_id, email);

CREATE TABLE feeds (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  name TEXT NOT NULL, type TEXT NOT NULL, slug TEXT NOT NULL, filters TEXT, fields TEXT,
  frequency_minutes INTEGER NOT NULL DEFAULT 60, active INTEGER NOT NULL DEFAULT 1,
  last_generated_at TEXT, last_count INTEGER, created_at TEXT NOT NULL
);
CREATE UNIQUE INDEX ux_feeds ON feeds(shop_id, slug);
CREATE TABLE feed_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL, feed_id INTEGER, items INTEGER, duration_ms INTEGER, status TEXT, message TEXT, created_at TEXT NOT NULL);

CREATE TABLE imports (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  type TEXT NOT NULL, filename TEXT NOT NULL, file_path TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'uploaded',
  headers TEXT, mapping TEXT, options TEXT,
  total_rows INTEGER NOT NULL DEFAULT 0, processed_rows INTEGER NOT NULL DEFAULT 0,
  created_count INTEGER NOT NULL DEFAULT 0, updated_count INTEGER NOT NULL DEFAULT 0,
  activated_count INTEGER NOT NULL DEFAULT 0, error_count INTEGER NOT NULL DEFAULT 0, skipped_count INTEGER NOT NULL DEFAULT 0,
  locked_until INTEGER, created_by INTEGER, created_at TEXT NOT NULL, started_at TEXT, finished_at TEXT
);
CREATE TABLE import_rows (
  id INTEGER PRIMARY KEY AUTOINCREMENT, import_id INTEGER NOT NULL REFERENCES imports(id) ON DELETE CASCADE,
  shop_id INTEGER NOT NULL, row_num INTEGER NOT NULL, data TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', message TEXT
);
CREATE INDEX ix_import_rows ON import_rows(import_id, status, id);
CREATE TABLE import_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, import_id INTEGER NOT NULL REFERENCES imports(id) ON DELETE CASCADE, shop_id INTEGER NOT NULL, level TEXT NOT NULL, message TEXT NOT NULL, created_at TEXT NOT NULL);

CREATE TABLE customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  email TEXT NOT NULL, name TEXT, phone TEXT, password_hash TEXT, address TEXT,
  failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until TEXT,
  created_at TEXT NOT NULL, updated_at TEXT, deleted_at TEXT
);
CREATE UNIQUE INDEX ux_customers ON customers(shop_id, email);

CREATE TABLE orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
  number TEXT NOT NULL, access_token TEXT NOT NULL, customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
  email TEXT NOT NULL, name TEXT NOT NULL, phone TEXT, billing TEXT, shipping TEXT,
  subtotal_cents INTEGER NOT NULL, shipping_cents INTEGER NOT NULL DEFAULT 0, fee_cents INTEGER NOT NULL DEFAULT 0,
  discount_cents INTEGER NOT NULL DEFAULT 0, total_cents INTEGER NOT NULL, currency TEXT NOT NULL DEFAULT 'EUR',
  shipping_method TEXT, payment_method TEXT, payment_status TEXT NOT NULL DEFAULT 'pending',
  status TEXT NOT NULL DEFAULT 'pending', shipping_status TEXT NOT NULL DEFAULT 'not_shipped', tracking_number TEXT,
  notes TEXT, admin_notes TEXT, ip_hash TEXT, created_at TEXT NOT NULL, updated_at TEXT
);
CREATE UNIQUE INDEX ux_orders_number ON orders(shop_id, number);
CREATE INDEX ix_orders_shop ON orders(shop_id, id);
CREATE TABLE order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  shop_id INTEGER NOT NULL, product_id INTEGER, variant_id INTEGER,
  name TEXT NOT NULL, sku TEXT, options TEXT, price_cents INTEGER NOT NULL, quantity INTEGER NOT NULL, total_cents INTEGER NOT NULL
);
CREATE TABLE order_history (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE, shop_id INTEGER NOT NULL, status TEXT, note TEXT, actor TEXT, created_at TEXT NOT NULL);

CREATE TABLE seo_settings (
  shop_id INTEGER PRIMARY KEY,
  site_title TEXT, title_separator TEXT DEFAULT ' | ', meta_description TEXT, og_image_id INTEGER,
  organization_type TEXT DEFAULT 'Organization', same_as TEXT, gsc_verification TEXT, ga4_id TEXT,
  robots_extra TEXT, llms_extra TEXT, noindex_site INTEGER NOT NULL DEFAULT 0, updated_at TEXT
);

CREATE TABLE redirects (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL DEFAULT 0,
  source TEXT NOT NULL, target TEXT, type INTEGER NOT NULL DEFAULT 301, match_type TEXT NOT NULL DEFAULT 'exact',
  active INTEGER NOT NULL DEFAULT 1, hits INTEGER NOT NULL DEFAULT 0, last_hit_at TEXT, note TEXT, created_at TEXT NOT NULL
);
CREATE INDEX ix_redirects ON redirects(shop_id, active, match_type, source);
CREATE TABLE not_found_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL DEFAULT 0, path TEXT NOT NULL,
  hits INTEGER NOT NULL DEFAULT 1, referrer TEXT, ignored INTEGER NOT NULL DEFAULT 0, first_hit_at TEXT NOT NULL, last_hit_at TEXT NOT NULL
);
CREATE UNIQUE INDEX ux_404 ON not_found_log(shop_id, path);

CREATE TABLE contact_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER NOT NULL DEFAULT 0,
  name TEXT, email TEXT, phone TEXT, subject TEXT, message TEXT, consent INTEGER NOT NULL DEFAULT 0, ip_hash TEXT, read_at TEXT, created_at TEXT NOT NULL
);
CREATE INDEX ix_msgs ON contact_messages(shop_id, id);

CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id INTEGER, actor_email TEXT, impersonator_id INTEGER,
  shop_id INTEGER, action TEXT NOT NULL, entity_type TEXT, entity_id INTEGER, ip TEXT,
  outcome TEXT NOT NULL DEFAULT 'success', details TEXT, created_at TEXT NOT NULL
);
CREATE INDEX ix_audit_shop ON audit_logs(shop_id, id);
CREATE INDEX ix_audit_id ON audit_logs(id);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT, shop_id INTEGER, audience TEXT NOT NULL DEFAULT 'super',
  type TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'info', title TEXT NOT NULL, body TEXT, dedup_key TEXT UNIQUE,
  read_at TEXT, created_at TEXT NOT NULL
);
CREATE INDEX ix_notif ON notifications(audience, read_at, id);

CREATE TABLE rate_limits (key TEXT PRIMARY KEY, hits INTEGER NOT NULL, reset_at INTEGER NOT NULL);
SQL;
