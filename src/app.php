<?php
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/../data';
const SCREENSHOT_DIR = DATA_DIR . '/screens';
const SCHEMA_VERSION = 3;
// Muss zur version in extension/manifest.json passen, sonst meldet der Admin-Bereich eine veraltete Erweiterung.
const EXTENSION_VERSION = '1.2.1';
const PENDING_TTL = 3600;
const PENDING_MAX = 200;
const HISTORY_DAYS = 90;
const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const CONTENT_KINDS = ['url', 'message', 'blank', 'standby'];

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
        if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < SCHEMA_VERSION) {
            migrate($pdo);
        }
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        $hasMonitors = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'monitors'")->fetchColumn();
        if (!$hasMonitors) {
            $pdo->exec('CREATE TABLE monitors (
                id INTEGER PRIMARY KEY,
                token_hash TEXT NOT NULL UNIQUE,
                code TEXT UNIQUE,
                name TEXT,
                kind TEXT NOT NULL DEFAULT \'standby\',
                content TEXT,
                rev INTEGER NOT NULL DEFAULT 1,
                zoom INTEGER NOT NULL DEFAULT 100,
                reload_minutes INTEGER NOT NULL DEFAULT 0,
                muted INTEGER NOT NULL DEFAULT 0,
                hide_cursor INTEGER NOT NULL DEFAULT 1,
                ext_version TEXT,
                browser TEXT,
                load_failed INTEGER NOT NULL DEFAULT 0,
                shot_at INTEGER,
                offline_notified INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL,
                last_seen INTEGER NOT NULL
            )');
            $pdo->exec('CREATE TABLE changes (
                id INTEGER PRIMARY KEY,
                monitor_id INTEGER NOT NULL REFERENCES monitors(id) ON DELETE CASCADE,
                kind TEXT NOT NULL DEFAULT \'standby\',
                content TEXT,
                activate_at INTEGER NOT NULL
            )');
            $pdo->exec('CREATE TABLE login_failures (ip TEXT NOT NULL, at INTEGER NOT NULL)');
        } elseif ($version < 2) {
            // Version 1 kannte nur Adressen in der Spalte url. Sie bleibt ungenutzt stehen.
            foreach ([
                "ALTER TABLE monitors ADD COLUMN kind TEXT NOT NULL DEFAULT 'standby'",
                'ALTER TABLE monitors ADD COLUMN content TEXT',
                'ALTER TABLE monitors ADD COLUMN zoom INTEGER NOT NULL DEFAULT 100',
                'ALTER TABLE monitors ADD COLUMN reload_minutes INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE monitors ADD COLUMN ext_version TEXT',
                'ALTER TABLE monitors ADD COLUMN browser TEXT',
                'ALTER TABLE monitors ADD COLUMN load_failed INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE monitors ADD COLUMN shot_at INTEGER',
                'ALTER TABLE monitors ADD COLUMN offline_notified INTEGER NOT NULL DEFAULT 0',
                "UPDATE monitors SET kind = 'url', content = url WHERE url IS NOT NULL",
                "ALTER TABLE changes ADD COLUMN kind TEXT NOT NULL DEFAULT 'standby'",
                'ALTER TABLE changes ADD COLUMN content TEXT',
                "UPDATE changes SET kind = 'url', content = url WHERE url IS NOT NULL",
            ] as $sql) {
                $pdo->exec($sql);
            }
        }
        if ($hasMonitors && $version < 3) {
            $pdo->exec('ALTER TABLE monitors ADD COLUMN muted INTEGER NOT NULL DEFAULT 0');
            $pdo->exec('ALTER TABLE monitors ADD COLUMN hide_cursor INTEGER NOT NULL DEFAULT 1');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS favorites (id INTEGER PRIMARY KEY, name TEXT NOT NULL, url TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS history (
            id INTEGER PRIMARY KEY,
            monitor_id INTEGER NOT NULL REFERENCES monitors(id) ON DELETE CASCADE,
            kind TEXT NOT NULL,
            content TEXT,
            source TEXT NOT NULL,
            at INTEGER NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS history_monitor ON history (monitor_id, at)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)');
        $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function setting(string $key): ?string
{
    $value = query('SELECT value FROM settings WHERE key = ?', [$key])->fetchColumn();
    return $value === false ? null : $value;
}

function set_setting(string $key, ?string $value): void
{
    query('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)', [$key, $value]);
}

function cron_key(): string
{
    $key = setting('cron_key');
    if ($key === null) {
        $key = bin2hex(random_bytes(16));
        set_setting('cron_key', $key);
    }
    return $key;
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

function describe_content(string $kind, ?string $content): string
{
    return match ($kind) {
        'url' => (string) $content,
        'message' => 'Nachricht: „' . $content . '“',
        'blank' => 'Schwarzbild',
        default => 'Standby-Bildschirm',
    };
}

function set_monitor_content(int $monitorId, string $kind, ?string $content, string $source): void
{
    $updated = query(
        'UPDATE monitors SET kind = ?, content = ?, rev = rev + 1, load_failed = 0 WHERE id = ? AND code IS NULL',
        [$kind, $content, $monitorId]
    )->rowCount();
    if ($updated) {
        $now = time();
        query('INSERT INTO history (monitor_id, kind, content, source, at) VALUES (?, ?, ?, ?, ?)', [$monitorId, $kind, $content, $source, $now]);
        query('DELETE FROM history WHERE at < ?', [$now - HISTORY_DAYS * 86400]);
    }
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

function monitor_by_token(string $token): array|false
{
    return $token === '' ? false
        : query('SELECT * FROM monitors WHERE token_hash = ?', [hash('sha256', $token)])->fetch();
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
            set_monitor_content((int) $change['monitor_id'], $change['kind'], $change['content'], 'Geplant');
            query('DELETE FROM changes WHERE id = ?', [$change['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $e) {
        db()->exec('ROLLBACK');
        throw $e;
    }
}

function send_mail(string $to, string $subject, string $body): bool
{
    global $config;
    $from = $config['mail_from'] ?? '';
    if ($from === '') {
        $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $from = 'noreply@' . preg_replace('/^www\./', '', $host);
    }
    $headers = "From: Digital Signage <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/** Meldet Monitore per E-Mail, die länger offline sind oder wieder online kamen. Höchstens einmal pro Minute. */
function check_offline_notifications(): void
{
    global $config;
    $email = setting('notify_email');
    $now = time();
    if (!$email || $now - (int) setting('last_offline_check') < 60) {
        return;
    }
    set_setting('last_offline_check', (string) $now);
    $limit = $now - (int) ($config['notify_after'] ?? 300);

    $offline = query('SELECT id, name, last_seen FROM monitors WHERE code IS NULL AND offline_notified = 0 AND last_seen < ?', [$limit])->fetchAll();
    $online = query('SELECT id, name FROM monitors WHERE code IS NULL AND offline_notified = 1 AND last_seen >= ?', [$limit])->fetchAll();
    if (!$offline && !$online) {
        return;
    }

    $lines = [];
    foreach ($offline as $monitor) {
        $lines[] = "Offline seit " . format_time((int) $monitor['last_seen']) . " Uhr: {$monitor['name']}";
        query('UPDATE monitors SET offline_notified = 1 WHERE id = ?', [$monitor['id']]);
    }
    foreach ($online as $monitor) {
        $lines[] = "Wieder online: {$monitor['name']}";
        query('UPDATE monitors SET offline_notified = 0 WHERE id = ?', [$monitor['id']]);
    }
    $subject = $offline
        ? (count($offline) === 1 ? "Monitor offline: {$offline[0]['name']}" : count($offline) . ' Monitore offline')
        : (count($online) === 1 ? "Monitor wieder online: {$online[0]['name']}" : count($online) . ' Monitore wieder online');
    send_mail($email, $subject, implode("\n", $lines) . "\n");
}

function screenshot_path(int $monitorId): string
{
    return SCREENSHOT_DIR . "/$monitorId.jpg";
}
