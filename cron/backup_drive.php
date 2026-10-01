<?php
/**
 * DetalStudio - Automated Backup to Google Drive (DB + Files)
 * Run this via Cron: 0 3 * * * php /path/to/cron/backup_drive.php
 */

require_once __DIR__ . '/../config/config.php';


// Check if we can run
if (php_sapi_name() !== 'cli') {
    die("This script must be run from CLI.");
}

// Ensure GoogleIntegration is loaded
if (!class_exists('App\GoogleIntegration')) {
    require_once __DIR__ . '/../includes/GoogleIntegration.php';
}

use App\GoogleIntegration;

// Settings
$backupDir = __DIR__ . '/../backups';
$uploadsDir = __DIR__ . '/../uploads';
$logFile = __DIR__ . '/../logs/backup.log';
$retentionCount = 7; // Keep last 7 backups

// Helpers
function logMsg($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $msg\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

// 0. Init
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
if (!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0755, true);

$date = date('Y-m-d_H-i-s');
$baseName = "backup_{$date}";
$sqlFile = "$backupDir/$baseName.sql";
$zipFile = "$backupDir/$baseName.zip";

logMsg("=== Starting Backup ===");

try {
    // 1. Database Dump
    logMsg("Dumping database " . DB_NAME . "...");
    $command = sprintf(
        'mysqldump --skip-ssl --user=%s --password=%s --host=%s %s > %s',
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_NAME),
        escapeshellarg($sqlFile)
    );
    
    exec($command, $output, $returnVar);
    
    if ($returnVar !== 0) {
        throw new Exception("mysqldump failed with exit code $returnVar");
    }
    
    if (!file_exists($sqlFile) || filesize($sqlFile) === 0) {
        throw new Exception("SQL dump file is empty or missing");
    }

    // 2. Create Archive (SQL + Uploads)
    logMsg("Creating ZIP archive...");
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        throw new Exception("Cannot create zip file");
    }

    // Add SQL
    $zip->addFile($sqlFile, "database.sql");

    // Add Uploads (Recursive)
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        // Skip directories (they would be added automatically)
        if (!$file->isDir()) {
            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = 'uploads/' . substr($filePath, strlen(realpath($uploadsDir)) + 1);

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();
    
    // Cleanup SQL
    unlink($sqlFile);
    logMsg("Archive created: $zipFile (" . round(filesize($zipFile) / 1024 / 1024, 2) . " MB)");

    // 3. Upload to Google Drive
    if (GoogleIntegration::isConfigured()) {
        logMsg("Uploading to Google Drive...");
        $fileId = GoogleIntegration::uploadFile($zipFile, basename($zipFile));
        
        if ($fileId) {
            logMsg("Uploaded successfully. ID: $fileId");
            
            // 4. Retention Policy (Cleanup old backups)
            logMsg("Checking retention policy (Keep: $retentionCount)...");
            $files = GoogleIntegration::listFiles(); // Gets files in backup folder sorted by date desc
            
            // Filter only backup zip files if needed (check name pattern)
            $backups = array_filter($files, function($f) {
                return strpos($f['name'], 'backup_') === 0 && strpos($f['name'], '.zip') !== false;
            });
            
            if (count($backups) > $retentionCount) {
                $toDelete = array_slice($backups, $retentionCount);
                foreach ($toDelete as $file) {
                    logMsg("Deleting old backup: " . $file['name']);
                    GoogleIntegration::deleteFile($file['id']);
                }
            }
            
            // Delete local zip to save space
            unlink($zipFile);
            logMsg("Local archive removed.");
            
        } else {
            logMsg("Error: Google Drive upload returned no ID.");
        }
    } else {
        logMsg("Warning: Google Drive not configured. Archive kept locally.");
    }

} catch (Exception $e) {
    logMsg("ERROR: " . $e->getMessage());
    
    // Send alert to admin via Telegram
    if (class_exists('App\Telegram')) {
        $alertMsg = "🚨 <b>ПОМИЛКА БЕКАПУ</b>\n\n";
        $alertMsg .= "❌ <b>Помилка:</b> " . htmlspecialchars($e->getMessage()) . "\n";
        $alertMsg .= "🕒 <b>Час:</b> " . date('Y-m-d H:i:s');
        
        // Notify Group (Alerts Topic)
        $topicId = defined('TELEGRAM_TOPIC_ALERTS') ? TELEGRAM_TOPIC_ALERTS : null;
        \App\Telegram::sendToGroup($alertMsg, null, null, $topicId);
        
        // Also notify Directors personally if needed, but Group Alert is usually sufficient for Dev/Admin
    }
}

logMsg("=== Backup Finished ===\n");
