<div class="row g-4">
    <div class="col-lg-8">
        <h1 class="h4 mb-3">Monitore</h1>
        <div id="monitors" data-refresh="?partial=list">
            <?php require __DIR__ . '/monitor_list.php'; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Monitor registrieren</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register">
                    <div class="mb-3">
                        <label class="form-label" for="reg-code">Registriercode</label>
                        <input class="form-control font-monospace text-uppercase" id="reg-code" name="code" placeholder="ABC-123" required autocomplete="off">
                        <div class="form-text">Wird auf dem neuen Monitor angezeigt.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reg-name">Name</label>
                        <input class="form-control" id="reg-name" name="name" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reg-url">Adresse <span class="text-body-secondary">(optional)</span></label>
                        <input class="form-control" id="reg-url" name="url" type="url" placeholder="https://">
                    </div>
                    <button class="btn btn-primary w-100">Registrieren</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="change-modal" tabindex="-1" aria-labelledby="change-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change">
            <input type="hidden" name="id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="change-title">Inhalt ändern</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="change-url">Adresse</label>
                    <input class="form-control" id="change-url" name="url" type="url" placeholder="https://">
                    <div class="form-text">Leer lassen, um den Standby-Bildschirm anzuzeigen.</div>
                </div>
                <fieldset>
                    <legend class="form-label fs-6">Wann soll die Änderung greifen?</legend>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="timing" id="timing-now" value="now" checked>
                        <label class="form-check-label" for="timing-now">Sofort</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="timing" id="timing-delay" value="delay">
                        <label class="form-check-label" for="timing-delay">Nach einer Wartezeit</label>
                        <div class="input-group input-group-sm mt-1">
                            <input class="form-control" name="minutes" type="number" min="1" value="15" aria-label="Wartezeit in Minuten" disabled>
                            <span class="input-group-text">Minuten</span>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="timing" id="timing-at" value="at">
                        <label class="form-check-label" for="timing-at">Zu einem Zeitpunkt</label>
                        <input class="form-control form-control-sm mt-1" name="at" type="datetime-local" aria-label="Datum und Uhrzeit" disabled>
                    </div>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button class="btn btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="rename-modal" tabindex="-1" aria-labelledby="rename-title" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="rename-title">Umbenennen</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="rename-name">Name</label>
                <input class="form-control" id="rename-name" name="name" required maxlength="100">
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>
