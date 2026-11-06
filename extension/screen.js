'use strict';

const label = document.getElementById('label');
const code = document.getElementById('code');
const title = document.getElementById('title');
const message = document.getElementById('message');
const hint = document.getElementById('hint');

function show(visible) {
    for (const element of [label, code, title, message, hint]) {
        element.hidden = !visible.includes(element);
    }
}

// Wählt die größte Schrift, bei der die Nachricht noch vollständig auf den Bildschirm passt.
function fitMessage() {
    let size = 25;
    do {
        message.style.fontSize = `${size}vh`;
        size -= 0.5;
    } while (size > 2 && (message.scrollHeight > innerHeight * 0.9 || message.scrollWidth > innerWidth * 0.92));
}

function render(state, failedAt) {
    const active = state?.status === 'active';
    document.body.classList.toggle('blank', active && state.kind === 'blank');

    if (state?.status === 'pending' && !state.offline) {
        show([label, code, hint]);
        code.textContent = `${state.code.slice(0, 3)}-${state.code.slice(3)}`;
        hint.textContent = 'Diesen Code im Admin-Bereich eingeben, um den Monitor zu registrieren.';
    } else if (active && state.kind === 'blank') {
        show([]);
    } else if (active && state.kind === 'message') {
        show([message]);
        message.textContent = state.content;
        fitMessage();
    } else if (failedAt) {
        show([title, hint]);
        title.textContent = state?.name ?? 'Monitor';
        hint.textContent = 'Der Inhalt ist gerade nicht erreichbar. Neuer Versuch läuft automatisch.';
    } else if (!active || state.offline) {
        show([title, hint]);
        title.textContent = 'Verbindung wird hergestellt';
        hint.textContent = 'Der Monitor versucht es automatisch weiter.';
    } else {
        show([title, hint]);
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
addEventListener('resize', refresh);
refresh();
