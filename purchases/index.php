<?php
/**
 * DetalStudio - Purchase Requests
 * Заявки на закупівлі
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$pageTitle = 'Закупівлі';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="card-header-actions">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cart me-1"></i> Заявки на закупівлі
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group me-2">
                            <a href="/purchases/" class="btn btn-outline-secondary <?= !isset($_GET['status']) ? 'active' : '' ?>">Всі</a>
                            <a href="/purchases/?status=pending" class="btn btn-outline-warning <?= ($_GET['status'] ?? '') === 'pending' ? 'active' : '' ?>">Очікують</a>
                            <a href="/purchases/?status=ordered" class="btn btn-outline-info <?= ($_GET['status'] ?? '') === 'ordered' ? 'active' : '' ?>">Замовлені</a>
                            <a href="/purchases/?status=received" class="btn btn-outline-success <?= ($_GET['status'] ?? '') === 'received' ? 'active' : '' ?>">Отримані</a>
                        </div>
                        <a href="/purchases/add.php" class="btn btn-primary">
                            <i class="bi bi-cart-plus me-2"></i>Нова закупівля
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>Номер</th>
                                <th>Постачальник</th>
                                <th>Створив</th>
                                <th>Сума</th>
                                <th>Статус</th>
                                <th>Дата</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $filters = [];
                            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
                            
                            $requests = PurchaseRequest::getList($filters);
                            foreach ($requests as $request): 
                                $statusBadges = [
                                    'pending' => 'bg-warning text-dark',
                                    'approved' => 'bg-primary',
                                    'ordered' => 'bg-info text-dark',
                                    'received' => 'bg-success',
                                    'closed' => 'bg-secondary'
                                ];
                                $statusNames = [
                                    'pending' => 'Очікує',
                                    'approved' => 'Погоджено',
                                    'ordered' => 'Замовлено',
                                    'received' => 'Отримано',
                                    'closed' => 'Закрито'
                                ];
                                ?>
                                <tr>
                                    <td>
                                        <a href="/purchases/view.php?id=<?= $request['id'] ?>" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($request['request_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($request['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($request['requested_by_name']) ?></td>
                                    <td class="fw-bold"><?= format_currency($request['total_amount']) ?></td>
                                    <td>
                                        <span class="badge <?= $statusBadges[$request['status']] ?? 'bg-secondary' ?>">
                                            <?= $statusNames[$request['status']] ?? $request['status'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('d.m.Y', strtotime($request['created_at'])) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="/purchases/view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php 
                                            $canDelete = ($request['status'] === 'pending' && (Auth::isAdmin() || $request['requested_by'] == Auth::getUserId())) ||
                                                        ($request['status'] === 'received' && Auth::isAdmin());
                                            if ($canDelete): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Видалити" onclick="confirmDeleteRequest(<?= $request['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

<form id="deleteRequestForm" action="/purchases/delete.php" method="POST" style="display: none;">
    <?= CSRF::input() ?>
    <input type="hidden" name="id" id="deleteRequestId">
</form>

<script>
function confirmDeleteRequest(id) {
    confirmDelete('Видалити заявку на закупівлю?', function() {
        document.getElementById('deleteRequestId').value = id;
        document.getElementById('deleteRequestForm').submit();
    });
}
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>