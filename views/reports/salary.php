<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Reports.php';

Auth::requireRole('director');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

$managers = App\Reports::getManagerPerformance($dateFrom, $dateTo);
$executors = App\Reports::getTasksByExecutor($dateFrom, $dateTo);

include __DIR__ . '/../layout/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 theme-text">💰 Зарплатна відомість</h1>
        
        <!-- Date Picker -->
        <form class="d-flex gap-2" method="GET">
            <input type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control form-control-sm theme-input">
            <span class="align-self-center theme-text-muted">—</span>
            <input type="date" name="date_to" value="<?= $dateTo ?>" class="form-control form-control-sm theme-input">
            <button type="submit" class="btn btn-sm btn-primary">Оновити</button>
        </form>
    </div>

    <?php include __DIR__ . '/nav.php'; ?>

    <div class="row g-4">
        
        <!-- MANAGERS -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-secondary highlight-hover">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">👨‍💼 Менеджери</h5>
                    <span class="badge bg-info">ПРОДАЖІ</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($managers)): ?>
                        <div class="text-center py-5 theme-text-muted">Даних не знайдено за цей період</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="ps-3">Ім'я</th>
                                        <th class="text-center">Замовлень</th>
                                        <th class="text-end">Оборот</th>
                                        <th class="text-center" style="width: 80px;">%</th>
                                        <th class="text-end pe-3">Бонус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($managers as $m): ?>
                                    <tr class="manager-row">
                                        <td class="ps-3 fw-bold theme-text"><?= htmlspecialchars($m['full_name']) ?></td>
                                        <td class="text-center"><?= $m['total_orders'] ?></td>
                                        <td class="text-end text-success fw-bold font-monospace" data-revenue="<?= $m['total_revenue'] ?>">
                                            <?= number_format($m['total_revenue'], 0, '.', ' ') ?> ₴
                                        </td>
                                        <td class="text-center">
                                            <input type="number" value="10" min="0" max="100" step="0.5" 
                                                   class="form-control form-control-sm text-center theme-input manager-percent">
                                        </td>
                                        <td class="text-end pe-3 text-accent-cyan fw-bold font-monospace manager-bonus">
                                            0 ₴
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- EXECUTORS -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-secondary highlight-hover">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">🛠 Виконавці</h5>
                    <span class="badge bg-warning text-dark">ЗАДАЧІ</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($executors)): ?>
                        <div class="text-center py-5 theme-text-muted">Даних не знайдено за цей період</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="ps-3">Ім'я</th>
                                        <th class="text-center">Готово</th>
                                        <th class="text-center text-danger">Простр.</th>
                                        <th class="text-center" style="width: 100px;">Ставка</th>
                                        <th class="text-end pe-3">Сума</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($executors as $e): ?>
                                    <tr class="executor-row">
                                        <td class="ps-3 fw-bold theme-text"><?= htmlspecialchars($e['full_name']) ?></td>
                                        <td class="text-center text-success fw-bold" data-tasks="<?= $e['completed_tasks'] ?>">
                                            <?= $e['completed_tasks'] ?>
                                        </td>
                                        <td class="text-center text-danger"><?= $e['overdue_tasks'] ?></td>
                                        <td class="text-center">
                                            <input type="number" value="200" min="0" step="10" 
                                                   class="form-control form-control-sm text-center theme-input executor-rate">
                                        </td>
                                        <td class="text-end pe-3 text-accent-cyan fw-bold font-monospace executor-total">
                                            0 ₴
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const formatCurrency = (val) => new Intl.NumberFormat('uk-UA').format(Math.round(val)) + ' ₴';

    // Manager Calculator
    const updateManagerBonus = (row) => {
        const rev = parseFloat(row.querySelector('[data-revenue]').dataset.revenue);
        const pct = parseFloat(row.querySelector('.manager-percent').value) || 0;
        const bonus = rev * (pct / 100);
        row.querySelector('.manager-bonus').textContent = formatCurrency(bonus);
    };

    document.querySelectorAll('.manager-percent').forEach(input => {
        const row = input.closest('tr');
        input.addEventListener('input', () => updateManagerBonus(row));
        updateManagerBonus(row);
    });

    // Executor Calculator
    const updateExecutorTotal = (row) => {
        const tasks = parseInt(row.querySelector('[data-tasks]').dataset.tasks);
        const rate = parseFloat(row.querySelector('.executor-rate').value) || 0;
        const total = tasks * rate;
        row.querySelector('.executor-total').textContent = formatCurrency(total);
    };

    document.querySelectorAll('.executor-rate').forEach(input => {
        const row = input.closest('tr');
        input.addEventListener('input', () => updateExecutorTotal(row));
        updateExecutorTotal(row);
    });
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
