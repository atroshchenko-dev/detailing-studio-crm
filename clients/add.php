<?php

/**
 * DetalStudio - Add Client
 * Додавання клієнта
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();

    $validator = new Validator($_POST);
    $validator->required('full_name', 'ПІБ Клієнта')
        ->required('phone', 'Телефон')
        ->min('full_name', 3, 'ПІБ Клієнта')
        ->numeric('phone', 'Телефон');

    // Validate car if checkbox is checked
    if (isset($_POST['add_car'])) {
        if (($_POST['car_make'] ?? '') === 'other') {
            $validator->required('car_make_custom', 'Марка авто (ручна)')
                ->required('car_model_custom', 'Модель авто (ручна)');
        } else {
            $validator->required('car_make', 'Марка авто')
                ->required('car_model', 'Модель авто');
        }
    }

    if ($validator->isValid()) {
        $data = [
            'name' => trim($_POST['full_name']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ];

        // Start transaction if possible (our DB class might not support explicit transactions easily, so we do procedural)
        $clientId = Client::create($data);

        if ($clientId) {
            $successMessage = 'Клієнта успішно створено';

            // Create car if requested
            if (isset($_POST['add_car'])) {
                $notes = '';
                if (isset($_POST['car_restyling']) && $_POST['car_restyling'] == '1') {
                    $notes = "Restyling";
                }

                // Resolve make name
                $makeName = '';
                if ($_POST['car_make'] === 'other') {
                    $makeName = trim($_POST['car_make_custom']);
                } else {
                    $db = Database::getInstance();
                    $make = $db->query("SELECT name FROM car_makes WHERE id = ?", [$_POST['car_make']])->fetch();
                    $makeName = $make['name'] ?? '';
                }

                // Resolve model name
                $modelName = '';
                if ($_POST['car_model'] === 'other' || $_POST['car_make'] === 'other') {
                    $modelName = trim($_POST['car_model_custom']);
                } else {
                    $modelName = trim($_POST['car_model']);
                }

                $carData = [
                    'client_id' => $clientId,
                    'make' => $makeName,
                    'model' => $modelName,
                    'year' => !empty($_POST['car_year']) ? (int)$_POST['car_year'] : null,
                    'license_plate' => trim($_POST['car_plate'] ?? ''),
                    'vin' => trim($_POST['car_vin'] ?? ''),
                    'notes' => $notes
                ];

                if (Car::create($carData)) {
                    $successMessage .= ' та додано автомобіль';
                } else {
                    $successMessage .= ', але виникла помилка при додаванні автомобіля';
                }
            }

            Flash::success($successMessage);
            redirect('/clients/');
        } else {
            Flash::error('Помилка при створенні клієнта');
        }
    } else {
        Flash::error('Будь ласка, виправте помилки');
        $errors = $validator->getErrors();
    }
}

$pageTitle = 'Додати клієнта';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-10 col-xl-9">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-person-plus me-2"></i>Додати клієнта
                    </h5>
                    <a href="/clients/" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Назад
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?= CSRF::input() ?>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3 border-bottom pb-2">Дані клієнта</h6>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">ПІБ Клієнта *</label>
                                <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                                <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Телефон *</label>
                                <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?= $errors['phone'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Примітки</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-6 border-start">
                            <h6 class="mb-3 border-bottom pb-2">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input" type="checkbox" id="addCarSwitch" name="add_car" <?= isset($_POST['add_car']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="addCarSwitch">Додати автомобіль</label>
                                </div>
                            </h6>

                            <div id="carFields" style="display: <?= isset($_POST['add_car']) ? 'block' : 'none' ?>;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Марка *</label>
                                        <select name="car_make" id="carMakeSelect" class="form-select">
                                            <option value="">Оберіть марку</option>
                                            <!-- Will be loaded via JS -->
                                        </select>
                                        <input type="text" name="car_make_custom" id="carMakeCustom" class="form-control mt-2" placeholder="Введіть марку вручну" style="display: none;">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Модель *</label>
                                        <select name="car_model" id="carModelSelect" class="form-select" disabled>
                                            <option value="">Спочатку оберіть марку</option>
                                        </select>
                                        <input type="text" name="car_model_custom" id="carModelCustom" class="form-control mt-2" placeholder="Введіть модель вручну" style="display: none;">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Рік</label>
                                        <select name="car_year" class="form-select">
                                            <option value="">Оберіть рік</option>
                                            <?php for ($y = date('Y') + 1; $y >= 1950; $y--): ?>
                                                <option value="<?= $y ?>" <?= (isset($_POST['car_year']) && $_POST['car_year'] == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Держ. номер</label>
                                        <input type="text" name="car_plate" class="form-control" placeholder="AA1234AA" value="<?= htmlspecialchars($_POST['car_plate'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="car_restyling" value="1" id="restylingCheck" <?= isset($_POST['car_restyling']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="restylingCheck">
                                                Рестайлінг
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">VIN</label>
                                    <input type="text" name="car_vin" class="form-control" value="<?= htmlspecialchars($_POST['car_vin'] ?? '') ?>">
                                </div>
                            </div>

                            <div id="noCarMessage" class="text-muted py-5 text-center" style="display: <?= isset($_POST['add_car']) ? 'none' : 'block' ?>;">
                                <i class="bi bi-car-front display-6 d-block mb-2"></i>
                                Автомобіль можна буде додати пізніше
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Зберегти клієнта
                        </button>
                    </div>
                </form>

                <script src="/assets/js/clients.js"></script>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>