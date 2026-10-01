<?php
/**
 * DetalStudio - Activate User
 * Активація користувача
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

    try {
        $stmt = db()->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        if ($stmt->execute([$id])) {
            Flash::success('Користувача активовано');
        } else {
            Flash::error('Помилка при активації користувача');
        }
    } catch (Exception $e) {
        Flash::error('Помилка бази даних: ' . $e->getMessage());
    }
}

redirect('/users/');
