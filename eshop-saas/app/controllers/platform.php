<?php
declare(strict_types=1);

/** First-run setup wizard. No default credentials exist; the first visitor creates the Super Admin. */
function setup_controller(): void {
    $errors = [];
    if (is_post()) {
        $email = mb_strtolower(clean_text(post('email'), 190));
        $pw = (string) ($_POST['password'] ?? '');
        if (!valid_email($email)) $errors['email'] = 'Enter a valid email.';
        if ($pe = password_policy($pw)) $errors['password'] = $pe;
        if ($pw !== (string) ($_POST['password2'] ?? '')) $errors['password2'] = 'Passwords do not match.';
        if (!$errors) {
            tx(static function () use ($email, $pw) {
                if (val("SELECT 1 FROM users WHERE role = 'super_admin'")) throw new UserError('Setup already completed.');
                $uid = insert('users', ['shop_id' => null, 'email' => $email, 'name' => clean_text(post('name'), 120), 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => 'super_admin', 'status' => 'active', 'created_at' => now()]);
                setting_set('platform_name', clean_text(post('platform_name'), 80) ?: 'ShopRent');
                setting_set('canonical_host', preg_replace('/^www\./', '', host_no_port()));
                setting_set('notify_email', $email);
                setting_set('installed_at', now());
                audit('setup.completed', 'user', $uid, null, 'success', [], ['id' => $uid, 'email' => $email, 'role' => 'super_admin']);
            }, true);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) val('SELECT id FROM users WHERE email = ?', [$email]);
            if (post('demo')) { @set_time_limit(180); try { demo_create_all(); } catch (Throwable $t) { log_error('demo: ' . $t->getMessage()); flash('warn', 'Demo shops could not be created: ' . $t->getMessage()); } }
            flash('ok', 'Setup complete. Welcome!');
            redirect('/superadmin/');
        }
    }
    render('platform/setup', ['errors' => $errors, 'checks' => setup_checks()]);
}
function setup_checks(): array {
    return [
        ['PHP ≥ 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
        ['PDO SQLite', extension_loaded('pdo_sqlite'), ''],
        ['GD with WebP', function_exists('imagewebp'), ''],
        ['mbstring', extension_loaded('mbstring'), ''],
        ['intl (Greek slugs)', extension_loaded('intl'), 'optional'],
        ['zip (backups)', class_exists('ZipArchive'), ''],
        ['fileinfo', extension_loaded('fileinfo'), ''],
        ['data/ writable', is_writable(DATA), ''],
        ['uploads/ writable', is_writable(UPLOADS), ''],
    ];
}

function platform_dispatch(string $path): void {
    security_headers();
    $GLOBALS['LOCALE'] = 'en';
    $GLOBALS['ERROR_RENDERER'] = static function (int $code, Throwable $t): void {
        if ($code === 404) not_found_log(0, request_path());
        http_response_code($code);
        echo view('platform/layout', ['content' => view('platform/error', ['code' => $code]), 'meta' => ['title' => $code === 404 ? 'Page not found' : 'Error', 'robots' => 'noindex'], 'graph' => []]);
    };
    $routes = [
        ['GET', '/', static fn() => platform_page('home')],
        ['GET', '/pricing/', static fn() => platform_page('pricing')],
        ['GET', '/templates/', static fn() => platform_page('templates')],
        ['GET', '/contact/', static fn() => platform_page('contact')],
        ['POST', '/contact/', static fn() => platform_contact_post()],
        ['GET', '/privacy/', static fn() => platform_page('legal', ['doc' => 'privacy'])],
        ['GET', '/terms/', static fn() => platform_page('legal', ['doc' => 'terms'])],
        ['GET', '/cookies/', static fn() => platform_page('legal', ['doc' => 'cookies'])],
        ['GET', '/robots.txt', static function () { header('Content-Type: text/plain; charset=utf-8'); echo robots_txt(abs_url('/sitemap.xml'), "Disallow: /s/\n"); exit; }],
        ['GET', '/sitemap.xml', static fn() => platform_sitemap()],
        ['GET', '/llms.txt', static fn() => platform_llms()],
    ];
    if (dispatch($routes, $path)) return;
    $r = redirect_match(0, $path);
    if ($r && $r[1]) redirect($r[1], $r[0]);
    abort(404);
}

function platform_plans(): array {
    $plans = rows("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY position");
    foreach ($plans as &$p) $p['features'] = plan_features((int) $p['id']);
    return $plans;
}
function platform_faq(): array {
    return [
        ['q' => 'What does “active products” mean?', 'a' => 'Your package limits how many products are visible in your store at the same time. You can store up to 10,000 products and switch which ones are active whenever you like.'],
        ['q' => 'Can I use my own domain?', 'a' => 'Yes. Every store runs on its own domain (e.g. example.gr or www.example.gr). You can bring a domain you already own.'],
        ['q' => 'What happens if my subscription expires?', 'a' => 'The store shows a temporary “back soon” page. Nothing is deleted: products, orders, customers and settings stay intact, and the store comes back immediately after renewal.'],
        ['q' => 'Can I change the design later?', 'a' => 'Yes. Switching between the 10 templates only changes the presentation. Your products, categories, orders and settings are not affected.'],
        ['q' => 'Can I import my products?', 'a' => 'Yes, from CSV or XML files, with column mapping, validation and duplicate detection by SKU. Large catalogues are imported in the background.'],
        ['q' => 'Do you support Skroutz and BestPrice?', 'a' => 'Yes, the store generates product feeds for Skroutz, BestPrice and custom XML formats (depending on package).'],
    ];
}
function platform_page(string $page, array $vars = []): never {
    $plans = platform_plans();
    $templates = rows("SELECT t.*, (SELECT slug FROM shops s WHERE s.template_key = t.key AND s.is_demo = 1 AND s.deleted_at IS NULL LIMIT 1) AS demo FROM templates t WHERE status = 'active' ORDER BY position");
    $name = (string) setting('platform_name', 'Platform');
    $url = abs_url(request_path());
    $home = abs_url('/');
    $org = ['@type' => 'Organization', '@id' => $home . '#organization', 'name' => setting('company_name') ?: $name, 'url' => $home];
    if ($e = setting('contact_email')) $org['email'] = $e;
    if ($p = setting('contact_phone')) $org['telephone'] = $p;
    $graph = [$org, ['@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => $name, 'publisher' => ['@id' => $home . '#organization'], 'inLanguage' => 'en']];
    $titles = ['home' => $name . ' — Rent a ready-made online store', 'pricing' => 'Pricing — ' . $name, 'templates' => '10 store templates — ' . $name, 'contact' => 'Contact — ' . $name, 'legal' => ucfirst($vars['doc'] ?? '') . ' — ' . $name];
    $descs = ['home' => 'Rent a complete e-shop on your own domain: 10 designs, product import, Skroutz/BestPrice feeds, SEO tools. Packages by number of active products.', 'pricing' => 'Simple packages by number of active products. Store up to 10,000 products on every package.', 'templates' => 'Ten professionally designed storefront templates for fashion, tech, food, home, beauty, sports and more.', 'contact' => 'Ask for a demo or a quote for your online store.', 'legal' => ''];
    $graph[] = ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $titles[$page] ?? $name, 'isPartOf' => ['@id' => $home . '#website']];
    if ($page === 'home' || $page === 'pricing') {
        $graph[] = ['@type' => 'Service', '@id' => $home . '#service', 'name' => 'E-shop rental', 'provider' => ['@id' => $home . '#organization'], 'serviceType' => 'Hosted e-commerce store',
            'offers' => array_map(static fn($p) => ['@type' => 'Offer', 'name' => $p['name'], 'price' => number_format($p['price_cents'] / 100, 2, '.', ''), 'priceCurrency' => $p['currency'], 'description' => $p['active_product_limit'] . ' active products'], $plans)];
        $graph[] = ld_faq_platform(platform_faq(), $url);
    }
    $content = view('platform/' . $page, $vars + compact('plans', 'templates', 'name'));
    header('Content-Type: text/html; charset=utf-8');
    echo view('platform/layout', ['content' => $content, 'meta' => ['title' => $titles[$page] ?? $name, 'description' => $descs[$page] ?? '', 'canonical' => $url, 'locale' => 'en', 'site_name' => $name, 'gsc' => setting('gsc_verification')], 'graph' => $graph, 'page' => $page]);
    exit;
}
function ld_faq_platform(array $faq, string $url): array {
    return ['@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => array_map(static fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faq)];
}
function platform_contact_post(): never {
    $name = clean_text(post('name'), 120); $email = mb_strtolower(clean_text(post('email'), 190)); $msg = clean_multiline(post('message'), 5000);
    if (spam_check()) { flash('ok', 'Thank you — we will get back to you shortly.'); redirect('/contact/'); }
    if (!rate_limit('platform_contact', 5, 3600)) { flash('error', 'Too many messages from your network. Please try again later.'); redirect('/contact/'); }
    if ($name === '' || !valid_email($email) || mb_strlen($msg) < 5 || empty($_POST['consent'])) { remember_input(); flash('error', 'Please fill in your name, a valid email, a message and accept the privacy policy.'); redirect('/contact/'); }
    insert('contact_messages', ['shop_id' => 0, 'name' => $name, 'email' => $email, 'phone' => clean_text(post('phone'), 40), 'subject' => clean_text(post('subject'), 160), 'message' => $msg, 'consent' => 1, 'ip_hash' => ip_hash(), 'created_at' => now()]);
    if ($to = setting('notify_email')) send_mail((string) $to, 'Platform contact: ' . $name, "$name <$email>\n" . clean_text(post('subject'), 160) . "\n\n$msg", $email);
    notify('message.new', "Contact form: $name", str_limit($msg, 200));
    clear_old();
    flash('ok', 'Thank you — we will get back to you shortly.');
    redirect('/contact/');
}
function platform_sitemap(): never {
    header('Content-Type: application/xml; charset=utf-8');
    $x = new XMLWriter(); $x->openMemory(); $x->setIndent(true); $x->startDocument('1.0', 'UTF-8');
    $x->startElement('urlset'); $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    foreach (['/', '/pricing/', '/templates/', '/contact/', '/privacy/', '/terms/', '/cookies/'] as $p) { $x->startElement('url'); $x->writeElement('loc', abs_url($p)); $x->endElement(); }
    $x->endElement(); $x->endDocument(); echo $x->outputMemory(); exit;
}
function platform_llms(): never {
    header('Content-Type: text/plain; charset=utf-8');
    $n = setting('platform_name', 'Platform');
    $o = "# $n\n\n> $n rents ready-made online stores (e-shops) on the customer's own domain, with 10 templates, product import/export, marketplace feeds and SEO tools.\n\n## Pricing (packages by number of active products)\n";
    foreach (platform_plans() as $p) $o .= '- ' . $p['name'] . ': ' . money((int) $p['price_cents']) . ' per ' . ((int) $p['billing_period_months'] === 1 ? 'month' : $p['billing_period_months'] . ' months') . ', ' . $p['active_product_limit'] . ' active products, up to ' . number_format((int) $p['stored_product_limit']) . ' stored, setup fee ' . money((int) $p['setup_fee_cents']) . "\n";
    $o .= "\n## Key pages\n- [Home](" . abs_url('/') . ")\n- [Pricing](" . abs_url('/pricing/') . ")\n- [Templates](" . abs_url('/templates/') . ")\n- [Contact](" . abs_url('/contact/') . ")\n\n## FAQ\n";
    foreach (platform_faq() as $f) $o .= "### {$f['q']}\n{$f['a']}\n\n";
    echo $o; exit;
}
