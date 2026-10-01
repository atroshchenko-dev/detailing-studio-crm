<?php
namespace App\Events;

/**
 * Подія: задача оновлена
 *
 * Слухачі: TelegramListener, GoogleCalendarListener, TaskCompletionListener.
 *
 * @package App\Events
 */
class TaskUpdated extends Event {
    /** @var int ID задачі */
    public int $taskId;
    /** @var array Дані задачі до зміни */
    public array $oldTask;
    /** @var array Дані задачі після зміни */
    public array $newTask;
    /** @var array Ключі змінених полів */
    public array $changedFields;

    /**
     * @param int   $taskId        ID задачі
     * @param array $oldTask       Старий стан
     * @param array $newTask       Новий стан
     * @param array $changedFields Змінені поля
     */
    public function __construct(int $taskId, array $oldTask, array $newTask, array $changedFields) {
        $this->taskId = $taskId;
        $this->oldTask = $oldTask;
        $this->newTask = $newTask;
        $this->changedFields = $changedFields;
    }
}
