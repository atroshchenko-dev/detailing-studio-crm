<?php
/**
 * DetalStudio - Service Categories Management
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireRole('director');

$pageTitle = 'Керування категоріями послуг';

// Handle Actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!CSRF::check($_POST['csrf_token'] ?? '')) {
        $message = ['type' => 'danger', 'text' => 'Помилка безпеки.'];
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'add') {
                $result = ServiceCategory::create($_POST);
                $message = $result ? ['type' => 'success', 'text' => 'Категорію додано.'] : ['type' => 'danger', 'text' => 'Помилка при додаванні.'];
            } elseif ($_POST['action'] == 'update') {
                $result = ServiceCategory::update($_POST['id'], $_POST);
                $message = $result ? ['type' => 'success', 'text' => 'Категорію оновлено.'] : ['type' => 'danger', 'text' => 'Помилка при оновленні.'];
            } elseif ($_POST['action'] == 'delete') {
                $result = ServiceCategory::delete($_POST['id']);
                if ($result === true) {
                    $message = ['type' => 'success', 'text' => 'Категорію видалено.'];
                } else {
                    $message = ['type' => 'danger', 'text' => $result ?: 'Помилка при видаленні.'];
                }
            }
        }
    }
}

$categories = ServiceCategory::getAll();

include __DIR__ . '/../views/layout/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="text-accent-orange">КАТЕГОРІЇ ПОСЛУГ</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="/services/" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>До послуг
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-lg me-2"></i>Додати категорію
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show" role="alert">
            <?= $message['text'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Порядок</th>
                            <th>Назва</th>
                            <th>Мітка (BADGE)</th>
                            <th class="text-end">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= $cat['display_order'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($cat['name']) ?></td>
                                <td><span class="badge <?= $cat['color_class'] ?>"><?= htmlspecialchars($cat['name']) ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-info edit-btn" 
                                            data-id="<?= $cat['id'] ?>" 
                                            data-name="<?= htmlspecialchars($cat['name']) ?>" 
                                            data-order="<?= $cat['display_order'] ?>"
                                            data-color="<?= $cat['color_class'] ?>"
                                            data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirmDelete('Ви впевнені, що хочете видалити цю категорію?', () => this.submit());">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                        <?= CSRF::input() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Додати категорію</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <?= CSRF::input() ?>
                <div class="mb-3">
                    <label class="form-label">Назва</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Колір (Bootstrap Class)</label>
                    <select name="color_class" class="form-select">
                        <option value="bg-primary">Primary (Blue)</option>
                        <option value="bg-secondary">Secondary (Gray)</option>
                        <option value="bg-success">Success (Green)</option>
                        <option value="bg-danger">Danger (Red)</option>
                        <option value="bg-warning">Warning (Yellow)</option>
                        <option value="bg-info">Info (Cyan)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Порядок відображення</label>
                    <input type="number" name="display_order" class="form-control" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                <button type="submit" class="btn btn-primary">Зберегти</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="editForm">
            <div class="modal-header">
                <h5 class="modal-title">Редагувати категорію</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-id">
                <?= CSRF::input() ?>
                <div class="mb-3">
                    <label class="form-label">Назва</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Колір (Bootstrap Class)</label>
                    <select name="color_class" id="edit-color" class="form-select">
                        <option value="bg-primary">Primary (Blue)</option>
                        <option value="bg-secondary">Secondary (Gray)</option>
                        <option value="bg-success">Success (Green)</option>
                        <option value="bg-danger">Danger (Red)</option>
                        <option value="bg-warning">Warning (Yellow)</option>
                        <option value="bg-info">Info (Cyan)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Порядок відображення</label>
                    <input type="number" name="display_order" id="edit-order" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                <button type="submit" class="btn btn-primary">Оновити</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit-id').value = this.dataset.id;
        document.getElementById('edit-name').value = this.dataset.name;
        document.getElementById('edit-order').value = this.dataset.order;
        document.getElementById('edit-color').value = this.dataset.color;
    });
});
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
