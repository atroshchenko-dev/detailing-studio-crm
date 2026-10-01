<?php
/**
 * DetalStudio - Client View
 * Картка клієнта
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$clientId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$clientId) {
    redirect('/clients/');
}

$client = Client::getById($clientId);
if (!$client) {
    Flash::error('Клієнта не знайдено');
    redirect('/clients/');
}

$cars = Car::getByClient($clientId);
$stats = Client::getStats($clientId);
$orders = Order::getList(['client_id' => $clientId]);

$pageTitle = 'Картка клієнта: ' . $client['name'];
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <!-- Client Profile & Stats -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-accent-cyan">
                    <i class="bi bi-person-badge me-2"></i>ПРОФІЛЬ
                </h5>
                <a href="/clients/edit.php?id=<?= $clientId ?>" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-pencil"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="display-6 text-white mb-2"><?= htmlspecialchars($client['name']) ?></div>
                    <div class="text-accent-orange fw-bold"><?= htmlspecialchars($client['phone']) ?></div>
                    <?php if ($client['email']): ?>
                        <div class="text-muted small"><?= htmlspecialchars($client['email']) ?></div>
                    <?php endif; ?>
                </div>

                <hr class="border-secondary">

                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-2 bg-dark-accent rounded">
                            <div class="small text-muted text-uppercase">Замовлень</div>
                            <div class="h5 mb-0"><?= $stats['total_orders'] ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-dark-accent rounded">
                            <div class="small text-muted text-uppercase">Автомобілів</div>
                            <div class="h5 mb-0"><?= count($cars) ?></div>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="p-3 bg-dark-accent rounded border-start border-3 border-success">
                            <div class="small text-muted text-uppercase">Загальна сума</div>
                            <div class="h4 mb-0 text-success"><?= format_currency($stats['total_spent']) ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($client['notes']): ?>
                    <div class="mt-4">
                        <label class="small text-muted text-uppercase">Нотатки</label>
                        <div class="p-3 bg-dark-accent rounded small text-light">
                            <?= nl2br(htmlspecialchars($client['notes'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark">
                <h5 class="mb-0 text-accent-orange">
                    <i class="bi bi-car-front me-2"></i>АВТОМОБІЛІ
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush bg-transparent">
                    <?php if (empty($cars)): ?>
                        <div class="p-4 text-center text-muted">Немає зареєстрованих авто</div>
                    <?php else: ?>
                        <?php foreach ($cars as $car): ?>
                            <div class="list-group-item bg-transparent border-secondary py-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-white h6 mb-1">
                                            <?= htmlspecialchars($car['make'] . ' ' . $car['model']) ?>
                                        </div>
                                        <?php if ($car['license_plate']): ?>
                                            <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($car['license_plate']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($car['year']): ?>
                                            <small class="text-muted ms-2"><?= $car['year'] ?> р.в.</small>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#history_<?= $car['id'] ?>">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                </div>
                                
                                <div class="collapse mt-3" id="history_<?= $car['id'] ?>">
                                    <div class="bg-dark p-2 rounded small">
                                        <div class="text-accent-cyan mb-2 border-bottom border-secondary pb-1">Історія обслуговування:</div>
                                        <?php 
                                        $history = Car::getServiceHistory($car['id']);
                                        if (empty($history)): ?>
                                            <div class="text-muted italic py-1">Роботи ще не проводились</div>
                                        <?php else: ?>
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach ($history as $h): ?>
                                                    <li class="mb-2 border-bottom border-dark pb-1">
                                                        <div class="d-flex justify-content-between">
                                                            <a href="/orders/view.php?id=<?= $h['order_id'] ?>" class="text-white text-decoration-none">
                                                                <?= htmlspecialchars($h['service_name']) ?>
                                                            </a>
                                                            <span class="text-muted" style="font-size: 0.7rem;"><?= format_date($h['order_date'], false) ?></span>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.75rem;">Замовлення #<?= $h['order_number'] ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders History -->
    <div class="col-lg-8">
        <div class="card shadow-sm overflow-hidden">
            <div class="card-header bg-dark">
                <h5 class="mb-0 text-white">
                    <i class="bi bi-receipt me-2"></i>ІСТОРІЯ ЗАМОВЛЕНЬ
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Дата</th>
                                <th>Автомобіль</th>
                                <th>Сума</th>
                                <th>Статус</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="fw-bold text-accent-cyan">#<?= $order['order_number'] ?></td>
                                    <td><?= format_date($order['created_at'], false) ?></td>
                                    <td><?= htmlspecialchars($order['make'] . ' ' . $order['model']) ?></td>
                                    <td class="fw-bold"><?= format_currency($order['total_amount'] - ($order['discount_amount'] ?? 0)) ?></td>
                                    <td><?= get_status_badge($order['status']) ?></td>
                                    <td class="text-end">
                                        <a href="/orders/view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-light">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
function get_status_badge($status) {
    $classes = [
        'new' => 'bg-info text-dark',
        'approved' => 'bg-primary',
        'in_progress' => 'bg-warning text-dark',
        'waiting_client' => 'bg-secondary',
        'closed' => 'bg-success',
        'cancelled' => 'bg-danger'
    ];
    $names = [
        'new' => 'Нове',
        'approved' => 'Підтверджено',
        'in_progress' => 'В роботі',
        'waiting_client' => 'Очікує',
        'closed' => 'Закрито',
        'cancelled' => 'Скасовано'
    ];
    $class = $classes[$status] ?? 'bg-light text-dark';
    $name = $names[$status] ?? $status;
    return "<span class=\"badge $class\">$name</span>";
}

include __DIR__ . '/../views/layout/footer.php'; 
?>
