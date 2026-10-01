# DetalStudio — Система управління автодетейлінг студією

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-Proprietary-red)
![Version](https://img.shields.io/badge/version-1.0.2-blue)

## Опис

DetalStudio — це повнофункціональна CRM/ERP веб-система для управління автодетейлінг студією. Побудована на PHP 8.0+ без важких фреймворків (custom MVC), з MySQL, інтеграціями Telegram Bot, Google Calendar/Drive, email-сповіщеннями та event-driven архітектурою.

> **Детальна документація** доступна в каталозі [`docs/`](docs/).

---

## 📑 Навігація по документації

| Документ | Опис |
|----------|------|
| 📐 [Архітектура](docs/ARCHITECTURE.md) | Компоненти, Event System, Request Lifecycle |
| 🗃️ [База даних](docs/DATABASE.md) | Схема, таблиці, міграції, ER-діаграма |
| 🤖 [Telegram Bot](docs/TELEGRAM_BOT.md) | Команди, Wizards, Notifications, Webhook |
| 🔌 [API Reference](docs/API_REFERENCE.md) | Всі AJAX ендпоінти |
| 📦 [Моделі](docs/MODELS.md) | Domain layer, бізнес-логіка |
| 🧩 [Модулі](docs/MODULES.md) | Функціональні модулі та сторінки |
| ⏰ [Cron Jobs](docs/CRON_JOBS.md) | Планувальник завдань |
| 🚀 [Деплой](DEPLOYMENT.md) | Інструкція розгортання (cPanel, Docker) |
| 📋 [Керівництво](PROJECT_MANUAL.md) | Стислий огляд для користувачів та розробників |
| 🤝 [Contributing](docs/CONTRIBUTING.md) | Гайд для розробників |
| 📝 [Changelog](CHANGELOG.md) | Історія змін |

---

## Функціональність

### 🎫 Управління замовленнями
- Створення та редагування замовлень з конструктором послуг
- Гнучке ціноутворення (базова ціна, кастомна ціна, знижки)
- Облік платежів (готівка, карта, ФОП) з підтримкою часткових оплат
- Kanban-дошка для візуалізації процесу
- Фотогалерея замовлення (до/після/процес) з Google Drive синхронізацією
- Статуси: `Нове` → `Погоджено` → `В роботі` → `Очікує клієнта` → `Закрито` / `Скасовано`
- Коментарі, історія змін (аудит-лог), експорт

### 📋 Управління задачами
- Задачі прив'язані до замовлення або автономні
- **Автономна прибутковість** — задачі можуть генерувати дохід і вести облік витрат без замовлення
- Призначення виконавців, дедлайнів, пріоритетів
- Kanban-дошка та календар задач
- Комісії виконавців та виплати
- Автоматичне створення задач при додаванні послуг
- Статуси: `Створено` → `Прийнято` → `В роботі` → `Пауза` → `Готово` → `Переробка` → `Закрито`

### 🤖 Telegram Bot інтеграція
- **Сповіщення** про замовлення, задачі, фінанси в окремі топіки (threads) супергрупи
- **Інтерактивні кнопки** для зміни статусів прямо з чату
- **Wizards** — покрокове створення замовлень і задач через бота (OrderWizard, TaskWizard, FinanceWizard)
- **Команди**: `/my_tasks`, `/my_orders`, `📊 Мій заробіток`, `💸 Закрити день`, `💳 Історія виплат`
- **Inline-календар** для вибору дат
- Пагінація списків (5 елементів на сторінку)
- Підтримка приватних повідомлень виконавцям

### 📦 Управління складом
- Номенклатура: матеріали, витратники, інструменти
- Кількісний облік з транзакціями (прихід/списання/корекція)
- Автоматичне списання по задачах
- Мінімальні залишки зі сповіщеннями
- Зв'язок з постачальниками

### 🛒 Закупівлі
- Повний lifecycle: `Заявка` → `Погоджено` → `Замовлено` → `Отримано` → `Закрито`
- Довідник постачальників
- Автоматичне оприбуткування на склад при отриманні

### 💰 Фінанси та звітність
- **Dashboard**: оперативна зведка, KPI-картки, графіки за 6 місяців, топ послуг/клієнтів
- **Звіти**: по замовленнях, фінансах, задачах, ефективності виконавців
- **Виплати**: облік комісій виконавців, функція "Закрити день"
- **Витрати**: облік інших бізнес-витрат
- Аналітика маржі та собівартості
- Фільтри та експорт (CSV, XLSX)

### 📁 Google Drive інтеграція
- Автоматичне створення папок для замовлень
- Завантаження та зберігання фото (Service Account)
- Структурована організація файлів

### 📅 Google Calendar інтеграція
- Синхронізація дедлайнів замовлень і задач
- Автоматичне створення/оновлення/видалення подій
- Нагадування про терміни

### 🏗️ Event-Driven Architecture
- Централізований `EventDispatcher`
- 13 типів подій (Order*, Task*, Backup*, Deadline*)
- 3 слухачі: `TelegramListener`, `GoogleCalendarListener`, `TaskCompletionListener`
- Підтримка декількох слухачів на одну подію

### 🛡️ Безпека
- CSRF-захист на всіх POST-запитах
- XSS-захист (helper `e()`)
- Підготовлені SQL-запити (PDO)
- Rate limiting на публічних ендпоінтах
- Захист session (httponly, secure, samesite)
- Security headers (HSTS, X-Frame-Options, X-Content-Type-Options)
- Telegram webhook secret validation

### 🛠️ Додаткові інструменти
- Веб-інтерфейс резервного копіювання (БД + файли → Google Drive)
- Система міграцій БД (38 міграцій)
- Аудит-лог всіх дій користувачів
- Глобальний пошук (замовлення, клієнти, авто, задачі)
- Публічне бронювання (booking.php)

---

## Ролі та права доступу

| Функція | 👑 Директор | 👨‍💼 Менеджер | 🔧 Виконавець |
|---------|:-----------:|:-----------:|:-------------:|
| Замовлення (CRUD) | ✅ | ✅ | ❌ |
| Видалення замовлень | ✅ | ❌ | ❌ |
| Задачі (створення) | ✅ | ✅ | ❌ |
| Мої задачі | ✅ | ✅ | ✅ |
| Зміна статусу задач | ✅ | ✅ | ✅ (свої) |
| Склад та закупівлі | ✅ | ✅ | ❌ |
| Погодження закупівель | ✅ | ❌ | ❌ |
| Фінансові звіти | ✅ | ⚠️ обмежено | ❌ |
| Виплати / Закрити день | ✅ | ✅ | ❌ |
| Мій заробіток | — | — | ✅ |
| Налаштування системи | ✅ | ❌ | ❌ |
| Бекапи та аудит | ✅ | ❌ | ❌ |

---

## Швидкий старт

### 🐳 Docker (рекомендовано)

```bash
git clone https://github.com/YourBugsBynny/detal-studio.git
cd detalstudio

# Скопіюйте та налаштуйте .env
cp .env.example .env
# Відредагуйте .env — вкажіть дані БД, Telegram, Google API

docker-compose up -d
```

| Сервіс | Порт | Опис |
|--------|------|------|
| app | 8080 | PHP + Apache |
| db | 33060 | MySQL 8.0 |
| phpmyadmin | 8081 | Адміністрування БД |
| worker | — | Фоновий обробник черги |

### Ручне встановлення

Детальні інструкції: [DEPLOYMENT.md](DEPLOYMENT.md) | [Shared Hosting](SHARED_HOSTING_DEPLOYMENT.md)

1. **PHP 8.0+** з розширеннями: `pdo`, `pdo_mysql`, `mbstring`, `curl`, `gd`, `zip`, `xml`
2. **MySQL 8.0+** або MariaDB 10.5+
3. **Composer**: `composer install`
4. Скопіюйте `.env.example` → `.env` та заповніть
5. Імпортуйте `config/database.sql`
6. Відкрийте сайт — логін `admin` / пароль `admin123`

---

## Структура проекту

```
detalstudio/
├── api/                        # AJAX ендпоінти (18 файлів)
├── admin/                      # Адміністрування (налаштування, аудит, бекапи, стійла)
├── assets/                     # Статичні ресурси
│   ├── css/main.css           # Основні стилі
│   ├── js/                    # JavaScript (main.js, dashboard.js, clients.js)
│   └── images/                # Зображення
├── calendar/                   # Модуль календаря (index, timeline)
├── clients/                    # Модуль клієнтів (CRUD + авто)
├── config/                     # Конфігурація
│   ├── config.php             # Головна конфігурація (завантажує .env)
│   ├── database.sql           # Початкова схема БД
│   ├── migrations/            # SQL/PHP міграції (38 файлів)
│   ├── credentials.json       # OAuth credentials
│   └── google_service_account.json
├── cron/                       # Планувальник (worker, deadlines, backups, reports)
├── docs/                       # Детальна документація
├── expenses/                   # Модуль витрат
├── includes/                   # 🧠 Ядро системи
│   ├── Controllers/           # Контролери (OrderController, WarehouseController)
│   ├── Events/                # Системні події (13 класів + EventDispatcher)
│   ├── Listeners/             # Слухачі подій (Telegram, Google, TaskCompletion)
│   ├── Models/                # Моделі даних (12 класів: Order, Task, Client...)
│   ├── Services/              # Сервісний шар (OrderService, TaskService)
│   ├── Traits/                # PHP Traits (TelegramApi, Commands, Webhook, Notifications, Order*)
│   ├── Utils/                 # Допоміжні утиліти (Helper)
│   ├── Auth.php               # Аутентифікація та авторизація
│   ├── Audit.php              # Аудит-лог
│   ├── BotConstants.php       # Константи UI Telegram-бота
│   ├── Database.php           # Singleton PDO-підключення
│   ├── FinanceWizard.php      # Wizard фінансових операцій (Telegram)
│   ├── OrderWizard.php        # Wizard створення замовлень (Telegram)
│   ├── TaskWizard.php         # Wizard створення задач (Telegram)
│   ├── Telegram.php           # Головний клас бота (використовує Traits)
│   ├── TelegramCalendar.php   # Inline-календар для Telegram
│   ├── TelegramConversation.php # Управління станом діалогів
│   ├── GoogleIntegration.php  # Google Calendar + Drive
│   ├── Reports.php            # Генерація звітів
│   ├── Logger.php             # Monolog wrapper
│   ├── Security.php           # Security headers
│   ├── Csrf.php               # CSRF-токени
│   ├── Validator.php          # Валідація вводу
│   ├── NotificationQueue.php  # Черга сповіщень
│   ├── events_init.php        # Реєстрація слухачів подій
│   └── aliases.php            # Аліаси класів для зворотної сумісності
├── migrations/                 # Кореневі міграції (legacy)
├── orders/                     # Модуль замовлень (index/Kanban, edit, view)
├── payments/                   # Модуль виплат
├── purchases/                  # Модуль закупівель
├── reports/                    # Модуль звітів (earnings, executors, profit)
├── services/                   # Модуль послуг (CRUD + категорії)
├── tasks/                      # Модуль задач (index/Kanban, view, calendar)
├── tracking/                   # Модуль трекінгу часу
├── users/                      # Модуль користувачів
├── views/                      # Шаблони
│   ├── layout/                # Header, Footer
│   ├── components/            # Переиспользовувані компоненти
│   │   └── dashboard/         # Dashboard компоненти (5 файлів)
│   ├── errors/                # Сторінки помилок
│   └── reports/               # Шаблони звітів
├── warehouse/                  # Модуль складу + постачальники
├── tools/                      # Утиліти (setup, testing)
├── scripts/                    # Скрипти (setup Telegram menu)
├── tests/                      # PHPUnit тести
├── uploads/                    # Завантажені файли
├── logs/                       # Логи (Monolog)
├── backups/                    # Резервні копії
├── vendor/                     # Composer залежності
├── .env.example                # Приклад змінних оточення
├── composer.json               # PHP залежності
├── telegram-webhook.php        # Точка входу Telegram webhook
├── booking.php                 # Публічне бронювання
├── index.php                   # Dashboard (головна)
├── login.php                   # Авторизація
├── profile.php                 # Профіль користувача
└── migrate.php                 # Запуск міграцій
```

---

## Технічний стек

| Компонент | Технологія |
|-----------|------------|
| Backend | PHP 8.0+ (custom MVC) |
| Database | MySQL 8.0 / MariaDB 10.5+ |
| Web Server | Apache / Nginx |
| Dependencies | Composer |
| Telegram | Bot API (direct HTTP) |
| Google | `google/apiclient` v2.15+ |
| Logging | `monolog/monolog` v3.10+ |
| PDF | `mpdf/mpdf` v8.2+ |
| Email | `phpmailer/phpmailer` v6.8+ |
| Environment | `vlucas/phpdotenv` v5.6+ |
| Static Analysis | `phpstan/phpstan` v1.10+ |
| Code Style | `squizlabs/php_codesniffer` v3.7+ |
| Testing | `phpunit/phpunit` v10+ |

---

## Підтримка

При виникненні проблем:
1. Перевірте логи: `logs/app.log`
2. Переконайтесь у коректності `.env` налаштувань
3. Перевірте підключення до БД
4. Дивіться [Troubleshooting](PROJECT_MANUAL.md#4-troubleshooting-вирішення-проблем) у PROJECT_MANUAL

---

## Ліцензія

Пропрієтарне програмне забезпечення. Розроблено для внутрішнього використання.

---

**DetalStudio v1.0.2** — Система управління автодетейлінг студією