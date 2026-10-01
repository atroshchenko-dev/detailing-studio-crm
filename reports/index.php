<?php
/**
 * DetalStudio - Reports
 * Звіти та аналітика
 */

require_once __DIR__ . '/../config/config.php';

use App\Reports;
use App\Auth;

Auth::requireAuth();
Auth::requireRole('director');

$pageTitle = 'Звіти та аналітика';

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

$summary = Reports::getFinancialSummary($dateFrom, $dateTo);
$monthlyData = Reports::getMonthlyFinancials(6);
$topServices = Reports::getTopServices(5, $dateFrom, $dateTo);

$managerPerformance = Reports::getManagerPerformance($dateFrom, $dateTo);
$clientRetention = Reports::getClientRetention($dateFrom, $dateTo);

include __DIR__ . '/../views/layout/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card theme-card border-secondary">
            <div class="card-header">
                <div class="card-header-actions">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up-arrow me-1"></i> Аналітика
                    </div>
                    <form method="GET" class="d-inline-flex gap-2">
                        <input type="date" name="date_from" class="form-control form-control-sm theme-input" value="<?= $dateFrom ?>">
                        <input type="date" name="date_to" class="form-control form-control-sm theme-input" value="<?= $dateTo ?>">
                        <button type="submit" class="btn btn-sm btn-info text-white">Оновити</button>
                        
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-download me-1"></i> Експорт
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end theme-dropdown" aria-labelledby="exportDropdown">
                                <li><a class="dropdown-item theme-dropdown-item" href="/admin/export_reports.php?type=financial&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">Фінанси (CSV)</a></li>
                                <li><a class="dropdown-item theme-dropdown-item" href="/admin/export_reports.php?type=orders&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">Замовлення (CSV)</a></li>
                                <li><a class="dropdown-item theme-dropdown-item" href="/admin/export_reports.php?type=clients&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">Топ Клієнтів (CSV)</a></li>
                                <li><a class="dropdown-item theme-dropdown-item" href="/admin/export_reports.php?type=services&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">Топ Послуг (CSV)</a></li>
                            </ul>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <!-- KPI Cards inside main card or as a row -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-lg">
                        <div class="card bg-dark text-white border-info border-start border-4">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase tiny-font">Загальний дохід</h6>
                                <h4 class="mb-0 text-info"><?= format_currency($summary['revenue']) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg">
                        <div class="card bg-dark text-white border-danger border-start border-4">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase tiny-font">Витрати закупівлі</h6>
                                <h4 class="mb-0 text-danger"><?= format_currency($summary['purchase_expenses']) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg">
                        <div class="card bg-dark text-white border-warning border-start border-4">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase tiny-font">Витрати студії</h6>
                                <h4 class="mb-0 text-warning"><?= format_currency($summary['general_expenses']) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg">
                        <div class="card bg-dark text-white border-success border-start border-4">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase tiny-font">Чистий прибуток</h6>
                                <h3 class="mb-0 text-success"><?= format_currency($summary['net_profit']) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Financial Charts -->
                    <div class="col-lg-8">
                        <div class="card theme-card h-100">
                            <div class="card-header py-2">Доходи та витрати</div>
                            <div class="card-body">
                                <canvas id="financialChart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Services -->
                    <div class="col-lg-4">
                        <div class="card theme-card h-100">
                            <div class="card-header py-2">Топ послуг за доходом</div>
                            <div class="card-body">
                                <canvas id="servicesChart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-4">
                     <!-- Manager Performance -->
                    <div class="col-md-6">
                        <div class="card theme-card">
                            <div class="card-header py-2 text-info">Ефективність менеджерів</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Менеджер</th>
                                                <th>Замовлень</th>
                                                <th>Виручка</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($managerPerformance as $manager): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($manager['username']) ?></td>
                                                    <td><?= $manager['order_count'] ?></td>
                                                    <td class="fw-bold"><?= format_currency($manager['total_revenue']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Client Retention -->
                    <div class="col-md-6">
                        <div class="card theme-card">
                            <div class="card-header py-2 text-accent-cyan">Лояльність клієнтів (Повторні візити)</div>
                            <div class="card-body text-center py-4">
                                <div class="row">
                                    <div class="col-6 border-end border-secondary">
                                        <h2 class="text-info"><?= $clientRetention['returning_clients'] ?></h2>
                                        <p class="text-muted small mb-0">Постійних клієнтів</p>
                                    </div>
                                    <div class="col-6">
                                        <h2 class="text-accent-orange"><?= $clientRetention['retention_rate'] ?>%</h2>
                                        <p class="text-muted small mb-0">Коефіцієнт повернення</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Colors from theme
    const accentCyan = '#1fade4';
    const accentOrange = '#ffcb00';
    const accentRed = '#ff6b6b';
    const textMain = getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim() || '#f2f2f2';

    // Financial Chart
    const financialCtx = document.getElementById('financialChart').getContext('2d');
    new Chart(financialCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($monthlyData, 'month')) ?>,
            datasets: [
                {
                    label: 'Дохід',
                    data: <?= json_encode(array_column($monthlyData, 'revenue')) ?>,
                    borderColor: accentCyan,
                    backgroundColor: 'rgba(31, 190, 228, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Витрати',
                    data: <?= json_encode(array_column($monthlyData, 'expenses')) ?>,
                    borderColor: accentRed,
                    backgroundColor: 'rgba(255, 107, 107, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textMain } }
            },
            scales: {
                y: { 
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: textMain }
                },
                x: { 
                    grid: { display: false },
                    ticks: { color: textMain }
                }
            }
        }
    });

    // Services Chart
    const servicesCtx = document.getElementById('servicesChart').getContext('2d');
    new Chart(servicesCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($topServices, 'name')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($topServices, 'total_revenue')) ?>,
                backgroundColor: [
                    accentCyan,
                    accentOrange,
                    '#38ef7d',
                    '#9f7aea',
                    '#ed64a6'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { color: textMain, padding: 20 }
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<style>
.tiny-font { font-size: 0.65rem; }
</style>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>