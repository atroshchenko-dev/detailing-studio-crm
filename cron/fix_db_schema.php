<?php

/**
 * DetalStudio - Critical Fix: Update Schema & Repair Data
 */

require_once __DIR__ . '/../config/config.php';

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only");
}

echo "=== Schema Repair Tool ===\n";

try {
    // 1. Migrate obsolete statuses FIRST
    echo "<h3>1. Migrating Obsolete Statuses...</h3>";
    $updateOld = "UPDATE tasks SET status = 'in_progress' WHERE status IN ('accepted', 'paused', 'revision')";
    db()->exec($updateOld);
    echo "Migrated 'accepted', 'paused', 'revision' tasks to 'in_progress'.<br>";

    // 2. Repair Corrupted Data (Fix blank statuses)
    echo "<h3>2. Repairing Blank Statuses...</h3>";

    // Fix Closed
    $fixClosed = "UPDATE tasks t 
                  JOIN orders o ON t.order_id = o.id 
                  SET t.status = 'closed' 
                  WHERE (t.status = '' OR t.status IS NULL) 
                  AND o.status = 'closed'";
    db()->exec($fixClosed);
    echo "Repaired tasks to 'closed'.<br>";

    // Fix Cancelled
    $fixCancelled = "UPDATE tasks t 
                     JOIN orders o ON t.order_id = o.id 
                     SET t.status = 'cancelled' 
                     WHERE (t.status = '' OR t.status IS NULL) 
                     AND o.status = 'cancelled'";
    db()->exec($fixCancelled);
    echo "Repaired tasks to 'cancelled'.<br>";

    // 3. Alter Table to CLEAN schema
    echo "<h3>3. Updating Table Schema...</h3>";

    // Clean ENUM definition
    $newEnum = "ENUM('created','in_progress','completed','closed','cancelled')";
    $alterQuery = "ALTER TABLE tasks MODIFY COLUMN status $newEnum NOT NULL DEFAULT 'created'";
    db()->exec($alterQuery);

    echo "Query executed: <pre>$alterQuery</pre>";
    echo "<b style='color:green'>Success! Schema updated.</b><br>";

    // 4. Update View (Exclude closed/cancelled from Overdue)
    echo "<h3>4. Updating Views...</h3>";
    $viewQuery = "CREATE OR REPLACE VIEW `v_task_overview` AS
        SELECT 
            t.id,
            t.title,
            t.status,
            t.priority,
            t.deadline,
            creator.full_name as creator_name,
            executor.full_name as executor_name,
            o.order_number,
            CASE 
                WHEN t.deadline < NOW() AND t.status NOT IN ('completed', 'closed', 'cancelled') THEN 1
                ELSE 0
            END as is_overdue
        FROM tasks t
        LEFT JOIN users creator ON t.creator_id = creator.id
        LEFT JOIN users executor ON t.executor_id = executor.id
        LEFT JOIN orders o ON t.order_id = o.id";

    db()->exec($viewQuery);
    echo "View `v_task_overview` updated.<br><hr>";

    echo "<h3>Done. Please verify in the app.</h3>";
} catch (Exception $e) {
    echo "<b style='color:red'>Error: " . $e->getMessage() . "</b>";
}
