<?php

/**
 * API - Get Car Makes
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();

header('Content-Type: application/json');

try {
    $makes = db()->query("SELECT id, name FROM car_makes ORDER BY name ASC")->fetchAll();
    echo json_encode($makes);
} catch (Exception $e) {
    error_log("get_makes API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
