<?php

namespace App;

use App\Models\Task;
use App\Models\Order;
use App\Models\Service;
use Exception;

/**
 * Майстер створення задач через Telegram
 *
 * Кроки: категорія → послуга → ціна → [виплата] → виконавець → дата.
 * Підтримує прив’язку до замовлення, цикл додавання, concurrency guard.
 *
 * @package App
 */
class TaskWizard
{
    /**
     * Запустити майстер створення задачі
     *
     * @param int|string $chatId           ID чату
     * @param int        $userId           ID користувача
     * @param int|null   $orderId          ID замовлення (опціонально)
     * @param int|null   $triggerMessageId ID повідомлення-тригера
     * @return void
     */
    public static function start($chatId, $userId, $orderId = null, $triggerMessageId = null)
    {
        // Check if order exists IF provided
        if ($orderId) {
            $order = Order::getById($orderId);
            if (!$order) {
                Telegram::sendMessage($chatId, "❌ Замовлення не знайдено.");
                return;
            }
        }

        TelegramConversation::set($chatId, 'task_category', ['order_id' => $orderId]);

        // Clean up triggering message if provided
        if ($triggerMessageId) {
            TelegramConversation::addMessageId($chatId, $triggerMessageId);
        }

        // Show Categories
        $categories = \App\Models\ServiceCategory::getAll();
        $keyboard = self::buildButtons($categories, 'wizard_task_cat_', 'name');

        // Add "Skip/Custom" button if they want to type manually immediately?
        // Or just let them type.

        $res = Telegram::sendMessage($chatId, "📂 Оберіть категорію послуг або напишіть назву задачі вручну:", $keyboard); // Changed message and keyboard
        if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
    }

    // Removed getCommonServicesKeyboard method

