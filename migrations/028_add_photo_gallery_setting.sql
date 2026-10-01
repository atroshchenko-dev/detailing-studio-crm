-- Додавання налаштування для вмикання/вимикання фотогалереї

-- Перевірка чи існує таблиця settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Додаємо налаштування для фотогалереї
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('feature_photo_gallery', '1', 'Вмикати/вимикати фотогалерею на сторінці замовлення (1 - увімкнено, 0 - вимкнено)')
ON DUPLICATE KEY UPDATE `description` = 'Вмикати/вимикати фотогалерею на сторінці замовлення (1 - увімкнено, 0 - вимкнено)';
