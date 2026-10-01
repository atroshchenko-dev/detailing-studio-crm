<?php

/**
 * DetalStudio - Order View
 * Перегляд замовлення
 */

require_once __DIR__ . '/../config/config.php';

use App\Controllers\OrderController;
use App\Auth;
use App\CSRF;
use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use App\Models\WarehouseItem;
use App\GoogleIntegration;

Auth::requireAuth();

// Instantiate Controller
$controller = new OrderController();
$orderId = $_GET['id'] ?? 0;
$data = $controller->view($orderId);

if (!$data) {
    http_response_code(404);
    include __DIR__ . '/../views/errors/404.php';
    exit;
}

// Extract all data variables
// Expected: order, orderItems, payments, comments, relatedTasks, client, car, debt
extract($data);

// Page Title
$pageTitle = 'Замовлення ' . $order['order_number'];
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <?php if (isset($order['is_deleted']) && $order['is_deleted']): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-trash-fill fs-4 me-3"></i>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">Це замовлення ВИДАЛЕНО</h5>
                    <p class="mb-0">Замовлення було видалено з основного списку. Ви бачите цю сторінку, бо перейшли за прямим посиланням.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <!-- Order Info Card -->
        <?php
        $totalTasks = count($relatedTasks);
        include __DIR__ . '/../views/components/order_info.php';
        ?>

        <!-- Order Items -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-tools me-2"></i>Послуги
                </h5>
                <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                    <a href="/orders/edit.php?id=<?= $orderId ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-pencil me-1"></i>Редагувати
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Послуга</th>
                                <th>Категорія</th>
                                <th>Кількість</th>
                                <th>Ціна</th>
                                <th>Сума</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['service_name']) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($item['service_category'] ?? '') ?></small></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= format_currency($item['unit_price']) ?></td>
                                    <td><?= format_currency($item['total_price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Разом:</th>
                                <th><?= format_currency($order['total_amount']) ?></th>
                            </tr>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <tr>
                                    <th colspan="4" class="text-end">Знижка:</th>
                                    <th class="text-danger">-<?= format_currency($order['discount_amount']) ?></th>
                                </tr>
                                <tr>
                                    <th colspan="4" class="text-end">До сплати:</th>
                                    <th><?= format_currency($order['total_amount'] - $order['discount_amount']) ?></th>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>

                <!-- Resources / Materials Section -->
                <?php if (Auth::hasAnyRole(['manager', 'director']) && Setting::get('feature_warehouse', '1') == '1'): ?>
                    <div class="mt-4 border-top pt-4">
                        <h6>
                            <i class="bi bi-box-seam me-2 text-accent-cyan"></i>Матеріали та витратники
                        </h6>
                        <div class="row">
                            <?php
                            $processedAny = false;
                            foreach ($orderItems as $item):
                                $templates = Service::getResourceTemplates($item['service_id']);
                                if (empty($templates)) continue;
                                $processedAny = true;
                            ?>
                                <div class="col-12 mb-3">
                                    <div class="bg-dark p-2 rounded border border-secondary border-opacity-25">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small fw-bold">Для: <?= htmlspecialchars($item['service_name']) ?></span>
                                        </div>
                                        <?php foreach ($templates as $res): ?>
                                            <form method="POST" class="row g-2 align-items-center mb-1">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="process_resources">
                                                <input type="hidden" name="warehouse_item_id" value="<?= $res['warehouse_item_id'] ?>">
                                                <input type="hidden" name="order_item_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="notes" value="Списано по замовленню #<?= $order['order_number'] ?>">

                                                <div class="col-6">
                                                    <span class="small"><?= htmlspecialchars($res['item_name']) ?></span>
                                                </div>
                                                <div class="col-3">
                                                    <input type="number" step="0.001" name="quantity" class="form-control form-control-sm" value="<?= $res['quantity'] * $item['quantity'] ?>">
                                                </div>
                                                <div class="col-3 text-end">
                                                    <button type="submit" class="btn btn-xs btn-outline-info" title="Списати">
                                                        <i class="bi bi-dash-circle me-1"></i>Списати
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (!$processedAny): ?>
                                <div class="col-12">
                                    <small class="text-muted">Для послуг у цьому замовленні не задано шаблонів матеріалів.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Tasks -->
        <div class="card mb-4" id="tasks">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-check me-2"></i>Задачі
                </h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                    <i class="bi bi-plus-circle me-1"></i>Додати
                </button>
            </div>
            <div class="card-body">
                <?php if (!empty($relatedTasks)): ?>
                    <?php
                    $totalTasksCount = count($relatedTasks);
                    $completedTasksCount = count(array_filter($relatedTasks, fn($t) => $t['status'] === 'completed'));
                    $progressPercent = $totalTasksCount > 0 ? round(($completedTasksCount / $totalTasksCount) * 100) : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Прогрес виконання</span>
                            <span><?= $completedTasksCount ?> / <?= $totalTasksCount ?> (<?= $progressPercent ?>%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progressPercent ?>%"></div>
                        </div>
                    </div>

                    <div class="list-group list-group-flush">
                        <?php foreach ($relatedTasks as $task): ?>
                            <div class="list-group-item border-0 mb-2 rounded shadow-sm bg-dark border-secondary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="me-3 flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge <?= get_status_badge_class($task['status']) ?>">
                                                <?= \App\Models\Task::getStatusName($task['status']) ?>
                                            </span>
                                            <a href="/tasks/view.php?id=<?= $task['id'] ?>" class="fw-bold text-decoration-none text-white">
                                                <?= htmlspecialchars($task['title']) ?>
                                            </a>
                                            <span class="badge <?= get_priority_badge_class($task['priority']) ?> p-1" style="font-size: 0.6rem;">
                                                <?= strtoupper($task['priority'][0]) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($task['description'])): ?>
                                            <p class="mb-1 text-muted small"><?= nl2br(htmlspecialchars(substr($task['description'], 0, 100))) ?><?= strlen($task['description']) > 100 ? '...' : '' ?></p>
                                        <?php endif; ?>
                                        <div class="small text-muted">
                                            <i class="bi bi-person me-1"></i><?= !empty($task['executor_names']) ? htmlspecialchars($task['executor_names']) : 'Не призначено' ?>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($task['deadline']): ?>
                                            <span class="small <?= $task['deadline'] < date('Y-m-d H:i:s') && $task['status'] != 'completed' ? 'text-danger' : 'text-muted' ?> text-nowrap me-2">
                                                <i class="bi bi-calendar-event me-1"></i><?= format_date($task['deadline'], 'd.m') ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (Auth::hasAnyRole(['manager', 'director', 'executor'])): ?>
                                            <?php if ($task['status'] === 'created'): ?>
                                                <button class="btn btn-sm btn-outline-info" onclick="updateTaskStatus(<?= $task['id'] ?>, 'in_progress')" title="Почати виконання">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled' && $task['status'] !== 'closed'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="updateTaskStatus(<?= $task['id'] ?>, 'completed')" title="Завершити">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <small>Задач по цьому замовленню немає</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payments -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-wallet2 me-2"></i>Платежі
                </h5>
                <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="bi bi-plus-circle me-1"></i>Додати платіж
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-dark-accent rounded">
                            <h4 class="text-primary"><?= format_currency($order['total_amount']) ?></h4>
                            <small class="text-muted">Загальна сума</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-dark-accent rounded">
                            <h4 class="text-success"><?= format_currency($order['paid_amount']) ?></h4>
                            <small class="text-muted">Оплачено</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-dark-accent rounded">
                            <h4 class="<?= $debt > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= format_currency($debt) ?>
                            </h4>
                            <small class="text-muted">Борг</small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($payments)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Сума</th>
                                    <th>Метод</th>
                                    <th>Примітки</th>
                                    <th>Користувач</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= format_date($payment['payment_date']) ?></td>
                                        <td><?= format_currency($payment['amount']) ?></td>
                                        <td>
                                            <?php
                                            $methods = ['cash' => 'Готівка', 'card' => 'Карта', 'fop' => 'ФОП'];
                                            echo $methods[$payment['payment_method']] ?? $payment['payment_method'];
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($payment['notes'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($payment['created_by_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <small>Платежів ще немає</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <?php if (Setting::get('feature_photo_gallery', '1') == '1'): ?>
            <!-- Photo Gallery -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-images me-2"></i>Фотогалерея
                    </h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                        <i class="bi bi-cloud-upload me-1"></i>Завантажити
                    </button>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="photoTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="before-tab" data-bs-toggle="tab" data-bs-target="#before" type="button">
                                До <span class="badge bg-secondary ms-1" id="beforeCount">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="after-tab" data-bs-toggle="tab" data-bs-target="#after" type="button">
                                Після <span class="badge bg-secondary ms-1" id="afterCount">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="process-tab" data-bs-toggle="tab" data-bs-target="#process" type="button">
                                Процес <span class="badge bg-secondary ms-1" id="processCount">0</span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="photoTabContent">
                        <div class="tab-pane fade show active" id="before" role="tabpanel">
                            <div class="row g-2" id="beforePhotos">
                                <div class="col-12 text-center text-muted py-3">Завантаження...</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="after" role="tabpanel">
                            <div class="row g-2" id="afterPhotos">
                                <div class="col-12 text-center text-muted py-3">Завантаження...</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="process" role="tabpanel">
                            <div class="row g-2" id="processPhotos">
                                <div class="col-12 text-center text-muted py-3">Завантаження...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>


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
                <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                    <!-- Status Update -->
                    <form method="POST" class="mb-3" id="updateStatusForm">
                        <?= CSRF::input() ?>
                        <input type="hidden" name="action" value="update_status">
                        <label class="form-label">Змінити статус</label>
                        <select name="status" id="orderStatusSelect" class="form-select mb-2">
                            <option value="new" <?= $order['status'] == 'new' ? 'selected' : '' ?>>Нові</option>
                            <option value="in_progress" <?= $order['status'] == 'in_progress' ? 'selected' : '' ?>>В роботі</option>
                            <option value="ready" <?= $order['status'] == 'ready' ? 'selected' : '' ?>>Готово</option>
                            <option value="closed" <?= $order['status'] == 'closed' ? 'selected' : '' ?>>Закрито</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-check-circle me-1"></i>Оновити статус
                        </button>
                    </form>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const form = document.getElementById('updateStatusForm');
                            const statusSelect = document.getElementById('orderStatusSelect');
                            const confirmBtn = document.getElementById('confirmCloseOrderBtn');
                            let modalInstance = null;

                            if (form) {
                                form.addEventListener('submit', function(e) {
                                    const status = statusSelect.value;
                                    const totalTasks = <?= count($relatedTasks) ?>;
                                    const incompleteTasks = <?= count(array_filter($relatedTasks, fn($t) => $t['status'] !== 'completed')) ?>;

                                    if (status === 'closed' && incompleteTasks > 0) {
                                        e.preventDefault();

                                        // Update modal text
                                        document.getElementById('incompleteTasksCount').textContent = incompleteTasks;

                                        // Show modal
                                        const modalEl = document.getElementById('confirmCloseOrderModal');
                                        modalInstance = new bootstrap.Modal(modalEl);
                                        modalInstance.show();
                                    }
                                });
                            }

                            // Handle Confirm Button Click
                            if (confirmBtn) {
                                confirmBtn.addEventListener('click', function() {
                                    if (form) {
                                        form.submit();
                                    }
                                    if (modalInstance) {
                                        modalInstance.hide();
                                    }
                                });
                            }
                        });
                    </script>
                <?php endif; ?>

                <!-- Google Drive Folder Integration Removed by User Request -->
                <?php /* if (GoogleIntegration::isConfigured()): ?>
                    <?php if ($order['google_folder_id']): ?>
                        <a href="<?= GoogleIntegration::getFolderUrl($order['google_folder_id']) ?>" target="_blank" class="btn btn-outline-info btn-sm w-100 mb-2">
                            <i class="bi bi-folder me-1"></i>Відкрити папку
                        </a>
                    <?php else: ?>
                        <form method="POST" class="mb-3">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="create_folder">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-folder-plus me-1"></i>Створити папку
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; */ ?>
                <!-- Related Tasks Link (View All) -->
                <?php if (!empty($relatedTasks)): ?>
                    <a href="/tasks/?order_id=<?= $orderId ?>" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                        <i class="bi bi-kanban me-1"></i>Відкрити в Kanban
                    </a>
                <?php endif; ?>

                <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                    <hr class="my-3">
                    <?php if (!empty($order['is_deleted'])): ?>
                        <form method="POST" class="mb-2">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="restore">
                            <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Відновити замовлення
                            </button>
                        </form>
                        <form id="permanentDeleteForm" method="POST">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="permanent_delete">
                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="confirmDelete('Ви впевнені? Замовлення буде видалено НАЗАВЖДИ разом з усіма задачами, платежами та коментарями!', () => document.getElementById('permanentDeleteForm').submit())">
                                <i class="bi bi-trash-fill me-1"></i>Видалити остаточно
                            </button>
                        </form>
                    <?php else: ?>
                        <form id="deleteOrderForm" method="POST">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="soft_delete">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="confirmDelete('Ви впевнені, що хочете видалити це замовлення? Всі пов\'язані задачі також будуть приховані.', () => document.getElementById('deleteOrderForm').submit())">
                                <i class="bi bi-trash me-1"></i>Видалити замовлення
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- QR Code Tracking -->
        <!-- Tracking Link -->
        <?php
        $trackingUrl = (defined('APP_URL') ? APP_URL : '') . '/tracking/?token=' . $order['access_token'];
        ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-link-45deg me-2"></i>Відстеження
                </h5>
            </div>
            <div class="card-body text-center">
                <p class="text-muted small mb-3">Посилання для клієнта</p>

                <div class="d-grid gap-2">
                    <a href="<?= $trackingUrl ?>" target="_blank" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Відкрити сторінку
                    </a>
                    <button class="btn btn-sm btn-outline-secondary" onclick="copyTrackingLink()">
                        <i class="bi bi-clipboard me-1"></i>Копіювати посилання
                    </button>
                </div>
            </div>
        </div>

        <script>
            function copyTrackingLink() {
                const url = '<?= $trackingUrl ?>';
                navigator.clipboard.writeText(url).then(() => {
                    alert('✓ Посилання скопійовано!');
                }).catch(err => {
                    prompt('Скопіюйте посилання:', url);
                });
            }
        </script>

        <!-- Order Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-info-circle me-2"></i>Інформація
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Номер:</strong></td>
                        <td><?= $order['order_number'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Менеджер:</strong></td>
                        <td><?= htmlspecialchars($order['manager_name']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Дата створення:</strong></td>
                        <td><?= format_date($order['created_at']) ?></td>
                    </tr>
                    <?php if ($order['stall_id']): ?>
                        <tr>
                            <td><strong>Бокс:</strong></td>
                            <td class="text-accent-cyan fw-bold"><?= htmlspecialchars($order['stall_name']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($order['planned_start']): ?>
                        <tr>
                            <td><strong>Дата прибуття:</strong></td>
                            <td><?= format_date($order['planned_start']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($order['deadline']): ?>
                        <tr>
                            <td><strong>Орієнтовна видача:</strong></td>
                            <td class="<?= $order['deadline'] < date('Y-m-d H:i:s') ? 'text-danger' : '' ?>">
                                <?= format_date($order['deadline']) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Додати платіж</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="add_payment">

                    <div class="mb-3">
                        <label class="form-label">Сума *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0" max="<?= $debt ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Метод оплати *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Готівка</option>
                            <option value="card">Карта</option>
                            <option value="fop">ФОП</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-success">Додати платіж</button>
                </div>
            </form>
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
                    <input type="hidden" name="action" value="create_task">

                    <div class="mb-3">
                        <label class="form-label">Назва *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Опис</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Виконавці</label>
                        <select name="executors[]" id="createTaskExecutors" class="form-select" multiple>
                            <?php
                            $executors = Auth::getAllUsers();
                            $executors = array_filter($executors, function ($user) {
                                return in_array($user['role'], ['executor', 'manager', 'director']);
                            });
                            foreach ($executors as $executor):
                            ?>
                                <option value="<?= $executor['id'] ?>"><?= htmlspecialchars($executor['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6">
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
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Дедлайн</label>
                                <input type="text" name="deadline" class="form-control datetime-picker" value="<?= $order['deadline'] ? date('d.m.Y H:i', strtotime($order['deadline'])) : '' ?>" placeholder="Дата і час">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Створити</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirm Close Order Modal -->
<div class="modal fade" id="confirmCloseOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Увага!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fs-5">У цьому замовленні є <strong><span id="incompleteTasksCount"></span></strong> невиконаних задач.</p>
                <p>Ви впевнені, що хочете закрити замовлення?</p>
                <div class="alert alert-secondary small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Всі невиконані задачі залишаться в системі.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                <button type="button" class="btn btn-warning text-dark" id="confirmCloseOrderBtn">
                    Так, закрити замовлення
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Photo Modal -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Завантажити фото</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadPhotoForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Тип фото *</label>
                        <select name="photo_type" class="form-select" required>
                            <option value="before">До робіт</option>
                            <option value="after">Після робіт</option>
                            <option value="process">В процесі</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Файл *</label>
                        <input type="file" name="photo" class="form-control" accept="image/*" required>
                        <small class="text-muted">JPG, PNG, WEBP. Макс 10MB</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Опис (необов'язково)</label>
                        <input type="text" name="caption" class="form-control" placeholder="Наприклад: Подряпина на дверях">
                    </div>

                    <div id="uploadProgress" class="progress" style="display: none; height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>

                    <div id="uploadError" class="alert alert-danger mt-2" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                        <i class="bi bi-upload me-1"></i>Завантажити
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Photo Gallery Logic
    document.addEventListener('DOMContentLoaded', function() {
        const orderId = <?= $order['id'] ?>;

        // Update Task Status
        window.updateTaskStatus = function(taskId, status) {
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
        };

        // Load photos
        function loadPhotos() {
            fetch(`/api/get_order_photos.php?order_id=${orderId}`)
                .then(r => r.json())
                .then(data => {
                    renderPhotos('before', data.before || []);
                    renderPhotos('after', data.after || []);
                    renderPhotos('process', data.process || []);

                    document.getElementById('beforeCount').textContent = (data.before || []).length;
                    document.getElementById('afterCount').textContent = (data.after || []).length;
                    document.getElementById('processCount').textContent = (data.process || []).length;
                })
                .catch(err => console.error('Failed to load photos:', err));
        }

        function renderPhotos(type, photos) {
            const container = document.getElementById(`${type}Photos`);

            if (photos.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-muted py-3">Немає фото</div>';
                return;
            }

            container.innerHTML = photos.map(photo => `
            <div class="col-md-3 col-sm-4 col-6">
                <div class="card bg-dark border-secondary h-100">
                    <img src="${photo.file_url}" class="card-img-top" style="height: 150px; object-fit: cover; cursor: pointer;" loading="lazy" onclick="window.open('${photo.file_url}', '_blank')">
                    ${photo.caption ? `<div class="card-body p-2"><small class="text-muted">${photo.caption}</small></div>` : ''}
                    <div class="card-footer p-1 text-end">
                        <small class="text-muted">${new Date(photo.created_at).toLocaleDateString('uk-UA')}</small>
                    </div>
                </div>
            </div>
        `).join('');
        }

        // Upload form handler
        const uploadForm = document.getElementById('uploadPhotoForm');
        const uploadProgress = document.getElementById('uploadProgress');
        const uploadError = document.getElementById('uploadError');
        const uploadBtn = document.getElementById('uploadBtn');

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(uploadForm);
            uploadProgress.style.display = 'block';
            uploadError.style.display = 'none';
            uploadBtn.disabled = true;

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    uploadProgress.querySelector('.progress-bar').style.width = percent + '%';
                    uploadProgress.querySelector('.progress-bar').textContent = percent + '%';
                }
            });

            xhr.addEventListener('load', function() {
                uploadBtn.disabled = false;

                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('uploadPhotoModal')).hide();
                        uploadForm.reset();
                        uploadProgress.style.display = 'none';
                        loadPhotos(); // Reload gallery
                    } else {
                        uploadError.textContent = response.error || 'Помилка завантаження';
                        uploadError.style.display = 'block';
                    }
                } else {
                    uploadError.textContent = 'Помилка сервера';
                    uploadError.style.display = 'block';
                }
            });

            xhr.addEventListener('error', function() {
                uploadBtn.disabled = false;
                uploadError.textContent = 'Помилка з\'єднання';
                uploadError.style.display = 'block';
            });

            xhr.open('POST', '/api/upload_order_photo.php');
            xhr.send(formData);
        });

        // Initial load
        loadPhotos();
    });
</script>

<script>
    // Quick task status update from order view
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
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>