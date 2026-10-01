-- Оновлення схеми таблиці orders
-- Зміна ENUM для колонки status

ALTER TABLE orders 
MODIFY COLUMN status ENUM('new','in_progress','ready','closed') NOT NULL DEFAULT 'new';

-- Перевірка нової схеми
DESCRIBE orders;
