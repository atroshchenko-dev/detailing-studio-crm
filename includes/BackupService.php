<?php
declare(strict_types=1);

namespace App;

use App\Events\EventDispatcher;
use App\Events\BackupProcessed;

use Exception;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use Throwable;

/**
 * DetalStudio - Backup Service
 * Handles database dumping and file archiving to Google Drive
 */
class BackupService {
    
    private static $logFile = __DIR__ . '/../logs/backup.log';

    /**
     * Run the full backup process
     */
    public static function run(): bool {
        self::log("=== Starting Automated Backup ===");
        
        $backupDir = __DIR__ . '/../backups';
        $uploadsDir = __DIR__ . '/../uploads';
        $date = date('Y-m-d_H-i-s');
        $baseName = "backup_{$date}";
        $sqlFile = "$backupDir/$baseName.sql";
        $zipFile = "$backupDir/$baseName.zip";

        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

        try {
            // 1. Database Dump
            self::log("Dumping database " . DB_NAME . "...");
            
            // Allow override via environment variable
            $mysqldumpPath = getenv('MYSQLDUMP_PATH') ?: '/usr/bin/mysqldump';
            
            if (!file_exists($mysqldumpPath) && $mysqldumpPath !== 'mysqldump') {
                $mysqldumpPath = 'mysqldump'; // Fallback to PATH
            }

            self::log("Using mysqldump path: $mysqldumpPath");

            $command = sprintf(
                '%s --no-tablespaces --skip-add-locks --skip-ssl --user=%s --password=%s --host=%s %s > %s 2>&1',
                $mysqldumpPath,
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_NAME),
                escapeshellarg($sqlFile)
            );
            
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                $errorMsg = implode("\n", $output);
                throw new Exception("mysqldump failed with exit code $returnVar. Error: $errorMsg");
            }

            // 2. Create ZIP
            self::log("Creating ZIP archive...");
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Cannot create zip file");
            }

            $zip->addFile($sqlFile, "database.sql");

            if (is_dir($uploadsDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploadsDir),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'uploads/' . substr($filePath, strlen(realpath($uploadsDir)) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }

            $zip->close();
            unlink($sqlFile); // Remove raw SQL
            self::log("Archive created: " . basename($zipFile) . " (" . round(filesize($zipFile) / 1024 / 1024, 2) . " MB)");

            // 3. Upload to Google Drive
            if (GoogleIntegration::isConfigured()) {
                self::log("Uploading to Google Drive...");
                $fileId = GoogleIntegration::uploadFile($zipFile, basename($zipFile));
                
                if ($fileId) {
                    self::log("Uploaded successfully. ID: $fileId");
                    
                    // 4. Retention Policy
                    $retention = defined('BACKUP_RETENTION_DAYS') ? BACKUP_RETENTION_DAYS : 7;
                    self::log("Cleaning up old backups (Retention: $retention days)...");
                    
                    $files = GoogleIntegration::listFiles();
                    $backups = array_filter($files, function($f) {
                        return strpos($f['name'], 'backup_') === 0 && strpos($f['name'], '.zip') !== false;
                    });
                    
                    // Sorting by name (which has timestamp) desc
                    usort($backups, function($a, $b) {
                        return strcmp($b['name'], $a['name']);
                    });

                    if (count($backups) > $retention) {
                        $toDelete = array_slice($backups, $retention);
                        foreach ($toDelete as $file) {
                            self::log("Deleting old backup: " . $file['name']);
                            GoogleIntegration::deleteFile($file['id']);
                        }
                    }
                    
                    if (file_exists($zipFile)) {
                        unlink($zipFile); // Remove local zip
                        self::log("Local archive removed.");
                    }
                } else {
                    throw new Exception("Google Drive upload failed. Check GoogleIntegration logs or quota.");
                }
            } else {
                self::log("Warning: Google Drive not configured. Archive kept locally at $zipFile");
            }

            EventDispatcher::dispatch(new BackupProcessed(
                true, 
                "✅ <b>БЕКАП УСПІШНИЙ</b>\n\nАрхів: <code>" . basename($zipFile) . "</code>\nЧас: " . date('H:i:s'),
                basename($zipFile)
            ));
            self::log("=== Backup Finished Successfully ===\n");
            return true;

        } catch (Throwable $e) {
            self::log("ERROR: " . $e->getMessage(), true);
            EventDispatcher::dispatch(new BackupProcessed(
                false, 
                "🚨 <b>ПОМИЛКА БЕКАПУ</b>\n\nПомилка: <code>" . htmlspecialchars($e->getMessage()) . "</code>"
            ));
            return false;
        }
    }

    private static function log(string $msg, bool $isError = false) {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = $isError ? "[ERROR] " : "[INFO] ";
        $line = "[$timestamp] $prefix$msg\n";
        echo $line;
        if (!is_dir(dirname(self::$logFile))) mkdir(dirname(self::$logFile), 0755, true);
        file_put_contents(self::$logFile, $line, FILE_APPEND);
    }
}
