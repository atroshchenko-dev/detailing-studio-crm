<?php
/**
 * Executor Statistics Report
 * Детальна статистика виконавців
 */
require_once __DIR__ . '/../../config/config.php';

use App\Auth;
use App\Reports;

Auth::requireRole('director');

$pageTitle = 'Статистика виконавців';

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Get executor stats
$executors = Reports::getTasksByExecutor($dateFrom, $dateTo);

// Calculate totals
$totalTasks = array_sum(array_column($executors, 'total_tasks'));
$totalCompleted = array_sum(array_column($executors, 'completed_tasks'));
$totalOverdue = array_sum(array_column($executors, 'overdue_tasks'));
$avgTime = count($executors) > 0 ? array_sum(array_column($executors, 'avg_completion_time')) / count($executors) : 0;

include __DIR__ . '/../layout/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 theme-text">📊 Статистика виконавців</h1>
        
        <!-- Date Picker -->
        <form class="d-flex gap-2" method="GET">
            <input type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control form-control-sm theme-input">
            <span class="align-self-center theme-text-muted">—</span>
            <input type="date" name="date_to" value="<?= $dateTo ?>" class="form-control form-control-sm theme-input">
            <button type="submit" class="btn btn-sm btn-primary">Оновити</button>
        </form>
    </div>

    <?php include __DIR__ . '/nav.php'; ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-info"><?= $totalTasks ?></div>
                    <div class="text-muted small">Всього задач</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-success"><?= $totalCompleted ?></div>
                    <div class="text-muted small">Виконано</div>
                    <?php if ($totalTasks > 0): ?>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: <?= round($totalCompleted / $totalTasks * 100) ?>%"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-danger"><?= $totalOverdue ?></div>
                    <div class="text-muted small">Прострочено</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-warning"><?= round($avgTime, 1) ?>h</div>
                    <div class="text-muted small">Середній час виконання</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Chart: Tasks by Executor -->
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary">
                <div class="card-header border-secondary">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Задачі по виконавцях</h5>
                </div>
                <div class="card-body">
                    <canvas id="executorTasksChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Chart: Completion Rate -->
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary">
                <div class="card-header border-secondary">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Швидкість виконання (год)</h5>
                </div>
                <div class="card-body">
                    <canvas id="avgTimeChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="col-12">
            <div class="card bg-dark border-secondary">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Детальна таблиця</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($executors)): ?>
                        <p class="text-muted text-center py-5">Немає даних за обраний період</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Виконавець</th>
                                        <th class="text-center">Всього</th>
                                        <th class="text-center text-success">Виконано</th>
                                        <th class="text-center text-warning">В роботі</th>
                                        <th class="text-center text-danger">Прострочено</th>
                                        <th class="text-center">% Виконання</th>
                                        <th class="text-center">Сер. час (год)</th>
                                        <th class="text-center">Продуктивність</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($executors as $e): 
                                        $completionRate = $e['total_tasks'] > 0 ? round($e['completed_tasks'] / $e['total_tasks'] * 100) : 0;
                                        $productivity = 'low';
                                        if ($completionRate >= 80 && $e['overdue_tasks'] == 0) $productivity = 'high';
                                        elseif ($completionRate >= 50) $productivity = 'medium';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                    <i class="bi bi-person-fill"></i>
                                                </div>
                                                <div class="fw-medium"><?= htmlspecialchars($e['full_name']) ?></div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= $e['total_tasks'] ?></td>
                                        <td class="text-center text-success fw-bold"><?= $e['completed_tasks'] ?></td>
                                        <td class="text-center text-warning"><?= $e['in_progress_tasks'] ?></td>
                                        <td class="text-center text-danger"><?= $e['overdue_tasks'] ?></td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <div class="progress" style="width: 60px; height: 6px;">
                                                    <div class="progress-bar <?= $completionRate >= 80 ? 'bg-success' : ($completionRate >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                                         style="width: <?= $completionRate ?>%"></div>
                                                </div>
                                                <span class="small"><?= $completionRate ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= $e['avg_completion_time'] ? round($e['avg_completion_time'], 1) : '—' ?></td>
                                        <td class="text-center">
                                            <?php if ($productivity === 'high'): ?>
                                                <span class="badge bg-success">🚀 Високa</span>
                                            <?php elseif ($productivity === 'medium'): ?>
                                                <span class="badge bg-warning text-dark">⚡ Середня</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">📉 Низька</span>
                                            <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    const executors = <?= json_encode($executors) ?>;
    
    if (executors.length === 0) return;
    
    const names = executors.map(e => e.full_name);
    const completed = executors.map(e => parseInt(e.completed_tasks) || 0);
    const inProgress = executors.map(e => parseInt(e.in_progress_tasks) || 0);
    const overdue = executors.map(e => parseInt(e.overdue_tasks) || 0);
    const avgTimes = executors.map(e => parseFloat(e.avg_completion_time) || 0);
    
    // Bar Chart - Tasks by Executor
    new Chart(document.getElementById('executorTasksChart'), {
        type: 'bar',
        data: {
            labels: names,
            datasets: [
                {
                    label: 'Виконано',
                    data: completed,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)', // green
                    borderRadius: 4
                },
                {
                    label: 'В роботі',
                    data: inProgress,
                    backgroundColor: 'rgba(245, 158, 11, 0.8)', // yellow
                    borderRadius: 4
                },
                {
                    label: 'Прострочено',
                    data: overdue,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)', // red
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: '#9ca3af' }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: { color: '#9ca3af' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    stacked: true,
                    ticks: { color: '#9ca3af' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                }
            }
        }
    });
    
    // Horizontal Bar Chart - Avg Completion Time
    new Chart(document.getElementById('avgTimeChart'), {
        type: 'bar',
        data: {
            labels: names,
            datasets: [{
                label: 'Годин на задачу',
                data: avgTimes,
                backgroundColor: executors.map((e, i) => {
                    const time = parseFloat(e.avg_completion_time) || 0;
                    if (time < 4) return 'rgba(16, 185, 129, 0.8)';
                    if (time < 8) return 'rgba(245, 158, 11, 0.8)';
                    return 'rgba(239, 68, 68, 0.8)';
                }),
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    ticks: { color: '#9ca3af' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    ticks: { color: '#9ca3af' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
