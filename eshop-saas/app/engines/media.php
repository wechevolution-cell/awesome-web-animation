<?php
declare(strict_types=1);

const MEDIA_MAX_BYTES = 10 * 1048576;
const MEDIA_MAX_DIM = 2000;
const MEDIA_WIDTHS = [400, 800, 1600];

function media_storage_used(int $shopId): int { return (int) val('SELECT COALESCE(SUM(size_bytes), 0) FROM media WHERE shop_id = ?', [$shopId]); }

/**
 * Validate & store an uploaded image (JPG/PNG/WebP/GIF, ≤10 MB, getimagesize + finfo),
 * resize to ≤2000px, write WebP responsive variants (+AVIF when enabled & supported).
 */
function media_upload(array $shop, array $file, ?int $replaceId = null, string $alt = ''): int {
    $sid = (int) $shop['id'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new UserError(match ($file['error'] ?? 0) { UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large (max 10 MB).', UPLOAD_ERR_NO_FILE => 'Choose a file to upload.', default => 'Upload failed. Please try again.' });
    }
    $tmp = (string) $file['tmp_name'];
    if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) throw new UserError('Invalid upload.');
    $size = (int) filesize($tmp);
    if ($size > MEDIA_MAX_BYTES) throw new UserError('The file is too large (max 10 MB).');
    if (media_storage_used($sid) + $size > shop_storage_limit_bytes($shop)) throw new UserError('Storage limit of your package reached. Delete unused media first.');
    $info = @getimagesize($tmp);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) throw new UserError('Only JPG, PNG, WebP and GIF images are allowed.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || $mime !== $info['mime']) throw new UserError('The file content does not match an allowed image type.');
    if ($info[0] * $info[1] > 40_000_000) throw new UserError('Image dimensions are too large.');
    $ext = $types[$info[2]];
    $base = slugify(pathinfo((string) $file['name'], PATHINFO_FILENAME), 60) ?: 'image';
    $rel = $sid . '/' . date('Y/m');
    $dir = UPLOADS . '/' . $rel;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new UserError('Upload folder is not writable.');
    $name = $base; $i = 1;
    while (file_exists("$dir/$name.$ext")) $name = $base . '-' . (++$i);

    $src = media_gd_load($tmp, $info[2]);
    $w = $info[0]; $h = $info[1];
    $variants = [];
    if ($src && !($info[2] === IMAGETYPE_GIF && $w <= MEDIA_MAX_DIM && $h <= MEDIA_MAX_DIM)) {
        [$src, $w, $h] = media_fit($src, $w, $h, MEDIA_MAX_DIM);
        media_gd_save($src, "$dir/$name.$ext", $info[2]);
    } else {
        if (!move_uploaded_file_safe($tmp, "$dir/$name.$ext")) throw new UserError('Could not store the file.');
    }
    if ($src && function_exists('imagewebp')) {
        foreach (MEDIA_WIDTHS as $vw) {
            if ($vw >= $w && $vw !== MEDIA_WIDTHS[0]) continue;
            [$res, $rw, $rh] = media_fit($src, $w, $h, $vw, true);
            $vf = "$name-$vw.webp";
            imagewebp($res, "$dir/$vf", 80);
            $variants['webp'][$rw] = "$rel/$vf";
            if (setting('avif_enabled') === '1' && function_exists('imageavif')) { $af = "$name-$vw.avif"; @imageavif($res, "$dir/$af", 55, 8); if (is_file("$dir/$af")) $variants['avif'][$rw] = "$rel/$af"; }
            if ($res !== $src) imagedestroy($res);
        }
    }
    if ($src) imagedestroy($src);
    $stored = (int) @filesize("$dir/$name.$ext");
    foreach ($variants as $set) foreach ($set as $vp) $stored += (int) @filesize(UPLOADS . '/' . $vp);
    $data = ['filename' => "$name.$ext", 'path' => "$rel/$name.$ext", 'external_url' => null, 'mime' => $mime, 'width' => $w, 'height' => $h, 'size_bytes' => $stored, 'variants' => jenc($variants)];
    if ($replaceId) {
        $old = owned('media', $replaceId, $sid);
        media_unlink_files($old);
        update('media', $data, 'id = ? AND shop_id = ?', [$replaceId, $sid]);
        audit('media.replaced', 'media', $replaceId, $sid);
        return $replaceId;
    }
    $id = insert('media', $data + ['shop_id' => $sid, 'alt' => clean_text($alt, 200) ?: null, 'created_at' => now()]);
    audit('media.uploaded', 'media', $id, $sid);
    return $id;
}
function move_uploaded_file_safe(string $from, string $to): bool { return PHP_SAPI === 'cli' ? copy($from, $to) : move_uploaded_file($from, $to); }

function media_gd_load(string $file, int $type): ?GdImage {
    $im = match ($type) { IMAGETYPE_JPEG => @imagecreatefromjpeg($file), IMAGETYPE_PNG => @imagecreatefrompng($file), IMAGETYPE_WEBP => @imagecreatefromwebp($file), IMAGETYPE_GIF => @imagecreatefromgif($file), default => false };
    if (!$im) return null;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) { $r = imagerotate($im, $rot, 0); if ($r) { imagedestroy($im); $im = $r; } }
    }
    imagepalettetotruecolor($im);
    imagealphablending($im, true);
    imagesavealpha($im, true);
    return $im;
}
/** @return array{GdImage,int,int} */
function media_fit(GdImage $src, int $w, int $h, int $max, bool $byWidth = false): array {
    $w = imagesx($src); $h = imagesy($src);
    $scale = $byWidth ? min(1, $max / $w) : min(1, $max / max($w, $h));
    if ($scale >= 1) return [$src, $w, $h];
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if (!$byWidth) imagedestroy($src);
    return [$dst, $nw, $nh];
}
function media_gd_save(GdImage $im, string $path, int $type): void {
    match ($type) { IMAGETYPE_JPEG => imagejpeg($im, $path, 84), IMAGETYPE_PNG => imagepng($im, $path, 7), IMAGETYPE_WEBP => imagewebp($im, $path, 82), IMAGETYPE_GIF => imagegif($im, $path), default => null };
}
function media_unlink_files(array $m): void {
    if ($m['path']) @unlink(UPLOADS . '/' . $m['path']);
    foreach (jdec($m['variants']) as $set) foreach ($set as $p) @unlink(UPLOADS . '/' . $p);
}
function media_delete(int $shopId, int $id): void {
    $m = owned('media', $id, $shopId);
    media_unlink_files($m);
    q('DELETE FROM media WHERE id = ? AND shop_id = ?', [$id, $shopId]);
    audit('media.deleted', 'media', $id, $shopId);
}
/** Remote image reference (used by imports): no download, rendered as-is. */
function media_external(int $shopId, string $url, string $alt = ''): ?int {
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url) || !safe_url($url)) return null;
    $ex = val('SELECT id FROM media WHERE shop_id = ? AND external_url = ?', [$shopId, $url]);
    if ($ex) return (int) $ex;
    return insert('media', ['shop_id' => $shopId, 'filename' => mb_substr(basename((string) parse_url($url, PHP_URL_PATH)) ?: 'remote', 0, 120), 'external_url' => mb_substr($url, 0, 1000), 'mime' => 'image/remote', 'size_bytes' => 0, 'alt' => $alt ?: null, 'created_at' => now()]);
}

