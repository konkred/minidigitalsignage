<?php
declare(strict_types=1);

// Ohne diese Prüfung endet eine open_basedir-Sperre in Plesk in einer leeren Fehlerseite.
if (!@is_readable(__DIR__ . '/../../src/app.php') || !@is_readable(__DIR__ . '/../../config.php')) {
    http_response_code(500);
    exit('PHP kann src/ oder config.php nicht lesen. In Plesk unter PHP-Einstellungen open_basedir auf {WEBSPACEROOT}{/}{:}{TMP}{/} setzen und prüfen, ob beide neben public/ liegen.');
}

require __DIR__ . '/../../src/app.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('{}');
}

try {
    $monitor = monitor_by_token((string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''));

    if (!$monitor) {
        $pending = create_pending_monitor();
        echo json_encode(['status' => 'pending'] + $pending);
        exit;
    }

    $report = json_decode((string) file_get_contents('php://input'), true);
    $report = is_array($report) ? $report : [];
    query(
        'UPDATE monitors SET last_seen = ?, ext_version = ?, browser = ?, load_failed = ? WHERE id = ?',
        [
            time(),
            substr((string) ($report['version'] ?? ''), 0, 20) ?: null,
            substr((string) ($report['browser'] ?? ''), 0, 40) ?: null,
            empty($report['loadFailed']) ? 0 : 1,
            $monitor['id'],
        ]
    );
    apply_due_changes((int) $monitor['id']);

    $monitor = query('SELECT * FROM monitors WHERE id = ?', [$monitor['id']])->fetch();
    echo json_encode($monitor['code'] !== null
        ? ['status' => 'pending', 'code' => $monitor['code']]
        : [
            'status' => 'active',
            'name' => $monitor['name'],
            'kind' => $monitor['kind'],
            'content' => $monitor['content'],
            // Für Erweiterungen der Version 1.0, die nur Adressen kennen.
            'url' => $monitor['kind'] === 'url' ? $monitor['content'] : null,
            'rev' => (int) $monitor['rev'],
            'zoom' => (int) $monitor['zoom'],
            'reloadMinutes' => (int) $monitor['reload_minutes'],
        ]);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    check_offline_notifications();
} catch (Throwable $e) {
    error_log('Signage poll: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(503);
        echo '{}';
    }
}
