<?php

/**
 * DetalStudio - Edit Order
 * Редагування замовлення
 */

require_once __DIR__ . '/../config/config.php';

use App\Models\Order;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stall;
use App\Auth;
use App\Flash;
use App\CSRF;

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('/orders/');
}

$order = Order::getById($id);
if (!$order) {
    Flash::error('Замовлення не знайдено');
    redirect('/orders/');
}

$errors = [];
$clients = Client::getAll();
$services = Service::getAll();
$orderItems = Order::getItems($id);
$client = Client::getById($order['client_id']);
$stalls = Stall::getAll();
$executors = Auth::getAllUsers(); // Fetch all users using Auth helper

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();

    $data = [
        'notes' => !empty($_POST['notes']) ? $_POST['notes'] : null,
        'deadline' => !empty($_POST['deadline']) ? $_POST['deadline'] : null,
        'discount_percent' => max(0, min(100, floatval($_POST['discount_percent'] ?? 0))),
        'stall_id' => !empty($_POST['stall_id']) ? intval($_POST['stall_id']) : null,
        'planned_start' => !empty($_POST['planned_start']) ? $_POST['planned_start'] : null,
        'planned_end' => !empty($_POST['planned_end']) ? $_POST['planned_end'] : null
    ];

    if (Order::update($id, $data)) {
        // Handle items
        // Simplified approach: remove all and re-add (if we want to keep it simple)
        // Better: update changed, add new, remove missing

        $processedServiceIds = [];
        if (!empty($_POST['services'])) {
            // Get current internal items to compare
            $currentItems = Order::getItems($id);
            $currentServiceMap = [];
            foreach ($currentItems as $ci) {
                $currentServiceMap[$ci['service_id']] = $ci;
            }

            foreach ($_POST['services'] as $serviceId => $itemData) {
                if (!empty($itemData['selected'])) {
                    $processedServiceIds[] = $serviceId;
                    $qty = max(0.01, floatval($itemData['quantity'] ?? 1));
                    $price = max(0, floatval($itemData['price'] ?? 0));
                    $total = $qty * $price;

                    if (isset($currentServiceMap[$serviceId])) {
                        // Update existing
                        Order::updateItem($currentServiceMap[$serviceId]['id'], [
                            'quantity' => $qty,
                            'unit_price' => $price,
                            'total_price' => $total
                        ]);
                    } else {
                        // Add new
                        $newItemId = Order::addItem($id, $serviceId, $qty, $price);

                        // Create Task if requested
                        if (!empty($itemData['create_task'])) {
                            $service = Service::getById($serviceId);

                            // Executors Logic
                            $executors = [];
                            if (!empty($itemData['task_executors'])) {
                                $executors = $itemData['task_executors'];
                            } elseif (!empty($itemData['executor'])) { // Fallback if old field used
                                $executors[] = $itemData['executor'];
                            }

                            $priority = $itemData['task_priority'] ?? 'medium';
                            $deadline = !empty($itemData['task_deadline']) ? $itemData['task_deadline'] : ($data['deadline'] ?? null);

                            \App\Models\Task::create([
                                'title' => $service['name'],
                                'description' => "Задача для послуги: " . $service['name'] . "\nЗамовлення: " . $order['order_number'],
                                'order_id' => $id,
                                'priority' => $priority,
                                'deadline' => $deadline,
                                'executors' => $executors
                            ]);
                        }
                    }
                }
            }

            // Remove missing
            foreach ($currentItems as $ci) {
                if (!in_array($ci['service_id'], $processedServiceIds)) {
                    Order::removeItem($ci['id']);
                }
            }
        }

        Flash::success('Замовлення оновлено');
        redirect("/orders/view.php?id=$id");
    } else {
        Flash::error('Помилка при оновленні замовлення');
    }
}

