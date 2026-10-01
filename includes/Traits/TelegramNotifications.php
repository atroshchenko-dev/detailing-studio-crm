<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Task;
use App\Auth;
use Exception;

/**
 * Telegram Notifications — синхронізація, повідомлення, алерти
 *
 * Відповідає за створення/оновлення/видалення Telegram-повідомлень
 * для замовлень та задач. Підтримує групові топіки та персональні чати.
 *
 * @package App\Traits
 * @property string $apiUrl
 */
trait TelegramNotifications
{
    /**
     * Надіслати повідомлення в чат
     *
     * @param int|string  $chatId           ID чату
     * @param string      $message          Текст повідомлення (HTML)
     * @param array|null  $keyboard         Inline keyboard markup
     * @param array|null  $notificationMeta Метадані для збереження в БД
     * @return array|false Відповідь API або false
     */
    public static function sendMessage($chatId, $message, $keyboard = null, $notificationMeta = null)
    {
        if (empty(self::getToken())) return false;

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'keyboard' => $keyboard
        ];

        if ($notificationMeta) {
            $data['save_notification'] = true;
            $data['notification_meta'] = $notificationMeta;
        }

        // Instant Send (Bypass Queue)
        return self::processQueueMessage($data);
    }

    /**
     * Редагувати існуюче повідомлення
     *
     * @param int|string $chatId    ID чату
     * @param int        $messageId ID повідомлення
     * @param string     $message   Новий текст
     * @param array|null $keyboard  Inline keyboard markup
     * @return array|false Відповідь API або false
     */
    public static function editMessage($chatId, $messageId, $message, $keyboard = null)
    {
        if (empty(self::getToken())) return false;

        return self::processQueueEditMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'keyboard' => $keyboard
        ]);
    }

    /**
     * Фактична відправка повідомлення через API
     *
     * Зберігає повідомлення в telegram_notifications
     * якщо передано save_notification=true.
     *
     * @param array $data Дані повідомлення {chat_id, text, keyboard, message_thread_id, ...}
     * @return array|false Відповідь API
     */
    public static function processQueueMessage($data)
    {
        $url = self::$apiUrl . self::getToken() . '/sendMessage';

        $requestData = [
            'chat_id' => $data['chat_id'],
            'text' => $data['text'],
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        if (!empty($data['keyboard'])) {
            $requestData['reply_markup'] = json_encode($data['keyboard']);
        }

        if (!empty($data['message_thread_id'])) {
            $requestData['message_thread_id'] = $data['message_thread_id'];
        }

        $response = self::makeRequest($url, $requestData);

        if (!$response || !isset($response['ok']) || !$response['ok']) {
            self::logDebug("QUEUE SEND FAILED to {$data['chat_id']}. Error: " . json_encode($response));
            return false;
        }

        // Handle post-send actions (like saving notification)
        if (isset($data['save_notification']) && $data['save_notification']) {
            $meta = $data['notification_meta'] ?? [];
            self::saveNotification(
                $meta['user_id'] ?? null,
                $data['text'],
                $meta['entity_type'] ?? null,
                $meta['entity_id'] ?? null,
                $data['chat_id'],
                $response['result']['message_id']
            );
        }

        return $response;
    }

    /**
     * Фактичне редагування повідомлення через API
     *
     * @param array $data Дані {chat_id, message_id, text, keyboard}
     * @return bool true при успіху (включаючи "not modified")
     */
    public static function processQueueEditMessage($data)
    {
        $params = [
            'chat_id' => $data['chat_id'],
            'message_id' => $data['message_id'],
            'text' => $data['text'],
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        if (!empty($data['keyboard'])) {
            $params['reply_markup'] = json_encode($data['keyboard']);
        }

        $response = self::request('editMessageText', $params);

        if (isset($response['ok']) && $response['ok']) {
            return true;
        }

        // Check for "message is not modified" error
        if (isset($response['description']) && strpos($response['description'], 'message is not modified') !== false) {
            return true; // Treat as success
        }

        return false;
    }

    /**
     * Надіслати повідомлення в групу (з підтримкою топіків)
     *
     * @param string     $message          Текст повідомлення
     * @param array|null $keyboard         Inline keyboard
     * @param array|null $notificationMeta Мета для збереження
     * @param int|null   $topicId          ID топіку (thread_id)
     * @return array|false Відповідь API
     */
    public static function sendToGroup($message, $keyboard = null, $notificationMeta = null, $topicId = null)
    {
        $groupId = self::getGroupChatId();
        if (empty($groupId)) return false;

        $data = [
            'chat_id' => $groupId,
            'text' => $message,
            'keyboard' => $keyboard,
        ];

        if ($topicId) {
            $data['message_thread_id'] = $topicId;
        }

        if ($notificationMeta) {
            $data['save_notification'] = true;
            $data['notification_meta'] = $notificationMeta;
        }
        // Instant Send
        return self::processQueueMessage($data);
    }

    /**
     * Зберегти відправлене повідомлення в БД
     *
     * @param int|null    $userId            ID користувача
     * @param string      $message           Текст повідомлення
     * @param string|null $entityType        Тип сутності (order/task)
     * @param int|null    $entityId          ID сутності
     * @param int|string  $chatId            ID чату
     * @param int|null    $telegramMessageId ID повідомлення в Telegram
     * @return void
     */
    private static function saveNotification($userId, $message, $entityType, $entityId, $chatId = 0, $telegramMessageId = null)
    {
        try {
            $stmt = db()->prepare("INSERT INTO telegram_notifications (user_id, chat_id, telegram_message_id, message, entity_type, entity_id, status, sent_at) VALUES (?, ?, ?, ?, ?, ?, 'sent', NOW())");
            $stmt->execute([$userId, $chatId, $telegramMessageId, $message, $entityType, $entityId]);
        } catch (Exception $e) {
            error_log("Failed to save notification: " . $e->getMessage());
        }
    }

    /**
     * Сформувати секцію Історія змін для Telegram-повідомлення
     *
     * @param string $entityType Тип сутності (order/task)
     * @param int    $entityId   ID сутності
     * @param int    $limit      Максимум записів
     * @return string HTML-текст секції
     */
    public static function buildHistorySection($entityType, $entityId, $limit = 5)
    {
        $tableName = ($entityType === 'order') ? 'orders' : 'tasks';
        $logs = \App\Audit::getLog([
            'table_name' => $tableName,
            'record_id' => $entityId
        ]);

        if (empty($logs)) return "";

        $history = "\n\n<b>🕰️ Історія змін:</b>\n";
        $logs = array_slice($logs, 0, $limit);

        foreach ($logs as $log) {
            $datetime = date('d.m H:i', strtotime($log['created_at']));
            $userNameStr = htmlspecialchars($log['user_name'] ?: 'Система');
            
            if (!empty($log['telegram_username'])) {
                $user = "<a href=\"https://t.me/" . htmlspecialchars($log['telegram_username']) . "\">{$userNameStr}</a>";
            } elseif (!empty($log['telegram_chat_id'])) {
                $user = "<a href=\"tg://user?id=" . htmlspecialchars($log['telegram_chat_id']) . "\">{$userNameStr}</a>";
            } else {
                $user = $userNameStr;
            }
            
            $action = \App\Audit::getActionDescription($log['action']);

            // Try to build detailed change description from old/new values
            $details = self::formatFieldChanges($log);
            if ($details) {
                $action = $details;
            } else {
                $action = str_replace(['Оновлення замовлення', 'Оновлення задачі'], 'Зміна', $action);
            }

            $history .= "▫️ {$datetime} - {$user}: {$action}\n";
        }

        return $history;
    }

    /**
     * Форматувати зміни полів у людино-зрозумілий текст
     *
     * @param array $log Запис аудит-логу
     * @return string Форматований текст змін або порожній рядок
     */
    private static function formatFieldChanges(array $log): string
    {
        $oldValue = !empty($log['old_value']) ? json_decode($log['old_value'], true) : null;
        $newValue = !empty($log['new_value']) ? json_decode($log['new_value'], true) : null;

        if (!$oldValue || !$newValue) {
            // For creation actions, return the action description as-is
            return '';
        }

        $fieldLabels = [
            'status' => 'статусу',
            'deadline' => 'дедлайну',
            'priority' => 'пріоритету',
            'executor_id' => 'виконавця',
            'title' => 'назви',
            'description' => 'опису',
            'total_amount' => 'суми',
            'paid_amount' => 'оплати',
            'notes' => 'приміток',
            'planned_start' => 'дати прибуття',
            'car_id' => 'авто',
        ];

        $statusNames = [
            'new' => 'Нове',
            'approved' => 'Погоджено',
            'in_progress' => 'В роботі',
            'waiting_client' => 'Очікує клієнта',
            'ready' => 'Готово',
            'closed' => 'Закрито',
            'cancelled' => 'Скасовано',
            'created' => 'Створено',
            'completed' => 'Виконано',
        ];

        $priorityNames = [
            'low' => 'Низький',
            'medium' => 'Середній',
            'high' => 'Високий',
            'urgent' => 'Терміново',
        ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            if (!array_key_exists($field, $newValue)) continue;

            $old = $oldValue[$field] ?? null;
            $new = $newValue[$field] ?? null;

            if ($old == $new || ($old === null && $new === null)) continue;

            // Format values based on field type
            if ($field === 'status') {
                $oldFormatted = $statusNames[$old] ?? $old;
                $newFormatted = $statusNames[$new] ?? $new;
            } elseif ($field === 'priority') {
                $oldFormatted = $priorityNames[$old] ?? $old;
                $newFormatted = $priorityNames[$new] ?? $new;
            } elseif (in_array($field, ['deadline', 'planned_start'])) {
                $oldFormatted = $old ? date('d.m', strtotime($old)) : '—';
                $newFormatted = $new ? date('d.m', strtotime($new)) : '—';
            } elseif (in_array($field, ['total_amount', 'paid_amount'])) {
                $oldFormatted = $old ? number_format((float)$old, 0, '.', ' ') . ' грн' : '0 грн';
                $newFormatted = $new ? number_format((float)$new, 0, '.', ' ') . ' грн' : '0 грн';
            } elseif (in_array($field, ['notes', 'description', 'title'])) {
                // For text fields, just indicate it was changed without showing full text
                $changes[] = "Зміна {$label}";
                continue;
            } else {
                $oldFormatted = $old ?: '—';
                $newFormatted = $new ?: '—';
            }

            if ($oldFormatted === $newFormatted) continue;

            $changes[] = "Зміна {$label}: {$oldFormatted} → {$newFormatted}";
        }

        if (empty($changes)) return '';

        // Show up to 2 most important changes to keep it compact
        return implode(', ', array_slice($changes, 0, 2));
    }

    /**
     * Синхронізувати/створити уніфіковане повідомлення для замовлення
     *
     * Оновлює існуючі повідомлення (група + менеджер) або створює нові.
     * Позначає змінені поля ✨ для візуального виділення.
     *
     * @param int   $orderId       ID замовлення
     * @param array $changedFields Список змінених полів
     * @return bool|false
     */
    public static function syncOrderNotification($orderId, array $changedFields = [])
    {
        $order = \App\Models\Order::getById($orderId);
        if (!$order) return false;

        $client = \App\Models\Client::getById($order['client_id']);
        $car = \App\Models\Car::getById($order['car_id']);

        $statusNames = [
            'new' => 'Нове',
            'approved' => 'Погоджено',
            'in_progress' => 'В роботі',
            'waiting_client' => 'Очікує клієнта',
            'ready' => 'Готово',
            'closed' => 'Закрито',
            'cancelled' => 'Скасовано'
        ];
        $statusIcons = [
            'new' => '🆕',
            'approved' => '🤝',
            'in_progress' => '⚙️',
            'waiting_client' => '⏳',
            'ready' => '🛎️',
            'closed' => '✅',
            'cancelled' => '⭕️'
        ];

        $icon = $statusIcons[$order['status']] ?? '🔄';
        $isStatusChanged = in_array('status', $changedFields);
        $statusText = ($statusNames[$order['status']] ?? $order['status']) . ($isStatusChanged ? " ✨" : "");

        $message = "{$icon} <b>ЗАМОВЛЕННЯ #{$order['order_number']}</b> ({$statusText})\n\n";

        $clientInfo = htmlspecialchars($client['name'] ?? '-');
        if (!empty($client['phone'])) $clientInfo .= " (" . \App\Utils\Helper::maskPhone($client['phone']) . ")";
        $message .= "👤 <b>Клієнт:</b> {$clientInfo}\n";

        $carInfo = htmlspecialchars(($car['make'] ?? '') . ' ' . ($car['model'] ?? ''));
        if (!empty($car['license_plate'])) $carInfo .= " (" . htmlspecialchars($car['license_plate']) . ")";

        $isCarChanged = !empty(array_intersect(['car_id', 'make', 'model', 'license_plate'], $changedFields));
        $message .= "🚘 <b>Авто:</b> {$carInfo}" . ($isCarChanged ? " ✨" : "") . "\n";

        if ($order['deadline']) {
            $isDeadlineChanged = in_array('deadline', $changedFields);
            $message .= "⏰ <b>Дедлайн:</b> " . format_date($order['deadline']) . ($isDeadlineChanged ? " ✨" : "") . "\n";
        }

        if (!empty($order['planned_start'])) {
            $isArrivalChanged = in_array('planned_start', $changedFields);
            $message .= "📅 <b>Прибуття:</b> " . format_date($order['planned_start']) . ($isArrivalChanged ? " ✨" : "") . "\n";
        }

        if (!empty($order['notes'])) {
            $isNotesChanged = in_array('notes', $changedFields);
            $message .= "\n💬 <b>Примітки:</b> " . htmlspecialchars($order['notes']) . ($isNotesChanged ? " ✨" : "") . "\n";
        }

        $url = (defined('APP_URL') ? APP_URL : '') . "/orders/view.php?id={$orderId}";
        $message .= "\n🔗 <a href=\"{$url}\">Відкрити в системі</a>";

        $message .= self::buildHistorySection('order', $orderId);
        $message .= "\n#замовлення #{$order['status']}";

        // Find existing notifications
        $stmt = db()->prepare("SELECT * FROM telegram_notifications WHERE entity_type = 'order' AND entity_id = ?");
        $stmt->execute([$orderId]);
        $notifications = $stmt->fetchAll();

        $groupChatId = self::getGroupChatId();
        $groupUpdated = false;

        foreach ($notifications as $notif) {
            $isGroup = ($notif['chat_id'] == $groupChatId);
            $keyboard = $isGroup ? self::getGroupOrderKeyboard($orderId) : self::getOrderKeyboard($orderId, $order['status']);

            if ($notif['telegram_message_id']) {
                $msgToSend = $message;
                if (!$isGroup && !empty($client['phone'])) {
                    // Unmask phone for personal messages
                    $msgToSend = str_replace(\App\Utils\Helper::maskPhone($client['phone']), htmlspecialchars($client['phone']), $message);
                }

                $success = self::editMessage($notif['chat_id'], $notif['telegram_message_id'], $msgToSend, $keyboard);
                if ($isGroup && $success) {
                    $groupUpdated = true;
                }
            }
        }

        // If no group notification yet, create one
        if (!$groupUpdated) {
            self::sendToGroup($message, self::getGroupOrderKeyboard($orderId), [
                'entity_type' => 'order',
                'entity_id' => $orderId
            ], defined('TELEGRAM_TOPIC_ORDERS') ? TELEGRAM_TOPIC_ORDERS : null);
        }

        // Personal notification to manager/creator
        if (!empty($order['manager_id'])) {
            $manager = Auth::getUserById((int)$order['manager_id']);
            if ($manager && !empty($manager['telegram_chat_id'])) {
                $managerChatId = $manager['telegram_chat_id'];

                $alreadyNotified = false;
                foreach ($notifications as $notif) {
                    if ($notif['chat_id'] == $managerChatId) {
                        $alreadyNotified = true;
                        break;
                    }
                }

                if (!$alreadyNotified) {
                    $personalMessage = $message;
                    if (!empty($client['phone'])) {
                        $personalMessage = str_replace(\App\Utils\Helper::maskPhone($client['phone']), htmlspecialchars($client['phone']), $message);
                    }
                    self::sendMessage($managerChatId, $personalMessage, self::getOrderKeyboard($orderId, $order['status']), [
                        'entity_type' => 'order',
                        'entity_id' => $orderId,
                        'user_id' => $order['manager_id']
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * Сформувати клавіатуру для замовлень в загальній групі
     *
     * @param int $orderId ID замовлення
     * @return array
     */
    private static function getGroupOrderKeyboard($orderId)
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📞 Контакти', 'callback_data' => 'order_contacts_' . $orderId]
                ]
            ]
        ];
    }

    /**
     * Синхронізувати/створити уніфіковане повідомлення для задачі
     *
     * При завершенні задачі видаляє персональні повідомлення.
     * Позначає змінені поля ✨ для візуального виділення.
     *
     * @param int   $taskId        ID задачі
     * @param array $changedFields Список змінених полів
     * @return bool|false
     */
    public static function syncTaskNotification($taskId, array $changedFields = [])
    {
        $task = Task::getById($taskId);
        if (!$task) return false;

        // Find existing notifications
        $stmt = db()->prepare("SELECT * FROM telegram_notifications WHERE entity_type = 'task' AND entity_id = ?");
        $stmt->execute([$taskId]);
        $notifications = $stmt->fetchAll();

        // If task is deleted and no previous notifications exist, don't start a new one
        if (!empty($task['is_deleted']) && empty($notifications)) {
            return true;
        }

        $priorityIcons = ['low' => '🟢', 'medium' => '🟡', 'high' => '🟠', 'urgent' => '🔴'];
        $statusIcons = ['created' => '🆕', 'in_progress' => '⚙️', 'completed' => '☑️', 'closed' => '📁', 'cancelled' => '⭕️'];

        $pIcon = $priorityIcons[$task['priority'] ?? 'medium'] ?? '🔵';
        $sIcon = $statusIcons[$task['status']] ?? '🔄';

        if (!empty($task['is_deleted'])) {
            $sIcon = '🗑️';
            $statusName = "ВИДАЛЕНО";
        } else {
            $isStatusChanged = in_array('status', $changedFields);
            $statusName = Task::getStatusName($task['status']) . ($isStatusChanged ? " ✨" : "");
        }

        $isTitleChanged = in_array('title', $changedFields);
        $message = "{$pIcon}{$sIcon} <b>ЗАДАЧА #{$taskId}</b> ({$statusName})\n\n";
        $message .= "📝 <b>Назва:</b> " . htmlspecialchars($task['title']) . ($isTitleChanged ? " ✨" : "") . "\n";

        if (!empty($task['order_id'])) {
            $order = \App\Models\Order::getById($task['order_id']);
            if ($order) {
                $carInfo = ($order['make'] ?? '') . ' ' . ($order['model'] ?? '');
                if (!empty($order['license_plate'])) $carInfo .= " (" . ($order['license_plate']) . ")";
                $message .= "🚘 <b>Авто:</b> " . htmlspecialchars($carInfo) . "\n";
            }
        }

        $isExecutorsChanged = in_array('executor_ids', $changedFields) || in_array('executors', $changedFields);
        
        $executorsStr = 'Не призначено';
        if (!empty($task['executors'])) {
            $execNames = [];
            foreach ($task['executors'] as $exec) {
                $execNameStr = htmlspecialchars($exec['full_name']);
                if (!empty($exec['telegram_username'])) {
                    $execNames[] = "<a href=\"https://t.me/" . htmlspecialchars($exec['telegram_username']) . "\">{$execNameStr}</a>";
                } elseif (!empty($exec['telegram_chat_id'])) {
                    $execNames[] = "<a href=\"tg://user?id=" . htmlspecialchars($exec['telegram_chat_id']) . "\">{$execNameStr}</a>";
                } else {
                    $execNames[] = $execNameStr;
                }
            }
            $executorsStr = implode(', ', $execNames);
        }
        
        $message .= "👤 <b>Виконавці:</b> {$executorsStr}" . ($isExecutorsChanged ? " ✨" : "") . "\n";

        if ($task['deadline']) {
            $isDeadlineChanged = in_array('deadline', $changedFields);
            $message .= "⏰ <b>Дедлайн:</b> " . format_date($task['deadline']) . ($isDeadlineChanged ? " ✨" : "") . "\n";
        }

        if (!empty($task['description']) && ($isTitleChanged || in_array('description', $changedFields))) {
            $message .= "\n💬 <b>Опис:</b> " . htmlspecialchars($task['description']) . "\n";
        }

        $url = (defined('APP_URL') ? APP_URL : '') . "/tasks/view.php?id={$taskId}";
        $message .= "\n🔗 <a href=\"{$url}\">Відкрити в системі</a>";

        $message .= self::buildHistorySection('task', $taskId);
        $message .= "\n#задача #{$task['status']}";

        $groupChatId = self::getGroupChatId();
        $notifiedChatIds = [];

        // If task is completed/closed, delete personal notifications
        if (in_array($task['status'], ['completed', 'closed'])) {
            foreach ($notifications as $notif) {
                if ($notif['chat_id'] != $groupChatId && $notif['telegram_message_id']) {
                    self::deleteMessage($notif['chat_id'], $notif['telegram_message_id']);
                }
            }
            $stmt = db()->prepare("DELETE FROM telegram_notifications WHERE entity_type = 'task' AND entity_id = ? AND chat_id != ?");
            $stmt->execute([$taskId, $groupChatId]);

            $groupNotif = null;
            foreach ($notifications as $notif) {
                if ($notif['chat_id'] == $groupChatId && $notif['telegram_message_id']) {
                    self::editMessage($notif['chat_id'], $notif['telegram_message_id'], $message, null);
                    $groupNotif = $notif;
                }
            }
            if (!$groupNotif) {
                self::sendToGroup($message, null, [
                    'entity_type' => 'task',
                    'entity_id' => $taskId
                ], defined('TELEGRAM_TOPIC_TASKS') ? TELEGRAM_TOPIC_TASKS : null);
            }
            return true;
        }

        // 1. Update existing messages
        foreach ($notifications as $notif) {
            $notifiedChatIds[] = $notif['chat_id'];
            $isGroup = ($notif['chat_id'] == $groupChatId);
            $keyboard = ($isGroup || !empty($task['is_deleted'])) ? null : self::getTaskKeyboard($taskId, $task['status']);

            if ($notif['telegram_message_id']) {
                self::editMessage($notif['chat_id'], $notif['telegram_message_id'], $message, $keyboard);
            }
        }

        // 2. Ensure Group is notified
        if (!in_array($groupChatId, $notifiedChatIds)) {
            self::sendToGroup($message, null, [
                'entity_type' => 'task',
                'entity_id' => $taskId
            ], defined('TELEGRAM_TOPIC_TASKS') ? TELEGRAM_TOPIC_TASKS : null);
        }

        // 3. Ensure all Executors are notified personally
        if (!empty($task['executors'])) {
            foreach ($task['executors'] as $executor) {
                if (!empty($executor['telegram_chat_id']) && !in_array($executor['telegram_chat_id'], $notifiedChatIds)) {
                    if ($executor['telegram_chat_id'] == $groupChatId) continue;

                    $keyboard = self::getTaskKeyboard($taskId, $task['status']);
                    self::sendMessage($executor['telegram_chat_id'], $message, $keyboard, [
                        'entity_type' => 'task',
                        'entity_id' => $taskId,
                        'user_id' => $executor['id']
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * Сповістити про зміну статусу задачі (делегує syncTaskNotification)
     *
     * @param array       $task      Дані задачі
     * @param string      $oldStatus Старий статус
     * @param string      $newStatus Новий статус
     * @param array|null  $user      Користувач
     * @return bool|false
     */
    public static function notifyTaskStatusChange($task, $oldStatus, $newStatus, $user)
    {
        return self::syncTaskNotification($task['id'], ['status']);
    }

    /**
     * Сповістити про нове замовлення (делегує syncOrderNotification)
     *
     * @param array $order  Дані замовлення
     * @param array $client Дані клієнта
     * @param array $car    Дані авто
     * @return bool|false
     */
    public static function notifyNewOrder($order, $client, $car)
    {
        return self::syncOrderNotification($order['id']);
    }

    /**
     * Notify order status change
     */
    public static function notifyOrderStatusChange($order, $oldStatus, $newStatus, $user)
    {
        return self::syncOrderNotification($order['id']);
    }

    /**
     * Сповістити про завершення всіх задач замовлення
     *
     * @param array $order Дані замовлення
     * @return bool|false
     */
    public static function notifyOrderAllTasksCompleted($order)
    {
        return self::syncOrderNotification($order['id']);
    }

    /**
     * Сповістити про наближення/прострочення дедлайну задачі
     *
     * @param array    $task      Дані задачі
     * @param int|float $hoursLeft Кількість годин до дедлайну
     * @return void
     */
    public static function notifyDeadline($task, $hoursLeft)
    {
        $message = "🚨 <b>ДЕДЛАЙН ЗАДАЧІ</b>\n\n";
        $message .= "📝 <b>Задача:</b> " . htmlspecialchars($task['title']) . "\n";
        $message .= "⏰ <b>Дедлайн:</b> " . format_date($task['deadline']) . "\n";
        $message .= "⌛ <b>Залишилось:</b> " . $hoursLeft . " годин\n";

        if ($task['executor_id']) {
            $executor = Auth::getUserById($task['executor_id']);
            if ($executor && !empty($executor['telegram_chat_id']) && $executor['telegram_chat_id'] != self::getGroupChatId()) {
                $keyboard = self::getTaskKeyboard($task['id'], $task['status'] ?? 'created');
                self::sendMessage($executor['telegram_chat_id'], $message, $keyboard);
            }
        }

        $message .= "\n#задача #дедлайн";
        self::sendToGroup($message, null, null, defined('TELEGRAM_TOPIC_ALERTS') ? TELEGRAM_TOPIC_ALERTS : null);
    }

    /**
     * Сповістити менеджерів про низький залишок на складі
     *
     * @param array $item Дані складської позиції
     * @return void
     */
    public static function notifyLowStock($item)
    {
        $message = "📉 <b>НИЗЬКИЙ ЗАЛИШОК НА СКЛАДІ</b>\n\n";
        $message .= "📦 <b>Назва:</b> " . htmlspecialchars($item['name']) . "\n";
        $message .= "📊 <b>Поточний залишок:</b> " . $item['current_quantity'] . " " . $item['unit'] . "\n";
        $message .= "⚠️ <b>Мінімальний залишок:</b> " . $item['min_quantity'] . " " . $item['unit'] . "\n";
        $message .= "\n#склад #низький_залишок";

        $stmt = db()->query("SELECT * FROM users WHERE role IN ('manager', 'director') AND telegram_chat_id IS NOT NULL AND is_active = 1");
        $users = $stmt->fetchAll();
        foreach ($users as $user) {
            self::sendMessage($user['telegram_chat_id'], $message);
        }

        self::sendToGroup($message, null, null, defined('TELEGRAM_TOPIC_ALERTS') ? TELEGRAM_TOPIC_ALERTS : null);
    }

    /**
     * Видалити всі Telegram-повідомлення для задачі
     *
     * @param int $taskId ID задачі
     * @return bool
     */
    public static function deleteTaskNotifications($taskId)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM telegram_notifications WHERE entity_type = 'task' AND entity_id = ?");
            $stmt->execute([$taskId]);
            $notifications = $stmt->fetchAll();

            foreach ($notifications as $notif) {
                if ($notif['telegram_message_id']) {
                    self::deleteMessage($notif['chat_id'], $notif['telegram_message_id']);
                }
            }

            $stmt = db()->prepare("DELETE FROM telegram_notifications WHERE entity_type = 'task' AND entity_id = ?");
            return $stmt->execute([$taskId]);
        } catch (Exception $e) {
            error_log("Failed to delete task notifications: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Видалити всі Telegram-повідомлення для замовлення
     *
     * @param int $orderId ID замовлення
     * @return bool
     */
    public static function deleteOrderNotifications($orderId)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM telegram_notifications WHERE entity_type = 'order' AND entity_id = ?");
            $stmt->execute([$orderId]);
            $notifications = $stmt->fetchAll();

            foreach ($notifications as $notif) {
                if ($notif['telegram_message_id']) {
                    self::deleteMessage($notif['chat_id'], $notif['telegram_message_id']);
                }
            }

            $stmt = db()->prepare("DELETE FROM telegram_notifications WHERE entity_type = 'order' AND entity_id = ?");
            return $stmt->execute([$orderId]);
        } catch (Exception $e) {
            error_log("Failed to delete order notifications: " . $e->getMessage());
            return false;
        }
    }
}
