<?php
/**
 * DetalStudio - Warehouse Transactions
 */

require_once __DIR__ . '/../../config/config.php';

use App\Controllers\WarehouseController;
use App\Auth;

Auth::requireAuth();

$controller = new WarehouseController();
$data = $controller->transactions();
$transactions = $data['transactions'];

$pageTitle = 'Історія руху складу';
include __DIR__ . '/../layout/header.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h1 class="h3 mb-0 text-white">📦 Історія руху</h1>
            <small class="text-muted">Всі операції по складу</small>
        </div>
        <div class="col-auto">
            <a href="/warehouse/" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Назад до складу
            </a>
        </div>
    </div>

    <div class="card bg-dark border-secondary shadow-lg">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="bg-secondary bg-opacity-10">
                        <tr>
                            <th>Дата</th>
                            <th>Тип</th>
                            <th>Товар</th>
                            <th class="text-end">Кількість</th>
                            <th>Підстава</th>
                            <th>Коментар</th>
                            <th>Хост</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): 
                             $typeClass = match($t['type']) {
                                 'in' => 'text-success',
                                 'out' => 'text-danger',
                                 'adjust' => 'text-warning',
                                 default => 'text-light'
                             };
                             $typeIcon = match($t['type']) {
                                 'in' => 'bi-arrow-down-circle-fill',
                                 'out' => 'bi-arrow-up-circle-fill',
                                 'adjust' => 'bi-exclamation-circle-fill',
                                  default => 'bi-circle'
                             };
                             $typeName = match($t['type']) {
                                 'in' => 'Прихід',
                                 'out' => 'Витрата',
                                 'adjust' => 'Інвентар.',
                                  default => $t['type']
                             };
                        ?>
                        <tr>
                            <td class="text-muted small">
                                <?= date('d.m.Y H:i', strtotime($t['created_at'])) ?>
                            </td>
                            <td>
                                <span class="<?= $typeClass ?> fw-bold">
                                    <i class="bi <?= $typeIcon ?> me-1"></i><?= $typeName ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($t['item_name']) ?></strong>
                            </td>
                            <td class="text-end font-monospace">
                                <?= $t['quantity'] > 0 ? ($t['type'] == 'out' ? '-' : '+') : '' ?><?= $t['quantity'] ?> <?= htmlspecialchars($t['unit']) ?>
                            </td>
                            <td>
                                <?php if ($t['order_number']): ?>
                                    <a href="/orders/view.php?id=<?= $t['order_id'] ?>" class="badge bg-secondary text-decoration-none">
                                        Замовлення #<?= $t['order_number'] ?>
                                    </a>
                                <?php elseif ($t['task_title']): ?>
                                    <span class="badge bg-dark border">Задача: <?= htmlspecialchars($t['task_title']) ?></span>
                                <?php elseif ($t['purchase_number']): ?>
                                    <span class="badge bg-info text-dark">Закупівля #<?= $t['purchase_number'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?= htmlspecialchars($t['notes'] ?? '') ?>
                            </td>
                            <td class="text-muted small">
                                <?= htmlspecialchars($t['created_by_name']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">Історія порожня</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
