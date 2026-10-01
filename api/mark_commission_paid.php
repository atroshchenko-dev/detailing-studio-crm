<?php

/**
 * API: Mark Commission Paid
 */
require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['director']);
\App\Csrf::verifyApi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
if (!$taskId) {
    echo json_encode(['error' => 'Invalid task ID']);
    exit;
}

try {
    $stmt = db()->prepare("UPDATE tasks SET is_commission_paid = 1 WHERE id = ?");
    $result = $stmt->execute([$taskId]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to update commission status']);
    }
} catch (Exception $e) {
    error_log("mark_commission_paid API error: " . $e->getMessage());
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
