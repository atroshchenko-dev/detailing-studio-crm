<?php

declare(strict_types=1);

namespace App;

use Exception;
use PDO;

/**
 * DetalStudio - Audit Log System
 * Система аудиту змін
 */

class Audit
{

    /**
     * Log action to audit log
     */
    public static function log($action, $tableName, $recordId, $oldValue = null, $newValue = null)
    {
        try {
            $userId = Auth::getUserId();
            // Translate 0 to null for DB foreign key compatibility
            if ($userId === 0) {
                $userId = null;
            }

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            // Handle null record_id (e.g. login failures)
            if ($recordId === null) {
                $recordId = 0;
            }

            $stmt = db()->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $action,
                $tableName,
                $recordId,
                $oldValue ? json_encode($oldValue) : null,
                $newValue ? json_encode($newValue) : null,
                $ipAddress
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log order changes
     */
    public static function logOrderChange($orderId, $oldData, $newData)
    {
        $changes = [];

        $trackedFields = ['status', 'total_amount', 'paid_amount', 'deadline', 'notes'];

        foreach ($trackedFields as $field) {
            if (array_key_exists($field, $oldData) && array_key_exists($field, $newData) && $oldData[$field] != $newData[$field]) {
                $changes[$field] = [
                    'old' => $oldData[$field],
                    'new' => $newData[$field]
                ];
            }
        }

        if (!empty($changes)) {
            $oldToLog = [];
            $newToLog = [];
            foreach ($changes as $key => $values) {
                $oldToLog[$key] = $values['old'];
                $newToLog[$key] = $values['new'];
            }
            self::log('order_updated', 'orders', $orderId, $oldToLog, $newToLog);
        }
    }

    /**
     * Log task changes
     */
    public static function logTaskChange($taskId, $oldData, $newData)
    {
        $changes = [];

        $trackedFields = ['status', 'executor_id', 'deadline', 'priority', 'title', 'description'];

        foreach ($trackedFields as $field) {
            if (array_key_exists($field, $oldData) && array_key_exists($field, $newData) && $oldData[$field] != $newData[$field]) {
                $changes[$field] = [
                    'old' => $oldData[$field],
                    'new' => $newData[$field]
                ];
            }
        }

        if (!empty($changes)) {
            $oldToLog = [];
            $newToLog = [];
            foreach ($changes as $key => $values) {
                $oldToLog[$key] = $values['old'];
                $newToLog[$key] = $values['new'];
            }
            self::log('task_updated', 'tasks', $taskId, $oldToLog, $newToLog);
        }
    }

    /**
     * Log warehouse transaction
     */
    public static function logWarehouseTransaction($itemId, $type, $quantity, $orderId = null, $taskId = null, $purchaseId = null)
    {
        $action = "warehouse_$type";
        $details = [
            'quantity' => $quantity,
            'order_id' => $orderId,
            'task_id' => $taskId,
            'purchase_id' => $purchaseId
        ];

        self::log($action, 'warehouse_items', $itemId, null, $details);
    }

    /**
     * Log payment
     */
    public static function logPayment($orderId, $amount, $method, $notes = null)
    {
        $details = [
            'amount' => $amount,
            'method' => $method,
            'notes' => $notes
        ];

        self::log('payment_added', 'orders', $orderId, null, $details);
    }

    /**
     * Log purchase request status change
     */
    public static function logPurchaseStatusChange($purchaseId, $oldStatus, $newStatus)
    {
        $details = [
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ];

        self::log('purchase_status_changed', 'purchase_requests', $purchaseId, null, $details);
    }

    /**
     * Get logs for specific entity
     */
    public static function getLogs($tableName, $recordId)
    {
        return self::getLog([
            'table_name' => $tableName,
            'record_id' => $recordId
        ]);
    }

    /**
     * Get audit log entries
     */
    public static function getLog($filters = [])
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['user_id'])) {
                $where[] = "al.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['table_name'])) {
                $where[] = "al.table_name = ?";
                $params[] = $filters['table_name'];
            }

            if (!empty($filters['record_id'])) {
                $where[] = "al.record_id = ?";
                $params[] = $filters['record_id'];
            }

            if (!empty($filters['action'])) {
                $where[] = "al.action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "DATE(al.created_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "DATE(al.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            $whereSql = $where ? "WHERE " . implode(' AND ', $where) : "";

            $sql = "SELECT 
                al.*,
                u.full_name as user_name,
                u.username,
                u.telegram_username,
                u.telegram_chat_id
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            $whereSql
            ORDER BY al.created_at DESC
            LIMIT 1000";

            $stmt = db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get audit log: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activity
     */
    public static function getRecentActivity($limit = 50)
    {
        try {
            $stmt = db()->prepare("SELECT 
                al.*,
                u.full_name as user_name,
                u.username,
                u.telegram_username,
                u.telegram_chat_id
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?");
            $stmt->execute([$limit]);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get recent activity: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean old audit logs (keep last 6 months)
     */
    public static function cleanOldLogs()
    {
        try {
            $stmt = db()->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to clean old audit logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all audit logs
     */
    public static function clearAllLogs()
    {
        try {
            $stmt = db()->prepare("TRUNCATE TABLE audit_log");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to clear all audit logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get action description in Ukrainian
     */
    public static function getActionDescription($action)
    {
        $descriptions = [
            'login_success' => 'Успішний вхід в систему',
            'login_failed' => 'Невдала спроба входу',
            'order_created' => 'Створення замовлення',
            'order_updated' => 'Оновлення замовлення',
            'order_status_updated' => 'Зміна статусу замовлення',
            'order_deleted' => 'Видалення замовлення',
            'order_restored' => 'Відновлення замовлення',
            'order_permanently_deleted' => 'Остаточне видалення замовлення',
            'task_created' => 'Створення задачі',
            'task_updated' => 'Оновлення задачі',
            'task_deleted' => 'Видалення задачі',
            'task_restored' => 'Відновлення задачі',
            'task_permanently_deleted' => 'Остаточне видалення задачі',
            'payment_added' => 'Додавання платежу',
            'payment_created' => 'Створення виплати співробітнику',
            'payment_updated' => 'Оновлення виплати співробітнику',
            'payment_deleted' => 'Видалення виплати співробітнику',
            'warehouse_in' => 'Прихід на склад',
            'warehouse_out' => 'Списання зі складу',
            'warehouse_adjustment' => 'Коригування залишків',
            'purchase_created' => 'Створення заявки на закупівлю',
            'purchase_status_changed' => 'Зміна статусу закупівлі',
            'purchase_deleted' => 'Видалення заявки на закупівлю',
            'expense_created' => 'Створення витрати',
            'user_created' => 'Створення користувача',
            'user_updated' => 'Оновлення користувача',
            'client_created' => 'Створення клієнта',
            'car_created' => 'Створення автомобіля',
            'service_created' => 'Створення послуги',
            'setting_updated' => 'Оновлення налаштувань',
            'audit_cleared' => 'Очищення журналу аудиту',
            'log_cleared' => 'Очищення системного журналу'
        ];

        return $descriptions[$action] ?? $action;
    }
    /**
     * Get all unique action types from DB for filter
     */
    public static function getActionTypes()
    {
        try {
            $stmt = db()->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }
}
