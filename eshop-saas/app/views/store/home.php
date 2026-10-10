<?php
$hasHero = false;
foreach ($sections as $s) {
    $file = VIEWS . '/store/sections/' . $s['type'] . '.php';
    if (!is_file($file)) continue;
    if ($s['type'] === 'hero') $hasHero = true;
    partial('store/sections/' . $s['type'], ['s' => $s, 'd' => $s['d'], 'shop' => $shop]);
}
if (!$hasHero) echo '<h1 class="sr">' . e($shop['name']) . '</h1>';
