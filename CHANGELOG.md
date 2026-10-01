# CHANGELOG

## [Access Control] - 2026-04-10

### 🐛 Telegram Bot & API Hotfixes
- **IDE PHP Warnings**: Resolved `Undefined type 'Auth'` in `api/search.php` by using fully qualified namespace `\App\Auth`. Added missing `@property` PHPDoc declarations for `$apiUrl` and `$suppressNotificationsForChatId` in `TelegramNotifications` and `TelegramWebhook` traits to satisfy static analysis tools.

### 🔒 Privacy & Data Protection
- **Phone Number Masking**: Implemented global phone number masking (`**********45`) for all non-manager roles (`executor`, `mechanic`, `washer`). 
  - Masked in Web UI (Order details component).
  - Masked in Global Search and Client Search API results.
  - Masked in Telegram group notifications.
  - Masked in Google Calendar event descriptions.
  - Full visibility is strictly retained for `manager` and `director` roles only.

## [Hotfix] - 2026-04-04

### 🐛 Telegram Bot Critical Fixes
- **Double `php://input` Read**: Fixed fatal crash where `handleWebhook()` tried to read `php://input` a second time after the entry script already consumed it. Input is now passed as a parameter.
- **`array_merge()` Crash**: Fixed `array_merge(): Argument #1 must be of type array, int given` caused by `TelegramConversation::get()` returning rate_limit records with integer data instead of arrays. Added `step != 'rate_limit'` filter and `is_array()` guard.
- **Wizard INSERT Conflict**: Fixed wizards (Order, Task, Finance) failing silently when a `rate_limit` record already existed for the `chat_id`. `TelegramConversation::set()` now uses `INSERT ... ON DUPLICATE KEY UPDATE` to overwrite stale rate_limit records.
- **Fatal Error Handler**: Added `register_shutdown_function` to `telegram-webhook.php` to catch and log fatal errors that previously returned raw 500 responses to Telegram.
- **Error Catching**: Changed `catch(Exception)` to `catch(Throwable)` to catch all PHP errors including `TypeError`.
- **Wizard Stale Data Blocking**: Fixed bug where users were permanently stuck on the "second date" step (silently failing). `TelegramConversation::start()` now clears context to prevent stale `order_id` values from blocking execution.
### 🌐 Network Resilience
- **cURL Connection Retry**: Added automatic retry with `FRESH_CONNECT` when outgoing requests to `api.telegram.org` timeout (blocked IPs by Imunify360). `CONNECTTIMEOUT` reduced to 3s for fast failure detection.
- **DNS Cache**: Added `CURLOPT_DNS_CACHE_TIMEOUT` to optimize DNS resolution.

### 🧹 Cleanup & Security
- **Removed from Git**: `test_webhook.php`, `tg_diag.php`, `setWebhook.php`, `git` (artifact), diagnostic log files.

## [Security Audit] - 2026-02-25

### 🔒 Security Fixes (Phase 1-2)
- **Secrets Migration**: All hardcoded secrets moved to `.env` file using `phpdotenv`
- **CSRF Protection**: Site-wide CSRF integration via meta tag + jQuery auto-header on all AJAX requests
- **API Error Leaks**: Replaced `$e->getMessage()` with generic messages across 16 API endpoints + server-side logging
- **Rate-Limiting**: IP-based rate limiting (5/10min) on public booking endpoint
- **Telegram Webhook**: Added `secret_token` validation via `X-Telegram-Bot-Api-Secret-Token`
- **Security Headers**: HTTPS redirect, HSTS (1yr), X-Frame-Options SAMEORIGIN, X-Content-Type-Options

### 🛡️ Access Control (Phase 3-5)
- **Directory Protection**: `.htaccess` deny for `/tools`, `/cron`, `/backups`, `/logs`
- **CLI-Only Cron Scripts**: All 12 cron scripts now require CLI access
- **Removed Hardcoded Backup Key**: `backup_database.php` no longer accepts `?key=backup2026` via web
- **Orphan Script Auth**: Added director-only auth to `audit_order_62.php`, `diag_audit.php`, `reset_cache.php`
- **Migration Script Guards**: CLI-only access for root-level migration scripts
- **Error Log Protection**: Blocked web access to 18+ `error_log` files via `.htaccess`
- **Session Hardening**: Added `session.cookie_secure=1`

### 📎 Input Validation (Phase 3-4)
- **Upload MIME Check**: Replaced client-supplied `$file['type']` with server-side `finfo_file()` validation
- **Extension Safety**: Fallback file extensions now use safe MIME-to-extension map instead of user-supplied `pathinfo()`
- **Date Validation**: Added YYYY-MM-DD format validation for timeline API date parameters
- **Removed `display_errors`**: Removed forced `display_errors=1` from diagnostic/migration scripts

### 🔧 Code Hardening (Phase 7-8)
- **Password Policy**: Increased `PASSWORD_MIN_LENGTH` from 6 to 8 (OWASP recommendation)
- **`dd()` Safety**: Debug helper now logs to `error_log` in web context instead of dumping to browser
- **SQL Identifier Whitelist**: `Validator::unique()` now validates `$table` and `$column` against regex
- **`migrate.php` Auth Fix**: Replaced non-existent `Auth::isAdmin()` with proper director-only guard
- **Backup Script Deprecation**: Marked `backup.php` and `backup_pdo.php` as deprecated in favor of `backup_database.php`
- **`e()` XSS Helper**: Added global escape function for convenient HTML output escaping

## [Unreleased] - 2026-03-31

