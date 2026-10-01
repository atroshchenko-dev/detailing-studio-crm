<?php

/**
 * DetalStudio - Edit Service
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('/services/');
}

$service = Service::getById($id);
if (!$service) {
    Flash::error('Послугу не знайдено');
    redirect('/services/');
}

$errors = [];
$categories = ServiceCategory::getAll();

$warehouseItems = [];
if (\App\Models\Setting::get('feature_warehouse', '1') == '1') {
    $warehouseItems = WarehouseItem::getAll();
}
$currentResources = Service::getResourceTemplates($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();

    $validator = new Validator($_POST);
    $validator->required('name', 'Назва послуги')
        ->required('category_id', 'Категорія')
        ->required('base_price', 'Ціна')
        ->numeric('base_price', 'Ціна')
        ->numeric('duration_minutes', 'Орієнтовний час');

    if ($validator->isValid()) {
        $data = [
            'name' => trim($_POST['name']),
            'category_id' => intval($_POST['category_id']),
            'base_price' => floatval($_POST['base_price']),
            'commission_type' => $_POST['commission_type'] ?? 'percentage',
            'commission_value' => floatval($_POST['commission_value'] ?? 0),
            'duration_minutes' => intval($_POST['duration_minutes'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'auto_create_task' => isset($_POST['auto_create_task']) ? 1 : 0,
            'custom_options' => $_POST['custom_options'] ?? [],
            'resource_templates' => $_POST['resource_templates'] ?? []
        ];

        if (Service::update($id, $data)) {
            Flash::success('Дані послуги оновлено');
            $service = array_merge($service, $data);
        } else {
            Flash::error('Помилка при оновленні послуги');
        }
    } else {
        Flash::error('Будь ласка, виправте помилки');
        $errors = $validator->getErrors();
        $service = array_merge($service, $_POST);
    }
}

$pageTitle = 'Редагувати послугу';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-accent-orange">
                        <i class="bi bi-pencil me-2"></i>РЕДАГУВАТИ ПОСЛУГУ
                    </h5>
                    <a href="/services/" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Назад
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?= CSRF::input() ?>

                    <div class="mb-3">
                        <label for="name" class="form-label">Назва послуги *</label>
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" required value="<?= htmlspecialchars($service['name']) ?>">
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= $errors['name'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Категорія *</label>
                        <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required>
                            <option value="">Оберіть категорію...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($service['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category_id'])): ?>
                            <div class="invalid-feedback"><?= $errors['category_id'] ?></div>
                        <?php endif; ?>
                    </div>

            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="commission_type" class="form-label text-accent-cyan">Тип комісії виконавця</label>
                <select class="form-select" id="commission_type" name="commission_type">
                    <option value="percentage" <?= ($service['commission_type'] ?? '') == 'percentage' ? 'selected' : '' ?>>Відсоток (%)</option>
                    <option value="fixed" <?= ($service['commission_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>Фіксована сума (грн)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="commission_value" class="form-label text-accent-cyan">Значення комісії</label>
                <input type="number" step="0.01" class="form-control" id="commission_value" name="commission_value" value="<?= htmlspecialchars($service['commission_value'] ?? 0) ?>">
            </div>
        </div>

        <div class="row text-white-50 small mb-3">
            <div class="col-12">
                <i class="bi bi-info-circle me-1"></i>Це значення буде автоматично нараховано виконавцю при завершенні задачі.
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="duration_minutes" class="form-label">Орієнтовний час (хв)</label>
            <input type="number" class="form-control <?= isset($errors['duration_minutes']) ? 'is-invalid' : '' ?>" id="duration_minutes" name="duration_minutes" value="<?= htmlspecialchars($service['duration_minutes'] ?? 0) ?>">
            <?php if (isset($errors['duration_minutes'])): ?>
                <div class="invalid-feedback"><?= $errors['duration_minutes'] ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="auto_create_task" name="auto_create_task" value="1" <?= (!empty($service['auto_create_task'])) ? 'checked' : '' ?>>
            <label class="form-check-label text-accent-cyan" for="auto_create_task">Автоматично створювати задачу при додаванні до замовлення</label>
        </div>
    </div>

    <!-- Resource Templates Section -->
    <?php if (\App\Models\Setting::get('feature_warehouse', '1') == '1'): ?>
        <div class="mb-4">
            <label class="form-label text-accent-cyan">
                <i class="bi bi-box-seam me-1"></i>Шаблон витратних матеріалів
            </label>
            <div id="resource-templates-container" class="bg-dark p-3 rounded border border-secondary mb-2">
                <?php if (empty($currentResources)): ?>
                    <div class="resource-row row g-2 mb-2 align-items-center">
                        <div class="col-md-6">
                            <select name="resource_templates[0][item_id]" class="form-select form-select-sm">
                                <option value="">Оберіть товар зі складу...</option>
                                <?php foreach ($warehouseItems as $wi): ?>
                                    <option value="<?= $wi['id'] ?>"><?= htmlspecialchars($wi['name']) ?> (<?= $wi['unit'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" step="0.001" name="resource_templates[0][quantity]" class="form-control form-control-sm" placeholder="К-сть">
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="resource_templates[0][notes]" class="form-control form-control-sm" placeholder="Прим.">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-resource" style="display:none;"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($currentResources as $idx => $res): ?>
                        <div class="resource-row row g-2 mb-2 align-items-center">
                            <div class="col-md-6">
                                <select name="resource_templates[<?= $idx ?>][item_id]" class="form-select form-select-sm">
                                    <option value="">Оберіть товар зі складу...</option>
                                    <?php foreach ($warehouseItems as $wi): ?>
                                        <option value="<?= $wi['id'] ?>" <?= ($wi['id'] == $res['warehouse_item_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($wi['name']) ?> (<?= $wi['unit'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.001" name="resource_templates[<?= $idx ?>][quantity]" class="form-control form-control-sm" value="<?= $res['quantity'] ?>" placeholder="К-сть">
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="resource_templates[<?= $idx ?>][notes]" class="form-control form-control-sm" value="<?= htmlspecialchars($res['notes']) ?>" placeholder="Прим.">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-resource"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-info" id="add-resource-row">
                <i class="bi bi-plus-lg me-1"></i>Додати матеріал
            </button>
            <div class="form-text mt-1">Ці матеріали будуть автоматично запропоновані до списання при виконанні послуги.</div>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Кастомні доповнення</label>
        <div id="custom-options-container">
            <?php
            $options = Service::getCustomOptions($id);
            if (empty($options)): ?>
                <div class="row g-2 mb-2 custom-option-row">
                    <div class="col-7">
                        <input type="text" name="custom_options[0][name]" class="form-control form-control-sm" placeholder="Назва (н-д: Озонування)">
                    </div>
                    <div class="col-4">
                        <input type="number" step="0.01" name="custom_options[0][price]" class="form-control form-control-sm" placeholder="Ціна">
                    </div>
                    <div class="col-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-option" style="display:none;"><i class="bi bi-dash"></i></button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($options as $idx => $opt): ?>
                    <div class="row g-2 mb-2 custom-option-row">
                        <div class="col-7">
                            <input type="text" name="custom_options[<?= $idx ?>][name]" class="form-control form-control-sm" value="<?= htmlspecialchars($opt['name']) ?>" placeholder="Назва">
                        </div>
                        <div class="col-4">
                            <input type="number" step="0.01" name="custom_options[<?= $idx ?>][price]" class="form-control form-control-sm" value="<?= htmlspecialchars($opt['price']) ?>" placeholder="Ціна">
                        </div>
                        <div class="col-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-option"><i class="bi bi-dash"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-info mt-2" id="add-custom-option">
            <i class="bi bi-plus-lg me-1"></i>Додати поле
        </button>
    </div>

    <div class="mb-4">
        <label for="description" class="form-label">Опис</label>
        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1 btn-lg">
            <i class="bi bi-check-lg me-2"></i>Оновити послугу
        </button>
    </div>
    </form>
</div>
</div>
</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let optionIndex = <?= count($options) ?: 1 ?>;
        const container = document.getElementById('custom-options-container');
        const addButton = document.getElementById('add-custom-option');

        addButton.addEventListener('click', function() {
            const div = document.createElement('div');
            div.className = 'row g-2 mb-2 custom-option-row';
            div.innerHTML = `
            <div class="col-7">
                <input type="text" name="custom_options[${optionIndex}][name]" class="form-control form-control-sm" placeholder="Назва">
            </div>
            <div class="col-4">
                <input type="number" step="0.01" name="custom_options[${optionIndex}][price]" class="form-control form-control-sm" placeholder="Ціна">
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-option"><i class="bi bi-dash"></i></button>
            </div>
        `;
            container.appendChild(div);
            optionIndex++;
        });

        container.addEventListener('click', function(e) {
            if (e.target.closest('.remove-option')) {
                e.target.closest('.custom-option-row').remove();
            }
        });

        // Resource Templates Logic
        let resourceIndex = <?= count($currentResources) ?: 1 ?>;
        const resourceContainer = document.getElementById('resource-templates-container');
        const addResourceButton = document.getElementById('add-resource-row');
        const warehouseItems = <?= json_encode($warehouseItems) ?>;

        addResourceButton.addEventListener('click', function() {
            const div = document.createElement('div');
            div.className = 'resource-row row g-2 mb-2 align-items-center';

            let optionsHtml = '<option value="">Оберіть товар...</option>';
            warehouseItems.forEach(item => {
                optionsHtml += `<option value="${item.id}">${item.name} (${item.unit})</option>`;
            });

            div.innerHTML = `
            <div class="col-md-6">
                <select name="resource_templates[${resourceIndex}][item_id]" class="form-select form-select-sm">
                    ${optionsHtml}
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" step="0.001" name="resource_templates[${resourceIndex}][quantity]" class="form-control form-control-sm" placeholder="К-сть">
            </div>
            <div class="col-md-2">
                <input type="text" name="resource_templates[${resourceIndex}][notes]" class="form-control form-control-sm" placeholder="Прим.">
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-resource"><i class="bi bi-trash"></i></button>
            </div>
        `;
            resourceContainer.appendChild(div);
            resourceIndex++;
        });

        resourceContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-resource')) {
                e.target.closest('.resource-row').remove();
            }
        });
    });
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>