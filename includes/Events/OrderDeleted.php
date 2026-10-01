<?php
namespace App\Events;

/**
 * Подія: замовлення видалено
 *
 * Слухачі: TelegramListener, GoogleCalendarListener.
 *
 * @package App\Events
 */
class OrderDeleted extends Event {
    /** @var int ID видаленого замовлення */
    public $orderId;
    /** @var array Дані замовлення на момент видалення */
    public $orderData;

    /**
     * @param int   $orderId   ID замовлення
     * @param array $orderData Дані замовлення
     */
    public function __construct($orderId, $orderData) {
        $this->orderId = $orderId;
        $this->orderData = $orderData;
    }
}
