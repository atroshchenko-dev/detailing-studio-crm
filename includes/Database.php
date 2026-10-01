<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use Exception;

/**
 * Singleton PDO-підключення до бази даних
 *
 * Використовує константи DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
 * із config.php (via .env).
 *
 * @package App
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        // Assume constants are defined in config.php
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Помилка підключення до бази даних");
        }
    }
    
    /**
     * Отримати єдиний екземпляр Database
     *
     * @return self
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Отримати PDO-підключення
     *
     * @return PDO
     */
    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Виконати SQL-запит з параметрами
     *
     * @param string $sql    SQL-запит з плейсхолдерами
     * @param array  $params Параметри для prepared statement
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Скинути singleton (розірвати підключення)
     *
     * Використовується в worker.php при втраті з'єднання.
     *
     * @return void
     */
    public static function reset(): void {
        self::$instance = null;
    }
}
