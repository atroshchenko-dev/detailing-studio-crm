<?php
/**
 * DetalStudio - Employee Payments List
 */

require_once __DIR__ . '/../config/config.php';
use App\Payments;
use App\Auth;
use App\Flash;

// Check access - only director
if (!Auth::isAdmin()) {
    Flash::set('danger', 'У вас немає прав для доступу до цього розділу');
    redirect('/');
}

$pageTitle = 'Виплати співробітникам';

// Filters
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$payments = Payments::getAll($filters);
$employees = Auth::getAllUsers(); // Assuming this exists or I'll need to check
$types = Payments::getTypes();
$methods = Payments::getMethods();

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
                    <label class="form-label">Співробітник</label>
                    <select name="user_id" class="form-select">
                        <option value="">Всі співробітники</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filters['user_id'] == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['full_name']) ?>
                            </option>
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
                        <i class="bi bi-filter me-2"></i>Фільтрувати
                    </button>
                    <a href="/payments/" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-actions">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-wallet2 me-1"></i> Реєстр виплат
                </div>
                <a href="/payments/add.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Додати виплату
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Співробітник</th>
                            <th>Тип</th>
                            <th>Спосіб</th>
                            <th>Сума</th>
                            <th>Примітка</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Виплат не знайдено</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($payment['payment_date'])) ?></td>
                                    <td>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($payment['employee_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $types[$payment['payment_type']] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $methods[$payment['payment_method']] ?></span>
                                    </td>
                                    <td>
                                        <span class="text-accent-orange fw-bold"><?= format_currency($payment['amount']) ?></span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($payment['notes'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/payments/edit.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="#" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Ви впевнені, що хочете видалити цей платіж?', () => window.location.href='/payments/delete.php?id=<?= $payment['id'] ?>')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
