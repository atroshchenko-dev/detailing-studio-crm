<?php

/**
 * API Endpoint: Update Order Time & Resource
 * Use for Timeline Drag & Drop
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
\App\Csrf::verifyApi();

if (!Auth::hasAnyRole(['manager', 'director'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$start = $_POST['start'] ?? null;
$end = $_POST['end'] ?? null;
$stallId = $_POST['stall_id'] ?? null;

if (!$orderId || !$start || !$end) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $db = Database::getInstance();

    // --- CONFLICT VALIDATION ---
    // If stall_id is provided, check for overlaps. 
    // If not provided in POST, we need to fetch the CURRENT stall_id of the order to validate against the new time.
    $targetStallId = $stallId;
    if (!$targetStallId) {
        $stmtStall = $db->prepare("SELECT stall_id FROM orders WHERE id = ?");
        $stmtStall->execute([$orderId]);
        $targetStallId = $stmtStall->fetchColumn();
    }

    if ($targetStallId) {
        $stmtCheck = $db->prepare("
            SELECT COUNT(*) 
            FROM orders 
            WHERE stall_id = ? 
              AND id != ? 
              AND is_deleted = 0
              AND (
                (planned_start < ? AND planned_end > ?) OR  -- New starts inside existing
                (planned_start < ? AND planned_end > ?) OR  -- New ends inside existing
                (planned_start >= ? AND planned_end <= ?)   -- New covers existing
              )
        ");
        // Overlap formula: (StartA < EndB) AND (EndA > StartB)
        // Let's use simpler: 
        $stmtCheck = $db->prepare("
            SELECT COUNT(*) 
            FROM orders 
            WHERE stall_id = ? 
              AND id != ? 
              AND is_deleted = 0
              AND planned_start < ? 
              AND planned_end > ?
        ");
        $stmtCheck->execute([$targetStallId, $orderId, $end, $start]);
        $conflictCount = $stmtCheck->fetchColumn();

        if ($conflictCount > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Цей час у вибраному боксі вже зайнятий іншим замовленням']);
            exit;
        }
    }
    // --- END VALIDATION ---

    $query = "UPDATE orders SET planned_start = ?, planned_end = ?";
    $params = [$start, $end];

    if ($stallId) {
        $query .= ", stall_id = ?";
        $params[] = $stallId;
    }

    $query .= " WHERE id = ?";
    $params[] = $orderId;

    $stmt = $db->prepare($query);
    $result = $stmt->execute($params);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
} catch (Exception $e) {
    App\Logger::error("Failed to update order time via API", [
        'order_id' => $orderId,
        'error' => $e->getMessage()
    ]);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
}
