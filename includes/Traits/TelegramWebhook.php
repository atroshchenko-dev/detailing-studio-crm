<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Task;
use App\Auth;
use Exception;

/**
 * Telegram Webhook — обробка вхідних запитів, callback, клавіатури
 *
 * Обробляє webhook-запити від Telegram: текстові команди,
 * callback_query (кнопки), reply-коментарі, фото-вкладення.
 * Маршрутизує конверсації до відповідних Wizards.
 *
 * @package App\Traits
 * @property string $apiUrl
 * @property string $suppressNotificationsForChatId
 */
trait TelegramWebhook
{
    /**
     * Обробити вхідний webhook-запит
     *
     * Маршрутизує message/callback_query до відповідного обробника.
     *
     * @param string|null $input JSON-тіло запиту (null = php://input)
     * @return void
     */
    public static function handleWebhook($input = null)
    {
        if ($input === null) {
            $input = file_get_contents('php://input');
        }
        $update = json_decode($input, true);

        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? $message['caption'] ?? '';
            $username = $message['from']['username'] ?? '';

            // Clean up chat: automatically delete known command messages
            $knownCommands = [
                '/start', 'Start', 'start', '/cancel', '/help', '/my_tasks', '/my_schedule', 
                '/profit', '/new_order', '/my_earnings', '/my_payouts', '/close_day',
                \App\BotConstants::BTN_NEW_ORDER,
                \App\BotConstants::BTN_NEW_TASK,
                \App\BotConstants::BTN_MY_TASKS,
                \App\BotConstants::BTN_ACTIVE_ORDERS,
                \App\BotConstants::BTN_MY_EARNINGS,
                \App\BotConstants::BTN_MY_PAYOUTS,
                \App\BotConstants::BTN_FINANCE,
                \App\BotConstants::BTN_CLOSE_DAY
            ];

            if (in_array($text, $knownCommands) || strpos($text, '/start ') === 0) {
                if (isset($message['message_id'])) {
                    self::deleteMessage($chatId, $message['message_id']);
                }
            }

            // Handle Start Command
            if (strpos($text, '/start') === 0) {
                $parts = explode(' ', $text);
                $payload = $parts[1] ?? null;
                self::handleStartCommand($chatId, $username, $payload);
                return;
            }

            // Find user ID for menu commands
            $stmt = db()->prepare("SELECT id FROM users WHERE telegram_chat_id = ?");
            $stmt->execute([$chatId]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                $_SESSION['user_id'] = $userId;
            }

            if ($text === '/start' || $text === 'Start' || $text === 'start') {
                self::handleStartCommand($chatId, $username, null);
                return;
            } elseif ($text === \App\BotConstants::BTN_NEW_ORDER) {
                if ($userId) {
                    $stmtRole = db()->prepare("SELECT role FROM users WHERE id = ?");
                    $stmtRole->execute([$userId]);
                    $userRole = $stmtRole->fetchColumn();
                    if (in_array($userRole, ['director', 'manager'])) {
                        \App\OrderWizard::start($chatId, $userId, $message['message_id'] ?? null);
                    } else {
                        self::sendMessage($chatId, "⛔ У вас немає прав для створення замовлень.");
                    }
                } else {
                    self::sendMessage($chatId, "❌ Ваш акаунт не знайдено в системі. Будь ласка, прив'яжіть його за допомогою команди /start.");
                }
                return;
            } elseif ($text === \App\BotConstants::BTN_NEW_TASK) {
                if ($userId) {
                    $stmtRole = db()->prepare("SELECT role FROM users WHERE id = ?");
                    $stmtRole->execute([$userId]);
                    $userRole = $stmtRole->fetchColumn();
                    if (in_array($userRole, ['director', 'manager'])) {
                        \App\TaskWizard::start($chatId, $userId, null, $message['message_id'] ?? null);
                    } else {
                        self::sendMessage($chatId, "⛔ У вас немає прав для створення задач.");
                    }
                } else {
                    self::sendMessage($chatId, "❌ Ваш акаунт не знайдено в системі. Будь ласка, прив'яжіть його за допомогою команди /start.");
                }
                return;
            } elseif ($text === \App\BotConstants::BTN_MY_TASKS) {
                self::handleMyTasksCommand($chatId, $username);
                return;
            } elseif ($text === \App\BotConstants::BTN_ACTIVE_ORDERS) {
                self::handleMyOrdersCommand($chatId, $username);
                return;
            } elseif ($text === \App\BotConstants::BTN_MY_EARNINGS || $text === '/my_earnings') {
                self::handleMyEarningsCommand($chatId, $username);
                return;
            } elseif ($text === \App\BotConstants::BTN_MY_PAYOUTS || $text === '/my_payouts') {
                self::handleMyPayoutsCommand($chatId, $username);
                return;
            } elseif ($text === \App\BotConstants::BTN_FINANCE) {
                self::handleFinanceCommand($chatId, $username);
                return;
            } elseif ($text === \App\BotConstants::BTN_CLOSE_DAY || $text === '/close_day') {
                if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1') {
                    self::handleCloseDayCommand($chatId, $username);
                } else {
                    self::sendMessage($chatId, "❌ Функція виплат відключена в налаштуваннях.");
                }
                return;
            } elseif ($text === '/my_tasks') {
                self::handleMyTasksCommand($chatId, $username);
                return;
            } elseif ($text === '/my_schedule') {
                self::handleMyScheduleCommand($chatId, $username);
                return;
            } elseif ($text === '/profit') {
                self::handleProfitCommand($chatId, $username);
                return;
            } elseif ($text === '/new_order') {
                self::handleNewOrderCommand($chatId, $username, $message['message_id'] ?? null);
                return;
            } elseif ($text === '/help') {
                self::handleHelpCommand($chatId, $username);
                return;
            }

