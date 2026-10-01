<?php
/**
 * DetalStudio - Delete Purchase Request
 * Видалення заявки на закупівлю
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $id = $_POST['id'] ?? null;
    $request = PurchaseRequest::getById($id);
    
    if (!$request) {
        die("Заявку не знайдено");
    }
    
    // Only pending requests or received requests (by director) can be deleted
    $canDelete = false;
    if ($request['status'] === 'pending' && (Auth::isDirector() || $request['requested_by'] == Auth::getUserId())) {
        $canDelete = true;
    } elseif ($request['status'] === 'received' && Auth::isDirector()) {
        $canDelete = true;
    }

    if ($canDelete) {
        $result = PurchaseRequest::delete($id);
        if ($result === true) {
            header("Location: /purchases/?deleted=1");
            exit;
        } else {
            die("Помилка при видаленні: " . $result);
        }
    } else {
        die("Ви не можете видалити цю заявку (вона вже в роботі або у вас недостатньо прав)");
    }
} else {
    header("Location: /purchases/");
    exit;
}
