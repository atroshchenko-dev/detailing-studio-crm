<?php

declare(strict_types=1);

namespace App;

use App\Models\Expense;
use App\Payments;
use Exception;

/**
 * Майстер фінансового обліку через Telegram
 *
 * Обробляє витрати студії та виплати співробітникам.
 * Показує детальну статистику прибутку з порівнянням по місяцям.
 *
 * @package App
 */
class FinanceWizard
{
    /**
     * Запустити майстер фінансів
     *
     * @param int|string $chatId           ID чату
     * @param int        $userId           ID користувача
     * @param int|null   $triggerMessageId ID повідомлення-тригера
     * @return void
     */
    public static function start($chatId, $userId, $triggerMessageId = null)
    {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏢 Витрата студії', 'callback_data' => 'wizard_finance_type_studio'],
                    ['text' => '👤 Виплата співробітнику', 'callback_data' => 'wizard_finance_type_employee']
                ],
                [['text' => '📊 Статистика прибутку', 'callback_data' => 'wizard_finance_stats']],
                [['text' => '✖️ Скасувати', 'callback_data' => 'wizard_finance_cancel']]
            ]
        ];

        TelegramConversation::set($chatId, 'finance_choose_type', [], $userId);
        $res = Telegram::sendMessage($chatId, "💸 <b>Фінансовий облік</b>\n\nОберіть тип операції:", $keyboard);
        
        if (is_array($res) && isset($res['result']['message_id'])) {
            TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
        }

        if ($triggerMessageId) {
            TelegramConversation::addMessageId($chatId, $triggerMessageId);
        }
    }

    /**
     * Обробити текстовий ввід (сума, примітка)
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

        switch ($step) {
            case 'finance_amount':
                $amount = (float)str_replace(',', '.', $text);
                if ($amount <= 0) {
                    $res = Telegram::sendMessage($chatId, "⚠️ Будь ласка, введіть коректну суму (число більше 0):");
                    if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                    return;
                }
                $data['amount'] = $amount;

                if ($data['type'] === 'studio') {
                    $categories = Expense::getCategories();
                    $keyboard = [];
                    foreach ($categories as $key => $label) {
                        $keyboard[] = [['text' => $label, 'callback_data' => 'wizard_finance_category_' . $key]];
                    }
                    $keyboard[] = [['text' => '⬅️ Назад', 'callback_data' => 'wizard_finance_back']];

                    TelegramConversation::set($chatId, 'finance_category', $data, $userId);
                    $res = Telegram::sendMessage($chatId, "📂 Оберіть категорію витрати:", ['inline_keyboard' => $keyboard]);
                } else {
                    // Payout - Choose Employee
                    $stmt = db()->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
                    $users = $stmt->fetchAll();
                    
                    $keyboard = [];
                    foreach ($users as $u) {
                        $keyboard[] = [['text' => $u['full_name'], 'callback_data' => 'wizard_finance_employee_' . $u['id']]];
                    }
                    $keyboard[] = [['text' => '⬅️ Назад', 'callback_data' => 'wizard_finance_back']];

                    TelegramConversation::set($chatId, 'finance_employee', $data, $userId);
                    $res = Telegram::sendMessage($chatId, "👤 Оберіть співробітника для виплати:", ['inline_keyboard' => $keyboard]);
                }
                if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'finance_notes':
                $data['notes'] = $text;
                self::askPaymentMethod($chatId, $userId, $data);
                break;
        }
    }

    /**
     * Обробити inline-callback (вибір типу, категорії, співробітника, методу, дати)
     *
     * @param int|string $chatId       ID чату
     * @param int        $userId       ID користувача
     * @param string     $callbackData Callback data
     * @param int|null   $messageId    ID повідомлення
     * @return void
     */
    public static function handleCallback($chatId, $userId, $callbackData, $messageId = null)
    {
        $conversation = TelegramConversation::get($chatId);
        if (!$conversation) return;

        $step = $conversation['step'];
        $data = $conversation['data'] ?? [];

        if ($callbackData === 'wizard_finance_cancel') {
            self::cleanup($chatId);
            Telegram::sendMessage($chatId, "✖️ Операцію скасовано.");
            return;
        }

        if ($callbackData === 'wizard_finance_back') {
            self::start($chatId, $userId);
            return;
        }

        if ($callbackData === 'wizard_finance_stats') {
            self::showDetailedStats($chatId);
            return;
        }

        switch ($step) {
            case 'finance_choose_type':
                if ($callbackData === 'wizard_finance_type_studio') {
                    $data['type'] = 'studio';
                } else {
                    $data['type'] = 'employee';
                }
                TelegramConversation::set($chatId, 'finance_amount', $data, $userId);
                $res = Telegram::sendMessage($chatId, "💵 Введіть суму:");
                if (is_array($res) && isset($res['result']['message_id'])) TelegramConversation::addMessageId($chatId, $res['result']['message_id']);
                break;

            case 'finance_category':
                if (strpos($callbackData, 'wizard_finance_category_') === 0) {
                    $data['category'] = substr($callbackData, 24);
                    self::askNotes($chatId, $userId, $data);
                }
                break;

            case 'finance_employee':
                if (strpos($callbackData, 'wizard_finance_employee_') === 0) {
                    $data['employee_id'] = (int)substr($callbackData, 24);
                    
                    $types = Payments::getTypes();
                    $keyboard = [];
                    foreach ($types as $key => $label) {
                        $keyboard[] = [['text' => $label, 'callback_data' => 'wizard_finance_paytype_' . $key]];
                    }
                    $keyboard[] = [
                        ['text' => '⬅️ Назад', 'callback_data' => 'wizard_finance_back'],
                        ['text' => '✖️ Скасувати', 'callback_data' => 'wizard_finance_cancel']
                    ];

                    TelegramConversation::set($chatId, 'finance_paytype', $data, $userId);
                    Telegram::sendMessage($chatId, "🏷 Оберіть тип виплати:", ['inline_keyboard' => $keyboard]);
                }
                break;

            case 'finance_paytype':
                if (strpos($callbackData, 'wizard_finance_paytype_') === 0) {
                    $data['paytype'] = substr($callbackData, 23);
                    self::askNotes($chatId, $userId, $data);
                }
                break;

            case 'finance_notes':
                if ($callbackData === 'wizard_finance_skip_notes') {
                    $data['notes'] = null;
                    self::askPaymentMethod($chatId, $userId, $data);
                }
                break;

            case 'finance_method':
                if (strpos($callbackData, 'wizard_finance_method_') === 0) {
                    $data['payment_method'] = substr($callbackData, 22);
                    
                    // Show Calendar
                    require_once __DIR__ . '/TelegramCalendar.php';
                    $year = (int)date('Y');
                    $month = (int)date('n');
                    $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_finance_');
                    $keyboard['inline_keyboard'][] = [
                        ['text' => '🗓 Сьогодні', 'callback_data' => 'wizard_cal_finance_today']
                    ];
                    
                    TelegramConversation::set($chatId, 'finance_date', $data, $userId);
                    Telegram::sendMessage($chatId, "📅 Оберіть дату операції:", $keyboard);
                }
                break;

            case 'finance_date':
                if (strpos($callbackData, 'wizard_cal_finance_') === 0) {
                    $action = substr($callbackData, 19);
                    if ($action === 'today') {
                        $data['date'] = date('Y-m-d');
                        self::finish($chatId, $userId, $data);
                    } elseif (strpos($action, 'day_') === 0) {
                        $data['date'] = substr($action, 4);
                        self::finish($chatId, $userId, $data);
                    } elseif (strpos($action, 'nav_') === 0) {
                        require_once __DIR__ . '/TelegramCalendar.php';
                        $parts = explode('_', substr($action, 4));
                        $year = (int)$parts[0];
                        $month = (int)$parts[1];
                        $keyboard = TelegramCalendar::getKeyboard($year, $month, 'wizard_cal_finance_');
                        $keyboard['inline_keyboard'][] = [
                            ['text' => '🗓 Сьогодні', 'callback_data' => 'wizard_cal_finance_today']
                        ];
                        Telegram::editMessage($chatId, $messageId, "📅 Оберіть дату операції:", $keyboard);
                    }
                }
                break;
        }
    }

    private static function askNotes($chatId, $userId, $data)
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '⏭️ Пропустити', 'callback_data' => 'wizard_finance_skip_notes']],
                [['text' => '✖️ Скасувати', 'callback_data' => 'wizard_finance_cancel']]
            ]
        ];
        TelegramConversation::set($chatId, 'finance_notes', $data, $userId);
        Telegram::sendMessage($chatId, "💬 Додайте примітку (або натисніть 'Пропустити'):", $keyboard);
    }

    private static function askPaymentMethod($chatId, $userId, $data)
    {
        $methods = Payments::getMethods();
        $keyboard = [];
        foreach ($methods as $key => $label) {
            $keyboard[] = [['text' => $label, 'callback_data' => 'wizard_finance_method_' . $key]];
        }
        TelegramConversation::set($chatId, 'finance_method', $data, $userId);
        Telegram::sendMessage($chatId, "💳 Оберіть спосіб оплати:", ['inline_keyboard' => $keyboard]);
    }

    private static function finish($chatId, $userId, $data)
    {
        try {
            // Concurrency Guard — prevent duplicate operations from double-click
            $db = db();
            $lockKey = 'finance_finalizing';

            $stmt = $db->prepare("SELECT step FROM telegram_conversations WHERE chat_id = ?");
            $stmt->execute([$chatId]);
            $current = $stmt->fetch();

            if (!$current || $current['step'] === $lockKey) {
                return;
            }

            $stmt = $db->prepare("UPDATE telegram_conversations SET step = ?, updated_at = NOW() WHERE chat_id = ? AND step != ?");
            $stmt->execute([$lockKey, $chatId, $lockKey]);

            if ($stmt->rowCount() === 0) {
                return; // Another request already acquired the lock
            }

            $oldUserId = \App\Auth::getUserId();
            \App\Auth::setContextUserId($userId);

            $success = false;
            if ($data['type'] === 'studio') {
                $expenseId = Expense::create([
                    'category' => $data['category'],
                    'amount' => $data['amount'],
                    'payment_method' => $data['payment_method'],
                    'payment_date' => $data['date'],
                    'notes' => $data['notes'] ?? null
                ]);
                $success = ($expenseId !== false);
            } else {
                $success = Payments::create([
                    'user_id' => $data['employee_id'],
                    'amount' => $data['amount'],
                    'payment_type' => $data['paytype'],
                    'payment_method' => $data['payment_method'],
                    'payment_date' => $data['date'],
                    'notes' => $data['notes'] ?? null
                ]);
            }

            \App\Auth::setContextUserId($oldUserId);

            if ($success) {
                self::cleanup($chatId);
                $typeLabel = ($data['type'] === 'studio') ? "Витрату студії" : "Виплату співробітнику";
                Telegram::sendMessage($chatId, "✅ <b>{$typeLabel} успішно збережено!</b>\n\nСума: " . number_format((float)$data['amount'], 2, '.', ' ') . " грн\nДата: {$data['date']}");
            } else {
                Telegram::sendMessage($chatId, "⭕️ Помилка при збереженні. Спробуйте ще раз або зверніться до адміністратора.");
            }
        } catch (Exception $e) {
            error_log("FinanceWizard Finish Error for chat $chatId: " . $e->getMessage());
            Telegram::sendMessage($chatId, "❌ Сталася помилка. Спробуйте ще раз або зверніться до адміністратора.");
        }
    }

    /**
     * Показати детальну статистику прибутку
     *
     * Сьогодні / Поточний місяць (витрати по категоріях, маржа)
     * / Порівняння з минулим місяцем.
     *
     * @param int|string $chatId ID чату
     * @return void
     */
    private static function showDetailedStats($chatId)
    {
        require_once __DIR__ . '/Reports.php';

        $today = date('Y-m-d');
        $summaryToday = Reports::getFinancialSummary($today, $today);

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $summaryMonth = Reports::getFinancialSummary($monthStart, $monthEnd);

        $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
        $summaryLastMonth = Reports::getFinancialSummary($lastMonthStart, $lastMonthEnd);

        $monthName = self::getUkrainianMonth((int)date('n'));
        $lastMonthName = self::getUkrainianMonth((int)date('n', strtotime('-1 month')));

        // === TODAY ===
        $message = "📊 <b>Детальна статистика прибутку</b>\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "📅 <b>Сьогодні</b> (" . date('d.m.Y') . ")\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "💵 Дохід: " . format_currency($summaryToday['revenue'] ?? 0) . "\n";
        $message .= "📉 Витрати: " . format_currency($summaryToday['total_expenses'] ?? 0) . "\n";
        $profitToday = ($summaryToday['profit'] ?? 0);
        $profitIcon = $profitToday >= 0 ? '📈' : '📉';
        $message .= "{$profitIcon} Прибуток: <b>" . format_currency($profitToday) . "</b>\n";

        // === THIS MONTH ===
        $message .= "\n━━━━━━━━━━━━━━━━━━\n";
        $message .= "🗓 <b>{$monthName} " . date('Y') . "</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "💵 Дохід: <b>" . format_currency($summaryMonth['revenue'] ?? 0) . "</b>\n\n";

        $message .= "📦 <b>Витрати по категоріях:</b>\n";
        $purchaseExp = (float)($summaryMonth['purchase_expenses'] ?? 0);
        $salaryExp = (float)($summaryMonth['salary_expenses'] ?? 0);
        $studioExp = (float)($summaryMonth['studio_expenses'] ?? 0);
        $totalExp = (float)($summaryMonth['total_expenses'] ?? 0);

        $message .= "  🛒 Закупівлі: " . format_currency($purchaseExp) . "\n";
        $message .= "  👥 Зарплати: " . format_currency($salaryExp) . "\n";
        $message .= "  🏢 Студія: " . format_currency($studioExp) . "\n";
        $message .= "  ─────────────\n";
        $message .= "  📉 Всього: <b>" . format_currency($totalExp) . "</b>\n\n";

        $profitMonth = ($summaryMonth['profit'] ?? 0);
        $profitIcon = $profitMonth >= 0 ? '📈' : '🔻';
        $message .= "{$profitIcon} <b>Чистий прибуток: " . format_currency($profitMonth) . "</b>\n";

        // Margin
        $revenueMonth = (float)($summaryMonth['revenue'] ?? 0);
        if ($revenueMonth > 0) {
            $margin = round(($profitMonth / $revenueMonth) * 100, 1);
            $message .= "📐 Маржинальність: <b>{$margin}%</b>\n";
        }

        // Payment methods
        $payments = $summaryMonth['payments'] ?? [];
        if (!empty($payments)) {
            $methodNames = [
                'cash' => '💵 Готівка',
                'card' => '💳 Картка',
                'transfer' => '🏦 Переказ',
                'online' => '🌐 Онлайн',
            ];
            $message .= "\n💳 <b>Оплати по методах:</b>\n";
            foreach ($payments as $p) {
                $method = $methodNames[$p['payment_method']] ?? ('🔹 ' . $p['payment_method']);
                $message .= "  {$method}: " . format_currency($p['total_amount']) . "\n";
            }
        }

        // === COMPARISON WITH LAST MONTH ===
        $revLast = (float)($summaryLastMonth['revenue'] ?? 0);
        $profitLast = (float)($summaryLastMonth['profit'] ?? 0);

        $message .= "\n━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 <b>Порівняння з {$lastMonthName}</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";

        // Revenue comparison
        $revGrowth = ($revLast > 0) ? round((($revenueMonth - $revLast) / $revLast) * 100, 1) : ($revenueMonth > 0 ? 100 : 0);
        $revArrow = $revGrowth >= 0 ? '🟢 ↑' : '🔴 ↓';
        $message .= "💵 Дохід: {$revArrow} {$revGrowth}%";
        $message .= " (" . format_currency($revLast) . " → " . format_currency($revenueMonth) . ")\n";

        // Profit comparison
        $profGrowth = ($profitLast > 0) ? round((($profitMonth - $profitLast) / $profitLast) * 100, 1) : ($profitMonth > 0 ? 100 : 0);
        $profArrow = $profGrowth >= 0 ? '🟢 ↑' : '🔴 ↓';
        $message .= "📈 Прибуток: {$profArrow} {$profGrowth}%";
        $message .= " (" . format_currency($profitLast) . " → " . format_currency($profitMonth) . ")\n";

        Telegram::sendMessage($chatId, $message);
    }

    /**
     * Отримати українську назву місяця
     *
     * @param int $month Номер місяця (1-12)
     * @return string Назва місяця
     */
    private static function getUkrainianMonth(int $month): string
    {
        $months = [
            1 => 'Січень', 2 => 'Лютий', 3 => 'Березень',
            4 => 'Квітень', 5 => 'Травень', 6 => 'Червень',
            7 => 'Липень', 8 => 'Серпень', 9 => 'Вересень',
            10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'
        ];
        return $months[$month] ?? '';
    }

    private static function cleanup($chatId)
    {
        $messageIds = TelegramConversation::getMessages($chatId);
        foreach ($messageIds as $msgId) {
            Telegram::deleteMessage($chatId, $msgId);
        }
        TelegramConversation::clear($chatId);
    }
}
