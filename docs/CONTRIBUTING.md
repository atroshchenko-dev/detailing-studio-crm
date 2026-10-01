# Contributing — DetalStudio

Гайд для розробників, що працюють з проектом.

## Технологічний стек

- **PHP 8.0+** — без фреймворку (custom MVC)
- **MySQL 8.0+** — PDO, prepared statements
- **Composer** — залежності та autoload (PSR-4)
- **jQuery** + vanilla JS — фронтенд
- **Bootstrap 5** — CSS фреймворк
- **FullCalendar** — календарні view

---

## Початок роботи

```bash
# 1. Клонуйте репозиторій
git clone <repo-url>
cd detalstudio

# 2. Встановіть залежності
composer install

# 3. Налаштуйте оточення
cp .env.example .env
# Відредагуйте .env

# 4. Імпортуйте БД
mysql -u root -p your_db < config/database.sql

# 5. Запустіть міграції
php migrate.php

# 6. Запустіть (Docker)
docker-compose up -d
```

---

## Структура коду

```
includes/                  # 🧠 Ядро (namespace App\)
├── Models/                # Робота з БД (Active Record style)
├── Controllers/           # HTTP обробники
├── Services/              # Складна бізнес-логіка
├── Events/                # Domain events + EventDispatcher
├── Listeners/             # Реакції на події
├── Traits/                # Telegram traits + Order traits
└── Utils/                 # Helper functions
```

---

## Coding Standards

### Загальні правила

| Правило | Стандарт |
|---------|---------|
| Кодування | UTF-8 |
| Відступи | 4 пробіли (не табуляції) |
| Кінець рядка | LF |
| Namespace | PSR-4: `App\` → `includes/` |
| Іменування класів | PascalCase |
| Іменування методів | camelCase |
| Іменування констант | UPPER_SNAKE_CASE |
| Іменування таблиць БД | snake_case, множина |
| Іменування колонок БД | snake_case |

### PHP

```php
<?php

declare(strict_types=1);

namespace App\Models;

/**
 * PHPDoc для кожного публічного методу
 */
class Example
{
    /**
     * Отримати запис за ID
     *
     * @param int $id ID запису
     * @return array|null Масив даних або null
     */
    public static function getById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT * FROM examples WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
```

### SQL

```php
// ✅ Правильно — prepared statements
$stmt = db()->prepare("SELECT * FROM orders WHERE status = ? AND client_id = ?");
$stmt->execute([$status, $clientId]);

// ❌ Неправильно — SQL injection
$result = db()->query("SELECT * FROM orders WHERE status = '$status'");
```

### XSS Protection

```php
// ✅ У views — завжди escape
<p><?= e($user['name']) ?></p>

// ❌ Неправильно
<p><?= $user['name'] ?></p>
```

### CSRF

```php
// HTML форми
<form method="POST">
    <?= Csrf::input() ?>
    ...
</form>

// API endpoints
Csrf::verifyApi();
```

---

## Додавання нових функцій

### 1. Нова модель

1. Створіть `includes/Models/NewModel.php`
2. Namespace: `App\Models`
3. Додайте модель до `includes/aliases.php`
4. Створіть міграцію в `config/migrations/`

### 2. Новий API endpoint

1. Створіть `api/new_endpoint.php`
2. Обов'язково: `require config.php`, `Auth::requireAuth()`, `Csrf::verifyApi()` (для POST)
3. Header: `Content-Type: application/json`
4. Обробка помилок: catch + `error_log()` + generic JSON response

### 3. Нова подія (Event)

1. Створіть `includes/Events/NewEvent.php` (extends `Event`)
2. Зареєструйте слухачів у `includes/events_init.php`
3. Диспатчте через `EventDispatcher::dispatch(new NewEvent(...))`

### 4. Нова міграція

1. Створіть `config/migrations/NNN_description.sql`
2. Номер — наступний за останнім (перевірте існуючі!)
3. Запустіть `php migrate.php`

### 5. Нова команда Telegram бота

1. Додайте константу в `includes/BotConstants.php`
2. Додайте обробник в `includes/Traits/TelegramCommands.php`
3. Додайте маршрутизацію в `includes/Traits/TelegramWebhook.php`

---

## Перевірка коду

### PHPStan (Static Analysis)

```bash
composer analyze
# або
vendor/bin/phpstan analyse --memory-limit=1G
```

### Code Style (PHP_CodeSniffer)

```bash
composer cs-check    # Перевірка
composer cs-fix      # Автовиправлення
```

### PHPUnit (Тести)

```bash
composer test
# або
vendor/bin/phpunit
```

---

## Git Workflow

### Гілки

| Гілка | Призначення |
|-------|-------------|
| `main` | Стабільна продакшн версія |
| `develop` | Поточна розробка |
| `feature/*` | Нові функції |
| `fix/*` | Баг-фікси |

### Commit Messages

Формат: `[Тип] Короткий опис`

```
[Feature] Add executor payout history
[Fix] Correct commission calculation for closed tasks
[Refactor] Extract OrderItems trait from Order model
[Security] Remove display_errors from migration scripts
[Docs] Update API reference for new endpoints
```

### Перед commit

1. `composer analyze` — без помилок
2. `composer cs-check` — без порушень стилю
3. `composer test` — всі тести проходять
4. Перевірте `.env` — не коммітьте secrets!

---

## Важливі правила

> ⚠️ **НІКОЛИ** не коммітьте `.env` файли. Використовуйте `.env.example`.

> ⚠️ **ЗАВЖДИ** використовуйте prepared statements для SQL запитів.

> ⚠️ **ЗАВЖДИ** escape HTML output у views через `e()` або `htmlspecialchars()`.

> ⚠️ Оновлюйте `CHANGELOG.md` при значних змінах.

> ⚠️ Нові Telegram кнопки **обов'язково** додавайте через `BotConstants`.

---

## Корисні ресурси

| Ресурс | Посилання |
|--------|-----------|
| Архітектура | [docs/ARCHITECTURE.md](ARCHITECTURE.md) |
| База даних | [docs/DATABASE.md](DATABASE.md) |
| API Reference | [docs/API_REFERENCE.md](API_REFERENCE.md) |
| Telegram Bot | [docs/TELEGRAM_BOT.md](TELEGRAM_BOT.md) |
| Моделі | [docs/MODELS.md](MODELS.md) |
| Модулі | [docs/MODULES.md](MODULES.md) |
