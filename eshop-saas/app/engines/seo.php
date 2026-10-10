<?php
declare(strict_types=1);

function seo_settings(int $shopId): array {
    return row('SELECT * FROM seo_settings WHERE shop_id = ?', [$shopId]) ?? ['shop_id' => $shopId, 'site_title' => null, 'title_separator' => ' | ', 'meta_description' => null, 'og_image_id' => null, 'organization_type' => 'Organization', 'same_as' => null, 'gsc_verification' => null, 'ga4_id' => null, 'robots_extra' => null, 'llms_extra' => null, 'noindex_site' => 0];
}

/** <head> meta block. $m: title, description, canonical, robots, image, type, locale, alternates */
function seo_head(array $m): string {
    $h = '<title>' . e($m['title']) . '</title>' . "\n";
    if (!empty($m['description'])) $h .= '<meta name="description" content="' . e(str_limit($m['description'], 300)) . '">' . "\n";
    $h .= '<meta name="robots" content="' . e($m['robots'] ?? 'index,follow,max-image-preview:large') . '">' . "\n";
    if (!empty($m['canonical'])) $h .= '<link rel="canonical" href="' . e($m['canonical']) . '">' . "\n";
    if (!empty($m['canonical'])) {
        $h .= '<link rel="alternate" hreflang="' . e($m['locale'] ?? 'el') . '" href="' . e($m['canonical']) . '">' . "\n";
        $h .= '<link rel="alternate" hreflang="x-default" href="' . e($m['canonical']) . '">' . "\n";
    }
    $h .= '<meta property="og:type" content="' . e($m['type'] ?? 'website') . '">' . "\n";
    $h .= '<meta property="og:title" content="' . e($m['og_title'] ?? $m['title']) . '">' . "\n";
    if (!empty($m['description'])) $h .= '<meta property="og:description" content="' . e(str_limit($m['description'], 300)) . '">' . "\n";
    if (!empty($m['canonical'])) $h .= '<meta property="og:url" content="' . e($m['canonical']) . '">' . "\n";
    if (!empty($m['site_name'])) $h .= '<meta property="og:site_name" content="' . e($m['site_name']) . '">' . "\n";
    $h .= '<meta property="og:locale" content="' . e(($m['locale'] ?? 'el') === 'el' ? 'el_GR' : 'en_US') . '">' . "\n";
    if (!empty($m['image'])) $h .= '<meta property="og:image" content="' . e($m['image']) . '">' . "\n";
    $h .= '<meta name="twitter:card" content="' . (!empty($m['image']) ? 'summary_large_image' : 'summary') . '">' . "\n";
    $h .= '<meta name="twitter:title" content="' . e($m['og_title'] ?? $m['title']) . '">' . "\n";
    if (!empty($m['description'])) $h .= '<meta name="twitter:description" content="' . e(str_limit($m['description'], 200)) . '">' . "\n";
    if (!empty($m['image'])) $h .= '<meta name="twitter:image" content="' . e($m['image']) . '">' . "\n";
    if (!empty($m['gsc'])) $h .= '<meta name="google-site-verification" content="' . e($m['gsc']) . '">' . "\n";
    return $h;
}

/** JSON-LD @graph script tag. */
function jsonld(array $graph): string {
    $graph = array_values(array_filter($graph));
    if (!$graph) return '';
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    return '<script type="application/ld+json">' . $json . '</script>';
}

