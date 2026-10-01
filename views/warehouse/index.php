<?php
/**
 * DetalStudio - Warehouse List
 *
 * @var array[] $items
 * @var float $totalValue
 * @var int $lowStockCount
 * @var array[] $suppliers
 */

require_once __DIR__ . '/../../config/config.php';

use App\Controllers\WarehouseController;
use App\Auth;
use App\CSRF;

Auth::requireAuth();

$controller = new WarehouseController();
$controller->handleRequest(); // Handle POSTs

$data = $controller->index();
extract($data);

$pageTitle = 'Складський облік';
$additionalScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>'; // For future charts?

include __DIR__ . '/../layout/header.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h1 class="h3 mb-0 text-white">📦 Склад</h1>
            <small class="text-muted">Керування залишками та товарами</small>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <a href="/warehouse/transactions.php" class="btn btn-outline-info">
                   <i class="bi bi-clock-history me-1"></i>Історія руху
                </a>
                <?php if (Auth::isAtLeast('manager')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createItemModal">
                    <i class="bi bi-plus-lg me-1"></i>Новий товар
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4 col-xl-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Загальна вартість складу</h6>
                    <div class="h2 mb-0 text-success fw-mono"><?= number_format($totalValue, 0, '.', ' ') ?> ₴</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                     <h6 class="text-muted text-uppercase mb-2">Всього позицій</h6>
                     <div class="h2 mb-0 text-white"><?= count($items) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Закінчуються (Low Stock)</h6>
                    <?php $lowStockClass = $lowStockCount > 0 ? 'text-warning' : 'text-success'; ?>
                    <div class="h2 mb-0 <?= $lowStockClass ?>"><?= $lowStockCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card bg-dark border-secondary mb-4">
        <div class="card-body p-2">
            <div class="input-group">
                <span class="input-group-text bg-secondary border-secondary text-light"><i class="bi bi-search"></i></span>
                <input type="text" id="warehouseSearch" class="form-control bg-dark text-light border-secondary" placeholder="Пошук (Назва, Артикул, Категорія)...">
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card bg-dark border-secondary shadow-lg">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle" id="warehouseTable">
                    <thead class="bg-secondary bg-opacity-10">
                        <tr>
                            <th class="ps-3">Назва / Артикул</th>
                            <th>Категорія</th>
                            <th class="text-end">Залишок</th>
                            <th class="text-end">Мін. к-сть</th>
                            <th class="text-end">Сер. ціна</th>
                            <th class="text-end">Сума</th>
                            <th class="text-end pe-3">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                             $isLow = $item['current_quantity'] <= $item['min_quantity'];
                             $totalItemValue = $item['current_quantity'] * $item['avg_price'];
                        ?>
                        <tr class="<?= $isLow ? 'table-warning text-dark' : '' ?> item-row" data-search="<?= mb_strtolower($item['name'] . ' ' . $item['sku'] . ' ' . $item['category']) ?>">
                            <td class="ps-3">
                                <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                                <?php if ($item['sku']): ?>
                                    <small class="<?= $isLow ? 'text-dark opacity-75' : 'text-muted' ?>"><?= htmlspecialchars($item['sku']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary text-light bg-opacity-50 border border-secondary"><?= htmlspecialchars($item['category']) ?></span>
                            </td>
                            <td class="text-end font-monospace fs-5">
                                <?= $item['current_quantity'] ?> <small class="fs-6 text-muted"><?= htmlspecialchars($item['unit']) ?></small>
                            </td>
                            <td class="text-end text-muted small">
                                <?= $item['min_quantity'] ?>
                            </td>
                            <td class="text-end text-muted">
                                <?= number_format($item['avg_price'], 2) ?> ₴
                            </td>
                            <td class="text-end fw-bold">
                                <?= number_format($totalItemValue, 0, '.', ' ') ?> ₴
                            </td>
                            <td class="text-end pe-3">
                                <?php if (Auth::isAtLeast('manager')): ?>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-success" onclick="openAddStockModal(<?= htmlspecialchars(json_encode($item)) ?>)" title="Додати залишок">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-light" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)" title="Редагувати">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if (Auth::isDirector()): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $item['id'] ?>)" title="Видалити">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam display-1 opacity-25"></i>
                                    <p class="mt-3">Склад порожній</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Поповнення складу: <span id="addStockItemName" class="text-info"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= CSRF::input() ?>
                <input type="hidden" name="action" value="add_stock">
                <input type="hidden" name="id" id="addStockId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Кількість (<span id="addStockUnit"></span>) *</label>
                        <input type="number" name="quantity" class="form-control bg-secondary text-light border-0" step="0.001" min="0.001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ціна закупівлі за одиницю (₴)</label>
                        <input type="number" name="price" class="form-control bg-secondary text-light border-0" step="0.01" min="0" placeholder="Залиште пустим, якщо ціна не змінилась">
                        <div class="form-text text-muted">Поточна середня: <span id="addStockCurrentPrice"></span> ₴</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <input type="text" name="notes" class="form-control bg-secondary text-light border-0" placeholder="Наприклад: Закупівля від постачальника Х">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-success">Підтвердити</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="createItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="itemModalTitle">Новий товар</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= CSRF::input() ?>
                <input type="hidden" name="action" value="create" id="itemFormAction">
                <input type="hidden" name="id" id="itemId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Назва *</label>
                        <input type="text" name="name" id="itemName" class="form-control bg-secondary text-light border-0" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Артикул (SKU)</label>
                            <input type="text" name="sku" id="itemSku" class="form-control bg-secondary text-light border-0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Категорія *</label>
                            <input type="text" name="category" id="itemCategory" class="form-control bg-secondary text-light border-0" list="categoryList" required>
                            <datalist id="categoryList">
                                <?php 
                                $cats = array_unique(array_column($items, 'category'));
                                foreach ($cats as $c) echo "<option value='".htmlspecialchars($c)."'>";
                                ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Одиниця виміру *</label>
                            <select name="unit" id="itemUnit" class="form-select bg-secondary text-light border-0">
                                <option value="шт">шт (Штуки)</option>
                                <option value="л">л (Літри)</option>
                                <option value="мл">мл (Мілілітри)</option>
                                <option value="кг">кг (Кілограми)</option>
                                <option value="г">г (Грами)</option>
                                <option value="м">м (Метри)</option>
                                <option value="комплект">комплект</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Мін. залишок</label>
                            <input type="number" name="min_quantity" id="itemMinQty" class="form-control bg-secondary text-light border-0" step="0.001">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Середня ціна (Собівартість) ₴</label>
                        <input type="number" name="avg_price" id="itemPrice" class="form-control bg-secondary text-light border-0" step="0.01">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" id="itemNotes" class="form-control bg-secondary text-light border-0" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm">
    <?= CSRF::input() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
    // Search
    document.getElementById('warehouseSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.item-row').forEach(row => {
            const text = row.dataset.search;
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    // Modals config
    const addStockModal = new bootstrap.Modal(document.getElementById('addStockModal'));
    const itemModal = new bootstrap.Modal(document.getElementById('createItemModal'));

    function openAddStockModal(item) {
        document.getElementById('addStockId').value = item.id;
        document.getElementById('addStockItemName').textContent = item.name;
        document.getElementById('addStockUnit').textContent = item.unit;
        document.getElementById('addStockCurrentPrice').textContent = item.avg_price;
        addStockModal.show();
    }

    function openEditModal(item) {
        document.getElementById('itemModalTitle').textContent = 'Редагувати товар';
        document.getElementById('itemFormAction').value = 'edit';
        document.getElementById('itemId').value = item.id;
        
        document.getElementById('itemName').value = item.name;
        document.getElementById('itemSku').value = item.sku;
        document.getElementById('itemCategory').value = item.category;
        document.getElementById('itemUnit').value = item.unit;
        document.getElementById('itemMinQty').value = item.min_quantity;
        document.getElementById('itemPrice').value = item.avg_price; // Be careful, users might mess this up
        document.getElementById('itemNotes').value = item.notes;
        
        itemModal.show();
    }
    
    // Reset modal on close for Create
    document.getElementById('createItemModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('itemModalTitle').textContent = 'Новий товар';
        document.getElementById('itemFormAction').value = 'create';
        document.getElementById('itemId').value = '';
        this.querySelector('form').reset();
    });

    function confirmDelete(id) {
        if(confirm('Ви впевнені? Це видалить товар зі списку (м\'яке видалення).')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
