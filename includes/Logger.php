<?php
declare(strict_types=1);

namespace App;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * DetalStudio - Logger Service
 * Wrapper around Monolog for structured logging
 */
class Logger {
    private static $instance = null;
    private $logger;

    private function __construct() {
        // Create the logger
        $this->logger = new MonologLogger('detalstudio');
        
        // Log path: logs/app.log (daily rotation)
        $logPath = __DIR__ . '/../logs/app.log';
        
        // Ensure logs directory exists
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }

        // Keep 14 days of logs, level DEBUG and up
        $handler = new RotatingFileHandler($logPath, 14, MonologLogger::DEBUG);
        
        // Custom format: [2023-10-25 14:00:00] detalstudio.ERROR: Message {"context":"json"} []
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $handler->setFormatter($formatter);
        
        $this->logger->pushHandler($handler);
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getMonolog(): MonologLogger {
        return $this->logger;
    }

    public static function error(string $message, array $context = []): void {
        self::getInstance()->getMonolog()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::getInstance()->getMonolog()->warning($message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::getInstance()->getMonolog()->info($message, $context);
    }
    
    public static function debug(string $message, array $context = []): void {
        self::getInstance()->getMonolog()->debug($message, $context);
    }
}
