<?php

/**
 * DetalStudio - Automated Backup
 * Creates DB dump and zips uploads, then sends to Google Drive
 * 
 * @deprecated Use backup_database.php (DB only + GDrive + alerts) or backup_drive.php (DB + files + GDrive).
 *             This script lacks logging, error alerts, compression, and retention policy.
 */

require_once __DIR__ . '/../config/config.php';

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only");
}

// Disable memory limit and increase timeout
ini_set('memory_limit', '-1');
set_time_limit(0);

echo "Starting Backup Process: " . date('Y-m-d H:i:s') . PHP_EOL;

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$dbFileName = "db_backup_{$timestamp}.sql";
$dbFilePath = $backupDir . '/' . $dbFileName;
$zipFileName = "files_backup_{$timestamp}.zip";
$zipFilePath = $backupDir . '/' . $zipFileName;

// 1. Database Backup (PHP Implementation)
echo "1. Dumping Database... ";
try {
    $db = db();
    $handle = fopen($dbFilePath, 'w+');

    // Get all tables
    $tables = [];
    $stmt = $db->query('SHOW TABLES');
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Drop table
        fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");

        // Create table
        $row = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        fwrite($handle, $row[1] . ";\n\n");

        // Data
        $stmt = $db->query("SELECT * FROM `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . addslashes($value) . "'";
                }
            }
            fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n");
        }
        fwrite($handle, "\n\n");
    }

    fclose($handle);
    echo "Done. Size: " . round(filesize($dbFilePath) / 1024, 2) . " KB" . PHP_EOL;
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// 2. Files Backup (Zip Uploads)
echo "2. Zipping Uploads... ";
$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $uploadPath = realpath(__DIR__ . '/../uploads');

    if (is_dir($uploadPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($uploadPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    $zip->close();
    echo "Done. Size: " . round(filesize($zipFilePath) / 1024, 2) . " KB" . PHP_EOL;
} else {
    echo "FAILED to create zip" . PHP_EOL;
}

// 3. Upload to Google Drive
echo "3. Uploading to Google Drive... ";

if (App\GoogleIntegration::isConfigured()) {
    // Create 'Backups' folder if needed or use root?
    // For simplicity, using root or configured ID.
    // Ideally we might want to check/create a "Backups" subfolder, but let's stick to root/configured for now.

    $dbUploaded = App\GoogleIntegration::uploadFile($dbFilePath, $dbFileName);
    $zipUploaded = App\GoogleIntegration::uploadFile($zipFilePath, $zipFileName);

    if ($dbUploaded) {
        echo "DB Uploaded (ID: $dbUploaded). ";
        // Cleanup local DB dump
        unlink($dbFilePath);
    } else {
        echo "DB Upload FAILED. ";
    }

    if ($zipUploaded) {
        echo "Zip Uploaded (ID: $zipUploaded). ";
        // Cleanup local Zip
        unlink($zipFilePath);
    } else {
        echo "Zip Upload FAILED. ";
    }

    echo PHP_EOL;
} else {
    echo "Google Drive not configured. Files kept locally." . PHP_EOL;
}

echo "Backup Complete." . PHP_EOL;
