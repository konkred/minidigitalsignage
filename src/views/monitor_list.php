<?php if (!$monitors): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-body-secondary py-5">
            Noch keine Monitore registriert. Starte einen Monitor und gib seinen Code rechts ein.
        </div>
    </div>
<?php endif; ?>
<?php foreach ($monitors as $monitor):
    $online = time() - (int) $monitor['last_seen'] <= $onlineAfter;
    ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div class="min-w-0">
                    <h2 class="h5 mb-1 text-break"><?= h($monitor['name']) ?></h2>
                    <?php if ($online): ?>
                        <span class="badge text-bg-success">Online</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Offline seit <?= h(format_time((int) $monitor['last_seen'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#change-modal"
                            data-id="<?= (int) $monitor['id'] ?>" data-url="<?= h($monitor['url']) ?>"
                            data-name="<?= h($monitor['name']) ?>">Inhalt ändern</button>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rename-modal"
                            data-id="<?= (int) $monitor['id'] ?>" data-name="<?= h($monitor['name']) ?>">Umbenennen</button>
                    <form method="post" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $monitor['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary" name="action" value="reload">Neu laden</button>
                        <button class="btn btn-sm btn-outline-danger" name="action" value="delete"
                                data-confirm="Monitor „<?= h($monitor['name']) ?>“ wirklich entfernen?">Entfernen</button>
                    </form>
                </div>
            </div>
            <div class="small text-body-secondary">Aktuell</div>
            <div class="text-break">
                <?php if ($monitor['url']): ?>
                    <a href="<?= h($monitor['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($monitor['url']) ?></a>
                <?php else: ?>
                    <span class="text-body-secondary">Standby-Bildschirm</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($changes[$monitor['id']])): ?>
                <div class="small text-body-secondary mt-3 mb-1">Geplant</div>
                <ul class="list-group">
                    <?php foreach ($changes[$monitor['id']] as $change): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <span class="text-break">
                                <strong><?= h(format_time((int) $change['activate_at'])) ?> Uhr:</strong>
                                <?= $change['url'] ? h($change['url']) : '<span class="text-body-secondary">Standby-Bildschirm</span>' ?>
                            </span>
                            <form method="post" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $change['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" name="action" value="cancel">Abbrechen</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
