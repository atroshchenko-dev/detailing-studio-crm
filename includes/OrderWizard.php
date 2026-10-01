<?php

declare(strict_types=1);

namespace App;

use App\Models\Order;
use App\Models\Client;
use App\Models\Car;
use Exception;

/**
 * Майстер створення замовлень через Telegram
 *
 * Кроки: телефон → клієнт → авто → дата прибуття → дедлайн.
 * Підтримує inline-календар, Back-навігацію та concurrency guard.
 *
 * @package App
 */
class OrderWizard
{
    private static function wlog($msg)
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logDir . '/telegram_webhook.log', date('Y-m-d H:i:s') . " - DEBUG: $msg" . PHP_EOL, FILE_APPEND);
    }

    /**
     * Запустити майстер створення замовлення
     *
     * @param int|string $chatId           ID чату
     * @param int        $userId           ID користувача
     * @param int|null   $triggerMessageId ID повідомлення-тригера для cleanup
     * @return void
     */
    public static function start($chatId, $userId, $triggerMessageId = null)
    {
        // Manually require Calendar
        require_once __DIR__ . '/TelegramCalendar.php';
        // CRITICAL: Clear conversation completely before starting new wizard.
        // TelegramConversation::set() uses array_merge, so stale keys like
        // 'order_id' from a previous wizard run would persist and block finish().
        TelegramConversation::clear($chatId);
        TelegramConversation::set($chatId, 'order_phone', [], $userId);
        $res = Telegram::sendMessage($chatId, "📦 <b>Створення нового замовлення</b>\n\nВведіть номер телефону клієнта (наприклад, 0501234567) або надішліть контакт.");
        if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);

        if ($triggerMessageId) {
            TelegramConversation::addMessageId($chatId, $triggerMessageId);
        }
    }

    /**
     * Обробити текстовий ввід користувача на поточному кроці
     *
     * @param int|string $chatId      ID чату
     * @param int        $userId      ID користувача
     * @param string     $text        Текст повідомлення
     * @param array      $messageData Telegram message object
     * @return void
     */
    public static function handle($chatId, $userId, $text, $messageData)
    {
        $conversation = TelegramConversation::get($chatId);
        if (!$conversation) return;

        $step = $conversation['step'];
        $data = $conversation['data'] ?? [];

        // Global Back Command (Optional, strictly we use buttons now)
        // but if user types /back we can handle it too? 
        // Let's stick to buttons for UI consistency.

        switch ($step) {
            case 'order_phone':
                // Check if contact shared
                if (isset($messageData['contact'])) {
                    $phone = $messageData['contact']['phone_number'];
                    $firstName = $messageData['contact']['first_name'];
                    $lastName = $messageData['contact']['last_name'] ?? '';
                    $name = trim("$firstName $lastName");
                } else {
                    $phone = preg_replace('/[^0-9]/', '', $text);
                    // Strict validation: 9 to 15 digits
                    if (strlen($phone) < 9 || strlen($phone) > 15) {
                        $res = Telegram::sendMessage($chatId, "❌ Невірний формат номеру. Номер повинен містити від 9 до 15 цифр.\nСпробуйте ще раз або введіть /cancel.");
                        if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                        return;
                    }
                    $name = ''; // Unknown yet
                }

                // Check if client exists
                $client = self::findClientByPhone($phone);

                $data['phone'] = $phone;

                if ($client) {
                    $data['client_id'] = $client['id'];
                    $data['client_name'] = $client['name'];

                    // Check for client's cars
                    $stmt = db()->prepare("SELECT * FROM cars WHERE client_id = ?");
                    $stmt->execute([$client['id']]);
                    $cars = $stmt->fetchAll();

                    if (count($cars) > 0) {
                        $data['cars'] = $cars;
                        TelegramConversation::set($chatId, 'order_choose_car', $data, $userId);

                        $keyboard = self::buildCarKeyboard($cars);
                        $res = Telegram::sendMessage($chatId, "👤 Клієнт знайдений: <b>{$client['name']}</b>\n\nОберіть авто зі списку:", ['inline_keyboard' => $keyboard]);
                        if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    } else {
                        // Client found but no cars
                        TelegramConversation::set($chatId, 'order_car_make_model', $data, $userId);
                        $res = Telegram::sendMessage($chatId, "👤 Клієнт знайдений: <b>{$client['name']}</b>\n\nАле авто не знайдено. Введіть Марку та Модель авто (наприклад: BMW X5):");
                        if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    }
                } else {
                    // New Client
                    if (!empty($name)) {
                        // Contact shared, so we have name
                        $data['client_name'] = $name;
                        TelegramConversation::set($chatId, 'order_car_make_model', $data, $userId);
                        $res = Telegram::sendMessage($chatId, "👤 Новий клієнт: <b>{$name}</b>\n\nВведіть Марку та Модель авто:");
                        if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    } else {
                        TelegramConversation::set($chatId, 'order_client_name', $data, $userId);
                        $res = Telegram::sendMessage($chatId, "👤 Клієнт не знайдений.\n\nВведіть Ім'я клієнта:");
                        if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    }
                }
                break;

            case 'order_choose_car':
                // User typed text instead of clicking button
                TelegramConversation::set($chatId, 'order_choose_car', $data, $userId); // Refresh step just in case
                $res = Telegram::sendMessage($chatId, "⚠️ Будь ласка, оберіть авто, натиснувши кнопку нижче, або натисніть 'Назад'.", ['inline_keyboard' => self::buildCarKeyboard($data['cars'] ?? [])]);
                if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'order_client_name':
                $data['client_name'] = $text;
                TelegramConversation::set($chatId, 'order_car_make_model', $data, $userId);
                $res = Telegram::sendMessage($chatId, "🚘 Тепер введіть Марку та Модель авто (наприклад: Toyota Camry):");
                if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'order_car_make_model':
                $data['car_make_model'] = $text;
                TelegramConversation::set($chatId, 'order_planned_date', $data, $userId);

                // Show Calendar
                $year = (int)date('Y');
                $month = (int)date('n');
                $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_start_');
                // Add Skip Button + Back
                $keyboard['inline_keyboard'][] = [
                    ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                    ['text' => '✖️ Скасувати', 'callback_data' => 'wizard_cancel'],
                    ['text' => '⏭️ Пропустити (Зараз)', 'callback_data' => 'wizard_cal_start_skip']
                ];

                $res = Telegram::sendMessage($chatId, "📅 Оберіть <b>Дату прибуття</b>:", $keyboard);
                if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'order_planned_date':
                $date = null;
                $input = strtolower(trim($text));
                if ($input === '/skip') {
                    $date = null;
                } elseif ($input === 'зараз' || $input === 'now') {
                    $date = date('Y-m-d');
                } else {
                    // Try to parse
                    $ts = strtotime($text);
                    if ($ts) {
                        $date = date('Y-m-d', $ts);
                    } else {
                        $res = Telegram::sendMessage($chatId, "⚠️ Невірний формат дати. Спробуйте ще раз (РРРР-ММ-ДД) або /skip:");
                        if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                        return;
                    }
                }
                $data['planned_start'] = $date;
                $data['description'] = ''; // Ensure description is empty since we skipped it

                TelegramConversation::set($chatId, 'order_deadline', $data, $userId);

                // Show Calendar
                $year = (int)date('Y');
                $month = (int)date('n');
                $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_end_');
                // Add Skip Button + Back
                $keyboard['inline_keyboard'][] = [
                    ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                    ['text' => '✖️ Скасувати', 'callback_data' => 'wizard_cancel'],
                    ['text' => '⏭️ Пропустити', 'callback_data' => 'wizard_cal_end_skip']
                ];

                $res = Telegram::sendMessage($chatId, "🏁 Оберіть <b>Орієнтовну дату видачі</b> (Дедлайн) або пропустіть:", $keyboard);
                if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;
        }
    }

    /**
     * Обробити inline-callback (кнопки календаря, вибір авто, Back)
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
        if (!$conversation) {
            self::wlog("OrderWizard::handleCallback NO CONVERSATION chat=$chatId callback=$callbackData");
            return;
        }

        $step = $conversation['step'];
        $data = $conversation['data'] ?? [];
        self::wlog("OrderWizard::handleCallback chat=$chatId step=$step callback=$callbackData userId=$userId");

        // Back Navigation logic
        if ($callbackData === 'wizard_back') {
            switch ($step) {
                // From Choose Car -> Back means restart (to enter phone)
                case 'order_choose_car':
                    self::start($chatId, $userId);
                    return;
                    // From Client Name -> Back means restart
                case 'order_client_name':
                    self::start($chatId, $userId);
                    return;
                    // From Car Make Model -> Back might be complex. 
                    // If we had a list of cars (new car flow), back to list. 
                    // If we explicitly entered make/model for existing client (no cars), back to phone?
                    // Simplest: If 'cars' exists in data, go to 'order_choose_car'. Else restart.
                case 'order_car_make_model':
                    if (isset($data['cars']) && count($data['cars']) > 0) {
                        TelegramConversation::set($chatId, 'order_choose_car', $data, $userId);
                        $keyboard = self::buildCarKeyboard($data['cars']);
                        $res = Telegram::sendMessage($chatId, "👤 Оберіть авто зі списку:", ['inline_keyboard' => $keyboard]);
                    } else {
                        self::start($chatId, $userId);
                    }
                    return;
                case 'order_planned_date':
                    // Back logic depends on where we came from.
                    // If we have 'car_id' and it was chosen from list -> order_choose_car
                    // But we might have just entered make/model.
                    // If 'cars' exists in data, we likely came from choose_car (existing client).
                    // If not, we came from make_model (new client/car).
                    // However, if we picked 'New Car' from list, 'cars' still exists.

                    // Let's assume:
                    // If data has 'car_make_model' set manually, we go back to 'order_car_make_model'.
                    // If data has 'car_id' set from list, we go back to 'order_choose_car'.

                    // Actually simpliest is to check if we are in 'new car' mode. 
                    // If we just entered make/model, we want to edit it.

                    if (isset($data['car_make_model']) && !isset($data['car_id'])) {
                        // We entered make/model manually (New Client OR New Car for Existing)
                        TelegramConversation::set($chatId, 'order_car_make_model', $data, $userId);
                        $res = Telegram::sendMessage($chatId, "🚘 Введіть Марку та Модель авто:");
                    } else {
                        // We selected from list
                        TelegramConversation::set($chatId, 'order_choose_car', $data, $userId);
                        $keyboard = self::buildCarKeyboard($data['cars'] ?? []);
                        $res = Telegram::sendMessage($chatId, "👤 Оберіть авто зі списку:", ['inline_keyboard' => $keyboard]);
                    }
                    if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    return;
                case 'order_deadline':
                    TelegramConversation::set($chatId, 'order_planned_date', $data, $userId);
                    $year = (int)date('Y');
                    $month = (int)date('n');
                    $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_start_');
                    $keyboard['inline_keyboard'][] = [
                        ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                        ['text' => '⏭️ Пропустити (Зараз)', 'callback_data' => 'wizard_cal_start_skip']
                    ];
                    $res = Telegram::sendMessage($chatId, "📅 Оберіть <b>Дату прибуття</b>:", $keyboard);
                    if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    return;
            }
            return;
        }

        if ($step === 'order_choose_car') {
            if (strpos($callbackData, 'wizard_car_') === 0) {
                $carId = substr($callbackData, 11);

                if ($carId === 'new') {
                    TelegramConversation::set($chatId, 'order_car_make_model', $data, $userId);
                    $res = Telegram::sendMessage($chatId, "Введіть Марку та Модель нового авто:");
                    if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                } else {
                    $data['car_id'] = (int)$carId;
                    TelegramConversation::set($chatId, 'order_planned_date', $data, $userId);

                    // Show Calendar
                    $year = (int)date('Y');
                    $month = (int)date('n');
                    $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_start_');
                    $keyboard['inline_keyboard'][] = [
                        ['text' => '🔙 Назад', 'callback_data' => 'wizard_back'],
                        ['text' => '⏩ Пропустити (Зараз)', 'callback_data' => 'wizard_cal_start_skip']
                    ];

                    $res = Telegram::sendMessage($chatId, "📅 Оберіть <b>Дату прибуття</b>:", $keyboard);
                    if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                }
            }
        } elseif ($step === 'order_planned_date' && strpos($callbackData, 'wizard_cal_start_') === 0) {
            $action = substr($callbackData, 17); // Remove 'wizard_cal_start_'

            if ($action === 'skip') {
                $data['planned_start'] = date('Y-m-d');
                TelegramConversation::set($chatId, 'order_deadline', $data, $userId);

                // Remove buttons from the old calendar message
                if ($messageId) {
                    Telegram::editMessage($chatId, $messageId, "📅 Дата прибуття: <b>" . date('Y-m-d') . "</b> (Зараз) ✅");
                }

                // Show Calendar for Deadline
                $year = (int)date('Y');
                $month = (int)date('n');
                $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_end_');
                $keyboard['inline_keyboard'][] = [
                    ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                    ['text' => '⏭️ Пропустити', 'callback_data' => 'wizard_cal_end_skip']
                ];

                $res = Telegram::sendMessage($chatId, "🏁 Оберіть <b>Орієнтовну дату видачі</b>:", $keyboard);
                if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                return;
            } elseif (strpos($action, 'nav_') === 0) {
                $parts = explode('_', substr($action, 4));
                $year = (int)$parts[0];
                $month = (int)$parts[1];
                $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_start_');
                $keyboard['inline_keyboard'][] = [
                    ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                    ['text' => '⏭️ Пропустити (Зараз)', 'callback_data' => 'wizard_cal_start_skip']
                ];

                Telegram::editMessage($chatId, $messageId, "📅 Оберіть <b>Дату прибуття</b>:", $keyboard);
            } elseif (strpos($action, 'day_') === 0) {
                $date = substr($action, 4);
                $data['planned_start'] = $date;

                TelegramConversation::set($chatId, 'order_deadline', $data, $userId);

                // Remove buttons from the old calendar message (critical for iOS)
                if ($messageId) {
                    Telegram::editMessage($chatId, $messageId, "📅 Дата прибуття: <b>$date</b> ✅");
                }

                // Show Calendar for Deadline
                $year = (int)date('Y');
                $month = (int)date('n');
                $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_end_');
                $keyboard['inline_keyboard'][] = [
                    ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                    ['text' => '⏭️ Пропустити', 'callback_data' => 'wizard_cal_end_skip']
                ];

                $res = Telegram::sendMessage($chatId, "🏁 Оберіть <b>Орієнтовну дату видачі</b>:", $keyboard);
                if (isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
            }
        } elseif ($step === 'order_deadline' && strpos($callbackData, 'wizard_cal_end_') === 0) {
            $action = substr($callbackData, 15); // Remove 'wizard_cal_end_'

            if ($action === 'skip') {
                $data['deadline'] = null;
                self::finish($chatId, $userId, $data);
                return;
            } elseif (strpos($action, 'nav_') === 0) {
                $parts = explode('_', substr($action, 4));
                $year = (int)$parts[0];
                $month = (int)$parts[1];
                $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_end_');
                $keyboard['inline_keyboard'][] = [
                    ['text' => '⬅️ Назад', 'callback_data' => 'wizard_back'],
                    ['text' => '⏭️ Пропустити', 'callback_data' => 'wizard_cal_end_skip']
                ];

                Telegram::editMessage($chatId, $messageId, "🏁 Оберіть <b>Орієнтовну дату видачі</b>:", $keyboard);
            } elseif (strpos($action, 'day_') === 0) {
                $date = substr($action, 4);
                $data['deadline'] = $date;
                self::wlog("OrderWizard: deadline day selected chat=$chatId date=$date, calling finish()");
                self::finish($chatId, $userId, $data);
            }
        // Fallback: user clicked OLD start calendar while step is already order_deadline (iOS issue)
        } elseif ($step === 'order_deadline' && strpos($callbackData, 'wizard_cal_start_') === 0) {
            $action = substr($callbackData, 17); // Remove 'wizard_cal_start_'

            if (strpos($action, 'day_') === 0 || $action === 'skip') {
                // User re-selected a start date from the old calendar — update it and prompt again
                $date = ($action === 'skip') ? date('Y-m-d') : substr($action, 4);
                $data['planned_start'] = $date;
                TelegramConversation::set($chatId, 'order_deadline', $data, $userId);

                // Edit the old calendar message to remove its buttons
                if ($messageId) {
                    $label = ($action === 'skip') ? $date . " (Зараз)" : $date;
                    Telegram::editMessage($chatId, $messageId, "📅 Дата прибуття: <b>$label</b> ✅");
                }
            } elseif (strpos($action, 'nav_') === 0) {
                // Navigation on old calendar — just remove the old calendar buttons
                if ($messageId) {
                    $startDate = $data['planned_start'] ?? date('Y-m-d');
                    Telegram::editMessage($chatId, $messageId, "📅 Дата прибуття: <b>$startDate</b> ✅");
                }
            }
        } elseif ($step === 'order_created_prompt') {
            if ($callbackData === 'wizard_order_add_task') {
                // Handover to TaskWizard
                \App\TaskWizard::start($chatId, $userId, $data['order_id']);
            } elseif ($callbackData === 'wizard_order_finish') {
                // Cleanup the prompt message
                $messageIds = TelegramConversation::getMessages($chatId);
                foreach ($messageIds as $msgId) {
                    Telegram::deleteMessage($chatId, $msgId);
                }
                TelegramConversation::clear($chatId);
                // Silent finish (no text message)
            }
        }
    }

    private static function finish($chatId, $userId, $data)
    {
        self::wlog("OrderWizard::finish START chat=$chatId user=$userId keys=" . implode(',', array_keys($data)));
        try {
            // 0. Concurrency Guard / Lock (Atomic update)
            $db = db();
            $lockKey = 'order_finalizing';

            // Check if already finalizing or order_id exists
            $stmt = $db->prepare("SELECT step, data FROM telegram_conversations WHERE chat_id = ?");
            $stmt->execute([$chatId]);
            $current = $stmt->fetch();

            if (!$current || $current['step'] === $lockKey) {
                self::wlog("OrderWizard::finish BLOCKED chat=$chatId reason=" . (!$current ? 'no_conversation' : 'already_finalizing'));
                return;
            }

            $currentData = json_decode($current['data'], true);
            // NOTE: We deliberately do NOT check $currentData['order_id'] here.
            // Previous bug: array_merge in TelegramConversation::set() preserved
            // stale order_id from prior wizard runs, permanently blocking finish().
            // The lock mechanism (step = 'order_finalizing') is sufficient.

            // Try to acquire lock atomically
            $stmt = $db->prepare("UPDATE telegram_conversations SET step = ?, updated_at = NOW() WHERE chat_id = ? AND step != ?");
            $stmt->execute([$lockKey, $chatId, $lockKey]);

            if ($stmt->rowCount() === 0) {
                self::wlog("OrderWizard::finish BLOCKED chat=$chatId reason=lock_race_condition");
                return;
            }
            self::wlog("OrderWizard::finish LOCK ACQUIRED chat=$chatId");
            // 1. Ensure Client
            if (isset($data['client_id'])) {
                $clientId = $data['client_id'];
                self::wlog("OrderWizard::finish chat=$chatId client=existing id=$clientId");
            } else {
                $clientId = Client::create([
                    'name' => $data['client_name'],
                    'phone' => $data['phone']
                ]);
                self::wlog("OrderWizard::finish chat=$chatId client=created id=$clientId");
            }

            // 2. Ensure Car
            if (isset($data['car_id'])) {
                $carId = $data['car_id'];
                self::wlog("OrderWizard::finish chat=$chatId car=existing id=$carId");
            } else {
                // Parse make/model
                $parts = explode(' ', $data['car_make_model'], 2);
                $make = $parts[0] ?? 'Unknown';
                $model = $parts[1] ?? 'Unknown';
                $license = ($data['car_license'] ?? '') === '/skip' ? null : ($data['car_license'] ?? null);
                self::wlog("OrderWizard::finish chat=$chatId car=creating make=$make model=$model");

                $carId = Car::create([
                    'client_id' => $clientId,
                    'make' => $make,
                    'model' => $model,
                    'license_plate' => $license,
                    'year' => date('Y')
                ]);
                self::wlog("OrderWizard::finish chat=$chatId car=created id=$carId");
            }

            // 3. Create Order
            $oldUserId = \App\Auth::getUserId();
            \App\Auth::setContextUserId($userId);
            self::wlog("OrderWizard::finish chat=$chatId creating order clientId=$clientId carId=$carId userId=$userId");

            $orderId = Order::create([
                'client_id' => $clientId,
                'car_id' => $carId,
                'status' => 'in_progress',
                'notes' => $data['description'] ?? '',
                'manager_id' => $userId,
                'planned_start' => $data['planned_start'] ?? null,
                'deadline' => $data['deadline'] ?? null
            ]);

            \App\Auth::setContextUserId($oldUserId);
            self::wlog("OrderWizard::finish chat=$chatId orderId=$orderId");

            if ($orderId) {
                // Save order_id to data to prevent re-processing if retried
                $data['order_id'] = $orderId;
                TelegramConversation::set($chatId, 'order_created_prompt', $data, $userId);

                // RESPOND TO USER FIRST — before any slow external API calls
                // This ensures the user sees the result immediately, even if
                // Google Calendar or Telegram group notifications are slow/timeout.
                $messageIds = TelegramConversation::getMessages($chatId);
                foreach ($messageIds as $msgId) {
                    Telegram::deleteMessage($chatId, $msgId);
                }

                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '➕ Додати послугу/задачу', 'callback_data' => 'wizard_order_add_task']],
                        [['text' => '✖️ Завершити', 'callback_data' => 'wizard_order_finish']]
                    ]
                ];
                $res = Telegram::sendMessage($chatId, "Бажаєте додати послуги та виконавців до цього замовлення?", $keyboard);
                if (isset($res['result']['message_id'])) {
                    TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                }

                // Dispatch Events AFTER user response (Telegram group + Google Calendar)
                // Wrapped in try/catch so slow/failed external APIs don't affect the user
                try {
                    \App\Events\EventDispatcher::dispatch(new \App\Events\OrderCreated((int)$orderId, [
                        'planned_start' => $data['planned_start'] ?? null,
                        'deadline' => $data['deadline'] ?? null,
                        'notes' => $data['description'] ?? ''
                    ]));
                } catch (\Throwable $eventError) {
                    self::wlog("OrderWizard: Event dispatch failed for order $orderId: " . $eventError->getMessage());
                }
            } else {
                Telegram::sendMessage($chatId, "❌ Помилка при збереженні замовлення.");
                TelegramConversation::clear($chatId);
            }
        } catch (\Throwable $e) {
            self::wlog("OrderWizard::finish EXCEPTION chat=$chatId: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            Telegram::sendMessage($chatId, "❌ Сталася помилка: " . mb_substr($e->getMessage(), 0, 100));
            TelegramConversation::clear($chatId);
        }
    }

    private static function findClientByPhone($phone)
    {
        // Simple search right 10 digits
        $search = substr($phone, -10);
        $stmt = db()->prepare("SELECT * FROM clients WHERE phone LIKE ? LIMIT 1");
        $stmt->execute(["%$search"]);
        return $stmt->fetch();
    }

    private static function buildCarKeyboard($cars)
    {
        $keyboard = [];
        foreach ($cars as $car) {
            $label = $car['make'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')';
            $keyboard[] = [['text' => $label, 'callback_data' => 'wizard_car_' . $car['id']]];
        }
        $keyboard[] = [['text' => '➕ Інше авто', 'callback_data' => 'wizard_car_new']];
        $keyboard[] = [['text' => '⬅️ Назад (До початку)', 'callback_data' => 'wizard_back']];
        return $keyboard;
    }
}
