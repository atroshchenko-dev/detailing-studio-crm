<?php
namespace App\Events;

/**
 * Подія: задача створена
 *
 * Слухачі: TelegramListener, GoogleCalendarListener.
 *
 * @package App\Events
 */
class TaskCreated extends Event {
    /** @var array Дані створеної задачі */
    public array $task;
    /** @var array|null Дані виконавця */
    public ?array $executor;
    /** @var array|null Дані автора */
    public ?array $creator;

    /**
     * @param array      $task     Задача
     * @param array|null $executor Виконавець (може бути null)
     * @param array|null $creator  Автор
     */
    public function __construct(array $task, ?array $executor, ?array $creator) {
        $this->task = $task;
        $this->executor = $executor;
        $this->creator = $creator;
    }
}
