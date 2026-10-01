<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Task;
use App\Reports;
use Exception;

/**
 * Telegram Bot Commands — menu, /start, /my_tasks, /profit, etc.
 *
 * @property string $apiUrl
 * @property string $suppressNotificationsForChatId
 */
trait TelegramCommands
{
    private static function sendMainMenu($chatId, $message = "Головне меню")
    {
        // Determine user role for role-dependent menu
        $stmt = db()->prepare("SELECT role FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $role = $stmt->fetchColumn() ?: '';
        $isManager = in_array($role, ['director', 'manager']);

        $rows = [
            [['text' => \App\BotConstants::BTN_MY_TASKS], ['text' => \App\BotConstants::BTN_MY_EARNINGS]],
            [['text' => \App\BotConstants::BTN_MY_PAYOUTS]]
        ];

        if ($isManager) {
            // Manager/Director-only buttons at the top
            array_unshift($rows, [['text' => \App\BotConstants::BTN_NEW_ORDER], ['text' => \App\BotConstants::BTN_NEW_TASK]]);
            $row = [['text' => \App\BotConstants::BTN_ACTIVE_ORDERS]];
            if ($role === 'director') {
                $row[] = ['text' => \App\BotConstants::BTN_FINANCE];
            }
            $rows[] = $row;
            
            if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1') {
                $rows[] = [['text' => \App\BotConstants::BTN_CLOSE_DAY]];
            }
        }

        $keyboard = [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'persistent' => true
        ];
        self::sendMessage($chatId, $message, $keyboard);
    }

    private static function handleStartCommand($chatId, $telegramUsername, $payload = null)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_username = ? AND is_active = 1");
        $stmt->execute([$telegramUsername]);
        $user = $stmt->fetch();

