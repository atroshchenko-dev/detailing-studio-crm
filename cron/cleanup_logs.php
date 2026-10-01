<?php

/**
 * DetalStudio - Cron Job: Cleanup Logs
 * Очищення старих логів
 */

require_once __DIR__ . '/../config/config.php';

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only");
}

try {
    // Clean old audit logs (keep last 6 months)
    $result = Audit::cleanOldLogs();

    // Clean old notifications (keep last 1 month)
    $stmt = db()->prepare("DELETE FROM telegram_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();

    echo "Log cleanup completed.\n";
} catch (Exception $e) {
    error_log("Cron job error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
