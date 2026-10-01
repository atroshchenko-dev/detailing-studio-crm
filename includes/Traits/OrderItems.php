<?php

declare(strict_types=1);

namespace App\Traits;

use Exception;

/**
 * Order Items — CRUD for order line items and total recalculation
 */
trait OrderItems
{
    /**
     * Get order items
     */
    public static function getItems($orderId)
    {
        try {
            $stmt = db()->prepare("SELECT oi.*, COALESCE(oi.custom_name, s.name) as service_name, sc.name as service_category 
                                   FROM order_items oi
                                   JOIN services s ON oi.service_id = s.id
                                   LEFT JOIN service_categories sc ON s.category_id = sc.id
                                   WHERE oi.order_id = ?");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get order items: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add order item
     */
    public static function addItem($orderId, $serviceId, $quantity = 1, $unitPrice = null, $executorId = null, $customName = null)
    {
        try {
            // Get service price if not provided
            if ($unitPrice === null) {
                $service = \App\Models\Service::getById($serviceId);
                $unitPrice = $service['base_price'] ?? 0;
            }

            $totalPrice = $quantity * $unitPrice;

            // Defensive: prevent exact duplicate items (same service + price + name)
            $checkStmt = db()->prepare(
                "SELECT id FROM order_items WHERE order_id = ? AND service_id = ? AND unit_price = ? AND (custom_name = ? OR (custom_name IS NULL AND ? IS NULL))"
            );
            $checkStmt->execute([$orderId, $serviceId, $unitPrice, $customName, $customName]);
            if ($checkStmt->fetch()) {
                error_log("Order::addItem() — duplicate prevented: order=$orderId, service=$serviceId, price=$unitPrice");
                return false; // Already exists
            }

            $stmt = db()->prepare("INSERT INTO order_items (order_id, service_id, quantity, unit_price, total_price, custom_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$orderId, $serviceId, $quantity, $unitPrice, $totalPrice, $customName]);

            // Recalculate order total
            self::recalculateTotal($orderId);

            // Auto-create task if enabled for service
            $service = $service ?? \App\Models\Service::getById($serviceId);
            if ($service && !empty($service['auto_create_task'])) {
                $order = self::getById($orderId);

                $executors = [];
                if ($executorId) {
                    $executors[] = $executorId;
                }

                \App\Models\Task::create([
                    'title' => $service['name'],
                    'description' => "Автоматично створена задача для послуги: " . $service['name'] . "\nВ замовленні: " . ($order['order_number'] ?? ''),
                    'order_id' => $orderId,
                    'priority' => 'medium',
                    'deadline' => $order['deadline'] ?? null,
                    'executors' => $executors
                ]);
            }

            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to add order item: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update order item
     */
    public static function updateItem($itemId, $data)
    {
        try {
            $allowedFields = ['quantity', 'unit_price', 'total_price', 'notes'];
            $updates = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return true;
            }

            $values[] = $itemId;
            $sql = "UPDATE order_items SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";

            $stmt = db()->prepare($sql);
            $result = $stmt->execute($values);

            // Recalculate order total
            $item = self::getItemById($itemId);
            if ($item) {
                self::recalculateTotal($item['order_id']);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to update order item: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get order item by ID
     */
    private static function getItemById($id)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM order_items WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get order item: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Remove order item
     */
    public static function removeItem($itemId)
    {
        try {
            $item = self::getItemById($itemId);
            if (!$item) return false;

            $stmt = db()->prepare("DELETE FROM order_items WHERE id = ?");
            $result = $stmt->execute([$itemId]);

            // Recalculate order total
            self::recalculateTotal($item['order_id']);

            return $result;
        } catch (Exception $e) {
            error_log("Failed to remove order item: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalculate order total
     */
    private static function recalculateTotal($orderId)
    {
        try {
            $stmt = db()->prepare("SELECT SUM(total_price) as total FROM order_items WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $result = $stmt->fetch();

            $total = $result['total'] ?? 0;

            // Get current discount percent
            $stmt = db()->prepare("SELECT discount_percent FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $discountPercent = $stmt->fetchColumn() ?: 0;

            $discountAmount = ($total * $discountPercent) / 100;

            $stmt = db()->prepare("UPDATE orders SET total_amount = ?, discount_amount = ? WHERE id = ?");
            return $stmt->execute([$total, $discountAmount, $orderId]);
        } catch (Exception $e) {
            error_log("Failed to recalculate order total: " . $e->getMessage());
            return false;
        }
    }
}
