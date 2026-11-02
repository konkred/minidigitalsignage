<?php
declare(strict_types=1);

require __DIR__ . '/../../src/app.php';
require __DIR__ . '/../../src/auth.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; script-src 'self' https://cdn.jsdelivr.net; style-src https://cdn.jsdelivr.net; img-src data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

start_admin_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function redirect_home(): never
{
    header('Location: ./', true, 303);
    exit;
}

function render(string $view, array $vars = []): never
{
    extract($vars);
    $flashes = take_flashes();
    require __DIR__ . '/../../src/views/layout.php';
    exit;
}

// Erststart: Passwort festlegen, abgesichert durch den Schlüssel aus data/setup-key.txt.
if (!password_is_set()) {
    $key = setup_key();
    if ($method === 'POST') {
        $password = (string) ($_POST['password'] ?? '');
        if (is_locked_out()) {
            flash('danger', 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.');
        } elseif (!csrf_is_valid()) {
            flash('danger', 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.');
        } elseif (!hash_equals($key, trim((string) ($_POST['key'] ?? '')))) {
            record_login_failure();
            flash('danger', 'Der Einrichtungsschlüssel stimmt nicht.');
        } elseif (strlen($password) < 10) {
            flash('danger', 'Das Passwort muss mindestens 10 Zeichen lang sein.');
        } else {
            file_put_contents(PASSWORD_FILE, password_hash($password, PASSWORD_DEFAULT));
            chmod(PASSWORD_FILE, 0600);
            unlink(SETUP_KEY_FILE);
            log_in();
        }
        redirect_home();
    }
    render('setup');
}

if (!is_logged_in()) {
    if (($_GET['partial'] ?? '') !== '' ) {
        http_response_code(401);
        exit;
    }
    if ($method === 'POST') {
        if (is_locked_out()) {
            flash('danger', 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.');
        } elseif (!csrf_is_valid()) {
            flash('danger', 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.');
        } elseif (password_verify((string) ($_POST['password'] ?? ''), (string) file_get_contents(PASSWORD_FILE))) {
            log_in();
        } else {
            record_login_failure();
            flash('danger', 'Das Passwort ist falsch.');
        }
        redirect_home();
    }
    render('login');
}

/** @return array{0: ?string, 1: ?string} Normalisierte URL und ggf. Fehlermeldung */
function read_url(): array
{
    $url = normalize_url((string) ($_POST['url'] ?? ''));
    return $url === null
        ? [null, 'Bitte eine vollständige Adresse angeben, die mit http:// oder https:// beginnt.']
        : [$url, null];
}

/** @return array{0: ?int, 1: ?string} Zeitpunkt (null = sofort) und ggf. Fehlermeldung */
function read_activation_time(): array
{
    switch ($_POST['timing'] ?? 'now') {
        case 'now':
            return [null, null];
        case 'delay':
            $minutes = filter_var($_POST['minutes'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 525600]]);
            return $minutes === false ? [null, 'Bitte eine gültige Anzahl Minuten angeben.'] : [time() + $minutes * 60, null];
        case 'at':
            $at = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) ($_POST['at'] ?? ''));
            if (!$at) {
                return [null, 'Bitte Datum und Uhrzeit angeben.'];
            }
            return $at->getTimestamp() <= time()
                ? [null, 'Der Zeitpunkt liegt in der Vergangenheit.']
                : [$at->getTimestamp(), null];
    }
    return [null, 'Ungültige Zeitangabe.'];
}

function handle_action(string $action): void
{
    $id = (int) ($_POST['id'] ?? 0);
    switch ($action) {
        case 'logout':
            session_destroy();
            redirect_home();

        case 'register':
            $code = normalize_code((string) ($_POST['code'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            [$url, $error] = read_url();
            if ($error || $name === '') {
                flash('danger', $error ?? 'Bitte einen Namen angeben.');
                return;
            }
            $done = query(
                'UPDATE monitors SET code = NULL, name = ?, url = ?, rev = rev + 1 WHERE code = ?',
                [$name, $url === '' ? null : $url, $code]
            )->rowCount();
            $done
                ? flash('success', "Monitor „{$name}“ wurde registriert.")
                : flash('danger', 'Zu diesem Code wartet kein Monitor. Bitte den angezeigten Code prüfen.');
            return;

        case 'change':
            [$url, $error] = read_url();
            [$at, $timeError] = read_activation_time();
            if ($error || $timeError) {
                flash('danger', $error ?? $timeError);
                return;
            }
            $url = $url === '' ? null : $url;
            if ($at === null) {
                query('UPDATE monitors SET url = ?, rev = rev + 1 WHERE id = ? AND code IS NULL', [$url, $id]);
                flash('success', 'Die Änderung wird in wenigen Sekunden angezeigt.');
            } else {
                query('INSERT INTO changes (monitor_id, url, activate_at) VALUES (?, ?, ?)', [$id, $url, $at]);
                flash('success', 'Die Änderung ist für ' . format_time($at) . ' Uhr geplant.');
            }
            return;

        case 'rename':
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                flash('danger', 'Bitte einen Namen angeben.');
                return;
            }
            query('UPDATE monitors SET name = ? WHERE id = ?', [$name, $id]);
            return;

        case 'reload':
            query('UPDATE monitors SET rev = rev + 1 WHERE id = ?', [$id]);
            flash('success', 'Der Monitor lädt in wenigen Sekunden neu.');
            return;

        case 'cancel':
            query('DELETE FROM changes WHERE id = ?', [$id]);
            return;

        case 'delete':
            query('DELETE FROM monitors WHERE id = ?', [$id]);
            flash('success', 'Der Monitor wurde entfernt und zeigt wieder einen Registriercode.');
            return;
    }
    flash('danger', 'Unbekannte Aktion.');
}

if ($method === 'POST') {
    if (csrf_is_valid()) {
        handle_action((string) ($_POST['action'] ?? ''));
    } else {
        flash('danger', 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.');
    }
    redirect_home();
}

apply_due_changes();

$monitors = query('SELECT * FROM monitors WHERE code IS NULL ORDER BY name COLLATE NOCASE')->fetchAll();
$changes = [];
foreach (query('SELECT * FROM changes ORDER BY activate_at, id')->fetchAll() as $change) {
    $changes[$change['monitor_id']][] = $change;
}
$onlineAfter = (int) $config['online_after'];

if (($_GET['partial'] ?? '') === 'list') {
    require __DIR__ . '/../../src/views/monitor_list.php';
    exit;
}

render('dashboard', compact('monitors', 'changes', 'onlineAfter'));
