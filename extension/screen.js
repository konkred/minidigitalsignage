'use strict';

const label = document.getElementById('label');
const code = document.getElementById('code');
const title = document.getElementById('title');
const hint = document.getElementById('hint');

function render(state, failedAt) {
    const pending = state?.status === 'pending' && !state.offline;
    label.hidden = code.hidden = !pending;
    title.hidden = pending;

    if (pending) {
        code.textContent = `${state.code.slice(0, 3)}-${state.code.slice(3)}`;
        hint.textContent = 'Diesen Code im Admin-Bereich eingeben, um den Monitor zu registrieren.';
    } else if (failedAt) {
        title.textContent = state?.name ?? 'Monitor';
        hint.textContent = 'Der Inhalt ist gerade nicht erreichbar. Neuer Versuch läuft automatisch.';
    } else if (!state || state.status === 'connecting' || state.offline) {
        title.textContent = 'Verbindung wird hergestellt';
        hint.textContent = 'Der Monitor versucht es automatisch weiter.';
    } else {
        title.textContent = state.name;
        hint.textContent = 'Derzeit ist kein Inhalt zugewiesen.';
    }
}

async function refresh() {
    const { state } = await chrome.storage.local.get('state');
    const { failedAt } = await chrome.storage.session.get('failedAt');
    render(state, failedAt);
}

chrome.storage.onChanged.addListener(refresh);
refresh();
