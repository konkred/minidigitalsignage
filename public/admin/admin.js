'use strict';

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm]');
    if (button && !confirm(button.dataset.confirm)) {
        event.preventDefault();
    }
});

const changeModal = document.getElementById('change-modal');
const renameModal = document.getElementById('rename-modal');

function syncTiming() {
    const timing = changeModal.querySelector('[name=timing]:checked').value;
    changeModal.querySelector('[name=minutes]').disabled = timing !== 'delay';
    changeModal.querySelector('[name=at]').disabled = timing !== 'at';
}

if (changeModal) {
    changeModal.addEventListener('change', syncTiming);
    changeModal.addEventListener('show.bs.modal', (event) => {
        const data = event.relatedTarget.dataset;
        changeModal.querySelector('form').reset();
        changeModal.querySelector('[name=id]').value = data.id;
        changeModal.querySelector('[name=url]').value = data.url;
        changeModal.querySelector('.modal-title').textContent = `Inhalt ändern: ${data.name}`;
        syncTiming();
    });
    renameModal.addEventListener('show.bs.modal', (event) => {
        const data = event.relatedTarget.dataset;
        renameModal.querySelector('[name=id]').value = data.id;
        renameModal.querySelector('[name=name]').value = data.name;
    });
}

// Status und geplante Änderungen aktuell halten, ohne eine offene Eingabe zu stören.
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
                list.innerHTML = await response.text();
            }
        } catch {
            // Nächster Versuch beim nächsten Intervall.
        }
    }, 5000);
}
