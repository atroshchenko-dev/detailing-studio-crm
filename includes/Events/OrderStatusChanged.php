<?php
namespace App\Events;

/**
 * Подія: статус замовлення змінено
 *
 * Слухач: TelegramListener — оновлює повідомлення в групі.
 *
 * @package App\Events
 */
class OrderStatusChanged extends Event {
    /** @var array Дані замовлення */
    public array $order;
    /** @var string Попередній статус */
    public string $oldStatus;
    /** @var string Новий статус */
    public string $newStatus;
    /** @var array|null Користувач, що змінив статус */
    public ?array $user;

    /**
     * @param array       $order     Дані замовлення
     * @param string      $oldStatus Старий статус
     * @param string      $newStatus Новий статус
     * @param array|null  $user      Хто змінив
     */
    public function __construct(array $order, string $oldStatus, string $newStatus, ?array $user) {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->user = $user;
    }
}
