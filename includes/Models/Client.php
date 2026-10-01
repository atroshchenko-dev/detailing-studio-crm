<?php
declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Audit;
use Exception;

/**
 * Модель клієнтів студії
 *
 * Забезпечує CRUD операції для клієнтської бази,
 * включаючи пошук, статистику та програму лояльності.
 *
 * @package App\Models
 */
class Client {
    
    /**
     * Створити нового клієнта
     *
     * @param array{name: string, phone: string, email?: string, notes?: string} $data Дані клієнта
     * @return string|false ID створеного клієнта або false при помилці
     */
    public static function create($data) {
        try {
            $formattedPhone = \App\Utils\Helper::formatPhone($data['phone']);
            $stmt = db()->prepare("INSERT INTO clients (name, phone, email, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $formattedPhone,
                $data['email'] ?? null,
                $data['notes'] ?? null
            ]);
            
            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to create client: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Отримати клієнта за ID
     *
     * @param int $id ID клієнта
     * @return array|null Дані клієнта або null якщо не знайдено
     */
    public static function getById($id) {
        try {
            $stmt = db()->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get client: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Отримати всіх клієнтів з кількістю їх автомобілів
     *
     * @return array Масив клієнтів відсортованих за ім'ям
     */
    public static function getAll() {
        try {
            $sql = "SELECT c.*, COUNT(car.id) as cars_count 
                    FROM clients c
                    LEFT JOIN cars car ON c.id = car.client_id
                    GROUP BY c.id
                    ORDER BY c.name";
            $stmt = db()->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get clients: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Оновити дані клієнта
     *
     * @param int   $id   ID клієнта
     * @param array $data Масив полів для оновлення (name, phone, email, notes)
     * @return bool true при успіху
     */
    public static function update($id, $data) {
        try {
            $allowedFields = ['name', 'phone', 'email', 'notes'];
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
            $sql = "UPDATE clients SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = db()->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Failed to update client: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Пошук клієнтів за ім'ям або телефоном
     *
     * @param string $query Пошуковий рядок (мін. фрагмент імені або номера)
     * @return array Масив знайдених клієнтів (макс. 20)
     */
    public static function search($query) {
        try {
            $sql = "SELECT c.*, COUNT(car.id) as cars_count 
                    FROM clients c
                    LEFT JOIN cars car ON c.id = car.client_id
                    WHERE c.name LIKE ? OR c.phone LIKE ?
                    GROUP BY c.id
                    ORDER BY c.name 
                    LIMIT 20";
            $stmt = db()->prepare($sql);
            $search = "%$query%";
            $stmt->execute([$search, $search]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to search clients: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Видалити клієнта
     *
     * Перевіряє наявність активних замовлень перед видаленням.
     * Soft-deleted замовлення очищуються для уникнення FK constraint.
     *
     * @param int $id ID клієнта
     * @return bool|string true при успіху, рядок з помилкою якщо є активні замовлення
     */
    public static function delete($id) {
        try {
            // Check if client has active orders
            $stmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ? AND is_deleted = 0");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                return "Cannot delete client with existing active orders.";
            }

            // Cleanup soft-deleted orders to allow client deletion (avoid FK constraint)
            try {
                // First delete linked headers/items/tasks if necessary? 
                // We'll rely on cascading or simple order deletion for now.
                // If this fails, the user will get a generic error, which is better than being blocked by "deleted" orders.
                $stmt = db()->prepare("DELETE FROM orders WHERE client_id = ? AND is_deleted = 1");
                $stmt->execute([$id]);
            } catch (Exception $e) {
                // Log but continue - maybe FK handles it or it will fail on client delete
                error_log("Failed to cleanup deleted orders: " . $e->getMessage());
            }

            $stmt = db()->prepare("DELETE FROM clients WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Failed to delete client: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати фінансову статистику клієнта
     *
     * Розраховує загальну суму замовлень, кількість та середній чек
     * на основі закритих (closed) замовлень.
     *
     * @param int $id ID клієнта
     * @return array{total_orders: int, total_spent: float, avg_order: float}
     */
    public static function getStats($id) {
        try {
            $stmt = db()->prepare("
                SELECT 
                    COUNT(id) as total_orders,
                    SUM(total_amount - discount_amount) as total_spent,
                    AVG(total_amount - discount_amount) as avg_order
                FROM orders 
                WHERE client_id = ? AND is_deleted = 0 AND status = 'closed'
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get client stats: " . $e->getMessage());
            return ['total_orders' => 0, 'total_spent' => 0, 'avg_order' => 0];
        }
    }

    /**
     * Розрахувати відсоток знижки за програмою лояльності
     *
     * Пороги: 10000₴ → 3%, 25000₴ → 5%, 50000₴ → 7%, 100000₴ → 10%
     *
     * @param int $id ID клієнта
     * @return int Відсоток знижки (0, 3, 5, 7 або 10)
     */
    public static function getLoyaltyDiscount($id) {
        $stats = self::getStats($id);
        $total = $stats['total_spent'] ?? 0;

        if ($total >= 100000) return 10;
        if ($total >= 50000) return 7;
        if ($total >= 25000) return 5;
        if ($total >= 10000) return 3;
        
        return 0;
    }
}
