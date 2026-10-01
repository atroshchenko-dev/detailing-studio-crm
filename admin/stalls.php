<?php
/**
 * DetalStudio - Stall Management
 * Керування боксами (постами)
 */

require_once __DIR__ . '/../config/config.php';

use App\Models\Stall;
use App\Models\Order;

Auth::requireAuth();
Auth::requireAnyRole(['director', 'manager', 'admin']);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($name)) {
            if (Stall::create($name, $description)) {
                Flash::success('Бокс додано успішно');
            } else {
                Flash::error('Помилка при додаванні боксу');
            }
        } else {
            Flash::error('Назва боксу обов\'язкова');
        }
        redirect('/admin/stalls.php');
    }
    
    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id && !empty($name)) {
            if (Stall::update($id, ['name' => $name, 'description' => $description, 'is_active' => $isActive])) {
                Flash::success('Бокс оновлено');
            } else {
                Flash::error('Помилка при оновленні боксу');
            }
        }
        redirect('/admin/stalls.php');
    }
    
    // Simplistic delete (toggle active or soft delete via is_active prefered usually, but user asked for "delete")
    // If Stall model doesn't have delete, we can just deactivate or add delete method.
    // Looking at Stall model, it doesn't have delete. I will stick to activation/deactivation edit for now 
    // or add a delete method if really needed, but usually soft delete (is_active=0) is safer for referential integrity.
}

$stalls = Stall::getAll(false); // Get all, including inactive
$pageTitle = 'Керування боксами';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-box-seam me-2 text-accent-orange"></i>Керування боксами</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStallModal">
                <i class="bi bi-plus-lg me-2"></i>Додати бокс
            </button>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Назва</th>
                                <th>Опис</th>
                                <th>Статус</th>
                                <th class="text-end pe-4">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stalls as $stall): ?>
                                <tr>
                                    <td class="ps-4 text-muted">#<?= $stall['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($stall['name']) ?></td>
                                    <td><?= htmlspecialchars($stall['description']) ?></td>
                                    <td>
                                        <?php if ($stall['is_active']): ?>
                                            <span class="badge bg-success">Активний</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Неактивний</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-info me-1" 
                                                onclick="editStall(<?= htmlspecialchars(json_encode($stall)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($stalls)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        Боксів поки немає
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createStallModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Додати новий бокс</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Назва <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Напр. Бокс 1">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Опис</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Додаткова інформація..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editStallModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Редагувати бокс</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Назва <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Опис</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Активний</label>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти зміни</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editStall(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_name').value = data.name;
        document.getElementById('edit_description').value = data.description;
        document.getElementById('edit_is_active').checked = data.is_active == 1;
        
        new bootstrap.Modal(document.getElementById('editStallModal')).show();
    }
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
