<?php

/**
 * DetalStudio - Migration Runner
 * Automatically applies SQL migrations from the migrations/ directory
 */

require_once __DIR__ . '/config/config.php';

// Check if running from CLI or authorized user
// CLI preferred, director-only for web
if (php_sapi_name() !== 'cli') {
    Auth::requireAuth();
    Auth::requireAnyRole(['director']);
}

class MigrationRunner
{
    private $db;
    private $migrationsDir;

    public function __construct()
    {
        $this->db = db();
        $this->migrationsDir = __DIR__ . '/migrations';
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable()
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration` varchar(255) NOT NULL,
                `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function run()
    {
        echo "Starting migrations...\n";

        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);

        $applied = $this->getAppliedMigrations();
        $count = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $applied)) {
                continue;
            }

            echo "Applying $filename... ";

            try {
                $sql = file_get_contents($file);
                if (empty(trim($sql))) {
                    echo "Skipped (empty)\n";
                    continue;
                }

                // Execute SQL (handling multiple statements)
                try {
                    $this->db->exec($sql);
                } catch (Exception $sqlError) {
                    // Ignore duplicate entry or duplicate column/table errors
                    if ($sqlError->getCode() == '23000' || strpos($sqlError->getMessage(), '1062') !== false || strpos($sqlError->getMessage(), '1060') !== false || strpos($sqlError->getMessage(), '1061') !== false) {
                        echo "Warning: Duplicate entry/column skipped. ";
                    } else {
                        throw $sqlError;
                    }
                }

                // Register migration
                $stmt = $this->db->prepare("INSERT IGNORE INTO migrations (migration) VALUES (?)");
                $stmt->execute([$filename]);

                echo "Done\n";
                $count++;
            } catch (Exception $e) {
                echo "FAILED: " . $e->getMessage() . "\n";
                return false;
            }
        }

        echo "Migrations finished. Applied $count new migration(s).\n";
        return true;
    }

    private function getAppliedMigrations()
    {
        $stmt = $this->db->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

$runner = new MigrationRunner();
if ($runner->run()) {
    exit(0);
} else {
    exit(1);
}
