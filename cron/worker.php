<?php

/**
 * DetalStudio - Background Worker
 * Runs queue processing in a loop
 */

require_once __DIR__ . '/../vendor/autoload.php';
// Config loads environment and DB interaction
require_once __DIR__ . '/../config/config.php';

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only");
}



// Disable timeout
set_time_limit(0);

// Graceful shutdown handling
$shutdown = false;

if (extension_loaded('pcntl')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$shutdown) {
        echo "[" . date('Y-m-d H:i:s') . "] SIGTERM received. Shutting down...\n";
        $shutdown = true;
    });
    pcntl_signal(SIGINT, function () use (&$shutdown) {
        echo "[" . date('Y-m-d H:i:s') . "] SIGINT received. Shutting down...\n";
        $shutdown = true;
    });
}

function check_stop_file()
{
    if (file_exists(__DIR__ . '/worker.stop')) {
        echo "[" . date('Y-m-d H:i:s') . "] worker.stop found. Shutting down...\n";
        unlink(__DIR__ . '/worker.stop');
        return true;
    }
    return false;
}

echo "[" . date('Y-m-d H:i:s') . "] Worker started (PID: " . getmypid() . ").\n";

while (!$shutdown) {
    if (check_stop_file()) {
        break;
    }

    try {
        // 2. Scheduled Tasks (Backups)
        $now = date('H:i');
        $today = date('Y-m-d');
        $lastBackupFile = __DIR__ . '/last_backup.txt';
        $lastBackupDate = file_exists($lastBackupFile) ? trim(file_get_contents($lastBackupFile)) : '';

        // Run backup if time is right and hasn't run today
        if ($now >= BACKUP_TIME && $lastBackupDate !== $today) {
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled backup starting...\n";
            $success = \App\BackupService::run();

            // Record attempt regardless of success to prevent tight-loop retries
            file_put_contents($lastBackupFile, $today);

            if ($success) {
                echo "[" . date('Y-m-d H:i:s') . "] Backup completed and recorded for $today\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Backup FAILED. Recorded attempt for $today to avoid flooding.\n";
            }
        }
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";

        // If DB connection is lost, reset the instance to force reconnection on next iteration
        if (strpos($e->getMessage(), 'gone away') !== false || strpos($e->getMessage(), 'Connection refused') !== false) {
            echo "[" . date('Y-m-d H:i:s') . "] Database connection lost. Resetting...\n";
            if (class_exists('Database')) {
                Database::reset();
            }
        }

        // Prevent tight loop in case of DB failure
        sleep(10);
    }

    // Wait 5 seconds
    sleep(5);
}

echo "[" . date('Y-m-d H:i:s') . "] Worker stopped.\n";
