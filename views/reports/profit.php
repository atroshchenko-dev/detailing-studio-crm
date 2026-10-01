<?php
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/Reports.php';

Auth::requireRole('director');

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

$summary = App\Reports::getFinancialSummary($dateFrom, $dateTo);

// Calculate Breakdown
$income = $summary['revenue'];
$expenses = $summary['total_expenses']; // Already includes purchase + salary + studio
$profit = $summary['profit'];

$profitMargin = $income > 0 ? ($profit / $income) * 100 : 0;

include __DIR__ . '/../layout/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 theme-text">📈 Звіт про прибутки та збитки (P&L)</h1>
        
        <form class="d-flex gap-2" method="GET">
            <input type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control form-control-sm theme-input">
            <span class="align-self-center theme-text-muted">—</span>
            <input type="date" name="date_to" value="<?= $dateTo ?>" class="form-control form-control-sm theme-input">
            <button type="submit" class="btn btn-sm btn-primary">Оновити</button>
        </form>
    </div>

    <?php include __DIR__ . '/nav.php'; ?>

    <!-- KPI CARDS -->
    <div class="row g-4 mb-4">
        <!-- Revenue -->
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-start border-info border-4 theme-card">
                <div class="card-body">
                    <h6 class="text-uppercase theme-text-muted small mb-2">Загальний дохід</h6>
                    <h3 class="mb-0 text-info font-monospace"><?= number_format($income, 0, '.', ' ') ?> ₴</h3>
                </div>
            </div>
        </div>

        <!-- Expenses -->
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-start border-danger border-4 theme-card">
                <div class="card-body">
                    <h6 class="text-uppercase theme-text-muted small mb-2">Загальні витрати</h6>
                    <h3 class="mb-0 text-danger font-monospace"><?= number_format($expenses, 0, '.', ' ') ?> ₴</h3>
                </div>
            </div>
        </div>

        <!-- Profit -->
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-start <?= $profit >= 0 ? 'border-success' : 'border-danger' ?> border-4 theme-card">
                <div class="card-body">
                    <h6 class="text-uppercase theme-text-muted small mb-2">Чистий прибуток</h6>
                    <h3 class="mb-0 <?= $profit >= 0 ? 'text-success' : 'text-danger' ?> font-monospace">
                        <?= number_format($profit, 0, '.', ' ') ?> ₴
                    </h3>
                    <small class="text-muted">Рентабельність: <?= number_format($profitMargin, 1) ?>%</small>
                </div>
            </div>
        </div>
        
        <!-- Category Breakdown -->
        <div class="col-md-3">
            <div class="card h-100 shadow-sm theme-card">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-center">
                     <div class="d-flex justify-content-between small mb-1">
                         <span class="theme-text-muted">Закупівлі:</span>
                         <span class="theme-text fw-bold"><?= number_format($summary['purchase_expenses'], 0, '.', ' ') ?></span>
                     </div>
                     <div class="d-flex justify-content-between small mb-1">
                         <span class="theme-text-muted">Зарплати:</span>
                         <span class="theme-text fw-bold"><?= number_format($summary['salary_expenses'], 0, '.', ' ') ?></span>
                     </div>
                     <div class="d-flex justify-content-between small">
                         <span class="theme-text-muted">Студія:</span>
                         <span class="theme-text fw-bold"><?= number_format($summary['studio_expenses'], 0, '.', ' ') ?></span>
                     </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0">Структура фінансів</h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribution Pie -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0">Розподіл витрат</h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="expenseChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const textColor = isLight ? '#666' : '#aaa';
    const gridColor = isLight ? 'rgba(0,0,0,0.05)' : 'rgba(255, 255, 255, 0.05)';

    // 1. Bar Chart
    const ctxMain = document.getElementById('mainChart').getContext('2d');
    new Chart(ctxMain, {
        type: 'bar',
        data: {
            labels: ['Доход', 'Витрати', 'Прибуток'],
            datasets: [{
                data: [<?= $income ?>, <?= $expenses ?>, <?= $profit ?>],
                backgroundColor: [
                    'rgba(25, 135, 84, 0.2)', // Green
                    'rgba(220, 53, 69, 0.2)',  // Red
                    'rgba(13, 110, 253, 0.2)'  // Blue
                ],
                borderColor: ['#198754', '#dc3545', '#0d6efd'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: textColor }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: textColor }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // 2. Doughnut Chart
    const ctxExp = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctxExp, {
        type: 'doughnut',
        data: {
            labels: ['Закупівлі', 'Зарплати', 'Студія'],
            datasets: [{
                data: [
                    <?= $summary['purchase_expenses'] ?>,
                    <?= $summary['salary_expenses'] ?>,
                    <?= $summary['studio_expenses'] ?>
                ],
                backgroundColor: ['#ffcb00', '#1fade4', '#aa00ff'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: textColor, font: { family: "'Rajdhani', sans-serif" } }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
