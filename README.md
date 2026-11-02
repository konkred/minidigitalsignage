# Mini Digital Signage

Weist Monitoren zentral eine Webseite zu. Die Adresse lässt sich sofort, nach einer Wartezeit oder zu einem festen Zeitpunkt ändern.

- **Server** (PHP und SQLite, ohne Build-Schritt und ohne Cron): Admin-Bereich unter `/admin` und Schnittstelle für die Monitore
- **Browser-Erweiterung** auf jedem Monitor: fragt alle 5 Sekunden den Server und öffnet die zugewiesene Seite

## Warum eine Erweiterung?

Viele Seiten, auch TORIS.online, verbieten das Einbetten in ein iframe (`X-Frame-Options: DENY`). Nach einer normalen Weiterleitung könnte die Monitor-Seite keine neue Adresse mehr entgegennehmen. Die Erweiterung lädt die Zielseite deshalb direkt im Tab, sie läuft also genauso wie bei manuellem Aufruf.

Die Monitor-Kennung liegt im Speicher der Erweiterung statt in einem Cookie. Sie bleibt erhalten, solange Browser-Profil und Erweiterungsordner bestehen bleiben.

## Server in Plesk einrichten

Voraussetzung: PHP 8.1 oder neuer mit `pdo_sqlite`.

1. Subdomain anlegen und den gesamten Projektordner hochladen.
2. Den **Dokumentenstamm** der Subdomain auf den Unterordner `public` setzen. `data/`, `src/` und `config.php` dürfen nicht öffentlich erreichbar sein.
3. SSL-Zertifikat aktivieren (zum Beispiel Let's Encrypt).
4. `https://deine-subdomain/admin/` aufrufen. Beim ersten Aufruf entsteht die Datei `data/setup-key.txt`.
5. Den Schlüssel aus dieser Datei im Dateimanager ablesen und zusammen mit dem neuen Passwort eingeben.

Die Zeitzone steht in `config.php`.

**Passwort vergessen:** `data/admin.pass` löschen und Schritt 4 wiederholen.

**Datensicherung:** Alle Daten liegen in `data/`.

## Monitor einrichten

1. Den Ordner `extension/` und `kiosk/` an einen festen Ort auf den Monitor-PC kopieren. Beide müssen nebeneinander liegen. Ändert sich der Pfad später, gilt der Monitor als neu und zeigt wieder einen Registriercode.
2. In `extension/config.js` die Serveradresse eintragen.
3. Die Erweiterung laden:
   - **Chromium:** Nichts zu tun, das Startskript lädt sie automatisch.
   - **Google Chrome:** Chrome ignoriert das automatische Laden seit Version 137, deshalb einmalig von Hand:
     1. `start-monitor --setup` ausführen
     2. Oben rechts den **Entwicklermodus** einschalten
     3. **Entpackte Erweiterung laden** wählen und den Ordner `extension/` auswählen
     4. Der Tab wechselt danach zum Registriercode, das Fenster kann geschlossen werden
4. Monitor starten:
   - Linux: `kiosk/start-monitor.sh`
   - Windows: `kiosk\start-monitor.bat`
5. Den angezeigten Code unter `/admin` eingeben.

Für den Autostart das Skript unter Linux in den Autostart der Desktop-Umgebung und unter Windows in den Ordner `shell:startup` legen (Verknüpfung genügt).

Das Vollbild beendest du mit `Alt+F4`. Einen Mauszeiger über der Zielseite blendet unter Linux zum Beispiel `unclutter` aus.

**Erweiterung aktualisieren:** Dateien ersetzen, `version` in `manifest.json` erhöhen und den Browser neu starten. Im Test lief bei unveränderter Version im selben Profil weiter der alte Code.

## Verhalten im Betrieb

- **Server nicht erreichbar:** Die aktuelle Seite läuft weiter, Änderungen kommen an, sobald die Verbindung zurück ist.
- **Inhalt nicht ladbar:** Statt der Browser-Fehlerseite erscheint ein neutraler Hinweis, alle 15 Sekunden folgt ein neuer Versuch.
- **Geplante Änderungen:** Maßgeblich ist die Serverzeit, die Uhr des Monitors spielt keine Rolle. Ein Monitor, der zum geplanten Zeitpunkt aus war, übernimmt die Änderung beim nächsten Start.
- **Unregistrierte Monitore:** Ein Code verfällt, wenn sich der Monitor eine Stunde lang nicht meldet. Danach bekommt er automatisch einen neuen.
