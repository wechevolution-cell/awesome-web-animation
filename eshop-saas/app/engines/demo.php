<?php
declare(strict_types=1);

/**
 * Demo data: one demo shop per template so each design can be previewed with realistic content.
 * Everything here is SAMPLE data (flagged in the storefront with a "demo" announcement bar).
 */
function demo_datasets(): array {
    $d = 'Δείγμα προϊόντος για επίδειξη του προτύπου.';
    return [
        'fashion' => ['shape' => 'shirt', 'hue' => 15, 'cats' => ['Γυναικεία > Φορέματα', 'Γυναικεία > Μπλούζες', 'Ανδρικά > Πουκάμισα', 'Ανδρικά > Παντελόνια', 'Αξεσουάρ'], 'products' => [
            ['Γυναικεία > Φορέματα', 'Λινό φόρεμα midi', 6900, 5500, 'Ύφασμα: 100% λινό', 'Size: S, Color: Άμμος|Size: M, Color: Άμμος|Size: L, Color: Μαύρο'],
            ['Γυναικεία > Φορέματα', 'Σατέν φόρεμα με τιράντες', 7900, null, 'Ύφασμα: σατέν βισκόζη', ''],
            ['Γυναικεία > Μπλούζες', 'Βαμβακερό t-shirt basic', 1900, null, 'Ύφασμα: οργανικό βαμβάκι', 'Size: S|Size: M|Size: L'],
            ['Γυναικεία > Μπλούζες', 'Πλεκτό πουλόβερ', 5400, 4300, 'Ύφασμα: μαλλί merino', ''],
            ['Ανδρικά > Πουκάμισα', 'Λινό πουκάμισο', 4900, null, 'Ύφασμα: 100% λινό', 'Size: M|Size: L|Size: XL'],
            ['Ανδρικά > Πουκάμισα', 'Oxford πουκάμισο', 4500, null, 'Ύφασμα: βαμβάκι oxford', ''],
            ['Ανδρικά > Παντελόνια', 'Chino παντελόνι', 5900, null, 'Εφαρμογή: slim', 'Size: 30|Size: 32|Size: 34'],
            ['Ανδρικά > Παντελόνια', 'Τζιν straight', 6500, 5200, 'Ύφασμα: denim 12oz', ''],
            ['Αξεσουάρ', 'Δερμάτινη ζώνη', 2900, null, 'Υλικό: φυσικό δέρμα', ''],
            ['Αξεσουάρ', 'Μεταξωτό φουλάρι', 3500, null, 'Υλικό: 100% μετάξι', ''],
            ['Αξεσουάρ', 'Ψάθινο καπέλο', 3200, null, 'Υλικό: ψάθα', ''],
            ['Γυναικεία > Μπλούζες', 'Πουκάμισο popeline', 4200, null, 'Ύφασμα: βαμβάκι popeline', ''],
        ]],
        'home' => ['shape' => 'vase', 'hue' => 35, 'cats' => ['Φωτισμός', 'Καθιστικό', 'Κουζίνα', 'Διακόσμηση'], 'products' => [
            ['Φωτισμός', 'Επιτραπέζιο φωτιστικό κεραμικό', 8900, null, 'Υλικό: κεραμικό, Λάμπα: E27', ''],
            ['Φωτισμός', 'Κρεμαστό φωτιστικό ρατάν', 12900, 9900, 'Υλικό: ρατάν, Διάμετρος: 45 cm', ''],
            ['Καθιστικό', 'Λινό μαξιλάρι καναπέ', 2900, null, 'Διαστάσεις: 50×50 cm', 'Color: Λευκό|Color: Ώχρα|Color: Γκρι'],
            ['Καθιστικό', 'Πλεκτή κουβέρτα', 6400, null, 'Υλικό: βαμβάκι', ''],
            ['Καθιστικό', 'Χαμηλό τραπέζι δρυς', 24900, null, 'Υλικό: μασίφ δρυς', ''],
            ['Κουζίνα', 'Σετ κεραμικά πιάτα (4)', 5600, 4500, 'Πλύσιμο: πλυντήριο', ''],
            ['Κουζίνα', 'Ξύλινη επιφάνεια κοπής', 3400, null, 'Υλικό: ξύλο ελιάς', ''],
            ['Κουζίνα', 'Γυάλινη καράφα', 2700, null, 'Χωρητικότητα: 1 L', ''],
            ['Διακόσμηση', 'Κεραμικό βάζο', 3900, null, 'Ύψος: 28 cm', ''],
            ['Διακόσμηση', 'Αρωματικό κερί σόγιας', 1800, null, 'Διάρκεια: 40 ώρες', ''],
            ['Διακόσμηση', 'Καθρέφτης τοίχου στρογγυλός', 9900, null, 'Διάμετρος: 60 cm', ''],
            ['Φωτισμός', 'Φωτιστικό δαπέδου αλουμινίου', 15900, null, 'Ύψος: 160 cm', ''],
        ]],
        'luxury' => ['shape' => 'watch', 'hue' => 42, 'cats' => ['Ρολόγια', 'Κοσμήματα', 'Δερμάτινα'], 'products' => [
            ['Ρολόγια', 'Αυτόματο ρολόι ατσάλι', 64000, null, 'Κάσα: 40 mm, Κίνηση: αυτόματη', ''],
            ['Ρολόγια', 'Χρονογράφος δερμάτινο λουρί', 48000, 42000, 'Κάσα: 42 mm', ''],
            ['Ρολόγια', 'Μινιμαλιστικό ρολόι χαλαζία', 21000, null, 'Κάσα: 38 mm', ''],
            ['Κοσμήματα', 'Χρυσό δαχτυλίδι 14K', 32000, null, 'Υλικό: χρυσός 14K', 'Size: 52|Size: 54|Size: 56'],
            ['Κοσμήματα', 'Ασημένιο κολιέ', 12000, null, 'Υλικό: ασήμι 925', ''],
            ['Κοσμήματα', 'Σκουλαρίκια με μαργαριτάρι', 15000, null, 'Υλικό: ασήμι 925', ''],
            ['Δερμάτινα', 'Δερμάτινος χαρτοφύλακας', 39000, null, 'Υλικό: δέρμα full grain', ''],
            ['Δερμάτινα', 'Πορτοφόλι δερμάτινο', 9500, 7900, 'Υλικό: δέρμα', ''],
            ['Δερμάτινα', 'Δερμάτινη τσάντα ώμου', 28000, null, 'Υλικό: δέρμα', ''],
        ]],
        'tech' => ['shape' => 'device', 'hue' => 225, 'cats' => ['Ήχος > Ακουστικά', 'Ήχος > Ηχεία', 'Smart Home', 'Αξεσουάρ κινητού', 'Gaming'], 'products' => [
            ['Ήχος > Ακουστικά', 'Ασύρματα ακουστικά ANC', 18900, 15900, 'Μπαταρία: 30 ώρες, Bluetooth: 5.3', 'Color: Μαύρο|Color: Λευκό'],
            ['Ήχος > Ακουστικά', 'True wireless earbuds', 8900, null, 'Μπαταρία: 6+24 ώρες', ''],
            ['Ήχος > Ηχεία', 'Φορητό ηχείο αδιάβροχο', 7900, null, 'Προστασία: IP67', ''],
            ['Ήχος > Ηχεία', 'Soundbar 2.1', 24900, null, 'Ισχύς: 120 W', ''],
            ['Smart Home', 'Έξυπνη λάμπα RGB', 1900, null, 'Συνδεσιμότητα: Wi-Fi', ''],
            ['Smart Home', 'Κάμερα ασφαλείας εσωτερικού χώρου', 4900, 3900, 'Ανάλυση: 2K', ''],
            ['Smart Home', 'Έξυπνη πρίζα', 1500, null, 'Μέτρηση κατανάλωσης: ναι', ''],
            ['Αξεσουάρ κινητού', 'Φορτιστής GaN 65W', 3900, null, 'Θύρες: 2× USB-C, 1× USB-A', ''],
            ['Αξεσουάρ κινητού', 'Power bank 20.000 mAh', 4500, null, 'Έξοδος: 22.5 W', ''],
            ['Gaming', 'Μηχανικό πληκτρολόγιο', 9900, 8400, 'Διακόπτες: tactile', ''],
            ['Gaming', 'Ασύρματο ποντίκι gaming', 6900, null, 'Αισθητήρας: 26K DPI', ''],
            ['Gaming', 'Gaming headset', 7400, null, 'Μικρόφωνο: αποσπώμενο', ''],
        ]],
        'organic' => ['shape' => 'bottle', 'hue' => 95, 'cats' => ['Περιποίηση προσώπου', 'Σώμα', 'Αρώματα σπιτιού', 'Βότανα'], 'products' => [
            ['Περιποίηση προσώπου', 'Ενυδατική κρέμα προσώπου', 2400, null, 'Συστατικά: αλόη, υαλουρονικό', ''],
            ['Περιποίηση προσώπου', 'Ορός βιταμίνης C', 2900, 2400, 'Περιεκτικότητα: 15%', ''],
            ['Περιποίηση προσώπου', 'Καθαριστικό gel', 1600, null, 'Τύπος δέρματος: όλοι', ''],
            ['Σώμα', 'Σαπούνι ελαιολάδου', 600, null, 'Βάρος: 120 g', ''],
            ['Σώμα', 'Βούτυρο σώματος καριτέ', 1900, null, 'Συσκευασία: 200 ml', ''],
            ['Σώμα', 'Λάδι μασάζ λεβάντας', 1700, null, 'Συσκευασία: 100 ml', ''],
            ['Αρώματα σπιτιού', 'Αρωματικό χώρου ξυλάκια', 2200, null, 'Άρωμα: συκιά', ''],
            ['Αρώματα σπιτιού', 'Κερί κεραμικό λεμονόχορτο', 2500, null, 'Διάρκεια: 45 ώρες', ''],
            ['Βότανα', 'Τσάι του βουνού', 450, null, 'Βάρος: 30 g', ''],
            ['Βότανα', 'Χαμομήλι βιολογικό', 400, null, 'Βάρος: 25 g', ''],
        ]],
        'grocery' => ['shape' => 'jar', 'hue' => 130, 'cats' => ['Φρούτα & λαχανικά', 'Γαλακτοκομικά', 'Ελαιόλαδο & μέλι', 'Σνακ'], 'products' => [
            ['Ελαιόλαδο & μέλι', 'Εξαιρετικό παρθένο ελαιόλαδο 1L', 1290, null, 'Οξύτητα: ≤0,3%', 'Size: 500 ml|Size: 1 L|Size: 5 L'],
            ['Ελαιόλαδο & μέλι', 'Θυμαρίσιο μέλι 450g', 990, null, 'Προέλευση: Ελλάδα', ''],
            ['Ελαιόλαδο & μέλι', 'Ελιές Καλαμών', 540, 450, 'Βάρος: 350 g', ''],
            ['Γαλακτοκομικά', 'Γιαούρτι στραγγιστό', 320, null, 'Λιπαρά: 2%', ''],
            ['Γαλακτοκομικά', 'Φέτα ΠΟΠ', 890, null, 'Βάρος: 400 g', ''],
            ['Γαλακτοκομικά', 'Κεφαλογραβιέρα', 1190, null, 'Βάρος: 500 g', ''],
            ['Φρούτα & λαχανικά', 'Ντομάτες βιολογικές 1kg', 380, null, 'Κατηγορία: Α', ''],
            ['Φρούτα & λαχανικά', 'Πορτοκάλια χυμού 2kg', 290, null, 'Προέλευση: Ελλάδα', ''],
            ['Σνακ', 'Παστέλι με σουσάμι', 210, null, 'Βάρος: 60 g', ''],
            ['Σνακ', 'Κράκερ ολικής άλεσης', 250, null, 'Βάρος: 200 g', ''],
            ['Σνακ', 'Μείγμα ξηρών καρπών', 640, 520, 'Βάρος: 250 g', ''],
        ]],
        'sports' => ['shape' => 'shoe', 'hue' => 330, 'cats' => ['Τρέξιμο', 'Γυμναστήριο', 'Ποδήλατο', 'Ένδυση'], 'products' => [
            ['Τρέξιμο', 'Παπούτσια τρεξίματος', 11900, 9500, 'Drop: 8 mm', 'Size: 41|Size: 42|Size: 43|Size: 44'],
            ['Τρέξιμο', 'Παπούτσια trail', 13900, null, 'Σόλα: Vibram', ''],
            ['Τρέξιμο', 'Ζώνη τρεξίματος', 1900, null, 'Υλικό: lycra', ''],
            ['Γυμναστήριο', 'Αλτήρες ρυθμιζόμενοι', 14900, null, 'Βάρος: 2–24 kg', ''],
            ['Γυμναστήριο', 'Στρώμα yoga', 3500, null, 'Πάχος: 6 mm', ''],
            ['Γυμναστήριο', 'Λάστιχα αντίστασης σετ', 2400, null, 'Επίπεδα: 5', ''],
            ['Ποδήλατο', 'Κράνος ποδηλάτου', 6900, 5500, 'Πιστοποίηση: EN 1078', ''],
            ['Ποδήλατο', 'Φώτα ποδηλάτου USB', 2900, null, 'Φωτεινότητα: 400 lm', ''],
            ['Ένδυση', 'Τεχνικό t-shirt', 2500, null, 'Ύφασμα: quick-dry', 'Size: S|Size: M|Size: L'],
            ['Ένδυση', 'Αντιανεμικό μπουφάν', 7900, null, 'Αδιαβροχοποίηση: DWR', ''],
            ['Ένδυση', 'Κολάν τρεξίματος', 3900, null, 'Ύφασμα: συμπίεσης', ''],
        ]],
    ];
}

