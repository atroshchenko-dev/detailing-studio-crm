<?php
namespace App\Events;

/**
 * Подія: задача видалена
 *
 * Слухачі: TelegramListener, GoogleCalendarListener.
 *
 * @package App\Events
 */
class TaskDeleted extends Event {
    /** @var int ID видаленої задачі */
    public $taskId;
    /** @var array Дані задачі на момент видалення */
    public $taskData;

    /**
     * @param int   $taskId   ID задачі
     * @param array $taskData Дані задачі
     */
    public function __construct($taskId, $taskData) {
        $this->taskId = $taskId;
        $this->taskData = $taskData;
    }
}