            if ($text === '/cancel') {
                \App\TelegramConversation::clear($chatId);
                self::sendMessage($chatId, "❌ Дію скасовано.");
                return;
            }

            // Check for active conversation
            $conversation = \App\TelegramConversation::get($chatId);
            if ($conversation) {
                if (isset($message['message_id'])) {
                    \App\TelegramConversation::addMessageId($chatId, $message['message_id']);
                }

                $stmt = db()->prepare("SELECT id FROM users WHERE telegram_chat_id = ?");
                $stmt->execute([$chatId]);
                $userId = $stmt->fetchColumn();

                if ($userId) {
                    if (strpos($conversation['step'], 'order_') === 0) {
                        \App\OrderWizard::handle($chatId, $userId, $text, $message);
                        return;
                    } elseif (strpos($conversation['step'], 'task_') === 0) {
                        \App\TaskWizard::handle($chatId, $userId, $text, $message);
                        return;
                    } elseif (strpos($conversation['step'], 'finance_') === 0) {
                        \App\FinanceWizard::handle($chatId, $userId, $text, $message);
                        return;
                    }
                }
            }

            // Handle Reply to Message (Comment)
            if (isset($message['reply_to_message'])) {
                self::handleReplyMessage($message, $chatId, $username);
                return;
            }

