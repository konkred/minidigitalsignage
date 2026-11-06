<?php
declare(strict_types=1);

// Ohne diese Prüfung endet eine open_basedir-Sperre in Plesk in einer leeren Fehlerseite.
if (!@is_readable(__DIR__ . '/../../src/app.php') || !@is_readable(__DIR__ . '/../../config.php')) {
    http_response_code(500);
    exit('PHP kann src/ oder config.php nicht lesen. In Plesk unter PHP-Einstellungen open_basedir auf {WEBSPACEROOT}{/}{:}{TMP}{/} setzen und prüfen, ob beide neben public/ liegen.');
}

require __DIR__ . '/../../src/app.php';
require __DIR__ . '/../../src/auth.php';

header('X-Robots-Tag: noindex');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; script-src 'self' https://cdn.jsdelivr.net; style-src https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

start_admin_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function redirect_home(): never
{
    header('Location: ./', true, 303);
    exit;
}

function render(string $view, array $vars = []): never
{
    header('Cache-Control: no-store');
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
            save_password($password);
            unlink(SETUP_KEY_FILE);
            log_in();
        }
        redirect_home();
    }
    render('setup');
}

if (!is_logged_in()) {
    if (isset($_GET['partial']) || isset($_GET['screenshot'])) {
        http_response_code(401);
        exit;
    }
    if ($method === 'POST') {
        if (is_locked_out()) {
            flash('danger', 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.');
        } elseif (!csrf_is_valid()) {
            flash('danger', 'Die Sitzung ist abgelaufen. Bitte erneut versuchen.');
        } elseif (password_matches((string) ($_POST['password'] ?? ''))) {
            log_in();
        } else {
            record_login_failure();
            flash('danger', 'Das Passwort ist falsch.');
        }
        redirect_home();
    }
    render('login');
}

if (isset($_GET['screenshot'])) {
    $file = screenshot_path((int) $_GET['screenshot']);
    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }
    // Die Adresse enthält den Aufnahmezeitpunkt, deshalb darf der Browser das Bild behalten.
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    readfile($file);
    exit;
}