$pageTitle = 'Редагувати замовлення #' . $order['order_number'];
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card shadow-sm">
            <div class="card-header bg-dark">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-accent-orange">
                        <i class="bi bi-pencil me-2"></i>РЕДАГУВАТИ ЗАМОВЛЕННЯ #<?= $order['order_number'] ?>
                    </h5>
                    <a href="/orders/view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Назад
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?= CSRF::input() ?>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Клієнт</h6>
                            <p class="mb-1 fw-bold"><?= htmlspecialchars($order['client_name']) ?></p>
                            <p class="text-muted small"><?= htmlspecialchars($client['phone'] ?? '') ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Автомобіль</h6>
                            <p class="mb-1 fw-bold"><?= htmlspecialchars($order['make'] . ' ' . $order['model']) ?></p>
                            <p class="text-muted small"><?= htmlspecialchars($order['license_plate'] ?? '') ?></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label text-accent-cyan">Бокс (Пост)</label>
                                <select name="stall_id" class="form-select">
                                    <option value="">-- Не призначено --</option>
                                    <?php foreach ($stalls as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $order['stall_id'] == $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Дата прибуття авто</label>
                                <input type="text" name="planned_start" class="form-control date-picker" value="<?= $order['planned_start'] ?>" placeholder="Дата...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label text-accent-cyan">Орієнтовна видача</label>
                                <input type="text" name="deadline" class="form-control date-picker" value="<?= $order['deadline'] ?>" placeholder="Дата...">
                            </div>
                        </div>
                        <!-- Hidden Planned End -->
                        <input type="hidden" name="planned_end" value="<?= $order['planned_end'] ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Примітки</label>
                                <textarea name="notes" class="form-control" rows="1"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Послуги</label>
                        <div class="table-responsive">

                            <!-- Floating Cart Summary -->
                            <div id="serviceCartSummary" class="alert alert-info d-flex justify-content-between align-items-center mb-2 sticky-top shadow-sm" style="display: none; top: 0; z-index: 100;">
                                <div>
                                    <i class="bi bi-cart-check me-2"></i>Обрано: <strong id="cartCount">0</strong>
                                </div>
                                <div class="fw-bold fs-5">
                                    <span id="cartTotal">0</span> ₴
                                </div>
                            </div>
                            <?php
                            // Extract unique categories for filter
                            $categories = array_filter(array_unique(array_column($services, 'category_name')));
                            sort($categories);
                            ?>

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
                                                <?php foreach ($categories as $cat):
                                                    if (empty($cat)) continue;
                                                ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0 py-1 px-2 rounded-pill" style="font-size: 0.8rem;" data-filter="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <table class="table table-sm align-middle" id="servicesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Послуга</th>
                                        <th style="width: 100px;">Кількість</th>
                                        <th style="width: 120px;">Ціна (грн)</th>
                                        <th style="width: 40px;" title="Створити задачу">Задача</th>
                                        <th style="width: 120px;" class="text-end">Сума</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $itemMap = [];
                                    foreach ($orderItems as $item) {
                                        $itemMap[$item['service_id']] = $item;
                                    }

                                    foreach ($services as $s):
                                        $catName = $s['category_name'] ?? '';
                                        $isSelected = isset($itemMap[$s['id']]);
                                        $qty = $isSelected ? $itemMap[$s['id']]['quantity'] : 1;
                                        $price = $isSelected ? $itemMap[$s['id']]['unit_price'] : $s['base_price'];
                                        $total = $qty * $price;
                                    ?>
                                        <tr class="service-row" data-name="<?= mb_strtolower($s['name']) ?>" data-category="<?= htmlspecialchars($catName) ?>">

                                            <td>
                                                <input type="checkbox" name="services[<?= $s['id'] ?>][selected]" value="1" class="service-checkbox form-check-input" <?= $isSelected ? 'checked' : '' ?>>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($s['name']) ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($s['category_name'] ?? '') ?></small>
                                            </td>
                                            <td>
                                                <input type="number" name="services[<?= $s['id'] ?>][quantity]" value="<?= $qty ?>" min="1" step="0.5" class="form-control form-control-sm quantity-input">
                                            </td>
                                            <td>
                                                <input type="number" name="services[<?= $s['id'] ?>][price]" value="<?= $price ?>" min="0" step="0.01" class="form-control form-control-sm price-input">
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center align-items-center gap-2">
                                                    <div class="form-check m-0">
                                                        <input class="form-check-input" type="checkbox" name="services[<?= $s['id'] ?>][create_task]" value="1" title="Створити задачу для цієї послуги (тільки для нових)">
                                                    </div>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary task-settings-btn" data-service-id="<?= $s['id'] ?>" title="Налаштування задачі">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                </div>
                                                <!-- Hidden inputs for Task Settings -->
                                                <div id="taskSettings_<?= $s['id'] ?>">
                                                    <input type="hidden" name="services[<?= $s['id'] ?>][task_priority]" value="medium" class="task-priority-input">
                                                    <input type="hidden" name="services[<?= $s['id'] ?>][task_deadline]" value="" class="task-deadline-input">
                                                    <div class="task-executors-container"></div>
                                                </div>
                                            </td>
                                            <td class="text-end total-cell fw-bold">
                                                <?= format_currency($total) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Сума без знижки:</th>
                                        <th id="subtotalAmount" class="text-end"><?= format_currency($order['total_amount']) ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="4" class="text-end align-middle">Знижка (%):</th>
                                        <th style="width: 120px;">
                                            <input type="number" name="discount_percent" id="orderDiscountPercent" class="form-control form-control-sm text-end" value="<?= $order['discount_percent'] ?>" min="0" max="100">
                                        </th>
                                    </tr>
                                    <tr class="table-dark">
                                        <th colspan="4" class="text-end h5">РАЗОМ:</th>
                                        <th id="totalAmount" class="h5 text-accent-cyan text-end">
                                            <?= format_currency($order['total_amount'] - $order['discount_amount']) ?>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg me-2"></i>Зберегти зміни
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Filter Logic ---
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

        // --- Calc Logic ---
        function calculateTotal() {
            let subtotal = 0;
            let selectedCount = 0;

            document.querySelectorAll('.service-checkbox').forEach(function(checkbox) {
                const row = checkbox.closest('tr');
                if (checkbox.checked) {
                    selectedCount++;
                    row.classList.add('selected');
                    const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
                    const price = parseFloat(row.querySelector('.price-input').value) || 0;
                    const rowTotal = qty * price;

                    row.querySelector('.total-cell').textContent = new Intl.NumberFormat('uk-UA', {
                        style: 'currency',
                        currency: 'UAH'
                    }).format(rowTotal);

                    subtotal += rowTotal;
                } else {
                    row.classList.remove('selected');
                }
            });

            document.getElementById('subtotalAmount').textContent = new Intl.NumberFormat('uk-UA', {
                style: 'currency',
                currency: 'UAH'
            }).format(subtotal);

            const discountPercent = parseFloat(document.getElementById('orderDiscountPercent').value) || 0;
            const discountAmount = (subtotal * discountPercent) / 100;
            const finalTotal = subtotal - discountAmount;

            document.getElementById('totalAmount').textContent = new Intl.NumberFormat('uk-UA', {
                style: 'currency',
                currency: 'UAH'
            }).format(finalTotal);

            // --- Update Floating Cart ---
            const cartSummary = document.getElementById('serviceCartSummary');
            if (cartSummary) {
                document.getElementById('cartCount').textContent = selectedCount;
                document.getElementById('cartTotal').textContent = finalTotal.toFixed(2);
                cartSummary.style.display = selectedCount > 0 ? 'flex' : 'none';
            }
        }

        document.querySelectorAll('.service-checkbox, .quantity-input, .price-input, #orderDiscountPercent').forEach(function(el) {
            el.addEventListener('change', calculateTotal);
            el.addEventListener('input', calculateTotal);
        });

        // Initial calculation to highlight pre-selected rows
        calculateTotal();
    });
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>

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
                    <label class="form-label">Дата виконання</label>
                    <input type="text" id="taskDeadlineInput" class="form-control flatpickr-date" placeholder="Дата">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveTaskSettingsBtn">Зберегти</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Task Settings Logic ---
        const taskSettingsModalEl = document.getElementById('taskSettingsModal');
        const taskSettingsModal = new bootstrap.Modal(taskSettingsModalEl);
        const saveTaskSettingsBtn = document.getElementById('saveTaskSettingsBtn');

        // Inputs in Modal
        const currentServiceIdInput = document.getElementById('currentServiceId');
        const taskPrioritySelect = document.getElementById('taskPrioritySelect');
        const taskDeadlineInput = document.getElementById('taskDeadlineInput');

        // Open Modal
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.task-settings-btn');
            if (btn) {
                e.preventDefault();
                const serviceId = btn.dataset.serviceId;
                currentServiceIdInput.value = serviceId;

                // Check checkbox automatically
                const checkbox = document.querySelector(`input[name="services[${serviceId}][create_task]"]`);
                if (checkbox) checkbox.checked = true;

                // Load existing values
                const priorityInput = document.querySelector(`input[name="services[${serviceId}][task_priority]"]`);
                const deadlineInput = document.querySelector(`input[name="services[${serviceId}][task_deadline]"]`);
                const executorsContainer = document.querySelector(`#taskSettings_${serviceId} .task-executors-container`);

                // Reset modal
                taskPrioritySelect.value = priorityInput ? priorityInput.value : 'medium';
                taskDeadlineInput.value = deadlineInput ? deadlineInput.value : '';

                // Restore executors
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