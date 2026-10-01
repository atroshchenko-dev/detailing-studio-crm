# Деплой на cPanel - Інструкція

## Крок 1: Підготовка

### 1.1 Перевірте вимоги хостингу
- PHP 8.0+
- MySQL 5.7+
- SSL підтримка
- SSH/Terminal доступ (опціонально)

### 1.2 Підготовка файлів
1. Завантажте всі файли проекту
2. Відредагуйте `config/config.php` з вашими даними
3. Видаліть зайві файли (якщо є)

## Крок 2: Налаштування cPanel

### 2.1 Створення піддомену
```
1. Увійдіть в cPanel
2. Знайдіть "Subdomains"
3. Створіть піддомен:
   - Subdomain: crm (або будь-який інший)
   - Domain: yourdomain.com
   - Document Root: public_html/crm
```

### 2.2 Створення бази даних
```
1. Знайдіть "MySQL Databases"
2. Створіть нову базу даних:
   - Database name: username_detalstudio
3. Створіть користувача:
   - Username: username_detaladmin
   - Password: [складний пароль]
4. Надайте права користувачу:
   - ALL PRIVILEGES
```

### 2.3 Імпорт бази даних
```
1. Знайдіть "phpMyAdmin"
2. Виберіть створену базу даних
3. Перейдіть до вкладки "Import"
4. Завантажте файл config/database.sql
5. Натисніть "Go"
```

## Крок 3: Налаштування файлів

### 3.1 Конфігурація бази даних
Відредагуйте `config/config.php`:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'username_detalstudio');
define('DB_USER', 'username_detaladmin');
define('DB_PASS', 'your_strong_password');
define('DB_CHARSET', 'utf8mb4');

// Telegram Bot Configuration
define('TELEGRAM_BOT_TOKEN', 'your_bot_token_here');
define('TELEGRAM_GROUP_CHAT_ID', 'your_group_id_here');
define('TELEGRAM_WEBHOOK_URL', 'https://crm.yourdomain.com/telegram-webhook.php');

// Google API Configuration
define('GOOGLE_SERVICE_ACCOUNT_JSON', 'your_google_service_account_json');
define('GOOGLE_CALENDAR_ID', 'primary');
define('GOOGLE_DRIVE_FOLDER_ID', 'your_drive_folder_id');

// Application Settings
define('APP_NAME', 'DetalStudio');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://crm.yourdomain.com');
define('TIMEZONE', 'Europe/Kiev');
define('SESSION_LIFETIME', 86400);

// Other settings...
```

### 3.2 Налаштування прав доступу
```bash
# Зробіть директорію uploads доступною для запису
chmod 755 /home/username/public_html/crm/uploads

# Налаштуйте права на файли
find /home/username/public_html/crm -type f -exec chmod 644 {} \;
find /home/username/public_html/crm -type d -exec chmod 755 {} \;
```

## Крок 4: Налаштування Telegram Bot

### 4.1 Створення бота
```
1. Напишіть @BotFather в Telegram
2. Використайте команду /newbot
3. Вкажіть назву та username бота
4. Збережіть отриманий токен
```

### 4.2 Отримання Group ID
```
1. Додайте бота в вашу групу
2. Напишіть в групі /start
3. Перейдіть за посиланням:
   https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
4. Знайдіть "chat":{"id":-100xxxxxxxxxx
5. Збережіть цей ID
```

### 4.3 Налаштування вебхука
```bash
# Встановіть вебхук
curl -F "url=https://crm.yourdomain.com/telegram-webhook.php" \
     https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook

# Перевірте статус
curl https://api.telegram.org/botYOUR_BOT_TOKEN/getWebhookInfo
```

## Крок 5: Налаштування Google API

### 5.1 Створення проекту
```
1. Перейдіть до https://console.cloud.google.com
2. Створіть новий проект
3. Увімкніть Google Drive API та Google Calendar API
```

### 5.2 Створення сервісного акаунту
```
1. Перейдіть до "Credentials"
2. Натисніть "Create Credentials" → "Service Account"
3. Вкажіть ім'я та опис
4. Завантажте JSON ключ
5. Збережіть вміст JSON в config
```

### 5.3 Налаштування доступу
```
1. Відкрийте Google Drive
2. Створіть папку для проекту
3. Поділіться папкою з сервісним акаунтом
4. Збережіть ID папки
```

## Крок 6: Налаштування Cron Jobs

### 6.1 Сповіщення про дедлайни
```
# Перейдіть до "Cron Jobs" в cPanel
# Додайте нове завдання:

Command: /usr/local/bin/php -f /home/username/public_html/cron/check_deadlines.php
Interval: Кожні 15 хвилин
```

### 6.2 Очищення старих логів
```
Command: /usr/local/bin/php -f /home/username/public_html/cron/cleanup_logs.php
Interval: Щодня о 3:00
```

## Крок 7: Перевірка

### 7.1 Перевірка бази даних
```
1. Перейдіть до phpMyAdmin
2. Перевірте, чи всі таблиці створені
3. Перевірте, чи є користувач admin
```

### 7.2 Перевірка сайту
```
1. Перейдіть до https://crm.yourdomain.com
2. Спробуйте увійти:
   - Логін: admin
   - Пароль: admin123
3. Змініть пароль після першого входу
```

### 7.3 Перевірка Telegram
```
1. Напишіть боту /start
2. Перевірте, чи прийшло повідомлення в групу
3. Перевірте вебхук: getWebhookInfo
```

## Розв'язання проблем

### Помилка підключення до бази даних
```
1. Перевірте налаштування в config/config.php
2. Перевірте, чи користувач має права
3. Перевірте, чи правильний хост
```

### Помилка 500 (Internal Server Error)
```
1. Перевірте логи помилок в cPanel
2. Перевірте права на файли
3. Перевірте синтаксис PHP
```

### Telegram бот не працює
```
1. Перевірте токен бота
2. Перевірте вебхук
3. Перевірте, чи бот доданий в групу
4. Перевірте логи
```

### Google Drive не працює
```
1. Перевірте JSON ключ
2. Перевірте, чи API увімкнено
3. Перевірте права доступу до папки
4. Перевірте логи
```

## Безпека

### SSL сертифікат
```
1. Перейдіть до "SSL/TLS" в cPanel
2. Увімкніть "Force HTTPS Redirect"
3. Перевірте, чи сайт відкривається по HTTPS
```

### Захист директорій
```
1. Створіть файл .htaccess в директорії uploads:
   php_flag engine off
   <FilesMatch "\.(php|php\.|php3|php4|phtml|pl|py|jsp|asp|aspx|cgi|sh|bash)$">
       ForceType text/plain
   </FilesMatch>

2. Захистіть директорію config:
   <Files "config.php">
       Require all denied
   </Files>
```

### Резервне копіювання
```
1. Налаштуйте автоматичне резервне копіювання в cPanel
2. Або використовуйте cron для експорту бази даних:
   
   mysqldump -u username_detaladmin -p'password' username_detalstudio > /home/username/backups/backup_$(date +%Y%m%d).sql
```

## Оновлення системи

### Перед оновленням
```
1. Зробіть резервну копію бази даних
2. Зробіть резервну копію файлів
3. Перевірте changelog
```

### Після оновлення
```
1. Оновіть базу даних (якщо потрібно)
2. Оновіть конфігурацію (якщо потрібно)
3. Перевірте права на файли
4. Перевірте роботу системи
```

---

**Успішного деплою!** 🚀