function media_by_id(?int $id, ?int $shopId = null): ?array {
    if (!$id) return null;
    return $shopId ? row('SELECT * FROM media WHERE id = ? AND shop_id = ?', [$id, $shopId]) : row('SELECT * FROM media WHERE id = ?', [$id]);
}
function media_url(?array $m, int $want = 0): string {
    if (!$m) return '';
    if (!empty($m['external_url'])) return $m['external_url'];
    if ($want) {
        $v = jdec($m['variants'])['webp'] ?? [];
        ksort($v);
        foreach ($v as $w => $p) if ($w >= $want) return '/uploads/' . $p;
        if ($v) return '/uploads/' . end($v);
    }
    return '/uploads/' . $m['path'];
}

/** Responsive <picture> with WebP/AVIF srcset, explicit width/height (no CLS), lazy by default. */
function img_tag(?array $m, string $alt, string $sizes = '100vw', array $attrs = []): string {
    if (!$m) return '';
    $alt = $alt !== '' ? $alt : (string) ($m['alt'] ?? '');
    $loading = $attrs['loading'] ?? 'lazy';
    $extra = '';
    foreach ($attrs as $k => $v) if (!in_array($k, ['loading'], true)) $extra .= ' ' . e($k) . '="' . e($v) . '"';
    if (!empty($m['external_url'])) {
        return '<img src="' . e($m['external_url']) . '" alt="' . e($alt) . '" loading="' . e($loading) . '" decoding="async" width="800" height="800"' . $extra . '>';
    }
    $v = jdec($m['variants']);
    $srcset = static function (array $set): string { ksort($set); return implode(', ', array_map(static fn($w, $p) => '/uploads/' . $p . ' ' . $w . 'w', array_keys($set), $set)); };
    $h = '<picture>';
    if (!empty($v['avif'])) $h .= '<source type="image/avif" srcset="' . e($srcset($v['avif'])) . '" sizes="' . e($sizes) . '">';
    if (!empty($v['webp'])) $h .= '<source type="image/webp" srcset="' . e($srcset($v['webp'])) . '" sizes="' . e($sizes) . '">';
    $h .= '<img src="/uploads/' . e($m['path']) . '" alt="' . e($alt) . '" width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '" loading="' . e($loading) . '" decoding="async"' . ($loading === 'eager' ? ' fetchpriority="high"' : '') . $extra . '>';
    return $h . '</picture>';
}

/** Deterministic, tasteful SVG placeholder for products without images (served at /img/ph/<seed>.svg). */
function placeholder_svg(string $seed, string $label = ''): string {
    $hsh = crc32($seed);
    $hue = $hsh % 360; $hue2 = ($hue + 40) % 360;
    $shape = $hsh % 4;
    $initials = mb_strtoupper(mb_substr(trim($label), 0, 1)) ?: '•';
    $shapes = [
        '<circle cx="400" cy="380" r="180" fill="hsl(' . $hue2 . ',45%,72%)"/>',
        '<rect x="230" y="210" width="340" height="340" rx="40" fill="hsl(' . $hue2 . ',45%,72%)" transform="rotate(12 400 380)"/>',
        '<path d="M400 170 L600 540 L200 540 Z" fill="hsl(' . $hue2 . ',45%,72%)"/>',
        '<ellipse cx="400" cy="400" rx="230" ry="150" fill="hsl(' . $hue2 . ',45%,72%)"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(' . $hue . ',35%,90%)"/><stop offset="1" stop-color="hsl(' . $hue . ',30%,80%)"/></linearGradient></defs><rect width="800" height="800" fill="url(#g)"/>'
        . $shapes[$shape] . '<text x="400" y="430" font-family="Georgia,serif" font-size="150" text-anchor="middle" fill="hsl(' . $hue . ',30%,30%)" opacity=".55">' . e($initials) . '</text></svg>';
}
