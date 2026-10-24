# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

DetalStudio: a CRM/ERP for a car-detailing studio (orders, tasks, clients and cars, services, warehouse, purchases, payments, salary/commission reports). Custom PHP MVC without a framework, MySQL via PDO, a Telegram bot as a second UI, and Google Calendar/Drive sync. UI text, comments and docs are in Ukrainian; keep new user-facing strings in Ukrainian. Long-form docs live in [docs/](docs/) (architecture, DB schema, bot, API, cron) and [PROJECT_MANUAL.md](PROJECT_MANUAL.md); read the relevant one before a larger change.

## Commands

```bash
composer install          # dependencies, including dev tools
composer test             # PHPUnit (tests/Unit), no database needed
php migrate.php           # apply new SQL files from migrations/ (needs a configured .env and DB)
```

`composer analyze` (PHPStan) and `composer cs-check` (PHPCS) are declared in `composer.json`, but the repo has no `phpstan.neon` or `phpcs.xml`, so neither runs usefully as is. Don't report them as passing; add a config first if a task needs them.

CI ([.github/workflows/tests.yml](.github/workflows/tests.yml)) runs `composer validate`, `php -l` over every PHP file, and PHPUnit on PHP 8.1 and 8.3. It does not deploy anything.

**PHP version**: `composer.json` says `>=8.0`, but the locked dependencies (Monolog 3, PHPUnit 10, phpdotenv 5.7) require **8.1+**. Treat 8.1 as the floor; don't use 8.2+ only syntax (readonly classes, DNF types) unless the hosting PHP is confirmed newer.

**composer.lock**: always change dependencies with `composer require` / `composer update <package>`, never by hand-editing `composer.json`. CI fails on a lock file that is out of sync.

## Architecture

**Request flow**: there is no router or front controller. Every page is its own PHP file under a module folder (`orders/index.php`, `warehouse/edit.php`, `api/search.php`, ...). Each one starts with `require_once __DIR__ . '/../config/config.php';`, which loads `.env`, sets security headers and the hardened session, defines all constants, registers event listeners (`includes/events_init.php`) and class aliases (`includes/aliases.php`). The page then calls `Auth::requireAuth()` / `Auth::requireAnyRole([...])`, gathers data from models, and `include`s `views/layout/header.php`, a view, and `views/layout/footer.php`. A new page follows the same shape.

**Code layout** (`App\` → `includes/`, PSR-4):

- `includes/Models/` — static Active Record style classes over PDO (`Order::getList()`, `Task::getOverdue()`). All SQL goes through prepared statements via the global `db()` helper; never interpolate request data into SQL.
- `includes/Services/`, `includes/Controllers/` — multi-step business logic (order/task creation, warehouse movements). `Order` itself is split into traits in `includes/Traits/Order*.php`.
- `includes/Events/` + `includes/Listeners/` — domain events. Models/services `EventDispatcher::dispatch(new OrderCreated(...))`; `TelegramListener` and `GoogleCalendarListener` react. The dispatcher catches listener exceptions so a Telegram or Google outage never breaks saving an order; keep side effects (messages, calendar, Drive) in listeners, not inline in models.
- `includes/Telegram.php` + `includes/Traits/Telegram*.php` — the bot: webhook handling, commands, inline keyboards, notifications to supergroup topics (`TELEGRAM_TOPIC_*`). Multi-step bot dialogs are the `*Wizard.php` classes with state in `TelegramConversation`. Entry point is [telegram-webhook.php](telegram-webhook.php); it must always answer HTTP 200, or Telegram retries the update.
- `includes/GoogleIntegration.php` — Calendar events for orders/tasks and photo upload to Drive, using a service account JSON at `config/google_service_account.json` (gitignored).
- `includes/aliases.php` — makes `Auth`, `Order`, `Csrf` and other classes usable without a namespace in pages and views. If you add a class that views need, add its alias there.
- `cron/` — CLI-only scripts (notification queue worker, deadlines, daily reports, backups, cleanup), blocked from the web by `cron/.htaccess`. Schedules are in [docs/CRON_JOBS.md](docs/CRON_JOBS.md).

**Roles**: `director` > `manager` > executors (`executor`, `mechanic`, `washer`), see `Auth::isAtLeast()`. Executors see client phones masked (`Helper::displayPhone()`); keep that rule for any new place that shows client contact data.

**Security conventions**:

- Every POST form includes `<?= Csrf::input() ?>` and its handler calls `Csrf::verify()`; AJAX endpoints in `api/` call `Csrf::verifyApi()` (reads the `X-CSRF-Token` header, set from `Csrf::meta()`).
- Escape output with `htmlspecialchars()`. The `e()` helper in `includes/Security.php` is namespaced as `App\e`, so in un-namespaced views a bare `e()` is undefined; use `htmlspecialchars()` or `\App\e()`.
- Secrets come only from `.env` (see [.env.example](.env.example)); never commit `.env`, `config/*.json` or SQL dumps (all gitignored).

**Database migrations**: plain SQL files in `migrations/`, applied in filename order by `migrate.php` and recorded in the `migrations` table. Some numbers repeat (`012_*`, `017_*`, `013_b_*`); order is by full filename, so name a new file with the next free number and make it safe to run on the live schema (no destructive changes without being asked).

## Tests

PHPUnit 10, unit tests only, in `tests/Unit/`. `tests/bootstrap.php` loads the autoloader and defines the few constants that `config/config.php` would, so tests never touch the database, session handler or network. Role and CSRF tests set `$_SESSION` directly and reset it in `tearDown()`. Anything that needs MySQL, Telegram or Google is not covered; don't add tests that call real services.

## Deployment

Production is a cPanel shared host, deployed by uploading files (see [DEPLOYMENT.md](DEPLOYMENT.md) and [SHARED_HOSTING_DEPLOYMENT.md](SHARED_HOSTING_DEPLOYMENT.md)); nothing in this repo deploys automatically. After a release that adds migrations, run `php migrate.php` on the server.