            // Fallback for unrecognized text commands
            if (!empty($text)) {
                self::sendMainMenu($chatId, "🤔 Я не розумію цю команду.\nВикористовуйте меню нижче або /help.");
            }
        }

        if (isset($update['callback_query'])) {
            self::handleCallbackQuery($update['callback_query']);
        }
    }

    /**
     * Обробити reply-повідомлення (коментар/фото до задачі)
     *
     * @param array      $message  Telegram message object
     * @param int|string $chatId   ID чату
     * @param string     $username Telegram username
     * @return void
     */
    private static function handleReplyMessage($message, $chatId, $username)
    {
        $replyTo = $message['reply_to_message'];
        $replyMessageId = $replyTo['message_id'];
        $text = $message['text'] ?? $message['caption'] ?? '';
        $photo = $message['photo'] ?? null;

        if (empty($text) && empty($photo)) return;

        // Find original notification
        $stmt = db()->prepare("SELECT * FROM telegram_notifications WHERE telegram_message_id = ? AND chat_id = ?");
        $stmt->execute([$replyMessageId, $chatId]);
        $notification = $stmt->fetch();

        if ($notification && $notification['entity_type'] === 'task') {
            $userStmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
            $userStmt->execute([$chatId]);
            $user = $userStmt->fetch();

            if ($user) {
                $oldSessionUser = $_SESSION['user_id'] ?? null;
                $_SESSION['user_id'] = $user['id'];

                $taskId = $notification['entity_id'];

                if (!empty($text)) {
                    $commentId = Task::addComment($taskId, $text);
                    if ($commentId) {
                        self::sendMessage($chatId, "✅ Коментар додано до задачі #" . $taskId);
                    } else {
                        self::sendMessage($chatId, "❌ Помилка додавання коментаря");
                    }
                }

                if ($photo) {
                    $bestPhoto = end($photo);
                    $fileData = self::downloadFile($bestPhoto['file_id']);

                    if ($fileData) {
                        $uploadDir = __DIR__ . '/../../uploads/tasks/' . $taskId . '/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        $fileName = 'telegram_' . date('Ymd_His') . '.' . $fileData['extension'];
                        $filePath = $uploadDir . $fileName;

                        if (file_put_contents($filePath, $fileData['content'])) {
                            $dbPath = 'uploads/tasks/' . $taskId . '/' . $fileName;
                            Task::addAttachment($taskId, $dbPath, $fileName, $user['id']);
                            self::sendMessage($chatId, "📷 Фото успішно прикріплено до задачі #" . $taskId);
                        } else {
                            self::sendMessage($chatId, "❌ Помилка збереження файлу");
                        }
                    } else {
                        self::sendMessage($chatId, "❌ Помилка завантаження файлу з Telegram");
                    }
                }

                $_SESSION['user_id'] = $oldSessionUser;
            } else {
                self::sendMessage($chatId, "❌ Ваш акаунт не знайдено в системі");
            }
        }
    }

    /**
     * Обробити callback_query (натискання inline-кнопок)
     *
     * Маршрутизує: task_*, page_*, view_*, wizard_*, order_*, olist_*, pay_*.
     *
     * @param array $callbackQuery Telegram callback_query object
     * @return void
     */
    private static function handleCallbackQuery($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];
        $telegramId = $callbackQuery['from']['id'];

        // Immediate answer is required by Telegram UI
        $url = self::$apiUrl . self::getToken() . '/answerCallbackQuery';
        self::makeRequest($url, ['callback_query_id' => $callbackQuery['id']]);

        $stmtGlobal = db()->prepare("SELECT id FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmtGlobal->execute([$telegramId]);
        $globalUserId = $stmtGlobal->fetchColumn();
        if ($globalUserId) {
            $_SESSION['user_id'] = $globalUserId;
        }

        $parts = explode('_', $data);
        if (count($parts) >= 3 && $parts[0] === 'task') {
            $action = $parts[1];
            $taskId = (int)$parts[2];
            $messageId = $callbackQuery['message']['message_id'] ?? null;

            $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
            $stmt->execute([$telegramId]);
            $user = $stmt->fetch();

            if ($user) {
                self::updateTaskStatus($taskId, $action, $user, $chatId, $messageId);
            }
        } elseif (strpos($data, 'page_tasks_') === 0) {
            $username = $callbackQuery['from']['username'] ?? '';
            $page = (int)str_replace('page_tasks_', '', $data);
            self::handleMyTasksCommand($chatId, $username, $callbackQuery['message']['message_id'], $page);
            return;
        } elseif (strpos($data, 'page_orders_') === 0) {
            $username = $callbackQuery['from']['username'] ?? '';
            $page = (int)str_replace('page_orders_', '', $data);
            self::handleMyOrdersCommand($chatId, $username, $callbackQuery['message']['message_id'], $page);
            return;
        } elseif (count($parts) >= 3 && $parts[0] === 'view' && $parts[1] === 'task') {
            $taskId = (int)$parts[2];
            $task = Task::getById($taskId);
            if ($task) {
                $message = "📂 <b>ПЕРЕГЛЯД ЗАДАЧІ</b>\n\n";
                $message .= "📋 <b>Назва:</b> " . htmlspecialchars($task['title']) . "\n";
                $message .= "👤 <b>Виконавці:</b> " . htmlspecialchars($task['executor_names'] ?? ($task['executor_name'] ?? 'Не призначено')) . "\n";
                $message .= "🔄 <b>Статус:</b> " . Task::getStatusName($task['status']) . "\n";
                if ($task['deadline']) $message .= "⏰ <b>Дедлайн:</b> " . format_date($task['deadline']) . "\n";
                if ($task['description']) $message .= "📝 <b>Опис:</b> " . htmlspecialchars(substr($task['description'], 0, 500)) . "\n";

                $keyboard = self::getTaskKeyboard($taskId, $task['status']);
                self::sendMessage($chatId, $message, $keyboard);
            }
        } elseif (strpos($data, 'wizard_') === 0) {
            if ($data === 'wizard_cancel' || $data === 'wizard_finance_cancel') {
                \App\TelegramConversation::clear($chatId);
                self::sendMessage($chatId, "❌ Дію скасовано.");
                return;
            }

            $stmt = db()->prepare("SELECT id FROM users WHERE telegram_chat_id = ? AND is_active = 1");
            $stmt->execute([$telegramId]);
            $user = $stmt->fetch();
            if ($user) {
                $conversation = \App\TelegramConversation::get($chatId);
                $step = $conversation['step'] ?? '';

                $cbMessageId = $callbackQuery['message']['message_id'] ?? null;
                if (strpos($data, 'wizard_task_') === 0 || strpos($step, 'task_') === 0) {
                    \App\TaskWizard::handleCallback($chatId, $user['id'], $data, $cbMessageId);
                } elseif (strpos($data, 'wizard_finance_') === 0 || strpos($step, 'finance_') === 0) {
                    \App\FinanceWizard::handleCallback($chatId, $user['id'], $data, $cbMessageId);
                } else {
                    \App\OrderWizard::handleCallback($chatId, $user['id'], $data, $cbMessageId);
                }
            } else {
                $logDir = dirname(__DIR__) . '/logs';
                file_put_contents($logDir . '/telegram_webhook.log', date('Y-m-d H:i:s') . " - DEBUG: WIZARD CALLBACK REJECTED: user not found for telegram_chat_id=$telegramId chatId=$chatId callback=$data" . PHP_EOL, FILE_APPEND);
            }
        } elseif (count($parts) >= 3 && $parts[0] === 'olist') {
            // Order list inline status change (from handleMyOrdersCommand)
            $action = $parts[1];
            $orderId = (int)$parts[2];
            $messageId = $callbackQuery['message']['message_id'] ?? null;

            $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
            $stmt->execute([$telegramId]);
            $user = $stmt->fetch();

            if ($user) {
                self::updateOrderStatus($orderId, $action, $user, $chatId, $messageId, true);
            }
        } elseif (count($parts) >= 2 && $parts[0] === 'pay') {
            $executorId = (int)$parts[1];
            $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
            $stmt->execute([$telegramId]);
            $user = $stmt->fetch();
            if ($user) {
                self::handlePayExecutorCallback($chatId, $user, $executorId, $callbackQuery['message']['message_id'] ?? null);
            }
        } elseif (count($parts) >= 3 && $parts[0] === 'order') {
            $action = $parts[1];
            $orderId = (int)$parts[2];

            $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
            $stmt->execute([$telegramId]);
            $user = $stmt->fetch();

            if ($user) {
                if ($action === 'add' && $parts[2] === 'task') {
                    \App\TaskWizard::start($chatId, $user['id'], (int)$parts[3]);
                } elseif ($action === 'contacts') {
                    if (in_array($user['role'], ['director', 'manager'])) {
                        $order = \App\Models\Order::getById($orderId);
                        if ($order) {
                            $client = \App\Models\Client::getById($order['client_id']);
                            if ($client && !empty($client['phone'])) {
                                $phoneFormatted = htmlspecialchars($client['phone']);
                                $clientName = htmlspecialchars($client['name'] ?? '-');
                                self::sendMessage($telegramId, "📞 <b>Контакти замовлення #{$order['order_number']}:</b>\n{$clientName} — {$phoneFormatted}");
                            } else {
                                self::sendMessage($telegramId, "ℹ️ Номер телефону не знайдено для замовлення #" . ($order['order_number'] ?? $orderId));
                            }
                        }
                    } else {
                        self::sendMessage($telegramId, "⛔ У вас немає прав для перегляду контактів клієнтів.");
                    }
                } else {
                    self::updateOrderStatus($orderId, $action, $user, $chatId, $callbackQuery['message']['message_id'] ?? null);
                }
            }
        }
    }

    /**
     * Сформувати inline-клавіатуру для задачі
     *
     * @param int    $taskId ID задачі
     * @param string $status Поточний статус
     * @return array Inline keyboard markup
     */
    private static function getTaskKeyboard($taskId, $status = 'created')
    {
        $url = APP_URL . '/tasks/view.php?id=' . $taskId;
        if (strpos($url, 'localhost') !== false && defined('TELEGRAM_WEBHOOK_URL') && !empty(TELEGRAM_WEBHOOK_URL)) {
            $ngrokBase = TELEGRAM_WEBHOOK_URL;
            $urlParts = parse_url($url);
            $url = $ngrokBase . ($urlParts['path'] ?? '') . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
        }

        $buttons = [];

        if ($status == 'created' || $status == 'new') {
            $buttons[] = ['text' => 'В роботу', 'callback_data' => 'task_progress_' . $taskId];
        } elseif ($status == 'in_progress') {
            $buttons[] = ['text' => 'Готово', 'callback_data' => 'task_complete_' . $taskId];
        }

        $keyboard = [];
        if (!empty($buttons)) {
            $keyboard[] = $buttons;
        }

        $keyboard[] = [['text' => 'Відкрити в системі', 'url' => $url]];

        return [
            'inline_keyboard' => $keyboard
        ];
    }

    /**
     * Сформувати inline-клавіатуру для замовлення
     *
     * @param int    $orderId ID замовлення
     * @param string $status  Поточний статус
     * @return array Inline keyboard markup
     */
    private static function getOrderKeyboard($orderId, $status)
    {
        $url = (defined('APP_URL') ? APP_URL : '') . '/orders/view.php?id=' . $orderId;
        $buttons = [];

        if ($status === 'new') {
            $buttons[] = [
                ['text' => '✅ Погодити', 'callback_data' => 'order_approve_' . $orderId],
                ['text' => '❌ Скасувати', 'callback_data' => 'order_cancel_' . $orderId]
            ];
        } elseif ($status === 'ready') {
            $buttons[] = [
                ['text' => '💰 Закрити замовлення', 'callback_data' => 'order_close_' . $orderId]
            ];
        }

        $buttons[] = [['text' => 'Відкрити в системі', 'url' => $url]];

        return [
            'inline_keyboard' => $buttons
        ];
    }

    /**
     * Оновити статус задачі через callback
     *
     * Перевіряє права (виконавець/створювач/директор).
     *
     * @param int        $taskId    ID задачі
     * @param string     $action    Дія (progress/complete)
     * @param array      $user      Користувач
     * @param int|string $chatId    ID чату
     * @param int|null   $messageId ID повідомлення
     * @return void
     */
    private static function updateTaskStatus($taskId, $action, $user, $chatId, $messageId = null)
    {
        $task = Task::getById($taskId);
        if (!$task) return;

        $isExecutor = in_array($user['id'], $task['executor_ids'] ?? []) || ($task['executor_id'] == $user['id']);
        $isCreator = ($task['creator_id'] == $user['id']);
        $isDirector = (($user['role'] ?? '') == 'director');

        if (!$isExecutor && !$isCreator && !$isDirector) return;

        $newStatus = '';
        switch ($action) {
            case 'progress':
                $newStatus = 'in_progress';
                break;
            case 'complete':
                $newStatus = 'completed';
                break;
            default:
                return;
        }

        if ($task['status'] === $newStatus) return;

        $_SESSION['user_id'] = $user['id'];
        self::$suppressNotificationsForChatId = $chatId;

        $result = Task::update($taskId, ['status' => $newStatus]);

        self::$suppressNotificationsForChatId = null;

        if ($result) {
            self::syncTaskNotification($taskId);

            if ($messageId) {
                $stmt = db()->prepare("SELECT COUNT(*) FROM telegram_notifications WHERE telegram_message_id = ? AND chat_id = ?");
                $stmt->execute([$messageId, $chatId]);
                $isSingleNotification = (int)$stmt->fetchColumn() > 0;

                if (!$isSingleNotification) {
                    self::handleMyTasksCommand($chatId, $user['telegram_username'], $messageId);
                }
            }
        }
    }

    /**
     * Оновити статус замовлення через callback
     *
     * @param int        $orderId       ID замовлення
     * @param string     $action        Дія (approve/progress/cancel/ready/close)
     * @param array      $user          Користувач
     * @param int|string $chatId        ID чату
     * @param int|null   $messageId     ID повідомлення
     * @param bool       $fromOrderList Чи викликано зі списку
     * @return void
     */
    private static function updateOrderStatus($orderId, $action, $user, $chatId, $messageId = null, $fromOrderList = false)
    {
        $order = \App\Models\Order::getById($orderId);
        if (!$order) return;

        if ($user['role'] !== 'director' && $user['role'] !== 'manager') {
            self::sendMessage($chatId, "⛔ У вас немає прав.");
            return;
        }

        $newStatus = '';
        switch ($action) {
            case 'approve':
                $newStatus = 'approved';
                break;
            case 'progress':
                $newStatus = 'in_progress';
                break;
            case 'cancel':
                $newStatus = 'cancelled';
                break;
            case 'ready':
                $newStatus = 'ready';
                break;
            case 'close':
                $newStatus = 'closed';
                break;
        }

        if ($newStatus && $newStatus !== $order['status']) {
            try {
                $orderService = new \App\Services\OrderService();

                $oldUserId = $_SESSION['user_id'] ?? null;
                $_SESSION['user_id'] = $user['id'];

                self::$suppressNotificationsForChatId = $chatId;
                $orderService->updateStatus($orderId, $newStatus);
                self::$suppressNotificationsForChatId = null;

                $_SESSION['user_id'] = $oldUserId;

                // If triggered from order list view, refresh the list
                if ($fromOrderList && $messageId) {
                    self::handleMyOrdersCommand($chatId, $user['telegram_username'] ?? '', $messageId);
                }

                // If order was moved to in_progress, offer to add a task
                if ($action === 'progress') {
                    $taskKeyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🛠 Додати задачу до #' . $order['order_number'], 'callback_data' => 'order_add_task_' . $order['id']]
                            ]
                        ]
                    ];
                    self::sendMessage($chatId, "✅ Замовлення <b>#{$order['order_number']}</b> взято в роботу.\nБажаєте додати задачі зараз?", $taskKeyboard);
                }
            } catch (Exception $e) {
                self::sendMessage($chatId, "❌ Помилка: " . $e->getMessage());
            }
        }
    }
}
