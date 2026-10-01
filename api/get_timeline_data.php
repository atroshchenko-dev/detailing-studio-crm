<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Models\Order;
use App\Models\Stall;

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate date format (YYYY-MM-DD)
$startRaw = $_GET['start'] ?? '';
$endRaw = $_GET['end'] ?? '';
$start = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw)) ? $startRaw . ' 00:00:00' : date('Y-m-01 00:00:00');
$end = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endRaw)) ? $endRaw . ' 23:59:59' : date('Y-m-t 23:59:59');

try {
    $fetch = $_GET['fetch'] ?? 'all';

    $responseData = [];

    // Fetch Resources (Stalls)
    if ($fetch === 'resources' || $fetch === 'all') {
        $stalls = Stall::getAll();
        $resources = [];
        foreach ($stalls as $stall) {
            $resources[] = [
                'id' => (string)$stall['id'],
                'title' => $stall['name']
            ];
        }
        if ($fetch === 'resources') {
            echo json_encode($resources);
            exit;
        }
        $responseData['resources'] = $resources;
    }

    // Fetch Events (Orders)
    if ($fetch === 'events' || $fetch === 'all') {
        $orders = Order::getForTimeline($start, $end);
        $events = [];
        foreach ($orders as $order) {
            $color = Order::getStatusColor($order['status']);

            $events[] = [
                'id' => (string)$order['id'],
                'resourceId' => (string)$order['stall_id'],
                'title' => $order['order_number'] . ' - ' . $order['client_name'],
                'start' => $order['planned_start'],
                'end' => $order['planned_end'],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'url' => '/orders/view.php?id=' . $order['id'],
                'extendedProps' => [
                    'car' => $order['make'] . ' ' . $order['model'],
                    'status' => $order['status']
                ]
            ];
        }
        if ($fetch === 'events') {
            echo json_encode($events);
            exit;
        }
        $responseData['events'] = $events;
    }

    echo json_encode($responseData);
} catch (Exception $e) {
    error_log("get_timeline_data API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
