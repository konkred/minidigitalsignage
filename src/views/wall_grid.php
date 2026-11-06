<?php if (!$monitors): ?>
    <p class="text-body-secondary">Noch keine Monitore registriert.</p>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($monitors as $monitor):
        $id = (int) $monitor['id'];
        $online = time() - (int) $monitor['last_seen'] <= $onlineAfter;
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <?php if ($monitor['shot_at']):
                    $shot = '?screenshot=' . $id . '&amp;v=' . (int) $monitor['shot_at']; ?>
                    <a href="<?= $shot ?>" target="_blank" title="Vorschau vergrößern">
                        <img src="<?= $shot ?>" class="card-img-top border-bottom" alt="Vorschau von <?= h($monitor['name']) ?>">
                    </a>
                <?php else: ?>
                    <div class="ratio ratio-16x9 bg-body-secondary border-bottom">
                        <div class="d-flex align-items-center justify-content-center small text-body-secondary">Noch keine Vorschau</div>
                    </div>
                <?php endif; ?>
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h2 class="h6 mb-0 text-break"><?= h($monitor['name']) ?></h2>
                        <div class="d-flex flex-wrap gap-1">
                            <?php if (!$online): ?>
                                <span class="badge text-bg-secondary">Offline seit <?= h(format_time((int) $monitor['last_seen'])) ?></span>
                            <?php elseif ($monitor['load_failed'] && $monitor['kind'] === 'url'): ?>
                                <span class="badge text-bg-warning">Inhalt lädt nicht</span>
                            <?php else: ?>
                                <span class="badge text-bg-success">Online</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($monitor['shot_at']): ?>
                        <div class="small text-body-secondary">Vorschau von <?= h(format_time((int) $monitor['shot_at'])) ?> Uhr</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
