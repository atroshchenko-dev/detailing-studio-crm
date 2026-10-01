<?php
/**
 * DetalStudio - Audit Log
 * Журнал подій
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireRole('director');

$pageTitle = 'Журнал подій';

// Filters
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d')
];

$logs = Audit::getLog($filters);
$users = Auth::getAllUsers();
$actions = Audit::getActionTypes();

include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-secondary">
            <div class="card-header bg-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-accent-orange">
                    <i class="bi bi-journal-text me-2"></i>ЖУРНАЛ ПОДІЙ
                </h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="bi bi-filter"></i> Фільтри
                </button>
            </div>
            
            <div class="collapse show" id="filterCollapse">
                <div class="card-body bg-dark border-bottom border-secondary">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Користувач</label>
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="">Всі користувачі</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $filters['user_id'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Дія</label>
                            <select name="action" class="form-select form-select-sm">
                                <option value="">Всі дії</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?= $action ?>" <?= $filters['action'] == $action ? 'selected' : '' ?>>
                                        <?= Audit::getActionDescription($action) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Від дати</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $filters['date_from'] ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">До дати</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $filters['date_to'] ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-primary w-100 me-2">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="/admin/audit.php" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-dark text-light">
                            <tr>
                                <th style="width: 150px;">Дата/Час</th>
                                <th style="width: 200px;">Користувач</th>
                                <th style="width: 200px;">Дія</th>
                                <th>Деталі</th>
                                <th style="width: 120px;">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">Записів не знайдено</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-muted">
                                            <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php if ($log['user_name']): ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-person-circle text-secondary me-2"></i>
                                                    <?= htmlspecialchars($log['user_name']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Система</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary border border-secondary text-light fw-normal">
                                                <?= Audit::getActionDescription($log['action']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="d-block text-muted mb-1">
                                                ID запису: <strong><?= $log['record_id'] ?></strong> (<?= $log['table_name'] ?>)
                                            </small>
                                            
                                            <?php if ($log['old_value'] || $log['new_value']): ?>
                                                <button class="btn btn-xs btn-outline-info py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#details_<?= $log['id'] ?>">
                                                    Показати зміни
                                                </button>
                                                <div class="collapse mt-2" id="details_<?= $log['id'] ?>">
                                                    <div class="card card-body bg-dark border-secondary p-2">
                                                        <?php if ($log['old_value']): ?>
                                                            <div class="mb-2">
                                                                <strong class="text-danger">Було:</strong>
                                                                <pre class="mb-0 text-muted" style="font-size: 11px;"><?= htmlspecialchars(print_r(json_decode($log['old_value'], true), true)) ?></pre>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($log['new_value']): ?>
                                                            <div>
                                                                <strong class="text-success">Стало:</strong>
                                                                <pre class="mb-0 text-muted" style="font-size: 11px;"><?= htmlspecialchars(print_r(json_decode($log['new_value'], true), true)) ?></pre>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?= $log['ip_address'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer bg-dark border-top border-secondary text-muted small">
                Показано останні <?= count($logs) ?> записів
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
