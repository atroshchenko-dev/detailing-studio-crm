<?php

/**
 * PHPUnit Test Bootstrap
 * 
 * Loads composer autoloader and defines minimal stubs
 * so unit tests can run without database or session.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Define constants that config.php normally sets,
// so we can test pure-logic functions without full bootstrap
if (!defined('APP_URL')) {
    define('APP_URL', 'https://test.example.com');
}
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}
