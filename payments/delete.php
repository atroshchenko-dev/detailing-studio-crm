<?php
/**
 * DetalStudio - Delete Employee Payment
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

if ($payment) {
    if (Payments::delete($id)) {
        Audit::log('payment_deleted', 'employee_payments', $id, $payment, null);
        Flash::set('Запис про виплату видалено', 'success');
    } else {
        Flash::set('Помилка при видаленні запису', 'danger');
    }
} else {
    Flash::set('Виплату не знайдено', 'danger');
}

redirect('/payments/');