/** @return array{0: ?string, 1: ?string, 2: ?string} Art, Inhalt und ggf. Fehlermeldung */
function read_content(): array
{
    $kind = (string) ($_POST['kind'] ?? 'url');
    if ($kind === 'url') {
        $url = normalize_url((string) ($_POST['url'] ?? ''));
        return $url === null || $url === ''
            ? [null, null, 'Bitte eine vollständige Adresse angeben, die mit http:// oder https:// beginnt.']
            : ['url', $url, null];
    }
    if ($kind === 'message') {
        $message = trim(str_replace("\r\n", "\n", (string) ($_POST['message'] ?? '')));
        return $message === '' || preg_match_all('/./us', $message) > 500
            ? [null, null, 'Bitte eine Nachricht mit höchstens 500 Zeichen angeben.']
            : ['message', $message, null];
    }
    return in_array($kind, CONTENT_KINDS, true) ? [$kind, null, null] : [null, null, 'Ungültige Inhaltsart.'];
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
            $url = normalize_url((string) ($_POST['url'] ?? ''));
            if ($url === null || $name === '') {
                flash('danger', $url === null ? 'Bitte eine vollständige Adresse angeben, die mit http:// oder https:// beginnt.' : 'Bitte einen Namen angeben.');
                return;
            }
            $monitorId = query('SELECT id FROM monitors WHERE code = ?', [$code])->fetchColumn();
            if (!$monitorId) {
                flash('danger', 'Zu diesem Code wartet kein Monitor. Bitte den angezeigten Code prüfen.');
                return;
            }
            query('UPDATE monitors SET code = NULL, name = ? WHERE id = ?', [$name, $monitorId]);
            set_monitor_content((int) $monitorId, $url === '' ? 'standby' : 'url', $url === '' ? null : $url, 'Registrierung');
            flash('success', "Monitor „{$name}“ wurde registriert.");
            return;

        case 'change':
            [$kind, $content, $error] = read_content();
            [$at, $timeError] = read_activation_time();
            if ($error || $timeError) {
                flash('danger', $error ?? $timeError);
                return;
            }
            $changeId = (int) ($_POST['change_id'] ?? 0);
            if ($changeId) {
                $change = query('SELECT * FROM changes WHERE id = ?', [$changeId])->fetch();
                if (!$change) {
                    flash('danger', 'Diese geplante Änderung gibt es nicht mehr.');
                } elseif ($at === null) {
                    query('DELETE FROM changes WHERE id = ?', [$changeId]);
                    set_monitor_content((int) $change['monitor_id'], $kind, $content, 'Sofort');
                    flash('success', 'Die Änderung wird in wenigen Sekunden angezeigt.');
                } else {
                    query('UPDATE changes SET kind = ?, content = ?, activate_at = ? WHERE id = ?', [$kind, $content, $at, $changeId]);
                    flash('success', 'Die Änderung ist jetzt für ' . format_time($at) . ' Uhr geplant.');
                }
                return;
            }
            $ids = array_unique(array_map('intval', (array) ($_POST['ids'] ?? [])));
            if (!$ids) {
                flash('danger', 'Bitte mindestens einen Monitor auswählen.');
                return;
            }
            foreach ($ids as $monitorId) {
                $at === null
                    ? set_monitor_content($monitorId, $kind, $content, 'Sofort')
                    : query('INSERT INTO changes (monitor_id, kind, content, activate_at) VALUES (?, ?, ?, ?)', [$monitorId, $kind, $content, $at]);
            }
            flash('success', $at === null
                ? 'Die Änderung wird in wenigen Sekunden angezeigt.'
                : 'Die Änderung ist für ' . format_time($at) . ' Uhr geplant.');
            return;

        case 'monitor_settings':
            $name = trim((string) ($_POST['name'] ?? ''));
            $zoom = filter_var($_POST['zoom'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 25, 'max_range' => 500]]);
            $reload = filter_var($_POST['reload_minutes'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1440]]);
            if ($name === '' || $zoom === false || $reload === false) {
                flash('danger', 'Bitte Name, Zoom (25 bis 500 %) und Neuladen (0 bis 1440 Minuten) prüfen.');
                return;
            }
            query(
                'UPDATE monitors SET name = ?, zoom = ?, reload_minutes = ?, muted = ?, hide_cursor = ? WHERE id = ?',
                [$name, $zoom, $reload, isset($_POST['muted']) ? 1 : 0, isset($_POST['hide_cursor']) ? 1 : 0, $id]
            );
            flash('success', 'Die Einstellungen sind gespeichert.');
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
            @unlink(screenshot_path($id));
            flash('success', 'Der Monitor wurde entfernt und zeigt wieder einen Registriercode.');
            return;

        case 'favorite_add':
            $name = trim((string) ($_POST['name'] ?? ''));
            $url = normalize_url((string) ($_POST['url'] ?? ''));
            if ($name === '' || !$url) {
                flash('danger', 'Bitte einen Namen und eine vollständige Adresse angeben.');
                return;
            }
            query('INSERT INTO favorites (name, url) VALUES (?, ?)', [$name, $url]);
            flash('success', "Favorit „{$name}“ wurde gespeichert.");
            return;

        case 'favorite_delete':
            query('DELETE FROM favorites WHERE id = ?', [$id]);
            return;

        case 'notify':
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('danger', 'Bitte eine gültige E-Mail-Adresse angeben.');
                return;
            }
            set_setting('notify_email', $email === '' ? null : $email);
            flash('success', $email === '' ? 'Benachrichtigungen sind ausgeschaltet.' : 'Benachrichtigungen gehen an ' . $email . '.');
            return;

        case 'test_mail':
            $email = setting('notify_email');
            if (!$email) {
                flash('danger', 'Bitte zuerst eine E-Mail-Adresse speichern.');
            } elseif (send_mail($email, 'Test der Benachrichtigung', "Diese Testnachricht kommt aus dem Admin-Bereich von Digital Signage.\n")) {
                flash('success', 'Die Testnachricht wurde an ' . $email . ' übergeben. Bitte auch den Spam-Ordner prüfen.');
            } else {
                flash('danger', 'Der Server konnte die E-Mail nicht versenden. Bitte die Mail-Einstellungen in Plesk prüfen.');
            }
            return;

        case 'password':
            $new = (string) ($_POST['new_password'] ?? '');
            if (!password_matches((string) ($_POST['current_password'] ?? ''))) {
                flash('danger', 'Das aktuelle Passwort ist falsch.');
            } elseif (strlen($new) < 10) {
                flash('danger', 'Das neue Passwort muss mindestens 10 Zeichen lang sein.');
            } else {
                save_password($new);
                flash('success', 'Das Passwort wurde geändert.');
            }
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
check_offline_notifications();

$monitors = query('SELECT * FROM monitors WHERE code IS NULL ORDER BY name COLLATE NOCASE')->fetchAll();
$changes = [];
foreach (query('SELECT * FROM changes ORDER BY activate_at, id')->fetchAll() as $change) {
    $changes[$change['monitor_id']][] = $change;
}
$history = [];
foreach (query('SELECT * FROM history ORDER BY at DESC, id DESC')->fetchAll() as $entry) {
    if (count($history[$entry['monitor_id']] ?? []) < 15) {
        $history[$entry['monitor_id']][] = $entry;
    }
}
$onlineAfter = (int) $config['online_after'];

$wall = ($_GET['view'] ?? '') === 'wall';
if (isset($_GET['partial'])) {
    header('Cache-Control: no-store');
    require __DIR__ . '/../../src/views/' . ($_GET['partial'] === 'wall' ? 'wall_grid.php' : 'monitor_list.php');
    exit;
}
if ($wall) {
    render('wall', compact('monitors', 'onlineAfter'));
}

$favorites = query('SELECT * FROM favorites ORDER BY name COLLATE NOCASE')->fetchAll();
$notifyEmail = setting('notify_email');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
$cronUrl = "$scheme://$host" . dirname($_SERVER['SCRIPT_NAME'], 2) . '/api/check.php?key=' . cron_key();
$cronUrl = str_replace('//api', '/api', $cronUrl);

render('dashboard', compact('monitors', 'changes', 'history', 'onlineAfter', 'favorites', 'notifyEmail', 'cronUrl'));
