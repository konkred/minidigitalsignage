'use strict';

importScripts('config.js');

const POLL_MS = 5000;
const RETRY_MS = 15000;
const SCREENSHOT_MS = 30000;
const SCREENSHOT_WIDTH = 640;
const SCREEN_URL = chrome.runtime.getURL('screen.html');
const VERSION = chrome.runtime.getManifest().version;
const BROWSER = (navigator.userAgent.match(/(Edg|Chrome)\/(\d+)/) ?? []).slice(1).join(' ').replace('Edg', 'Edge');

let ticking = false;

async function fetchState() {
    const { token } = await chrome.storage.local.get('token');
    const { loadError } = await chrome.storage.session.get('loadError');
    const response = await fetch(`${SERVER_URL}/api/poll.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(token ? { 'X-Device-Token': token } : {}) },
        body: JSON.stringify({ version: VERSION, browser: BROWSER, loadFailed: Boolean(loadError) }),
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

function showsWebsite(state) {
    return state.status === 'active' && state.kind === 'url' && Boolean(state.content);
}

// Navigiert nur, wenn sich der Soll-Zustand geändert hat. So bleibt eine laufende Seite unberührt.
async function apply(state, tab) {
    const { appliedKey, failedAt, navigatedAt } = await chrome.storage.session.get(['appliedKey', 'failedAt', 'navigatedAt']);
    const website = showsWebsite(state);
    const key = website ? `content:${state.rev}` : 'screen';
    const now = Date.now();

    if (key !== appliedKey || (failedAt && now - failedAt >= RETRY_MS)) {
        await chrome.storage.session.set({
            appliedKey: key,
            failedAt: 0,
            navigatedAt: now,
            ...(key !== appliedKey ? { loadError: false } : {}),
        });
        await chrome.tabs.update(tab.id, { url: website ? state.content : SCREEN_URL });
    } else if (website && !failedAt && state.reloadMinutes > 0 && now - (navigatedAt ?? now) >= state.reloadMinutes * 60000) {
        await chrome.storage.session.set({ navigatedAt: now });
        await chrome.tabs.reload(tab.id);
    }
}

async function applyZoom(state, tab) {
    const zoom = (state.zoom ?? 100) / 100;
    if (state.status === 'active' && Math.abs((await chrome.tabs.getZoom(tab.id)) - zoom) > 0.001) {
        await chrome.tabs.setZoom(tab.id, zoom);
    }
}

// Schaltet alle Tabs stumm, nicht nur den erkannten Kiosk-Tab. Zusätzlich stellt
// content.js die Video- und Audio-Elemente der Seite selbst stumm.
async function applyMute(state) {
    const muted = state.status === 'active' && Boolean(state.muted);
    for (const tab of await chrome.tabs.query({})) {
        if (Boolean(tab.mutedInfo?.muted) !== muted) {
            await chrome.tabs.update(tab.id, { muted });
        }
    }
}

// Schickt alle 30 Sekunden ein verkleinertes Bildschirmfoto für die Vorschau im Admin-Bereich.
async function sendScreenshot(state, tab) {
    const { shotAt } = await chrome.storage.session.get('shotAt');
    if (state.status !== 'active' || state.offline || Date.now() - (shotAt ?? 0) < SCREENSHOT_MS) {
        return;
    }
    await chrome.storage.session.set({ shotAt: Date.now() });
    const dataUrl = await chrome.tabs.captureVisibleTab(tab.windowId, { format: 'jpeg', quality: 80 });
    const bitmap = await createImageBitmap(await (await fetch(dataUrl)).blob());
    const height = Math.round(bitmap.height * SCREENSHOT_WIDTH / bitmap.width);
    const canvas = new OffscreenCanvas(SCREENSHOT_WIDTH, height);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, SCREENSHOT_WIDTH, height);
    const { token } = await chrome.storage.local.get('token');
    await fetch(`${SERVER_URL}/api/screenshot.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'image/jpeg', 'X-Device-Token': token },
        body: await canvas.convertToBlob({ type: 'image/jpeg', quality: 0.7 }),
        signal: AbortSignal.timeout(10000),
    });
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
        const tab = await kioskTab();
        await apply(state, tab);
        await applyZoom(state, tab).catch(() => {});
        await applyMute(state).catch(() => {});
        await sendScreenshot(state, tab).catch(() => {});
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
    await chrome.storage.session.set({ failedAt: Date.now(), loadError: true });
    await chrome.tabs.update(details.tabId, { url: SCREEN_URL });
});

chrome.webNavigation.onCompleted.addListener(async (details) => {
    if (details.frameId === 0 && !details.url.startsWith(SCREEN_URL) && details.url.startsWith('http')) {
        await chrome.storage.session.set({ loadError: false });
    }
});

// Verhindert, dass das Betriebssystem den Bildschirm nach der Energiespar-Zeit abschaltet.
chrome.power.requestKeepAwake('display');

chrome.runtime.onInstalled.addListener(tick);
chrome.runtime.onStartup.addListener(tick);

// Das Intervall hält den Service Worker wach. Falls er doch beendet wird, weckt ihn der Alarm.
chrome.alarms.create('tick', { periodInMinutes: 0.5 });
chrome.alarms.onAlarm.addListener(tick);
setInterval(tick, POLL_MS);
tick();
