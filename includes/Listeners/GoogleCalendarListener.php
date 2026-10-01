<?php

namespace App\Listeners;

use App\GoogleIntegration;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\Client;
use App\Models\Car;
use App\Models\Service;

/**
 * Слухач Google Calendar
 *
 * Синхронізує події замовлень та задач з Google Calendar
 * через GoogleIntegration Service Account.
 * Створює події Прибуття та Видачі для замовлень.
 *
 * @package App\Listeners
 */
class GoogleCalendarListener
{

    /**
     * Створити календарні події для нового замовлення
     *
     * @param OrderCreated $event
     * @return void
     */
    public static function onOrderCreated(OrderCreated $event)
    {
        $orderId = $event->orderId;
        $order = Order::getById($orderId);
        if (!$order || !GoogleIntegration::isConfigured()) return;

        $client = Client::getById($order['client_id']);
        $car = Car::getById($order['car_id']);

        $description = "📞 Телефон: " . \App\Utils\Helper::maskPhone($client['phone'] ?? '') . "\n";
        $description .= "👤 Менеджер: " . ($order['manager_name'] ?? '-') . "\n";
        if (!empty($order['notes'])) $description .= "💬 Примітки: {$order['notes']}\n";
        $description .= "\n🔗 Посилання: " . (defined('APP_URL') ? APP_URL : '') . "/orders/view.php?id={$orderId}";

        // 1. Arrival Event
        if (!empty($order['planned_start'])) {
            $title = "🚘 Прибуття #{$order['order_number']} - " . ($client['name'] ?? '') . " (" . ($car['make'] ?? '') . ")";
            GoogleIntegration::createOrderCalendarEvent($orderId, $title, $order['planned_start'], $description, 'google_arrival_event_id');
        }

        // 2. Delivery Event
        if (!empty($order['deadline'])) {
            $title = "🛎️ Видача #{$order['order_number']} - " . ($client['name'] ?? '') . " (" . ($car['make'] ?? '') . ")";
            GoogleIntegration::createOrderCalendarEvent($orderId, $title, $order['deadline'], $description, 'google_event_id');
        }
    }

    /**
     * Оновити календарні події при зміні замовлення
     *
     * @param OrderUpdated $event
     * @return void
     */
    public static function onOrderUpdated(OrderUpdated $event)
    {
        $order = $event->newOrder;
        $changed = $event->changedFields;

        if (!GoogleIntegration::isConfigured()) return;

        // Sync if relevant fields changed
        if (array_intersect(['deadline', 'planned_start', 'client_id', 'car_id', 'notes', 'status'], $changed)) {

            $client = Client::getById($order['client_id']);
            $car = Car::getById($order['car_id']);

            $description = "📞 Телефон: " . \App\Utils\Helper::maskPhone($client['phone'] ?? '') . "\n";
            $description .= "👤 Менеджер: " . ($order['manager_name'] ?? '-') . "\n";
            if (!empty($order['notes'])) $description .= "💬 Примітки: {$order['notes']}\n";
            $description .= "\n🔗 Посилання: " . (defined('APP_URL') ? APP_URL : '') . "/orders/view.php?id={$order['id']}";

            // Update Arrival Event
            if (!empty($order['planned_start'])) {
                $title = "🚘 Прибуття #{$order['order_number']} - " . ($client['name'] ?? '') . " (" . ($car['make'] ?? '') . ")";
                if ($order['google_arrival_event_id']) {
                    GoogleIntegration::updateOrderCalendarEvent($order['google_arrival_event_id'], $title, $order['planned_start'], $description);
                } else {
                    GoogleIntegration::createOrderCalendarEvent($order['id'], $title, $order['planned_start'], $description, 'google_arrival_event_id');
                }
            }

            // Update Delivery Event
            if (!empty($order['deadline'])) {
                $title = "🛎️ Видача #{$order['order_number']} - " . ($client['name'] ?? '') . " (" . ($car['make'] ?? '') . ")";
                if ($order['google_event_id']) {
                    GoogleIntegration::updateOrderCalendarEvent($order['google_event_id'], $title, $order['deadline'], $description);
                } else {
                    GoogleIntegration::createOrderCalendarEvent($order['id'], $title, $order['deadline'], $description, 'google_event_id');
                }
            }
        }
    }

