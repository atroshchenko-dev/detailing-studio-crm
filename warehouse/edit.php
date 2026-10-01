<?php
/**
 * DetalStudio - Edit Warehouse Item
 * Редагування товару
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('/warehouse/');
}

$item = WarehouseItem::getById($id);
if (!$item) {
    Flash::error('Товар не знайдено');
    redirect('/warehouse/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $validator = new Validator($_POST);
    $validator->required('name', 'Назва товару')
              ->required('category', 'Категорія')
              ->required('unit', 'Одиниця виміру')
              ->required('min_quantity', 'Мін. залишок')
              ->numeric('min_quantity', 'Мін. залишок')
              ->unique('sku', 'warehouse_items', 'sku', 'Артикул', $id);
              
    if ($validator->isValid()) {
        $data = [
            'name' => trim($_POST['name']),
            'sku' => trim($_POST['sku'] ?? ''),
            'category' => trim($_POST['category']),
            'description' => trim($_POST['description'] ?? ''),
            'unit' => trim($_POST['unit']),
            'min_quantity' => floatval($_POST['min_quantity'])
        ];
        
        if (WarehouseItem::update($id, $data)) {
            Flash::success('Дані товару оновлено');
            $item = array_merge($item, $data);
        } else {
            Flash::error('Помилка при оновленні товару');
        }
    } else {
        Flash::error('Будь ласка, виправте помилки');
        $errors = $validator->getErrors();
        $item = array_merge($item, $_POST);
    }
}

$pageTitle = 'Редагувати товар';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-accent-orange">
                        <i class="bi bi-pencil me-2"></i>РЕДАГУВАТИ ТОВАР
                    </h5>
                    <a href="/warehouse/" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Назад
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?= CSRF::input() ?>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Назва товару *</label>
                            <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" required value="<?= htmlspecialchars($item['name']) ?>">
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= $errors['name'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sku" class="form-label">Артикул (SKU)</label>
                            <input type="text" class="form-control <?= isset($errors['sku']) ? 'is-invalid' : '' ?>" id="sku" name="sku" value="<?= htmlspecialchars($item['sku'] ?? '') ?>">
                            <?php if (isset($errors['sku'])): ?>
                                <div class="invalid-feedback"><?= $errors['sku'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Категорія *</label>
                            <select class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>" id="category" name="category" required>
                                <option value="consumables" <?= $item['category'] == 'consumables' ? 'selected' : '' ?>>Витратні матеріали</option>
                                <option value="parts" <?= $item['category'] == 'parts' ? 'selected' : '' ?>>Запчастини</option>
                                <option value="equipment" <?= $item['category'] == 'equipment' ? 'selected' : '' ?>>Обладнання</option>
                                <option value="tools" <?= $item['category'] == 'tools' ? 'selected' : '' ?>>Інструменти</option>
                            </select>
                            <?php if (isset($errors['category'])): ?>
                                <div class="invalid-feedback"><?= $errors['category'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Од. вим. *</label>
                            <select class="form-select <?= isset($errors['unit']) ? 'is-invalid' : '' ?>" id="unit" name="unit" required>
                                <option value="шт" <?= $item['unit'] == 'шт' ? 'selected' : '' ?>>шт</option>
                                <option value="л" <?= $item['unit'] == 'л' ? 'selected' : '' ?>>л</option>
                                <option value="мл" <?= $item['unit'] == 'мл' ? 'selected' : '' ?>>мл</option>
                                <option value="кг" <?= $item['unit'] == 'кг' ? 'selected' : '' ?>>кг</option>
                                <option value="г" <?= $item['unit'] == 'г' ? 'selected' : '' ?>>г</option>
                                <option value="м" <?= $item['unit'] == 'м' ? 'selected' : '' ?>>м</option>
                                <option value="комплект" <?= $item['unit'] == 'комплект' ? 'selected' : '' ?>>комплект</option>
                            </select>
                            <?php if (isset($errors['unit'])): ?>
                                <div class="invalid-feedback"><?= $errors['unit'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="min_quantity" class="form-label">Мін. залишок *</label>
                            <input type="number" step="0.01" class="form-control <?= isset($errors['min_quantity']) ? 'is-invalid' : '' ?>" id="min_quantity" name="min_quantity" required value="<?= htmlspecialchars($item['min_quantity']) ?>">
                            <?php if (isset($errors['min_quantity'])): ?>
                                <div class="invalid-feedback"><?= $errors['min_quantity'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Опис</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Для зміни залишку товару скористайтесь функціями приходу або списання на сторінці перегляду.
                        Поточний залишок: <strong><?= $item['current_quantity'] ?> <?= $item['unit'] ?></strong>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-check-lg me-2"></i>Зберегти зміни
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
