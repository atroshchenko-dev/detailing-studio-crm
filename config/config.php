<?php

/**
 * DetalStudio - Configuration
 * Автодетейлінг студія - Конфігурація
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file
$dotenvPath = dirname(__DIR__);
if (file_exists($dotenvPath . '/.env')) {
    if (class_exists(Dotenv::class)) {
        $dotenv = Dotenv::createImmutable($dotenvPath);
        $dotenv->load();
    } else {
        // Fallback: parse .env manually if vlucas/phpdotenv not installed
        $lines = file($dotenvPath . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

use App\Logger;

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Security Headers
\App\Security::sendHeaders();

// Hardened Session Management
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set Custom Exception Handler
set_exception_handler(function ($e) {
    Logger::error("Uncaught Exception: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    // Don't expose sensitive info in production
    http_response_code(500);
    if (ini_get('display_errors')) {
        echo "<b>Fatal Error:</b> " . $e->getMessage();
    } else {
        $errorPage = __DIR__ . '/../views/errors/500_generic.php';
        if (file_exists($errorPage)) {
            include $errorPage;
        } else {
            echo "<h1>500 Internal Server Error</h1><p>Something went wrong. Please check logs.</p>";
        }
    }
    exit;
});

// Set Custom Error Handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $level = 'info';
    switch ($errno) {
        case E_USER_ERROR:
        case E_RECOVERABLE_ERROR:
            $level = 'error';
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $level = 'warning';
            break;
    }

    Logger::$level($errstr, [
        'file' => $errfile,
        'line' => $errline
    ]);

    return false; // Continue normal execution if not fatal
});


// Database Configuration


// Database Configuration
// НАЛАШТУЙТЕ ЦІ ПАРАМЕТРИ ПІД СВОЄГО ХОСТИНГ-ПРОВАЙДЕРА
// ПРИКЛАД: define('DB_HOST', 'localhost');
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Telegram Bot Configuration
define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
define('TELEGRAM_GROUP_CHAT_ID', $_ENV['TELEGRAM_GROUP_CHAT_ID'] ?? '');
define('TELEGRAM_WEBHOOK_URL', $_ENV['TELEGRAM_WEBHOOK_URL'] ?? '');

// Telegram Topics (Threads)
define('TELEGRAM_TOPIC_ORDERS', $_ENV['TELEGRAM_TOPIC_ORDERS'] ?? 79);
define('TELEGRAM_TOPIC_TASKS', $_ENV['TELEGRAM_TOPIC_TASKS'] ?? 80);
define('TELEGRAM_TOPIC_FINANCE', $_ENV['TELEGRAM_TOPIC_FINANCE'] ?? 81);
define('TELEGRAM_TOPIC_ALERTS', $_ENV['TELEGRAM_TOPIC_ALERTS'] ?? 82);
define('TELEGRAM_TOPIC_GENERAL', $_ENV['TELEGRAM_TOPIC_GENERAL'] ?? 87);

// Google API Configuration
$serviceAccountPath = __DIR__ . '/google_service_account.json';
define('GOOGLE_SERVICE_ACCOUNT_JSON', file_exists($serviceAccountPath) ? file_get_contents($serviceAccountPath) : '');
define('GOOGLE_CALENDAR_ID', $_ENV['GOOGLE_CALENDAR_ID'] ?? '');
define('GOOGLE_DRIVE_FOLDER_ID', $_ENV['GOOGLE_DRIVE_FOLDER_ID'] ?? '');


// Application Settings
define('APP_NAME', 'DetalStudio');
define('APP_VERSION', '1.0.2');
define('CACHE_VERSION', APP_VERSION); // Use version for proper browser caching
define('APP_URL', $_ENV['APP_URL'] ?? '');
define('TIMEZONE', 'Europe/Kiev'); // Часовий пояс
define('SESSION_LIFETIME', 86400); // Час життя сесії в секундах (24 години)

// Pagination
define('ITEMS_PER_PAGE', 20);

// File Upload Settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Security Settings
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCK_TIME', 900); // 15 хвилин в секундах

// Notification Settings
define('NOTIFY_TASK_DEADLINE_HOURS', 2); // За скільки годин сповіщувати про дедлайн задачі
define('NOTIFY_ORDER_DEADLINE_HOURS', 24); // За скільки годин сповіщувати про дедлайн замовлення

// Backup Settings
define('BACKUP_RETENTION_DAYS', 7);
define('BACKUP_TIME', '03:00');

// Telegram Group Notification Filters
define('TELEGRAM_NOTIFY_GROUP_NEW_ORDER', true);
define('TELEGRAM_NOTIFY_GROUP_ORDER_STATUS', true);
define('TELEGRAM_NOTIFY_GROUP_NEW_TASK', true);
define('TELEGRAM_NOTIFY_GROUP_TASK_STATUS', true);

// Currency Settings
if (!defined('CURRENCY')) {
    define('CURRENCY', 'UAH');
}
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '₴');
}

// Email / SMTP Settings
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? ''); // your-email@gmail.com
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? ''); // App Password
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? 'noreply@example.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'DetalStudio');
define('EMAIL_NOTIFICATIONS_ENABLED', false); // Set to true when SMTP configured

date_default_timezone_set(TIMEZONE);

/**
 * Database global helper
 */
if (!function_exists('db')) {
    function db()
    {
        return \App\Database::getInstance()->getConnection();
    }
}

if (!function_exists('dd')) {
    /**
     * Helper function for debugging (CLI only in production)
     */
    function dd($var)
    {
        if (php_sapi_name() === 'cli') {
            var_dump($var);
            die();
        }
        // In web context, log instead of dumping to browser
        error_log('dd() called: ' . print_r($var, true));
        http_response_code(500);
        die('Internal error');
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect helper
     */
    function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}

/**
 * Format currency
 */
function format_currency($amount)
{
    return \App\Utils\Helper::formatCurrency($amount);
}

/**
 * Format date
 */
function format_date($date, $format = 'd.m.Y H:i')
{
    return \App\Utils\Helper::formatDate($date, $format);
}

/**
 * Get status badge class
 */
function get_status_badge_class($status)
{
    return \App\Utils\Helper::getBadgeClass('status', $status);
}

/**
 * Get priority badge class
 */
function get_priority_badge_class($priority)
{
    return \App\Utils\Helper::getBadgeClass('priority', $priority);
}

/**
 * Convert empty string to null (for DB)
 */
function empty_to_null($value)
{
    return \App\Utils\Helper::emptyToNull($value);
}

/**
 * Generate unique order number
 */
function generate_order_number()
{
    return \App\Utils\Helper::generateOrderNumber();
}

/**
 * Generate unique purchase request number
 */
function generate_purchase_request_number()
{
    return \App\Utils\Helper::generatePurchaseRequestNumber();
}

/**
 * Core Library Inclusions & Compatibility
 * Глобальне підключення основних бібліотек та аліаси для зворотної сумісності
 */
require_once __DIR__ . '/../includes/events_init.php';
require_once __DIR__ . '/../includes/aliases.php';
