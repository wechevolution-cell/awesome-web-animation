<?php
declare(strict_types=1);

/** Full backup: consistent DB snapshot (VACUUM INTO) + uploads + secret. Returns zip path. */
function backup_create(string $label = 'manual'): string {
    if (!class_exists('ZipArchive')) throw new UserError('The PHP zip extension is required for backups.');
    $dir = DATA . '/backups';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'backup-' . date('Ymd-His') . '-' . preg_replace('/[^a-z0-9\-]/', '', strtolower($label)) . '.zip';
    $snap = $dir . '/snapshot-' . bin2hex(random_bytes(4)) . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($snap));
    $zip = new ZipArchive();
    if ($zip->open("$dir/$name", ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new UserError('Could not create the backup file.');
    $zip->addFile($snap, 'data/app.sqlite');
    if (is_file(DATA . '/.secret')) $zip->addFile(DATA . '/.secret', 'data/.secret');
    if (is_dir(UPLOADS)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOADS, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile()) $zip->addFile($f->getPathname(), 'uploads/' . substr($f->getPathname(), strlen(UPLOADS) + 1));
    }
    $zip->setArchiveComment('Platform backup ' . date('c') . ' — restore: replace data/app.sqlite and uploads/');
    $zip->close();
    @unlink($snap);
    audit('backup.created', 'backup', null, null, 'success', ['file' => $name]);
    return "$dir/$name";
}
function backups_list(): array {
    $out = [];
    foreach (glob(DATA . '/backups/backup-*.zip') ?: [] as $f) $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
    usort($out, static fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}
function backup_path(string $name): ?string {
    if (!preg_match('/^backup-[A-Za-z0-9\-]+\.zip$/', $name)) return null;
    $p = DATA . '/backups/' . $name;
    return is_file($p) ? $p : null;
}
