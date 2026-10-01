-- Force UTF8 for connection
SET NAMES utf8mb4;

-- Fix users
UPDATE users SET full_name = 'Адміністратор' WHERE username = 'admin';

-- Fix services
UPDATE services SET name = 'Мийка кузова', description = 'Комплексна мийка зовнішньої частини автомобіля' WHERE id = 1;
UPDATE services SET name = 'Хімчистка салону', description = 'Глибока хімчистка всіх поверхонь салону' WHERE id = 2;
UPDATE services SET name = 'Полірування кузова', description = 'Повне полірування з захисним покриттям' WHERE id = 3;
UPDATE services SET name = 'Керамічне покриття', description = 'Нанесення керамічного покриття кузова' WHERE id = 4;
UPDATE services SET name = 'Чорніння шин', description = 'Обробка шин спеціальним складом' WHERE id = 5;

-- Fix warehouse
UPDATE warehouse_items SET name = 'Шампунь для мийки', unit = 'л' WHERE id = 1;
UPDATE warehouse_items SET name = 'Воск для полірування', unit = 'л' WHERE id = 2;
UPDATE warehouse_items SET name = 'Мікрофібра', unit = 'шт' WHERE id = 3;
UPDATE warehouse_items SET name = 'Чорніння шин', unit = 'л' WHERE id = 4;
