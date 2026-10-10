<?php
declare(strict_types=1);

/**
 * The 10 storefront templates. A template is ONLY presentation: it picks structural
 * variants for shared components + a stylesheet + default design tokens.
 * It never owns data, so switching is instant and loss-free.
 */
function template_registry(): array {
    static $r = null;
    if ($r) return $r;
    $serif = '"Iowan Old Style", "Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif';
    $sans = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    $grotesk = '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif';
    $mono = 'ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace';
    $rounded = '"SF Pro Rounded", ui-rounded, "Nunito", "Segoe UI", system-ui, sans-serif';
    $condensed = '"Arial Narrow", "Roboto Condensed", "Helvetica Neue", Impact, sans-serif';
    return $r = [
        'aurora' => [
            'name' => 'Aurora', 'style' => 'Swiss Minimal Store', 'description' => 'Clean Swiss grid, large product photography and precise typography. Fits any catalogue.',
            'header' => 'classic', 'hero' => 'split', 'card' => 'minimal', 'footer' => 'columns', 'product' => 'gallery', 'listing' => 'sidebar', 'cols' => 4,
            'tokens' => ['primary' => '#111111', 'secondary' => '#f2f2f0', 'accent' => '#2f5bff', 'background' => '#ffffff', 'surface' => '#f6f6f4', 'text' => '#111111', 'muted' => '#6b6b6b', 'button_text' => '#ffffff', 'link' => '#2f5bff'],
            'fonts' => ['heading' => $grotesk, 'body' => $sans], 'radius' => '4px',
        ],
        'maison' => [
            'name' => 'Maison', 'style' => 'Luxury Boutique', 'description' => 'Centered serif identity, cream paper tones and generous whitespace for premium goods.',
            'header' => 'centered', 'hero' => 'editorial', 'card' => 'editorial', 'footer' => 'centered', 'product' => 'sticky', 'listing' => 'top', 'cols' => 3,
            'tokens' => ['primary' => '#1d1a16', 'secondary' => '#efe8dc', 'accent' => '#9a7b4f', 'background' => '#faf7f1', 'surface' => '#f2ece2', 'text' => '#1d1a16', 'muted' => '#6e655a', 'button_text' => '#faf7f1', 'link' => '#7a5c33'],
            'fonts' => ['heading' => $serif, 'body' => $sans], 'radius' => '0px',
        ],
        'noir' => [
            'name' => 'Noir', 'style' => 'Dark Premium Store', 'description' => 'Full-bleed cinematic hero on a deep dark canvas with warm gold accents.',
            'header' => 'transparent', 'hero' => 'fullbleed', 'card' => 'overlay', 'footer' => 'dark', 'product' => 'sticky', 'listing' => 'top', 'cols' => 3,
            'tokens' => ['primary' => '#d6b46a', 'secondary' => '#1b1b1d', 'accent' => '#d6b46a', 'background' => '#0e0e10', 'surface' => '#18181b', 'text' => '#f3f1ec', 'muted' => '#a19d95', 'button_text' => '#0e0e10', 'link' => '#e2c47f'],
            'fonts' => ['heading' => $serif, 'body' => $sans], 'radius' => '2px',
        ],
        'pulse' => [
            'name' => 'Pulse', 'style' => 'Tech Store', 'description' => 'Bento-grid hero, spec-first product cards and crisp technical type for electronics.',
            'header' => 'mega', 'hero' => 'bento', 'card' => 'spec', 'footer' => 'columns', 'product' => 'gallery', 'listing' => 'sidebar', 'cols' => 4,
            'tokens' => ['primary' => '#3b5bfd', 'secondary' => '#e9edff', 'accent' => '#00c2a8', 'background' => '#f5f7fb', 'surface' => '#ffffff', 'text' => '#0b1020', 'muted' => '#5b6478', 'button_text' => '#ffffff', 'link' => '#3b5bfd'],
            'fonts' => ['heading' => $sans, 'body' => $sans, 'mono' => $mono], 'radius' => '14px',
        ],
        'vogue' => [
            'name' => 'Vogue', 'style' => 'Fashion Magazine', 'description' => 'Oversized editorial typography, asymmetric grid and magazine-style category storytelling.',
            'header' => 'minimal', 'hero' => 'type', 'card' => 'editorial', 'footer' => 'minimal', 'product' => 'stacked', 'listing' => 'top', 'cols' => 3,
            'tokens' => ['primary' => '#0a0a0a', 'secondary' => '#f4e9e4', 'accent' => '#d2462f', 'background' => '#ffffff', 'surface' => '#f7f2ef', 'text' => '#0a0a0a', 'muted' => '#6d6762', 'button_text' => '#ffffff', 'link' => '#b13a26'],
            'fonts' => ['heading' => $serif, 'body' => $grotesk], 'radius' => '0px',
        ],
        'bazaar' => [
            'name' => 'Bazaar', 'style' => 'Marketplace', 'description' => 'Search-first header, dense catalogue, compact cards and deep filters for big ranges.',
            'header' => 'search', 'hero' => 'banners', 'card' => 'compact', 'footer' => 'columns', 'product' => 'gallery', 'listing' => 'sidebar', 'cols' => 5,
            'tokens' => ['primary' => '#f05a28', 'secondary' => '#fff1ea', 'accent' => '#1e7b4f', 'background' => '#f4f5f7', 'surface' => '#ffffff', 'text' => '#1b1d21', 'muted' => '#606670', 'button_text' => '#ffffff', 'link' => '#c4431a'],
            'fonts' => ['heading' => $sans, 'body' => $sans], 'radius' => '8px',
        ],
        'terra' => [
            'name' => 'Terra', 'style' => 'Organic Lifestyle', 'description' => 'Earthy palette, soft rounded shapes and warm storytelling for natural products.',
            'header' => 'classic', 'hero' => 'split', 'card' => 'soft', 'footer' => 'centered', 'product' => 'gallery', 'listing' => 'top', 'cols' => 3,
            'tokens' => ['primary' => '#3f5b3a', 'secondary' => '#e9e4d6', 'accent' => '#c0703f', 'background' => '#f7f4ec', 'surface' => '#efe9db', 'text' => '#2a2a22', 'muted' => '#6c6a5c', 'button_text' => '#f7f4ec', 'link' => '#3f5b3a'],
            'fonts' => ['heading' => $serif, 'body' => $rounded], 'radius' => '22px',
        ],
        'atelier' => [
            'name' => 'Atelier', 'style' => 'Architectural Commerce', 'description' => 'Hairline architectural grid, expansive whitespace and spatial navigation for design objects.',
            'header' => 'minimal', 'hero' => 'architect', 'card' => 'line', 'footer' => 'minimal', 'product' => 'sticky', 'listing' => 'top', 'cols' => 3,
            'tokens' => ['primary' => '#2b2b2b', 'secondary' => '#ececea', 'accent' => '#b5651d', 'background' => '#fbfbfa', 'surface' => '#f1f1ef', 'text' => '#1f1f1f', 'muted' => '#707070', 'button_text' => '#ffffff', 'link' => '#8a4a12'],
            'fonts' => ['heading' => $grotesk, 'body' => $grotesk], 'radius' => '0px',
        ],
        'fresh' => [
            'name' => 'Fresh', 'style' => 'Food & Grocery', 'description' => 'Bright, friendly and fast: category chips, quick-add cards and big tap targets.',
            'header' => 'search', 'hero' => 'banners', 'card' => 'quick', 'footer' => 'columns', 'product' => 'gallery', 'listing' => 'top', 'cols' => 4,
            'tokens' => ['primary' => '#1f8a4c', 'secondary' => '#e5f5ea', 'accent' => '#ffb627', 'background' => '#ffffff', 'surface' => '#f3f8f4', 'text' => '#13281b', 'muted' => '#56695c', 'button_text' => '#ffffff', 'link' => '#17703c'],
            'fonts' => ['heading' => $rounded, 'body' => $rounded], 'radius' => '16px',
        ],
        'volt' => [
            'name' => 'Volt', 'style' => 'Neo-Brutalist Sports', 'description' => 'Heavy borders, hard shadows, condensed uppercase type and electric colour for bold brands.',
            'header' => 'bold', 'hero' => 'brutal', 'card' => 'brutal', 'footer' => 'dark', 'product' => 'gallery', 'listing' => 'sidebar', 'cols' => 4,
            'tokens' => ['primary' => '#111111', 'secondary' => '#d7ff3a', 'accent' => '#ff3b6b', 'background' => '#f3f1ea', 'surface' => '#ffffff', 'text' => '#111111', 'muted' => '#4b4b4b', 'button_text' => '#d7ff3a', 'link' => '#111111'],
            'fonts' => ['heading' => $condensed, 'body' => $grotesk], 'radius' => '0px',
        ],
    ];
}

