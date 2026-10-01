<?php
/**
 * DetalStudio - Delete Client
 * Видалення клієнта
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    redirect('/clients/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $result = Client::delete($id);

    if ($result === true) {
        Flash::success('Клієнта видалено');
    } else {
        $error = is_string($result) ? $result : 'Помилка при видаленні клієнта';
        Flash::error($error);
    }
}

redirect('/clients/');
