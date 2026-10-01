<?php

/**
 * API: Get Upcoming Deadlines
 * Returns tasks with upcoming deadlines for dashboard widget
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');
    $tomorrow = date('Y-m-d 23:59:59', strtotime('+1 day'));

    $stmt = db()->prepare("
        SELECT 
            t.id,
            t.title,
            t.deadline,
            t.priority,
            t.status,
            o.order_number,
            u.full_name as executor_name,
            TIMESTAMPDIFF(HOUR, NOW(), t.deadline) as hours_left,
            COUNT(*) as task_count
        FROM tasks t
        LEFT JOIN orders o ON t.order_id = o.id
        LEFT JOIN users u ON t.executor_id = u.id
        WHERE t.is_deleted = 0 
          AND (o.is_deleted = 0 OR o.id IS NULL)
          AND t.status NOT IN ('completed', 'closed', 'cancelled')
          AND t.deadline IS NOT NULL
          AND t.deadline <= ?
        GROUP BY t.title, t.order_id, t.executor_id, t.deadline
        ORDER BY t.deadline ASC
        LIMIT 10
    ");

    $stmt->execute([$tomorrow]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add urgency level
    foreach ($tasks as &$task) {
        $hours = (int)$task['hours_left'];
        if ($hours < 0) {
            $task['urgency'] = 'overdue';
            $task['urgency_text'] = 'Прострочено';
        } elseif ($hours <= 2) {
            $task['urgency'] = 'critical';
            $task['urgency_text'] = 'Терміново';
        } elseif ($hours <= 8) {
            $task['urgency'] = 'warning';
            $task['urgency_text'] = 'Скоро';
        } else {
            $task['urgency'] = 'normal';
            $task['urgency_text'] = 'Сьогодні';
        }
    }

    echo json_encode($tasks);
} catch (Exception $e) {
    error_log("get_upcoming_deadlines API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
