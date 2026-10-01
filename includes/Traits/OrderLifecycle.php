<?php

declare(strict_types=1);

namespace App\Traits;

use App\Audit;
use App\GoogleIntegration;
use Exception;

/**
 * Order Lifecycle — soft delete, restore, hard delete, tracking, task completion check
 */
trait OrderLifecycle
{
    public static function softDelete($id)
    {
        try {
            $stmt = db()->prepare("UPDATE orders SET is_deleted = 1 WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result) {
                Audit::log('order_deleted', 'orders', $id);

                // Dispatch OrderDeleted
                $order = self::getById($id);
                if ($order) {
                    \App\Events\EventDispatcher::dispatch(new \App\Events\OrderDeleted((int)$id, $order));
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to soft delete order: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore soft deleted order
     */
    public static function restore($id)
    {
        try {
            $stmt = db()->prepare("UPDATE orders SET is_deleted = 0 WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result) {
                Audit::log('order_restored', 'orders', $id);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to restore order: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Permanent delete order
     */
    public static function hardDelete($id)
    {
        $db = db();
        try {
            $order = self::getById($id);
            if (!$order) return false;

            $db->beginTransaction();

            // 1. Delete tasks and their dependants
            $stmtTasks = $db->prepare("SELECT id, google_event_id FROM tasks WHERE order_id = ?");
            $stmtTasks->execute([$id]);
            $tasks = $stmtTasks->fetchAll();

            foreach ($tasks as $task) {
                $db->prepare("DELETE FROM task_executors WHERE task_id = ?")->execute([$task['id']]);
                $db->prepare("DELETE FROM task_comments WHERE task_id = ?")->execute([$task['id']]);
                if (!empty($task['google_event_id'])) {
                    GoogleIntegration::deleteCalendarEvent($task['google_event_id']);
                }
                $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$task['id']]);
            }

            // 2. Delete order items
            $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]);

            // 3. Delete order payments
            $db->prepare("DELETE FROM order_payments WHERE order_id = ?")->execute([$id]);

            // 4. Delete order comments
            $db->prepare("DELETE FROM order_comments WHERE order_id = ?")->execute([$id]);

            // 5. Delete telegram notifications for this order
            $db->prepare("DELETE FROM telegram_notifications WHERE entity_type = 'order' AND entity_id = ?")->execute([$id]);

            // 6. Delete order from Google Calendar
            if (!empty($order['google_event_id'])) {
                GoogleIntegration::deleteCalendarEvent($order['google_event_id']);
            }
            if (!empty($order['google_arrival_event_id'])) {
                GoogleIntegration::deleteCalendarEvent($order['google_arrival_event_id']);
            }

            // 7. Delete order itself
            $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);

            $db->commit();

            Audit::log('order_permanently_deleted', 'orders', $id, null, ['order_number' => $order['order_number']]);

            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to hard delete order: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get order by Number and Phone (for public tracking)
     */
    public static function getForTracking($orderNumber, $phone)
    {
        try {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

            $stmt = db()->prepare("SELECT o.*, 
                                  c.name as client_name, c.phone as client_phone,
                                  car.make, car.model, car.license_plate
                                  FROM orders o
                                  LEFT JOIN clients c ON o.client_id = c.id
                                  LEFT JOIN cars car ON o.car_id = car.id
                                  WHERE o.order_number = ? AND o.is_deleted = 0");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch();

            if (!$order) {
                return null;
            }

            $dbPhone = preg_replace('/[^0-9]/', '', $order['client_phone']);

            if (!$cleanPhone || strlen($cleanPhone) < 4) {
                return null;
            }

            if ($dbPhone === $cleanPhone || strpos($dbPhone, $cleanPhone) !== false) {
                return $order;
            }

            return null;
        } catch (Exception $e) {
            error_log("Failed to get order for tracking: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if all tasks for order are completed
     */
    public static function checkAllTasksCompleted($orderId)
    {
        try {
            $stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE order_id = ? AND status NOT IN ('completed', 'cancelled') AND is_deleted = 0");
            $stmt->execute([$orderId]);
            $count = (int)$stmt->fetchColumn();

            return $count === 0;
        } catch (Exception $e) {
            error_log("Failed to check all tasks completed: " . $e->getMessage());
            return false;
        }
    }
}
