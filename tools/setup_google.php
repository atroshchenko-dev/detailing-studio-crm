<?php
/**
 * Setup Google OAuth Token
 * Run this from CLI
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

require_once __DIR__ . '/../vendor/autoload.php';

$credentialsPath = __DIR__ . '/../config/credentials.json';
$tokenPath = __DIR__ . '/../config/token.json';

if (!file_exists($credentialsPath)) {
    die("Error: config/credentials.json not found.\n");
}

$client = new Google\Client();
$client->setApplicationName('DetalStudio Backup');
$client->setScopes([
    Google\Service\Drive::DRIVE,
    Google\Service\Calendar::CALENDAR
]);
$client->setAuthConfig($credentialsPath);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

// Load existing token if valid
if (file_exists($tokenPath)) {
    $accessToken = json_decode(file_get_contents($tokenPath), true);
    $client->setAccessToken($accessToken);
}

if ($client->isAccessTokenExpired()) {
    // Refresh if possible
    if ($client->getRefreshToken()) {
        try {
            $newAccessToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            // Save updated token
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            echo "Token refreshed successfully.\n";
            exit(0);
        } catch (Exception $e) {
            echo "Error refreshing token: " . $e->getMessage() . "\n";
            // Check if error is "invalid_grant" which means token is revoked/bad
             if (strpos($e->getMessage(), 'invalid_grant') === false) {
                 exit(1); 
             }
             echo "Requesting new token...\n";
        }
    } 
    
    // Request new token
    $authUrl = $client->createAuthUrl();
    echo "Open the following link in your browser:\n\n";
    echo $authUrl . "\n\n";
    echo "Enter verification code: ";
    
    $authCode = trim(fgets(STDIN));
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    
    // Check for errors
    if (array_key_exists('error', $accessToken)) {
        die(join(', ', $accessToken));
    }
    
    // Save token
    if (!file_exists(dirname($tokenPath))) {
        mkdir(dirname($tokenPath), 0755, true);
    }
    
    file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    echo "\nToken saved to config/token.json\n";
} else {
    echo "Token is already valid.\n";
}
