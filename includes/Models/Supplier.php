<?php
declare(strict_types=1);

namespace App\Models;

use Exception;

/**
 * Модель постачальників
 *
 * @package App\Models
 */
class Supplier {
    
    /**
     * Створити нового постачальника
     *
     * @param array{name: string, contact_person?: string, phone?: string, email?: string, address?: string, notes?: string} $data
     * @return string|false ID створеного постачальника або false
     */
    public static function create($data) {
        try {
            $formattedPhone = isset($data['phone']) ? \App\Utils\Helper::formatPhone($data['phone']) : null;
            $stmt = db()->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['contact_person'] ?? null,
                $formattedPhone,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['notes'] ?? null
            ]);
            
            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to create supplier: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Отримати постачальника за ID
     *
     * @param int $id ID постачальника
     * @return array|null Дані постачальника або null
     */
    public static function getById($id) {
        try {
            $stmt = db()->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get supplier: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Отримати всіх постачальників
     *
     * @param bool $activeOnly Тільки активні (default: true)
     * @return array Масив постачальників
     */
    public static function getAll($activeOnly = true) {
        try {
            $sql = "SELECT * FROM suppliers";
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY name";
            
            $stmt = db()->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get suppliers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Оновити постачальника
     *
     * @param int   $id   ID постачальника
     * @param array $data Масив полів для оновлення
     * @return bool true при успіху
     */
    public static function update($id, $data) {
        try {
            $allowedFields = ['name', 'contact_person', 'phone', 'email', 'address', 'notes', 'is_active'];
            $updates = [];
            $values = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = ($field === 'phone') ? \App\Utils\Helper::formatPhone($data[$field]) : $data[$field];
                }
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $values[] = $id;
            $sql = "UPDATE suppliers SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = db()->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Failed to update supplier: " . $e->getMessage());
            return false;
        }
    }
}