function ld_breadcrumbs(array $crumbs): ?array {
    if (count($crumbs) < 2) return null;
    $items = [];
    foreach (array_values($crumbs) as $i => [$name, $url]) $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
    return ['@type' => 'BreadcrumbList', '@id' => end($crumbs)[1] . '#breadcrumb', 'itemListElement' => $items];
}
function ld_faq(array $faq, string $url): ?array {
    if (!$faq) return null;
    return ['@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => array_map(static fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faq)];
}
/** Product + Offer only when the required fields exist. Never emits ratings/reviews (no fake data). */
function ld_product(array $shop, array $p, string $url, array $images, string $orgId, array $variants = []): ?array {
    if (!$p['name'] || (int) $p['price_cents'] <= 0) return null;
    $offer = ['@type' => 'Offer', 'url' => $url, 'priceCurrency' => $shop['currency'], 'price' => number_format(product_effective_price($p) / 100, 2, '.', ''),
        'availability' => product_in_stock($p) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'itemCondition' => 'https://schema.org/NewCondition', 'seller' => ['@id' => $orgId]];
    $ld = ['@type' => 'Product', '@id' => $url . '#product', 'name' => $p['name'], 'url' => $url, 'description' => str_limit((string) ($p['short_description'] ?: $p['description'] ?: $p['name']), 500), 'offers' => $offer];
    if ($images) $ld['image'] = $images;
    if ($p['sku']) $ld['sku'] = $p['sku'];
    if ($p['barcode'] && preg_match('/^\d{8,14}$/', $p['barcode'])) $ld['gtin'] = $p['barcode'];
    if ($p['brand']) $ld['brand'] = ['@type' => 'Brand', 'name' => $p['brand']];
    if ($p['weight_g']) $ld['weight'] = ['@type' => 'QuantitativeValue', 'value' => (int) $p['weight_g'], 'unitCode' => 'GRM'];
    return $ld;
}

function robots_txt(string $sitemapUrl, string $extra = '', bool $noindex = false, string $base = ''): string {
    $b = rtrim($base, '/');
    if ($noindex) return "User-agent: *\nDisallow: /\n";
    $dis = "Disallow: $b/admin/\nDisallow: $b/cart/\nDisallow: $b/checkout/\nDisallow: $b/account/\nDisallow: $b/order/\nDisallow: $b/search/\nDisallow: $b/wishlist/\nDisallow: /superadmin/\n";
    $txt = "# Robots policy\nUser-agent: *\nAllow: /\n$dis\n";
    foreach (['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended'] as $bot) $txt .= "User-agent: $bot\nAllow: /\n$dis\n";
    if (trim($extra) !== '') $txt .= trim($extra) . "\n\n";
    return $txt . "Sitemap: $sitemapUrl\n";
}

/* ---------- Analyzer (SEO 0-100, AEO 0-100) ---------- */
function seo_analyze(array $in): array {
    $title = trim((string) ($in['seo_title'] ?? '') ?: (string) ($in['title'] ?? ''));
    $desc = trim((string) ($in['seo_description'] ?? ''));
    $slug = (string) ($in['slug'] ?? '');
    $kw = normalize_text((string) ($in['focus_keyword'] ?? ''));
    $html = (string) ($in['content'] ?? '');
    $short = (string) ($in['short_description'] ?? '');
    $faq = is_array($in['faq'] ?? null) ? $in['faq'] : faq_parse((string) ($in['faq'] ?? ''));
    $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ' ', $short . ' ' . $html))));
    $ntext = normalize_text($text);
    $words = $ntext === '' ? 0 : count(preg_split('/\s+/u', $ntext));
    $first = normalize_text(mb_substr($text, 0, 400));
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $h2); preg_match_all('/<h3[^>]*>(.*?)<\/h3>/is', $html, $h3);
    preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $html, $links);
    preg_match_all('/<img\s[^>]*>/i', $html, $imgs);
    $internal = 0; $external = 0;
    foreach ($links[1] as $l) preg_match('#^https?://#i', $l) ? $external++ : $internal++;
    $imgNoAlt = 0; foreach ($imgs[0] as $im) if (!preg_match('/alt="[^"]+"/i', $im)) $imgNoAlt++;
    $density = 0.0;
    if ($kw !== '' && $words > 0) $density = round(substr_count(' ' . $ntext . ' ', ' ' . $kw . ' ') * count(explode(' ', $kw)) / $words * 100, 2);
    $imagesCount = (int) ($in['images_count'] ?? count($imgs[0]));
    $type = (string) ($in['type'] ?? 'product');
    $checks = [];
    $add = static function (string $group, string $label, bool $ok, int $weight, string $tip = '') use (&$checks): void { $checks[] = compact('group', 'label', 'ok', 'weight', 'tip'); };
    $add('seo', 'Focus keyword set', $kw !== '', 8, 'Choose the main search phrase for this page.');
    $add('seo', 'Keyword in SEO title', $kw !== '' && str_contains(normalize_text($title), $kw), 10);
    $add('seo', 'SEO title length 30–60', mb_strlen($title) >= 30 && mb_strlen($title) <= 60, 8, 'Now ' . mb_strlen($title) . ' characters.');
    $add('seo', 'Meta description 70–160', mb_strlen($desc) >= 70 && mb_strlen($desc) <= 160, 10, 'Now ' . mb_strlen($desc) . ' characters.');
    $add('seo', 'Keyword in meta description', $kw !== '' && str_contains(normalize_text($desc), $kw), 6);
    $add('seo', 'Keyword in slug', $kw !== '' && str_contains(str_replace('-', ' ', $slug), str_replace(' ', ' ', normalize_text(str_replace('-', ' ', slugify($kw))))), 6);
    $add('seo', 'Short, clean slug (≤ 60)', $slug !== '' && strlen($slug) <= 60, 4);
    $add('seo', 'Keyword in introduction', $kw !== '' && str_contains($first, $kw), 8);
    $add('seo', 'Uses H2 subheadings', count($h2[1]) > 0 || $type === 'product', 6);
    $add('seo', 'Keyword density 0.5–3%', $kw === '' ? false : ($density >= 0.5 && $density <= 3), 6, "Now $density%.");
    $add('seo', 'Content length (' . ($type === 'product' ? '≥ 150' : '≥ 300') . ' words)', $words >= ($type === 'product' ? 150 : 300), 10, "Now $words words.");
    $add('seo', 'Has internal links', $internal > 0 || $type === 'product', 4);
    $add('seo', 'Has external/source links', $external > 0 || $type === 'product', 2);
    $add('seo', 'Images have alt text', $imgNoAlt === 0, 4, $imgNoAlt ? "$imgNoAlt image(s) without alt." : '');
    $add('seo', 'Has image(s)', $imagesCount > 0, 4);
    $add('seo', 'Indexable', empty($in['robots']) || !str_contains((string) $in['robots'], 'noindex'), 4);
    $add('aeo', 'Direct answer in first paragraph (≤ 60 words)', $text !== '' && count(preg_split('/\s+/u', trim(mb_substr(strip_tags(preg_replace('/<\/p>.*/su', '', $short ?: $html) ?? ''), 0, 600)))) <= 60 && mb_strlen($text) > 40, 14);
    $add('aeo', 'Short summary / TL;DR present', mb_strlen(trim($short)) >= 40 || (bool) preg_match('/tl;?dr|σε συντομία|summary/i', $html), 12);
    $add('aeo', 'FAQ present', count($faq) > 0, 16);
    $add('aeo', 'Question-style headings', (bool) preg_match('/\?/', implode(' ', array_merge($h2[1], $h3[1]))) || count($faq) >= 2, 10);
    $add('aeo', 'Lists for scannability', (bool) preg_match('/<(ul|ol)\b/i', $html), 10);
    $add('aeo', 'Table or specifications', (bool) preg_match('/<table\b/i', $html) || (int) ($in['attributes_count'] ?? 0) > 0, 10);
    $add('aeo', 'Key facts (brand / SKU / price) structured', $type !== 'product' || (!empty($in['brand']) && !empty($in['sku'])), 10);
    $add('aeo', 'Sources / references linked', $external > 0 || $type === 'product', 6);
    $add('aeo', 'Schema type available', true, 12, $type === 'product' ? 'Product + Offer + BreadcrumbList' : ($faq ? 'WebPage + FAQPage' : 'WebPage'));
    $score = static function (string $g) use ($checks): int {
        $tot = 0; $ok = 0;
        foreach ($checks as $c) if ($c['group'] === $g) { $tot += $c['weight']; if ($c['ok']) $ok += $c['weight']; }
        return $tot ? (int) round($ok / $tot * 100) : 0;
    };
    return ['seo' => $score('seo'), 'aeo' => $score('aeo'), 'checks' => $checks, 'words' => $words, 'density' => $density];
}

