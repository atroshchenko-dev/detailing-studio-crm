<?php
declare(strict_types=1);

namespace App\Models;

use App\Audit;
use Exception;
use PDO;

/**
 * Модель системних налаштувань (key-value store)
 *
 * Використовує in-memory кеш для мінімізації запитів до БД.
 *
 * @package App\Models
 */
class Setting {
    
    private static $cache = [];

    /**
     * Отримати значення налаштування за ключем
     *
     * @param string      $key     Ключ налаштування
     * @param string|null $default Значення за замовчуванням
     * @return string|null Значення налаштування або default
     */
    public static function get($key, $default = null) {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $stmt = db()->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $value = $result ? $result['value'] : $default;
            self::$cache[$key] = $value;
            
            return $value;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Встановити значення налаштування (INSERT ON DUPLICATE KEY UPDATE)
     *
     * @param string $key   Ключ налаштування
     * @param string $value Нове значення
     * @return bool true при успіху
     */
    public static function set($key, $value) {
        try {
            $stmt = db()->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
            $success = $stmt->execute([$key, $value, $value]);
            
            if ($success) {
                self::$cache[$key] = $value;
                
                // Log action
                Audit::log('setting_updated', 'settings', 0, null, ['key' => $key, 'value' => $value]);
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("Failed to set setting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати всі налаштування
     *
     * @return array Масив всіх налаштувань відсортованих за ключем
     */
    public static function getAll() {
        try {
            $stmt = db()->query("SELECT * FROM settings ORDER BY `key`");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}
