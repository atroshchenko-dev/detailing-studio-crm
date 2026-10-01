<?php

/**
 * DetalStudio - Orders List
 * Список замовлень
 */

require_once __DIR__ . '/../config/config.php';

use App\Controllers\OrderController;
use App\Models\Order;
use App\Models\Client;
use App\Models\Service;
use App\Auth;

Auth::requireAuth();

// Instantiate Controller
$controller = new OrderController();
$data = $controller->index();

// Extract data for view
$orders = $data['orders'];
$ordersByStatus = $data['ordersByStatus'];
$viewType = $data['viewType'];
$tab = $data['tab'];
$clients = $data['clients'];
$services = $data['services'];
$executors = $data['executors'];
$totalOrders = $data['totalOrders'];
$stalls = \App\Models\Stall::getAll();

$pageTitle = 'Замовлення';
$additionalScripts = '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';

// Helper functions for view (if needed locally, or keep using Models directly where helpers aren't enough)
// Note: We keep using App\Models\Order static methods for badges if they are static.

include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-actions">
                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-group" role="group">
                            <a href="?view=kanban" class="btn btn-sm <?= $viewType == 'kanban' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="bi bi-kanban"></i> Kanban
                            </a>
                            <a href="?view=list&tab=<?= $tab ?>" class="btn btn-sm <?= $viewType == 'list' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="bi bi-list"></i> Список
                            </a>
                        </div>
                    </div>

                    <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOrderModal">
                            <i class="bi bi-plus-circle me-1"></i>Додати замовлення
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">

                <!-- TABS -->
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

                <?php if ($viewType === 'list'): ?>

                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-receipt display-4"></i>
                            <p class="mt-3">Замовлень не знайдено</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Номер</th>
                                        <th>Клієнт</th>
                                        <th>Авто</th>
                                        <th>Сума</th>
                                        <th>Оплачено</th>
                                        <th>Борг</th>
                                        <th>Статус</th>
                                        <th>Менеджер</th>
                                        <th>Дата</th>
                                        <th>Дія</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <?php
                                        $debt = $order['total_amount'] - $order['paid_amount'];
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($order['client_name']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($order['make']) ?>
                                                <?= htmlspecialchars($order['model']) ?>
                                                <?php if ($order['license_plate']): ?>
                                                    <br><small class="text-muted"><?= $order['license_plate'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= format_currency($order['total_amount']) ?></td>
                                            <td><?= format_currency($order['paid_amount']) ?></td>
                                            <td>
                                                <?php if ($debt > 0): ?>
                                                    <span class="text-danger"><?= format_currency($debt) ?></span>
                                                <?php else: ?>
                                                    <span class="text-success">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm p-0 border-0 badge <?= get_status_badge_class($order['status']) ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <?= Order::getStatusName($order['status']) ?>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php
                                                            $statuses = [
                                                                'new' => 'Нові',
                                                                'in_progress' => 'В роботі',
                                                                'ready' => 'Готово',
                                                                'closed' => 'Закрито'
                                                            ];
                                                            foreach ($statuses as $k => $v):
                                                            ?>
                                                                <li><a class="dropdown-item status-update-item" href="#" data-id="<?= $order['id'] ?>" data-status="<?= $k ?>"><?= $v ?></a></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge <?= get_status_badge_class($order['status']) ?>">
                                                        <?= Order::getStatusName($order['status']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['manager_name']) ?></td>
                                            <td><?= format_date($order['created_at'], 'd.m.Y') ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="/orders/view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary" title="Перегляд">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (Auth::hasAnyRole(['manager', 'director'])): ?>
                                                        <a href="/orders/edit.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-warning" title="Редагувати">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- KANBAN VIEW -->
                    <!-- KANBAN VIEW -->
                    <div class="kanban-board">
                        <?php
                        // Status config
                        $allColumns = [
                            'new' => ['title' => 'Нові', 'badge' => 'bg-info'],
                            'in_progress' => ['title' => 'В роботі', 'badge' => 'bg-warning text-dark'],
                            'ready' => ['title' => 'Готово', 'badge' => 'bg-success'],
                            'closed' => ['title' => 'Закрито', 'badge' => 'bg-secondary'],
                            'cancelled' => ['title' => 'Скасовано', 'badge' => 'bg-danger']
                        ];

                        // Filter columns based on tab
                        $kanbanColumns = [];
                        if ($tab === 'active') {
                            $kanbanColumns = array_intersect_key($allColumns, array_flip(['new', 'in_progress', 'ready']));
                        } elseif ($tab === 'history') {
                            $kanbanColumns = array_intersect_key($allColumns, array_flip(['closed', 'cancelled']));
                        } else {
                            $kanbanColumns = $allColumns;
                        }
                        ?>

                        <?php foreach ($kanbanColumns as $statusKey => $colData): ?>
                            <div class="kanban-column">
                                <h6 class="mb-3">
                                    <span class="badge <?= $colData['badge'] ?>"><?= $colData['title'] ?></span>
                                    <span class="badge bg-secondary ms-1"><?= count($ordersByStatus[$statusKey]) ?></span>
                                </h6>
                                <div class="kanban-cards" data-status-group="<?= $statusKey ?>">
                                    <?php foreach ($ordersByStatus[$statusKey] as $order): ?>
                                        <div class="kanban-card" data-order-id="<?= $order['id'] ?>" data-current-status="<?= $order['status'] ?>">
                                            <div class="d-flex justify-content-between mb-2">
                                                <strong><?= $order['order_number'] ?></strong>
                                                <small class="text-muted"><?= format_date($order['created_at'], 'd.m') ?></small>
                                            </div>
                                            <div class="mb-2">
                                                <div class="fw-bold text-truncate" title="<?= htmlspecialchars($order['client_name']) ?>">
                                                    <?= htmlspecialchars($order['client_name']) ?>
                                                </div>
                                                <div class="small text-muted text-truncate">
                                                    <?= htmlspecialchars($order['make']) ?> <?= htmlspecialchars($order['model']) ?>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-secondary">
                                                <span class="text-accent-cyan small fw-bold">
                                                    <?= format_currency($order['total_amount']) ?>
                                                </span>
                                                <a href="/orders/view.php?id=<?= $order['id'] ?>" class="btn btn-xs btn-outline-light py-0" style="font-size: 0.7rem;">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                            <?php if ($order['manager_name']): ?>
                                                <div class="mt-1 text-end">
                                                    <small class="text-muted" style="font-size: 0.7rem;">
                                                        <i class="bi bi-person"></i> <?= htmlspecialchars(explode(' ', $order['manager_name'])[0]) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Order Modal -->
<div class="modal fade" id="createOrderModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Нове замовлення</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= \App\CSRF::input() ?>
                    <input type="hidden" name="action" value="create">

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="client_mode" id="clientModeExisting" value="existing" checked>
                                <label class="form-check-label" for="clientModeExisting">Існуючий клієнт</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="client_mode" id="clientModeNew" value="new">
                                <label class="form-check-label" for="clientModeNew">Новий клієнт</label>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Client Section -->
                    <div id="existingClientSection">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Клієнт *</label>
                                    <div class="position-relative">
                                        <input type="text" id="clientSearchInput" class="form-control" placeholder="Введіть ім'я або телефон..." autocomplete="off">
                                        <input type="hidden" name="client_id" id="clientSelect" value="">
                                        <div id="clientSearchResults" class="position-absolute w-100 border rounded bg-dark shadow" style="display: none; max-height: 250px; overflow-y: auto; z-index: 1000; top: 100%;"></div>
                                    </div>
                                    <div class="mt-2 text-info small" id="loyaltyStatus" style="display:none;">
                                        <i class="bi bi-star-fill me-1"></i>Знижка лояльності: <span id="loyaltyDiscountValue">0</span>%
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Автомобіль *</label>
                                    <div class="input-group">
                                        <select name="car_id" class="form-select" id="carSelect">
                                            <option value="">Спочатку оберіть клієнта</option>
                                        </select>
                                        <button class="btn btn-outline-secondary" type="button" id="btnAddNewCar" title="Додати інше авто">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="is_new_car" id="isNewCar" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Client Section -->
                    <div id="newClientSection" class="border rounded p-3 mb-3" style="display: none; background: rgba(255,255,255,0.05); border-color: #333 !important;">
                        <h6 class="mb-3 text-info"><i class="bi bi-person-plus me-2"></i>Дані клієнта</h6>
                        <input type="hidden" name="is_new_client" id="isNewClient" value="0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ПІБ Клієнта *</label>
                                <input type="text" name="new_client_name" class="form-control" id="newClientName">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Телефон *</label>
                                <input type="tel" name="new_client_phone" class="form-control" id="newClientPhone">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="new_client_email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Примітки</label>
                                <input type="text" name="new_client_notes" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- New Car Section (Shared) -->
                    <div id="newCarSection" class="border rounded p-3 mb-3" style="display: none; background: rgba(255,255,255,0.05); border-color: #333 !important;">
                        <h6 class="mb-3 text-info"><i class="bi bi-car-front me-2"></i>Дані автомобіля</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Марка *</label>
                                <select name="new_car_make" id="newCarMakeSelect" class="form-select">
                                    <option value="">Оберіть марку</option>
                                </select>
                                <input type="text" name="new_car_make_custom" id="newCarMakeCustom" class="form-control mt-2" placeholder="Марка вручну" style="display: none;">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Модель *</label>
                                <select name="new_car_model" id="newCarModelSelect" class="form-select" disabled>
                                    <option value="">Оберіть модель</option>
                                </select>
                                <input type="text" name="new_car_model_custom" id="newCarModelCustom" class="form-control mt-2" placeholder="Модель вручну" style="display: none;">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Рік</label>
                                <select name="new_car_year" class="form-select">
                                    <option value="">Рік</option>
                                    <?php for ($y = date('Y') + 1; $y >= 1950; $y--): ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Держ. номер</label>
                                <input type="text" name="new_car_plate" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3 pt-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="new_car_restyling" value="1" id="restylingCheck">
                                    <label class="form-check-label" for="restylingCheck">
                                        Рестайлінг
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">VIN</label>
                            <input type="text" name="new_car_vin" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <?php if (\App\Models\Setting::get('feature_stalls', '1') == '1'): ?>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label text-accent-cyan">Бокс (Пост)</label>
                                    <select name="stall_id" class="form-select">
                                        <option value="">-- Не призначено --</option>
                                        <?php foreach ($stalls as $s): ?>
                                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Дата прибуття авто</label>
                                <input type="text" name="planned_start" class="form-control date-picker" placeholder="Дата...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-accent-cyan">Орієнтовна видача</label>
                                <input type="text" name="deadline" class="form-control date-picker" placeholder="Дата...">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Примітки до замовлення</label>
                                <textarea name="notes" class="form-control" rows="1"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Послуги</label>

                        <!-- Floating Cart Summary -->
                        <div id="serviceCartSummary" class="alert alert-info d-flex justify-content-between align-items-center mb-2 sticky-top shadow-sm" style="display: none; top: 0; z-index: 100;">
                            <div>
                                <i class="bi bi-cart-check me-2"></i>Обрано: <strong id="cartCount">0</strong>
                            </div>
                            <div class="fw-bold fs-5">
                                <span id="cartTotal">0</span> ₴
                            </div>
                        </div>

                        <!-- Services Filter -->
                        <div class="card bg-dark border-secondary mb-3">
                            <div class="card-body p-2">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-secondary border-secondary text-white"><i class="bi bi-search"></i></span>
                                            <input type="text" id="serviceSearch" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Пошук послуги...">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="d-flex flex-wrap gap-1" id="categoryFilters">
                                            <button type="button" class="btn btn-sm btn-info flex-shrink-0 active py-1 px-2 rounded-pill" style="font-size: 0.8rem;" data-filter="all">Всі</button>
                                            <?php
                                            // Handle case where category_name might be null or empty
                                            $categories = array_filter(array_unique(array_column($services, 'category_name')));
                                            sort($categories);
                                            foreach ($categories as $cat):
                                                if (empty($cat)) continue;
                                            ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0 py-1 px-2 rounded-pill" style="font-size: 0.8rem;" data-filter="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover" id="servicesTable">
                                <thead style="position: sticky; top: 0; background: #000; z-index: 10;">
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th class="sortable" data-sort="name" style="cursor: pointer;">Послуга <i class="bi bi-arrow-down-up text-muted"></i></th>
                                        <th class="sortable" data-sort="category" style="cursor: pointer;">Категорія <i class="bi bi-arrow-down-up text-muted"></i></th>
                                        <th style="width: 100px;">Кількість</th>
                                        <th class="sortable" data-sort="price" style="cursor: pointer; width: 120px;">Ціна <i class="bi bi-arrow-down-up text-muted"></i></th>
                                        <?php if (\App\Models\Setting::get('feature_tasks', '1') == '1'): ?>
                                            <th style="width: 40px;" title="Створити задачу">Задача</th>
                                        <?php endif; ?>
                                        <th style="width: 120px;">Сума</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service):
                                        $catName = $service['category_name'] ?? '';
                                    ?>
                                        <tr class="service-row" data-name="<?= mb_strtolower($service['name']) ?>" data-category="<?= htmlspecialchars($catName) ?>" data-price="<?= $service['base_price'] ?>">
                                            <td>
                                                <input type="checkbox" name="services[<?= $service['id'] ?>][selected]" value="1" class="service-checkbox form-check-input">
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($service['name']) ?></div>
                                            </td>
                                            <td><span class="badge bg-secondary text-dark small"><?= htmlspecialchars($catName) ?></span></td>
                                            <td>
                                                <input type="number" name="services[<?= $service['id'] ?>][quantity]" value="1" min="1" class="form-control form-control-sm quantity-input" style="width: 80px;">
                                            </td>
                                            <td>
                                                <input type="number" name="services[<?= $service['id'] ?>][price]" value="<?= $service['base_price'] ?>" min="0" step="0.01" class="form-control form-control-sm price-input" style="width: 100px;">
                                            </td>
                                            <?php if (\App\Models\Setting::get('feature_tasks', '1') == '1'): ?>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                                        <div class="form-check m-0">
                                                            <input class="form-check-input" type="checkbox" name="services[<?= $service['id'] ?>][create_task]" value="1" title="Створити задачу для цієї послуги">
                                                        </div>
                                                        <button type="button" class="btn btn-xs btn-outline-secondary task-settings-btn" data-service-id="<?= $service['id'] ?>" title="Налаштування задачі">
                                                            <i class="bi bi-gear"></i>
                                                        </button>
                                                    </div>
                                                    <!-- Hidden inputs for Task Settings -->
                                                    <div id="taskSettings_<?= $service['id'] ?>">
                                                        <input type="hidden" name="services[<?= $service['id'] ?>][task_priority]" value="medium" class="task-priority-input">
                                                        <input type="hidden" name="services[<?= $service['id'] ?>][task_deadline]" value="" class="task-deadline-input">
                                                        <!-- Executors will be appended dynamically or via separate request logic, 
                                                             but simplest is to just have a container we update via JS -->
                                                        <div class="task-executors-container"></div>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                            <td class="total-cell fw-bold text-accent-cyan"><?= format_currency($service['base_price']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr id="noServicesFound" style="display: none;">
                                        <td colspan="7" class="text-center text-muted py-3">
                                            <i class="bi bi-search me-2"></i>Послуг не знайдено
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="6" class="text-end">Сума без знижки:</th>
                                        <th id="subtotalAmount"><?= format_currency(0) ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="6" class="text-end align-middle">Персональна знижка (%):</th>
                                        <th style="width: 120px;">
                                            <input type="number" name="discount_percent" id="orderDiscountPercent" class="form-control form-control-sm" value="0" min="0" max="100">
                                        </th>
                                    </tr>
                                    <tr class="table-dark">
                                        <th colspan="6" class="text-end h5">Разом до сплати:</th>
                                        <th id="totalAmount" class="h5 text-accent-cyan"><?= format_currency(0) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Створити замовлення
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (\App\Models\Setting::get('feature_tasks', '1') == '1'): ?>
    <!-- Task Settings Modal -->
    <div class="modal fade" id="taskSettingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Налаштування задачі</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="currentServiceId">

                    <div class="mb-3">
                        <label class="form-label">Виконавці</label>
                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background: #2b3035;">
                            <?php foreach ($executors as $ex): ?>
                                <div class="form-check">
                                    <input class="form-check-input task-executor-checkbox" type="checkbox" value="<?= $ex['id'] ?>" id="exec_modal_<?= $ex['id'] ?>">
                                    <label class="form-check-label" for="exec_modal_<?= $ex['id'] ?>">
                                        <?= htmlspecialchars($ex['full_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Пріоритет</label>
                        <select id="taskPrioritySelect" class="form-select">
                            <option value="low">Низький</option>
                            <option value="medium" selected>Середній</option>
                            <option value="high">Високий</option>
                            <option value="urgent">Терміновий</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Дедлайн</label>
                        <input type="text" id="taskDeadlineInput" class="form-control datetime-picker" placeholder="Дата і час">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="button" class="btn btn-sm btn-primary" id="saveTaskSettingsBtn">Зберегти</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($viewType === 'kanban'): ?>
            // --- KANBAN LOGIC ---
            document.querySelectorAll('.kanban-cards').forEach(function(column) {
                new Sortable(column, {
                    group: 'kanban_orders',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: function(evt) {
                        const orderId = evt.item.dataset.orderId;
                        const groupTitle = evt.to.dataset.statusGroup;
                        let newStatus = '';

                        // Map Group Title to Single Status
                        switch (groupTitle) {
                            case 'new':
                                newStatus = 'new';
                                break;
                            case 'approved':
                                newStatus = 'approved';
                                break;
                            case 'in_progress':
                                newStatus = 'in_progress';
                                break;
                            case 'waiting':
                                newStatus = 'waiting_client';
                                break;
                            case 'ready':
                                newStatus = 'ready';
                                break;
                            case 'closed':
                                newStatus = 'closed';
                                break;
                        }

                        if (!newStatus) return;

                        // Send API request
                        const formData = new FormData();
                        formData.append('order_id', orderId);
                        formData.append('status', newStatus);

                        fetch('/api/update_order_status.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    // Correct: update visual or do nothing as it's already moved.
                                    evt.item.dataset.currentStatus = newStatus;
                                } else {
                                    alert('Помилка: ' + data.message);
                                    location.reload();
                                }
                            })
                            .catch(e => {
                                console.error(e);
                                alert('Помилка мережі');
                                location.reload();
                            });
                    }
                });
            });
        <?php endif; ?>

        // --- Status Update Handler (List View) ---
        document.querySelectorAll('.status-update-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const orderId = this.dataset.id;
                const newStatus = this.dataset.status;

                if (!confirm('Змінити статус замовлення?')) return;

                const formData = new FormData();
                formData.append('order_id', orderId);
                formData.append('status', newStatus);

                fetch('/api/update_order_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Помилка: ' + data.message);
                        }
                    })
                    .catch(e => {
                        console.error(e);
                        alert('Помилка мережі');
                    });
            });
        });

        // Cart summary elements (used by calculateTotal below)
        const cartSummary = document.getElementById('serviceCartSummary');
        const cartCount = document.getElementById('cartCount');
        const cartTotal = document.getElementById('cartTotal');

        // --- Existing Service Filter Logic ---
        const serviceSearch = document.getElementById('serviceSearch');
        const categoryBtns = document.querySelectorAll('#categoryFilters button');
        const serviceRows = document.querySelectorAll('.service-row');
        const noServicesMsg = document.getElementById('noServicesFound');

        let currentFilter = 'all';
        let currentSearch = '';

        function filterServices() {
            let visibleCount = 0;
            serviceRows.forEach(row => {
                const name = row.dataset.name;
                const category = row.dataset.category;

                const matchesSearch = name.includes(currentSearch);
                const matchesCategory = currentFilter === 'all' || category === currentFilter;

                if (matchesSearch && matchesCategory) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                noServicesMsg.style.display = '';
            } else {
                noServicesMsg.style.display = 'none';
            }
        }

        if (serviceSearch) {
            serviceSearch.addEventListener('input', function() {
                currentSearch = this.value.toLowerCase().trim();
                filterServices();
            });
        }

        if (categoryBtns) {
            categoryBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Update active state
                    categoryBtns.forEach(b => b.classList.remove('active', 'btn-info'));
                    categoryBtns.forEach(b => b.classList.add('btn-outline-secondary'));

                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('active', 'btn-info');

                    currentFilter = this.dataset.filter;
                    filterServices();
                });
            });
        }

        // --- Sorting Logic ---
        const sortableHeaders = document.querySelectorAll('.sortable');
        let currentSortField = null;
        let currentSortDir = 'asc';

        sortableHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const field = this.dataset.sort;

                // Toggle direction
                if (currentSortField === field) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortField = field;
                    currentSortDir = 'asc';
                }

                // Update header icons
                sortableHeaders.forEach(h => {
                    const icon = h.querySelector('i');
                    icon.className = 'bi bi-arrow-down-up text-muted';
                });
                const activeIcon = this.querySelector('i');
                activeIcon.className = currentSortDir === 'asc' ? 'bi bi-arrow-up text-info' : 'bi bi-arrow-down text-info';

                // Sort rows
                const tbody = document.querySelector('#servicesTable tbody');
                const rows = Array.from(serviceRows);

                rows.sort((a, b) => {
                    let valA, valB;

                    if (field === 'name') {
                        valA = a.dataset.name;
                        valB = b.dataset.name;
                    } else if (field === 'category') {
                        valA = a.dataset.category;
                        valB = b.dataset.category;
                    } else if (field === 'price') {
                        valA = parseFloat(a.dataset.price) || 0;
                        valB = parseFloat(b.dataset.price) || 0;
                    }

                    if (typeof valA === 'string') {
                        return currentSortDir === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    } else {
                        return currentSortDir === 'asc' ? valA - valB : valB - valA;
                    }
                });

                // Re-append sorted rows
                rows.forEach(row => tbody.appendChild(row));
                // Keep noServicesFound at the end
                tbody.appendChild(noServicesMsg.closest('tr') || noServicesMsg);
            });
        });

        // --- Price Calculation Logic ---
        const quantityInputs = document.querySelectorAll('.quantity-input');
        const priceInputs = document.querySelectorAll('.price-input');
        const checkboxes = document.querySelectorAll('.service-checkbox');
        const discountInput = document.getElementById('orderDiscountPercent');
        const subtotalEl = document.getElementById('subtotalAmount');
        const totalEl = document.getElementById('totalAmount');

        function calculateTotal() {
            let subtotal = 0;
            let selectedCount = 0;

            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (checkbox.checked) {
                    selectedCount++;
                    row.classList.add('selected');
                    const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                    const price = parseFloat(row.querySelector('.price-input').value) || 0;
                    const rowTotal = quantity * price;

                    // Update row total display
                    row.querySelector('.total-cell').textContent = rowTotal.toFixed(2);

                    subtotal += rowTotal;
                } else {
                    row.classList.remove('selected');
                }
            });

            // Calculate discount
            const discountPercent = parseFloat(discountInput.value) || 0;
            const total = subtotal - (subtotal * discountPercent / 100);

            // Update footer display
            subtotalEl.textContent = subtotal.toFixed(2);
            totalEl.textContent = total.toFixed(2);

            // Update floating cart
            if (cartSummary) {
                cartCount.textContent = selectedCount;
                cartTotal.textContent = total.toFixed(2);
                cartSummary.style.display = selectedCount > 0 ? 'flex' : 'none';
            }
        }

        // Attach listeners
        [...quantityInputs, ...priceInputs, ...checkboxes].forEach(input => {
            input.addEventListener('change', calculateTotal);
            input.addEventListener('input', calculateTotal);
        });

        if (discountInput) {
            discountInput.addEventListener('input', calculateTotal);
        }

        // Initial Calc
        calculateTotal();

        // Client/Car Dynamic Loading
        const clientSelect = document.getElementById('clientSelect');
        const carSelect = document.getElementById('carSelect');
        const btnAddNewCar = document.getElementById('btnAddNewCar');
        const existingClientSection = document.getElementById('existingClientSection');
        const newClientSection = document.getElementById('newClientSection');
        const newCarSection = document.getElementById('newCarSection');
        const isNewClientInput = document.getElementById('isNewClient');
        const isNewCarInput = document.getElementById('isNewCar');

        // --- Client Autocomplete Logic ---
        const clientSearchInput = document.getElementById('clientSearchInput');
        const clientSearchResults = document.getElementById('clientSearchResults');
        let searchTimeout = null;

        if (clientSearchInput) {
            function searchClients(query) {
                clearTimeout(searchTimeout);

                searchTimeout = setTimeout(() => {
                    fetch(`/api/search_clients.php?q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(clients => {
                            if (!Array.isArray(clients)) {
                                console.error('Invalid response from search_clients API:', clients);
                                clientSearchResults.style.display = 'none';
                                return;
                            }

                            if (clients.length === 0) {
                                clientSearchResults.innerHTML = '<div class="p-2 text-muted">Клієнтів не знайдено</div>';
                            } else {
                                clientSearchResults.innerHTML = clients.map(c => `
                                <div class="client-result p-2 border-bottom hover-highlight" style="cursor: pointer;" data-id="${c.id}" data-name="${c.name}" data-phone="${c.phone || ''}">
                                    <div class="fw-bold text-white">${c.name}</div>
                                    <small class="text-muted">${c.phone || ''}</small>
                                </div>
                            `).join('');
                            }
                            clientSearchResults.style.display = 'block';
                        })
                        .catch(err => {
                            console.error(err);
                            clientSearchResults.style.display = 'none';
                        });
                }, 200);
            }

            clientSearchInput.addEventListener('input', function() {
                searchClients(this.value.trim());
            });

            clientSearchInput.addEventListener('focus', function() {
                searchClients(this.value.trim());
            });

            // Handle result click
            clientSearchResults.addEventListener('click', function(e) {
                const item = e.target.closest('.client-result');
                if (item) {
                    const clientId = item.dataset.id;
                    const clientName = item.dataset.name;
                    const clientPhone = item.dataset.phone;

                    clientSearchInput.value = `${clientName} (${clientPhone})`;
                    clientSelect.value = clientId;
                    clientSearchResults.style.display = 'none';

                    // Trigger car loading
                    loadCarsForClient(clientId);

                    // Fetch loyalty discount
                    fetchLoyaltyDiscount(clientId);
                }
            });

            // Hide results on outside click
            document.addEventListener('click', function(e) {
                if (!clientSearchInput.contains(e.target) && !clientSearchResults.contains(e.target)) {
                    clientSearchResults.style.display = 'none';
                }
            });
        }

        function loadCarsForClient(clientId) {
            carSelect.innerHTML = '<option value="">Завантаження...</option>';

            if (!clientId) {
                carSelect.innerHTML = '<option value="">Спочатку оберіть клієнта</option>';
                return;
            }

            fetch(`/api/get_cars.php?client_id=${clientId}`)
                .then(r => r.json())
                .then(cars => {
                    carSelect.innerHTML = '<option value="">Оберіть авто</option>';
                    cars.forEach(car => {
                        const option = document.createElement('option');
                        option.value = car.id;
                        option.textContent = `${car.make} ${car.model} (${car.license_plate})`;
                        carSelect.appendChild(option);
                    });
                })
                .catch(err => {
                    console.error(err);
                    carSelect.innerHTML = '<option value="">Помилка завантаження</option>';
                });
        }

        function fetchLoyaltyDiscount(clientId) {
            fetch(`/api/get_client_discount.php?client_id=${clientId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.discount > 0) {
                        document.getElementById('loyaltyDiscountValue').textContent = data.discount;
                        document.getElementById('loyaltyStatus').style.display = 'block';
                        const discountInput = document.getElementById('orderDiscountPercent');
                        if (discountInput && parseFloat(discountInput.value) < data.discount) {
                            discountInput.value = data.discount;
                            calculateTotal();
                        }
                    } else {
                        document.getElementById('loyaltyStatus').style.display = 'none';
                    }
                })
                .catch(err => console.error(err));
        }


        // Toggle Client Mode
        document.querySelectorAll('input[name="client_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'new') {
                    existingClientSection.style.display = 'none';
                    newClientSection.style.display = 'block';
                    newCarSection.style.display = 'block'; // New clients always need new car
                    btnAddNewCar.style.display = 'none'; // Can't select existing car for new client

                    isNewClientInput.value = '1';
                    isNewCarInput.value = '1';

                    // Reset select
                    clientSelect.value = '';
                    carSelect.innerHTML = '<option value="">Новий клієнт</option>';
                } else {
                    existingClientSection.style.display = 'block';
                    newClientSection.style.display = 'none';
                    newCarSection.style.display = 'none';
                    btnAddNewCar.style.display = 'block';

                    isNewClientInput.value = '0';
                    isNewCarInput.value = '0';
                }
            });
        });

        // Note: Car loading is now handled by loadCarsForClient() called from autocomplete

        // Toggle New Car Mode for Existing Client
        if (btnAddNewCar) {
            btnAddNewCar.addEventListener('click', function() {
                const isAddingNew = newCarSection.style.display === 'none';

                if (isAddingNew) {
                    newCarSection.style.display = 'block';
                    carSelect.disabled = true;
                    isNewCarInput.value = '1';
                    this.innerHTML = '<i class="bi bi-x-lg"></i>';
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-outline-danger');
                } else {
                    newCarSection.style.display = 'none';
                    carSelect.disabled = false;
                    isNewCarInput.value = '0';
                    this.innerHTML = '<i class="bi bi-plus-lg"></i>';
                    this.classList.remove('btn-outline-danger');
                    this.classList.add('btn-outline-secondary');
                }
            });
        }

        // Car Make/Model Logic
        const makeSelect = document.getElementById('newCarMakeSelect');
        const modelSelect = document.getElementById('newCarModelSelect');
        const makeCustom = document.getElementById('newCarMakeCustom');
        const modelCustom = document.getElementById('newCarModelCustom');

        // Load Makes
        fetch('/api/get_makes.php')
            .then(r => r.json())
            .then(makes => {
                if (makeSelect) {
                    makes.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id;
                        opt.textContent = m.name;
                        makeSelect.appendChild(opt);
                    });


                    // Add Other option
                    const other = document.createElement('option');
                    other.value = 'other';
                    other.textContent = 'Інша (ввести вручну)';
                    makeSelect.appendChild(other);
                }
            });

        if (makeSelect) {
            makeSelect.addEventListener('change', function() {
                const makeId = this.value;

                if (makeId === 'other') {
                    makeCustom.style.display = 'block';
                    modelSelect.innerHTML = '<option value="other">Інша</option>';
                    modelSelect.disabled = true; // No models to fetch for custom make
                    modelCustom.style.display = 'block';
                } else if (makeId) {
                    makeCustom.style.display = 'none';
                    modelCustom.style.display = 'none';
                    modelSelect.disabled = false;
                    modelSelect.innerHTML = '<option value="">Завантаження...</option>';

                    // Fetch Models
                    fetch(`/api/get_models.php?make_id=${makeId}`)
                        .then(r => r.json())
                        .then(models => {
                            modelSelect.innerHTML = '<option value="">Оберіть модель</option>';
                            models.forEach(m => {
                                const opt = document.createElement('option');
                                opt.value = m.name; // We store name string
                                opt.textContent = m.name;
                                modelSelect.appendChild(opt);
                            });

                            const other = document.createElement('option');
                            other.value = 'other';
                            other.textContent = 'Інша (ввести вручну)';
                            modelSelect.appendChild(other);
                        });
                } else {
                    makeCustom.style.display = 'none';
                    modelSelect.innerHTML = '<option value="">Оберіть модель</option>';
                    modelSelect.disabled = true;
                    modelCustom.style.display = 'none';
                }
            });
        }

        if (modelSelect) {
            modelSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    modelCustom.style.display = 'block';
                } else {
                    modelCustom.style.display = 'none';
                }
            });
        }
    });

    // --- Task Settings Logic ---
    document.addEventListener('DOMContentLoaded', function() {
        const taskSettingsModalEl = document.getElementById('taskSettingsModal');
        const taskSettingsModal = new bootstrap.Modal(taskSettingsModalEl);
        const saveTaskSettingsBtn = document.getElementById('saveTaskSettingsBtn');

        // Inputs in Modal
        const currentServiceIdInput = document.getElementById('currentServiceId');
        const taskExecutorsSelect = document.getElementById('taskExecutorsSelect');
        const taskPrioritySelect = document.getElementById('taskPrioritySelect');
        const taskDeadlineInput = document.getElementById('taskDeadlineInput');

        // Open Modal
        document.body.addEventListener('click', function(e) {
            // Use closest to handle clicks on icon inside button
            const btn = e.target.closest('.task-settings-btn');
            if (btn) {
                e.preventDefault();
                const serviceId = btn.dataset.serviceId;
                currentServiceIdInput.value = serviceId;

                // Check checkbox automatically when opening settings
                const checkbox = document.querySelector(`input[name="services[${serviceId}][create_task]"]`);
                if (checkbox) checkbox.checked = true;

                // Load existing values from hidden inputs
                const priorityInput = document.querySelector(`input[name="services[${serviceId}][task_priority]"]`);
                const deadlineInput = document.querySelector(`input[name="services[${serviceId}][task_deadline]"]`);
                const executorsContainer = document.querySelector(`#taskSettings_${serviceId} .task-executors-container`);

                // Reset modal
                taskPrioritySelect.value = priorityInput ? priorityInput.value : 'medium';
                taskDeadlineInput.value = deadlineInput ? deadlineInput.value : '';

                // Restore executors selection
                const existingExecutorIds = [];
                executorsContainer.querySelectorAll('input').forEach(inp => {
                    existingExecutorIds.push(inp.value);
                });



                Array.from(document.querySelectorAll('.task-executor-checkbox')).forEach(cb => {
                    cb.checked = existingExecutorIds.includes(cb.value);
                });

                taskSettingsModal.show();
            }
        });

        // Save Settings
        saveTaskSettingsBtn.addEventListener('click', function() {
            const serviceId = currentServiceIdInput.value;
            const priority = taskPrioritySelect.value;
            const deadline = taskDeadlineInput.value;

            // Update hidden inputs
            const priorityInput = document.querySelector(`input[name="services[${serviceId}][task_priority]"]`);
            if (priorityInput) priorityInput.value = priority;

            const deadlineInput = document.querySelector(`input[name="services[${serviceId}][task_deadline]"]`);
            if (deadlineInput) deadlineInput.value = deadline;

            // Update Executors
            const executorsContainer = document.querySelector(`#taskSettings_${serviceId} .task-executors-container`);
            executorsContainer.innerHTML = ''; // Clear

            const selectedExecutorIds = Array.from(document.querySelectorAll('.task-executor-checkbox:checked')).map(cb => cb.value);

            selectedExecutorIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `services[${serviceId}][task_executors][]`;
                input.value = id;
                executorsContainer.appendChild(input);
            });

            // Update UI Button style
            const btn = document.querySelector(`.task-settings-btn[data-service-id="${serviceId}"]`);
            if (btn) {
                if (selectedExecutorIds.length > 0 || priority !== 'medium' || deadline) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-info');
                } else {
                    btn.classList.remove('btn-info');
                    btn.classList.add('btn-outline-secondary');
                }
            }

            taskSettingsModal.hide();
        });

        // Initialize Flatpickr on modal deadline input
        if (typeof flatpickr !== 'undefined' && taskDeadlineInput) {
            flatpickr(taskDeadlineInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                locale: typeof flatpickr.l10ns.uk !== 'undefined' ? 'uk' : 'default'
            });
        }
    });
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>