    /**
     * Видалити календарні події при видаленні замовлення
     *
     * @param \App\Events\OrderDeleted $event
     * @return void
     */
    public static function onOrderDeleted(\App\Events\OrderDeleted $event)
    {
        $order = $event->orderData;
        if (!GoogleIntegration::isConfigured()) return;

        if (!empty($order['google_event_id'])) {
            GoogleIntegration::deleteCalendarEvent($order['google_event_id']);
        }
        if (!empty($order['google_arrival_event_id'])) {
            GoogleIntegration::deleteCalendarEvent($order['google_arrival_event_id']);
        }
    }

    /**
     * Видалити календарну подію при видаленні задачі
     *
     * @param \App\Events\TaskDeleted $event
     * @return void
     */
    public static function onTaskDeleted(\App\Events\TaskDeleted $event)
    {
        $task = $event->taskData;
        if (!empty($task['google_event_id']) && GoogleIntegration::isConfigured()) {
            GoogleIntegration::deleteCalendarEvent($task['google_event_id']);
        }
    }

    // ... existing onTaskCreated ...


    /**
     * Створити календарну подію для нової задачі
     *
     * @param \App\Events\TaskCreated $event
     * @return void
     */
    public static function onTaskCreated(\App\Events\TaskCreated $event)
    {
        $task = $event->task;
        if (!empty($task['deadline']) && GoogleIntegration::isConfigured()) {
            $priorityIcons = ['low' => '🟢', 'medium' => '🟡', 'high' => '🟠', 'urgent' => '🔴'];
            $pIcon = $priorityIcons[$task['priority'] ?? 'medium'] ?? '🟡';

            $executorName = $task['executor_names'] ?: 'Не призначено';
            // Logic to build title/description...
            $title = "{$pIcon} Задача: " . $task['title'] . " ({$executorName})";

            $description = "";
            if (!empty($task['order_id'])) {
                $order = Order::getById($task['order_id']);
                if ($order) {
                    $description .= "🚘 Замовлення #{$order['order_number']} ({$order['make']} {$order['model']})\n\n";
                }
            }
            if (!empty($task['description'])) {
                $description .= "💬 Опис:\n" . $task['description'] . "\n\n";
            }
            $description .= "LINK: " . (defined('APP_URL') ? APP_URL : '') . "/tasks/view.php?id={$task['id']}";

            GoogleIntegration::createTaskCalendarEvent($task['id'], $title, $task['deadline'], $description);
        }
    }

    /**
     * Оновити календарну подію при оновленні задачі
     *
     * @param \App\Events\TaskUpdated $event
     * @return void
     */
    public static function onTaskUpdated(\App\Events\TaskUpdated $event)
    {
        $task = $event->newTask;
        $changed = $event->changedFields;

        // Sync if relevant fields changed
        if (array_intersect(['deadline', 'title', 'description', 'status', 'priority'], $changed)) {
            $priorityIcons = ['low' => '🟢', 'medium' => '🟡', 'high' => '🟠', 'urgent' => '🔴'];
            $pIcon = $priorityIcons[$task['priority'] ?? 'medium'] ?? '🟡';

            if ($task['status'] === 'completed') $pIcon = '☑️';

            $executorName = $task['executor_names'] ?: 'Не призначено';
            $title = "{$pIcon} Задача: " . $task['title'] . " ({$executorName})";

            $description = "";
            if (!empty($task['order_id'])) {
                $order = Order::getById($task['order_id']);
                if ($order) {
                    $description .= "🚘 Замовлення #{$order['order_number']} ({$order['make']} {$order['model']})\n\n";
                }
            }
            if (!empty($task['description'])) {
                $description .= "💬 Опис:\n" . $task['description'] . "\n\n";
            }
            $description .= "Статус: " . \App\Models\Task::getStatusName($task['status']) . "\n";
            $description .= "LINK: " . (defined('APP_URL') ? APP_URL : '') . "/tasks/view.php?id={$task['id']}";

            if ($task['google_event_id']) {
                GoogleIntegration::updateTaskCalendarEvent($task['google_event_id'], $title, $task['deadline'], $description);
            } elseif ($task['deadline']) {
                GoogleIntegration::createTaskCalendarEvent($task['id'], $title, $task['deadline'], $description);
            }
        }
    }
}
