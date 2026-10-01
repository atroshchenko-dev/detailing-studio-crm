<?php

/**
 * DetalStudio - Tasks Kanban Board
 * Канбан-дошка задач
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\CSRF;
use App\Validator;
use App\Flash;
use App\Models\Task;
use App\Models\Order;

Auth::requireAuth();

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::verify();

    if ($_POST['action'] == 'create') {
        $validator = new Validator($_POST);
        $validator->required('title', 'Назва задачі')
            ->required('priority', 'Пріоритет');

        if ($validator->isValid()) {
            $taskId = Task::create([
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description'] ?? ''),
                'order_id' => $_POST['order_id'] ?? null,
                'executors' => $_POST['executors'] ?? [],
                'priority' => $_POST['priority'] ?? 'medium',
                'deadline' => $_POST['deadline'] ?? null,
                'commission_amount' => !empty($_POST['commission_amount']) ? floatval($_POST['commission_amount']) : 0.00
            ]);

            if ($taskId) {
                $_SESSION['success'] = 'Задачу створено успішно';
                redirect("/tasks/view.php?id=$taskId");
            } else {
                Flash::error('Помилка при створенні задачі');
            }
        } else {
            Flash::error('Будь ласка, виправте помилки');
            foreach ($validator->getErrors() as $error) {
                Flash::error($error);
            }
        }
    } elseif ($_POST['action'] == 'restore' && Auth::hasAnyRole(['manager', 'director'])) {
        $taskId = $_POST['task_id'] ?? 0;
        if ($taskId && Task::restore($taskId)) {
            $_SESSION['success'] = 'Задачу відновлено';
        }
        redirect('/tasks/?tab=deleted');
    } elseif ($_POST['action'] == 'permanent_delete' && Auth::hasAnyRole(['manager', 'director'])) {
        $taskId = $_POST['task_id'] ?? 0;
        if ($taskId && Task::hardDelete($taskId)) {
            $_SESSION['success'] = 'Задачу остаточно видалено';
        }
        redirect('/tasks/?tab=deleted');
    }
}

// Get view filters
$viewType = $_GET['view'] ?? 'kanban';
$tab = $_GET['tab'] ?? 'active';

$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['executor_id'])) $filters['executor_id'] = $_GET['executor_id'];
if (!empty($_GET['order_id'])) $filters['order_id'] = $_GET['order_id'];

// Apply tab filters
if ($tab === 'active') {
    // For list view, we filter out completed
    if ($viewType === 'list') {
        $filters['status'] = ['created', 'accepted', 'in_progress'];
    }
    // For Kanban, we handle it per column, but maybe we want to limit 'completed' column?
} elseif ($tab === 'history') {
    $filters['status'] = ['completed', 'cancelled'];
} elseif ($tab === 'deleted') {
    $filters['only_deleted'] = true;
} elseif ($tab === 'all') {
    // Show all statuses, no additional status filter needed
}

// Get tasks by status for Kanban
$statuses = ['created', 'in_progress', 'completed', 'closed', 'cancelled'];
$tasksByStatus = [];
$includeDeleted = ($tab === 'all');
$onlyDeleted = ($tab === 'deleted');
foreach ($statuses as $status) {
    if ($tab === 'active' && $status === 'completed') {
        // For active view, show only recent completed tasks (last 24h) to keep board clean
        $tasks = Task::getByStatus($status);
        $tasksByStatus[$status] = array_filter($tasks, function ($t) {
            if (empty($t['completed_at'])) return true;
            return strtotime($t['completed_at']) > strtotime('-2 days');
        });
    } elseif ($tab === 'history') {
        // In history tab, show closed and cancelled
        if (in_array($status, ['closed', 'cancelled'])) {
            $tasksByStatus[$status] = Task::getByStatus($status);
        } else {
            $tasksByStatus[$status] = [];
        }
    } elseif ($tab === 'deleted') {
        // Show only deleted tasks
        $tasksByStatus[$status] = Task::getByStatus($status, false, true);
    } elseif ($tab === 'all') {
        // Show all including deleted
        $tasksByStatus[$status] = Task::getByStatus($status, true);
    } else {
        // Active tab, other statuses
        $tasksByStatus[$status] = Task::getByStatus($status);
    }
}

// Get all tasks for list view
$allTasks = Task::getList($filters);

// Get executors and orders for filters
$executors = Auth::getAllUsers();
$executors = array_filter($executors, function ($user) {
    return in_array($user['role'], ['executor', 'manager', 'director']);
});

// Get active orders for linked tasks
$activeOrders = Order::getList(['status' => ['new', 'approved', 'in_progress', 'waiting_client']]);

$pageTitle = 'Задачі';
$additionalScripts = '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-actions">
                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-group" role="group">
                            <a href="?view=kanban&tab=<?= $tab ?>" class="btn btn-sm <?= $viewType == 'kanban' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="bi bi-kanban"></i> Kanban
                            </a>
                            <a href="?view=list&tab=<?= $tab ?>" class="btn btn-sm <?= $viewType == 'list' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="bi bi-list"></i> Список
                            </a>
                        </div>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="bi bi-plus-circle me-1"></i>Додати задачу
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'active' ? 'active' : '' ?>" href="?view=<?= $viewType ?>&tab=active">
                            <i class="bi bi-play-circle me-1"></i>Активні
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'history' ? 'active' : '' ?>" href="?view=<?= $viewType ?>&tab=history">
                            <i class="bi bi-archive me-1"></i>Історія
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>" href="?view=<?= $viewType ?>&tab=all">
                            <i class="bi bi-list me-1"></i>Всі
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'deleted' ? 'active' : '' ?>" href="?view=<?= $viewType ?>&tab=deleted">
                            <i class="bi bi-trash me-1"></i>Видалені
                        </a>
                    </li>
                </ul>

                <?php if ($viewType == 'kanban'): ?>
                    <!-- Kanban Board -->
                    <div class="kanban-board">
                        <?php
                        $statusNames = [
                            'created' => 'Створено',
                            'in_progress' => 'В роботі',
                            'completed' => 'Готово',
                            'closed' => 'Закрито',
                            'cancelled' => 'Скасовано'
                        ];

                        // Define statuses to show based on tab
                        if ($tab === 'history') {
                            // Show closed and cancelled in history
                            $kanbanStatuses = ['closed', 'cancelled'];
                        } elseif ($tab === 'all' || $tab === 'deleted') {
                            // Show all status columns
                            $kanbanStatuses = ['created', 'in_progress', 'completed', 'closed', 'cancelled'];
                        } else {
                            // Show active phases in active tab
                            $kanbanStatuses = ['created', 'in_progress', 'completed'];
                        }

                        foreach ($kanbanStatuses as $status):
                            // Skip if column data empty (safety check, though we populate it)
                            if (!isset($tasksByStatus[$status])) continue;
                        ?>
                            <div class="kanban-column">
                                <h6 class="mb-3">
                                    <span class="badge <?= $status === 'cancelled' ? 'bg-danger' : get_status_badge_class($status) ?>"><?= $statusNames[$status] ?></span>
                                    <span class="badge bg-secondary ms-1"><?= count($tasksByStatus[$status]) ?></span>
                                </h6>
                                <div class="kanban-cards" data-status="<?= $status ?>">
                                    <?php foreach ($tasksByStatus[$status] as $task): ?>
                                        <div class="kanban-card" data-task-id="<?= $task['id'] ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0">
                                                    <a href="/tasks/view.php?id=<?= $task['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($task['title']) ?>
                                                    </a>
                                                </h6>
                                                <span class="badge <?= get_priority_badge_class($task['priority']) ?> ms-2">
                                                    <?= ucfirst($task['priority']) ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($task['description'])): ?>
                                                <p class="text-muted small mb-2"><?= nl2br(htmlspecialchars(substr($task['description'], 0, 100))) ?><?= strlen($task['description']) > 100 ? '...' : '' ?></p>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center text-muted small">
                                                <span>
                                                    <?php if (!empty($task['executor_names'])): ?>
                                                        <i class="bi bi-person"></i> <?= htmlspecialchars($task['executor_names']) ?>
                                                    <?php else: ?>
                                                        <i class="bi bi-person"></i> Не призначено
                                                    <?php endif; ?>
                                                </span>
                                                <?php if (!empty($task['deadline'])): ?>
                                                    <span class="<?= strtotime($task['deadline']) < time() ? 'text-danger' : '' ?>">
                                                        <i class="bi bi-clock"></i> <?= date('d.m.Y H:i', strtotime($task['deadline'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($task['order_number'])): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="bi bi-receipt"></i>
                                                        <a href="/orders/view.php?id=<?= $task['order_id'] ?? '#' ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($task['order_number']) ?>
                                                        </a>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- List View -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Назва</th>
                                    <th>Пріоритет</th>
                                    <th>Статус</th>
                                    <th>Виконавець</th>
                                    <th>Дедлайн</th>
                                    <th>Замовлення</th>
                                    <th>Дія</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($task['title']) ?></strong>
                                            <?php if (!empty($task['description'])): ?>
                                                <br><small class="text-muted"><?= nl2br(htmlspecialchars(substr($task['description'], 0, 50))) ?><?= strlen($task['description']) > 50 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= get_priority_badge_class($task['priority'] ?? 'medium') ?>">
                                                <?= ucfirst($task['priority'] ?? 'medium') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= get_status_badge_class($task['status']) ?>">
                                                <?= Task::getStatusName($task['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= !empty($task['executor_names']) ? htmlspecialchars($task['executor_names']) : '<span class="text-muted">Не призначено</span>' ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($task['deadline'])): ?>
                                                <span class="<?= strtotime($task['deadline']) < time() ? 'text-danger' : '' ?>">
                                                    <?= format_date($task['deadline']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($task['order_id'])): ?>
                                                <a href="/orders/view.php?id=<?= $task['order_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($task['order_number'] ?? '#') ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="/tasks/view.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($tab === 'deleted' && Auth::hasAnyRole(['manager', 'director'])): ?>
                                                    <form method="POST" class="d-inline">
                                                        <?= CSRF::input() ?>
                                                        <input type="hidden" name="action" value="restore">
                                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Відновити">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Видалити задачу НАЗАВЖДИ?')">
                                                        <?= CSRF::input() ?>
                                                        <input type="hidden" name="action" value="permanent_delete">
                                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Видалити остаточно">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif (!in_array($task['status'], ['completed', 'closed', 'cancelled'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="updateTaskStatus(<?= $task['id'] ?>, 'completed')" title="Завершити">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Нова задача</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Назва *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Опис</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Виконавці</label>
                                <select name="executors[]" id="createTaskExecutors" class="form-select" multiple>
                                    <?php foreach ($executors as $executor): ?>
                                        <option value="<?= $executor['id'] ?>"><?= htmlspecialchars($executor['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Пріоритет</label>
                                <select name="priority" class="form-select">
                                    <option value="low">Низький</option>
                                    <option value="medium" selected>Середній</option>
                                    <option value="high">Високий</option>
                                    <option value="urgent">Терміновий</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-accent-cyan">Дата виконання</label>
                                <input type="text" name="deadline" class="form-control date-picker" placeholder="Оберіть дату...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Замовлення</label>
                                <select name="order_id" class="form-select">
                                    <option value="">Не прив'язано</option>
                                    <?php foreach ($activeOrders as $ord): ?>
                                        <option value="<?= $ord['id'] ?>">
                                            <?= $ord['order_number'] ?> - <?= htmlspecialchars($ord['client_name']) ?> (<?= htmlspecialchars($ord['make']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1'): ?>
                    <div class="mb-3">
                        <label class="form-label text-accent-cyan">Виплата виконавцю (₴)</label>
                        <input type="number" name="commission_amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        <div class="form-text text-muted">Залиште порожнім, якщо сума обчислюється автоматично по факту.</div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Створити задачу
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateTaskStatus(taskId, status) {
        if (!confirm('Ви впевнені, що хочете змінити статус задачі?')) return;

        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('status', status);

        fetch('/api/update_task_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Помилка: ' + (data.error || 'Невідома помилка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Помилка з\'єднання');
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Tom Select
        new TomSelect("#createTaskExecutors", {
            plugins: ['remove_button'],
            maxItems: null,
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: 'Оберіть виконавців...',
            render: {
                item: function(data, escape) {
                    return '<div class="item">' + escape(data.text) + '</div>';
                }
            }
        });

        <?php if ($viewType == 'kanban'): ?>
            // Initialize drag and drop for kanban
            document.querySelectorAll('.kanban-cards').forEach(function(column) {
                new Sortable(column, {
                    group: 'kanban',
                    animation: 150,
                    ghostClass: 'kanban-ghost',
                    onEnd: function(evt) {
                        const taskId = evt.item.dataset.taskId;
                        const newStatus = evt.to.dataset.status;

                        // Update task status via AJAX
                        fetch('/api/update_task_status.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'task_id=' + taskId + '&status=' + newStatus
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Update status badge
                                    location.reload();
                                }
                            });
                    }
                });
            });
        <?php endif; ?>
    });
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>