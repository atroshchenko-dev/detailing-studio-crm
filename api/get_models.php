<?php

/**
 * API - Get Car Models
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();

header('Content-Type: application/json');

$makeId = $_GET['make_id'] ?? null;

if (!$makeId) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = db()->prepare("SELECT id, name FROM car_models WHERE make_id = ? ORDER BY name ASC");
    $stmt->execute([$makeId]);
    $models = $stmt->fetchAll();
    echo json_encode($models);
} catch (Exception $e) {
    error_log("get_models API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
