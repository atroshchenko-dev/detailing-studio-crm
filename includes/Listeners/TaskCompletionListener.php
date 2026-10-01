<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskUpdated;
use App\Models\Service;
use App\Models\WarehouseItem;
use App\Models\Order;
use App\Models\Task;

/**
 * Слухач завершення задач
 *
 * Обробляє побічні ефекти при зміні статусу задачі на 'completed':
 * 1. Списання матеріалів зі складу (за шаблоном послуги)
 * 2. Розрахунок комісії виконавця
 * 3. Перевірка чи всі задачі замовлення виконані → OrderTasksCompleted
 *
 * @package App\Listeners
 */
class TaskCompletionListener
{
    /**
     * Обробити оновлення задачі
     *
     * Виконується лише якщо статус змінився на 'completed'.
     *
     * @param TaskUpdated $event Подія оновлення задачі
     * @return void
     */
    public function onTaskUpdated(TaskUpdated $event): void
    {
        $freshTask = $event->newTask;
        $changedFields = $event->changedFields;

        // Ensure we only process if the task status just changed to 'completed'
        if (!in_array('status', $changedFields, true) || $freshTask['status'] !== 'completed') {
            return;
        }

        $taskId = $event->taskId;
        $oldTask = $event->oldTask;

        $this->handleWarehouseDeduction($taskId, $freshTask);
        $this->calculateCommission($taskId, $freshTask);
        $this->checkOrderReadiness($oldTask);
    }

    private function handleWarehouseDeduction(int $taskId, array $freshTask): void
    {
        if (empty($freshTask['service_id'])) return;

        if (\App\Models\Setting::get('feature_warehouse', '1') == '1') {
            try {
                $resources = Service::getResourceTemplates((int)$freshTask['service_id']);
                if (!empty($resources)) {
                    foreach ($resources as $res) {
                        $qty = (float)$res['quantity'];
                        $itemId = (int)$res['warehouse_item_id'];

                        $resultDeduct = WarehouseItem::removeStock(
                            $itemId,
                            $qty,
                            !empty($freshTask['order_id']) ? (int)$freshTask['order_id'] : null,
                            $taskId,
                            "Авто-списання по задачі: " . $freshTask['title']
                        );

                        if ($resultDeduct !== true) {
                            \App\Logger::warning("Stock deduction failed for Task $taskId: " . $resultDeduct, [
                                'item_id' => $itemId,
                                'qty' => $qty
                            ]);
                        }
                    }
                }
            } catch (\Throwable $te) {
                \App\Logger::error("Critical error in stock deduction for Task $taskId: " . $te->getMessage());
            }
        }
    }

    private function calculateCommission(int $taskId, array $taskRaw): void
    {
        if (empty($taskRaw['service_id'])) return;
        if (floatval($taskRaw['commission_amount'] ?? 0) > 0) return; // already calculated

        $serviceItem = Service::getById($taskRaw['service_id']);
        if ($serviceItem) {
            $basePrice = $serviceItem['base_price'];
            if (!empty($taskRaw['order_id'])) {
                $stPrice = db()->prepare("SELECT unit_price FROM order_items WHERE order_id = ? AND service_id = ? LIMIT 1");
                $stPrice->execute([$taskRaw['order_id'], $taskRaw['service_id']]);
                $foundPrice = $stPrice->fetchColumn();
                if ($foundPrice !== false) {
                    $basePrice = $foundPrice;
                }
            }

            $commission = 0;
            if (($serviceItem['commission_type'] ?? 'percentage') === 'percentage') {
                $commission = ($basePrice * ($serviceItem['commission_value'] ?? 0)) / 100;
            } else {
                $commission = $serviceItem['commission_value'] ?? 0;
            }

            $stmt = db()->prepare("UPDATE tasks SET commission_amount = ? WHERE id = ?");
            $stmt->execute([$commission, $taskId]);
        }
    }

    private function checkOrderReadiness(array $oldTask): void
    {
        if (empty($oldTask['order_id'])) return;

        $orderId = $oldTask['order_id'];
        $stmtCheck = db()->prepare("SELECT COUNT(*) FROM tasks WHERE order_id = ? AND status != 'completed' AND is_deleted = 0");
        $stmtCheck->execute([$orderId]);
        $incompleteCount = $stmtCheck->fetchColumn();

        if ($incompleteCount == 0) {
            // All done!
            $order = Order::getById($orderId);
            if ($order && !in_array($order['status'], ['ready', 'closed', 'cancelled'])) {
                (new \App\Services\OrderService())->updateStatus((int)$orderId, 'ready');
                \App\Events\EventDispatcher::dispatch(new \App\Events\OrderTasksCompleted($order));
            }
        }
    }
}
