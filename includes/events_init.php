<?php

use App\Events\EventDispatcher;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\OrderUpdated;
use App\Events\OrderDeleted;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Events\TaskUpdated;
use App\Events\TaskDeleted;
use App\Events\OrderTasksCompleted;
use App\Events\BackupProcessed;
use App\Events\DeadlineReached;
use App\Listeners\TelegramListener;
use App\Listeners\GoogleCalendarListener;
use App\Listeners\TaskCompletionListener;

// --- Manual include for newly added Listener to bypass optimized composer classmap on prod ---
require_once __DIR__ . '/Listeners/TaskCompletionListener.php';
// --------------------------------------------------------------------------------------------


// Order Events
EventDispatcher::addListener(OrderCreated::class, [TelegramListener::class, 'onOrderCreated']);
EventDispatcher::addListener(OrderCreated::class, [GoogleCalendarListener::class, 'onOrderCreated']);

EventDispatcher::addListener(OrderStatusChanged::class, [TelegramListener::class, 'onOrderStatusChanged']);

EventDispatcher::addListener(OrderUpdated::class, [TelegramListener::class, 'onOrderUpdated']);
EventDispatcher::addListener(OrderUpdated::class, [GoogleCalendarListener::class, 'onOrderUpdated']);
EventDispatcher::addListener(OrderTasksCompleted::class, [TelegramListener::class, 'onOrderTasksCompleted']);
EventDispatcher::addListener(OrderDeleted::class, [GoogleCalendarListener::class, 'onOrderDeleted']);
EventDispatcher::addListener(OrderDeleted::class, [TelegramListener::class, 'onOrderDeleted']);

// Task Events
EventDispatcher::addListener(TaskCreated::class, [TelegramListener::class, 'onTaskCreated']);
EventDispatcher::addListener(TaskCreated::class, [GoogleCalendarListener::class, 'onTaskCreated']); // Added Task support

EventDispatcher::addListener(TaskStatusChanged::class, [TelegramListener::class, 'onTaskStatusChanged']);

EventDispatcher::addListener(TaskUpdated::class, [TelegramListener::class, 'onTaskUpdated']);
EventDispatcher::addListener(TaskUpdated::class, [GoogleCalendarListener::class, 'onTaskUpdated']);
EventDispatcher::addListener(TaskUpdated::class, [new TaskCompletionListener(), 'onTaskUpdated']);
EventDispatcher::addListener(TaskDeleted::class, [GoogleCalendarListener::class, 'onTaskDeleted']);
EventDispatcher::addListener(TaskDeleted::class, [TelegramListener::class, 'onTaskDeleted']);

// Generic / System Events
EventDispatcher::addListener(BackupProcessed::class, [TelegramListener::class, 'onBackupProcessed']);
EventDispatcher::addListener(DeadlineReached::class, [TelegramListener::class, 'onDeadlineReached']);
