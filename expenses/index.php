<?php
/**
 * DetalStudio - Studio Expenses List
 */

require_once __DIR__ . '/../config/config.php';


Auth::requireAuth();
Auth::requireAnyRole(['director']);

$pageTitle = 'Загальні витрати студії';

$filters = [
    'category' => $_GET['category'] ?? '',
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-t')
];

$expenses = Expense::getList($filters);
$categories = Expense::getCategories();

include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Filters -->
        <div class="card mb-4 border-secondary">
             <div class="card-header py-2">
                <i class="bi bi-filter me-2"></i>Фільтри
             </div>
            <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Категорія</label>
                    <select name="category" class="form-select">
                        <option value="">Всі категорії</option>
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $filters['category'] == $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">З дати</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">По дату</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-info w-100 me-2">
                        <i class="bi bi-filter"></i> Фільтрувати
                    </button>
                    <a href="/expenses/" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-actions">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-table me-1"></i> Список витрат
                </div>
                <a href="/expenses/add.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Додати витрату
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Категорія</th>
                            <th>Спосіб</th>
                            <th>Сума</th>
                            <th>Примітка</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Витрат не знайдено</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $total = 0;
                            foreach ($expenses as $e): 
                                $total += $e['amount'];
                            ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($e['payment_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-outline-info border border-info text-info">
                                            <?= $categories[$e['category']] ?? $e['category'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $e['payment_method'] ?></span>
                                    </td>
                                    <td class="fw-bold text-accent-cyan">
                                        <?= format_currency($e['amount']) ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($e['notes']) ?>
                                    </td>
                                    <td>
                                        <a href="/expenses/edit.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-info me-1">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button onclick="confirmDeleteExpense(<?= $e['id'] ?>)" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($expenses)): ?>
                        <tfoot>
                            <tr class="table-dark">
                                <th colspan="3" class="text-end">РАЗОМ:</th>
                                <th class="text-accent-orange"><?= format_currency($total) ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script>

function confirmDeleteExpense(id) {
    confirmDelete('Ви впевнені, що хочете видалити цей запис? Цю дію не можна скасувати.', function() {
        window.location.href = '/expenses/delete.php?id=' + id;
    });
}
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