function demo_template_map(): array {
    return ['aurora' => 'home', 'maison' => 'fashion', 'noir' => 'luxury', 'pulse' => 'tech', 'vogue' => 'fashion', 'bazaar' => 'mix', 'terra' => 'organic', 'atelier' => 'home', 'fresh' => 'grocery', 'volt' => 'sports'];
}

/** Draws a simple studio-style "product photo" (background + soft shadow + silhouette) as WebP. */
function demo_image(int $shopId, string $shape, int $hue, int $seed, string $name): ?int {
    if (!function_exists('imagewebp')) return null;
    $w = 900;
    $im = imagecreatetruecolor($w, $w);
    $hsl = static function (float $h, float $s, float $l) use ($im) {
        $h = fmod($h, 360) / 360; $q = $l < .5 ? $l * (1 + $s) : $l + $s - $l * $s; $p = 2 * $l - $q;
        $f = static function ($t) use ($p, $q) { if ($t < 0) $t += 1; if ($t > 1) $t -= 1; return $t < 1 / 6 ? $p + ($q - $p) * 6 * $t : ($t < .5 ? $q : ($t < 2 / 3 ? $p + ($q - $p) * (2 / 3 - $t) * 6 : $p)); };
        return imagecolorallocate($im, (int) round($f($h + 1 / 3) * 255), (int) round($f($h) * 255), (int) round($f($h - 1 / 3) * 255));
    };
    $bgH = $hue + ($seed % 5) * 6;
    for ($y = 0; $y < $w; $y += 6) imagefilledrectangle($im, 0, $y, $w, $y + 6, $hsl($bgH, .22, .90 - $y / $w * .08));
    $shadow = imagecolorallocatealpha($im, 0, 0, 0, 100);
    imagefilledellipse($im, 450, 770, 520, 70, $shadow);
    $main = $hsl($hue + ($seed * 37) % 60 - 30, .35 + ($seed % 3) * .1, .45 + ($seed % 4) * .06);
    $dark = $hsl($hue + ($seed * 37) % 60 - 30, .35, .28);
    $light = imagecolorallocatealpha($im, 255, 255, 255, 80);
    switch ($shape) {
        case 'shirt':
            imagefilledpolygon($im, [300, 200, 380, 170, 450, 210, 520, 170, 600, 200, 720, 330, 640, 390, 610, 360, 610, 760, 290, 760, 290, 360, 260, 390, 180, 330], $main);
            imagefilledpolygon($im, [380, 170, 450, 250, 520, 170, 450, 210], $dark);
            imageline($im, 450, 250, 450, 760, $dark);
            break;
        case 'vase':
            imagefilledellipse($im, 450, 560, 380, 420, $main);
            imagefilledrectangle($im, 395, 230, 505, 400, $main);
            imagefilledellipse($im, 450, 230, 150, 40, $dark);
            imagefilledellipse($im, 380, 520, 60, 200, $light);
            break;
        case 'watch':
            imagefilledrectangle($im, 395, 120, 505, 780, $dark);
            imagefilledellipse($im, 450, 450, 330, 330, $hsl(40, .5, .55));
            imagefilledellipse($im, 450, 450, 290, 290, $hsl(220, .15, .15));
            imageline($im, 450, 450, 450, 340, $light); imageline($im, 450, 450, 530, 470, $light);
            imagefilledrectangle($im, 615, 435, 640, 465, $hsl(40, .5, .55));
            break;
        case 'device':
            imagefilledrectangle($im, 250, 230, 650, 690, $dark);
            imagefilledrectangle($im, 270, 250, 630, 670, $main);
            imagefilledellipse($im, 450, 460, 200, 200, $light);
            imagefilledellipse($im, 450, 460, 90, 90, $dark);
            break;
        case 'bottle':
            imagefilledrectangle($im, 340, 330, 560, 760, $main);
            imagefilledellipse($im, 450, 330, 220, 80, $main);
            imagefilledrectangle($im, 405, 200, 495, 300, $dark);
            imagefilledrectangle($im, 360, 470, 540, 620, $light);
            break;
        case 'jar':
            imagefilledrectangle($im, 300, 330, 600, 740, $main);
            imagefilledellipse($im, 450, 740, 300, 60, $main);
            imagefilledrectangle($im, 290, 260, 610, 330, $dark);
            imagefilledrectangle($im, 330, 430, 570, 600, $light);
            break;
        case 'shoe':
            imagefilledpolygon($im, [170, 640, 210, 470, 330, 450, 420, 380, 520, 420, 640, 520, 760, 560, 760, 660, 170, 660], $main);
            imagefilledrectangle($im, 170, 650, 760, 700, $dark);
            imageline($im, 330, 470, 520, 470, $light); imageline($im, 340, 510, 560, 510, $light);
            break;
        default:
            imagefilledellipse($im, 450, 450, 420, 420, $main);
    }
    $sid = $shopId;
    $rel = $sid . '/demo';
    $dir = UPLOADS . '/' . $rel;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = slugify($name, 50) . '-' . $seed . '.webp';
    imagewebp($im, "$dir/$file", 78);
    $small = imagescale($im, 400, 400);
    $sf = slugify($name, 50) . '-' . $seed . '-400.webp';
    if ($small) { imagewebp($small, "$dir/$sf", 75); imagedestroy($small); }
    imagedestroy($im);
    return insert('media', ['shop_id' => $sid, 'filename' => $file, 'path' => "$rel/$file", 'mime' => 'image/webp', 'width' => $w, 'height' => $w, 'size_bytes' => (int) filesize("$dir/$file") + (int) @filesize("$dir/$sf"),
        'variants' => jenc(['webp' => [400 => "$rel/$sf", 900 => "$rel/$file"]]), 'alt' => $name, 'created_at' => now()]);
}

