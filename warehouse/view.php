<?php
/**
 * DetalStudio - View Warehouse Item
 * Перегляд товару та історія руху
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('/warehouse/');
}

$item = WarehouseItem::getById($id);
if (!$item) {
    Flash::error('Товар не знайдено');
    redirect('/warehouse/');
}

// Handle Form Submissions (Stock Movements)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $action = $_POST['action'] ?? '';
    $quantity = floatval($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    $result = false;
    
    if ($quantity <= 0) {
        Flash::error('Кількість має бути більше нуля');
    } else {
        if ($action === 'add_stock') {
            $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
            $result = WarehouseItem::addStock($id, $quantity, $price, $notes);
            if ($result === true) Flash::success('Залишок додано успішно');
        } elseif ($action === 'remove_stock') {
            $result = WarehouseItem::removeStock($id, $quantity, null, null, $notes);
            if ($result === true) Flash::success('Залишок списано успішно');
        } elseif ($action === 'adjust_stock') {
            // Here quantity is the NEW REAL quantity
            $result = WarehouseItem::adjustStock($id, $quantity, $notes);
            if ($result === true) Flash::success('Інвентаризацію проведено успішно');
        }
        
        if ($result !== true) {
            $error = is_string($result) ? $result : 'Виникла помилка при оновленні залишку';
            Flash::error($error);
        } else {
            // Refresh item data
            $item = WarehouseItem::getById($id);
        }
    }
}

$transactions = WarehouseItem::getTransactions($id);

$pageTitle = 'Картка товару: ' . $item['name'];
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <!-- Item Details -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Інформація</h5>
                <a href="/warehouse/edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i>
                </a>
            </div>
            <div class="card-body">
                <h3 class="card-title text-primary"><?= htmlspecialchars($item['name']) ?></h3>
                <h6 class="card-subtitle mb-2 text-muted">SKU: <?= htmlspecialchars($item['sku'] ?? 'Не вказано') ?></h6>
                
                <hr>
                
                <div class="row mb-3">
                    <div class="col-6 text-muted">Категорія:</div>
                    <div class="col-6 fw-bold">
                        <?php
                        $categoryNames = [
                            'consumables' => 'Витратні матеріали',
                            'parts' => 'Запчастини',
                            'equipment' => 'Обладнання',
                            'tools' => 'Інструменти'
                        ];
                        echo $categoryNames[$item['category']] ?? $item['category'];
                        ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6 text-muted">Поточний залишок:</div>
                    <div class="col-6 fw-bold fs-5">
                        <span class="<?= $item['current_quantity'] <= $item['min_quantity'] ? 'text-danger' : 'text-success' ?>">
                            <?= $item['current_quantity'] ?> <?= $item['unit'] ?>
                        </span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6 text-muted">Середня ціна:</div>
                    <div class="col-6 fw-bold"><?= format_currency($item['avg_price']) ?></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6 text-muted">Загальна вартість:</div>
                    <div class="col-6 fw-bold"><?= format_currency($item['current_quantity'] * $item['avg_price']) ?></div>
                </div>
                
                <?php if ($item['description']): ?>
                <div class="mt-3">
                    <small class="text-muted">Опис:</small>
                    <p class="small"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStockModal">
                        <i class="bi bi-plus-circle me-2"></i>Прихід
                    </button>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#removeStockModal">
                        <i class="bi bi-dash-circle me-2"></i>Списання
                    </button>
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                        <i class="bi bi-sliders me-2"></i>Інвентаризація
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Історія руху</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Тип</th>
                                <th>Кількість</th>
                                <th>Залишок після</th>
                                <th>Примітки</th>
                                <th>Користувач</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Історія порожня</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><?= date('d.m.Y H:i', strtotime($trans['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $types = [
                                                'in' => ['text-success', 'Прихід', '+'],
                                                'out' => ['text-danger', 'Витрата', '-'],
                                                'adjust' => ['text-warning', 'Коригування', '']
                                            ];
                                            $type = $types[$trans['type']] ?? ['text-secondary', $trans['type'], ''];
                                            ?>
                                            <span class="<?= $type[0] ?> fw-bold"><?= $type[1] ?></span>
                                        </td>
                                        <td>
                                            <span class="<?= $type[0] ?>">
                                                <?= $type[2] ?><?= $trans['quantity'] ?>
                                            </span>
                                        </td>
                                        <td>-</td> <!-- Needs DB calc or storing remaining balance in log -->
                                        <td>
                                            <small><?= htmlspecialchars($trans['notes'] ?? '') ?></small>
                                            <?php if ($trans['order_id']): ?>
                                                <a href="/orders/view.php?id=<?= $trans['order_id'] ?>" class="text-decoration-none ms-1">Order #<?= $trans['order_number'] ?? $trans['order_id'] ?></a>
                                            <?php elseif ($trans['purchase_id']): ?>
                                                <a href="/purchases/view.php?id=<?= $trans['purchase_id'] ?>" class="badge bg-info text-dark text-decoration-none ms-1">
                                                    <i class="bi bi-cart me-1"></i>Закупівля #<?= $trans['purchase_number'] ?? $trans['purchase_id'] ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= htmlspecialchars($trans['created_by_name']) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Stock -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Прихід товару</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <?= CSRF::input() ?>
                <input type="hidden" name="action" value="add_stock">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Кількість (<?= $item['unit'] ?>)</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ціна закупівлі (за од.)</label>
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="Залиште порожнім, якщо ціна попередня (<?= $item['avg_price'] ?>)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-success">Додати</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Remove Stock -->
<div class="modal fade" id="removeStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Списання товару</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <?= CSRF::input() ?>
                <input type="hidden" name="action" value="remove_stock">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Кількість (<?= $item['unit'] ?>)</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" required min="0.01" max="<?= $item['current_quantity'] ?>">
                        <div class="form-text">Доступно: <?= $item['current_quantity'] ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примітки (Причина)</label>
                        <textarea name="notes" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-danger">Списати</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Adjust Stock -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Інвентаризація (Коригування)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <?= CSRF::input() ?>
                <input type="hidden" name="action" value="adjust_stock">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Фактична кількість (<?= $item['unit'] ?>)</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" required min="0">
                        <div class="form-text">Поточна облікова кількість: <?= $item['current_quantity'] ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
