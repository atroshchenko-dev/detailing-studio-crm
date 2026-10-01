<?php
require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();

header('Content-Type: application/json');

$clientId = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT);
if (!$clientId) {
    echo json_encode(['error' => 'Invalid client ID']);
    exit;
}

$discount = Client::getLoyaltyDiscount($clientId);
echo json_encode(['discount' => $discount]);
