<?php

declare(strict_types=1);

namespace App\Models;

use Exception;

/**
 * Модель послуг автодетейлінгу
 *
 * Управління каталогом послуг: CRUD, кастомні опції,
 * шаблони ресурсів (матеріали), категорії та комісії.
 *
 * @package App\Models
 */
class Service
{

    /**
     * Отримати послугу за ID
     *
     * @param int $id ID послуги
     * @return array|null Дані послуги або null
     */
    public static function getById($id)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM services WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get service: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отримати всі послуги з категоріями
     *
     * @param bool     $activeOnly  Тільки активні послуги
     * @param int|null $categoryId  Фільтр за категорією
     * @return array Масив послуг з назвами категорій
     */
    public static function getAll($activeOnly = true, $categoryId = null)
    {
        try {
            $sql = "SELECT s.*, sc.name as category_name, sc.color_class 
                    FROM services s 
                    LEFT JOIN service_categories sc ON s.category_id = sc.id";
            $where = [];
            $params = [];

            if ($activeOnly) {
                $where[] = "s.is_active = 1";
            }
            if ($categoryId) {
                $where[] = "s.category_id = ?";
                $params[] = $categoryId;
            }

            if ($where) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            $sql .= " ORDER BY sc.display_order, s.name";

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get services: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Створити нову послугу
     *
     * @param array{name: string, category_id: int, base_price: float, commission_type?: string, commission_value?: float, duration_minutes?: int, description?: string, auto_create_task?: int, custom_options?: array} $data
     * @return string|false ID створеної послуги або false при помилці
     */
    public static function create($data)
    {
        try {
            $stmt = db()->prepare("INSERT INTO services (name, category_id, base_price, commission_type, commission_value, duration_minutes, description, auto_create_task) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['category_id'],
                $data['base_price'],
                $data['commission_type'] ?? 'percentage',
                $data['commission_value'] ?? 0,
                $data['duration_minutes'] ?? 0,
                $data['description'] ?? null,
                $data['auto_create_task'] ?? 0
            ]);

            $serviceId = (int)db()->lastInsertId();



            if (!empty($data['custom_options'])) {
                self::syncCustomOptions($serviceId, $data['custom_options']);
            }

            return $serviceId;
        } catch (Exception $e) {
            error_log("Failed to create service: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Оновити послугу
     *
     * Оновлює основні дані, кастомні опції та шаблони ресурсів в одній операції.
     *
     * @param int   $id   ID послуги
     * @param array $data Масив полів для оновлення
     * @return bool true при успіху
     */
    public static function update($id, $data)
    {
        try {
            $allowedFields = ['name', 'category_id', 'base_price', 'commission_type', 'commission_value', 'duration_minutes', 'description', 'is_active', 'auto_create_task'];
            $updates = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates) && !isset($data['custom_options'])) {
                return true;
            }

            if (!empty($updates)) {
                $values[] = $id;
                $sql = "UPDATE services SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                $stmt = db()->prepare($sql);
                $stmt->execute($values);
            }



            if (isset($data['custom_options'])) {
                self::syncCustomOptions($id, $data['custom_options']);
            }

            if (isset($data['resource_templates'])) {
                self::syncResourceTemplates($id, $data['resource_templates']);
            }

            return true;
        } catch (Exception $e) {
            error_log("Failed to update service: " . $e->getMessage());
            return false;
        }
    }




    /**
     * Синхронізувати кастомні опції послуги
     *
     * Видаляє всі існуючі опції та створює нові. Використовує replace strategy.
     *
     * @param int   $serviceId ID послуги
     * @param array $options   Масив опцій [{name: string, price: float}, ...]
     * @return bool true при успіху
     */
    public static function syncCustomOptions($serviceId, $options)
    {
        try {
            db()->prepare("DELETE FROM service_custom_options WHERE service_id = ?")->execute([$serviceId]);
            if (!empty($options)) {
                $stmt = db()->prepare("INSERT INTO service_custom_options (service_id, name, price) VALUES (?, ?, ?)");
                foreach ($options as $opt) {
                    if (!empty($opt['name'])) {
                        $stmt->execute([$serviceId, trim($opt['name']), floatval($opt['price'] ?? 0)]);
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to sync custom options: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати кастомні опції послуги
     *
     * @param int $serviceId ID послуги
     * @return array Масив кастомних опцій
     */
    public static function getCustomOptions($serviceId)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM service_custom_options WHERE service_id = ? ORDER BY id ASC");
            $stmt->execute([$serviceId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get custom options: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Синхронізувати шаблони ресурсів (матеріали) для послуги
     *
     * @param int   $serviceId ID послуги
     * @param array $items     Масив ресурсів [{item_id: int, quantity: float, notes?: string}, ...]
     * @return bool true при успіху
     */
    public static function syncResourceTemplates($serviceId, $items)
    {
        try {
            db()->prepare("DELETE FROM service_resource_templates WHERE service_id = ?")->execute([$serviceId]);
            if (!empty($items)) {
                $stmt = db()->prepare("INSERT INTO service_resource_templates (service_id, warehouse_item_id, quantity, notes) VALUES (?, ?, ?, ?)");
                foreach ($items as $item) {
                    if (!empty($item['item_id'])) {
                        $stmt->execute([
                            $serviceId,
                            $item['item_id'],
                            floatval($item['quantity'] ?? 1),
                            trim($item['notes'] ?? '')
                        ]);
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to sync resource templates: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати шаблони ресурсів послуги з деталями складських позицій
     *
     * @param int $serviceId ID послуги
     * @return array Масив ресурсів з назвами, одиницями та цінами
     */
    public static function getResourceTemplates($serviceId)
    {
        try {
            $stmt = db()->prepare("SELECT srt.*, wi.name as item_name, wi.unit, wi.avg_price 
                                  FROM service_resource_templates srt
                                  JOIN warehouse_items wi ON srt.warehouse_item_id = wi.id
                                  WHERE srt.service_id = ?");
            $stmt->execute([$serviceId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get resource templates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Видалити послугу
     *
     * Перевіряє чи послуга використовується в активних замовленнях.
     * Видаляє пов'язані кастомні опції та шаблони ресурсів.
     *
     * @param int $id ID послуги
     * @return bool|string true при успіху або рядок з помилкою
     */
    public static function delete($id)
    {
        $inTransaction = false;
        try {
            // Check usage in ACTIVE orders only
            // If an order is soft-deleted, we should allow deleting the service,
            // (or at least we interpret "used in orders" as used in active ones).
            // However, this leaves historical data in soft-deleted orders pointing to a non-existent service.
            // But since the order is deleted, maybe it's acceptable.
            // A safer approach is to check if there are any *active* orders using this service.
            $stmt = db()->prepare("SELECT COUNT(*) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.service_id = ? AND o.is_deleted = 0");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                // Determine if we should allow deletion or just set to inactive?
                // For now, prevent hard delete if used in ACTIVE orders, suggest deactivation
                return "Ця послуга використовується в активних замовленнях. Ви можете лише деактивувати її.";
            }

            $inTransaction = db()->inTransaction();
            if (!$inTransaction) {
                db()->beginTransaction();
            }

            // Delete relations

            db()->prepare("DELETE FROM service_custom_options WHERE service_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM service_resource_templates WHERE service_id = ?")->execute([$id]);

            // Delete service
            $stmt = db()->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$id]);

            if (!$inTransaction) {
                db()->commit();
            }
            return true;
        } catch (Exception $e) {
            if (!$inTransaction && db()->inTransaction()) {
                db()->rollBack();
            }
            error_log("Failed to delete service: " . $e->getMessage());
            return "Помилка при видаленні послуги: " . $e->getMessage();
        }
    }
}
