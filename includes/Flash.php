<?php
declare(strict_types=1);

namespace App;

/**
 * Flash Messages Class
 */

class Flash {
    
    const SUCCESS = 'success';
    const ERROR = 'danger'; // Bootstrap uses 'danger' for errors
    const INFO = 'info';
    const WARNING = 'warning';
    
    /**
     * Set a flash message
     */
    public static function set($type, $message) {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Get and clear messages
     */
    public static function get() {
        if (!isset($_SESSION['flash_messages'])) {
            return [];
        }
        
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    
    /**
     * Helper for success
     */
    public static function success($message) {
        self::set(self::SUCCESS, $message);
    }
    
    /**
     * Helper for error
     */
    public static function error($message) {
        self::set(self::ERROR, $message);
    }
}
