<?php

/**
 * DetalStudio - System Settings
 * Налаштування системи
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\CSRF;
use App\Models\Setting;
use App\Flash;



Auth::requireAuth();
Auth::requireRole('director');

$pageTitle = 'Налаштування системи';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();

    $settings = [
        'company_name',
        'currency_symbol',
        'items_per_page',
        'telegram_bot_token',
        'telegram_group_chat_id',
        'telegram_notifications',
        'telegram_notify_new_order',
        'telegram_notify_order_status',
        'telegram_notify_new_task',
        'telegram_notify_task_status',
        'feature_stalls',
        'feature_tasks',
        'feature_warehouse',
        'feature_photo_gallery',
        'feature_executor_payouts',
        'work_day_start',
        'work_day_end'
    ];

    $success = true;
    foreach ($settings as $key) {
        $value = $_POST[$key] ?? '';
        // Handle checkboxes
        if (strpos($key, 'notify') !== false || $key === 'telegram_notifications' || strpos($key, 'feature_') === 0) {
            $value = isset($_POST[$key]) ? '1' : '0';
        }

        if (!Setting::set($key, $value)) {
            $success = false;
        }
    }

    if ($success) {
        Flash::success('Налаштування збережено');
        redirect('/admin/settings.php');
    } else {
        Flash::error('Помилка при збереженні налаштувань');
    }
}

$allSettings = [];
foreach (Setting::getAll() as $s) {
    $allSettings[$s['key']] = $s['value'];
}

// Helpers
function get_setting($key, $default = '')
{
    global $allSettings;
    return $allSettings[$key] ?? $default;
}

include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <form method="POST" action="">
            <?= CSRF::input() ?>

            <!-- General Settings -->
            <div class="card shadow-sm border-secondary mb-4">
                <div class="card-header bg-dark">
                    <h5 class="mb-0 text-accent-orange">
                        <i class="bi bi-gear me-2"></i>ЗАГАЛЬНІ
                    </h5>
                </div>
                <div class="card-body bg-dark border-top border-secondary">
                    <div class="mb-3">
                        <label class="form-label">Назва компанії</label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars(get_setting('company_name', 'DetalStudio')) ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Символ валюти</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars(get_setting('currency_symbol', '₴')) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Елементів на сторінці (пагінація)</label>
                            <input type="number" name="items_per_page" class="form-control" value="<?= htmlspecialchars(get_setting('items_per_page', '20')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Work Schedule Settings -->
            <div class="card shadow-sm border-secondary mb-4">
                <div class="card-header bg-dark">
                    <h5 class="mb-0 text-success">
                        <i class="bi bi-clock me-2"></i>РОБОЧИЙ ГРАФІК
                    </h5>
                </div>
                <div class="card-body bg-dark border-top border-secondary">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Початок робочого дня</label>
                            <input type="time" name="work_day_start" class="form-control" value="<?= htmlspecialchars(get_setting('work_day_start', '09:00')) ?>">
                            <div class="form-text text-muted">Використовується як час за замовчуванням для "Дати прибуття"</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Кінець робочого дня</label>
                            <input type="time" name="work_day_end" class="form-control" value="<?= htmlspecialchars(get_setting('work_day_end', '18:00')) ?>">
                            <div class="form-text text-muted">Використовується як час за замовчуванням для "Орієнтовної видачі"</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Telegram Notifications -->
            <div class="card shadow-sm border-secondary mb-4">
                <div class="card-header bg-dark">
                    <h5 class="mb-0 text-info">
                        <i class="bi bi-telegram me-2"></i>СПОВІЩЕННЯ TELEGRAM
                    </h5>
                </div>
                <div class="card-body bg-dark border-top border-secondary">
                    <div class="mb-3">
                        <label class="form-label">Bot Token</label>
                        <input type="text" name="telegram_bot_token" class="form-control" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" value="<?= htmlspecialchars(get_setting('telegram_bot_token', '')) ?>">
                        <div class="form-text text-muted">Токен від @BotFather</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ID Групи (Chat ID)</label>
                        <input type="text" name="telegram_group_chat_id" class="form-control" placeholder="-100xxxxxxxxxx" value="<?= htmlspecialchars(get_setting('telegram_group_chat_id', '')) ?>">
                        <div class="form-text text-muted">ID групового чату для сповіщень</div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="telegram_notifications" name="telegram_notifications" <?= get_setting('telegram_notifications') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="telegram_notifications">Увімкнути сповіщення Telegram (GLOBAL)</label>
                    </div>

                    <div class="ms-4">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Типи сповіщень</h6>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="telegram_notify_new_order" name="telegram_notify_new_order" <?= get_setting('telegram_notify_new_order') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted" for="telegram_notify_new_order">Нові замовлення</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="telegram_notify_order_status" name="telegram_notify_order_status" <?= get_setting('telegram_notify_order_status') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted" for="telegram_notify_order_status">Зміна статусу замовлення</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="telegram_notify_new_task" name="telegram_notify_new_task" <?= get_setting('telegram_notify_new_task') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted" for="telegram_notify_new_task">Нові задачі</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="telegram_notify_task_status" name="telegram_notify_task_status" <?= get_setting('telegram_notify_task_status') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted" for="telegram_notify_task_status">Зміна статусу задачі</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Features -->
            <div class="card shadow-sm border-secondary mb-4">
                <div class="card-header bg-dark">
                    <h5 class="mb-0 text-accent-cyan">
                        <i class="bi bi-cpu me-2"></i>ФУНКЦІЇ СИСТЕМИ
                    </h5>
                </div>
                <div class="card-body bg-dark border-top border-secondary">
                    <div class="alert alert-info border-info bg-dark py-2 mb-3" style="font-size: 0.85rem;">
                        <i class="bi bi-info-circle me-2"></i>Тут можна вимкнути модулі, які ви не використовуєте, щоб спростити інтерфейс.
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="feature_stalls" name="feature_stalls" <?= get_setting('feature_stalls', '1') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="feature_stalls">Графік боксів та вибір боксів у замовленнях</label>
                        <div class="form-text text-muted small">При вимкненні пункт "Графік боксів" зникне з меню, а поле вибору боксу — з форми замовлення.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="feature_tasks" name="feature_tasks" <?= get_setting('feature_tasks', '1') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="feature_tasks">Модуль "Завдання"</label>
                        <div class="form-text text-muted small">При вимкненні розділ "Задачі" зникне з меню, а функція створення задач — з форми замовлення.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="feature_warehouse" name="feature_warehouse" <?= get_setting('feature_warehouse', '1') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="feature_warehouse">Модуль "Склад та Закупівлі"</label>
                        <div class="form-text text-muted small">При вимкненні розділи "Склад" та "Закупівлі" будуть приховані.</div>
                    </div>


                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="feature_photo_gallery" name="feature_photo_gallery" <?= get_setting('feature_photo_gallery', '1') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="feature_photo_gallery">Фотогалерея замовлень</label>
                        <div class="form-text text-muted small">При вимкненні блок фотогалереї не буде відображатися на сторінці перегляду замовлення.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="feature_executor_payouts" name="feature_executor_payouts" <?= get_setting('feature_executor_payouts', '1') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="feature_executor_payouts">Система виплат виконавцям ("Закрити день")</label>
                        <div class="form-text text-muted small">Вмикає поля для вказання виплати при створенні задачі та функцію "Закрити день" в звітах і боті.</div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 mb-5">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save me-2"></i>Зберегти налаштування
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>