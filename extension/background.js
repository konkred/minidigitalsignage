'use strict';

importScripts('config.js');

const POLL_MS = 5000;
const RETRY_MS = 15000;
const SCREEN_URL = chrome.runtime.getURL('screen.html');

let ticking = false;

async function fetchState() {
    const { token } = await chrome.storage.local.get('token');
    const response = await fetch(`${SERVER_URL}/api/poll.php`, {
        method: 'POST',
        headers: token ? { 'X-Device-Token': token } : {},
        cache: 'no-store',
        signal: AbortSignal.timeout(POLL_MS - 500),
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    const { token: newToken, ...state } = await response.json();
    if (newToken) {
        await chrome.storage.local.set({ token: newToken });
    }
    return state;
}

async function kioskTab() {
    const [tab] = await chrome.tabs.query({ active: true, lastFocusedWindow: true });
    return tab ?? (await chrome.tabs.query({ active: true }))[0] ?? chrome.tabs.create({});
}

// Navigiert nur, wenn sich der Soll-Zustand geändert hat. So bleibt eine laufende Seite unberührt.
async function apply(state) {
    const { appliedKey, failedAt } = await chrome.storage.session.get(['appliedKey', 'failedAt']);
    const hasContent = state.status === 'active' && state.url;
    const key = hasContent ? `content:${state.rev}` : 'screen';
    if (key === appliedKey && (!failedAt || Date.now() - failedAt < RETRY_MS)) {
        return;
    }
    await chrome.storage.session.set({ appliedKey: key, failedAt: 0 });
    const tab = await kioskTab();
    await chrome.tabs.update(tab.id, { url: hasContent ? state.url : SCREEN_URL });
}

async function tick() {
    if (ticking) {
        return;
    }
    ticking = true;
    try {
        let state;
        try {
            state = await fetchState();
        } catch {
            // Server nicht erreichbar: Der letzte bekannte Inhalt läuft weiter.
            const { state: lastState } = await chrome.storage.local.get('state');
            state = { ...(lastState ?? { status: 'connecting' }), offline: true };
        }
        await chrome.storage.local.set({ state });
        await apply(state);
    } catch (error) {
        console.error('Signage tick failed', error);
    } finally {
        ticking = false;
    }
}

// Kann ein Inhalt nicht geladen werden, erscheint statt der Browser-Fehlerseite der eigene Bildschirm.
chrome.webNavigation.onErrorOccurred.addListener(async (details) => {
    if (details.frameId !== 0 || details.error === 'net::ERR_ABORTED' || details.url.startsWith(SCREEN_URL)) {
        return;
    }
    await chrome.storage.session.set({ failedAt: Date.now() });
    await chrome.tabs.update(details.tabId, { url: SCREEN_URL });
});

chrome.runtime.onInstalled.addListener(tick);
chrome.runtime.onStartup.addListener(tick);

// Das Intervall hält den Service Worker wach. Falls er doch beendet wird, weckt ihn der Alarm.
chrome.alarms.create('tick', { periodInMinutes: 0.5 });
chrome.alarms.onAlarm.addListener(tick);
setInterval(tick, POLL_MS);
tick();
