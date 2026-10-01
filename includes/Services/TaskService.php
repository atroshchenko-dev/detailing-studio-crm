<?php
namespace App\Services;

use App\Validator;
use App\Models\Task;
use App\Telegram;
use App\Auth;
use App\Audit;
use App\Events\EventDispatcher;
use App\Events\TaskCreated;
use Exception;

/**
 * Сервіс управління задачами
 *
 * Валідація, створення задач з аудит-логуванням
 * та dispatchінгом TaskCreated події.
 *
 * @package App\Services
 */
class TaskService {

    /**
     * Валідувати дані задачі
     *
     * @param array $data Дані з форми
     * @return void
     * @throws Exception При помилці валідації
     */
    public function validateTaskData(array $data): void {
        $validator = new Validator($data);
        $validator->required('title', 'Назва задачі')
                  ->required('priority', 'Пріоритет');
        
        if (!$validator->isValid()) {
            throw new Exception('Помилка валідації задачі: ' . implode(', ', $validator->getErrors()));
        }
        
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (isset($data['priority']) && !in_array($data['priority'], $validPriorities)) {
             throw new Exception("Невірний пріоритет: {$data['priority']}");
        }
    }

    /**
     * Створити нову задачу
     *
     * Валідує, створює запис, логує в audit та диспатчить TaskCreated.
     *
     * @param array $data Дані задачі (title, description, order_id, executors, priority, deadline)
     * @return int ID створеної задачі
     * @throws Exception При помилці валідації або БД
     */
    public function createTask(array $data): int {
        $this->validateTaskData($data);

        $taskId = Task::create([
            'title' => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'order_id' => $data['order_id'] ?? null,
            'executors' => $data['executors'] ?? [],
            'priority' => $data['priority'] ?? 'medium',
            'deadline' => $data['deadline'] ?? null
        ]);

        if (!$taskId) {
            throw new Exception("Failed to create task");
        }

        $taskId = (int)$taskId;

        // Audit Log
        Audit::log('task_created', 'tasks', $taskId, null, ['title' => $data['title']]);

        // Dispatch Event
        $task = Task::getById($taskId);
        $executor = null;
        if (!empty($data['executors'])) {
            $firstExecutorId = is_array($data['executors']) ? $data['executors'][0] : $data['executors'];
            $executor = Auth::getUserById($firstExecutorId);
        }
        $creator = Auth::getUser();
        EventDispatcher::dispatch(new TaskCreated($task ?: $data, $executor, $creator));

        return $taskId;
    }
}
