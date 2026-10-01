<?php

/**
 * DetalStudio - Task View
 * Перегляд задачі
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\CSRF;
use App\Models\Task;

Auth::requireAuth();

$taskId = $_GET['id'] ?? 0;
$task = Task::getById($taskId);

if (!$task) {
    http_response_code(404);
    include __DIR__ . '/../views/errors/404.php';
    exit;
}

// Check permissions
$isExecutor = in_array(Auth::getUserId(), $task['executor_ids'] ?? []) || ($task['executor_id'] == Auth::getUserId());
if (Auth::isExecutor() && !$isExecutor) {
    Auth::denyAccess();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::verify();

    switch ($_POST['action']) {
        case 'update_status':
            $validStatuses = ['created', 'in_progress', 'completed', 'closed', 'cancelled'];
            if (!in_array($_POST['status'], $validStatuses, true)) {
                \App\Flash::set('error', 'Невірний статус');
                redirect("/tasks/view.php?id=$taskId");
            }
            Task::update($taskId, ['status' => $_POST['status']]);
            $_SESSION['success'] = 'Статус задачі оновлено';
            redirect("/tasks/view.php?id=$taskId");
            break;

        case 'update_executors':
            if (!Auth::hasAnyRole(['manager', 'director'])) {
                \App\Flash::set('error', 'У вас немає прав для зміни виконавців');
                redirect("/tasks/view.php?id=$taskId");
            }
            Task::update($taskId, ['executors' => $_POST['executors'] ?? []]);
            $_SESSION['success'] = 'Виконавців задачі оновлено';
            redirect("/tasks/view.php?id=$taskId");
            break;

        case 'update_deadline':
            if (!Auth::hasAnyRole(['manager', 'director'])) {
                \App\Flash::set('error', 'У вас немає прав для зміни дедлайну');
                redirect("/tasks/view.php?id=$taskId");
            }
            Task::update($taskId, ['deadline' => $_POST['deadline'] ?: null]);
            $_SESSION['success'] = 'Дедлайн задачі оновлено';
            redirect("/tasks/view.php?id=$taskId");
            break;

        case 'update_commission':
            if (!Auth::hasAnyRole(['manager', 'director'])) {
                \App\Flash::set('error', 'У вас немає прав');
                redirect("/tasks/view.php?id=$taskId");
            }
            Task::update($taskId, ['commission_amount' => !empty($_POST['commission_amount']) ? floatval($_POST['commission_amount']) : 0.00]);
            $_SESSION['success'] = 'Суму виплати оновлено';
            redirect("/tasks/view.php?id=$taskId");
            break;

        case 'soft_delete':
            if (Auth::hasAnyRole(['manager', 'director'])) {
                Task::softDelete($taskId);
                $_SESSION['success'] = 'Задачу видалено';
                redirect("/tasks/");
            }
            break;

        case 'restore':
            if (Auth::hasAnyRole(['manager', 'director'])) {
                Task::restore($taskId);
                $_SESSION['success'] = 'Задачу відновлено';
                redirect("/tasks/view.php?id=$taskId");
            }
            break;

        case 'permanent_delete':
            if (Auth::hasAnyRole(['manager', 'director'])) {
                Task::hardDelete($taskId);
                $_SESSION['success'] = 'Задачу остаточно видалено';
                redirect("/tasks/?tab=deleted");
            }
            break;

        case 'add_comment':
            $commentId = Task::addComment($taskId, $_POST['comment']);
            if ($commentId) {
                $_SESSION['success'] = 'Коментар додано';
                redirect("/tasks/view.php?id=$taskId#comments");
            }
            break;
    }
}

// Get task details
$comments = Task::getComments($taskId);
$creator = Auth::getUserById($task['creator_id']);
// $executor = $task['executor_id'] ? Auth::getUserById($task['executor_id']) : null; // No longer just one

// Get available executors for the assignment dropdown
$allUsers = Auth::getAllUsers();
$executors = array_filter($allUsers, function ($user) {
    return $user['role'] === 'executor' || $user['id'] == Auth::getUserId(); // Managers/Directors can also be executors if needed, or at least they can assign. Actually, we filter by role 'executor' in index.php, so let's stick to that for consistency but add director/manager in case they want to assign to themselves.
});
// Redefining $executors to just role 'executor' like in index.php for consistency
$executors = array_filter($allUsers, function ($user) {
    return in_array($user['role'], ['executor', 'manager', 'director']);
});

$pageTitle = 'Задача: ' . $task['title'];
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Task Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list-check me-2"></i><?= htmlspecialchars($task['title']) ?>
                    <span class="badge <?= get_status_badge_class($task['status']) ?> ms-2">
                        <?= Task::getStatusName($task['status']) ?>
                    </span>
                    <span class="badge <?= get_priority_badge_class($task['priority']) ?> ms-1">
                        <?= ucfirst($task['priority']) ?>
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($task['description']): ?>
                    <div class="mb-3">
                        <h6>Опис</h6>
                        <p><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Створив:</strong></td>
                                <td><?= htmlspecialchars($creator['full_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Виконавці:</strong></td>
                                <td><?= !empty($task['executor_names']) ? htmlspecialchars($task['executor_names']) : '<span class="text-muted">Не призначено</span>' ?></td>
                            </tr>
                            <?php if ($task['deadline']): ?>
                                <tr>
                                    <td><strong>Дедлайн:</strong></td>
                                    <td class="<?= $task['deadline'] < date('Y-m-d H:i:s') ? 'text-danger' : '' ?>">
                                        <?= format_date($task['deadline']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!$task['order_id'] && $task['profit_amount'] > 0): ?>
                                <tr>
                                    <td><strong class="text-success"><i class="bi bi-cash-coin me-1"></i>Прибуток:</strong></td>
                                    <td class="text-success fw-bold"><?= format_currency($task['profit_amount']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Створено:</strong></td>
                                <td><?= format_date($task['created_at']) ?></td>
                            </tr>
                            <?php if (!empty($task['started_at'])): ?>
                                <tr>
                                    <td><strong>Почато:</strong></td>
                                    <td><?= format_date($task['started_at']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($task['completed_at']): ?>
                                <tr>
                                    <td><strong>Завершено:</strong></td>
                                    <td><?= format_date($task['completed_at']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <?php if ($task['order_id']): ?>
                    <div class="mt-3">
                        <h6>Замовлення</h6>
                        <a href="/orders/view.php?id=<?= $task['order_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-receipt me-1"></i>Переглянути замовлення
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments -->
        <div class="card mb-4" id="comments">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-chat-dots me-2"></i>КОМЕНТАРІ
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="add_comment">
                    <div class="input-group">
                        <input type="text" name="comment" class="form-control" placeholder="Додати коментар..." required>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </form>

                <?php if (!empty($comments)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($comments as $comment): ?>
                            <div class="list-group-item border-0 mb-2 rounded shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-accent-cyan small"><?= htmlspecialchars($comment['user_name']) ?></strong>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= format_date($comment['created_at']) ?></small>
                                </div>
                                <div class="comment-text">
                                    <p class="mb-0 text-light"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <small>Коментарів ще немає</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-gear me-2"></i>Дії
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2 mb-3">
                    <?php if ($task['status'] === 'created'): ?>
                        <button class="btn btn-success" onclick="quickUpdateStatus('in_progress')">
                            <i class="bi bi-play-fill me-2"></i>Почати роботу
                        </button>
                    <?php endif; ?>

                    <?php if ($task['status'] !== 'completed' && $task['status'] !== 'closed' && $task['status'] !== 'cancelled'): ?>
                        <button class="btn btn-outline-success" onclick="quickUpdateStatus('completed')">
                            <i class="bi bi-check-lg me-2"></i>Завершити
                        </button>
                    <?php endif; ?>

                </div>

                <form method="POST" id="statusForm">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="update_status">
                    <label class="form-label">Змінити статус</label>
                    <select name="status" id="statusSelect" class="form-select mb-2">
                        <option value="created" <?= $task['status'] == 'created' ? 'selected' : '' ?>>Створено</option>
                        <option value="in_progress" <?= $task['status'] == 'in_progress' ? 'selected' : '' ?>>В роботі</option>
                        <option value="completed" <?= $task['status'] == 'completed' ? 'selected' : '' ?>>Готово</option>
                        <option value="closed" <?= $task['status'] == 'closed' ? 'selected' : '' ?>>Закрито</option>
                        <option value="cancelled" <?= $task['status'] == 'cancelled' ? 'selected' : '' ?>>Скасовано</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-check-circle me-1"></i>Оновити статус
                    </button>
                </form>

                <script>
                    function quickUpdateStatus(status) {
                        const select = document.getElementById('statusSelect');
                        const form = document.getElementById('statusForm');
                        if (select && form) {
                            select.value = status;
                            form.submit();
                        }
                    }
                </script>

                <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                <hr class="my-3">

                <form method="POST">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="update_executors">
                    <label class="form-label">Призначити виконавців</label>
                    <select name="executors[]" id="updateTaskExecutors" class="form-select mb-2" multiple>
                        <?php foreach ($executors as $ex): ?>
                            <option value="<?= $ex['id'] ?>" <?= in_array($ex['id'], $task['executor_ids'] ?? []) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-person-check me-1"></i>Оновити виконавців
                    </button>
                </form>

                <hr class="my-3">

                <form method="POST">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="update_deadline">
                    <label class="form-label">Змінити дедлайн</label>
                    <input type="text" name="deadline" class="form-control form-control-sm date-picker mb-2" value="<?= $task['deadline'] ? date('Y-m-d', strtotime($task['deadline'])) : '' ?>" placeholder="Оберіть дату...">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-calendar-event me-1"></i>Оновити дедлайн
                    </button>
                </form>

                <?php if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1'): ?>
                <hr class="my-3">
                <form method="POST">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="update_commission">
                    <label class="form-label"><i class="bi bi-cash-coin me-1"></i>Змінити виплату (₴)</label>
                    <input type="number" step="0.01" min="0" name="commission_amount" class="form-control form-control-sm mb-2" value="<?= $task['commission_amount'] > 0 ? floatval($task['commission_amount']) : '' ?>" placeholder="Автоматично">
                    <button type="submit" class="btn btn-outline-success btn-sm w-100">
                        Оновити виплату
                    </button>
                </form>
                <?php endif; ?>

                <?php endif; ?>

                <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                    <hr class="my-3">
                    <?php if (!empty($task['is_deleted'])): ?>
                        <form method="POST" class="mb-2">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="restore">
                            <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Відновити задачу
                            </button>
                        </form>
                        <form id="permanentDeleteForm" method="POST">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="permanent_delete">
                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="confirmDelete('Ви впевнені? Задачу буде видалено НАЗАВЖДИ разом з усіма коментарями та вкладеннями!', () => document.getElementById('permanentDeleteForm').submit())">
                                <i class="bi bi-trash-fill me-1"></i>Видалити остаточно
                            </button>
                        </form>
                    <?php else: ?>
                        <form id="deleteTaskForm" method="POST">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="soft_delete">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="confirmDelete('Ви впевнені, що хочете видалити цю задачу?', () => document.getElementById('deleteTaskForm').submit())">
                                <i class="bi bi-trash me-1"></i>Видалити задачу
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Task Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-info-circle me-2"></i>Інформація
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>ID:</strong></td>
                        <td>#<?= $task['id'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Пріоритет:</strong></td>
                        <td>
                            <span class="badge <?= get_priority_badge_class($task['priority']) ?>">
                                <?= ucfirst($task['priority']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($task['deadline']): ?>
                        <tr>
                            <td><strong>Дедлайн:</strong></td>
                            <td class="<?= $task['deadline'] < date('Y-m-d H:i:s') ? 'text-danger' : '' ?>">
                                <?= format_date($task['deadline']) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1' && $task['commission_amount'] > 0): ?>
                        <tr>
                            <td><strong>Виплата:</strong></td>
                            <td class="text-accent-cyan fw-bold">
                                <?= format_currency($task['commission_amount']) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        new TomSelect("#updateTaskExecutors", {
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
    });
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>