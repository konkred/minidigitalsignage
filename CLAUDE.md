# Mini Digital Signage

Weist Monitoren zentral eine Webseite, Textnachricht oder ein Schwarzbild zu. Einrichtung, Funktionen und Betrieb stehen in der `README.md`. Die Oberfläche ist deutsch und bewusst generisch, ohne Bezug zu Turnieren oder einem bestimmten Einsatz.

## Aufbau

- `public/`: Dokumentenstamm des Webservers
  - `admin/`: Admin-Bereich (`index.php` ist Controller und Aktions-Handler und liefert auch die Vorschaubilder aus, dazu `admin.js`)
  - `api/poll.php`: Abfrage der Monitore, zugleich Statusmeldung (Versionen, Ladefehler)
  - `api/screenshot.php`: Upload der Vorschaubilder
  - `api/check.php`: optionaler Cron-Aufruf mit Schlüssel aus den Einstellungen
- `src/`: `app.php` (Datenbank, Migration, Helfer, Zeitplanung, Benachrichtigungen), `auth.php` (Sitzung, Login, CSRF, Passwort), `views/`
- `extension/`: Browser-Erweiterung (Manifest V3), läuft auf jedem Monitor; `screen.html` zeigt Code, Nachricht, Schwarzbild und Standby
- `kiosk/`: Startskripte für Linux und Windows mit Neustart nach Absturz
- `data/`: entsteht zur Laufzeit (SQLite, Sitzungen, Passwort-Hash, `screens/` für Vorschaubilder), nicht im Repository

Keine Abhängigkeiten und kein Build-Schritt: PHP 8.1+ mit `pdo_sqlite`, Bootstrap 5.3 per CDN mit SRI-Hashes. Das Zielsystem ist Plesk-Webspace, also ohne Daemons, Shell-Voraussetzung oder zwingenden Cron. Auch `mbstring` wird nicht vorausgesetzt.

## Entwurfsentscheidungen

- **Kein iframe und keine Web-Player-Seite.** TORIS.online und viele andere Seiten verbieten das Einbetten (`X-Frame-Options: DENY`). Die Erweiterung lädt die Zielseite direkt im Tab.
- **Kennung im Speicher der Erweiterung, nicht im Cookie.** Sie geht als Header `X-Device-Token` an den Server. `Authorization` scheidet aus, weil Apache mit PHP-FPM diesen Header oft verwirft. In der Datenbank liegt nur der SHA-256-Hash.
- **Inhalt als `kind` und `content`.** `kind` ist `url`, `message`, `blank` oder `standby`. Nur bei `url` navigiert die Erweiterung, alles andere zeigt `screen.html` ohne Seitenwechsel.
- **Geplante Änderungen und Benachrichtigungen ohne Cron.** `apply_due_changes()` und `check_offline_notifications()` laufen beim Polling und im Admin-Bereich. Maßgeblich ist die Serverzeit. `check.php` deckt nur den Fall ab, dass gar kein Monitor mehr fragt.
- **`rev` steuert das Neuladen.** Die Erweiterung navigiert nur, wenn sich `rev` oder der Zustand ändert, damit eine laufende Seite unberührt bleibt. Jede Inhaltsänderung und „Neu laden“ erhöhen `rev`. Inhaltsänderungen laufen immer über `set_monitor_content()`, das auch den Verlauf schreibt.
- **Migration über `PRAGMA user_version`.** Neue Installationen bekommen das aktuelle Schema direkt, bestehende werden in `migrate()` angehoben. Version 1 hatte die Spalte `url`; sie bleibt in migrierten Datenbanken ungenutzt stehen, weil `RENAME COLUMN` auf alten SQLite-Versionen fehlt.
- **Rückwärtskompatibel zur Erweiterung 1.0.** `poll.php` liefert zusätzlich `url`, solange alte Erweiterungen im Einsatz sein können.
- **`EXTENSION_VERSION` in `src/app.php` muss zur `version` in `extension/manifest.json` passen**, sonst zeigt der Admin-Bereich „Erweiterung veraltet“.
- **Nur `http(s)`-URLs** gelangen in die Datenbank (`normalize_url()`).
- **Ersteinrichtung** ist durch einen Schlüssel in `data/setup-key.txt` geschützt. Wird `data/admin.pass` gelöscht, startet sie erneut, das ist der vorgesehene Passwort-Reset.

## Lokal starten

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8099 -t public
```

Der Admin-Bereich liegt dann unter `http://127.0.0.1:8099/admin/`. Für die Erweiterung `SERVER_URL` in `extension/config.js` auf diese Adresse setzen, diese Änderung aber nicht committen. E-Mails lassen sich lokal mit `-d sendmail_path="tee -a mail.log"` mitschneiden.

## Beim Testen beachten

- Es gibt keine automatisierte Testsuite.
- Google Chrome ignoriert `--load-extension` seit Version 137. Automatisiert geht das Laden über `--remote-debugging-pipe`, `--enable-unsafe-extension-debugging` und den CDP-Befehl `Extensions.loadUnpacked`.
- Bei gleicher `version` im Manifest lief im selben Profil weiter der alte Hintergrundcode. Nach Änderungen an der Erweiterung die Version erhöhen oder mit frischem Profil testen.
- `php -S` bedient ohne `PHP_CLI_SERVER_WORKERS` nur eine Verbindung gleichzeitig. Vorab geöffnete Chrome-Verbindungen können ein lokales Testziel dadurch um Sekunden blockieren.
- Im Praxistest am 6. Nov. 2026 lief Version 1.0 auf Plesk mit HTTPS (PHP 8.4) und auf einem Monitor mit Windows LTSC inklusive Autostart. Die Testseiten waren eine echte TORIS-Startertafel und YouTube.
- Version 1.1 ist nur lokal getestet, mit Chrome 156 und einer migrierten Datenbank aus Version 1.0. Nicht getestet: die Neustart-Schleife in `start-monitor.bat`, das Linux-Startskript mit echtem Browser, Chromium, der Mailversand über Plesk.