/** Creates one demo shop per template (idempotent by slug). */
function demo_create_all(): array {
    $plan = row("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY active_product_limit DESC LIMIT 1");
    $sets = demo_datasets();
    $sets['mix'] = ['shape' => 'device', 'hue' => 20, 'cats' => [], 'products' => []];
    foreach (['tech', 'home', 'sports', 'organic'] as $k) {
        $sets['mix']['cats'] = array_merge($sets['mix']['cats'], $sets[$k]['cats']);
        foreach (array_slice($sets[$k]['products'], 0, 6) as $p) { $p[] = $sets[$k]['shape']; $p[] = $sets[$k]['hue']; $sets['mix']['products'][] = $p; }
    }
    $created = [];
    $i = 0;
    foreach (demo_template_map() as $tpl => $setKey) {
        $slug = 'demo-' . $tpl;
        if (val('SELECT 1 FROM shops WHERE slug = ?', [$slug])) continue;
        $m = template_get($tpl);
        $set = $sets[$setKey];
        $r = shop_provision(['name' => $m['name'] . ' Demo', 'slug' => $slug, 'email' => "demo-$tpl@example.com", 'admin_email' => "owner+$tpl@example.com", 'plan_id' => $plan['id'], 'months' => 12, 'template_key' => $tpl, 'locale' => 'el', 'is_demo' => 1, 'status' => 'ACTIVE']);
        $sid = $r['shop_id'];
        $shop = shop_by_id($sid);
        shop_setting_set($sid, 'announcement', 'Demo κατάστημα — τα προϊόντα και οι τιμές είναι ενδεικτικά. / Demo store — sample products and prices.');
        shop_setting_set($sid, 'tagline', 'Κατάστημα επίδειξης του προτύπου ' . $m['name'] . ' (' . $m['style'] . ').');
        shop_setting_set($sid, 'contact_email', "demo-$tpl@example.com");
        update('seo_settings', ['noindex_site' => 1, 'meta_description' => 'Demo store for the ' . $m['name'] . ' template.'], 'shop_id = ?', [$sid]);
        $cache = [];
        foreach ($set['cats'] as $c) category_ensure_path($sid, $c, $cache);
        $seed = 0; $heroImg = null;
        foreach ($set['products'] as $p) {
            [$cat, $name, $price, $sale, $spec, $variants] = $p;
            $shape = $p[6] ?? $set['shape']; $hue = $p[7] ?? $set['hue'];
            $mid = demo_image($sid, $shape, (int) $hue, ++$seed, $name);
            $mid2 = $seed % 3 === 0 ? demo_image($sid, $shape, (int) $hue + 25, $seed + 100, $name . ' 2') : null;
            $heroImg ??= $mid;
            $catId = category_ensure_path($sid, $cat, $cache);
            $vIn = ['variant_options' => [], 'variant_sku' => [], 'variant_price' => [], 'variant_stock' => [], 'variant_id' => []];
            foreach (array_filter(explode('|', $variants)) as $vi => $vo) { $vIn['variant_options'][] = $vo; $vIn['variant_sku'][] = 'D' . $sid . '-' . $seed . '-' . $vi; $vIn['variant_price'][] = ''; $vIn['variant_stock'][] = 5 + $vi; $vIn['variant_id'][] = 0; }
            $attrs = array_map('trim', explode(',', $spec));
            product_save($shop, null, $vIn + [
                'name' => $name, 'sku' => 'D' . $sid . '-' . str_pad((string) $seed, 3, '0', STR_PAD_LEFT), 'price' => cents_input($price), 'sale_price' => $sale ? cents_input($sale) : '',
                'stock' => 4 + ($seed * 7) % 30, 'manage_stock' => 1, 'brand' => 'Demo', 'categories' => [$catId], 'featured' => $seed <= 8 ? 1 : 0,
                'short_description' => 'Δείγμα προϊόντος: ' . mb_strtolower($name) . '. ' . 'Κείμενο προτύπου — ελέγξτε πριν τη δημοσίευση.',
                'description' => '<p>' . e($name) . ' — δείγμα περιγραφής για την επίδειξη του προτύπου. Τα στοιχεία είναι ενδεικτικά.</p><ul><li>' . e($attrs[0] ?? '') . '</li></ul>',
                'images' => array_filter([$mid, $mid2]), 'attr_name' => array_map(static fn($a) => trim(explode(':', $a)[0]), $attrs), 'attr_value' => array_map(static fn($a) => trim(explode(':', $a, 2)[1] ?? $a), $attrs),
                'status' => 'active', 'faq' => "Q: Είναι αυτό πραγματικό προϊόν;\nA: Όχι, είναι δείγμα για την προβολή του προτύπου.",
            ]);
        }
        $hero = row("SELECT id, data FROM homepage_sections WHERE shop_id = ? AND type = 'hero'", [$sid]);
        $heroCopy = ['aurora' => ['Απλά πράγματα, καλά φτιαγμένα.', 'Αντικείμενα για το σπίτι με καθαρές γραμμές.'], 'maison' => ['Η νέα συλλογή', 'Διαχρονικά κομμάτια σε φυσικά υφάσματα.'], 'noir' => ['Χρόνος. Μέταλλο. Δέρμα.', 'Επιλεγμένα αντικείμενα που κρατούν μια ζωή.'],
            'pulse' => ['Τεχνολογία που απλώς δουλεύει.', 'Ήχος, smart home και gaming σε ένα μέρος.'], 'vogue' => ['Νέα εποχή', 'Μια εκδοτική ματιά στη μόδα της σεζόν.'], 'bazaar' => ['Όλα όσα χρειάζεστε, σε ένα καλάθι.', 'Χιλιάδες κωδικοί, γρήγορη αναζήτηση.'],
            'terra' => ['Φροντίδα από τη φύση.', 'Φυσικά καλλυντικά και βότανα σε απλές συνταγές.'], 'atelier' => ['Αντικείμενα με αρχιτεκτονική σκέψη', 'Έπιπλα και φωτισμός για ήρεμους χώρους.'], 'fresh' => ['Φρέσκα, κάθε μέρα.', 'Παντοπωλείο με ελληνικά προϊόντα, στην πόρτα σας.'], 'volt' => ['Κινήσου πιο γρήγορα.', 'Εξοπλισμός για τρέξιμο, γυμναστήριο και ποδήλατο.']][$tpl];
        update('homepage_sections', ['data' => jenc(['title' => $heroCopy[0], 'subtitle' => $heroCopy[1], 'eyebrow' => 'Demo · ' . $m['style'], 'cta_text' => 'Δείτε τα προϊόντα', 'cta_link' => 'products', 'image_id' => null, 'image2_id' => null])], 'id = ?', [$hero['id']]);
        update('homepage_sections', ['data' => jenc(['title' => 'Δωρεάν αποστολή για παραγγελίες άνω των 50 € (παράδειγμα)', 'text' => 'Κείμενο προτύπου — ελέγξτε πριν τη δημοσίευση.', 'cta_text' => 'Αγορά', 'cta_link' => 'products', 'image_id' => null])], "shop_id = ? AND type = 'banner'", [$sid]);
        $created[] = $sid;
        $i++;
    }
    return $created;
}
