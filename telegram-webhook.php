<?php

/**
 * DetalStudio - Telegram Webhook Handler
 * Точка входу для Telegram Webhook
 */

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logDir . '/telegram_webhook.log', 
            date('Y-m-d H:i:s') . " - FATAL: {$error['message']} in {$error['file']}:{$error['line']}" . PHP_EOL, FILE_APPEND);
        if (!headers_sent()) {
            http_response_code(200);
            echo "OK";
        }
    }
});

require_once __DIR__ . '/config/config.php';

use App\Telegram;

// Log incoming request for debugging
$input = file_get_contents('php://input');
if ($input) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/telegram_webhook.log', date('Y-m-d H:i:s') . " - RAW: " . $input . PHP_EOL, FILE_APPEND);
}

// Validate Telegram secret_token (if configured)
$webhookSecret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
if ($webhookSecret !== '') {
    $receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($webhookSecret, $receivedToken)) {
        http_response_code(403);
        error_log("Telegram webhook: invalid secret_token from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        exit('Forbidden');
    }
}

// Відправляємо Telegram статус 200 OK і закриваємо з'єднання
ignore_user_abort(true); // Дозволяємо PHP працювати після закриття з'єднання
ob_start();
http_response_code(200);
echo "OK";
header('Connection: close');
header('Content-Length: ' . ob_get_length());
ob_end_flush();
ob_flush();
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request(); // FPM specific
}

// Зауваження: Будь-який подальший вивід echo/print вже не дійде до клієнта, оскільки з'єднання закрите.

// Handle the update
try {
    Telegram::handleWebhook($input);
} catch (\Throwable $e) {
    error_log("Telegram Webhook Error: " . $e->getMessage());
    file_put_contents($logDir . '/telegram_webhook.log', date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
}
