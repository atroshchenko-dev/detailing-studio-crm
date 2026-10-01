<?php
/**
 * DetalStudio - Add Expense
 */

require_once __DIR__ . '/../config/config.php';


Auth::requireAuth();
Auth::requireAnyRole(['director']);

$pageTitle = 'Додати витрату';
$categories = Expense::getCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $data = [
        'category' => $_POST['category'],
        'amount' => floatval($_POST['amount']),
        'payment_method' => $_POST['payment_method'],
        'payment_date' => $_POST['payment_date'],
        'notes' => $_POST['notes']
    ];
    
    if (Expense::create($data)) {
        Flash::success('Витрату успішно додано');
        redirect('/expenses/');
    } else {
        Flash::error('Помилка при створенні запису');
    }
}

include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-info border-top border-4">
            <div class="card-header bg-dark">
                <h5 class="mb-0 text-accent-cyan">
                    <i class="bi bi-plus-lg me-2"></i>ДОДАТИ ВИТРАТУ
                </h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?= CSRF::input() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Категорія</label>
                        <select name="category" class="form-select" required>
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Сума (грн)</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Дата платежу</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Спосіб оплати</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">Готівка</option>
                            <option value="card">Карта</option>
                            <option value="fop">ФОП</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Примітка</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Додати запис
                        </button>
                        <a href="/expenses/" class="btn btn-outline-secondary">Скасувати</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
