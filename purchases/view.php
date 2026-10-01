<?php
/**
 * DetalStudio - Purchase Request View
 * Перегляд заявки на закупівлю
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();

$id = $_GET['id'] ?? null;
$request = PurchaseRequest::getById($id);

if (!$request) {
    die("Заявку не знайдено");
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    CSRF::verify();
    
    $action = $_POST['action'];
    $newStatus = null;
    $canPerform = false;
    
    switch ($action) {
        case 'approve':
            if (Auth::isDirector()) {
                $newStatus = 'approved';
                $canPerform = true;
            }
            break;
        case 'order':
            if (Auth::isAtLeast('manager') && $request['status'] === 'approved') {
                $newStatus = 'ordered';
                $canPerform = true;
            }
            break;
        case 'receive':
            if (Auth::isAtLeast('manager') && $request['status'] === 'ordered') {
                $newStatus = 'received';
                $canPerform = true;
            }
            break;
        case 'cancel':
            if ($request['status'] === 'pending' && (Auth::isDirector() || $request['requested_by'] == Auth::getUserId())) {
                $newStatus = 'closed'; // Or cancelled if we had that
                $canPerform = true;
            }
            break;
    }
    
    if ($canPerform && $newStatus) {
        if (PurchaseRequest::update($id, ['status' => $newStatus])) {
            header("Location: /purchases/view.php?id=$id&status_updated=1");
            exit;
        }
    }
}

$items = PurchaseRequest::getItems($id);
$history = Audit::getLogs('purchase_requests', $id);

$statusBadges = [
    'pending' => 'bg-warning text-dark',
    'approved' => 'bg-primary',
    'ordered' => 'bg-info text-dark',
    'received' => 'bg-success',
    'closed' => 'bg-secondary'
];
$statusNames = [
    'pending' => 'Очікує погодження',
    'approved' => 'Погоджено (можна замовляти)',
    'ordered' => 'Замовлено',
    'received' => 'Отримано (залишки оновлено)',
    'closed' => 'Закрито/Скасовано'
];

$pageTitle = 'Заявка ' . $request['request_number'];
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Main Details -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>Заявка <?= htmlspecialchars($request['request_number']) ?>
                </h5>
                <span class="badge <?= $statusBadges[$request['status']] ?? 'bg-secondary' ?> p-2">
                    <?= $statusNames[$request['status']] ?? $request['status'] ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6 border-end">
                        <label class="small text-muted d-block">Постачальник</label>
                        <div class="h5"><?= htmlspecialchars($request['supplier_name']) ?></div>
                        
                        <label class="small text-muted d-block mt-3">Створив</label>
                        <div><?= htmlspecialchars($request['requested_by_name']) ?></div>
                        <div class="small text-muted"><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></div>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <label class="small text-muted d-block">Загальна сума</label>
                        <div class="h3 text-accent-cyan"><?= format_currency($request['total_amount']) ?></div>
                        
                        <?php if ($request['approved_at']): ?>
                            <label class="small text-muted d-block mt-3">Погоджено</label>
                            <div><?= htmlspecialchars($request['approved_by_name']) ?></div>
                            <div class="small text-muted"><?= date('d.m.Y H:i', strtotime($request['approved_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($request['notes']): ?>
                    <div class="p-3 bg-dark-accent rounded mb-4">
                        <label class="small text-muted d-block mb-1">Примітки</label>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                    </div>
                <?php endif; ?>

                <h6 class="border-bottom pb-2 mb-3">Специфікація товарів</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Товар</th>
                                <th class="text-center">Кількість</th>
                                <th class="text-end">Ціна од.</th>
                                <th class="text-end">Сума</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <a href="/warehouse/view.php?id=<?= $item['item_id'] ?>" class="fw-bold text-decoration-none text-info">
                                            <?= htmlspecialchars($item['item_name']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                                        </a>
                                        <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                    </td>
                                    <td class="text-center"><?= $item['quantity'] ?> <?= $item['unit'] ?></td>
                                    <td class="text-end"><?= format_currency($item['unit_price']) ?></td>
                                    <td class="text-end fw-bold"><?= format_currency($item['total_price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Усього до сплати:</th>
                                <th class="text-end text-accent-cyan h5"><?= format_currency($request['total_amount']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Історія змін</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($history)): ?>
                        <div class="p-3 text-center text-muted">Історія поки порожня</div>
                    <?php else: ?>
                        <?php foreach ($history as $log): 
                            $details = json_decode($log['new_value'], true);
                            ?>
                            <div class="list-group-item bg-transparent border-dark-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-dark-accent"><?= htmlspecialchars($log['action']) ?></span>
                                    <small class="text-muted"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></small>
                                </div>
                                <div class="small">
                                    <span class="text-accent-orange"><?= htmlspecialchars($log['user_name'] ?? 'Система') ?>:</span>
                                    <?php if ($log['action'] === 'purchase_status_change'): ?>
                                        Змінено статус з <strong><?= $details['old_status'] ?></strong> на <strong><?= $details['new_status'] ?></strong>
                                    <?php else: ?>
                                        <?= htmlspecialchars($log['action']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Sidebar -->
    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header">
                <h6 class="mb-0">Дії</h6>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['status_updated'])): ?>
                    <div class="alert alert-success small py-2">Статус успішно оновлено!</div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <?php if ($request['status'] === 'pending' && Auth::isDirector()): ?>
                        <form method="POST" class="mb-2">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-check-circle me-2"></i>Погодити заявку
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'approved'): ?>
                        <form method="POST" class="mb-2">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="order">
                            <button type="submit" class="btn btn-info w-100 text-dark fw-bold">
                                <i class="bi bi-truck me-2"></i>Позначити як «Замовлено»
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'ordered'): ?>
                        <form method="POST">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="receive">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-box-seam me-2"></i>Товар отримано (Оприбуткувати)
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'received'): ?>
                        <div class="alert alert-success py-2 mb-3">
                            <i class="bi bi-check2-all me-2"></i>Оприбутковано на склад.
                        </div>
                        <?php if (Auth::isAdmin()): ?>
                            <button type="button" class="btn btn-outline-danger w-100" onclick="confirmDeleteRequest(<?= $request['id'] ?>)">
                                <i class="bi bi-trash me-2"></i>Скасувати та видалити
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'closed'): ?>
                        <div class="alert alert-secondary mb-0">Заявку скасовано або закрито.</div>
                    <?php endif; ?>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <a href="/purchases/" class="btn btn-sm btn-link text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>До списку закупівель
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteRequestForm" action="/purchases/delete.php" method="POST" style="display: none;">
    <?= CSRF::input() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
</form>

<script>
function confirmDeleteRequest(id) {
    if (typeof confirmDelete === 'function') {
        confirmDelete('Видалити цю закупівлю? Товари будуть автоматично списані зі складу.', function() {
            document.getElementById('deleteRequestForm').submit();
        });
    } else {
        if (confirm('Видалити цю закупівлю? Товари будуть автоматично списані зі складу.')) {
            document.getElementById('deleteRequestForm').submit();
        }
    }
}
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
