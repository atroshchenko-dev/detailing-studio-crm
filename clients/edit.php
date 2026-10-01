<?php

/**
 * DetalStudio - Edit Client
 * Редагування клієнта
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('/clients/');
}

$client = Client::getById($id);
if (!$client) {
    Flash::error('Клієнта не знайдено');
    redirect('/clients/');
}

$activeTab = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_client':
                $validator = new Validator($_POST);
                $validator->required('full_name', 'ПІБ Клієнта')
                    ->required('phone', 'Телефон')
                    ->min('full_name', 3, 'ПІБ Клієнта')
                    ->numeric('phone', 'Телефон');

                if ($validator->isValid()) {
                    $data = [
                        'name' => trim($_POST['full_name']),
                        'phone' => trim($_POST['phone']),
                        'email' => trim($_POST['email'] ?? ''),
                        'notes' => trim($_POST['notes'] ?? '')
                    ];

                    if (Client::update($id, $data)) {
                        Flash::success('Дані клієнта оновлено');
                        $client = array_merge($client, $data);
                        // Map name back to full_name for display if needed, though we use name in DB
                        $client['full_name'] = $data['name'];
                    } else {
                        Flash::error('Помилка при оновленні клієнта');
                    }
                } else {
                    Flash::error('Будь ласка, виправте помилки');
                    $errors = $validator->getErrors();
                    $client = array_merge($client, $_POST);
                }
                break;

            case 'add_car':
                $activeTab = 'cars';
                $validator = new Validator($_POST);

                // Validate car inputs
                if (($_POST['make'] ?? '') === 'other') {
                    $validator->required('make_custom', 'Марка авто (ручна)')
                        ->required('model_custom', 'Модель авто (ручна)');
                } else {
                    $validator->required('make', 'Марка авто')
                        ->required('model', 'Модель авто');
                }

                if ($validator->isValid()) {
                    // Resolve make name
                    $makeName = '';
                    if ($_POST['make'] === 'other') {
                        $makeName = trim($_POST['make_custom']);
                    } else {
                        $stmt = db()->prepare("SELECT name FROM car_makes WHERE id = ?");
                        $stmt->execute([$_POST['make']]);
                        $make = $stmt->fetch();
                        $makeName = $make['name'] ?? '';
                    }

                    // Resolve model name
                    $modelName = '';
                    if (($_POST['model'] ?? '') === 'other' || ($_POST['make'] ?? '') === 'other') {
                        $modelName = trim($_POST['model_custom']);
                    } else {
                        $modelName = trim($_POST['model']);
                    }

                    $carData = [
                        'client_id' => $id,
                        'make' => $makeName,
                        'model' => $modelName,
                        'year' => !empty($_POST['year']) ? (int)$_POST['year'] : null,
                        'license_plate' => trim($_POST['license_plate'] ?? ''),
                        'vin' => trim($_POST['vin'] ?? ''),
                        'notes' => trim($_POST['notes'] ?? '')
                    ];

                    if (Car::create($carData)) {
                        Flash::success('Автомобіль додано');
                        redirect("/clients/edit.php?id=$id&tab=cars");
                    } else {
                        Flash::error('Помилка при додаванні автомобіля');
                    }
                } else {
                    Flash::error('Будь ласка, заповніть обов\'язкові поля авто');
                }
                break;

            case 'update_car':
                $activeTab = 'cars';
                $validator = new Validator($_POST);
                $validator->required('car_id', 'ID Авто');

                // Validate car inputs
                if (($_POST['make'] ?? '') === 'other') {
                    $validator->required('make_custom', 'Марка авто (ручна)')
                        ->required('model_custom', 'Модель авто (ручна)');
                } else {
                    $validator->required('make', 'Марка авто')
                        ->required('model', 'Модель авто');
                }

                if ($validator->isValid()) {
                    $carId = (int)$_POST['car_id'];
                    // Verify car belongs to client
                    $car = Car::getById($carId);
                    if ($car && $car['client_id'] == $id) {

                        // Resolve make name
                        $makeName = '';
                        if ($_POST['make'] === 'other') {
                            $makeName = trim($_POST['make_custom']);
                        } else {
                            $stmt = db()->prepare("SELECT name FROM car_makes WHERE id = ?");
                            $stmt->execute([$_POST['make']]);
                            $make = $stmt->fetch();
                            $makeName = $make['name'] ?? '';
                        }

                        // Resolve model name
                        $modelName = '';
                        if (($_POST['model'] ?? '') === 'other' || ($_POST['make'] ?? '') === 'other') {
                            $modelName = trim($_POST['model_custom']);
                        } else {
                            $modelName = trim($_POST['model']);
                        }

                        $carData = [
                            'make' => $makeName,
                            'model' => $modelName,
                            'year' => !empty($_POST['year']) ? (int)$_POST['year'] : null,
                            'license_plate' => trim($_POST['license_plate'] ?? ''),
                            'vin' => trim($_POST['vin'] ?? ''),
                            'notes' => trim($_POST['notes'] ?? '')
                        ];

                        if (Car::update($carId, $carData)) {
                            Flash::success('Дані автомобіля оновлено');
                            redirect("/clients/edit.php?id=$id&tab=cars");
                        } else {
                            Flash::error('Помилка при оновленні автомобіля');
                        }
                    } else {
                        Flash::error('Автомобіль не знайдено або він не належить цьому клієнту');
                    }
                } else {
                    Flash::error('Будь ласка, заповніть обов\'язкові поля авто');
                }
                break;

            case 'delete_car':
                $activeTab = 'cars';
                $carId = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);

                if ($carId) {
                    // Verify car belongs to client
                    $car = Car::getById($carId);
                    if ($car && $car['client_id'] == $id) {
                        if (Car::delete($carId)) {
                            Flash::success('Автомобіль видалено');
                            redirect("/clients/edit.php?id=$id&tab=cars");
                        } else {
                            Flash::error('Помилка при видаленні автомобіля');
                        }
                    } else {
                        Flash::error('Автомобіль не знайдено або він не належить цьому клієнту');
                    }
                }
                break;
        }
    }
}

// Get client cars
$cars = Car::getByClient($id);

// Check url tab
if (isset($_GET['tab']) && $_GET['tab'] == 'cars') {
    $activeTab = 'cars';
}

$pageTitle = 'Редагувати клієнта';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil me-2"></i>Редагувати клієнта
                    </h5>
                    <a href="/clients/" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Назад
                    </a>
                </div>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-4" id="clientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab == 'info' ? 'active' : '' ?>" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">Основна інформація</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab == 'cars' ? 'active' : '' ?>" id="cars-tab" data-bs-toggle="tab" data-bs-target="#cars" type="button" role="tab">Автомобілі</button>
                    </li>
                </ul>

                <div class="tab-content" id="clientTabsContent">
                    <!-- Info Tab -->
                    <div class="tab-pane fade <?= $activeTab == 'info' ? 'show active' : '' ?>" id="info" role="tabpanel">
                        <form action="" method="POST">
                            <?= CSRF::input() ?>
                            <input type="hidden" name="action" value="update_client">

                            <div class="mb-3">
                                <label for="full_name" class="form-label">ПІБ Клієнта *</label>
                                <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name" required value="<?= htmlspecialchars($client['name'] ?? $client['full_name'] ?? '') ?>">
                                <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['full_name'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Телефон *</label>
                                    <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" required value="<?= htmlspecialchars($client['phone']) ?>">
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?= $errors['phone'] ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Примітки</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-check-lg me-2"></i>Зберегти зміни
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Cars Tab -->
                    <div class="tab-pane fade <?= $activeTab == 'cars' ? 'show active' : '' ?>" id="cars" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Список автомобілів</h6>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCarModal">
                                <i class="bi bi-plus-lg me-1"></i>Додати авто
                            </button>
                        </div>

                        <?php if (empty($cars)): ?>
                            <div class="text-center py-4 text-muted bg-dark-accent rounded">
                                <i class="bi bi-car-front display-6 mb-2"></i>
                                <p class="mb-0">У клієнта поки немає автомобілів</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($cars as $car): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($car['make']) ?> <?= htmlspecialchars($car['model']) ?>
                                                    <?php if ($car['year']): ?>
                                                        <span class="text-muted small">(<?= $car['year'] ?>)</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <div class="small text-muted">
                                                    <?php if ($car['license_plate']): ?>
                                                        <span class="me-2"><i class="bi bi-123 me-1"></i><?= htmlspecialchars($car['license_plate']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($car['vin']): ?>
                                                        <span><i class="bi bi-qr-code me-1"></i><?= htmlspecialchars($car['vin']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="editCar(<?= htmlspecialchars(json_encode($car)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Ви впевнені, що хочете видалити це авто?');">
                                                <?= CSRF::input() ?>
                                                <input type="hidden" name="action" value="delete_car">
                                                <input type="hidden" name="car_id" value="<?= $car['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add Car Modal -->
<div class="modal fade" id="addCarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Додати автомобіль</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="add_car">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Марка *</label>
                            <select name="make" id="add_make" class="form-select" required>
                                <option value="">Оберіть марку</option>
                            </select>
                            <input type="text" name="make_custom" id="add_make_custom" class="form-control mt-2" placeholder="Введіть марку вручну" style="display: none;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Модель *</label>
                            <select name="model" id="add_model" class="form-select" disabled required>
                                <option value="">Спочатку оберіть марку</option>
                            </select>
                            <input type="text" name="model_custom" id="add_model_custom" class="form-control mt-2" placeholder="Введіть модель вручну" style="display: none;">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Рік</label>
                            <input type="number" name="year" class="form-control" min="1900" max="<?= date('Y') + 1 ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Держ. номер</label>
                            <input type="text" name="license_plate" class="form-control" placeholder="AA1234AA">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">VIN код</label>
                        <input type="text" name="vin" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Додати авто</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Car Modal -->
<div class="modal fade" id="editCarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редагувати автомобіль</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="editCarForm">
                <div class="modal-body">
                    <?= CSRF::input() ?>
                    <input type="hidden" name="action" value="update_car">
                    <input type="hidden" name="car_id" id="edit_car_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Марка *</label>
                            <select name="make" id="edit_make" class="form-select" required>
                                <option value="">Оберіть марку</option>
                            </select>
                            <input type="text" name="make_custom" id="edit_make_custom" class="form-control mt-2" placeholder="Введіть марку вручну" style="display: none;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Модель *</label>
                            <select name="model" id="edit_model" class="form-select" disabled required>
                                <option value="">Спочатку оберіть марку</option>
                            </select>
                            <input type="text" name="model_custom" id="edit_model_custom" class="form-control mt-2" placeholder="Введіть модель вручну" style="display: none;">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Рік</label>
                            <input type="number" name="year" id="edit_year" class="form-control" min="1900" max="<?= date('Y') + 1 ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Держ. номер</label>
                            <input type="text" name="license_plate" id="edit_license_plate" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">VIN код</label>
                        <input type="text" name="vin" id="edit_vin" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти зміни</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Shared functionality for both Add and Edit modals
        setupCarDropdowns('add');
        setupCarDropdowns('edit');
    });

    function setupCarDropdowns(prefix) {
        const makeSelect = document.getElementById(prefix + '_make');
        const modelSelect = document.getElementById(prefix + '_model');
        const makeCustom = document.getElementById(prefix + '_make_custom');
        const modelCustom = document.getElementById(prefix + '_model_custom');

        // Load makes
        fetch('/api/get_makes.php')
            .then(r => r.json())
            .then(makes => {
                // Keep "Choose make" option
                while (makeSelect.options.length > 1) {
                    makeSelect.remove(1);
                }

                makes.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.name;
                    opt.dataset.name = m.name; // Store name for matching
                    makeSelect.appendChild(opt);
                });
                const otherOpt = document.createElement('option');
                otherOpt.value = 'other';
                otherOpt.textContent = '-- Інше / Вручну --';
                makeSelect.appendChild(otherOpt);
            });

        makeSelect.addEventListener('change', function() {
            const makeId = this.value;
            modelSelect.innerHTML = '<option value="">Оберіть модель</option>';

            if (makeId === 'other') {
                makeCustom.style.display = 'block';
                makeCustom.setAttribute('required', '');
                modelCustom.style.display = 'block';
                modelCustom.setAttribute('required', '');
                modelSelect.style.display = 'none';
                modelSelect.removeAttribute('required');
            } else if (makeId) {
                makeCustom.style.display = 'none';
                makeCustom.removeAttribute('required');
                modelSelect.style.display = 'block';
                modelSelect.removeAttribute('disabled');
                modelSelect.setAttribute('required', '');
                modelCustom.style.display = 'none';
                modelCustom.removeAttribute('required');

                fetch('/api/get_models.php?make_id=' + makeId)
                    .then(r => r.json())
                    .then(models => {
                        models.forEach(m => {
                            const opt = document.createElement('option');
                            opt.value = m.name; // Use name directly
                            opt.textContent = m.name;
                            modelSelect.appendChild(opt);
                        });
                        const otherOpt = document.createElement('option');
                        otherOpt.value = 'other';
                        otherOpt.textContent = '-- Інша модель --';
                        modelSelect.appendChild(otherOpt);

                        // Trigger event for pre-selection if needed?
                        // Not needed here, manual selection
                    });
            } else {
                modelSelect.setAttribute('disabled', '');
                makeCustom.style.display = 'none';
                modelCustom.style.display = 'none';
            }
        });

        modelSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                modelCustom.style.display = 'block';
                modelCustom.setAttribute('required', '');
            } else {
                modelCustom.style.display = 'none';
                modelCustom.removeAttribute('required');
            }
        });
    }

    function editCar(car) {
        document.getElementById('edit_car_id').value = car.id;
        document.getElementById('edit_year').value = car.year || '';
        document.getElementById('edit_license_plate').value = car.license_plate || '';
        document.getElementById('edit_vin').value = car.vin || '';
        document.getElementById('edit_notes').value = car.notes || '';

        // Handle specific Make/Model pre-selection
        const makeSelect = document.getElementById('edit_make');
        const modelSelect = document.getElementById('edit_model');
        const makeCustom = document.getElementById('edit_make_custom');
        const modelCustom = document.getElementById('edit_model_custom');

        // Reset custom fields and dropdowns visibility
        makeCustom.style.display = 'none';
        makeCustom.removeAttribute('required');
        modelCustom.style.display = 'none';
        modelCustom.removeAttribute('required');
        modelSelect.style.display = 'block';
        modelSelect.setAttribute('disabled', ''); // Disable until make is selected/loaded
        modelSelect.innerHTML = '<option value="">Спочатку оберіть марку</option>'; // Reset models

        // Try to find make by name
        let foundMake = false;
        for (let i = 0; i < makeSelect.options.length; i++) {
            if (makeSelect.options[i].dataset.name === car.make) {
                makeSelect.selectedIndex = i;
                foundMake = true;
                break;
            }
        }

        if (foundMake) {
            const makeId = makeSelect.value;

            // Enable model select and fetch models
            modelSelect.removeAttribute('disabled');
            modelSelect.setAttribute('required', '');

            fetch('/api/get_models.php?make_id=' + makeId)
                .then(r => r.json())
                .then(models => {
                    modelSelect.innerHTML = '<option value="">Оберіть модель</option>';
                    let foundModel = false;
                    models.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.name;
                        opt.textContent = m.name;
                        if (m.name === car.model) {
                            opt.selected = true;
                            foundModel = true;
                        }
                        modelSelect.appendChild(opt);
                    });

                    const otherOpt = document.createElement('option');
                    otherOpt.value = 'other';
                    otherOpt.textContent = '-- Інша модель --';
                    modelSelect.appendChild(otherOpt);

                    if (!foundModel) {
                        // Model not found in list, select 'other' and fill custom
                        otherOpt.selected = true;
                        modelCustom.style.display = 'block';
                        modelCustom.setAttribute('required', '');
                        modelCustom.value = car.model;
                    }
                });

        } else {
            // Make not found (or custom), select 'other'
            // Find 'other' option
            for (let i = 0; i < makeSelect.options.length; i++) {
                if (makeSelect.options[i].value === 'other') {
                    makeSelect.selectedIndex = i;
                    break;
                }
            }

            // Manually trigger the change logic for 'other'
            makeCustom.style.display = 'block';
            makeCustom.setAttribute('required', '');
            modelCustom.style.display = 'block';
            modelCustom.setAttribute('required', '');
            modelSelect.style.display = 'none';
            modelSelect.removeAttribute('required');

            makeCustom.value = car.make;
            modelCustom.value = car.model;
        }

        new bootstrap.Modal(document.getElementById('editCarModal')).show();
    }
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>