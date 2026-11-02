<?php
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/../data';
const PENDING_TTL = 3600;
const PENDING_MAX = 200;
const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    exit('Die PHP-Erweiterung pdo_sqlite fehlt. Bitte in Plesk unter PHP-Einstellungen aktivieren.');
}
if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0770, true) || !is_writable(DATA_DIR)) {
    http_response_code(500);
    exit('Das Verzeichnis data/ ist nicht beschreibbar.');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DATA_DIR . '/signage.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE IF NOT EXISTS monitors (
            id INTEGER PRIMARY KEY,
            token_hash TEXT NOT NULL UNIQUE,
            code TEXT UNIQUE,
            name TEXT,
            url TEXT,
            rev INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL,
            last_seen INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS changes (
            id INTEGER PRIMARY KEY,
            monitor_id INTEGER NOT NULL REFERENCES monitors(id) ON DELETE CASCADE,
            url TEXT,
            activate_at INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_failures (ip TEXT NOT NULL, at INTEGER NOT NULL)');
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_time(int $timestamp): string
{
    return date(date('Y-m-d', $timestamp) === date('Y-m-d') ? '\h\e\u\t\e H:i' : 'd.m.Y H:i', $timestamp);
}

function format_code(string $code): string
{
    return substr($code, 0, 3) . '-' . substr($code, 3);
}

function normalize_code(string $input): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($input));
}

/** Liefert die bereinigte Adresse, '' für „kein Inhalt“ oder null, wenn sie kein http(s)-Ziel ist. */
function normalize_url(string $input): ?string
{
    $url = trim($input);
    if ($url === '') {
        return '';
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = parse_url($url, PHP_URL_HOST);
    return in_array($scheme, ['http', 'https'], true) && $host && strlen($url) <= 2000 ? $url : null;
}

function create_pending_monitor(): array
{
    $now = time();
    query('DELETE FROM monitors WHERE code IS NOT NULL AND last_seen < ?', [$now - PENDING_TTL]);
    if ((int) query('SELECT COUNT(*) FROM monitors WHERE code IS NOT NULL')->fetchColumn() >= PENDING_MAX) {
        throw new RuntimeException('Zu viele unregistrierte Monitore.');
    }
    $token = bin2hex(random_bytes(32));
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= CODE_ALPHABET[random_int(0, strlen(CODE_ALPHABET) - 1)];
        }
    } while (query('SELECT 1 FROM monitors WHERE code = ?', [$code])->fetchColumn());
    query(
        'INSERT INTO monitors (token_hash, code, created_at, last_seen) VALUES (?, ?, ?, ?)',
        [hash('sha256', $token), $code, $now, $now]
    );
    return ['token' => $token, 'code' => $code];
}

/**
 * Wendet fällige Änderungen an. Geplant wird ohne Cron: Der Monitor fragt alle paar
 * Sekunden nach, dabei wird die jeweils späteste fällige Änderung wirksam.
 */
function apply_due_changes(?int $monitorId = null): void
{
    $now = time();
    $filter = $monitorId === null ? '' : ' AND monitor_id = ' . $monitorId;
    if (!query("SELECT 1 FROM changes WHERE activate_at <= ?$filter LIMIT 1", [$now])->fetchColumn()) {
        return;
    }
    db()->exec('BEGIN IMMEDIATE');
    try {
        $due = query("SELECT * FROM changes WHERE activate_at <= ?$filter ORDER BY activate_at, id", [$now])->fetchAll();
        foreach ($due as $change) {
            query('UPDATE monitors SET url = ?, rev = rev + 1 WHERE id = ?', [$change['url'], $change['monitor_id']]);
            query('DELETE FROM changes WHERE id = ?', [$change['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $e) {
        db()->exec('ROLLBACK');
        throw $e;
    }
}
