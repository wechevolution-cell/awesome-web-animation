<?php
// Fetches pages and validates every JSON-LD block parses and has @context/@graph with typed nodes.
// php tests/jsonld-check.php http://127.0.0.1:8000 /path1 /path2 ...
$base = $argv[1]; $bad = 0; $n = 0;
foreach (array_slice($argv, 2) as $p) {
    $html = @file_get_contents($base . $p);
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', (string) $html, $m);
    if (!$m[1]) { echo "NO JSON-LD $p\n"; $bad++; continue; }
    foreach ($m[1] as $j) {
        $n++;
        $d = json_decode($j, true);
        $types = $d ? array_map(fn($x) => $x['@type'] ?? '?', $d['@graph'] ?? []) : [];
        if (!$d || ($d['@context'] ?? '') !== 'https://schema.org' || !$types || in_array('?', $types, true)) { echo "INVALID $p\n"; $bad++; }
        else echo "ok  $p  [" . implode(', ', $types) . "]\n";
    }
}
echo "$n blocks, $bad problems\n"; exit($bad ? 1 : 0);
