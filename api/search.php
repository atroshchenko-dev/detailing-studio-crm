<?php
/**
 * DetalStudio - Global Search API
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

\App\Auth::requireAuth();

$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$results = [
    'orders' => [],
    'clients' => [],
    'cars' => [],
    'tasks' => []
];

$db = db();
$searchTerm = "%$query%";

try {
    // Boolean mode supports '+' for mandatory, '-' for exclude, '*' for wildcard
    // We'll use a simple wildcard for better matching: "term*"
    $booleanQuery = "";
    $words = explode(' ', $query);
    foreach ($words as $word) {
        if (strlen($word) >= 2) {
            $booleanQuery .= "+" . $word . "* ";
        }
    }
    $booleanQuery = trim($booleanQuery);

    // 1. Search Orders (Usually exact or prefix match is preferred here)
    // We'll keep LIKE for orders as numbers are short and usually searched from start or as a whole
    $stmt = $db->prepare("SELECT id, order_number, total_amount, status FROM orders WHERE (order_number LIKE ? OR order_number = ?) AND is_deleted = 0 ORDER BY (order_number = ?) DESC LIMIT 5");
    $stmt->execute([$searchTerm, $query, $query]);
    foreach ($stmt->fetchAll() as $row) {
        $results['orders'][] = [
            'type' => 'Замовлення',
            'text' => $row['order_number'],
            'subtext' => format_currency($row['total_amount']) . " (" . \App\Models\Order::getStatusName($row['status']) . ")",
            'url' => "/orders/view.php?id={$row['id']}",
            'icon' => 'bi-receipt'
        ];
    }

    // 2. Search Clients (FULLTEXT)
    if ($booleanQuery) {
        $stmt = $db->prepare("SELECT id, name, phone, MATCH(name) AGAINST(? IN BOOLEAN MODE) as score 
                             FROM clients 
                             WHERE MATCH(name) AGAINST(? IN BOOLEAN MODE) OR phone LIKE ? 
                             ORDER BY score DESC LIMIT 5");
        $stmt->execute([$booleanQuery, $booleanQuery, $searchTerm]);
        foreach ($stmt->fetchAll() as $row) {
            $results['clients'][] = [
                'type' => 'Клієнт',
                'text' => $row['name'],
                'subtext' => \App\Utils\Helper::displayPhone($row['phone']),
                'url' => "/clients/view.php?id={$row['id']}",
                'icon' => 'bi-person'
            ];
        }
    }

    // 3. Search Cars (FULLTEXT)
    if ($booleanQuery) {
        $stmt = $db->prepare("SELECT id, make, model, license_plate, MATCH(license_plate, vin, make, model) AGAINST(? IN BOOLEAN MODE) as score 
                             FROM cars 
                             WHERE MATCH(license_plate, vin, make, model) AGAINST(? IN BOOLEAN MODE) OR license_plate LIKE ? 
                             ORDER BY score DESC LIMIT 5");
        $stmt->execute([$booleanQuery, $booleanQuery, $searchTerm]);
        foreach ($stmt->fetchAll() as $row) {
            $text = "{$row['make']} {$row['model']}";
            if ($row['license_plate']) $text .= " ({$row['license_plate']})";
            
            $results['cars'][] = [
                'type' => 'Авто',
                'text' => $text,
                'url' => "/cars/view.php?id={$row['id']}",
                'icon' => 'bi-car-front'
            ];
        }
    }

    // 4. Search Tasks (FULLTEXT)
    if ($booleanQuery) {
        $stmt = $db->prepare("SELECT id, title, status, MATCH(title) AGAINST(? IN BOOLEAN MODE) as score 
                             FROM tasks 
                             WHERE (MATCH(title) AGAINST(? IN BOOLEAN MODE)) AND is_deleted = 0 
                             ORDER BY score DESC LIMIT 5");
        $stmt->execute([$booleanQuery, $booleanQuery]);
        foreach ($stmt->fetchAll() as $row) {
            $results['tasks'][] = [
                'type' => 'Задача',
                'text' => $row['title'],
                'subtext' => \App\Models\Task::getStatusName($row['status']),
                'url' => "/tasks/view.php?id={$row['id']}",
                'icon' => 'bi-list-check'
            ];
        }
    }

} catch (Exception $e) {
    error_log("Search Error: " . $e->getMessage());
}

echo json_encode($results);
