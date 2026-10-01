<?php
/**
 * DetalStudio - Delete (Deactivate) User
 * Деактивація користувача
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireRole('director');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    redirect('/users/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();

    if ($id == Auth::getUserId()) {
        Flash::error('Ви не можете деактивувати самі себе');
        redirect('/users/');
    }

    try {
        $stmt = db()->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        if ($stmt->execute([$id])) {
            Flash::success('Користувача деактивовано');
        } else {
            Flash::error('Помилка при деактивації користувача');
        }
    } catch (Exception $e) {
        Flash::error('Помилка бази даних: ' . $e->getMessage());
    }
}

redirect('/users/');
