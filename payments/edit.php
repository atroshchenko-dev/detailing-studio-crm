<?php
/**
 * DetalStudio - Edit Employee Payment
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

$id = $_GET['id'] ?? null;
$payment = $id ? Payments::getById($id) : null;

if (!$payment) {
    Flash::set('Виплату не знайдено', 'danger');
    redirect('/payments/');
}

$pageTitle = 'Редагувати виплату';
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
        if (Payments::update($id, $data)) {
            Audit::log('payment_updated', 'employee_payments', $id, $payment, $data);
            Flash::set('Виплату успішно оновлено', 'success');
            redirect('/payments/');
        } else {
            Flash::set('Помилка при оновленні виплати', 'danger');
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
                    <li class="breadcrumb-item active">Редагувати виплату</li>
                </ol>
            </nav>
            <h2 class="text-accent-orange">РЕДАГУВАННЯ ВИПЛАТИ</h2>
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
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $payment['user_id'] == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['full_name']) ?> (<?= Auth::getRoleLabel($emp['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Сума</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" value="<?= $payment['amount'] ?>" required>
                                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Дата виплати</label>
                                <input type="date" name="payment_date" class="form-control" value="<?= $payment['payment_date'] ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Тип виплати</label>
                                <select name="payment_type" class="form-select" required>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $payment['payment_type'] == $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Спосіб оплати</label>
                                <select name="payment_method" class="form-select" required>
                                    <?php foreach ($methods as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $payment['payment_method'] == $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Примітка</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($payment['notes'] ?? '') ?></textarea>
                        </div>

                        <hr class="my-4 border-secondary">

                        <div class="d-flex justify-content-between">
                            <a href="/payments/" class="btn btn-outline-secondary">Скасувати</a>
                            <div class="d-flex gap-2">
                                <a href="#" class="btn btn-outline-danger" onclick="return confirmDelete('Ви впевнені, що хочете видалити цей платіж?', () => window.location.href='/payments/delete.php?id=<?= $id ?>')">
                                    <i class="bi bi-trash me-2"></i>Видалити
                                </a>
                                <button type="submit" class="btn btn-primary px-5">Оновити</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
