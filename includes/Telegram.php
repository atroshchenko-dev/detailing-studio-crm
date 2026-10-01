<?php

declare(strict_types=1);

namespace App;

use App\Models\Task;
use Exception;
use PDO;

/**
 * Головний клас Telegram бота DetalStudio
 *
 * Використовує 4 traits для розділення відповідальності:
 * - TelegramApi — HTTP-запити до Bot API
 * - TelegramNotifications — відправка sync-сповіщень
 * - TelegramCommands — команди, меню, обробники
 * - TelegramWebhook — обробка вхідних webhook
 *
 * @package App
 */

require_once __DIR__ . '/../config/config.php';

// Manually require Wizard files to bypass potential Autoloader caching issues on hosting
require_once __DIR__ . '/TelegramConversation.php';
require_once __DIR__ . '/OrderWizard.php';
require_once __DIR__ . '/TaskWizard.php';
require_once __DIR__ . '/FinanceWizard.php';
require_once __DIR__ . '/TelegramCalendar.php';

class Telegram
{
    use Traits\TelegramApi;
    use Traits\TelegramNotifications;
    use Traits\TelegramCommands;
    use Traits\TelegramWebhook;

    public static $suppressNotificationsForChatId = null;
}
