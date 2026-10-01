<?php
/**
 * DetalStudio - Add Employee Payment
 */

require_once __DIR__ . '/../config/config.php';
use App\Payments;
use App\Auth;
use App\Flash;
use App\Audit;

// Check access - only director
if (!Auth::isAdmin()) {
    Flash::set('У вас немає прав для доступу до цього розділу', 'danger');
    redirect('/');
}

$pageTitle = 'Додати виплату';
$employees = Auth::getAllUsers();
$types = Payments::getTypes();
$methods = Payments::getMethods();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'user_id' => $_POST['user_id'],
        'amount' => $_POST['amount'],
        'payment_type' => $_POST['payment_type'],
        'payment_method' => $_POST['payment_method'],
        'payment_date' => $_POST['payment_date'],
        'notes' => $_POST['notes']
    ];
    
    // Validation
    $errors = [];
    if (empty($data['user_id'])) $errors[] = "Виберіть співробітника";
    if (empty($data['amount']) || $data['amount'] <= 0) $errors[] = "Вкажіть коректну суму";
    if (empty($data['payment_date'])) $errors[] = "Вкажіть дату виплати";
    
    if (empty($errors)) {
        if (Payments::create($data)) {
            Audit::log('payment_created', 'employee_payments', db()->lastInsertId(), null, $data);
            Flash::set('Виплату успішно додано', 'success');
            redirect('/payments/');
        } else {
            Flash::set('Помилка при збереженні виплати', 'danger');
        }
    } else {
        Flash::set(implode('<br>', $errors), 'danger');
    }
}

include __DIR__ . '/../views/layout/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/payments/">Виплати</a></li>
                    <li class="breadcrumb-item active">Додати виплату</li>
                </ol>
            </nav>
            <h2 class="text-accent-orange">НОВА ВИПЛАТА</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Співробітник</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Виберіть співробітника...</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= (isset($_POST['user_id']) && $_POST['user_id'] == $emp['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['full_name']) ?> (<?= Auth::getRoleLabel($emp['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Сума</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" value="<?= $_POST['amount'] ?? '' ?>" required>
                                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Дата виплати</label>
                                <input type="date" name="payment_date" class="form-control" value="<?= $_POST['payment_date'] ?? date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Тип виплати</label>
                                <select name="payment_type" class="form-select" required>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= (isset($_POST['payment_type']) && $_POST['payment_type'] == $key) ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Спосіб оплати</label>
                                <select name="payment_method" class="form-select" required>
                                    <?php foreach ($methods as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == $key) ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Примітка</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>

                        <hr class="my-4 border-secondary">

                        <div class="d-flex justify-content-between">
                            <a href="/payments/" class="btn btn-outline-secondary">Скасувати</a>
                            <button type="submit" class="btn btn-primary px-5">Зберегти</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle me-2"></i>Довідка</h5>
                <p>У цьому розділі ви можете фіксувати будь-які грошові виплати співробітникам:</p>
                <ul>
                    <li><strong>Зарплата</strong> - планова виплата за місяць/період.</li>
                    <li><strong>Аванс</strong> - часткова виплата в рахунок майбутньої зарплати.</li>
                    <li><strong>Бонус</strong> - премії за гарну роботу або великі об'єми.</li>
                </ul>
                <p class="mb-0">Всі виплати фіксуються в системі аудиту для контролю фінансів.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
