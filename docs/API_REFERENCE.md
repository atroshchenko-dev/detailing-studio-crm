# API Reference — DetalStudio

Всі ендпоінти знаходяться в каталозі `api/`. Кожен файл — окремий AJAX endpoint.

## Загальні правила

| Параметр | Значення |
|----------|---------|
| Base URL | `/api/` |
| Content-Type | `application/json` |
| Аутентифікація | PHP Session (cookie). Більшість потребують `Auth::requireAuth()` |
| CSRF | POST запити потребують `X-CSRF-Token` header або `csrf_token` в body |
| Помилки | HTTP status code + JSON `{"error": "message"}` або `{"success": false, "message": "..."}` |

### CSRF для AJAX

jQuery автоматично додає header з meta-тегу:
```javascript
$.ajaxSetup({
    headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') }
});
```

---

## Ендпоінти

### 1. Глобальний пошук

**`GET /api/search.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `q` | string | ✅ | Пошуковий запит (мін. 2 символи) |

**Відповідь:**
```json
{
  "orders": [{"type": "Замовлення", "text": "ORD-2026-001", "subtext": "5000.00 ₴ (В роботі)", "url": "/orders/view.php?id=1", "icon": "bi-receipt"}],
  "clients": [{"type": "Клієнт", "text": "Іван Петренко", "subtext": "+380991234567", "url": "/clients/view.php?id=1", "icon": "bi-person"}],
  "cars": [{"type": "Авто", "text": "BMW X5 (AA1234BB)", "url": "/cars/view.php?id=1", "icon": "bi-car-front"}],
  "tasks": [{"type": "Задача", "text": "Полірування", "subtext": "В роботі", "url": "/tasks/view.php?id=1", "icon": "bi-list-check"}]
}
```

**Auth**: Потрібна | **CSRF**: Ні (GET) | **Ролі**: Всі авторизовані

---

### 2. Пошук клієнтів

**`GET /api/search_clients.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `q` | string | ✅ | Ім'я або телефон клієнта |

**Відповідь:** Масив клієнтів `[{"id": 1, "name": "...", "phone": "..."}]`

**Auth**: Потрібна | **CSRF**: Ні

---

### 3. Календарні події

**`GET /api/calendar_events.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `start` | date | ❌ | Початок діапазону (YYYY-MM-DD). Default: today |
| `end` | date | ❌ | Кінець діапазону. Default: +30 днів |

**Відповідь:** Масив подій у форматі FullCalendar:
```json
[
  {
    "id": "task_15",
    "title": "📋 Полірування",
    "start": "2026-04-05",
    "allDay": true,
    "url": "/tasks/view.php?id=15",
    "backgroundColor": "#ffc107",
    "extendedProps": {"type": "task", "status": "В роботі"}
  },
  {
    "id": "order_end_3",
    "title": "🏁 Видача #ORD-2026-003",
    "start": "2026-04-06T14:00:00",
    "url": "/orders/view.php?id=3",
    "backgroundColor": "#6610f2",
    "extendedProps": {"type": "order", "subtype": "deadline"}
  }
]
```

**Auth**: Потрібна | **CSRF**: Ні | **Ролі**: Виконавці бачать лише свої

---

### 4. Публічне бронювання

**`POST /api/create_booking.php`**

> ⚠️ **Публічний endpoint** — не потребує авторизації. Rate limited: 5 запитів / 10 хвилин / IP.

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `name` | string | ✅ | Ім'я клієнта |
| `phone` | string | ✅ | Телефон |
| `car_make` | string | ❌ | Марка авто |
| `car_model` | string | ❌ | Модель авто |
| `service` | string | ❌ | Бажана послуга |
| `date` | string | ❌ | Бажана дата |
| `notes` | string | ❌ | Коментар |

**Відповідь:**
```json
{"success": true, "order_id": 42}
```

**Auth**: ❌ | **CSRF**: ❌ | **Rate Limit**: ✅

---

### 5. Дані для графіків (Dashboard)

**`GET /api/get_chart_data.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `months` | int | ❌ | Кількість місяців (1-24). Default: 6 |

**Відповідь:**
```json
{
  "labels": ["Лист", "Груд", "Січ", "Лют", "Бер", "Кві"],
  "revenue": [45000.00, 52000.00, 38000.00, 61000.00, 55000.00, 48000.00],
  "tasks": [12, 15, 8, 20, 18, 14],
  "managerRevenue": [{"name": "...", "total": 30000}],
  "serviceRevenue": [{"name": "...", "total": 50000}],
  "clientStats": {"new": 5, "returning": 12}
}
```

