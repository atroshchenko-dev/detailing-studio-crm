<?php
/**
 * Migration: Add access_token to orders
 */

require_once __DIR__ . '/../config/config.php';

echo "Migration started...\n";

try {
    // 1. Check if column exists
    $stmt = db()->query("SHOW COLUMNS FROM orders LIKE 'access_token'");
    $exists = $stmt->fetch();

    if (!$exists) {
        // 2. Add column
        echo "Adding access_token column...\n";
        db()->exec("ALTER TABLE orders ADD COLUMN access_token VARCHAR(64) NULL AFTER status");
        db()->exec("ALTER TABLE orders ADD INDEX (access_token)");
        echo "Column added.\n";
    } else {
        echo "Column already exists.\n";
    }

    // 3. Generate tokens for existing orders
    echo "Generating tokens for existing orders...\n";
    $stmt = db()->query("SELECT id FROM orders WHERE access_token IS NULL");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $count = 0;
    foreach ($ids as $id) {
        $token = bin2hex(random_bytes(16)); // 32 chars
        $update = db()->prepare("UPDATE orders SET access_token = ? WHERE id = ?");
        $update->execute([$token, $id]);
        $count++;
    }

    echo "Updated $count orders.\n";
    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
