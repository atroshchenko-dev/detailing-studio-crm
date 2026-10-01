<?php
/**
 * DetalStudio - Delete Warehouse Item
 * Видалення товару
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    redirect('/warehouse/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $result = WarehouseItem::delete($id);

    if ($result === true) {
        Flash::success('Товар видалено');
    } else {
        $error = is_string($result) ? $result : 'Помилка при видаленні товару';
        Flash::error($error);
    }
}

redirect('/warehouse/');
