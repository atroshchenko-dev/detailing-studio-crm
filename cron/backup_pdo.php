<?php

/**
 * Database Backup (PDO version for Docker)
 * Works without mysqldump
 * 
 * @deprecated Use backup_database.php instead — it has GDrive upload, logging, Telegram alerts, and DB records.
 *             This script is kept as a lightweight fallback for environments without those features.
 */

require_once __DIR__ . '/../config/config.php';

// CLI-only access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

class PDOBackup
{

    private $backupDir;

    public function __construct()
    {
        $this->backupDir = __DIR__ . '/../backups/database/';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function createBackup()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.sql";
        $filepath = $this->backupDir . $filename;

        try {
            $sql = $this->generateSQL();
            file_put_contents($filepath, $sql);

            // Compress
            $gzFilepath = $filepath . '.gz';
            $this->compressFile($filepath, $gzFilepath);
            unlink($filepath);

            $filesize = filesize($gzFilepath);
            $filesizeMB = round($filesize / 1024 / 1024, 2);

            echo "✓ Backup created: $filename.gz ($filesizeMB MB)\n";

            // Clean old backups
            $this->cleanOldBackups(7);

            return ['success' => true, 'filename' => $filename . '.gz', 'size' => $filesizeMB];
        } catch (Exception $e) {
            echo "✗ Backup failed: " . $e->getMessage() . "\n";
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function generateSQL()
    {
        $sql = "-- DetalStudio Database Backup\n";
        $sql .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Get all tables
        $stmt = db()->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Get CREATE TABLE
            $stmtCreate = db()->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmtCreate->fetch(PDO::FETCH_NUM);

            $sql .= "-- Table: $table\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createTable[1] . ";\n\n";

            // Get data
            $stmtData = db()->query("SELECT * FROM `$table`");
            $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) > 0) {
                $sql .= "-- Data for $table\n";

                foreach ($rows as $row) {
                    $values = array_map(function ($val) {
                        if ($val === null) return 'NULL';
                        return "'" . addslashes($val) . "'";
                    }, array_values($row));

                    $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    private function compressFile($source, $destination)
    {
        $fp = gzopen($destination, 'w9');
        $file = fopen($source, 'rb');
        while (!feof($file)) {
            gzwrite($fp, fread($file, 1024 * 512));
        }
        fclose($file);
        gzclose($fp);
    }

    private function cleanOldBackups($days)
    {
        $files = glob($this->backupDir . 'backup_*.sql.gz');
        $cutoff = time() - ($days * 24 * 60 * 60);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

$backup = new PDOBackup();
$result = $backup->createBackup();

exit($result['success'] ? 0 : 1);
