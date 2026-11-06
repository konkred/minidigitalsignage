# Mini Digital Signage

Weist Monitoren zentral eine Webseite, eine Textnachricht oder ein Schwarzbild zu, sofort, nach einer Wartezeit oder zu einem festen Zeitpunkt.

- **Server** (PHP und SQLite, ohne Build-Schritt): Admin-Bereich unter `/admin` und Schnittstelle für die Monitore
- **Browser-Erweiterung** auf jedem Monitor: fragt alle 5 Sekunden den Server und zeigt den zugewiesenen Inhalt

## Funktionen

- Monitore per Code registrieren, benennen und entfernen
- Inhalt: Webseite, große Textnachricht, Schwarzbild oder Standby-Bildschirm
- Änderungen sofort, nach einer Wartezeit oder zu einem Zeitpunkt; geplante Änderungen bearbeiten oder abbrechen
- Mehrere Monitore auf einmal ändern
- Live-Vorschau: Jeder Monitor schickt alle 30 Sekunden ein Bildschirmfoto
- Status: online/offline, „Inhalt lädt nicht“, Versionen von Erweiterung und Browser
- Pro Monitor: Zoom und automatisches Neuladen im Intervall
- Favoriten für häufige Adressen
- Verlauf: welcher Monitor wann welchen Inhalt bekam (90 Tage)
- E-Mail, wenn ein Monitor ausfällt und wenn er zurück ist
- Passwort im Admin-Bereich ändern

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

Zeitzone, Offline-Schwelle für Benachrichtigungen und Absenderadresse stehen in `config.php`.

**Fehlermeldung „PHP kann src/ oder config.php nicht lesen“:** In den PHP-Einstellungen der Subdomain `open_basedir` auf `{WEBSPACEROOT}{/}{:}{TMP}{/}` setzen. Mit `{DOCROOT}` darf PHP nur in `public/` lesen.

**Passwort vergessen:** `data/admin.pass` löschen und Schritt 4 wiederholen.

**Datensicherung:** Alle Daten liegen in `data/`.

**Update:** Alle Dateien außer `data/` ersetzen. Die Datenbank wird beim ersten Aufruf automatisch angepasst.

### Benachrichtigungen

Die E-Mail-Adresse trägst du im Admin-Bereich unter **Einstellungen** ein, dort gibt es auch eine Testnachricht. Versendet wird über die PHP-Mailfunktion des Webspace.

Geprüft wird bei jeder Anfrage eines Monitors. Fallen alle Monitore gleichzeitig aus, etwa weil das Internet am Platz weg ist, fragt niemand mehr. Für diesen Fall in Plesk unter **Geplante Aufgaben** eine Aufgabe vom Typ **URL abrufen** anlegen, die jede Minute die Adresse aus den Einstellungen aufruft.

## Monitor einrichten

1. Den Ordner `extension/` und `kiosk/` an einen festen Ort auf den Monitor-PC kopieren. Beide müssen nebeneinander liegen. Ändert sich der Pfad später, gilt der Monitor als neu und zeigt wieder einen Registriercode.
   Der Ort muss für den angemeldeten Benutzer beschreibbar sein, unter Windows also zum Beispiel `C:\Signage` oder ein Ordner im Benutzerprofil, nicht `C:\Program Files`. Sonst landen Updates eventuell nicht in dem Ordner, aus dem der Browser lädt.
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

Für den Autostart das Skript unter Linux in den Autostart der Desktop-Umgebung und unter Windows als Verknüpfung in den Ordner `shell:startup` legen. Unter Windows in den Eigenschaften der Verknüpfung **Ausführen: Minimiert** wählen, denn das Konsolenfenster bleibt für den automatischen Neustart geöffnet.

Stürzt der Browser ab, starten ihn die Skripte nach 5 Sekunden neu. Wer ihn regulär mit `Alt+F4` schließt, beendet auch das Skript. Einen Mauszeiger über der Zielseite blendet unter Linux zum Beispiel `unclutter` aus.

**Erweiterung aktualisieren:** Den Ordner `extension/` ersetzen, die eigene `config.js` behalten und den Browser neu starten. Steht im Admin-Bereich „Erweiterung veraltet“, läuft auf dem Monitor noch eine ältere Version.

## Verhalten im Betrieb

- **Server nicht erreichbar:** Der aktuelle Inhalt läuft weiter, Änderungen kommen an, sobald die Verbindung zurück ist.
- **Inhalt nicht ladbar:** Statt der Browser-Fehlerseite erscheint ein neutraler Hinweis, alle 15 Sekunden folgt ein neuer Versuch. Im Admin-Bereich steht dann „Inhalt lädt nicht“.
- **Geplante Änderungen:** Maßgeblich ist die Serverzeit, die Uhr des Monitors spielt keine Rolle. Ein Monitor, der zum geplanten Zeitpunkt aus war, übernimmt die Änderung beim nächsten Start.
- **Unregistrierte Monitore:** Ein Code verfällt, wenn sich der Monitor eine Stunde lang nicht meldet. Danach bekommt er automatisch einen neuen.
