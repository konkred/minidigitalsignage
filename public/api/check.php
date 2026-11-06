<?php
declare(strict_types=1);

// Ohne diese Prüfung endet eine open_basedir-Sperre in Plesk in einer leeren Fehlerseite.
if (!@is_readable(__DIR__ . '/../../src/app.php') || !@is_readable(__DIR__ . '/../../config.php')) {
    http_response_code(500);
    exit('PHP kann src/ oder config.php nicht lesen. In Plesk unter PHP-Einstellungen open_basedir auf {WEBSPACEROOT}{/}{:}{TMP}{/} setzen und prüfen, ob beide neben public/ liegen.');
}

require __DIR__ . '/../../src/app.php';

// Optional für eine geplante Aufgabe in Plesk („URL abrufen“). Ohne sie prüft der Server
// nur, solange mindestens ein Monitor oder der Admin-Bereich Anfragen stellt.
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (!hash_equals(cron_key(), (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Ungültiger Schlüssel');
}

apply_due_changes();
check_offline_notifications();
echo 'OK';
