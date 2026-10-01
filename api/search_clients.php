<?php

/**
 * API: Search Clients
 * Returns clients matching a search query (name or phone)
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Models\Client;

header('Content-Type: application/json; charset=utf-8');

// Require authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');

try {
    if (strlen($query) < 1) {
        // Return latest active clients if no query
        $stmt = db()->prepare("
            SELECT id, name, phone, email 
            FROM clients 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT id, name, phone, email 
            FROM clients 
            WHERE (name LIKE ? OR phone LIKE ?)
            ORDER BY name
            LIMIT 15
        ");
        $search = "%{$query}%";
        $stmt->execute([$search, $search]);
    }
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mask phone for non-manager roles
    if (!Auth::hasAnyRole(['director', 'manager'])) {
        foreach ($clients as &$c) {
            $c['phone'] = \App\Utils\Helper::maskPhone($c['phone']);
        }
        unset($c);
    }

    echo json_encode($clients);
} catch (Exception $e) {
    error_log("search_clients API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