/** Keyword cannibalization: accent/case-insensitive overlap between focus keywords in the same shop. */
function seo_cannibalization(int $shopId, string $keyword, string $type, int $exceptId): array {
    $kw = normalize_text($keyword);
    if ($kw === '') return [];
    $tok = array_unique(explode(' ', $kw));
    $out = [];
    $sources = [
        ['product', "SELECT id, name AS title, focus_keyword FROM products WHERE shop_id = ? AND deleted_at IS NULL AND focus_keyword IS NOT NULL AND focus_keyword <> ''"],
        ['category', "SELECT id, name AS title, focus_keyword FROM categories WHERE shop_id = ? AND focus_keyword IS NOT NULL AND focus_keyword <> ''"],
        ['page', "SELECT id, title, focus_keyword FROM pages WHERE shop_id = ? AND deleted_at IS NULL AND focus_keyword IS NOT NULL AND focus_keyword <> ''"],
    ];
    foreach ($sources as [$t, $sql]) {
        foreach (rows($sql, [$shopId]) as $r) {
            if ($t === $type && (int) $r['id'] === $exceptId) continue;
            $o = array_unique(explode(' ', normalize_text($r['focus_keyword'])));
            $inter = count(array_intersect($tok, $o)); $union = count(array_unique(array_merge($tok, $o)));
            $sim = $union ? $inter / $union : 0;
            if ($sim >= 0.6) $out[] = ['type' => $t, 'id' => (int) $r['id'], 'title' => $r['title'], 'keyword' => $r['focus_keyword'], 'similarity' => (int) round($sim * 100)];
        }
    }
    return $out;
}
