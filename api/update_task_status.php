<?php

/**
 * DetalStudio - API: Update Task Status
 * Оновити статус задачі
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
\App\Csrf::verifyApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $taskId = $_POST['task_id'] ?? 0;
    $newStatus = $_POST['status'] ?? '';

    if (!$taskId || !$newStatus) {
        throw new Exception('Missing required parameters');
    }

    $validStatuses = ['created', 'in_progress', 'completed', 'closed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses, true)) {
        throw new Exception('Invalid status');
    }

    $task = Task::getById($taskId);
    if (!$task) {
        throw new Exception('Task not found');
    }

    // Check permissions
    if (Auth::isExecutor() && $task['executor_id'] != Auth::getUserId()) {
        throw new Exception('Permission denied');
    }

    $result = Task::update($taskId, ['status' => $newStatus]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update task');
    }
} catch (Exception $e) {
    error_log("update_task_status API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
