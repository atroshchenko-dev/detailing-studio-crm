<?php

namespace App\Listeners;

use App\Telegram;
use App\Events\Event;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\OrderUpdated;
use App\Events\OrderDeleted;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Events\TaskUpdated;
use App\Events\TaskDeleted;
use App\Events\OrderTasksCompleted;
use App\Events\BackupProcessed;
use App\Events\DeadlineReached;
use App\Models\Order;
use App\Models\Client;
use App\Models\Car;

/**
 * Слухач Telegram-сповіщень
 *
 * Реагує на доменні події та відправляє сповіщення
 * в Telegram групу (топіки) та особисті чати користувачів.
 *
 * @package App\Listeners
 */
class TelegramListener
{

    /**
     * Сповістити про нове замовлення
     *
     * @param OrderCreated $event Подія створення замовлення
     * @return void
     */
    public static function onOrderCreated(OrderCreated $event)
    {
        $orderId = $event->orderId;
        // Fetch full objects as Telegram::notifyNewOrder expects them
        // Note: In a perfect world, Event would carry objects, but OrderService 
        // creates Order via ID, so we might need to fetch.
        // However, looking at OrderService, it doesn't have full objects handy immediately after creation
        // unless we refactor createOrder to return them.
        // Let's fetch them here to be safe and decouple.

        $order = Order::getById($orderId);
        if ($order) {
            $client = Client::getById($order['client_id']);
            $car = Car::getById($order['car_id']);
            Telegram::notifyNewOrder($order, $client, $car);
        }
    }

    /**
     * Обробити зміну статусу замовлення (делегується onOrderUpdated)
     *
     * @param OrderStatusChanged $event
     * @return void
     */
    public static function onOrderStatusChanged(OrderStatusChanged $event)
    {
        // Handled by onOrderUpdated to avoid duplicates and support "changed fields" icons
        /*
        Telegram::notifyOrderStatusChange(
            $event->order,
            $event->oldStatus,
            $event->newStatus,
            $event->user
        );
        */
    }

    /**
     * Синхронізувати Telegram-повідомлення при оновленні замовлення
     *
     * @param OrderUpdated $event
     * @return void
     */
    public static function onOrderUpdated(OrderUpdated $event)
    {
        $order = $event->newOrder;
        $changed = $event->changedFields;

        // Refresh Telegram message if any displayed field changed
        if (array_intersect(['deadline', 'planned_start', 'notes', 'status'], $changed)) {
            Telegram::syncOrderNotification($order['id'], $changed);
        }
    }

    /**
     * Видалити Telegram-сповіщення при видаленні замовлення
     *
     * @param OrderDeleted $event
     * @return void
     */
    public static function onOrderDeleted(OrderDeleted $event)
    {
        Telegram::deleteOrderNotifications($event->orderId);
    }

    /**
     * Створити sync-повідомлення для нової задачі
     *
     * @param TaskCreated $event
     * @return void
     */
    public static function onTaskCreated(TaskCreated $event)
    {
        $task = $event->task;
        Telegram::syncTaskNotification($task['id']);
    }


    /**
     * Сповістити про зміну статусу задачі
     *
     * @param TaskStatusChanged $event
     * @return void
     */
    public static function onTaskStatusChanged(TaskStatusChanged $event)
    {
        Telegram::notifyTaskStatusChange(
            $event->task,
            $event->oldStatus,
            $event->newStatus,
            $event->user
        );
    }

    /**
     * Сповістити про завершення всіх задач замовлення
     *
     * @param OrderTasksCompleted $event
     * @return void
     */
    public static function onOrderTasksCompleted(OrderTasksCompleted $event)
    {
        Telegram::notifyOrderAllTasksCompleted($event->order);
    }
    /**
     * Синхронізувати Telegram-повідомлення при оновленні задачі
     *
     * @param TaskUpdated $event
     * @return void
     */
    public static function onTaskUpdated(TaskUpdated $event)
    {
        Telegram::syncTaskNotification($event->newTask['id'], $event->changedFields);
    }

    /**
     * Видалити Telegram-сповіщення при видаленні задачі
     *
     * @param TaskDeleted $event
     * @return void
     */
    public static function onTaskDeleted(TaskDeleted $event)
    {
        Telegram::deleteTaskNotifications($event->taskId);
    }

    /**
     * Сповістити про результат бекапу
     *
     * @param BackupProcessed $event
     * @return void
     */
    public static function onBackupProcessed(BackupProcessed $event)
    {
        $topicId = defined('TELEGRAM_TOPIC_ALERTS') ? TELEGRAM_TOPIC_ALERTS : null;
        Telegram::sendToGroup($event->message, null, null, $topicId);
    }

    /**
     * Сповістити про наближення/прострочення дедлайну
     *
     * @param DeadlineReached $event
     * @return void
     */
    public static function onDeadlineReached(DeadlineReached $event)
    {
        Telegram::notifyDeadline($event->entity, $event->urgency);
    }
}
