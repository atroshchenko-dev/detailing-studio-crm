<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Audit;
use App\Telegram;
use App\GoogleIntegration;
use Exception;

/**
 * Orders Model
 *
 * Refactored: items, payments/comments, and lifecycle methods
 * extracted into traits for maintainability.
 * All public API remains identical — callers don't need changes.
 */
class Order
{
    use \App\Traits\OrderItems;
    use \App\Traits\OrderPayments;
    use \App\Traits\OrderLifecycle;

    /**
     * Create new order
     */
    public static function create($data)
    {
        try {
            $orderNumber = generate_order_number();
            $accessToken = bin2hex(random_bytes(16)); // 32 chars

            $workStart = \App\Models\Setting::get('work_day_start', '09:00');
            $workEnd = \App\Models\Setting::get('work_day_end', '18:00');

            // Handle default times for date-only inputs
            $plannedStart = $data['planned_start'] ?? null;
            if ($plannedStart && strlen($plannedStart) === 10) { // Y-m-d
                $plannedStart .= ' ' . $workStart . ':00';
            }

            $deadline = $data['deadline'] ?? null;
            if ($deadline && strlen($deadline) === 10) { // Y-m-d
                $deadline .= ' ' . $workEnd . ':00';
            }

            $stmt = db()->prepare("INSERT INTO orders (order_number, client_id, car_id, manager_id, status, total_amount, notes, deadline, access_token, stall_id, planned_start, planned_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $orderNumber,
                empty_to_null($data['client_id'] ?? null),
                empty_to_null($data['car_id'] ?? null),
                $data['manager_id'] ?? Auth::getUserId(),
                $data['status'] ?? 'new',
                $data['total_amount'] ?? 0,
                empty_to_null($data['notes'] ?? null),
                empty_to_null($deadline),
                $accessToken,
                empty_to_null($data['stall_id'] ?? null),
                empty_to_null($plannedStart),
                empty_to_null($data['planned_end'] ?? null)
            ]);

            $orderId = db()->lastInsertId();

            if (!$orderId) {
                error_log("Order creation failed: Database insert returned no ID");
                return false;
            }

            // Log action
            try {
                Audit::log('order_created', 'orders', $orderId, null, ['status' => 'new']);
            } catch (\Throwable $e) {
                error_log("Audit log failed for order $orderId: " . $e->getMessage());
            }

            return $orderId;
        } catch (\Throwable $e) {
            // Self-healing: Check for missing columns
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                try {
                    self::fixMissingColumns();
                    throw new Exception("Базу даних автоматично оновлено! Спробуйте натиснути 'Зберегти' ще раз.");
                } catch (Exception $ex) {
                    // If self-heal fails, show original error
                }
            }
            throw new Exception("DB Error in Order::create: " . $e->getMessage());
        }
    }

    /**
     * Fix Missing Columns (Self-Healing)
     */
    public static function fixMissingColumns()
    {
        $db = db();
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN access_token VARCHAR(64) DEFAULT NULL");
        } catch (Exception $e) {
        }
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN stall_id INT DEFAULT NULL");
        } catch (Exception $e) {
        }
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN planned_start DATETIME DEFAULT NULL");
        } catch (Exception $e) {
        }
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN planned_end DATETIME DEFAULT NULL");
        } catch (Exception $e) {
        }
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN google_arrival_event_id VARCHAR(255) DEFAULT NULL");
        } catch (Exception $e) {
        }
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0.00");
        } catch (Exception $e) {
        }
        try {
            $db->exec("ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00");
        } catch (Exception $e) {
        }

        $db->exec("CREATE TABLE IF NOT EXISTS stalls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Insert default stall if table empty
        $stmt = $db->query("SELECT COUNT(*) FROM stalls");
        if ($stmt->fetchColumn() == 0) {
            $db->exec("INSERT INTO stalls (name) VALUES ('Бокс 1')");
        }
    }

    /**
     * Get order by ID
     */
    public static function getById($id)
    {
        try {
            $stmt = db()->prepare("SELECT o.id, o.order_number, o.client_id, o.car_id, o.manager_id, o.status, o.total_amount, o.paid_amount, o.discount_amount, o.discount_percent, o.notes, o.deadline, o.access_token, o.stall_id, o.planned_start, o.planned_end, o.google_event_id, o.google_arrival_event_id, o.is_deleted, o.created_at, o.updated_at,
                                         u.full_name as manager_name, c.name as client_name, car.make, car.model, car.license_plate, s.name as stall_name
                                  FROM orders o
                                  LEFT JOIN users u ON o.manager_id = u.id
                                  LEFT JOIN clients c ON o.client_id = c.id
                                  LEFT JOIN cars car ON o.car_id = car.id
                                  LEFT JOIN stalls s ON o.stall_id = s.id
                                  WHERE o.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get order: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update order
     */
    public static function update($id, $data)
    {
        try {
            $oldOrder = self::getById($id);
            if (!$oldOrder) return false;

            // Validate status before building SQL — prevent empty status from being written to DB
            if (isset($data['status'])) {
                if (empty($data['status'])) {
                    \App\Logger::warning("Order::update - Attempted to set EMPTY status for ID: $id. Caller: " . self::getCaller());
                    unset($data['status']);
                } else {
                    \App\Logger::debug("Order::update - ID: $id, New Status: '{$data['status']}', Caller: " . self::getCaller());
                }
            }

            $allowedFields = ['status', 'total_amount', 'discount_amount', 'discount_percent', 'paid_amount', 'notes', 'deadline', 'payment_method', 'stall_id', 'planned_start', 'planned_end', 'google_event_id', 'google_arrival_event_id'];
            $updates = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $val = $data[$field];

                    // Apply default time for date-only inputs
                    if (($field === 'planned_start' || $field === 'deadline') && $val && strlen($val) === 10) {
                        if ($field === 'planned_start') {
                            $val .= ' ' . \App\Models\Setting::get('work_day_start', '09:00') . ':00';
                        } elseif ($field === 'deadline') {
                            $val .= ' ' . \App\Models\Setting::get('work_day_end', '18:00') . ':00';
                        }
                    }

                    $updates[] = "$field = ?";
                    $values[] = $val;
                }
            }

            if (empty($updates)) {
                return true;
            }

            $values[] = $id;
            $sql = "UPDATE orders SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";

            $stmt = db()->prepare($sql);
            $result = $stmt->execute($values);

            // Log changes
            Audit::logOrderChange($id, $oldOrder, $data);

            // Dispatch Events
            if (isset($data['status']) && $data['status'] != $oldOrder['status']) {
                $user = Auth::getUserById(Auth::getUserId());
                \App\Events\EventDispatcher::dispatch(new \App\Events\OrderStatusChanged($oldOrder, $oldOrder['status'], $data['status'], $user));
            }

            $freshOrder = self::getById($id);
            if ($freshOrder) {
                $changedFields = [];
                foreach ($data as $key => $val) {
                    if (array_key_exists($key, $oldOrder) && $oldOrder[$key] != $val) {
                        $changedFields[] = $key;
                    }
                }
                if (!empty($changedFields)) {
                    \App\Events\EventDispatcher::dispatch(new \App\Events\OrderUpdated((int)$id, $oldOrder, $freshOrder, $changedFields));
                }
            }

            // Recalculate if discount percent changed
            if (isset($data['discount_percent'])) {
                self::recalculateTotal($id);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to update order: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get orders with filters
     */
    public static function getList($filters = [], $limit = null, $offset = 0)
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['status'])) {
                if (is_array($filters['status'])) {
                    $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                    $where[] = "o.status IN ($placeholders)";
                    foreach ($filters['status'] as $s) {
                        $params[] = $s;
                    }
                } else {
                    $where[] = "o.status = ?";
                    $params[] = $filters['status'];
                }
            }

            if (!empty($filters['client_id'])) {
                $where[] = "o.client_id = ?";
                $params[] = $filters['client_id'];
            }

            if (!empty($filters['manager_id'])) {
                $where[] = "o.manager_id = ?";
                $params[] = $filters['manager_id'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "DATE(o.created_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "DATE(o.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            if (Auth::isExecutor()) {
                $where[] = "EXISTS (
                    SELECT 1 FROM tasks t 
                    JOIN task_executors te ON t.id = te.task_id 
                    WHERE t.order_id = o.id AND te.user_id = ? AND (t.is_deleted = 0 OR t.is_deleted IS NULL)
                )";
                $params[] = Auth::getUserId();
            }

            if (isset($filters['is_deleted'])) {
                $where[] = "o.is_deleted = ?";
                $params[] = (int)$filters['is_deleted'];
            } else {
                $where[] = "(o.is_deleted = 0 OR o.is_deleted IS NULL)";
            }

            $whereSql = $where ? "WHERE " . implode(' AND ', $where) : "";

            $sql = "SELECT o.id, o.order_number, o.client_id, o.car_id, o.manager_id, o.status, o.total_amount, o.paid_amount, o.deadline, o.created_at, 
                           c.name as client_name, car.make, car.model, car.license_plate, u.full_name as manager_name, s.name as stall_name
                    FROM orders o
                    LEFT JOIN clients c ON o.client_id = c.id
                    LEFT JOIN cars car ON o.car_id = car.id
                    LEFT JOIN users u ON o.manager_id = u.id
                    LEFT JOIN stalls s ON o.stall_id = s.id
                    $whereSql
                    ORDER BY o.created_at DESC";

            if ($limit) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = (int)$limit;
                $params[] = (int)$offset;
            }

            $stmt = db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get orders: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get status name in Ukrainian
     */
    public static function getStatusName($status)
    {
        $names = [
            'new' => 'Новий',
            'approved' => 'Підтверджено',
            'in_progress' => 'В роботі',
            'waiting_parts' => 'Очікування запчастин',
            'waiting_client' => 'Очікування клієнта',
            'ready' => 'Готово',
            'completed' => 'Виконано',
            'closed' => 'Закрито',
            'cancelled' => 'Скасовано'
        ];

        return $names[$status] ?? ($status ?: '<порожньо>');
    }

    /**
     * Get caller information for debugging
     */
    private static function getCaller()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $caller = "Unknown";
        if (isset($backtrace[2])) {
            $caller = (isset($backtrace[2]['class']) ? $backtrace[2]['class'] . '::' : '') . $backtrace[2]['function'];
        }
        return $caller;
    }

    public static function getCount()
    {
        try {
            $stmt = db()->query("SELECT COUNT(*) FROM orders WHERE is_deleted = 0");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Failed to get order count: " . $e->getMessage());
            return 0;
        }
    }

    public static function getForTimeline(string $startDate, string $endDate): array
    {
        try {
            $stmt = db()->prepare("SELECT o.id, o.order_number, o.planned_start, o.planned_end, o.stall_id, o.status,
                                         c.name as client_name, car.make, car.model
                                  FROM orders o
                                  LEFT JOIN clients c ON o.client_id = c.id
                                  LEFT JOIN cars car ON o.car_id = car.id
                                  WHERE o.stall_id IS NOT NULL 
                                    AND o.planned_start IS NOT NULL 
                                    AND o.planned_end IS NOT NULL
                                    AND o.is_deleted = 0
                                    AND o.planned_start <= ? 
                                    AND o.planned_end >= ?");
            $stmt->execute([$endDate, $startDate]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get orders for timeline: " . $e->getMessage());
            return [];
        }
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'new' => '#17a2b8',            // Info (Blue-Green)
            'in_progress' => '#ffc107',    // Warning (Yellow)
            'ready' => '#20c997',          // Teal/Greenish
            'closed' => '#28a745',         // Success (Green)
            default => '#17a2b8',
        };
    }
}
