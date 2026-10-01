<?php
namespace App\Events;

/**
 * Подія: замовлення оновлено
 *
 * Диспатчиться при зміні даних замовлення (ціна, дедлайн, клієнт тощо).
 * Слухачі: TelegramListener, GoogleCalendarListener.
 *
 * @package App\Events
 */
class OrderUpdated extends Event {
    /** @var int ID замовлення */
    public int $orderId;
    /** @var array Дані замовлення до зміни */
    public array $oldOrder;
    /** @var array Дані замовлення після зміни (з БД) */
    public array $newOrder;
    /** @var array Ключі змінених полів */
    public array $changedFields;

    /**
     * @param int   $orderId       ID замовлення
     * @param array $oldOrder      Старий стан
     * @param array $newOrder      Новий стан
     * @param array $changedFields Змінені поля
     */
    public function __construct(int $orderId, array $oldOrder, array $newOrder, array $changedFields) {
        $this->orderId = $orderId;
        $this->oldOrder = $oldOrder;
        $this->newOrder = $newOrder;
        $this->changedFields = $changedFields;
    }
}
