<?php
declare(strict_types=1);

/**
 * Small Markdown importer: headings, lists, tables, blockquotes, code blocks, links, bold/italic.
 * A "## FAQ" (or "## Συχνές ερωτήσεις") section whose "### Question" headings are followed by
 * answer paragraphs is extracted into a repeatable FAQ structure.
 * Returns ['html' => sanitized html, 'faq' => [[q, a], ...], 'title' => first H1 or null].
 */
function markdown_import(string $md): array {
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $lines = explode("\n", $md);
    $html = ''; $faq = []; $title = null; $inFaq = false; $faqQ = null; $faqA = [];
    $flushFaq = static function () use (&$faq, &$faqQ, &$faqA): void {
        if ($faqQ !== null) $faq[] = ['q' => $faqQ, 'a' => trim(implode("\n", $faqA))];
        $faqQ = null; $faqA = [];
    };
    $inline = static function (string $t): string {
        $t = e($t);
        $t = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $t) ?? $t;
        $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t) ?? $t;
        $t = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\w)/u', '<em>$1</em>', $t) ?? $t;
        $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', static fn($m) => safe_url(html_entity_decode($m[2])) ? '<a href="' . $m[2] . '">' . $m[1] . '</a>' : $m[1], $t) ?? $t;
        return $t;
    };
    $i = 0; $n = count($lines);
    while ($i < $n) {
        $line = $lines[$i];
        if (preg_match('/^```/u', $line)) {
            $code = []; $i++;
            while ($i < $n && !preg_match('/^```/u', $lines[$i])) $code[] = $lines[$i++];
            $i++;
            $html .= '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
            continue;
        }
        if (preg_match('/^(#{1,4})\s+(.+?)\s*#*$/u', $line, $m)) {
            $lvl = strlen($m[1]); $text = trim($m[2]);
            if ($lvl === 1) { $title ??= $text; $i++; continue; }
            if ($lvl === 2) {
                if ($inFaq) $flushFaq();
                $inFaq = (bool) preg_match('/^(faq|frequently asked|συχνές ερωτήσεις|ερωτήσεις)/iu', $text);
                if ($inFaq) { $i++; continue; }
            }
            if ($inFaq && $lvl === 3) { $flushFaq(); $faqQ = $text; $i++; continue; }
            $html .= "<h$lvl id=\"" . e(slugify($text)) . "\">" . $inline($text) . "</h$lvl>";
            $i++; continue;
        }
        if ($inFaq) { if ($faqQ !== null) $faqA[] = $line; $i++; continue; }
        if (preg_match('/^\s*\|.*\|\s*$/u', $line) && isset($lines[$i + 1]) && preg_match('/^\s*\|?\s*:?-{2,}/u', $lines[$i + 1])) {
            $cells = static fn($l) => array_map('trim', explode('|', trim(trim($l), '|')));
            $head = $cells($line); $i += 2;
            $t = '<table><thead><tr>' . implode('', array_map(static fn($c) => '<th scope="col">' . $inline($c) . '</th>', $head)) . '</tr></thead><tbody>';
            while ($i < $n && preg_match('/^\s*\|.*\|\s*$/u', $lines[$i])) {
                $t .= '<tr>' . implode('', array_map(static fn($c) => '<td>' . $inline($c) . '</td>', $cells($lines[$i]))) . '</tr>';
                $i++;
            }
            $html .= $t . '</tbody></table>';
            continue;
        }
        if (preg_match('/^\s*([-*+]|\d+[.)])\s+/u', $line, $m)) {
            $ordered = ctype_digit($m[1][0]);
            $items = [];
            while ($i < $n && preg_match('/^\s*([-*+]|\d+[.)])\s+(.*)$/u', $lines[$i], $mm)) { $items[] = $inline($mm[2]); $i++; }
            $tag = $ordered ? 'ol' : 'ul';
            $html .= "<$tag><li>" . implode('</li><li>', $items) . "</li></$tag>";
            continue;
        }
        if (preg_match('/^>\s?(.*)$/u', $line)) {
            $q = [];
            while ($i < $n && preg_match('/^>\s?(.*)$/u', $lines[$i], $mm)) { $q[] = $inline($mm[1]); $i++; }
            $html .= '<blockquote><p>' . implode('<br>', $q) . '</p></blockquote>';
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})\s*$/u', $line)) { $html .= '<hr>'; $i++; continue; }
        if (trim($line) === '') { $i++; continue; }
        $p = [];
        while ($i < $n && trim($lines[$i]) !== '' && !preg_match('/^(#{1,4}\s|```|>|\s*([-*+]|\d+[.)])\s|\s*\|)/u', $lines[$i])) $p[] = $inline(trim($lines[$i++]));
        $html .= '<p>' . implode(' ', $p) . '</p>';
    }
    if ($inFaq) $flushFaq();
    return ['html' => sanitize_html($html), 'faq' => $faq, 'title' => $title];
}

/** FAQ textarea format: "Q: question" line followed by answer lines; blank line separates. */
function faq_parse(string $text): array {
    $out = []; $q = null; $a = [];
    foreach (preg_split('/\R/u', $text) as $line) {
        if (preg_match('/^\s*(Q|Ε|Ερώτηση|Question)\s*[:：]\s*(.+)$/iu', $line, $m)) {
            if ($q !== null && trim(implode(' ', $a)) !== '') $out[] = ['q' => $q, 'a' => trim(implode("\n", $a))];
            $q = clean_text($m[2], 300); $a = [];
        } elseif ($q !== null) {
            $a[] = preg_replace('/^\s*(A|Α|Απάντηση|Answer)\s*[:：]\s*/iu', '', $line);
        }
    }
    if ($q !== null && trim(implode(' ', $a)) !== '') $out[] = ['q' => $q, 'a' => trim(implode("\n", $a))];
    return array_slice(array_map(static fn($f) => ['q' => $f['q'], 'a' => clean_multiline($f['a'], 2000)], $out), 0, 30);
}
function faq_to_text(array $faq): string {
    return implode("\n\n", array_map(static fn($f) => 'Q: ' . ($f['q'] ?? '') . "\nA: " . ($f['a'] ?? ''), $faq));
}
