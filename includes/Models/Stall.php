<?php
declare(strict_types=1);

namespace App\Models;

use Exception;
use PDO;

/**
 * DetalStudio - Stall Model
 * Represents a detailing box/stall/post
 */
class Stall {
    
    /**
     * Отримати всі робочі місця/бокси
     *
     * @param bool $activeOnly Тільки активні (default: true)
     * @return array Масив робочих місць
     */
    public static function getAll(bool $activeOnly = true): array {
        try {
            $sql = "SELECT * FROM stalls";
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY name ASC";
            
            $stmt = db()->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get stalls: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Отримати бокс за ID
     *
     * @param int $id ID боксу
     * @return array|null Дані боксу або null
     */
    public static function getById(int $id): ?array {
        try {
            $stmt = db()->prepare("SELECT * FROM stalls WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            error_log("Failed to get stall by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Створити новий бокс
     *
     * @param string $name        Назва боксу
     * @param string $description Опис (default: '')
     * @return int|false ID створеного боксу або false
     */
    public static function create(string $name, string $description = ''): int|bool {
        try {
            $stmt = db()->prepare("INSERT INTO stalls (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            return (int)db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to create stall: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Оновити бокс
     *
     * @param int   $id   ID боксу
     * @param array $data Поля для оновлення (name, description, is_active)
     * @return bool true при успіху
     */
    public static function update(int $id, array $data): bool {
        try {
            $fields = [];
            $params = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'description', 'is_active'])) {
                    $fields[] = "$key = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($fields)) return false;
            
            $params[] = $id;
            $sql = "UPDATE stalls SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = db()->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Failed to update stall: " . $e->getMessage());
            return false;
        }
    }
}