        if ($user) {
            // Update binding if needed
            if ($user['telegram_chat_id'] != $chatId) {
                $stmt = db()->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
                $stmt->execute([$chatId, $user['id']]);
                self::sendMessage($chatId, "✅ Ваш Telegram акаунт успішно прив'язано до системи DetalStudio!");
            }

            // Handle Payload (Deep Linking)
            if ($payload) {
                if (strpos($payload, 'task_') === 0) {
                    $taskId = (int)str_replace('task_', '', $payload);
                    $task = Task::getById($taskId);

                    if ($task) {
                        $isExecutor = in_array($user['id'], $task['executor_ids'] ?? []) || ($task['executor_id'] == $user['id']);
                        $canView = ($user['role'] == 'director' || $user['role'] == 'manager' || $isExecutor || $task['creator_id'] == $user['id']);

                        if ($canView) {
                            $message = "📂 <b>ПЕРЕГЛЯД ЗАДАЧІ</b>\n\n";
                            $message .= "📝 <b>Назва:</b> " . htmlspecialchars($task['title']) . "\n";
                            $message .= "👤 <b>Виконавці:</b> " . htmlspecialchars($task['executor_names'] ?? ($task['executor_name'] ?? 'Не призначено')) . "\n";
                            $message .= "🔄 <b>Статус:</b> " . $task['status'] . "\n";

                            if ($task['deadline']) {
                                $message .= "⏰ <b>Дедлайн:</b> " . format_date($task['deadline']) . "\n";
                            }

                            if ($task['description']) {
                                $message .= "📝 <b>Опис:</b> " . htmlspecialchars(substr($task['description'], 0, 500)) . "\n";
                            }

                            $message .= "\n#задача #" . $task['status'];

                            $keyboard = self::getTaskKeyboard($task['id'], $task['status']);

                            self::sendMessage($chatId, $message, $keyboard, [
                                'entity_type' => 'task',
                                'entity_id' => $task['id'],
                                'user_id' => $user['id']
                            ]);
                            return;
                        } else {
                            self::sendMessage($chatId, "⛔️ У вас немає прав на перегляд цієї задачі.");
                            return;
                        }
                    } else {
                        self::sendMessage($chatId, "❌ Задачу не знайдено.");
                    }
                }
            } else {
                self::sendMainMenu($chatId, "👋 Вітаємо в DetalStudio Bot!\n\nВикористовуйте меню для навігації.");
            }
        } else {
            self::sendMessage($chatId, "❌ Користувача з таким Telegram ніком не знайдено в системі.\n\nЗверніться до адміністратора для налаштування профілю.");
        }
    }

    private static function handleNewOrderCommand($chatId, $username, $triggerMessageId = null)
    {
        $stmt = db()->prepare("SELECT id, role FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if ($user && ($user['role'] === 'director' || $user['role'] === 'manager')) {
            try {
                \App\OrderWizard::start($chatId, $user['id'], $triggerMessageId);
            } catch (Exception $e) {
                error_log("OrderWizard start error for chat $chatId: " . $e->getMessage());
                self::sendMessage($chatId, "❌ Помилка запуску. Спробуйте ще раз або зверніться до адміністратора.");
            }
        } else {
            self::sendMessage($chatId, "⛔ У вас немає прав для створення замовлень.");
        }
    }

    /**
     * Handle /help command
     */
    private static function handleHelpCommand($chatId, $username)
    {
        $stmt = db()->prepare("SELECT role FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $role = $stmt->fetchColumn() ?: '';

        $message = "📖 <b>Довідка DetalStudio Bot</b>\n\n";
        $message .= "<b>Доступні команди:</b>\n";
        $message .= "/start — Прив'язка акаунту / Головне меню\n";
        $message .= "/my_tasks — Мої активні задачі\n";
        $message .= "/my_schedule — Розклад на 48 годин\n";
        $message .= "/cancel — Скасувати поточну дію\n";
        $message .= "/help — Ця довідка\n";

        if (in_array($role, ['director', 'manager'])) {
            $message .= "\n<b>Команди менеджера:</b>\n";
            $message .= "/new_order — Створити замовлення\n";
            $message .= "/profit — Фінансовий звіт\n";
            $message .= "/close_day — Закрити день (виплати)\n";
        }

        $message .= "\n<b>Меню:</b>\n";
        $message .= "Використовуйте кнопки меню внизу екрану для швидкого доступу до функцій.\n";
        $message .= "\n<b>Коментарі:</b>\n";
        $message .= "Відповідайте (reply) на повідомлення про задачу, щоб додати коментар або фото.\n";

        self::sendMessage($chatId, $message);
    }

    /**
     * Setup Bot Menu Commands
     */
    public static function setupBotCommands()
    {
        if (empty(self::getToken())) return false;

        $commands = [
            ['command' => 'start', 'description' => 'Головне меню / Прив\'язка'],
            ['command' => 'my_tasks', 'description' => 'Мої задачі'],
            ['command' => 'help', 'description' => 'Довідка']
        ];

        $url = self::$apiUrl . self::getToken() . '/setMyCommands';
        return self::makeRequest($url, ['commands' => json_encode($commands)]);
    }

    private static function handleMyTasksCommand($chatId, $username, $messageId = null, $page = 1)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user) {
            self::sendMessage($chatId, "❌ Ваш акаунт не прив'язано до системи. Натисніть /start для інструкцій.");
            return;
        }

        $tasks = Task::getList([
            'executor_id' => $user['id'],
            'status' => ['new', 'created', 'accepted', 'in_progress']
        ]);

        if (empty($tasks)) {
            $msg = "✅ У вас немає активних задач. Гарного відпочинку!";
            if ($messageId) {
                self::editMessage($chatId, $messageId, $msg, null);
            } else {
                self::sendMessage($chatId, $msg);
            }
            return;
        }

        $count = count($tasks);
        $perPage = 5;
        $totalPages = ceil($count / $perPage);
        $page = max(1, min($page, (int)$totalPages));
        
        $offset = ($page - 1) * $perPage;
        $paginatedTasks = array_slice($tasks, $offset, $perPage);

        $message = "<b>📋 Ваші активні задачі ($count)</b> - Сторінка $page/$totalPages:\n\n";

        $statusIcons = [
            'created' => '🆕',
            'new' => '🆕',
            'in_progress' => '⚙️'
        ];

        $keyboard = [];
        $now = new \DateTime();

        foreach ($paginatedTasks as $task) {
            $icon = $statusIcons[$task['status']] ?? '🔹';

            $deadlineText = "";
            if ($task['deadline']) {
                $deadline = new \DateTime($task['deadline']);
                $isOverdue = $deadline < $now;
                $deadlineText = " " . ($isOverdue ? "⏰❗️ " : "⏰ ") . date('d.m H:i', strtotime($task['deadline']));
            }

            $carInfo = "";
            if (!empty($task['car_make'])) {
                $make = $task['car_make'];
                $model = $task['car_model'] ?? "";
                if (strtolower($model) === 'unknown') $model = "";

                $carInfo = "🚘 " . trim($make . " " . $model);
                if (!empty($task['car_plate'])) $carInfo .= " (" . $task['car_plate'] . ")";
                $carInfo .= "\n";
            }

            $message .= "{$icon} <b>{$task['title']}</b> (#{$task['id']}){$deadlineText}\n";
            $message .= $carInfo;
            $message .= "🔗 <a href=\"" . (defined('APP_URL') ? APP_URL : '') . "/tasks/view.php?id=" . $task['id'] . "\">Відкрити в системі</a>\n";
            $message .= "-------------------\n";

            $row = [];
            if ($task['status'] === 'created' || $task['status'] === 'new') {
                $row[] = ['text' => '⚙️ В роботу #' . $task['id'], 'callback_data' => 'task_progress_' . $task['id']];
            } elseif ($task['status'] === 'in_progress') {
                $row[] = ['text' => '☑️ Готово #' . $task['id'], 'callback_data' => 'task_complete_' . $task['id']];
            }
            if (!empty($row)) {
                $keyboard[] = $row;
            }
        }

        // Pagination controls
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 1) {
                $navRow[] = ['text' => '⬅️ Назад', 'callback_data' => 'page_tasks_' . ($page - 1)];
            }
            if ($page < $totalPages) {
                $navRow[] = ['text' => 'Вперед ➡️', 'callback_data' => 'page_tasks_' . ($page + 1)];
            }
            $keyboard[] = $navRow;
        }

        $markup = !empty($keyboard) ? ['inline_keyboard' => $keyboard] : null;

        if ($messageId) {
            self::editMessage($chatId, $messageId, $message, $markup);
        } else {
            self::sendMessage($chatId, $message, $markup);
        }
    }

    /**
     * Handle '📋 Активні замовлення' command (For directors/managers)
     * Lists all active orders with inline status-change buttons.
     */
    private static function handleMyOrdersCommand($chatId, $username, $messageId = null, $page = 1)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user || !in_array($user['role'], ['director', 'manager'])) {
            self::sendMessage($chatId, "🚫 У вас немає прав для перегляду замовлень.");
            return;
        }

        $orders = \App\Models\Order::getList([
            'status' => ['new', 'approved', 'in_progress', 'waiting_parts', 'waiting_client', 'ready']
        ]);

        if (empty($orders)) {
            $msg = "✅ Немає активних замовлень.";
            if ($messageId) {
                self::editMessage($chatId, $messageId, $msg, null);
            } else {
                self::sendMessage($chatId, $msg);
            }
            return;
        }

        $now = new \DateTime();
        $keyboard = [];

        // Group orders by status sections
        $newOrders = [];
        $readyOrders = [];
        $workOrders = []; // in_progress, approved, waiting_*

        foreach ($orders as $order) {
            if ($order['status'] === 'new') {
                $newOrders[] = $order;
            } elseif ($order['status'] === 'ready') {
                $readyOrders[] = $order;
            } else {
                $workOrders[] = $order;
            }
        }

        $count = count($orders);
        $message = "<b>📦 Активні замовлення ($count):</b>\n";

        // Helper: format single order line
        $formatOrder = function ($order, $compact = false) use ($now) {
            $deadlineText = "";
            if (!empty($order['deadline'])) {
                $deadline = new \DateTime($order['deadline']);
                $isOverdue = $deadline < $now;
                $deadlineText = " " . ($isOverdue ? "⏰❗️ " : "⏰ ") . date('d.m H:i', strtotime($order['deadline']));
            }

            $carInfo = "";
            if (!empty($order['make'])) {
                $carInfo = trim(($order['make'] ?? '') . ' ' . ($order['model'] ?? ''));
                if (!empty($order['license_plate'])) {
                    $carInfo .= ' (' . $order['license_plate'] . ')';
                }
            }

            $line = "<b>#{$order['order_number']}</b>{$deadlineText}\n";
            if (!empty($order['client_name'])) {
                $line .= "👤 " . htmlspecialchars($order['client_name']) . "\n";
            }
            if ($carInfo) {
                $line .= "🚘 " . htmlspecialchars($carInfo) . "\n";
            }
            if (!$compact) {
                $line .= "🔗 <a href=\"" . (defined('APP_URL') ? APP_URL : '') . "/orders/view.php?id=" . $order['id'] . "\">Відкрити в системі</a>\n";
            }
            return $line;
        };

        // --- Section 1: In progress (compact, no buttons) ---
        if (!empty($workOrders)) {
            $statusNames = [
                'approved' => 'Погоджено',
                'in_progress' => 'В роботі',
                'waiting_parts' => 'Очікує запчастин',
                'waiting_client' => 'Очікує клієнта'
            ];
            $statusIcons = [
                'approved' => '🤝',
                'in_progress' => '⚙️',
                'waiting_parts' => '⏳',
                'waiting_client' => '⏳'
            ];
            $message .= "\n⚙️ <b>В роботі (" . count($workOrders) . "):</b>\n";
            $limit = 10;
            $displayWorkOrders = array_slice($workOrders, 0, $limit);
            foreach ($displayWorkOrders as $order) {
                $icon = $statusIcons[$order['status']] ?? '🔹';
                $statusText = $statusNames[$order['status']] ?? $order['status'];
                $deadlineText = "";
                if (!empty($order['deadline'])) {
                    $deadline = new \DateTime($order['deadline']);
                    $isOverdue = $deadline < $now;
                    $deadlineText = ($isOverdue ? " ⏰❗️" : " ⏰") . date('d.m', strtotime($order['deadline']));
                }
                $carInfo = "";
                if (!empty($order['make'])) {
                    $carInfo = " | " . trim(($order['make'] ?? '') . ' ' . ($order['model'] ?? ''));
                }
                $clientInfo = !empty($order['client_name']) ? " | " . htmlspecialchars($order['client_name']) : "";
                $message .= "{$icon} <b>#{$order['order_number']}</b>{$deadlineText}{$clientInfo}{$carInfo}\n";
            }
            if (count($workOrders) > $limit) {
                $message .= "<i>... та ще " . (count($workOrders) - $limit) . " замовлень. <a href=\"" . (defined('APP_URL') ? APP_URL : '') . "/orders/index.php\">Відкрити CRM</a></i>\n";
            }
        }

        // --- Section 2: New orders (with Approve buttons) ---
        if (!empty($newOrders)) {
            $message .= "\n🆕 <b>Нові (" . count($newOrders) . "):</b>\n";
            $limit = 5;
            $displayNewOrders = array_slice($newOrders, 0, $limit);
            foreach ($displayNewOrders as $order) {
                $message .= "\n" . $formatOrder($order);
                $message .= "-------------------\n";
                $keyboard[] = [
                    ['text' => '⚙️ В роботу #' . $order['order_number'], 'callback_data' => 'olist_progress_' . $order['id']],
                    ['text' => '✖️ Відхилити', 'callback_data' => 'olist_cancel_' . $order['id']]
                ];
            }
            if (count($newOrders) > $limit) {
                $message .= "<i>... та ще " . (count($newOrders) - $limit) . " нових. <a href=\"" . (defined('APP_URL') ? APP_URL : '') . "/orders/index.php\">Відкрити CRM</a></i>\n";
            }
        }

        // --- Section 3: Ready orders (with Close buttons) ---
        if (!empty($readyOrders)) {
            $message .= "\n🏁 <b>Готові (" . count($readyOrders) . "):</b>\n";
            $limit = 5;
            $displayReadyOrders = array_slice($readyOrders, 0, $limit);
            foreach ($displayReadyOrders as $order) {
                $message .= "\n" . $formatOrder($order);
                $message .= "-------------------\n";
                $keyboard[] = [
                    ['text' => '💰 Закрити #' . $order['order_number'], 'callback_data' => 'olist_close_' . $order['id']]
                ];
            }
            if (count($readyOrders) > $limit) {
                $message .= "<i>... та ще " . (count($readyOrders) - $limit) . " готових. <a href=\"" . (defined('APP_URL') ? APP_URL : '') . "/orders/index.php\">Відкрити CRM</a></i>\n";
            }
        }

        $markup = !empty($keyboard) ? ['inline_keyboard' => $keyboard] : null;

        if ($messageId) {
            self::editMessage($chatId, $messageId, $message, $markup);
        } else {
            self::sendMessage($chatId, $message, $markup);
        }
    }

    /**
     * Handle /my_schedule command
     */
    private static function handleMyScheduleCommand($chatId, $username)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user) {
            self::sendMessage($chatId, "❌ Ваш акаунт не знайдено або не активовано.");
            return;
        }

        $stmt = db()->prepare("
            SELECT t.*, o.order_number 
            FROM tasks t 
            LEFT JOIN orders o ON t.order_id = o.id
            WHERE EXISTS (SELECT 1 FROM task_executors te WHERE te.task_id = t.id AND te.user_id = ?)
            AND t.status IN ('created', 'in_progress')
            AND t.is_deleted = 0
            AND (t.deadline >= NOW() AND t.deadline <= DATE_ADD(NOW(), INTERVAL 48 HOUR))
            ORDER BY t.deadline ASC
        ");
        $stmt->execute([$user['id']]);
        $tasks = $stmt->fetchAll();

        if (empty($tasks)) {
            self::sendMessage($chatId, "📅 На найближчі 48 годин у вас немає запланованих задач.");
            return;
        }

        $message = "📅 <b>Ваш розклад на найближчі 48 годин:</b>\n\n";
        foreach ($tasks as $task) {
            $deadline = date('d.m H:i', strtotime($task['deadline']));
            $priority = ($task['priority'] === 'urgent') ? "🔴" : (($task['priority'] === 'high') ? "🟠" : (($task['priority'] === 'medium') ? "🟡" : "🟢"));

            $message .= "{$priority} #{$task['id']} <b>{$task['title']}</b>\n";
            $message .= "⏰ Дедлайн: {$deadline}\n";
            if ($task['order_number']) {
                $message .= "📦 Замовлення: {$task['order_number']}\n";
            }
            $message .= "-------------------\n";
        }

        self::sendMessage($chatId, $message);
    }

    /**
     * Handle '📊 Мій заробіток' command (For executors)
     */
    private static function handleMyEarningsCommand($chatId, $username)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user) {
            self::sendMessage($chatId, "❌ Ваш акаунт не знайдено.");
            return;
        }

        // Today's earnings (completed, closed) — split equally among executors
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $stmtToday = db()->prepare("
            SELECT COALESCE(SUM(
                t.commission_amount / (SELECT COUNT(*) FROM task_executors te2 WHERE te2.task_id = t.id)
            ), 0)
            FROM tasks t
            JOIN task_executors te ON t.id = te.task_id AND te.user_id = ?
            WHERE t.status IN ('completed', 'closed')
              AND t.is_deleted = 0
              AND t.updated_at BETWEEN ? AND ?
        ");
        $stmtToday->execute([$user['id'], $todayStart, $todayEnd]);
        $earnedToday = (float)$stmtToday->fetchColumn();

        // Total unpaid debt (Unified Ledger) — split equally among executors
        $stmtDebt = db()->prepare("
            SELECT 
                COALESCE(SUM(
                    t.commission_amount / (SELECT COUNT(*) FROM task_executors te2 WHERE te2.task_id = t.id)
                ), 0) - COALESCE(
                    (SELECT SUM(amount) FROM employee_payments WHERE user_id = ? AND payment_type = 'salary')
                , 0) as total_unpaid
            FROM tasks t
            JOIN task_executors te ON t.id = te.task_id AND te.user_id = ?
            WHERE t.status IN ('completed', 'closed') AND t.is_deleted = 0
        ");
        $stmtDebt->execute([$user['id'], $user['id']]);
        $totalUnpaid = (float)$stmtDebt->fetchColumn();

        $message = "📊 <b>Ваш заробіток:</b>\n\n";
        $message .= "💰 <b>Зароблено за сьогодні:</b> " . format_currency($earnedToday) . "\n";
        $message .= "💳 <b>Всього до виплати (борг):</b> " . format_currency($totalUnpaid) . "\n\n";
        $message .= "<i>Статистика враховує лише задачі у статусі 'Готово' або 'Закрито'.</i>";

        self::sendMessage($chatId, $message);
    }

    /**
     * Handle '💳 Історія виплат' command (For executors)
     */
    private static function handleMyPayoutsCommand($chatId, $username)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user) {
            self::sendMessage($chatId, "❌ Ваш акаунт не знайдено.");
            return;
        }

        $stmtPayments = db()->prepare("
            SELECT amount, payment_date, notes
            FROM employee_payments
            WHERE user_id = ?
            ORDER BY payment_date DESC, created_at DESC
            LIMIT 10
        ");
        $stmtPayments->execute([$user['id']]);
        $payments = $stmtPayments->fetchAll();

        if (empty($payments)) {
            self::sendMessage($chatId, "📁 У вас ще немає історії виплат.");
            return;
        }

        $message = "💳 <b>Ваші останні виплати:</b>\n\n";
        foreach ($payments as $p) {
            $date = date('d.m.Y', strtotime($p['payment_date']));
            $amount = format_currency($p['amount']);
            $notes = $p['notes'] ? " ({$p['notes']})" : "";
            $message .= "🗓 {$date} — <b>{$amount}</b>{$notes}\n";
        }

        self::sendMessage($chatId, $message);
    }

    /**
     * Handle /profit command (For directors/managers)
     */
    private static function handleProfitCommand($chatId, $username)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user || ($user['role'] !== 'director' && $user['role'] !== 'manager')) {
            self::sendMessage($chatId, "🚫 У вас немає прав для перегляду фінансової звітності.");
            return;
        }

        require_once __DIR__ . '/../Reports.php';

        $today = date('Y-m-d');
        $summaryToday = Reports::getFinancialSummary($today, $today);

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $summaryMonth = Reports::getFinancialSummary($monthStart, $monthEnd);

        $message = "📊 <b>Фінансовий звіт:</b>\n\n";
        $message .= "📅 <b>Сьогодні:</b>\n";
        $message .= "💵 Дохід: " . format_currency($summaryToday['revenue']) . "\n";
        $message .= "📉 Витрати: " . format_currency($summaryToday['total_expenses']) . "\n";
        $message .= "📈 Прибуток: <b>" . format_currency($summaryToday['profit']) . "</b>\n\n";

        $message .= "🗓 <b>Цей місяць:</b>\n";
        $message .= "💵 Дохід: " . format_currency($summaryMonth['revenue']) . "\n";
        $message .= "📉 Витрати: " . format_currency($summaryMonth['total_expenses']) . "\n";
        $message .= "📈 Прибуток: <b>" . format_currency($summaryMonth['profit']) . "</b>\n";

        self::sendMessage($chatId, $message);
    }

    /**
     * Handle '💸 Фінанси' command (For directors only)
     */
    private static function handleFinanceCommand($chatId, $username)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] !== 'director') {
            self::sendMessage($chatId, "🚫 У вас немає прав для внесення фінансових операцій.");
            return;
        }

        \App\FinanceWizard::start($chatId, $user['id']);
    }

    /**
     * Handle '💸 Закрити день' command
     */
    private static function handleCloseDayCommand($chatId, $username)
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND is_active = 1");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if (!$user || !in_array($user['role'], ['director', 'manager'])) {
            self::sendMessage($chatId, "🚫 У вас немає прав для закриття дня.");
            return;
        }

        self::renderCloseDayPayouts($chatId);
    }

    /**
     * Renders or updates the close day message
     */
    private static function renderCloseDayPayouts($chatId, $messageId = null)
    {
        // Get all completed/closed tasks with unpaid commissions — split equally among executors
        $stmt = db()->prepare("
            SELECT 
                u.id as executor_id, 
                u.full_name as executor_name, 
                COALESCE(SUM(
                    t.commission_amount / (SELECT COUNT(*) FROM task_executors te2 WHERE te2.task_id = t.id)
                ), 0) - COALESCE(
                    (SELECT SUM(amount) FROM employee_payments WHERE user_id = u.id AND payment_type = 'salary')
                , 0) as total_payout
            FROM users u
            JOIN task_executors te ON u.id = te.user_id
            JOIN tasks t ON te.task_id = t.id AND t.status IN ('completed', 'closed') AND t.is_deleted = 0 AND t.commission_amount > 0
            WHERE u.is_active = 1
            GROUP BY u.id
            HAVING total_payout > 0
        ");
        $stmt->execute();
        $payouts = $stmt->fetchAll();

        if (empty($payouts)) {
            $msg = "✅ Всі виплати виконавцям закрито. Боргів немає.";
            if ($messageId) {
                self::editMessage($chatId, $messageId, $msg, null);
            } else {
                self::sendMessage($chatId, $msg);
            }
            return;
        }

        $message = "💸 <b>Виплати на кінець дня:</b>\n\n";
        $keyboard = [];

        foreach ($payouts as $p) {
            $message .= "👤 " . htmlspecialchars($p['executor_name']) . ": <b>" . format_currency($p['total_payout']) . "</b>\n";
            $keyboard[] = [
                ['text' => '💰 Виплатити ' . mb_substr($p['executor_name'], 0, 15) . ': ' . strip_tags(format_currency($p['total_payout'])), 'callback_data' => 'pay_' . $p['executor_id']]
            ];
        }

        $markup = !empty($keyboard) ? ['inline_keyboard' => $keyboard] : null;

        if ($messageId) {
            self::editMessage($chatId, $messageId, $message, $markup);
        } else {
            self::sendMessage($chatId, $message, $markup);
        }
    }

    /**
     * Handle pay executor callback
     */
    private static function handlePayExecutorCallback($chatId, $user, $executorId, $messageId)
    {
        if (!in_array($user['role'], ['director', 'manager'])) {
            self::sendMessage($chatId, "⛔ У вас немає прав.");
            return;
        }

        // Calculate total unpaid debt (Unified Ledger) — split equally among executors
        $stmt = db()->prepare("
            SELECT 
                COALESCE(SUM(
                    t.commission_amount / (SELECT COUNT(*) FROM task_executors te2 WHERE te2.task_id = t.id)
                ), 0) - COALESCE(
                    (SELECT SUM(amount) FROM employee_payments WHERE user_id = ? AND payment_type = 'salary')
                , 0) as total
            FROM tasks t
            JOIN task_executors te ON t.id = te.task_id AND te.user_id = ?
            WHERE t.status IN ('completed', 'closed') AND t.is_deleted = 0
        ");
        $stmt->execute([$executorId, $executorId]);
        $totalAmount = (float)$stmt->fetchColumn();

        if ($totalAmount > 0) {
            // Log payment into employee_payments table
            require_once __DIR__ . '/../Payments.php';
            \App\Payments::create([
                'user_id' => $executorId,
                'amount' => $totalAmount,
                'payment_type' => 'salary',
                'payment_method' => 'cash',
                'payment_date' => date('Y-m-d'),
                'notes' => 'Виплата за задачі (Закриття дня)'
            ]);

            // Push notification
            $stmtUser = db()->prepare("SELECT telegram_chat_id FROM users WHERE id = ?");
            $stmtUser->execute([$executorId]);
            $executorChatId = $stmtUser->fetchColumn();

            if ($executorChatId) {
                $msg = "✅ <b>Вам нараховано виплату!</b>\n";
                $msg .= "💰 Сума: <b>" . format_currency($totalAmount) . "</b>\n";
                $msg .= "📉 Залишок боргу: <b>0.00 ₴</b>\n\n";
                $msg .= "<i>Деталі можна переглянути через «" . \App\BotConstants::BTN_MY_PAYOUTS . "».</i>";

                self::sendMessage($executorChatId, $msg);
            }
        }

        // Get executor name for success toast
        $stmt = db()->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$executorId]);
        $executorName = $stmt->fetchColumn();

        self::sendMessage($chatId, "✅ Виплату для {$executorName} проведено.");

        // Re-render
        self::renderCloseDayPayouts($chatId, $messageId);
    }
}
