<div class="row g-4">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Monitore</h1>
            <?php if (count($monitors) > 1): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#change-modal"
                        data-all="1" data-kind="url" data-content="">Mehrere ändern</button>
            <?php endif; ?>
        </div>
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
                        <input class="form-control" id="reg-url" name="url" type="url" placeholder="https://" list="favorite-urls">
                    </div>
                    <button class="btn btn-primary w-100">Registrieren</button>
                </form>
            </div>
        </div>
    </div>
</div>

<datalist id="favorite-urls">
    <?php foreach ($favorites as $favorite): ?>
        <option value="<?= h($favorite['url']) ?>"><?= h($favorite['name']) ?></option>
    <?php endforeach; ?>
</datalist>

<div class="modal fade" id="change-modal" tabindex="-1" aria-labelledby="change-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change">
            <input type="hidden" name="change_id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="change-title">Inhalt ändern</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <fieldset class="mb-3" data-monitor-choice>
                    <legend class="form-label fs-6">Monitore</legend>
                    <?php foreach ($monitors as $monitor): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $monitor['id'] ?>" id="ids-<?= (int) $monitor['id'] ?>">
                            <label class="form-check-label" for="ids-<?= (int) $monitor['id'] ?>"><?= h($monitor['name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Inhalt</legend>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="kind" id="kind-url" value="url" checked>
                        <label class="btn btn-outline-secondary btn-sm" for="kind-url">Webseite</label>
                        <input type="radio" class="btn-check" name="kind" id="kind-message" value="message">
                        <label class="btn btn-outline-secondary btn-sm" for="kind-message">Nachricht</label>
                        <input type="radio" class="btn-check" name="kind" id="kind-blank" value="blank">
                        <label class="btn btn-outline-secondary btn-sm" for="kind-blank">Schwarzbild</label>
                        <input type="radio" class="btn-check" name="kind" id="kind-standby" value="standby">
                        <label class="btn btn-outline-secondary btn-sm" for="kind-standby">Standby</label>
                    </div>
                </fieldset>
                <div class="mb-3" data-kind-field="url">
                    <label class="form-label" for="change-url">Adresse</label>
                    <input class="form-control" id="change-url" name="url" type="url" placeholder="https://" list="favorite-urls">
                    <?php if (!$favorites): ?>
                        <div class="form-text">Häufige Adressen kannst du unter Einstellungen als Favoriten speichern.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3" data-kind-field="message">
                    <label class="form-label" for="change-message">Nachricht</label>
                    <textarea class="form-control" id="change-message" name="message" rows="3" maxlength="500"></textarea>
                    <div class="form-text">Erscheint groß und mittig auf dem Monitor.</div>
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

<div class="modal fade" id="monitor-modal" tabindex="-1" aria-labelledby="monitor-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="monitor_settings">
            <input type="hidden" name="id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="monitor-title">Monitor-Einstellungen</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="monitor-name">Name</label>
                    <input class="form-control" id="monitor-name" name="name" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="monitor-zoom">Zoom</label>
                    <div class="input-group">
                        <input class="form-control" id="monitor-zoom" name="zoom" type="number" min="25" max="500" step="5" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Vergrößert Webseiten auf diesem Monitor, zum Beispiel 150 % für große Fernseher.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="monitor-reload">Automatisch neu laden</label>
                    <div class="input-group">
                        <span class="input-group-text">alle</span>
                        <input class="form-control" id="monitor-reload" name="reload_minutes" type="number" min="0" max="1440" required>
                        <span class="input-group-text">Minuten</span>
                    </div>
                    <div class="form-text">0 schaltet das automatische Neuladen aus.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button class="btn btn-primary">Speichern</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="settings-modal" tabindex="-1" aria-labelledby="settings-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="settings-title">Einstellungen</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <h3 class="h6">Favoriten</h3>
                <?php if ($favorites): ?>
                    <ul class="list-group mb-2">
                        <?php foreach ($favorites as $favorite): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                <span class="text-break"><strong><?= h($favorite['name']) ?></strong><br><span class="small text-body-secondary"><?= h($favorite['url']) ?></span></span>
                                <form method="post" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $favorite['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="favorite_delete">Löschen</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" class="row g-2 mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="favorite_add">
                    <div class="col-sm-4"><input class="form-control" name="name" placeholder="Name" required maxlength="100" aria-label="Name des Favoriten"></div>
                    <div class="col-sm-6"><input class="form-control" name="url" type="url" placeholder="https://" required aria-label="Adresse des Favoriten"></div>
                    <div class="col-sm-2"><button class="btn btn-outline-primary w-100">Hinzufügen</button></div>
                </form>

                <h3 class="h6">Benachrichtigung bei Ausfall</h3>
                <form method="post" class="row g-2 mb-2">
                    <?= csrf_field() ?>
                    <div class="col-sm-6">
                        <input class="form-control" name="email" type="email" value="<?= h($notifyEmail) ?>" placeholder="E-Mail-Adresse" aria-label="E-Mail-Adresse für Benachrichtigungen">
                    </div>
                    <div class="col-sm-6 d-flex gap-2">
                        <button class="btn btn-outline-primary" name="action" value="notify">Speichern</button>
                        <button class="btn btn-outline-secondary" name="action" value="test_mail" <?= $notifyEmail ? '' : 'disabled' ?>>Test senden</button>
                    </div>
                </form>
                <p class="small text-body-secondary mb-1">
                    Eine E-Mail geht raus, wenn ein Monitor mehrere Minuten offline ist, und noch einmal, wenn er zurück ist. Leer lassen schaltet das aus.
                    Geprüft wird bei jeder Anfrage eines Monitors. Damit auch ein Totalausfall auffällt, in Plesk eine geplante Aufgabe vom Typ „URL abrufen“ anlegen, die jede Minute diese Adresse aufruft:
                </p>
                <input class="form-control form-control-sm font-monospace mb-4" value="<?= h($cronUrl) ?>" readonly aria-label="Adresse für die geplante Aufgabe">

                <h3 class="h6">Passwort ändern</h3>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="col-sm-5"><input class="form-control" name="current_password" type="password" placeholder="Aktuelles Passwort" required autocomplete="current-password" aria-label="Aktuelles Passwort"></div>
                    <div class="col-sm-5"><input class="form-control" name="new_password" type="password" placeholder="Neues Passwort" minlength="10" required autocomplete="new-password" aria-label="Neues Passwort"></div>
                    <div class="col-sm-2"><button class="btn btn-outline-primary w-100">Ändern</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
