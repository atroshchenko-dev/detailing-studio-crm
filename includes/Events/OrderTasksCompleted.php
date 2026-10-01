<?php
namespace App\Events;

/**
 * Подія: всі задачі замовлення виконані
 *
 * Диспатчиться TaskCompletionListener-ом коли остання задача
 * замовлення переходить у статус 'completed'.
 * Слухач: TelegramListener — сповіщає менеджера.
 *
 * @package App\Events
 */
class OrderTasksCompleted extends Event {
    /** @var array Дані замовлення */
    public array $order;

    /**
     * @param array $order Замовлення, всі задачі якого виконані
     */
    public function __construct(array $order) {
        $this->order = $order;
    }
}
