<?php
namespace App\Events;

/**
 * Подія: замовлення створено
 *
 * Диспатчиться після створення нового замовлення.
 * Слухачі: TelegramListener, GoogleCalendarListener.
 *
 * @package App\Events
 */
class OrderCreated extends Event {
    /** @var int ID створеного замовлення */
    public int $orderId;
    /** @var array Дані замовлення (deadline, client_id, car_id, etc.) */
    public array $orderData;

    public function __construct(int $orderId, array $orderData) {
        $this->orderId = $orderId;
        $this->orderData = $orderData;
    }
}
