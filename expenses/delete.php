<?php
/**
 * DetalStudio - Delete Expense
 */

require_once __DIR__ . '/../config/config.php';


Auth::requireAuth();
Auth::requireAnyRole(['director']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id) {
    if (Expense::delete($id)) {
        Flash::success('Витрату видалено');
    } else {
        Flash::error('Помилка при видаленні');
    }
}

redirect('/expenses/');
