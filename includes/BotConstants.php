<?php

declare(strict_types=1);

namespace App;

/**
 * Централізовані константи UI Telegram бота
 *
 * Всі тексти кнопок визначені тут для унифікації між
 * відправкою клавіатури та обробкою callback-даних.
 *
 * @package App
 */
class BotConstants
{
    // Common Menu
    public const BTN_NEW_ORDER       = '➕ Нове замовлення';
    public const BTN_NEW_TASK        = '📝 Нова задача';
    public const BTN_MY_TASKS        = '📋 Активні задачі';
    
    // Executor Custom Menu
    public const BTN_MY_EARNINGS     = '📊 Мій заробіток';
    public const BTN_MY_PAYOUTS      = '💳 Історія виплат';

    // Manager / Director Menu
    public const BTN_ACTIVE_ORDERS   = '📦 Активні замовлення';
    public const BTN_FINANCE         = '💸 Фінанси';
    public const BTN_CLOSE_DAY       = '💸 Закрити день';
}
