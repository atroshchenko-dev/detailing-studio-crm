<?php

/**
 * DetalStudio - Cron Job: Check Deadlines
 * Перевірка дедлайнів та сповіщення
 */

require_once __DIR__ . '/../config/config.php';

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only");
}

try {
    // Check for overdue tasks
    $overdueTasks = Task::getOverdue();

    foreach ($overdueTasks as $task) {
        // Check if notification was already sent (last 2 hours)
        $stmt = db()->prepare("SELECT id FROM telegram_notifications 
                              WHERE entity_type = 'task' 
                              AND entity_id = ? 
                              AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        $stmt->execute([$task['id']]);

        if (!$stmt->fetch()) {
            // Send notification via Events
            \App\Events\EventDispatcher::dispatch(new \App\Events\DeadlineReached($task, 'task', 'менше 2 годин'));
        }
    }

    echo "Deadline check completed. " . count($overdueTasks) . " overdue tasks found.\n";
} catch (Exception $e) {
    error_log("Cron job error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
