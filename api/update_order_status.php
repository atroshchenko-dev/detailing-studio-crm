<?php

/**
 * API Endpoint: Update Order Status
 * Used for Kanban drag-and-drop updates
 */

require_once __DIR__ . '/../config/config.php';

// Ensure JSON response
header('Content-Type: application/json');

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
\App\Csrf::verifyApi();

// Only allow Managers and Directors
if (!Auth::hasRole('manager') && !Auth::hasRole('director')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get parameters
$orderId = $_POST['order_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$orderId || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate status
$validStatuses = ['new', 'approved', 'in_progress', 'waiting_client', 'waiting_parts', 'ready', 'completed', 'closed', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Use OrderService for proper validation (e.g., debt check on close)
    $orderService = new \App\Services\OrderService();
    $result = $orderService->updateStatus((int)$orderId, $status);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} catch (Exception $e) {
    // Log error using our new Monolog logger
    App\Logger::error("Failed to update order status via API", [
        'order_id' => $orderId,
        'status' => $status,
        'error' => $e->getMessage()
    ]);

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Внутрішня помилка сервера']);
}
