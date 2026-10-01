<?php

/**
 * API: Get Order Photos
 * Returns all photos for an order
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

try {
    $stmt = db()->prepare("
        SELECT 
            p.*,
            u.full_name as uploader_name
        FROM order_photos p
        LEFT JOIN users u ON p.uploaded_by = u.id
        WHERE p.order_id = ?
        ORDER BY p.photo_type, p.created_at DESC
    ");

    $stmt->execute([$orderId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by type
    $grouped = [
        'before' => [],
        'after' => [],
        'process' => []
    ];

    foreach ($photos as $photo) {
        $photo['file_url'] = $photo['file_path']; // Already relative path
        $grouped[$photo['photo_type']][] = $photo;
    }

    echo json_encode($grouped);
} catch (Exception $e) {
    error_log("get_order_photos API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