### 🤖 Telegram Bot & Payouts Architecture
- **Unified Global Ledger**: Transitioned from binary task-specific 'is_paid' flags to a dynamic global balance ledger (`SUM(commissions) - SUM(salary_payments)`). This natively supports arbitrary partial payouts (advances) for executors.
- **Bot Constants & Stability**: Created `App\BotConstants` to centralize all Telegram UI strings, preventing unhandled webhooks caused by minor emoji or space mismatches.
- **Bot Pagination**: Implemented inline keyboard pagination (5 items per page) for `/my_tasks` and `/my_orders` commands to prevent Telegram chat flooding.
- **Payout History**: Added a new `💳 Історія виплат` menu button for executors, querying the `employee_payments` table to display recent salary transactions.
- **Automated Push Notifications**: The system now instantly dispatches a Telegram message to the executor detailing the credited amount and their remaining debt whenever a manager processes a payout (via Bot or Web).
- **Security**: Hardened payout subqueries with strict `payment_type = 'salary'` filtering to prevent non-salary transactions (e.g., business expenses, random bonuses) from corrupting the executor's task debt balance.

## [Legacy Updates] - 2026-03-30

### 💰 Executor Payouts & Close Day (Фінансовий облік виконавців)
- **Settings Toggle**: Added a global feature switch `feature_executor_payouts` in `admin/settings.php` to enable/disable the entire payout flow.
- **Database Architecture**: Added `commission_amount` (decimal) and `is_commission_paid` (tinyint) to the `tasks` table via safe decoupled migrations (`012` & `013`).
- **Web App**:
  - Modal `tasks/index.php` and sidebar `tasks/view.php` now support setting and editing executor commission amounts natively.
  - Added **"Закрити день"** (Close Day) modal to `reports/earnings.php`, which aggregates all unpaid, completed tasks per executive and processes the payment in one click.
  - Automatically logs processed task payouts into the global `employee_payments` registry (`/payments/` page) as a "Зарплата" (salary) type transaction.
- **Telegram Bot**:
  - Added a `task_commission` step to the `TaskWizard`, capturing commission directly from chat when the setting is active.
  - Added **"💸 Закрити день"** button to the main menu for Managers/Directors to review debts and pay executors via inline buttons.
  - Added **"📊 Мій заробіток"** specialized command explicitly for executors, displaying *Today's Earnings* and *Total Unpaid Debt*.

## [Legacy Updates] - 2026-03-28
- **Dashboard Modularization**: Decomposed monolithic `views/dashboard.php` into 5 reusable components (`today_stats.php`, `stat_cards.php`, `analytics_charts.php`, `recent_tables.php`, `top_lists.php`).
- **JS Externalization**: Extracted 400+ lines of inline JavaScript from dashboard into `assets/js/dashboard.js` for better caching and CSP compliance.
- **Task SRP Refactoring**: Extracted task completion side-effects (warehouse deduction, commission calculation, order readiness check) from `Task.php` into a dedicated `TaskCompletionListener`.

### 🐛 Bug Fixes
- **Event Dispatcher Callable Error**: Fixed PHP 8+ `TypeError` where non-static methods were passed as array callables in `events_init.php` by properly instantiating `TaskCompletionListener`.
- **Hosting Compatibility**: Reverted `filemtime()` to `time()` in asset versioning to prevent potential issues on restricted shared hosting environments (cPanel).
- **Dashboard Namespaces**: Restored global alias usage (`Order::`, `Task::`) in extracted components to match the legacy project configuration and prevent `Class not found` fatal errors.

## [Legacy Updates] - 2026-03-27

### 🚀 New Features & Enhancements
- **Standalone Task Profitability**: Tasks can now generate independent income and track direct expenses without being attached to an Order. Added formal "Closed" workflow.
- **Telegram Calendar Integration**: Interactive inline calendar added to the Telegram bot for seamless date selection and navigation without flooding the chat.
- **Browser-based Backup Management**: Developed a secure web interface to manage database and file backups directly from the browser (`backups.php`).
- **Refactored Architecture**:
  - Split monolithic `includes/models.php` into individual model files within `includes/models/` for better maintainability.
  - Extracted `Supplier` and `Expense` models from `includes/models_finance.php` and `includes/models_warehouse.php` into dedicated files.
  - Updated `includes/models.php` to act as a loader for the new structure.
- **UI/UX Improvements**:
  - **Sidebar Optimization**: 
    - Implemented a compact sidebar mode.
    - Added state persistence using Local Storage (sidebar remembers if it was open/closed).
    - Fixed transitions and layout shifts (margin adjustments) when toggling the sidebar.
    - Added tooltips for menu items in collapsed mode.
  - **Dashboard**:
    - Resolved "black screen" issue caused by Service Worker/caching conflicts.
    - Ensured correct rendering of the analytics chart (`index.php`).
  - **Mobile Responsiveness**: Improved layout behavior on smaller screens.
- **Telegram Integration**:
  - **Notifications**: Fixed issue with duplicate task notifications appearing in multiple topics.
  - **Interaction**: Fixed inline button functionality in Telegram messages.
  - **Commands**: refined command handling structure.

### 🐛 Bug Fixes
- Fixed `Service Worker` caching issues preventing page loads.
- Resolved layout gaps (white space) on the right side of the content area.
- Fixed `Task` model commission calculation logic (currently verified).
- Addressed duplicates in Telegram task alerts.

### 🛠 Technical
- Verified Docker environment stability.
- Cleaned up duplicate/unused include files during refactoring.
