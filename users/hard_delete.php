<?php
/**
 * DetalStudio - Hard Delete User
 * Повне видалення користувача
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
        Flash::error('Ви не можете видалити самі себе');
        redirect('/users/');
    }

    try {
        // Check dependencies
        // 1. Check if user is creator of any tasks
        $stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE creator_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            Flash::error('Неможливо видалити: користувач створив задачі. Спочатку видаліть їх або перепризначте.');
            redirect('/users/');
        }

        // 2. Check if user is executor of any tasks (legacy check)
        $stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE executor_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            Flash::error('Неможливо видалити: користувач є виконавцем задач. Перепризначте задачі.');
            redirect('/users/');
        }

        // 3. Check if user is in task_executors
        $stmt = db()->prepare("SELECT COUNT(*) FROM task_executors WHERE user_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            Flash::error('Неможливо видалити: користувач є виконавцем задач (multi). Перепризначте задачі.');
            redirect('/users/');
        }

        // 4. Check if user has executed orders (is in order history or assigned?)
        // Assuming simple check for now: just tasks and maybe creation logs? 
        // We will perform hard delete.

        db()->beginTransaction();

        // Remove from task_executors (should be 0 by now due to check, but just in case of race)
        db()->prepare("DELETE FROM task_executors WHERE user_id = ?")->execute([$id]);
        
        // Remove user
        $stmt = db()->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        db()->commit();
        Flash::success('Користувача повністю видалено');
        
    } catch (Exception $e) {
        db()->rollBack();
        Flash::error('Помилка при видаленні: ' . $e->getMessage());
    }
}

redirect('/users/');
