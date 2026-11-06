'use strict';

// Setzt auf jeder angezeigten Seite die Einstellungen aus dem Admin-Bereich um:
// Mauszeiger ausblenden und Video- und Audio-Elemente stummschalten.
const hideCursorStyle = document.createElement('style');
hideCursorStyle.textContent = '*, *::before, *::after { cursor: none !important; }';
const mutedByUs = new WeakSet();
let muteMedia = false;

function muteElement(element) {
    if (muteMedia && element instanceof HTMLMediaElement && !element.muted) {
        element.muted = true;
        mutedByUs.add(element);
    }
}

function syncMedia() {
    for (const element of document.querySelectorAll('video, audio')) {
        if (muteMedia) {
            muteElement(element);
        } else if (mutedByUs.has(element)) {
            element.muted = false;
            mutedByUs.delete(element);
        }
    }
}

async function syncSettings() {
    const { state } = await chrome.storage.local.get('state');
    const active = state?.status === 'active';
    if (active && state.hideCursor !== false) {
        (document.head ?? document.documentElement).append(hideCursorStyle);
    } else {
        hideCursorStyle.remove();
    }
    muteMedia = active && Boolean(state.muted);
    syncMedia();
}

// Seiten wie YouTube legen Videos erst später an oder schalten den Ton selbst wieder ein.
for (const type of ['play', 'volumechange', 'loadedmetadata']) {
    document.addEventListener(type, (event) => muteElement(event.target), true);
}
new MutationObserver(() => muteMedia && syncMedia()).observe(document, { childList: true, subtree: true });

chrome.storage.onChanged.addListener(syncSettings);
syncSettings();
