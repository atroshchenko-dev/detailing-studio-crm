<?php

/**
 * DetalStudio - Dashboard
 * Головна сторінка дашборду
 */

require_once __DIR__ . '/config/config.php';

Auth::requireAuth();

// Get dashboard statistics
$stats = Reports::getDashboardStats();

// Get recent orders
$recentOrders = Order::getList([], 5);

// Get recent tasks
$recentTasks = Task::getList(['status' => ['created', 'in_progress']], 10);

// Get top services and clients (for analytics)
$topServices = Reports::getTopServices(5);
$topClients = Reports::getTopClients(5);

// Get overdue tasks
$overdueTasks = Task::getOverdue();

// Get low stock items
$lowStockItems = [];
if (\App\Models\Setting::get('feature_warehouse', '1') == '1') {
    $lowStockItems = WarehouseItem::getLowStock();
}


// Get today's stats
$todayStats = Reports::getTodayStats();

// Get growth trends
$trends = Reports::getGrowthTrends();


include __DIR__ . '/views/layout/header.php';
include __DIR__ . '/views/dashboard.php';
include __DIR__ . '/views/layout/footer.php';
