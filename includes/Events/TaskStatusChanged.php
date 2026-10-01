<?php
namespace App\Events;

/**
 * Подія: статус задачі змінено
 *
 * Слухач: TelegramListener — оновлює повідомлення.
 *
 * @package App\Events
 */
class TaskStatusChanged extends Event {
    /** @var array Дані задачі */
    public array $task;
    /** @var string Попередній статус */
    public string $oldStatus;
    /** @var string Новий статус */
    public string $newStatus;
    /** @var array|null Користувач, що змінив статус */
    public ?array $user;

    /**
     * @param array      $task      Задача
     * @param string     $oldStatus Старий статус
     * @param string     $newStatus Новий статус
     * @param array|null $user      Хто змінив
     */
    public function __construct(array $task, string $oldStatus, string $newStatus, ?array $user) {
        $this->task = $task;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->user = $user;
    }
}
