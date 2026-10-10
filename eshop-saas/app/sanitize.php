<?php
declare(strict_types=1);

/**
 * Allow-list HTML sanitizer (DOM based). Anything not explicitly allowed is removed.
 * No <script>, no on* attributes, no javascript:/data: URLs, iframes only from YouTube/Vimeo/Google Maps.
 */
function sanitize_html(?string $html): string {
    $html = (string) $html;
    if (trim($html) === '') return '';
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'small' => [], 'mark' => [], 'sub' => [], 'sup' => [],
        'a' => ['href', 'title', 'rel', 'target'], 'ul' => [], 'ol' => ['start'], 'li' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'blockquote' => ['cite'], 'code' => [], 'pre' => [], 'hr' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['scope', 'colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'caption' => [],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'title'], 'figure' => [], 'figcaption' => [],
        'span' => [], 'div' => [], 'dl' => [], 'dt' => [], 'dd' => [], 'details' => [], 'summary' => [],
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading'],
    ];
    $drop = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'frame', 'frameset', 'applet'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if (!$root) return '';

    $walk = static function (DOMNode $node) use (&$walk, $allowed, $drop, $doc): void {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) { $node->removeChild($child); continue; }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->nodeName);
            if (in_array($tag, $drop, true)) { $node->removeChild($child); continue; }
            if (!isset($allowed[$tag])) {
                $walk($child);
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                $attr = $child->attributes->item($a);
                $name = strtolower($attr->nodeName);
                if (!in_array($name, $allowed[$tag], true)) { $child->removeAttribute($attr->nodeName); continue; }
                if (in_array($name, ['href', 'src', 'cite'], true) && !safe_url($attr->nodeValue, $tag === 'img')) $child->removeAttribute($attr->nodeName);
            }
            if ($tag === 'iframe') {
                $src = $child->getAttribute('src');
                if (!preg_match('#^https://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/|player\.vimeo\.com/video/|google\.com/maps/embed|maps\.google\.com/maps)#i', $src)) { $node->removeChild($child); continue; }
                $child->setAttribute('loading', 'lazy');
            }
            if ($tag === 'a') {
                if ($child->getAttribute('target') === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
                elseif ($child->hasAttribute('target')) $child->removeAttribute('target');
            }
            if ($tag === 'img' && !$child->hasAttribute('loading')) $child->setAttribute('loading', 'lazy');
            $walk($child);
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}

function safe_url(string $url, bool $image = false): bool {
    $u = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    $u = preg_replace('/[\x00-\x20]+/', '', $u) ?? '';
    if ($u === '') return false;
    if (preg_match('#^(https?:|mailto:|tel:|/|\#|\?)#i', $u)) return !str_starts_with($u, '//') || $image;
    if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $u)) return false; // javascript:, data:, vbscript:...
    return true; // relative
}

/** Plain text, single line. */
function clean_text(mixed $s, int $max = 255): string {
    $s = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $s)) ?? '');
    return mb_substr($s, 0, $max);
}
function clean_multiline(mixed $s, int $max = 5000): string {
    $s = str_replace("\r\n", "\n", strip_tags((string) $s));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}
function clean_url(mixed $s): string { $s = clean_text($s, 500); return safe_url($s) ? $s : ''; }
