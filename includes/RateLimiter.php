<?php
namespace App;

class RateLimiter {
    /**
     * Record a login attempt
     */
    public static function recordLoginAttempt(string $username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = db()->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        $stmt->execute([$ip, $username]);
    }

    /**
     * Check if an IP or username is blocked
     * @return bool True if blocked
     */
    public static function isLoginBlocked(string $username): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
        $lockTime = defined('LOGIN_LOCK_TIME') ? LOGIN_LOCK_TIME : 900; // 15 mins
        
        $since = date('Y-m-d H:i:s', time() - $lockTime);
        
        // Check by IP
        $stmt = db()->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE ip_address = ? AND attempted_at > ?
        ");
        $stmt->execute([$ip, $since]);
        if ($stmt->fetchColumn() >= $maxAttempts) {
            return true;
        }
        
        // Check by Username
        $stmt = db()->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE username = ? AND attempted_at > ?
        ");
        $stmt->execute([$username, $since]);
        if ($stmt->fetchColumn() >= $maxAttempts) {
            return true;
        }
        
        return false;
    }

    /**
     * Clear attempts after successful login
     */
    public static function clearLoginAttempts(string $username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = db()->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $stmt->execute([$ip, $username]);
    }
}