function template_get(string $key): array {
    $r = template_registry();
    return ($r[$key] ?? $r['aurora']) + ['key' => isset($r[$key]) ? $key : 'aurora'];
}

/** Font pairs a shop may pick when its plan has the "typography" feature. */
function font_choices(): array {
    return [
        'template' => 'Template default',
        'sans' => 'Modern sans', 'serif' => 'Classic serif', 'grotesk' => 'Swiss grotesk', 'rounded' => 'Friendly rounded', 'mono' => 'Technical mono',
    ];
}
function font_stack(string $k): ?string {
    return [
        'sans' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'serif' => '"Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif',
        'grotesk' => '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif',
        'rounded' => '"SF Pro Rounded", ui-rounded, "Nunito", "Segoe UI", system-ui, sans-serif',
        'mono' => 'ui-monospace, "SF Mono", Menlo, Consolas, monospace',
    ][$k] ?? null;
}

function token_keys(): array {
    return ['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'background' => 'Background', 'surface' => 'Surface', 'text' => 'Text', 'muted' => 'Muted text', 'button_text' => 'Button text', 'link' => 'Links'];
}

/** Effective tokens = template defaults overridden by validated shop colours. */
function shop_tokens(array $shop, ?string $templateKey = null): array {
    $t = template_get($templateKey ?? $shop['template_key']);
    $tokens = $t['tokens'];
    $custom = jdec(shop_setting((int) $shop['id'], 'colors'));
    if (($custom['_template'] ?? '') === $t['key'] || ($custom['_template'] ?? '') === '*') {
        foreach ($tokens as $k => $v) if (!empty($custom[$k]) && valid_hex($custom[$k])) $tokens[$k] = strtolower($custom[$k]);
    }
    return $tokens;
}

function tokens_css(array $shop, ?string $templateKey = null): string {
    $t = template_get($templateKey ?? $shop['template_key']);
    $tok = shop_tokens($shop, $t['key']);
    $css = ':root{';
    foreach ($tok as $k => $v) $css .= '--c-' . str_replace('_', '-', $k) . ':' . $v . ';';
    $fh = $t['fonts']['heading']; $fb = $t['fonts']['body'];
    if (shop_feature($shop, 'typography')) {
        $h = font_stack((string) shop_setting((int) $shop['id'], 'font_heading', '')); if ($h) $fh = $h;
        $b = font_stack((string) shop_setting((int) $shop['id'], 'font_body', '')); if ($b) $fb = $b;
    }
    $css .= '--f-heading:' . $fh . ';--f-body:' . $fb . ';--f-mono:' . ($t['fonts']['mono'] ?? 'ui-monospace, Menlo, monospace') . ';--radius:' . $t['radius'] . ';';
    return $css . '}';
}

/** WCAG relative-luminance contrast ratio for two hex colours. */
function contrast_ratio(string $a, string $b): float {
    $lum = static function (string $hex): float {
        $hex = ltrim($hex, '#');
        $c = array_map(static fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
        $c = array_map(static fn($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };
    $l1 = $lum($a); $l2 = $lum($b);
    return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
}
