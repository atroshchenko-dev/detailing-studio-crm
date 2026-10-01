<?php

/**
 * DetalStudio - API: Get Cars by Client
 * Отримати автомобілі клієнта
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

Auth::requireAuth();

$clientId = $_GET['client_id'] ?? 0;

if (!$clientId) {
    http_response_code(400);
    echo json_encode(['error' => 'Client ID is required']);
    exit;
}

try {
    $cars = Car::getByClient($clientId);
    echo json_encode($cars);
} catch (Exception $e) {
    error_log("get_cars API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
