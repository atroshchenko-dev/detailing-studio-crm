<?php

/**
 * DetalStudio - Add Service
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$errors = [];
$categories = ServiceCategory::getAll();
$allServices = Service::getAll();

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
            'duration_minutes' => intval($_POST['duration_minutes'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'custom_options' => $_POST['custom_options'] ?? []
        ];

        $serviceId = Service::create($data);

        if ($serviceId) {
            Flash::success('Послугу успішно створено');
            redirect('/services/');
        } else {
            Flash::error('Помилка при створенні послуги');
        }
    } else {
        Flash::error('Будь ласка, виправте помилки');
        $errors = $validator->getErrors();
    }
}

$pageTitle = 'Додати послугу';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-accent-orange">
                        <i class="bi bi-plus-lg me-2"></i>ДОДАТИ ПОСЛУГУ
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
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Наприклад: Делікатна мийка">
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= $errors['name'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Категорія *</label>
                        <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required>
                            <option value="">Оберіть категорію...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category_id'])): ?>
                            <div class="invalid-feedback"><?= $errors['category_id'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="base_price" class="form-label">Ціна (грн) *</label>
                            <input type="number" step="0.01" class="form-control <?= isset($errors['base_price']) ? 'is-invalid' : '' ?>" id="base_price" name="base_price" required value="<?= htmlspecialchars($_POST['base_price'] ?? '') ?>" placeholder="0.00">
                            <?php if (isset($errors['base_price'])): ?>
                                <div class="invalid-feedback"><?= $errors['base_price'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="duration_minutes" class="form-label">Орієнтовний час (хв)</label>
                            <input type="number" class="form-control <?= isset($errors['duration_minutes']) ? 'is-invalid' : '' ?>" id="duration_minutes" name="duration_minutes" value="<?= htmlspecialchars($_POST['duration_minutes'] ?? '') ?>" placeholder="60">
                            <?php if (isset($errors['duration_minutes'])): ?>
                                <div class="invalid-feedback"><?= $errors['duration_minutes'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>



                    <div class="mb-3">
                        <label class="form-label">Кастомні доповнення</label>
                        <div id="custom-options-container">
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
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-info mt-2" id="add-custom-option">
                            <i class="bi bi-plus-lg me-1"></i>Додати поле
                        </button>
                        <div class="form-text mt-1">Додайте специфічні доповнення, яких немає в загальному списку.</div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Опис</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Детальний опис послуги..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg me-2"></i>Зберегти послугу
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let optionIndex = 1;
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
    });
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>