<?php
/**
 * DetalStudio - Warehouse
 * Склад
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$pageTitle = 'Склад';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-actions">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-boxes me-1"></i> Склад
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/warehouse/suppliers.php" class="btn btn-outline-info">
                            <i class="bi bi-people me-2"></i>Постачальники
                        </a>
                        <a href="/warehouse/add.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Додати товар
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>Назва / SKU</th>
                                <th>Категорія</th>
                                <th>Кількість</th>
                                <th>Сер. ціна</th>
                                <th>Загальна вартість</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $items = WarehouseItem::getAll();
                            foreach ($items as $item): ?>
                                <tr class="<?= $item['current_quantity'] <= $item['min_quantity'] ? 'table-warning' : '' ?>">
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                                        <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'consumables' => 'bg-info',
                                            'parts' => 'bg-primary',
                                            'equipment' => 'bg-secondary',
                                            'tools' => 'bg-warning'
                                        ];
                                        $badgeClass = $badges[$item['category']] ?? 'bg-secondary';
                                        $categoryNames = [
                                            'consumables' => 'Витратні матеріали',
                                            'parts' => 'Запчастини',
                                            'equipment' => 'Обладнання',
                                            'tools' => 'Інструменти'
                                        ];
                                        $categoryName = $categoryNames[$item['category']] ?? $item['category'];
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $categoryName ?></span>
                                    </td>
                                    <td>
                                        <?php if ($item['current_quantity'] <= $item['min_quantity']): ?>
                                            <i class="bi bi-exclamation-triangle-fill text-danger me-1" title="Малий залишок"></i>
                                        <?php endif; ?>
                                        <?= $item['current_quantity'] ?> <?= $item['unit'] ?>
                                    </td>
                                    <td><?= format_currency($item['avg_price']) ?></td>
                                    <td><?= format_currency($item['current_quantity'] * $item['avg_price']) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="/warehouse/view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Перегляд та рух">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="/warehouse/edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Редагувати">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Видалити" onclick="confirmDeleteItem(<?= $item['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteItemForm" action="/warehouse/delete.php" method="POST" style="display: none;">
    <?= CSRF::input() ?>
    <input type="hidden" name="id" id="deleteItemId">
</form>

<script>
function confirmDeleteItem(id) {
    confirmDelete('Ви впевнені, що хочете видалити цей товар? Цю дію не можна скасувати.', function() {
        document.getElementById('deleteItemId').value = id;
        document.getElementById('deleteItemForm').submit();
    });
}
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>