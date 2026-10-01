<?php

/**
 * Automated Database Backup Script
 * Backs up database to local storage and optionally to Google Drive
 */

require_once __DIR__ . '/../config/config.php';

use App\GoogleIntegration;

class DatabaseBackup
{

    private $backupDir;
    private $retentionDays = 7;

    public function __construct()
    {
        $this->backupDir = __DIR__ . '/../backups/database/';

        // Create backup directory if not exists
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Create database backup
     */
    public function createBackup()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;
        $gzFilepath = $filepath . '.gz';

        try {
            // MySQL dump command
            $command = sprintf(
                'mysqldump --no-tablespaces --skip-ssl --user=%s --password=%s --host=%s %s > %s 2>&1',
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_NAME),
                escapeshellarg($filepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception("MySQL dump failed: " . implode("\n", $output));
            }

            // Check if file was created
            if (!file_exists($filepath) || filesize($filepath) === 0) {
                throw new Exception("Backup file is empty or not created");
            }

            // Compress the backup
            $this->compressFile($filepath, $gzFilepath);

            // Remove uncompressed file
            unlink($filepath);

            $filesize = filesize($gzFilepath);
            $filesizeMB = round($filesize / 1024 / 1024, 2);

            $this->log("✓ Backup created: $filename.gz ($filesizeMB MB)");

            // Upload to Google Drive (optional)
            $driveFileId = null;
            if (GoogleIntegration::isConfigured()) {
                try {
                    $driveFileId = $this->uploadToGoogleDrive($gzFilepath, $filename . '.gz');
                    $this->log("✓ Uploaded to Google Drive: ID $driveFileId");
                } catch (Exception $e) {
                    $this->log("⚠ Google Drive upload failed: " . $e->getMessage());
                }
            }

            // Clean old backups
            $this->cleanOldBackups();

            // Save backup record to database
            $this->saveBackupRecord($filename . '.gz', $filesize, $driveFileId);

            return [
                'success' => true,
                'filename' => $filename . '.gz',
                'size' => $filesizeMB,
                'path' => $gzFilepath
            ];
        } catch (Exception $e) {
            $this->log("✗ Backup failed: " . $e->getMessage());

            // Send error notification
            $this->notifyError($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Compress file using gzip
     */
    private function compressFile($source, $destination)
    {
        $fp = gzopen($destination, 'w9');
        if (!$fp) {
            throw new Exception("Failed to create gzip file");
        }

        $file = fopen($source, 'rb');
        while (!feof($file)) {
            gzwrite($fp, fread($file, 1024 * 512));
        }
        fclose($file);
        gzclose($fp);
    }

    /**
     * Upload to Google Drive
     */
    private function uploadToGoogleDrive($filepath, $filename)
    {
        // Use GoogleIntegration class
        $folderId = defined('GOOGLE_DRIVE_BACKUP_FOLDER_ID')
            ? constant('GOOGLE_DRIVE_BACKUP_FOLDER_ID')
            : constant('GOOGLE_DRIVE_FOLDER_ID');

        return GoogleIntegration::uploadFile($filepath, $filename, $folderId);
    }

    /**
     * Clean old backups (keep only last N days)
     */
    private function cleanOldBackups()
    {
        $files = glob($this->backupDir . 'backup_*.sql.gz');
        $cutoffTime = time() - ($this->retentionDays * 24 * 60 * 60);

        $deleted = 0;
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->log("🗑 Cleaned $deleted old backup(s)");
        }
    }

    /**
     * Save backup record to database
     */
    private function saveBackupRecord($filename, $filesize, $driveFileId = null)
    {
        try {
            $stmt = db()->prepare("
                CREATE TABLE IF NOT EXISTS backups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    filename VARCHAR(255) NOT NULL,
                    filesize BIGINT NOT NULL,
                    google_drive_id VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_created (created_at)
                )
            ");
            $stmt->execute();

            $stmt = db()->prepare("
                INSERT INTO backups (filename, filesize, google_drive_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$filename, $filesize, $driveFileId]);
        } catch (Exception $e) {
            $this->log("⚠ Failed to save backup record: " . $e->getMessage());
        }
    }

    /**
     * Get list of backups
     */
    public function getBackupList()
    {
        $backups = [];

        // Get from database
        try {
            $stmt = db()->query("
                SELECT * FROM backups 
                ORDER BY created_at DESC 
                LIMIT 30
            ");
            $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback to file system
            $files = glob($this->backupDir . 'backup_*.sql.gz');
            rsort($files);

            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'filesize' => filesize($file),
                    'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                    'google_drive_id' => null
                ];
            }
        }

        return $backups;
    }

    /**
     * Delete backup
     */
    public function deleteBackup($filename)
    {
        $filename = basename($filename); // Security: prevent path traversal
        $filepath = $this->backupDir . $filename;

        try {
            // 1. Get Google Drive ID from database before deletion
            $stmt = db()->prepare("SELECT google_drive_id FROM backups WHERE filename = ?");
            $stmt->execute([$filename]);
            $backupRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Delete from Google Drive if ID exists
            if ($backupRecord && !empty($backupRecord['google_drive_id'])) {
                if (GoogleIntegration::isConfigured()) {
                    $driveId = $backupRecord['google_drive_id'];
                    try {
                        if (GoogleIntegration::deleteFile($driveId)) {
                            $this->log("🗑 Deleted backup from Google Drive: $driveId");
                        } else {
                            $this->log("⚠ Failed to delete from Google Drive (API returned false): $driveId");
                        }
                    } catch (Exception $e) {
                        $this->log("⚠ Google Drive deletion error: " . $e->getMessage());
                    }
                }
            }

            // 3. Delete from filesystem
            if (file_exists($filepath)) {
                if (!unlink($filepath)) {
                    throw new Exception("Failed to delete file from filesystem");
                }
            } else {
                // If it's a .sql.gz file but not found, maybe check without .gz (backward compatibility)
                // But generally we expect the exact filename passed from UI
                $this->log("⚠ File not found on disk during deletion: $filename");
            }

            // 4. Delete from database
            $stmt = db()->prepare("DELETE FROM backups WHERE filename = ?");
            $stmt->execute([$filename]);

            $this->log("🗑 Deleted backup: $filename");

            return ['success' => true];
        } catch (Exception $e) {
            $this->log("✗ Deletion failed for $filename: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Log message
     */
    private function log($message)
    {
        $logFile = __DIR__ . '/../logs/backup.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);

        echo "[$timestamp] $message\n";
    }

    /**
     * Notify about error
     */
    private function notifyError($error)
    {
        // Email notification (if configured)
        $emailEnabled = defined('EMAIL_NOTIFICATIONS_ENABLED') ? constant('EMAIL_NOTIFICATIONS_ENABLED') : false;
        if ($emailEnabled) {
            $to = defined('ADMIN_EMAIL') ? constant('ADMIN_EMAIL') : 'admin@example.com';
            $subject = 'Backup Failed - DetalStudio';
            $message = "Database backup failed at " . date('Y-m-d H:i:s') . "\n\nError: $error";
            @mail($to, $subject, $message);
        }
    }
}

// Run backup only when executed directly from CLI (not when included/required)
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $backup = new DatabaseBackup();
    $result = $backup->createBackup();

    if ($result['success']) {
        echo "\n✓ Backup completed successfully!\n";
        echo "  File: {$result['filename']}\n";
        echo "  Size: {$result['size']} MB\n";
        exit(0);
    } else {
        echo "\n✗ Backup failed: {$result['error']}\n";
        exit(1);
    }
}