    /**
     * Обробити текстовий ввід користувача
     *
     * @param int|string $chatId  ID чату
     * @param int        $userId  ID користувача
     * @param string     $text    Текст повідомлення
     * @param array      $message Telegram message object
     * @return void
     */
    public static function handle($chatId, $userId, $text, $message)
    {
        $conversation = TelegramConversation::get($chatId);
        if (!$conversation) return;

        $step = $conversation['step'];
        $data = $conversation['data'];

        switch ($step) {
            case 'task_category': // Added
            case 'task_service': // Added
                $data['description'] = trim($text);
                TelegramConversation::update($chatId, 'task_price', $data);

                // Ask for Price
                // Providing buttons for quick selection could be nice, but simple text is fine.
                $res = Telegram::sendMessage($chatId, "💵 Вкажіть вартість роботи (цифрою) або '0':"); // Changed message
                if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'task_price':
                $price = preg_replace('/[^0-9.]/', '', $text); // Allow decimals
                if ($price === '') {
                    // Check if we have a default price from service
                    if (empty($data['price'])) {
                        $res = Telegram::sendMessage($chatId, "⚠️ Введіть коректну суму.");
                        if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                        return;
                    }
                } else {
                    $data['price'] = (float)$price;
                }

                if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1') {
                    TelegramConversation::update($chatId, 'task_commission', $data);
                    $res = Telegram::sendMessage($chatId, "💰 Вкажіть розмір виплати виконавцю (або '0'):", ['inline_keyboard' => [[['text' => '⬅️ Назад', 'callback_data' => 'wizard_back']]]]);
                } else {
                    $data['commission'] = 0.00;
                    TelegramConversation::update($chatId, 'task_executor', $data);
                    $keyboard = self::getExecutorKeyboard();
                    $res = Telegram::sendMessage($chatId, "👤 Оберіть виконавця:", $keyboard);
                }
                
                if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'task_commission':
                $commission = preg_replace('/[^0-9.]/', '', $text); // Allow decimals
                if ($commission === '') {
                    $res = Telegram::sendMessage($chatId, "⚠️ Введіть коректну суму або 0.");
                    if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    return;
                }
                $data['commission'] = (float)$commission;
                TelegramConversation::update($chatId, 'task_executor', $data);
                
                $keyboard = self::getExecutorKeyboard();
                $res = Telegram::sendMessage($chatId, "👤 Оберіть виконавця:", $keyboard);
                if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'task_executor':
                // Note: user might type instead of clicking button. 
                // If they type, it's ambiguous. Better to enforce button or generic 'unknown'.
                // If they typed name, we can't easily map.
                // Let's assume they MUST pick or we treat text as error.
                // But handleCallback is where button clicks go. 
                // If we are here, they typed something.
                $res = Telegram::sendMessage($chatId, "⚠️ Будь ласка, натисніть кнопку для вибору виконавця:", self::getExecutorKeyboard());
                if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'task_execution_date':
                $date = null;
                $input = strtolower(trim($text));
                if ($input === '/skip') {
                    $date = null;
                } else {
                    $ts = strtotime($text);
                    if ($ts) {
                        $date = date('Y-m-d H:i:s', $ts); // Store as datetime but UI will show Date only
                        // User requested "Only date without time". 
                        // If user enters "2023-10-10", strtotime gives 00:00:00.
                    } else {
                        $res = Telegram::sendMessage($chatId, "⚠️ Невірний формат дати. Спробуйте ще раз (РРРР-ММ-ДД) або /skip:");
                        if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                        return;
                    }
                }
                $data['deadline'] = $date; // Store in deadline column

                self::finish($chatId, $userId, $data['order_id'], $data['description'], $data['price'], $data['commission'] ?? 0.00, $data['executor_id'], $data['service_id'] ?? null, $data['deadline']);
                break;
        }
    }

    /**
     * Обробити inline-callback (вибір категорії/послуги/виконавця, календар, Back)
     *
     * @param int|string $chatId       ID чату
     * @param int        $userId       ID користувача
     * @param string     $callbackData Callback data рядок
     * @param int|null   $messageId    ID повідомлення для редагування
     * @return void
     */
    public static function handleCallback($chatId, $userId, $callbackData, $messageId = null)
    {
        $conversation = TelegramConversation::get($chatId);
        if (!$conversation) return;

        $data = $conversation['data'] ?? [];
        $step = $conversation['step']; // Added

        // Back Navigation
        if ($callbackData === 'wizard_back') {
            switch ($step) {
                case 'task_service': // Back to Category
                    self::start($chatId, $userId, $data['order_id']); // Restart checks categories
                    return;
                case 'task_price': // Back to Service List
                    if (!empty($data['category_id'])) {
                        $catId = $data['category_id'];
                        $services = Service::getAll(true, $catId);
                        TelegramConversation::update($chatId, 'task_service', $data);
                        $keyboard = self::buildButtons($services, 'wizard_task_srv_', 'name', true, true);
                        $res = Telegram::sendMessage($chatId, "⚙️ Оберіть послугу:", $keyboard);
                        if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    } else {
                        // If no category (manual entry?), go to start
                        self::start($chatId, $userId, $data['order_id']);
                    }
                    return;
                case 'task_executor': // Back to Price OR Commission
                    if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1') {
                        TelegramConversation::update($chatId, 'task_commission', $data);
                        $res = Telegram::sendMessage($chatId, "💰 Вкажіть розмір виплати виконавцю (або '0'):", ['inline_keyboard' => [[['text' => '⬅️ Назад', 'callback_data' => 'wizard_back']]]]);
                    } else {
                        TelegramConversation::update($chatId, 'task_price', $data);
                        $keyboard = [
                            'inline_keyboard' => [[
                                ['text' => '✅ Залишити ' . strip_tags(format_currency($data['price'])), 'callback_data' => 'wizard_task_price_confirm'],
                                ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back']
                            ]]
                        ];
                        $res = Telegram::sendMessage($chatId, "💵 Вкажіть вартість роботи (цифрою) або підтвердіть:", $keyboard);
                    }
                    if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    return;
                case 'task_commission': // Back to Price
                    TelegramConversation::update($chatId, 'task_price', $data);
                    $keyboard = [
                        'inline_keyboard' => [[
                            ['text' => '✅ Залишити ' . strip_tags(format_currency($data['price'])), 'callback_data' => 'wizard_task_price_confirm'],
                            ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back']
                        ]]
                    ];
                    $res = Telegram::sendMessage($chatId, "💵 Вкажіть вартість роботи (цифрою) або підтвердіть:", $keyboard);
                    if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    return;
                case 'task_execution_date': // Back to Executor
                    TelegramConversation::update($chatId, 'task_executor', $data);
                    $keyboard = self::getExecutorKeyboard();
                    // Add back button manually to executor keyboard if not in helper? 
                    // helper `getExecutorKeyboard` calls `buildButtons`. We need to update that too.
                    $res = Telegram::sendMessage($chatId, "👤 Оберіть виконавця:", $keyboard);
                    if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    return;
            }
            return;
        }

        // Category Selection
        if (strpos($callbackData, 'wizard_task_cat_') === 0) {
            $catId = str_replace('wizard_task_cat_', '', $callbackData);
            $data['category_id'] = $catId; // Store for Back nav

            // Fetch services in category
            $services = Service::getAll(true, $catId);

            if (empty($services)) {
                Telegram::sendMessage($chatId, "⚠️ В цій категорії немає послуг. Напишіть назву вручну:", ['inline_keyboard' => [[['text' => '⬅️ Назад', 'callback_data' => 'wizard_back']]]]);
                return;
            }

            TelegramConversation::update($chatId, 'task_service', $data);

            $keyboard = self::buildButtons($services, 'wizard_task_srv_', 'name', true, true); // +Back
            $res = Telegram::sendMessage($chatId, "⚙️ Оберіть послугу:", $keyboard);
            if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
        }
        // Service Selection
        elseif (strpos($callbackData, 'wizard_task_srv_') === 0) {
            $serviceId = str_replace('wizard_task_srv_', '', $callbackData);

            // Fetch service details
            $service = Service::getById($serviceId);
            if ($service) {
                $data['description'] = $service['name'];
                $data['price'] = (float)$service['base_price'];
                $data['service_id'] = $serviceId;

                TelegramConversation::update($chatId, 'task_price', $data);

                $keyboard = [
                    'inline_keyboard' => [[
                        ['text' => '✅ Залишити ' . strip_tags(format_currency($data['price'])), 'callback_data' => 'wizard_task_price_confirm'],
                        ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back']
                    ]]
                ];

                $res = Telegram::sendMessage($chatId, "💵 Стандартна вартість: " . format_currency($data['price']) . "\nНадішліть іншу суму або підтвердіть поточну:", $keyboard);
                if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
            }
        }
        // Price Confirmation
        elseif ($callbackData === 'wizard_task_price_confirm') {
            if (\App\Models\Setting::get('feature_executor_payouts', '1') == '1') {
                TelegramConversation::update($chatId, 'task_commission', $data);
                $res = Telegram::sendMessage($chatId, "💰 Вкажіть розмір виплати виконавцю (або '0'):", ['inline_keyboard' => [[['text' => '⬅️ Назад', 'callback_data' => 'wizard_back']]]]);
            } else {
                $data['commission'] = 0.00;
                TelegramConversation::update($chatId, 'task_executor', $data);
                $keyboard = self::getExecutorKeyboard();
                $res = Telegram::sendMessage($chatId, "👤 Оберіть виконавця:", $keyboard);
            }
            if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
        }
        // Executor Selection
        elseif (strpos($callbackData, 'wizard_task_executor_') === 0) {
            $executorId = (int)str_replace('wizard_task_executor_', '', $callbackData);

            $data['executor_id'] = $executorId;
            TelegramConversation::update($chatId, 'task_execution_date', $data);

            // Show Calendar
            $year = (int)date('Y');
            $month = (int)date('n');
            $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_task_cal_');
            $keyboard['inline_keyboard'][] = [
                ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                ['text' => '⏭️ Пропустити', 'callback_data' => 'wizard_task_cal_skip']
            ];

            $res = Telegram::sendMessage($chatId, "📅 Оберіть <b>Дату виконання</b> або пропустіть (Сьогодні):", $keyboard);
            if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
        }
        // Calendar Actions
        elseif (strpos($callbackData, 'wizard_task_cal_') === 0) {
            $action = substr($callbackData, 16);

            if ($action === 'skip') {
                self::finish($chatId, $userId, $data['order_id'], $data['description'], $data['price'], $data['commission'] ?? 0.00, $data['executor_id'], $data['service_id'] ?? null, null);
                return;
            } elseif (strpos($action, 'nav_') === 0) {
                $parts = explode('_', substr($action, 4));
                $year = (int)$parts[0];
                $month = (int)$parts[1];
                $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_task_cal_');
                $keyboard['inline_keyboard'][] = [
                    ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                    ['text' => '⏭️ Пропустити', 'callback_data' => 'wizard_task_cal_skip']
                ];

                Telegram::editMessage($chatId, $messageId, "📅 Оберіть <b>Дату виконання</b>:", $keyboard);
            } elseif (strpos($action, 'day_') === 0) {
                $date = substr($action, 4);
                self::finish($chatId, $userId, $data['order_id'], $data['description'], $data['price'], $data['commission'] ?? 0.00, $data['executor_id'], $data['service_id'] ?? null, $date);
            }
        }
        // Loop Actions
        elseif ($callbackData === 'wizard_task_add_another') {
            self::start($chatId, $userId, $data['order_id']);
        } elseif ($callbackData === 'wizard_task_finish') {
            // Cleanup the prompt message
            $messageIds = TelegramConversation::getMessages($chatId);
            foreach ($messageIds as $msgId) {
                Telegram::deleteMessage($chatId, $msgId);
            }
            TelegramConversation::clear($chatId);
            // Silent finish
        }
    }

    /**
     * Завершити майстер: створити задачу в БД
     *
     * @param int|string $chatId      ID чату
     * @param int        $userId      ID користувача
     * @param int|null   $orderId     ID замовлення
     * @param string     $description Назва/опис задачі
     * @param float      $price       Вартість
     * @param float      $commission  Виплата виконавцю
     * @param int        $executorId  ID виконавця
     * @param int|null   $serviceId   ID послуги
     * @param string|null $deadline   Дедлайн
     * @return void
     */
    private static function finish($chatId, $userId, $orderId, $description, $price, $commission, $executorId, $serviceId = null, $deadline = null)
    {
        try {
            // Concurrency Guard (mirrors OrderWizard::finish())
            // Prevents duplicate task creation from double callback delivery
            $db = db();
            $lockKey = 'task_finalizing';

            $stmt = $db->prepare("SELECT step, data FROM telegram_conversations WHERE chat_id = ?");
            $stmt->execute([$chatId]);
            $current = $stmt->fetch();

            if (!$current || $current['step'] === $lockKey) {
                return; // Already finalizing or no conversation
            }

            // Try to acquire lock atomically
            $stmt = $db->prepare("UPDATE telegram_conversations SET step = ?, updated_at = NOW() WHERE chat_id = ? AND step != ?");
            $stmt->execute([$lockKey, $chatId, $lockKey]);

            if ($stmt->rowCount() === 0) {
                return; // Another request already acquired the lock
            }

            // 1. Create Task
            $profitAmount = (!$orderId && $price > 0) ? $price : 0.00;
            
            $taskId = Task::create([
                'title' => $description,
                'description' => "Створено через Telegram",
                'order_id' => $orderId, // Can be null now
                'executor_id' => $executorId,
                'creator_id' => $userId, // Pass creator explicitly
                'priority' => 'medium',
                'status' => 'new',
                'deadline' => $deadline,
                'profit_amount' => $profitAmount,
                'commission_amount' => $commission
            ]);

            if ($taskId) {
                // 2. Add to Order Items (Bill) ONLY if Order Exists
                if ($orderId && $price > 0) {
                    // If specific service ID was selected, use it. Otherwise find Misc.
                    $billServiceId = $serviceId ?: self::getMiscServiceId();
                    if ($billServiceId) {
                        Order::addItem($orderId, $billServiceId, 1, $price, $executorId, $description);
                    }
                }

                // Sync with Google Calendar is now handled by TaskCreated event listener

                // CLEANUP: Delete all wizard messages
                $messageIds = TelegramConversation::getMessages($chatId);
                foreach ($messageIds as $msgId) {
                    Telegram::deleteMessage($chatId, $msgId);
                }

                // Prompt for Loop
                // Update step to wait for loop answer
                $data = ['order_id' => $orderId]; // Keep order_id
                TelegramConversation::set($chatId, 'task_loop_prompt', $data, $userId); // New step name

                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '➕ Додати ще', 'callback_data' => 'wizard_task_add_another']],
                        [['text' => '✅ Готово', 'callback_data' => 'wizard_task_finish']]
                    ]
                ];
                $res = Telegram::sendMessage($chatId, "Бажаєте додати ще одну задачу?", $keyboard);
                if (is_array($res) && isset($res['result']['message_id'])) {
                    TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                }
            } else {
                Telegram::sendMessage($chatId, "❌ Помилка створення задачі (DB Error).");
                // Don't clear, let retry? Or clear.
                TelegramConversation::clear($chatId);
            }
        } catch (Exception $e) {
            error_log("TaskWizard Finish Error for chat $chatId: " . $e->getMessage());
            Telegram::sendMessage($chatId, "❌ Сталася помилка. Спробуйте ще раз або зверніться до адміністратора.");
            TelegramConversation::clear($chatId);
        }
    }

    private static function getExecutorKeyboard()
    {
        $stmt = db()->query("SELECT id, full_name, role FROM users WHERE is_active = 1 AND role IN ('executor', 'manager', 'director')"); // Allow anyone appropriate
        $users = $stmt->fetchAll();

        return self::buildButtons($users, 'wizard_task_executor_', 'full_name', false, true); // Using buildButtons + Back
    }

    private static function getMiscServiceId()
    {
        // Try to find 'Інше' or 'Miscellaneous'
        $stmt = db()->prepare("SELECT id FROM services WHERE name = 'Інше' OR name = 'Miscellaneous' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();

        if ($id) return $id;

        // Valid fallback: Create it if not exists to avoid SQL errors
        try {
            // Check if service with ID 1 exists
            $stmt = db()->query("SELECT id FROM services WHERE id = 1");
            if ($stmt->fetch()) return 1;

            // Create 'Інше'
            $stmt = db()->prepare("INSERT INTO services (name, category, base_price) VALUES (?, ?, ?)");
            $stmt->execute(['Інше', 'Загальне', 0]);
            return db()->lastInsertId();
        } catch (Exception $e) {
            return 1; // Last resort fallback
        }
    }

    private static function buildButtons($items, $prefix, $labelKey, $showPrice = false, $addBackButton = false)
    {
        $cols = 2;
        $buttons = [];
        $row = [];

        foreach ($items as $item) {
            $label = $item[$labelKey];
            $row[] = ['text' => $label, 'callback_data' => $prefix . $item['id']];
            if (count($row) >= $cols) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) $buttons[] = $row;

        if ($addBackButton) {
            $buttons[] = [
                ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                ['text' => '✖️ Скасувати', 'callback_data' => 'wizard_cancel']
            ];
        }

        return ['inline_keyboard' => $buttons];
    }
}
