<?php
/**
 * Setup Telegram Bot Menu
 */
require_once __DIR__ . '/../config/config.php';

if (php_sapi_name() !== 'cli') {
    die("CLI only");
}

echo "📢 Setting up Telegram Bot Commands...\n";

$result = Telegram::setupBotCommands();

if ($result['ok']) {
    echo "✅ Success! Commands updated.\n";
} else {
    echo "❌ Failed: " . json_encode($result) . "\n";
}
