<?php

declare(strict_types=1);

namespace App;

/**
 * CSRF-захист для форм та API
 *
 * Генерує токени, вбудовує в HTML-форми та мета-теги,
 * перевіряє токени на POST-запитах та API-викликах.
 * API перевірка підтримує X-CSRF-Token header.
 *
 * @package App
 */
class Csrf
{

    /**
     * Get or generate CSRF token
     */
    public static function getToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Check CSRF token
     */
    public static function check(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generate hidden input field for HTML forms
     */
    public static function input(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Generate meta tag for AJAX requests.
     * Include this in <head> of your layout.
     */
    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . self::getToken() . '">';
    }

    /**
     * Verify CSRF token for API/AJAX POST requests.
     * Checks both POST body (csrf_token) and X-CSRF-Token header.
     * Returns false and sends 403 JSON response if invalid.
     */
    public static function verifyApi(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true; // Only verify POST requests
        }

        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (!self::check($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token invalid. Оновіть сторінку.']);
            exit;
        }

        return true;
    }

    /**
     * Verify request (legacy helper — uses die() for non-API)
     */
    public static function verify(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::check($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                $errorPage = __DIR__ . '/../views/errors/403.php';
                if (file_exists($errorPage)) {
                    include $errorPage;
                } else {
                    echo '<b>403 Forbidden</b>: CSRF validation failed. Refresh the page and try again.';
                }
                exit;
            }
        }
    }
}
