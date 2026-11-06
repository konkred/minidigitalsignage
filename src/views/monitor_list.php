<?php
/** Gibt den Inhalt als HTML aus; Adressen werden zum Link. */
function content_html(string $kind, ?string $content): string
{
    return $kind === 'url'
        ? '<a href="' . h($content) . '" target="_blank" rel="noopener noreferrer">' . h($content) . '</a>'
        : '<span class="text-body-secondary">' . h(describe_content($kind, $content)) . '</span>';
}
?>
<?php if (!$monitors): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-body-secondary py-5">
            Noch keine Monitore registriert. Starte einen Monitor und gib seinen Code rechts ein.
        </div>
    </div>
<?php endif; ?>
<?php foreach ($monitors as $monitor):
    $id = (int) $monitor['id'];
    $online = time() - (int) $monitor['last_seen'] <= $onlineAfter;
    $info = array_filter([
        $monitor['ext_version'] ? 'Erweiterung ' . $monitor['ext_version'] : null,
        $monitor['browser'],
        (int) $monitor['zoom'] !== 100 ? 'Zoom ' . (int) $monitor['zoom'] . ' %' : null,
        (int) $monitor['reload_minutes'] > 0 ? 'Neuladen alle ' . (int) $monitor['reload_minutes'] . ' Min.' : null,
    ]);
    ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-4">
                    <?php if ($monitor['shot_at']):
                        $shot = '?screenshot=' . $id . '&amp;v=' . (int) $monitor['shot_at']; ?>
                        <a href="<?= $shot ?>" target="_blank" title="Vorschau vergrößern">
                            <img src="<?= $shot ?>" class="img-fluid rounded border" alt="Vorschau von <?= h($monitor['name']) ?>">
                        </a>
                        <div class="small text-body-secondary mt-1">Vorschau von <?= h(format_time((int) $monitor['shot_at'])) ?> Uhr</div>
                    <?php else: ?>
                        <div class="ratio ratio-16x9 rounded border bg-body-secondary">
                            <div class="d-flex align-items-center justify-content-center small text-body-secondary">Noch keine Vorschau</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-sm-8">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <h2 class="h5 mb-1 text-break"><?= h($monitor['name']) ?></h2>
                            <?php if ($online): ?>
                                <span class="badge text-bg-success">Online</span>
                                <?php if ($monitor['load_failed'] && $monitor['kind'] === 'url'): ?>
                                    <span class="badge text-bg-warning">Inhalt lädt nicht</span>
                                <?php endif; ?>
                                <?php if ($monitor['ext_version'] !== EXTENSION_VERSION): ?>
                                    <span class="badge text-bg-info">Erweiterung veraltet</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Offline seit <?= h(format_time((int) $monitor['last_seen'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#change-modal"
                                    data-id="<?= $id ?>" data-name="<?= h($monitor['name']) ?>"
                                    data-kind="<?= h($monitor['kind']) ?>" data-content="<?= h($monitor['content']) ?>">Inhalt ändern</button>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#monitor-modal"
                                    data-id="<?= $id ?>" data-name="<?= h($monitor['name']) ?>"
                                    data-zoom="<?= (int) $monitor['zoom'] ?>" data-reload="<?= (int) $monitor['reload_minutes'] ?>">Einstellungen</button>
                            <form method="post" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button class="btn btn-sm btn-outline-secondary" name="action" value="reload">Neu laden</button>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="delete"
                                        data-confirm="Monitor „<?= h($monitor['name']) ?>“ wirklich entfernen?">Entfernen</button>
                            </form>
                        </div>
                    </div>
                    <div class="small text-body-secondary">Aktuell</div>
                    <div class="text-break"><?= content_html($monitor['kind'], $monitor['content']) ?></div>
                    <?php if ($info): ?>
                        <div class="small text-body-secondary mt-1"><?= h(implode(' · ', $info)) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($changes[$id])): ?>
                        <div class="small text-body-secondary mt-3 mb-1">Geplant</div>
                        <ul class="list-group">
                            <?php foreach ($changes[$id] as $change): ?>
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span class="text-break">
                                        <strong><?= h(format_time((int) $change['activate_at'])) ?> Uhr:</strong>
                                        <?= content_html($change['kind'], $change['content']) ?>
                                    </span>
                                    <form method="post" class="m-0 d-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $change['id'] ?>">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#change-modal"
                                                data-id="<?= $id ?>" data-name="<?= h($monitor['name']) ?>" data-change-id="<?= (int) $change['id'] ?>"
                                                data-kind="<?= h($change['kind']) ?>" data-content="<?= h($change['content']) ?>"
                                                data-at="<?= date('Y-m-d\TH:i', (int) $change['activate_at']) ?>">Bearbeiten</button>
                                        <button class="btn btn-sm btn-outline-secondary" name="action" value="cancel">Abbrechen</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($history[$id])): ?>
                        <button class="btn btn-link btn-sm px-0 mt-2" data-bs-toggle="collapse" data-bs-target="#history-<?= $id ?>"
                                aria-expanded="false" aria-controls="history-<?= $id ?>">Verlauf</button>
                        <div class="collapse" id="history-<?= $id ?>">
                            <ul class="list-unstyled small mb-0">
                                <?php foreach ($history[$id] as $entry): ?>
                                    <li class="text-break">
                                        <span class="text-body-secondary"><?= h(format_time((int) $entry['at'])) ?> · <?= h($entry['source']) ?>:</span>
                                        <?= content_html($entry['kind'], $entry['content']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
