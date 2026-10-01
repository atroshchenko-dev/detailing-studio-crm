<?php

/**
 * API: Get Order Templates
 * Returns list of pre-defined order templates (service bundles)
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // First check if templates table exists
    $stmt = db()->query("SHOW TABLES LIKE 'order_templates'");
    if ($stmt->rowCount() === 0) {
        // Return sample templates from services if table doesn't exist
        $stmt = db()->query("
            SELECT 
                s.id,
                s.name,
                s.category,
                s.base_price,
                c.name as category_name,
                c.color_class
            FROM services s
            LEFT JOIN service_categories c ON s.category = c.id
            WHERE s.is_active = 1
            ORDER BY s.category, s.name
            LIMIT 20
        ");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by category as "templates"
        $templates = [];
        $currentCategory = null;

        foreach ($services as $s) {
            $catName = $s['category_name'] ?? 'Інше';
            if (!isset($templates[$catName])) {
                $templates[$catName] = [
                    'name' => $catName,
                    'color_class' => $s['color_class'] ?? 'bg-secondary',
                    'services' => []
                ];
            }
            $templates[$catName]['services'][] = [
                'id' => (int)$s['id'],
                'name' => $s['name'],
                'price' => (float)$s['base_price']
            ];
        }

        echo json_encode(array_values($templates));
        exit;
    }

    // If table exists, get actual templates
    $stmt = db()->query("
        SELECT t.*, 
               GROUP_CONCAT(ts.service_id) as service_ids,
               GROUP_CONCAT(s.name SEPARATOR ', ') as service_names,
               SUM(s.base_price) as total_price
        FROM order_templates t
        LEFT JOIN order_template_services ts ON t.id = ts.template_id
        LEFT JOIN services s ON ts.service_id = s.id
        WHERE t.is_active = 1
        GROUP BY t.id
        ORDER BY t.sort_order, t.name
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    error_log("get_order_templates API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
