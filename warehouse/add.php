<?php
/**
 * DetalStudio - Add Warehouse Item
 * Додання товару
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $validator = new Validator($_POST);
    $validator->required('name', 'Назва товару')
              ->required('category', 'Категорія')
              ->required('unit', 'Одиниця виміру')
              ->required('min_quantity', 'Мін. залишок')
              ->numeric('min_quantity', 'Мін. залишок')
              ->unique('sku', 'warehouse_items', 'sku', 'Артикул');
              
    if ($validator->isValid()) {
        $data = [
            'name' => trim($_POST['name']),
            'sku' => trim($_POST['sku'] ?? ''),
            'category' => trim($_POST['category']),
            'description' => trim($_POST['description'] ?? ''),
            'unit' => trim($_POST['unit']),
            'min_quantity' => floatval($_POST['min_quantity'])
        ];
        
        $itemId = WarehouseItem::create($data);
        
        if ($itemId) {
            // Initial stock?
            $initialQty = floatval($_POST['initial_quantity'] ?? 0);
            $initialPrice = floatval($_POST['initial_price'] ?? 0);
            
            if ($initialQty > 0) {
                WarehouseItem::addStock($itemId, $initialQty, $initialPrice, 'Початковий залишок');
            }
            
            Flash::success('Товар успішно створено');
            redirect('/warehouse/');
        } else {
            Flash::error('Помилка при створенні товару');
        }
    } else {
        Flash::error('Будь ласка, виправте помилки');
        $errors = $validator->getErrors();
    }
}

$pageTitle = 'Додати товар';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-accent-orange">
                        <i class="bi bi-plus-lg me-2"></i>ДОДАТИ ТОВАР
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
                            <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= $errors['name'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sku" class="form-label">Артикул (SKU)</label>
                            <input type="text" class="form-control <?= isset($errors['sku']) ? 'is-invalid' : '' ?>" id="sku" name="sku" value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>">
                            <?php if (isset($errors['sku'])): ?>
                                <div class="invalid-feedback"><?= $errors['sku'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Категорія *</label>
                            <select class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>" id="category" name="category" required>
                                <option value="">Оберіть категорію...</option>
                                <option value="consumables" <?= ($_POST['category'] ?? '') == 'consumables' ? 'selected' : '' ?>>Витратні матеріали</option>
                                <option value="parts" <?= ($_POST['category'] ?? '') == 'parts' ? 'selected' : '' ?>>Запчастини</option>
                                <option value="equipment" <?= ($_POST['category'] ?? '') == 'equipment' ? 'selected' : '' ?>>Обладнання</option>
                                <option value="tools" <?= ($_POST['category'] ?? '') == 'tools' ? 'selected' : '' ?>>Інструменти</option>
                            </select>
                            <?php if (isset($errors['category'])): ?>
                                <div class="invalid-feedback"><?= $errors['category'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Од. вим. *</label>
                            <select class="form-select <?= isset($errors['unit']) ? 'is-invalid' : '' ?>" id="unit" name="unit" required>
                                <option value="шт" <?= ($_POST['unit'] ?? '') == 'шт' ? 'selected' : '' ?>>шт</option>
                                <option value="л" <?= ($_POST['unit'] ?? '') == 'л' ? 'selected' : '' ?>>л</option>
                                <option value="мл" <?= ($_POST['unit'] ?? '') == 'мл' ? 'selected' : '' ?>>мл</option>
                                <option value="кг" <?= ($_POST['unit'] ?? '') == 'kg' ? 'selected' : '' ?>>кг</option>
                                <option value="г" <?= ($_POST['unit'] ?? '') == 'г' ? 'selected' : '' ?>>г</option>
                                <option value="м" <?= ($_POST['unit'] ?? '') == 'м' ? 'selected' : '' ?>>м</option>
                                <option value="комплект" <?= ($_POST['unit'] ?? '') == 'комплект' ? 'selected' : '' ?>>комплект</option>
                            </select>
                            <?php if (isset($errors['unit'])): ?>
                                <div class="invalid-feedback"><?= $errors['unit'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="min_quantity" class="form-label">Мін. залишок *</label>
                            <input type="number" step="0.01" class="form-control <?= isset($errors['min_quantity']) ? 'is-invalid' : '' ?>" id="min_quantity" name="min_quantity" required value="<?= htmlspecialchars($_POST['min_quantity'] ?? '0') ?>">
                            <?php if (isset($errors['min_quantity'])): ?>
                                <div class="invalid-feedback"><?= $errors['min_quantity'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Опис</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3">Початковий залишок (необов'язково)</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="initial_quantity" class="form-label">Кількість</label>
                            <input type="number" step="0.01" class="form-control" id="initial_quantity" name="initial_quantity" value="<?= htmlspecialchars($_POST['initial_quantity'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="initial_price" class="form-label">Ціна за одиницю (грн)</label>
                            <input type="number" step="0.01" class="form-control" id="initial_price" name="initial_price" value="<?= htmlspecialchars($_POST['initial_price'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Зберегти
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
