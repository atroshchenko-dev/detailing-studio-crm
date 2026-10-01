<?php

/**
 * Migration: Sync existing tasks to Google Calendar
 * Adds existing tasks with deadlines to Google Calendar
 */

require_once __DIR__ . '/../config/config.php';

use App\GoogleIntegration;
use App\Models\Task;
use App\Models\Order;

echo "=== Синхронізація існуючих задач з Google Calendar ===\n\n";

if (!GoogleIntegration::isConfigured()) {
    echo "❌ Google Integration не налаштовано. Пропускаємо міграцію.\n";
    exit(0);
}

// Знайти всі задачі з дедлайном, які не мають google_event_id
$stmt = db()->prepare("
    SELECT t.* 
    FROM tasks t
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.deadline IS NOT NULL 
    AND (t.google_event_id IS NULL OR t.google_event_id = '')
    AND t.is_deleted = 0
    AND (o.is_deleted = 0 OR o.id IS NULL)
    ORDER BY t.deadline ASC
");
$stmt->execute();
$tasks = $stmt->fetchAll();

$total = count($tasks);
echo "Знайдено задач для синхронізації: $total\n\n";

if ($total === 0) {
    echo "✅ Немає задач для синхронізації.\n";
    exit(0);
}

$success = 0;
$failed = 0;

foreach ($tasks as $taskData) {
    $task = Task::getById($taskData['id']);
    if (!$task) {
        echo "⚠️  Не вдалось завантажити задачу #{$taskData['id']}\n";
        $failed++;
        continue;
    }

    // Побудувати title і description (така ж логіка як в GoogleCalendarListener)
    $priorityIcons = ['low' => '⚪', 'medium' => '🔵', 'high' => '🟠', 'urgent' => '🔥'];
    $pIcon = $priorityIcons[$task['priority'] ?? 'medium'] ?? '🔵';

    if ($task['status'] === 'completed') {
        $pIcon = '✅';
    }

    $executorName = $task['executor_names'] ?: 'Не призначено';
    $title = "{$pIcon} Задача: " . $task['title'] . " ({$executorName})";

    $description = "";
    if (!empty($task['order_id'])) {
        $order = Order::getById($task['order_id']);
        if ($order) {
            $description .= "🚗 Замовлення #{$order['order_number']} ({$order['make']} {$order['model']})\n\n";
        }
    }
    if (!empty($task['description'])) {
        $description .= "📝 Опис:\n" . $task['description'] . "\n\n";
    }
    $description .= "Статус: " . Task::getStatusName($task['status']) . "\n";
    $description .= "LINK: " . (defined('APP_URL') ? APP_URL : '') . "/tasks/view.php?id={$task['id']}";

    echo "Задача #{$task['id']}: {$task['title']} (дедлайн: {$task['deadline']})... ";

    $result = GoogleIntegration::createTaskCalendarEvent(
        (int)$task['id'],
        $title,
        $task['deadline'],
        $description
    );

    if ($result) {
        echo "✅ Синхронізовано (Event ID: $result)\n";
        $success++;
    } else {
        echo "❌ Помилка\n";
        $failed++;
    }

    // Невелика затримка, щоб не перевантажити API
    usleep(200000); // 0.2 секунди
}

echo "\n=== Результати ===\n";
echo "✅ Успішно: $success\n";
echo "❌ Помилки: $failed\n";
echo "📊 Всього: $total\n";
