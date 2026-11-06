<?php
declare(strict_types=1);

// Ohne diese Prüfung endet eine open_basedir-Sperre in Plesk in einer leeren Fehlerseite.
if (!@is_readable(__DIR__ . '/../../src/app.php') || !@is_readable(__DIR__ . '/../../config.php')) {
    http_response_code(500);
    exit('PHP kann src/ oder config.php nicht lesen. In Plesk unter PHP-Einstellungen open_basedir auf {WEBSPACEROOT}{/}{:}{TMP}{/} setzen und prüfen, ob beide neben public/ liegen.');
}

require __DIR__ . '/../../src/app.php';

const MAX_SCREENSHOT_BYTES = 512 * 1024;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$monitor = monitor_by_token((string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''));
if (!$monitor || $monitor['code'] !== null) {
    http_response_code(403);
    exit;
}

$image = (string) file_get_contents('php://input', false, null, 0, MAX_SCREENSHOT_BYTES + 1);
if (strlen($image) > MAX_SCREENSHOT_BYTES || !str_starts_with($image, "\xFF\xD8\xFF")) {
    http_response_code(400);
    exit;
}

if (!is_dir(SCREENSHOT_DIR)) {
    mkdir(SCREENSHOT_DIR, 0770, true);
}
$target = screenshot_path((int) $monitor['id']);
file_put_contents("$target.tmp", $image);
rename("$target.tmp", $target);
query('UPDATE monitors SET shot_at = ? WHERE id = ?', [time(), $monitor['id']]);
http_response_code(204);