**Auth**: Потрібна | **CSRF**: Ні

---

### 6. Найближчі дедлайни

**`GET /api/get_upcoming_deadlines.php`**

Повертає задачі з дедлайнами протягом наступних 24 годин.

**Відповідь:**
```json
[
  {
    "id": 15,
    "title": "Полірування кузова",
    "deadline": "2026-04-03 18:00:00",
    "priority": "high",
    "status": "in_progress",
    "order_number": "ORD-2026-003",
    "executor_name": "Олександр",
    "hours_left": 2,
    "urgency": "critical",
    "urgency_text": "Терміново"
  }
]
```

**Рівні urgency**: `overdue` (прострочено), `critical` (≤2 год), `warning` (≤8 год), `normal` (сьогодні)

**Auth**: Потрібна | **CSRF**: Ні

---

### 7. Зміна статусу замовлення

**`POST /api/update_order_status.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `order_id` | int | ✅ | ID замовлення |
| `status` | string | ✅ | Новий статус |

**Допустимі статуси**: `new`, `approved`, `in_progress`, `waiting_client`, `waiting_parts`, `ready`, `completed`, `closed`, `cancelled`

**Відповідь:** `{"success": true}` або `{"success": false, "message": "..."}`

**Auth**: Потрібна | **CSRF**: ✅ | **Ролі**: manager, director

---

### 8. Зміна статусу задачі

**`POST /api/update_task_status.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `task_id` | int | ✅ | ID задачі |
| `status` | string | ✅ | Новий статус |

**Допустимі статуси**: `created`, `in_progress`, `completed`, `closed`, `cancelled`

**Auth**: Потрібна | **CSRF**: ✅ | **Ролі**: Всі (виконавці — тільки свої)

---

### 9. Завантаження фото замовлення

**`POST /api/upload_order_photo.php`** (multipart/form-data)

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `order_id` | int | ✅ | ID замовлення |
| `photo_type` | string | ❌ | `before`, `after`, `process`. Default: `before` |
| `caption` | string | ❌ | Підпис до фото |
| `photo` | file | ✅ | Файл зображення (JPG, PNG, WEBP, max 10MB) |

**Відповідь:**
```json
{
  "success": true,
  "photo_id": 42,
  "file_path": "/uploads/orders/5/before_1712345678_abc123.webp",
  "google_drive_id": "1abc...",
  "message": "Фото завантажено та синхронізовано з Google Drive"
}
```

**Auth**: Потрібна | **CSRF**: ✅ | **MIME validation**: Server-side (`finfo`)

---

### 10. Отримання фото замовлення

**`GET /api/get_order_photos.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `order_id` | int | ✅ | ID замовлення |

**Auth**: Потрібна | **CSRF**: Ні

---

### 11. Авто клієнта

**`GET /api/get_cars.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `client_id` | int | ✅ | ID клієнта |

**Auth**: Потрібна | **CSRF**: Ні

---

### 12. Марки авто

**`GET /api/get_makes.php`**

Повертає список всіх марок з каталогу `car_makes`.

**Auth**: Потрібна | **CSRF**: Ні

---

### 13. Моделі авто

**`GET /api/get_models.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `make_id` | int | ✅ | ID марки |

**Auth**: Потрібна | **CSRF**: Ні

---

### 14. Знижка клієнта

**`GET /api/get_client_discount.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `client_id` | int | ✅ | ID клієнта |

**Auth**: Потрібна | **CSRF**: Ні

---

### 15. Шаблони замовлень

**`GET /api/get_order_templates.php`**

Повертає шаблони (попередні замовлення клієнта) для швидкого створення.

**Auth**: Потрібна | **CSRF**: Ні

---

### 16. Timeline дані

**`GET /api/get_timeline_data.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `start` | date | ❌ | Початок діапазону |
| `end` | date | ❌ | Кінець діапазону |

**Auth**: Потрібна | **CSRF**: Ні

---

### 17. Оновлення часу події

**`POST /api/update_event_time.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `event_id` | string | ✅ | ID події (формат: `task_15` або `order_start_3`) |
| `start` | datetime | ✅ | Новий час початку |
| `end` | datetime | ❌ | Новий час закінчення |

**Auth**: Потрібна | **CSRF**: ✅ | **Ролі**: manager, director

---

### 18. Позначити комісію оплаченою

**`POST /api/mark_commission_paid.php`**

| Параметр | Тип | Обов'язковий | Опис |
|----------|-----|:---:|------|
| `task_id` | int | ✅ | ID задачі |

**Auth**: Потрібна | **CSRF**: ✅ | **Ролі**: manager, director
