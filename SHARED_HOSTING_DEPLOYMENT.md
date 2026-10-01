# 🚀 DetalStudio - Інструкція з розгортання на Shared Hosting (cPanel)

## ✅ Варіант: "Повний Пакет" (Vendor включено)

Ця інструкція адаптована для хостингу, де немає доступу до SSH або Composer.
Залежності вже включені в архів.

### 📋 Покрокова інструкція розгортання

### 1️⃣ Завантаження архіву

Я підготував для вас повний архів:
**Файл:** `c:\Ant\detalstudio\detalstudio_full.zip` (~260+ MB)
*Включає папку `vendor/`*

1. Завантажте цей файл через **cPanel File Manager**.
   - Перейдіть у папку `public_html` (або створіть підпапку, наприклад `crm`).
   - Натисніть **Upload** і виберіть `detalstudio_full.zip`.

### 2️⃣ Розпакування

1. У cPanel File Manager виберіть файл `detalstudio_full.zip`.
2. Натисніть **Extract** (ПКМ -> Extract).
3. Після розпакування **видаліть архів** `detalstudio_full.zip`, щоб звільнити місце.

---

### 3️⃣ Налаштування бази даних

1. **Створити базу даних**:
   - cPanel -> MySQL Databases.
   - Створити нову БД.
   - Створити користувача і додати його до БД (надати всі права).

2. **Імпортувати структуру**:
   - cPanel -> phpMyAdmin.
   - Виберіть вашу нову базу.
   - Натисніть **Import**.
   - Виберіть файл з папки проєкту: `config/database.sql`.
   - Натисніть **Go**.

---

### 4️⃣ Конфігурація

1. Знайдіть файл `config/config.php`.
2. Натисніть **Edit**.
3. Змініть налаштування під хостинг:

```php
// Налаштування БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'ваша_назва_бд');
define('DB_USER', 'ваш_користувач');
define('DB_PASS', 'ваш_пароль');

// Адреса сайту
define('APP_URL', 'https://yourdomain.com'); 

// Webhook (якщо є SSL)
define('TELEGRAM_WEBHOOK_URL', 'https://yourdomain.com/telegram-webhook.php');
```

---

### 5️⃣ Права доступу (Permissions)

Переконайтеся, що папки мають права запису (755):
- `uploads/`
- `backups/`
- `logs/`

---

### 6️⃣ Cron Jobs (Планувальник)

Для роботи сповіщень та календаря потрібно налаштувати Cron.

cPanel -> **Cron Jobs**:

**Раз на хвилину (* * * * *):**
```bash
/usr/local/bin/php /home/username/public_html/cron/worker.php > /dev/null 2>&1
```
*(Шлях до PHP може відрізнятися, уточніть у хостера або використовуйте `php`)*

---

### 7️⃣ SSL (HTTPS)

1. cPanel -> **SSL/TLS Status**.
2. Run AutoSSL.
3. Перевірте, що сайт відкривається через `https://`.

---

### 📞 Troubleshooting

**Помилка 500?**
- Перевірте версію PHP у cPanel -> **Select PHP Version**. Потрібна **8.0 або вище**.
- Перевірте права на папки (755) та файли (644).

**Google Calendar не працює?**
- Перевірте файл `config/google_service_account.json`. Він має бути на сервері (архів його містить).

---

## 🎉 Готово!

Тепер система повністю готова до роботи без використання терміналу!
