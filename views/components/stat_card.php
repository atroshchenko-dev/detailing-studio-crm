<?php
/**
 * Dashboard Stat Card Component
 */
?>
<div class="col-xl-3 col-md-6 mb-4">
    <div class="card stats-card <?= $type ?>-card h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase text-muted mb-2"><?= $label ?></h6>
                    <h3 class="mb-0 count-up" 
                        <?= isset($isCurrency) && $isCurrency ? 'data-is-currency="true"' : '' ?> 
                        data-value="<?= $value ?>">0</h3>
                    <?php if (isset($subtext)): ?>
                        <small class="<?= $subtextClass ?? 'text-muted' ?>">
                            <?= $subtext ?>
                        </small>
                    <?php endif; ?>
                    <?php if (isset($trend)): ?>
                        <div class="mt-2 text-muted small" style="font-size: 0.75rem;">
                            <span class="<?= $trend >= 0 ? 'text-success' : 'text-danger' ?>">
                                <i class="bi bi-graph-<?= $trend >= 0 ? 'up' : 'down' ?>-arrow"></i>
                                <?= abs($trend) ?>%
                            </span>
                            <span class="ms-1">від минулого місяця</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="stats-icon">
                    <i class="bi bi-<?= $icon ?>"></i>
                </div>
            </div>
        </div>
    </div>
</div>
