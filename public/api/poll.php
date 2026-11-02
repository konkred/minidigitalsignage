<?php
declare(strict_types=1);

require __DIR__ . '/../../src/app.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('{}');
}

try {
    $token = (string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
    $monitor = $token === '' ? false
        : query('SELECT id FROM monitors WHERE token_hash = ?', [hash('sha256', $token)])->fetch();

    if (!$monitor) {
        $pending = create_pending_monitor();
        echo json_encode(['status' => 'pending'] + $pending);
        exit;
    }

    $now = time();
    query('UPDATE monitors SET last_seen = ? WHERE id = ? AND last_seen < ?', [$now, $monitor['id'], $now - 4]);
    apply_due_changes((int) $monitor['id']);

    $monitor = query('SELECT code, name, url, rev FROM monitors WHERE id = ?', [$monitor['id']])->fetch();
    echo json_encode($monitor['code'] !== null
        ? ['status' => 'pending', 'code' => $monitor['code']]
        : ['status' => 'active', 'name' => $monitor['name'], 'url' => $monitor['url'], 'rev' => (int) $monitor['rev']]);
} catch (Throwable $e) {
    error_log('Signage poll: ' . $e->getMessage());
    http_response_code(503);
    echo '{}';
}
