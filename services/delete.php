<?php
/**
 * DetalStudio - Delete Service
 * Видалення послуги
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    redirect('/services/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $result = Service::delete($id);

    if ($result === true) {
        Flash::success('Послугу видалено');
    } else {
        $error = is_string($result) ? $result : 'Помилка при видаленні послуги';
        Flash::error($error);
    }
}

redirect('/services/');
