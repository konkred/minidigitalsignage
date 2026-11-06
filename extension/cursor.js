'use strict';

// Blendet den Mauszeiger auf angezeigten Seiten aus, solange es im Admin-Bereich eingestellt ist.
const hideCursorStyle = document.createElement('style');
hideCursorStyle.textContent = '*, *::before, *::after { cursor: none !important; }';

async function syncCursor() {
    const { state } = await chrome.storage.local.get('state');
    if (state?.status === 'active' && state.hideCursor !== false) {
        (document.head ?? document.documentElement).append(hideCursorStyle);
    } else {
        hideCursorStyle.remove();
    }
}

chrome.storage.onChanged.addListener(syncCursor);
syncCursor();
