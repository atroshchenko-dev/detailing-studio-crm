<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Audit;
use App\Telegram;
use App\GoogleIntegration;
use Exception;

/**
 * Tasks Model
 */
class Task
{

    /**
     * Create new task
     */
    public static function create($data)
    {
        $db = db();
        try {
            $inTransaction = $db->inTransaction();
            if (!$inTransaction) {
                $db->beginTransaction();
            }

            $stmt = $db->prepare("INSERT INTO tasks (title, description, order_id, creator_id, executor_id, priority, deadline, profit_amount, commission_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Legacy/Primary executor support
            $primaryExecutorId = $data['executor_id'] ?? null;
            if (!$primaryExecutorId && !empty($data['executors'])) {
                $primaryExecutorId = reset($data['executors']);
            }

            $deadline = $data['deadline'] ?? null;
            if ($deadline && strlen($deadline) === 10) {
                $deadline .= ' ' . \App\Models\Setting::get('work_day_end', '18:00') . ':00';
            }

            $stmt->execute([
                $data['title'],
                empty_to_null($data['description'] ?? null),
                empty_to_null($data['order_id'] ?? null),
                $data['creator_id'] ?? Auth::getUserId(),
                empty_to_null($primaryExecutorId),
                $data['priority'] ?? 'medium',
                empty_to_null($deadline),
                $data['profit_amount'] ?? 0.00,
                $data['commission_amount'] ?? 0.00
            ]);

            $taskId = $db->lastInsertId();

            // Handle multiple executors
            $executors = $data['executors'] ?? [];
            if ($primaryExecutorId) {
                $executors[] = $primaryExecutorId;
            }
            $executors = array_unique($executors);

            if (!empty($executors)) {
                $stmtExec = $db->prepare("INSERT IGNORE INTO task_executors (task_id, user_id) VALUES (?, ?)");
                foreach ($executors as $executorId) {
                    $stmtExec->execute([$taskId, $executorId]);
                }
            }

            // Log action
            Audit::log('task_created', 'tasks', $taskId, null, ['status' => 'created']);

            if (!$inTransaction) {
                $db->commit();
            }

            // Notifications and Google Calendar Sync now handled by Event Listeners
            $actualCreatorId = $data['creator_id'] ?? Auth::getUserId();
            $creator = Auth::getUserById($actualCreatorId);
            if ($creator === false) $creator = null;

            $task = self::getById($taskId);

            \App\Events\EventDispatcher::dispatch(new \App\Events\TaskCreated($task, null, $creator));

            return $taskId;
        } catch (\Throwable $e) {
            if (isset($inTransaction) && !$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            // DEBUG: Show validation/code error
            throw new Exception("Task Creation Error: " . $e->getMessage());
        }
    }

    /**
     * Get task by ID
     */
    public static function getById($id)
    {
        try {
            $stmt = db()->prepare("SELECT t.id, t.title, t.description, t.order_id, t.service_id, t.creator_id, t.executor_id, t.status, t.priority, t.deadline, t.started_at, t.created_at, t.updated_at, t.completed_at, t.google_event_id, t.is_deleted, t.profit_amount, t.commission_amount,
                                  creator.full_name as creator_name,
                                  o.order_number
                                  FROM tasks t
                                  LEFT JOIN users creator ON t.creator_id = creator.id
                                  LEFT JOIN orders o ON t.order_id = o.id
                                  WHERE t.id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();

            if ($task) {
                // Fetch executors
                $stmtExec = db()->prepare("SELECT u.* FROM users u JOIN task_executors te ON u.id = te.user_id WHERE te.task_id = ?");
                $stmtExec->execute([$id]);
                $task['executors'] = $stmtExec->fetchAll();
                $task['executor_ids'] = array_column($task['executors'], 'id');

                // Fallback for UI that expects string
                $task['executor_names'] = implode(', ', array_column($task['executors'], 'full_name'));

                // Ensure legacy support if executor_id is lost/mismatched (though we try to keep it synced)
                if (empty($task['executor_name']) && !empty($task['executors'])) {
                    $task['executor_name'] = $task['executors'][0]['full_name'];
                }
            }

            return $task;
        } catch (Exception $e) {
            error_log("Failed to get task: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update task
     */
    public static function update($id, $data)
    {
        try {
            $oldTask = self::getById($id);
            if (!$oldTask) return false;

            // Sync executors first if present
            if (isset($data['executors'])) {
                $newExecutors = array_unique($data['executors']);

                db()->prepare("DELETE FROM task_executors WHERE task_id = ?")->execute([$id]);

                if (!empty($newExecutors)) {
                    $stmtExec = db()->prepare("INSERT IGNORE INTO task_executors (task_id, user_id) VALUES (?, ?)");
                    foreach ($newExecutors as $uid) {
                        $stmtExec->execute([$id, $uid]);
                    }
                }

                // Update legacy id
                $data['executor_id'] = !empty($newExecutors) ? reset($newExecutors) : null;

                // Notifications for new executors are now handled by TaskUpdated event
                // which calculates diff of executors.
            }

            $allowedFields = ['title', 'description', 'status', 'executor_id', 'priority', 'deadline', 'started_at', 'completed_at', 'profit_amount', 'commission_amount'];
            $updates = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $val = $data[$field];

                    if ($field === 'deadline' && $val && strlen($val) === 10) {
                        $val .= ' ' . \App\Models\Setting::get('work_day_end', '18:00') . ':00';
                    }

                    $updates[] = "$field = ?";
                    $values[] = $val;
                }
            }

            if (empty($updates)) {
                return true;
            }

            $values[] = $id;
            $sql = "UPDATE tasks SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";

            $stmt = db()->prepare($sql);
            $result = $stmt->execute($values);

            // Log changes
            Audit::logTaskChange($id, $oldTask, $data);

            // Notifications and Google Sync moved to Event Listeners

            // 1. New Executor Notification (?)
            // Handled by manual dispatch if we really need it, but for now we trust TaskUpdated 
            // might not suffice for "New Executor Added" unless we specifically check for it.
            // But wait, Task::update logic for new executors was removed above?
            // Ah, I need to check lines 160-190 for new executors logic.
            // That block is untouched by this replacement which targets lines 216-257.
            // So new executor notifications (lines 160-190) are still using Telegram::notifyNewTask explicitly.
            // We should ideally refactor that too, but it's a bit complex with diffing.
            // For now, let's clean up Status Change and Google Sync.

            // Dispatch Status Change
            if (isset($data['status']) && $data['status'] != $oldTask['status']) {
                $user = Auth::getUserById(Auth::getUserId());
                \App\Events\EventDispatcher::dispatch(new \App\Events\TaskStatusChanged($oldTask, $oldTask['status'], $data['status'], $user));
            }

            // Dispatch TaskUpdated
            $freshTask = self::getById($id);
            if ($freshTask) {
                // Determine changed fields
                $changedFields = [];
                foreach ($data as $key => $val) {
                    if (array_key_exists($key, $oldTask) && $oldTask[$key] != $val) {
                        $changedFields[] = $key;
                    }
                }

                if (!empty($changedFields)) {
                    \App\Events\EventDispatcher::dispatch(new \App\Events\TaskUpdated((int)$id, $oldTask, $freshTask, $changedFields));
                }

            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to update task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add attachment to task
     */
    public static function addAttachment($taskId, $filePath, $fileName = null, $userId = null)
    {
        try {
            $stmt = db()->prepare("INSERT INTO task_attachments (task_id, file_path, file_name, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $taskId,
                $filePath,
                $fileName,
                $userId
            ]);
            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to add attachment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tasks with filters
     */
    public static function getList($filters = [], $limit = null, $offset = 0)
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['status'])) {
                if (is_array($filters['status'])) {
                    $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                    $where[] = "t.status IN ($placeholders)";
                    foreach ($filters['status'] as $s) $params[] = $s;
                } else {
                    $where[] = "t.status = ?";
                    $params[] = $filters['status'];
                }
            }

            if (!empty($filters['executor_id'])) {
                // Check if user is ONE OF the executors
                $where[] = "EXISTS (SELECT 1 FROM task_executors te WHERE te.task_id = t.id AND te.user_id = ?)";
                $params[] = $filters['executor_id'];
            }

            if (!empty($filters['creator_id'])) {
                $where[] = "t.creator_id = ?";
                $params[] = $filters['creator_id'];
            }

            if (!empty($filters['order_id'])) {
                $where[] = "t.order_id = ?";
                $params[] = $filters['order_id'];
            }

            if (Auth::isExecutor()) {
                // Executors can only see their tasks
                $where[] = "EXISTS (SELECT 1 FROM task_executors te WHERE te.task_id = t.id AND te.user_id = ?)";
                $params[] = Auth::getUserId();
            }

            // Filter deleted tasks based on flags
            if (!empty($filters['only_deleted'])) {
                $where[] = "t.is_deleted = 1";
            } elseif (empty($filters['include_deleted'])) {
                $where[] = "(t.is_deleted = 0 AND (o.is_deleted = 0 OR o.id IS NULL))";
            }

            $whereSql = $where ? "WHERE " . implode(' AND ', $where) : "";

            $sql = "SELECT t.id, t.title, t.order_id, t.executor_id, t.status, t.priority, t.deadline, t.created_at, t.profit_amount, t.commission_amount,
                    creator.full_name as creator_name,
                    o.order_number,
                    car.make as car_make, car.model as car_model, car.license_plate as car_plate,
                    (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') 
                     FROM task_executors te 
                     JOIN users u ON te.user_id = u.id 
                     WHERE te.task_id = t.id) as executor_names
                    FROM tasks t
                    LEFT JOIN users creator ON t.creator_id = creator.id
                    LEFT JOIN orders o ON t.order_id = o.id
                    LEFT JOIN cars car ON o.car_id = car.id
                    $whereSql
                    ORDER BY t.created_at DESC";

            if ($limit) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
            }

            $stmt = db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get tasks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get tasks by status (for Kanban)
     */
    public static function getByStatus($status, $includeDeleted = false, $onlyDeleted = false)
    {
        try {
            $sql = "SELECT t.*, 
                    creator.full_name as creator_name,
                    o.order_number,
                    (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') 
                     FROM task_executors te 
                     JOIN users u ON te.user_id = u.id 
                     WHERE te.task_id = t.id) as executor_names
                    FROM tasks t
                    LEFT JOIN users creator ON t.creator_id = creator.id
                    LEFT JOIN orders o ON t.order_id = o.id
                    WHERE t.status = ?";

            $params = [$status];

            if (Auth::isExecutor()) {
                $sql .= " AND EXISTS (SELECT 1 FROM task_executors te WHERE te.task_id = t.id AND te.user_id = ?)";
                $params[] = Auth::getUserId();
            }

            if ($onlyDeleted) {
                $sql .= " AND t.is_deleted = 1";
            } elseif (!$includeDeleted) {
                $sql .= " AND t.is_deleted = 0 AND (o.is_deleted = 0 OR o.id IS NULL)";
            }

            $sql .= " ORDER BY t.priority DESC, t.created_at DESC";

            $stmt = db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get tasks by status: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get overdue tasks
     */
    public static function getOverdue()
    {
        try {
            $sql = "SELECT t.*, 
                    creator.full_name as creator_name,
                    o.order_number,
                    (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') 
                     FROM task_executors te 
                     JOIN users u ON te.user_id = u.id 
                     WHERE te.task_id = t.id) as executor_names
                    FROM tasks t
                    LEFT JOIN users creator ON t.creator_id = creator.id
                    LEFT JOIN orders o ON t.order_id = o.id
                    WHERE t.deadline < NOW() 
                    AND t.status NOT IN ('completed')
                    AND t.is_deleted = 0 AND (o.is_deleted = 0 OR o.id IS NULL)";

            if (Auth::isExecutor()) {
                $sql .= " AND EXISTS (SELECT 1 FROM task_executors te WHERE te.task_id = t.id AND te.user_id = ?)";
                $params = [Auth::getUserId()];
            } else {
                $params = [];
            }

            $stmt = db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get overdue tasks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get tasks for calendar
     */
    public static function getForCalendar($startDate, $endDate)
    {
        try {
            $sql = "SELECT t.*, 
                    creator.full_name as creator_name,
                    o.order_number,
                    (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') 
                     FROM task_executors te 
                     JOIN users u ON te.user_id = u.id 
                     WHERE te.task_id = t.id) as executor_names
                    FROM tasks t
                    LEFT JOIN users creator ON t.creator_id = creator.id
                    LEFT JOIN orders o ON t.order_id = o.id
                    WHERE t.deadline IS NOT NULL 
                    AND DATE(t.deadline) BETWEEN ? AND ?
                    AND t.is_deleted = 0 AND (o.is_deleted = 0 OR o.id IS NULL)";

            if (Auth::isExecutor()) {
                $sql .= " AND EXISTS (SELECT 1 FROM task_executors te WHERE te.task_id = t.id AND te.user_id = ?)";
                $params = [$startDate, $endDate, Auth::getUserId()];
            } else {
                $params = [$startDate, $endDate];
            }

            $sql .= " ORDER BY t.deadline ASC";

            $stmt = db()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get tasks for calendar: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add comment
     */
    public static function addComment($taskId, $comment, $mediaLinks = null)
    {
        try {
            $stmt = db()->prepare("INSERT INTO task_comments (task_id, user_id, comment, media_links) VALUES (?, ?, ?, ?)");
            $stmt->execute([$taskId, Auth::getUserId(), $comment, $mediaLinks ? json_encode($mediaLinks) : null]);

            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to add comment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get task comments
     */
    public static function getComments($taskId)
    {
        try {
            $stmt = db()->prepare("SELECT tc.*, u.full_name as user_name 
                                  FROM task_comments tc
                                  JOIN users u ON tc.user_id = u.id
                                  WHERE tc.task_id = ?
                                  ORDER BY tc.created_at DESC");
            $stmt->execute([$taskId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get comments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get status name in Ukrainian
     */
    public static function getStatusName($status)
    {
        $names = [
            'created' => 'Створено',
            'in_progress' => 'В роботі',
            'completed' => 'Готово',
            'closed' => 'Закрито',
            'cancelled' => 'Скасовано'
        ];

        return $names[$status] ?? $status;
    }

    /**
     * Soft delete task
     */
    public static function softDelete($id)
    {
        try {
            $stmt = db()->prepare("UPDATE tasks SET is_deleted = 1 WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result) {
                Audit::log('task_deleted', 'tasks', $id);

                // Dispatch TaskDeleted
                $task = self::getById($id);
                if ($task) {
                    \App\Events\EventDispatcher::dispatch(new \App\Events\TaskDeleted($id, $task));
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to soft delete task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore soft deleted task
     */
    public static function restore($id)
    {
        try {
            $stmt = db()->prepare("UPDATE tasks SET is_deleted = 0 WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result) {
                Audit::log('task_restored', 'tasks', $id);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to restore task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Permanent delete task
     */
    public static function hardDelete($id)
    {
        $db = db();
        try {
            $task = self::getById($id);
            if (!$task) return false;

            $db->beginTransaction();

            // 1. Delete task executors
            $db->prepare("DELETE FROM task_executors WHERE task_id = ?")->execute([$id]);

            // 2. Delete task comments
            $db->prepare("DELETE FROM task_comments WHERE task_id = ?")->execute([$id]);

            // 3. Delete task attachments
            $db->prepare("DELETE FROM task_attachments WHERE task_id = ?")->execute([$id]);

            // 4. Delete from Google Calendar
            if (!empty($task['google_event_id'])) {
                GoogleIntegration::deleteCalendarEvent($task['google_event_id']);
            }

            // 5. Delete telegram notifications
            $db->prepare("DELETE FROM telegram_notifications WHERE entity_type = 'task' AND entity_id = ?")->execute([$id]);

            // 6. Delete task itself
            $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);

            $db->commit();

            Audit::log('task_permanently_deleted', 'tasks', $id, null, ['title' => $task['title']]);

            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to hard delete task: " . $e->getMessage());
            return false;
        }
    }
}
