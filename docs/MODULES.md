# Модулі — DetalStudio

Кожен модуль — це набір PHP-файлів у відповідному каталозі, що реалізує CRUD та бізнес-логіку для конкретної сутності.

## Загальна структура модуля

```
module/
├── index.php      # Список (таблиця або Kanban)
├── add.php        # Форма створення
├── edit.php       # Форма редагування
├── view.php       # Детальний перегляд
└── delete.php     # Видалення (POST обробник)
```

Всі сторінки:
- Підключають `config/config.php`
- Перевіряють авторизацію (`Auth::requireAuth()`)
- Використовують шаблон `views/layout/header.php` + `footer.php`
- Захищені CSRF на POST запитах

---

## 1. Замовлення (`orders/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Kanban-дошка + таблиця замовлень, фільтри |
| `edit.php` | Створення/редагування: конструктор послуг, клієнт, авто |
| `view.php` | Деталі: платежі, задачі, коментарі, фото, історія |

**Модель**: `App\Models\Order`
**Контролер**: `App\Controllers\OrderController`
**Сервіс**: `App\Services\OrderService`
**API**: `update_order_status.php`, `upload_order_photo.php`, `get_order_photos.php`, `get_order_templates.php`
**Ролі**: director (повний), manager (CRUD без видалення), executor (лише перегляд через задачі)
**Статуси**: new → approved → in_progress → waiting_client → closed / cancelled

---

## 2. Задачі (`tasks/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Kanban + таблиця задач, фільтри по виконавцю/статусу |
| `view.php` | Деталі: матеріали, коментарі, комісія, історія |
| `calendar.php` | Календарний вид (FullCalendar) |

**Модель**: `App\Models\Task`
**Сервіс**: `App\Services\TaskService`
**API**: `update_task_status.php`, `calendar_events.php`, `mark_commission_paid.php`
**Ролі**: director/manager (CRUD), executor (перегляд своїх, зміна статусу)
**Статуси**: created → accepted → in_progress → paused → completed → revision / closed

---

## 3. Клієнти (`clients/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Таблиця клієнтів, пошук |
| `add.php` | Створення клієнта + авто |
| `edit.php` | Редагування клієнта + управління авто |
| `view.php` | Деталі: авто, замовлення, історія |
| `delete.php` | Видалення |

**Модель**: `App\Models\Client`, `App\Models\Car`
**API**: `search_clients.php`, `get_cars.php`, `get_makes.php`, `get_models.php`, `get_client_discount.php`
**Ролі**: director/manager

---

## 4. Склад (`warehouse/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Таблиця позицій складу |
| `add.php` | Додавання позиції |
| `edit.php` | Редагування |
| `view.php` | Деталі: транзакції, історія руху |
| `delete.php` | Видалення |
| `suppliers.php` | Довідник постачальників |
| `suppliers_save.php` | Збереження постачальника |

**Модель**: `App\Models\WarehouseItem`, `App\Models\Supplier`
**Контролер**: `App\Controllers\WarehouseController`
**Ролі**: director/manager

---

## 5. Закупівлі (`purchases/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Таблиця заявок |
| `add.php` | Створення заявки |
| `view.php` | Деталі + change status |
| `delete.php` | Видалення |

**Модель**: `App\Models\PurchaseRequest`
**Ролі**: director (повний + погодження), manager (створення)
**Статуси**: pending → approved → ordered → received → closed

---

## 6. Послуги (`services/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Каталог послуг |
| `add.php` | Створення послуги |
| `edit.php` | Редагування (ціна, категорія, авто-задача) |
| `delete.php` | Видалення |
| `status.php` | Зміна статусу (active/inactive) |
| `categories.php` | Управління категоріями |

**Модель**: `App\Models\Service`, `App\Models\ServiceCategory`
**Ролі**: director (повний), manager (перегляд)

---

## 7. Звіти (`reports/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Загальні звіти (фін. зведка, фільтри по датах) |
| `earnings.php` | Заробіток виконавців + "Закрити день" |
| `executors.php` | Ефективність виконавців |
| `profit.php` | Прибутковість |
| `salary.php` | Зарплатна відомість |

**Клас**: `App\Reports`
**API**: `get_chart_data.php`
**Ролі**: director (повний), manager (обмежений)

---

## 8. Виплати (`payments/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Таблиця виплат |
| `add.php` | Створення виплати |
| `edit.php` | Редагування |
| `delete.php` | Видалення |

**Клас**: `App\Payments`
**Ролі**: director/manager

---

## 9. Витрати (`expenses/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Таблиця витрат |
| `add.php` | Створення |
| `edit.php` | Редагування |
| `delete.php` | Видалення |

**Модель**: `App\Models\Expense`
**Ролі**: director/manager

---

## 10. Користувачі (`users/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Таблиця користувачів |
| `add.php` | Створення (роль, Telegram username) |
| `edit.php` | Редагування |
| `delete.php` | Soft delete |
| `activate.php` | Активація |
| `hard_delete.php` | Повне видалення (director only) |

**Ролі**: director (повний), manager (перегляд)

---

## 11. Адміністрування (`admin/`)

| Файл | Призначення | Ролі |
|------|-------------|------|
| `settings.php` | Системні налаштування (feature flags) | director |
| `audit.php` | Перегляд аудит-логу | director |
| `audit_logs.php` | Детальний перегляд логів | director |
| `backups.php` | Управління бекапами (створення, завантаження) | director |
| `stalls.php` | Робочі місця / бокси | director |
| `export_reports.php` | Експорт звітів (CSV/XLSX) | director |

---

## 12. Календар (`calendar/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | FullCalendar з задачами та замовленнями |
| `timeline.php` | Timeline-вид по днях |

**API**: `calendar_events.php`, `update_event_time.php`, `get_timeline_data.php`

---

## 13. Трекінг (`tracking/`)

| Файл | Призначення |
|------|-------------|
| `index.php` | Відстеження часу по задачах |

---

## 14. Публічне бронювання (`booking.php`)

Окрема сторінка без авторизації для клієнтів. Дозволяє залишити заявку на запис.

**API**: `create_booking.php`
**Rate limit**: 5 запитів / 10 хв / IP
