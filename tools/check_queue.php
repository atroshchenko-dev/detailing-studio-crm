<?php
require_once __DIR__ . '/../config/config.php';

try {
    $stmt = db()->query("SELECT status, COUNT(*) as count FROM notification_queue GROUP BY status");
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "Queue Status:\n";
    if (empty($stats)) {
        echo "Queue is empty.\n";
    } else {
        foreach ($stats as $status => $count) {
            echo " - $status: $count\n";
        }
    }
    
    // Check recent errors
    $stmt = db()->query("SELECT * FROM notification_queue WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 3");
    $errors = $stmt->fetchAll();
    if ($errors) {
        echo "\nRecent Errors:\n";
        foreach ($errors as $err) {
            echo " [{$err['id']}] {$err['action']} ({$err['type']}): {$err['error_message']}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
