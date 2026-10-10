<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    foreach ([DATA, DATA . '/logs', DATA . '/backups', DATA . '/imports', DATA . '/feeds'] as $d) if (!is_dir($d)) @mkdir($d, 0775, true);
    $file = getenv('APP_DB') ?: DATA . '/app.sqlite';
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 8000');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    db_migrate($pdo);
    return $pdo;
}

function db_migrate(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
    $done = $pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = glob(APP . '/migrations/*.php');
    sort($files);
    foreach ($files as $f) {
        $v = basename($f, '.php');
        if (in_array($v, $done, true)) continue;
        $pdo->beginTransaction();
        try {
            $sql = require $f;
            $pdo->exec($sql);
            $pdo->prepare('INSERT INTO migrations(version, applied_at) VALUES(?, ?)')->execute([$v, date('Y-m-d H:i:s')]);
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }
    }
    if (!$pdo->query('SELECT COUNT(*) FROM subscription_plans')->fetchColumn()) {
        require_once APP . '/seed.php';
        seed_defaults($pdo);
    }
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []): mixed { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function col(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(PDO::FETCH_COLUMN); }
function insert(string $table, array $data): int {
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}
/** UPDATE helper. $where must contain placeholders bound by $params. */
function update(string $table, array $data, string $where, array $params = []): int {
    $set = implode(',', array_map(static fn($c) => "$c = ?", array_keys($data)));
    return q("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $params))->rowCount();
}
/** Transaction helper. Nested calls join the outer transaction (depth-tracked, since BEGIN IMMEDIATE bypasses PDO's own state). */
function tx(callable $fn, bool $immediate = false): mixed {
    static $depth = 0;
    $pdo = db();
    if ($depth > 0) { $depth++; try { return $fn(); } finally { $depth--; } }
    $pdo->exec($immediate ? 'BEGIN IMMEDIATE' : 'BEGIN');
    $depth = 1;
    try { $r = $fn(); $depth = 0; $pdo->exec('COMMIT'); return $r; }
    catch (Throwable $t) { $depth = 0; try { $pdo->exec('ROLLBACK'); } catch (Throwable) {} throw $t; }
}
function in_ph(array $items): string { return implode(',', array_fill(0, max(1, count($items)), '?')); }
