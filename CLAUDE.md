# Mini Digital Signage

Weist Monitoren zentral eine Webseite zu. Einrichtung und Betrieb stehen in der `README.md`. Die Oberfläche ist deutsch und bewusst generisch, ohne Bezug zu Turnieren oder einem bestimmten Einsatz.

## Aufbau

- `public/`: Dokumentenstamm des Webservers
  - `admin/`: Admin-Bereich (`index.php` ist Controller und Aktions-Handler, dazu `admin.js`)
  - `api/poll.php`: Schnittstelle für die Monitore
- `src/`: `app.php` (Datenbank, Helfer, Zeitplanung), `auth.php` (Sitzung, Login, CSRF), `views/`
- `extension/`: Browser-Erweiterung (Manifest V3), läuft auf jedem Monitor
- `kiosk/`: Startskripte für Linux und Windows
- `data/`: entsteht zur Laufzeit (SQLite, Sitzungen, Passwort-Hash), nicht im Repository

Keine Abhängigkeiten und kein Build-Schritt: PHP 8.1+ mit `pdo_sqlite`, Bootstrap 5.3 per CDN mit SRI-Hashes. Das Zielsystem ist Plesk-Webspace, also ohne Daemons, Cron oder Shell-Voraussetzung.

## Entwurfsentscheidungen

- **Kein iframe und keine Web-Player-Seite.** TORIS.online und viele andere Seiten verbieten das Einbetten (`X-Frame-Options: DENY`). Die Erweiterung lädt die Zielseite direkt im Tab.
- **Kennung im Speicher der Erweiterung, nicht im Cookie.** Sie geht als Header `X-Device-Token` an den Server. `Authorization` scheidet aus, weil Apache mit PHP-FPM diesen Header oft verwirft. In der Datenbank liegt nur der SHA-256-Hash.
- **Geplante Änderungen ohne Cron.** `apply_due_changes()` läuft beim Polling und beim Laden des Admin-Bereichs. Maßgeblich ist die Serverzeit.
- **`rev` steuert das Neuladen.** Die Erweiterung navigiert nur, wenn sich `rev` oder der Zustand ändert, damit eine laufende Seite unberührt bleibt. Jede URL-Änderung und „Neu laden“ erhöhen `rev`.
- **Nur `http(s)`-URLs** gelangen in die Datenbank (`normalize_url()`).
- **Ersteinrichtung** ist durch einen Schlüssel in `data/setup-key.txt` geschützt. Wird `data/admin.pass` gelöscht, startet sie erneut, das ist der vorgesehene Passwort-Reset.

## Lokal starten

```bash
php -S 127.0.0.1:8099 -t public
```

Der Admin-Bereich liegt dann unter `http://127.0.0.1:8099/admin/`. Für die Erweiterung `SERVER_URL` in `extension/config.js` auf diese Adresse setzen, diese Änderung aber nicht committen.

## Beim Testen beachten

- Es gibt keine automatisierte Testsuite.
- Google Chrome ignoriert `--load-extension` seit Version 137. Automatisiert geht das Laden über `--remote-debugging-pipe`, `--enable-unsafe-extension-debugging` und den CDP-Befehl `Extensions.loadUnpacked`.
- Bei gleicher `version` im Manifest lief im selben Profil weiter der alte Hintergrundcode. Nach Änderungen an der Erweiterung die Version erhöhen oder mit frischem Profil testen.
- `php -S` bedient standardmäßig nur eine Verbindung gleichzeitig. Vorab geöffnete Chrome-Verbindungen können ein lokales Testziel dadurch um Sekunden blockieren. Abhilfe: `PHP_CLI_SERVER_WORKERS=4`.
- Bisher nicht getestet: Plesk mit HTTPS, die Startskripte im Kiosk-Modus, Chromium und Windows LTSC.
