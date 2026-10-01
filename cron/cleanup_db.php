<?php

/**
 * DetalStudio - Cron Job: Database Cleanup
 * Повне видалення записів, позначених як "видалені", та очищення "сирітських" записів.
 */

require_once __DIR__ . '/../config/config.php';

// Check if running from CLI or authorized user
if (php_sapi_name() !== 'cli') {
    if (!App\Auth::isAdmin()) {
        die("Unauthorized access.");
    }
}

$isDryRun = true;
if (php_sapi_name() === 'cli') {
    $isDryRun = !in_array('--run', $argv);
} elseif (isset($_GET['run']) && $_GET['run'] == '1') {
    $isDryRun = false;
}

echo "=== DetalStudio Database Cleanup ===\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (No changes will be made)" : "LIVE RUN (Data will be permanently deleted)") . "\n\n";

/**
 * Check if table exists in database
 */
function tableExists($db, $table)
{
    try {
        $result = $db->query("SELECT 1 FROM $table LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

try {
    $db = db();
    if (!$isDryRun) {
        $db->beginTransaction();
    }

    $stats = [
        'tasks' => 0,
        'task_executors' => 0,
        'task_comments' => 0,
        'task_attachments' => 0,
        'orders' => 0,
        'order_items' => 0,
        'order_payments' => 0,
        'order_comments' => 0,
        'order_photos' => 0,
        'cars' => 0,
        'clients' => 0
    ];

    // Check existing tables
    $hasTasks = tableExists($db, 'tasks');
    $hasOrders = tableExists($db, 'orders');
    $hasCars = tableExists($db, 'cars');
    $hasClients = tableExists($db, 'clients');

    // 1. CLEANUP DELETED TASKS
    if ($hasTasks) {
        // Get IDs of tasks to be deleted
        $deletedTaskIds = $db->query("SELECT id FROM tasks WHERE is_deleted = 1")->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($deletedTaskIds)) {
            $stats['tasks'] = count($deletedTaskIds);
            $placeholders = implode(',', array_fill(0, count($deletedTaskIds), '?'));

            // Cleanup relations safely
            if (tableExists($db, 'task_executors')) {
                $stats['task_executors'] = $db->query("SELECT COUNT(*) FROM task_executors WHERE task_id IN (SELECT id FROM tasks WHERE is_deleted = 1)")->fetchColumn();
                if (!$isDryRun) $db->prepare("DELETE FROM task_executors WHERE task_id IN ($placeholders)")->execute($deletedTaskIds);
            }
            if (tableExists($db, 'task_comments')) {
                $stats['task_comments'] = $db->query("SELECT COUNT(*) FROM task_comments WHERE task_id IN (SELECT id FROM tasks WHERE is_deleted = 1)")->fetchColumn();
                if (!$isDryRun) $db->prepare("DELETE FROM task_comments WHERE task_id IN ($placeholders)")->execute($deletedTaskIds);
            }
            if (tableExists($db, 'task_attachments')) {
                $stats['task_attachments'] = $db->query("SELECT COUNT(*) FROM task_attachments WHERE task_id IN (SELECT id FROM tasks WHERE is_deleted = 1)")->fetchColumn();
                if (!$isDryRun) $db->prepare("DELETE FROM task_attachments WHERE task_id IN ($placeholders)")->execute($deletedTaskIds);
            }

            if (!$isDryRun) {
                $db->prepare("DELETE FROM tasks WHERE id IN ($placeholders)")->execute($deletedTaskIds);
            }
        }
    }

    // 2. CLEANUP DELETED ORDERS
    if ($hasOrders) {
        $deletedOrderIds = $db->query("SELECT id FROM orders WHERE is_deleted = 1")->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($deletedOrderIds)) {
            $stats['orders'] = count($deletedOrderIds);
            $placeholders = implode(',', array_fill(0, count($deletedOrderIds), '?'));

            // Cleanup relations safely
            if (tableExists($db, 'order_items')) {
                $stats['order_items'] = $db->query("SELECT COUNT(*) FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE is_deleted = 1)")->fetchColumn();
                if (!$isDryRun) $db->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)")->execute($deletedOrderIds);
            }
            if (tableExists($db, 'order_payments')) {
                $stats['order_payments'] = $db->query("SELECT COUNT(*) FROM order_payments WHERE order_id IN (SELECT id FROM orders WHERE is_deleted = 1)")->fetchColumn();
                if (!$isDryRun) $db->prepare("DELETE FROM order_payments WHERE order_id IN ($placeholders)")->execute($deletedOrderIds);
            }
            if (tableExists($db, 'order_comments')) {
                $stats['order_comments'] = $db->query("SELECT COUNT(*) FROM order_comments WHERE order_id IN (SELECT id FROM orders WHERE is_deleted = 1)")->fetchColumn();
                if (!$isDryRun) $db->prepare("DELETE FROM order_comments WHERE order_id IN ($placeholders)")->execute($deletedOrderIds);
            }
            if (tableExists($db, 'order_photos')) {
                $stats['order_photos'] = $db->query("SELECT COUNT(*) FROM order_photos WHERE order_id IN (SELECT id FROM orders WHERE is_deleted = 1)")->fetchColumn();
                if (!$isDryRun) $db->prepare("DELETE FROM order_photos WHERE order_id IN ($placeholders)")->execute($deletedOrderIds);
            }

            // Find tasks linked to deleted orders
            if ($hasTasks) {
                $linkedTaskIds = $db->query("SELECT id FROM tasks WHERE order_id IN (SELECT id FROM orders WHERE is_deleted = 1)")->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($linkedTaskIds)) {
                    $stats['tasks'] += count($linkedTaskIds);
                    $tPlaceholders = implode(',', array_fill(0, count($linkedTaskIds), '?'));
                    if (!$isDryRun) {
                        if (tableExists($db, 'task_executors')) $db->prepare("DELETE FROM task_executors WHERE task_id IN ($tPlaceholders)")->execute($linkedTaskIds);
                        if (tableExists($db, 'task_comments')) $db->prepare("DELETE FROM task_comments WHERE task_id IN ($tPlaceholders)")->execute($linkedTaskIds);
                        if (tableExists($db, 'task_attachments')) $db->prepare("DELETE FROM task_attachments WHERE task_id IN ($tPlaceholders)")->execute($linkedTaskIds);
                        $db->prepare("DELETE FROM tasks WHERE id IN ($tPlaceholders)")->execute($linkedTaskIds);
                    }
                }
            }

            if (!$isDryRun) {
                $db->prepare("DELETE FROM orders WHERE id IN ($placeholders)")->execute($deletedOrderIds);
            }
        }
    }

    // 3. CLEANUP ORPHANED CARS (No client)
    if ($hasCars && $hasClients) {
        $stats['cars'] = $db->query("SELECT COUNT(*) FROM cars WHERE client_id NOT IN (SELECT id FROM clients)")->fetchColumn();
        if ($stats['cars'] > 0 && !$isDryRun) {
            $db->exec("DELETE FROM cars WHERE client_id NOT IN (SELECT id FROM clients)");
        }
    }

    // 4. CLEANUP ORPHANED CLIENTS (No orders AND No cars)
    if ($hasClients && $hasOrders && $hasCars) {
        $stats['clients'] = $db->query("SELECT COUNT(*) FROM clients WHERE id NOT IN (SELECT DISTINCT client_id FROM orders) AND id NOT IN (SELECT DISTINCT client_id FROM cars)")->fetchColumn();
        if ($stats['clients'] > 0 && !$isDryRun) {
            $db->exec("DELETE FROM clients WHERE id NOT IN (SELECT DISTINCT client_id FROM orders) AND id NOT IN (SELECT DISTINCT client_id FROM cars)");
        }
    }

    // 5. CLEANUP OLD TELEGRAM CONVERSATIONS (TTL 24 hours)
    if (tableExists($db, 'telegram_conversations')) {
        $stats['telegram_conversations'] = $db->query("SELECT COUNT(*) FROM telegram_conversations WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        if ($stats['telegram_conversations'] > 0 && !$isDryRun) {
            $db->exec("DELETE FROM telegram_conversations WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        }
    }

    if (!$isDryRun) {
        $db->commit();
        echo "Cleanup completed successfully.\n";
    } else {
        echo "Dry run completed. No data was deleted.\n";
    }

    echo "\nSummary of records to be deleted/deleted:\n";
    foreach ($stats as $entity => $count) {
        echo "- " . ucfirst(str_replace('_', ' ', $entity)) . ": $count\n";
    }
} catch (Exception $e) {
    if (!$isDryRun && isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database cleanup error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
