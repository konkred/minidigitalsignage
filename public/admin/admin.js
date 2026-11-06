'use strict';

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm]');
    if (button && !confirm(button.dataset.confirm)) {
        event.preventDefault();
    }
});

const changeModal = document.getElementById('change-modal');
const monitorModal = document.getElementById('monitor-modal');

function field(modal, name) {
    return modal.querySelector(`[name="${name}"]`);
}

function syncChangeForm() {
    const kind = changeModal.querySelector('[name=kind]:checked').value;
    changeModal.querySelectorAll('[data-kind-field]').forEach((element) => {
        element.hidden = element.dataset.kindField !== kind;
    });
    field(changeModal, 'url').required = kind === 'url';
    field(changeModal, 'message').required = kind === 'message';
    const timing = changeModal.querySelector('[name=timing]:checked').value;
    field(changeModal, 'minutes').disabled = timing !== 'delay';
    field(changeModal, 'at').disabled = timing !== 'at';
}

if (changeModal) {
    changeModal.addEventListener('change', syncChangeForm);
    changeModal.addEventListener('show.bs.modal', (event) => {
        const data = event.relatedTarget.dataset;
        const form = changeModal.querySelector('form');
        form.reset();
        field(changeModal, 'change_id').value = data.changeId ?? '';
        changeModal.querySelector(`[name=kind][value="${data.kind || 'url'}"]`).checked = true;
        field(changeModal, 'url').value = data.kind === 'url' ? data.content : '';
        field(changeModal, 'message').value = data.kind === 'message' ? data.content : '';
        changeModal.querySelectorAll('[name="ids[]"]').forEach((checkbox) => {
            checkbox.checked = data.all === '1' || checkbox.value === data.id;
        });
        // Eine geplante Änderung gehört zu genau einem Monitor.
        changeModal.querySelector('[data-monitor-choice]').hidden = Boolean(data.changeId);
        if (data.at) {
            changeModal.querySelector('#timing-at').checked = true;
            field(changeModal, 'at').value = data.at;
        }
        changeModal.querySelector('.modal-title').textContent = data.changeId
            ? `Geplante Änderung bearbeiten: ${data.name}`
            : data.all === '1' ? 'Mehrere Monitore ändern' : `Inhalt ändern: ${data.name}`;
        syncChangeForm();
    });
    monitorModal.addEventListener('show.bs.modal', (event) => {
        const data = event.relatedTarget.dataset;
        field(monitorModal, 'id').value = data.id;
        field(monitorModal, 'name').value = data.name;
        field(monitorModal, 'zoom').value = data.zoom;
        field(monitorModal, 'reload_minutes').value = data.reload;
        field(monitorModal, 'muted').checked = data.muted === '1';
        field(monitorModal, 'hide_cursor').checked = data.hideCursor === '1';
        monitorModal.querySelector('.modal-title').textContent = `Einstellungen: ${data.name}`;
    });
}

// Status, Vorschau und geplante Änderungen aktuell halten, ohne eine offene Eingabe zu stören.
const list = document.getElementById('monitors');
if (list) {
    setInterval(async () => {
        if (document.hidden || document.querySelector('.modal.show')) {
            return;
        }
        try {
            const response = await fetch(list.dataset.refresh, { cache: 'no-store' });
            if (response.status === 401) {
                location.reload();
            } else if (response.ok) {
                const open = [...list.querySelectorAll('.collapse.show')].map((element) => element.id);
                list.innerHTML = await response.text();
                open.forEach((id) => {
                    document.getElementById(id)?.classList.add('show');
                    list.querySelector(`[data-bs-target="#${id}"]`)?.setAttribute('aria-expanded', 'true');
                });
            }
        } catch {
            // Nächster Versuch beim nächsten Intervall.
        }
    }, 5000);
}
