<?php

/**
 * DetalStudio - Create Booking API
 * API для створення заявки на запис
 */

require_once __DIR__ . '/../config/config.php';


header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Rate limiting: 5 bookings per 10 minutes per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitDir = __DIR__ . '/../logs/rate_limit';
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0755, true);
    }
    $rateLimitFile = $rateLimitDir . '/' . md5($ip) . '.json';
    $maxRequests = 5;
    $windowSeconds = 600; // 10 minutes
    $now = time();

    $attempts = [];
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if (is_array($data)) {
            // Keep only attempts within the window
            $attempts = array_filter($data, fn($t) => ($now - $t) < $windowSeconds);
        }
    }

    if (count($attempts) >= $maxRequests) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Забагато запитів. Спробуйте через декілька хвилин.']);
        exit;
    }

    $attempts[] = $now;
    file_put_contents($rateLimitFile, json_encode(array_values($attempts)));

    // Basic Validation
    if (empty($_POST['name']) || empty($_POST['phone'])) {
        throw new Exception('Вкажіть ім\'я та телефон');
    }

    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $carMake = trim($_POST['car_make'] ?? '');
    $carModel = trim($_POST['car_model'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Transaction to safely create client, car and order
    db()->beginTransaction();

    // 1. Find or Create Client
    // Simple search by phone
    $stmt = db()->prepare("SELECT * FROM clients WHERE phone LIKE ?");
    // Normalize phone search loose (match last 10 digits?) - for now simple exact match or LIKE
    $searchPhone = '%' . substr(preg_replace('/[^0-9]/', '', $phone), -9);
    $stmt->execute([$searchPhone]);
    $client = $stmt->fetch();

    if (!$client) {
        $stmt = db()->prepare("INSERT INTO clients (name, phone, client_type) VALUES (?, ?, 'new')");
        $stmt->execute([$name, $phone]);
        $clientId = db()->lastInsertId();
    } else {
        $clientId = $client['id'];
    }

    // 2. Create Car (if info provided)
    $carId = null;
    if ($carMake) {
        // Try to find if this client has this car? For simplicity, create new car log if checking is complex.
        // Assuming we just create a "Potential Car" record or link later. 
        // Let's create a car entry.
        $stmt = db()->prepare("INSERT INTO cars (client_id, make, model) VALUES (?, ?, ?)");
        $stmt->execute([$clientId, $carMake, $carModel]);
        $carId = db()->lastInsertId();
    }

    // 3. Create Order
    $orderNumber = generate_order_number();
    $accessToken = bin2hex(random_bytes(16));

    // Status 'new' is fine.
    // Notes combine service wishlist and user notes
    $fullNotes = "ОНЛАЙН ЗАПИС\n";
    if ($service) $fullNotes .= "Бажана послуга: $service\n";
    if ($date) $fullNotes .= "Бажана дата: $date\n";
    if ($notes) $fullNotes .= "Коментар: $notes";

    // Since we don't have manager session, we set manager_id to NULL or a default admin?
    // Let's set to NULL (Unassigned).

    $stmt = db()->prepare("INSERT INTO orders (order_number, client_id, car_id, status, notes, access_token, created_at) VALUES (?, ?, ?, 'new', ?, ?, NOW())");
    $stmt->execute([$orderNumber, $clientId, $carId, $fullNotes, $accessToken]);
    $orderId = db()->lastInsertId();

    db()->commit();

    // 4. Dispatch OrderCreated Event
    \App\Events\EventDispatcher::dispatch(new \App\Events\OrderCreated($orderId, $_POST));

    // Also notify directors? (Optional, skipping to avoid spam if they are in group)

    echo json_encode(['success' => true, 'order_id' => $orderId]);
} catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log("create_booking API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Внутрішня помилка сервера']);
}
