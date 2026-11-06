#!/usr/bin/env bash
# Startet den Browser im Vollbild als Signage-Monitor und nach einem Absturz neu.
# Wer den Browser regulär schließt (Alt+F4), beendet auch das Skript.
# Einmalig nach der Installation mit --setup aufrufen, wenn die Erweiterung
# von Hand geladen werden muss (nötig bei Google Chrome).
set -uo pipefail

extension_dir="$(cd "$(dirname "$(readlink -f "$0")")/../extension" && pwd)"
profile_dir="${XDG_CONFIG_HOME:-$HOME/.config}/signage-monitor"

browser="${SIGNAGE_BROWSER:-}"
if [[ -z "$browser" ]]; then
    for candidate in chromium chromium-browser google-chrome google-chrome-stable; do
        if command -v "$candidate" >/dev/null; then
            browser="$candidate"
            break
        fi
    done
fi
if [[ -z "$browser" ]]; then
    echo "Kein Chromium oder Chrome gefunden. Pfad über SIGNAGE_BROWSER angeben." >&2
    exit 1
fi

if [[ "${1:-}" == "--setup" ]]; then
    echo "Erweiterungsordner: $extension_dir"
    exec "$browser" --user-data-dir="$profile_dir" --no-first-run chrome://extensions
fi

while true; do
    "$browser" \
        --kiosk \
        --user-data-dir="$profile_dir" \
        --load-extension="$extension_dir" \
        --no-first-run \
        --noerrdialogs \
        --disable-infobars \
        --disable-session-crashed-bubble \
        --disable-features=Translate \
        --password-store=basic \
        about:blank
    exit_code=$?
    if [[ $exit_code -eq 0 ]]; then
        break
    fi
    echo "Browser mit Code $exit_code beendet, Neustart in 5 Sekunden." >&2
    sleep 5
done
