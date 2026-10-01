<?php
require_once __DIR__ . '/../config/config.php';

echo "<h3>Telegram Webhook Setup</h3>";

$token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';
$webhookUrl = defined('TELEGRAM_WEBHOOK_URL') ? TELEGRAM_WEBHOOK_URL : '';

if (empty($token)) {
    die("<b style='color: red;'>Error: TELEGRAM_BOT_TOKEN is not defined in config.php</b>");
}

if (empty($webhookUrl)) {
    die("<b style='color: red;'>Error: TELEGRAM_WEBHOOK_URL is not defined in config.php</b>");
}

echo "<b>Bot Token:</b> " . substr($token, 0, 10) . "..." . "<br>";
echo "<b>Target Webhook URL:</b> " . htmlspecialchars($webhookUrl) . "<br><br>";

$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhookUrl);

echo "Connecting to Telegram...<br>";

$response = file_get_contents($apiUrl);
$result = json_decode($response, true);

if ($result && $result['ok']) {
    echo "<h4 style='color: green;'>✅ Webhook successfully set!</h4>";
    echo "<pre>" . print_r($result, true) . "</pre>";
} else {
    echo "<h4 style='color: red;'>❌ Failed to set webhook.</h4>";
    echo "<pre>" . print_r($result ?: $response, true) . "</pre>";
}

echo "<br><p><a href='../index.php'>Back to Dashboard</a></p>";
