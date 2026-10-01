CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT,
    `description` VARCHAR(255),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT IGNORE INTO settings (`key`, `value`, `description`) VALUES
('company_name', 'DetalStudio', 'Назва компанії'),
('currency_symbol', '₴', 'Символ валюти'),
('items_per_page', '20', 'Кількість елементів на сторінці'),
('telegram_notifications', '1', 'Увімкнути сповіщення Telegram'),
('telegram_notify_new_order', '1', 'Сповіщати про нові замовлення'),
('telegram_notify_order_status', '1', 'Сповіщати про зміну статусу замовлення'),
('telegram_notify_new_task', '1', 'Сповіщати про нові задачі'),
('telegram_notify_task_status', '1', 'Сповіщати про зміну статусу задачі');
