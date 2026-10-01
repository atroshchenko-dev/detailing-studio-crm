<?php

/**
 * DetalStudio - Calendar Events API
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();

header('Content-Type: application/json');

try {
    $start = $_GET['start'] ?? date('Y-m-d');
    $end = $_GET['end'] ?? date('Y-m-d', strtotime('+30 days'));

    // Fetch Tasks
    $tasks = \App\Models\Task::getForCalendar($start, $end);

    // Fetch Orders with deadlines
    // We need a method in Order model for this. Let's add it or use getList with date filters
    // Order::getList filters by created_at usually. Let's add specific logic here or update Order model later.
    // For now, let's use a direct query for efficiency or add Order::getForCalendar.
    // Since we didn't add Order::getForCalendar in the plan, I'll add a quick query here or reuse getList if possible (but getList filters by creation date).
    // Let's add a direct query for orders here for now to save time, or better, add Order::getForCalendar method.
    // Actually, I should probably add Order::getForCalendar to be consistent. 
    // BUT, for now, let's just query orders directly.

    $where = "(deadline BETWEEN ? AND ? OR planned_start BETWEEN ? AND ?) AND is_deleted = 0";
    $params = [$start, $end, $start, $end];

    if (Auth::isExecutor()) {
        // Executors see orders they are assigned to via tasks? Or maybe they don't see orders directly unless assigned.
        // Let's assume executor sees orders where they are manager or have a task.
        // For simplicity, let's show orders if they are manager.
        $where .= " AND (manager_id = ? OR EXISTS (SELECT 1 FROM tasks t WHERE t.order_id = orders.id AND EXISTS (SELECT 1 FROM task_executors te WHERE te.task_id = t.id AND te.user_id = ?)))";
        $params[] = Auth::getUserId();
        $params[] = Auth::getUserId();
    }

    $stmt = db()->prepare("SELECT * FROM orders WHERE $where");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $events = [];

    // Process Tasks
    foreach ($tasks as $task) {
        $events[] = [
            'id' => 'task_' . $task['id'],
            'title' => '📋 ' . $task['title'],
            'start' => date('Y-m-d', strtotime($task['deadline'])), // All Day
            'allDay' => true,
            'url' => '/tasks/view.php?id=' . $task['id'], // Fix URL to view.php
            'backgroundColor' => get_status_color($task['status']),
            'borderColor' => get_status_color($task['status']),
            'extendedProps' => [
                'type' => 'task',
                'description' => $task['description'],
                'status' => \App\Models\Task::getStatusName($task['status'])
            ]
        ];
    }

    // Process Orders
    foreach ($orders as $order) {
        // Event 1: Arrival
        if (!empty($order['planned_start'])) {
            $events[] = [
                'id' => 'order_start_' . $order['id'],
                'title' => '🚗 Прибуття #' . $order['order_number'],
                'start' => $order['planned_start'],
                'url' => '/orders/view.php?id=' . $order['id'],
                'backgroundColor' => '#17a2b8', // Info Blue
                'borderColor' => '#17a2b8',
                'extendedProps' => [
                    'type' => 'order',
                    'subtype' => 'arrival',
                    'status' => \App\Models\Order::getStatusName($order['status'])
                ]
            ];
        }

        // Event 2: Completion
        if (!empty($order['deadline'])) {
            $events[] = [
                'id' => 'order_end_' . $order['id'],
                'title' => '🏁 Видача #' . $order['order_number'],
                'start' => $order['deadline'],
                'url' => '/orders/view.php?id=' . $order['id'],
                'backgroundColor' => '#6610f2', // Purple
                'borderColor' => '#6610f2',
                'extendedProps' => [
                    'type' => 'order',
                    'subtype' => 'deadline',
                    'status' => \App\Models\Order::getStatusName($order['status'])
                ]
            ];
        }
    }

    echo json_encode($events);
} catch (Exception $e) {
    error_log("calendar_events API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}

function get_status_color($status)
{
    switch ($status) {
        case 'completed':
            return '#198754';
        case 'in_progress':
            return '#ffc107';
        case 'new':
            return '#0dcaf0';
        default:
            return '#6c757d';
    }
}
