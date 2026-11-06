<?php
declare(strict_types=1);

const PASSWORD_FILE = DATA_DIR . '/admin.pass';
const SETUP_KEY_FILE = DATA_DIR . '/setup-key.txt';
const MAX_LOGIN_FAILURES = 5;
const LOGIN_LOCK_SECONDS = 900;
const SESSION_SECONDS = 43200;

function start_admin_session(): void
{
    $sessionDir = DATA_DIR . '/sessions';
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0770, true);
    }
    session_save_path($sessionDir);
    ini_set('session.gc_maxlifetime', (string) SESSION_SECONDS);
    ini_set('session.use_strict_mode', '1');
    session_name('signage_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function password_is_set(): bool
{
    return is_file(PASSWORD_FILE);
}

function password_matches(string $password): bool
{
    return password_verify($password, (string) file_get_contents(PASSWORD_FILE));
}

function save_password(string $password): void
{
    file_put_contents(PASSWORD_FILE, password_hash($password, PASSWORD_DEFAULT));
    chmod(PASSWORD_FILE, 0600);
}

function setup_key(): string
{
    if (!is_file(SETUP_KEY_FILE)) {
        file_put_contents(SETUP_KEY_FILE, bin2hex(random_bytes(8)) . "\n");
        chmod(SETUP_KEY_FILE, 0600);
    }
    return trim((string) file_get_contents(SETUP_KEY_FILE));
}

function is_locked_out(): bool
{
    query('DELETE FROM login_failures WHERE at < ?', [time() - LOGIN_LOCK_SECONDS]);
    $failures = query('SELECT COUNT(*) FROM login_failures WHERE ip = ?', [$_SERVER['REMOTE_ADDR'] ?? '']);
    return (int) $failures->fetchColumn() >= MAX_LOGIN_FAILURES;
}

function record_login_failure(): void
{
    query('INSERT INTO login_failures (ip, at) VALUES (?, ?)', [$_SERVER['REMOTE_ADDR'] ?? '', time()]);
}

function log_in(): void
{
    query('DELETE FROM login_failures WHERE ip = ?', [$_SERVER['REMOTE_ADDR'] ?? '']);
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['last_active'] = time();
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin']) || time() - (int) ($_SESSION['last_active'] ?? 0) > SESSION_SECONDS) {
        return false;
    }
    $_SESSION['last_active'] = time();
    return true;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    return hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''));
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}
