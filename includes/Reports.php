<?php

declare(strict_types=1);

namespace App;

use Exception;
use PDO;

/**
 * DetalStudio - Reports and Analytics
 * Звіти та аналітика
 */


class Reports
{

    /**
     * Get dashboard statistics
     */
    public static function getDashboardStats($dateFrom = null, $dateTo = null)
    {
        try {
            $dateWhere = "";
            $params = [];

            if ($dateFrom && $dateTo) {
                $dateWhere = "WHERE DATE(created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            // Orders stats
            $stmt = db()->prepare("SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as completed_orders,
                SUM(total_amount) as total_revenue,
                SUM(paid_amount) as total_paid,
                (SUM(total_amount) - SUM(paid_amount)) as total_debt
                FROM orders " . ($dateWhere ? $dateWhere . " AND is_deleted = 0 AND status != 'cancelled'" : "WHERE is_deleted = 0 AND status != 'cancelled'"));
            $stmt->execute($params);
            $orders = $stmt->fetch();

            // Additional Revenue from Tasks
            $taskRevSql = "SELECT SUM(profit_amount) FROM tasks WHERE status = 'closed' AND is_deleted = 0 AND order_id IS NULL";
            if ($dateFrom && $dateTo) {
                $taskRevSql .= " AND DATE(updated_at) BETWEEN ? AND ?";
            }
            $stmt = db()->prepare($taskRevSql);
            $stmt->execute($params);
            $taskRevenue = (float)$stmt->fetchColumn();

            $orders['total_revenue'] = (float)($orders['total_revenue'] ?? 0) + $taskRevenue;
            $orders['total_paid'] = (float)($orders['total_paid'] ?? 0) + $taskRevenue;

            // Tasks stats
            $taskSql = "SELECT 
                COUNT(t.id) as total_tasks,
                COUNT(CASE WHEN t.status IN ('created', 'in_progress') THEN 1 END) as active_tasks,
                COUNT(CASE WHEN t.status IN ('completed', 'closed') THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.deadline < NOW() AND t.status IN ('created', 'in_progress') THEN 1 END) as overdue_tasks
                FROM tasks t
                LEFT JOIN orders o ON t.order_id = o.id
                WHERE t.is_deleted = 0 AND (o.is_deleted = 0 OR o.id IS NULL)";

            if ($dateFrom && $dateTo) {
                $taskSql .= " AND DATE(t.created_at) BETWEEN ? AND ?";
            }

            $stmt = db()->prepare($taskSql);
            $stmt->execute($params);
            $tasks = $stmt->fetch();

            // Warehouse stats
            $warehouse = ['total_items' => 0, 'low_stock_items' => 0, 'total_value' => 0];
            $purchases = ['total_requests' => 0, 'pending_requests' => 0, 'total_amount' => 0];

            if (\App\Models\Setting::get('feature_warehouse', '1') == '1') {
                $stmt = db()->query("SELECT 
                    COUNT(*) as total_items,
                    COUNT(CASE WHEN current_quantity <= min_quantity THEN 1 END) as low_stock_items,
                    SUM(current_quantity * avg_price) as total_value
                    FROM warehouse_items WHERE is_active = 1");
                $warehouse = $stmt->fetch();

                // Purchase stats
                $stmt = db()->prepare("SELECT 
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                    SUM(total_amount) as total_amount
                    FROM purchase_requests $dateWhere");
                $stmt->execute($params);
                $purchases = $stmt->fetch();
            }

            return [
                'orders' => $orders,
                'tasks' => $tasks,
                'warehouse' => $warehouse,
                'purchases' => $purchases
            ];
        } catch (Exception $e) {
            error_log("Failed to get dashboard stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get statistics for current day (Today)
     */
    public static function getTodayStats()
    {
        try {
            // New orders today
            $stmt = db()->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND is_deleted = 0 AND status != 'cancelled'");
            $newOrders = $stmt->fetchColumn();

            // Completed orders today
            $stmt = db()->query("SELECT COUNT(*) FROM orders WHERE DATE(updated_at) = CURDATE() AND status = 'closed' AND is_deleted = 0");
            $completedOrders = $stmt->fetchColumn();

            // Revenue today
            $stmt = db()->query("SELECT SUM(amount) FROM order_payments WHERE DATE(payment_date) = CURDATE()");
            $revenueToday = (float)$stmt->fetchColumn();

            $stmt = db()->query("SELECT SUM(profit_amount) FROM tasks WHERE DATE(updated_at) = CURDATE() AND status = 'closed' AND is_deleted = 0 AND order_id IS NULL");
            $revenueToday += (float)$stmt->fetchColumn();

            // Tasks due today
            $stmt = db()->query("SELECT COUNT(t.id) FROM tasks t LEFT JOIN orders o ON t.order_id = o.id WHERE DATE(t.deadline) = CURDATE() AND t.status IN ('created', 'in_progress') AND t.is_deleted = 0 AND (o.is_deleted = 0 OR o.id IS NULL)");
            $tasksDue = $stmt->fetchColumn();

            return [
                'new_orders' => $newOrders,
                'completed_orders' => $completedOrders,
                'revenue' => $revenueToday,
                'tasks_due' => $tasksDue
            ];
        } catch (Exception $e) {
            error_log("Failed to get today stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get revenue by period
     */
    public static function getRevenueByPeriod($period = 'month', $dateFrom = null, $dateTo = null)
    {
        try {
            $dateFormat = $period == 'day' ? '%Y-%m-%d' : ($period == 'week' ? '%Y-%u' : '%Y-%m');

            $where = "WHERE status = 'closed' AND is_deleted = 0";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND DATE(created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $stmt = db()->prepare("SELECT 
                DATE_FORMAT(created_at, '$dateFormat') as period,
                COUNT(*) as orders_count,
                SUM(total_amount) as revenue,
                SUM(paid_amount) as paid,
                AVG(total_amount) as avg_order
                FROM orders
                $where
                GROUP BY DATE_FORMAT(created_at, '$dateFormat')
                ORDER BY period ASC");
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get revenue by period: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top services
     */
    public static function getTopServices($limit = 10, $dateFrom = null, $dateTo = null)
    {
        try {
            $where = "";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where = "WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'closed' AND o.is_deleted = 0";
                $params = [$dateFrom, $dateTo];
            } else {
                $where = "WHERE o.status = 'closed' AND o.is_deleted = 0";
            }

            $stmt = db()->prepare("SELECT 
                s.name as service_name,
                s.category,
                COUNT(oi.id) as times_ordered,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.total_price) as total_revenue
                FROM order_items oi
                JOIN services s ON oi.service_id = s.id
                JOIN orders o ON oi.order_id = o.id
                $where
                GROUP BY s.id, s.name, s.category
                ORDER BY total_revenue DESC
                LIMIT ?");
            $params[] = $limit;
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get top services: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top clients
     */
    public static function getTopClients($limit = 10, $dateFrom = null, $dateTo = null)
    {
        try {
            $where = "WHERE o.status = 'closed' AND o.is_deleted = 0";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $stmt = db()->prepare("SELECT 
                c.id,
                c.name,
                c.phone,
                COUNT(o.id) as orders_count,
                SUM(o.total_amount) as total_spent,
                AVG(o.total_amount) as avg_order
                FROM clients c
                JOIN orders o ON c.id = o.client_id
                $where
                GROUP BY c.id, c.name, c.phone
                ORDER BY total_spent DESC
                LIMIT ?");
            $params[] = $limit;
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get top clients: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get tasks statistics by executor
     */
    public static function getTasksByExecutor($dateFrom = null, $dateTo = null)
    {
        try {
            $where = "";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where = "WHERE DATE(t.created_at) BETWEEN ? AND ? AND t.is_deleted = 0";
                $params = [$dateFrom, $dateTo];
            } else {
                $where = "WHERE t.is_deleted = 0";
            }

            $stmt = db()->prepare("SELECT 
                u.id,
                u.full_name,
                COUNT(t.id) as total_tasks,
                COUNT(CASE WHEN t.status IN ('completed', 'closed') THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as in_progress_tasks,
                COUNT(CASE WHEN t.deadline < NOW() AND t.status IN ('created', 'in_progress') THEN 1 END) as overdue_tasks,
                AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.completed_at)) as avg_completion_time
                FROM users u
                LEFT JOIN task_executors te ON u.id = te.user_id
                LEFT JOIN tasks t ON te.task_id = t.id
                $where
                AND u.role IN ('executor', 'manager', 'director')
                AND u.is_active = 1
                GROUP BY u.id, u.full_name
                ORDER BY completed_tasks DESC");
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get tasks by executor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get warehouse turnover
     */
    public static function getWarehouseTurnover($dateFrom = null, $dateTo = null)
    {
        try {
            $where = "";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where = "WHERE DATE(wt.created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $stmt = db()->prepare("SELECT 
                wi.name as item_name,
                wi.category,
                wi.unit,
                SUM(CASE WHEN wt.type = 'in' THEN wt.quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN wt.type = 'out' THEN wt.quantity ELSE 0 END) as total_out,
                SUM(CASE WHEN wt.type = 'in' THEN wt.quantity * wt.price ELSE 0 END) as total_in_value,
                SUM(CASE WHEN wt.type = 'out' THEN wt.quantity * wi.avg_price ELSE 0 END) as total_out_value
                FROM warehouse_transactions wt
                JOIN warehouse_items wi ON wt.item_id = wi.id
                $where
                GROUP BY wi.id, wi.name, wi.category, wi.unit
                ORDER BY total_out_value DESC");
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get warehouse turnover: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get financial summary
     */
    public static function getFinancialSummary($dateFrom = null, $dateTo = null)
    {
        try {
            $where = "WHERE o.status = 'closed' AND o.is_deleted = 0";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            // Revenue by payment method
            $stmt = db()->prepare("SELECT 
                op.payment_method,
                SUM(op.amount) as total_amount
                FROM order_payments op
                JOIN orders o ON op.order_id = o.id
                $where
                GROUP BY op.payment_method");
            $stmt->execute($params);
            $payments = $stmt->fetchAll();

            // Total revenue
            $stmt = db()->prepare("SELECT SUM(total_amount) as total_revenue FROM orders o $where");
            $stmt->execute($params);
            $revenue = $stmt->fetch();
            $totalRevenue = (float)($revenue['total_revenue'] ?? 0);

            $taskWhere = "WHERE status = 'closed' AND is_deleted = 0 AND order_id IS NULL";
            $taskParams = [];
            if ($dateFrom && $dateTo) {
                $taskWhere .= " AND DATE(updated_at) BETWEEN ? AND ?";
                $taskParams = [$dateFrom, $dateTo];
            }
            $stmt = db()->prepare("SELECT SUM(profit_amount) FROM tasks $taskWhere");
            $stmt->execute($taskParams);
            $totalRevenue += (float)$stmt->fetchColumn();

            // Purchase expenses
            $sqlPurchases = "SELECT SUM(total_amount) as total_expenses FROM purchase_requests WHERE status = 'closed'";
            if ($dateFrom && $dateTo) {
                $stmt = db()->prepare($sqlPurchases . " AND DATE(created_at) BETWEEN ? AND ?");
                $stmt->execute([$dateFrom, $dateTo]);
            } else {
                $stmt = db()->query($sqlPurchases);
            }
            $expenses = $stmt->fetch();

            // Salary expenses (Employee Payments)
            $sqlSalaries = "SELECT SUM(amount) as total_salaries FROM employee_payments";
            if ($dateFrom && $dateTo) {
                $stmt = db()->prepare($sqlSalaries . " WHERE DATE(payment_date) BETWEEN ? AND ?");
                $stmt->execute([$dateFrom, $dateTo]);
            } else {
                $stmt = db()->query($sqlSalaries);
            }
            $salaries = $stmt->fetch();

            // General studio expenses
            $sqlGeneral = "SELECT SUM(amount) as total_general FROM expenses";
            if ($dateFrom && $dateTo) {
                $stmt = db()->prepare($sqlGeneral . " WHERE DATE(payment_date) BETWEEN ? AND ?");
                $stmt->execute([$dateFrom, $dateTo]);
            } else {
                $stmt = db()->query($sqlGeneral);
            }
            $generalExpenses = $stmt->fetch();

            $purchaseExpenses = $expenses['total_expenses'] ?? 0;
            $salaryExpenses = $salaries['total_salaries'] ?? 0;
            $studioExpenses = $generalExpenses['total_general'] ?? 0;
            $totalExpenses = $purchaseExpenses + $salaryExpenses + $studioExpenses;

            return [
                'revenue' => $totalRevenue,
                'purchase_expenses' => $purchaseExpenses,
                'salary_expenses' => $salaryExpenses,
                'studio_expenses' => $studioExpenses,
                'total_expenses' => $totalExpenses,
                'profit' => $totalRevenue - $totalExpenses,
                'payments' => $payments
            ];
        } catch (Exception $e) {
            error_log("Failed to get financial summary: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monthly financials for charts
     */
    public static function getMonthlyFinancials($months = 6)
    {
        try {
            // Calculate start date
            $starDateObj = new \DateTime("first day of -$months months");
            $startDate = $starDateObj->format('Y-m-d');

            // Initialize arrays with 0 for all months
            $data = [
                'labels' => [],
                'income' => [],
                'expenses' => [],
                'profit' => []
            ];

            $map = [];
            // Pre-fill map with all months
            for ($i = $months - 1; $i >= 0; $i--) {
                $m = date('Y-m', strtotime("-$i months"));
                $data['labels'][] = date('M Y', strtotime("-$i months"));
                $map[$m] = [
                    'revenue' => 0,
                    'expenses' => 0
                ];
            }

            // 1. Get Revenue (Orders)
            $stmt = db()->prepare("
                SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as total
                FROM orders 
                WHERE status = 'closed' AND is_deleted = 0 AND created_at >= ?
                GROUP BY month
            ");
            $stmt->execute([$startDate]);
            $revenues = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // 1.5 Get Revenue (Tasks)
            $stmt = db()->prepare("
                SELECT DATE_FORMAT(updated_at, '%Y-%m') as month, SUM(profit_amount) as total
                FROM tasks 
                WHERE status = 'closed' AND is_deleted = 0 AND order_id IS NULL AND updated_at >= ?
                GROUP BY month
            ");
            $stmt->execute([$startDate]);
            $taskRevs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // 2. Get Expenses (Purchases)
            $stmt = db()->prepare("
                SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as total
                FROM purchase_requests 
                WHERE status = 'closed' AND created_at >= ?
                GROUP BY month
            ");
            $stmt->execute([$startDate]);
            $purchases = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // 3. Get Expenses (Salaries)
            $stmt = db()->prepare("
                SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
                FROM employee_payments 
                WHERE payment_date >= ?
                GROUP BY month
            ");
            $stmt->execute([$startDate]);
            $salaries = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // 4. Get Expenses (General)
            $stmt = db()->prepare("
                SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
                FROM expenses 
                WHERE payment_date >= ?
                GROUP BY month
            ");
            $stmt->execute([$startDate]);
            $general = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Aggregate data
            foreach ($map as $month => &$val) {
                // Revenue
                $val['revenue'] = (float)($revenues[$month] ?? 0) + (float)($taskRevs[$month] ?? 0);

                // Expenses sum
                $exp = (float)($purchases[$month] ?? 0) +
                    (float)($salaries[$month] ?? 0) +
                    (float)($general[$month] ?? 0);
                $val['expenses'] = $exp;

                // Push to result arrays
                $data['income'][] = $val['revenue'];
                $data['expenses'][] = $val['expenses'];
                $data['profit'][] = $val['revenue'] - $val['expenses'];
            }

            return $data;
        } catch (Exception $e) {
            error_log("Failed to get monthly financials: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get manager performance stats
     */
    public static function getManagerPerformance($dateFrom = null, $dateTo = null)
    {
        try {
            $where = "WHERE o.status = 'closed' AND o.is_deleted = 0";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $stmt = db()->prepare("SELECT 
                u.id,
                u.full_name,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_check
                FROM users u
                JOIN orders o ON u.id = o.manager_id
                $where
                GROUP BY u.id, u.full_name
                ORDER BY total_revenue DESC");
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get manager performance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get manager revenue share (For Chart)
     */
    public static function getManagerRevenue($limit = 5, $dateFrom = null, $dateTo = null)
    {
        try {
            $where = "WHERE o.status = 'closed' AND o.is_deleted = 0";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $stmt = db()->prepare("SELECT 
                u.full_name as label,
                SUM(o.total_amount) as value
                FROM orders o
                JOIN users u ON o.manager_id = u.id
                $where
                GROUP BY u.id, u.full_name
                ORDER BY value DESC
                LIMIT ?");
            $params[] = $limit;
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get manager revenue: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top services revenue share (For Chart)
     */
    public static function getServiceRevenueShare($limit = 5, $dateFrom = null, $dateTo = null)
    {
        try {
            $where = "WHERE o.status = 'closed' AND o.is_deleted = 0";
            $params = [];

            if ($dateFrom && $dateTo) {
                $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
                $params = [$dateFrom, $dateTo];
            }

            $stmt = db()->prepare("SELECT 
                s.name as label,
                SUM(oi.total_price) as value
                FROM order_items oi
                JOIN services s ON oi.service_id = s.id
                JOIN orders o ON oi.order_id = o.id
                $where
                GROUP BY s.id, s.name
                ORDER BY value DESC
                LIMIT ?");
            $params[] = $limit;
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get service revenue share: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get client retention stats
     */
    public static function getClientRetention($dateFrom = null, $dateTo = null)
    {
        try {
            // New Clients: First order created within report period
            // Returning Clients: Order in report period, but first order was accumulated BEFORE report period start

            if (!$dateFrom || !$dateTo) {
                // Default to this month if not set
                $dateFrom = date('Y-m-01');
                $dateTo = date('Y-m-t');
            }

            // 1. Get all clients who made an order in this period
            $stmt = db()->prepare("SELECT DISTINCT client_id FROM orders 
                                  WHERE status = 'closed' AND is_deleted = 0 
                                  AND DATE(created_at) BETWEEN ? AND ?");
            $stmt->execute([$dateFrom, $dateTo]);
            $activeClients = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($activeClients)) {
                return ['new' => 0, 'returning' => 0];
            }

            $newClients = 0;
            $returningClients = 0;

            // Check each client's history
            $placeholders = implode(',', array_fill(0, count($activeClients), '?'));
            $sql = "SELECT client_id, MIN(created_at) as first_order_date 
                    FROM orders 
                    WHERE client_id IN ($placeholders) AND status = 'closed' AND is_deleted = 0
                    GROUP BY client_id";

            $stmt = db()->prepare($sql);
            $stmt->execute($activeClients);
            $clientHistories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [client_id => first_order_date]

            foreach ($activeClients as $clientId) {
                $firstOrderDate = date('Y-m-d', strtotime($clientHistories[$clientId]));
                if ($firstOrderDate >= $dateFrom) {
                    $newClients++;
                } else {
                    $returningClients++;
                }
            }

            return [
                'new' => $newClients,
                'returning' => $returningClients
            ];
        } catch (Exception $e) {
            error_log("Failed to get client retention: " . $e->getMessage());
            return ['new' => 0, 'returning' => 0];
        }
    }

    /**
     * Get growth trends (This Month vs Last Month)
     */
    public static function getGrowthTrends()
    {
        try {
            $thisMonthStart = date('Y-m-01');
            $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
            $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

            // 1. Revenue Growth
            $stmt = db()->prepare("SELECT SUM(total_amount) FROM orders WHERE status != 'cancelled' AND is_deleted = 0 AND created_at >= ?");
            $stmt->execute([$thisMonthStart]);
            $revThis = (float)$stmt->fetchColumn();
            
            $stmt = db()->prepare("SELECT SUM(profit_amount) FROM tasks WHERE status = 'closed' AND is_deleted = 0 AND order_id IS NULL AND updated_at >= ?");
            $stmt->execute([$thisMonthStart]);
            $revThis += (float)$stmt->fetchColumn();

            $stmt = db()->prepare("SELECT SUM(total_amount) FROM orders WHERE status != 'cancelled' AND is_deleted = 0 AND created_at >= ? AND created_at <= ?");
            $stmt->execute([$lastMonthStart, $lastMonthEnd]);
            $revLast = (float)$stmt->fetchColumn();

            $stmt = db()->prepare("SELECT SUM(profit_amount) FROM tasks WHERE status = 'closed' AND is_deleted = 0 AND order_id IS NULL AND updated_at >= ? AND updated_at <= ?");
            $stmt->execute([$lastMonthStart, $lastMonthEnd]);
            $revLast += (float)$stmt->fetchColumn();

            $revenueGrowth = 0;
            if ($revLast > 0) {
                $revenueGrowth = (($revThis - $revLast) / $revLast) * 100;
            } elseif ($revThis > 0) {
                $revenueGrowth = 100;
            }

            // 2. Orders Count Growth
            $stmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND status != 'cancelled' AND created_at >= ?");
            $stmt->execute([$thisMonthStart]);
            $ordThis = (int)$stmt->fetchColumn();

            $stmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND status != 'cancelled' AND created_at >= ? AND created_at <= ?");
            $stmt->execute([$lastMonthStart, $lastMonthEnd]);
            $ordLast = (int)$stmt->fetchColumn();

            $ordersGrowth = 0;
            if ($ordLast > 0) {
                $ordersGrowth = (($ordThis - $ordLast) / $ordLast) * 100;
            } elseif ($ordThis > 0) {
                $ordersGrowth = 100;
            }

            // 3. Tasks Completed Growth
            // For tasks, we check 'completed_at'
            $stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE status IN ('completed', 'closed') AND is_deleted = 0 AND completed_at >= ?");
            $stmt->execute([$thisMonthStart]);
            $taskThis = (int)$stmt->fetchColumn();

            $stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE status IN ('completed', 'closed') AND is_deleted = 0 AND completed_at >= ? AND completed_at <= ?");
            $stmt->execute([$lastMonthStart, $lastMonthEnd]);
            $taskLast = (int)$stmt->fetchColumn();

            $tasksGrowth = 0;
            if ($taskLast > 0) {
                $tasksGrowth = (($taskThis - $taskLast) / $taskLast) * 100;
            } elseif ($taskThis > 0) {
                $tasksGrowth = 100;
            }

            return [
                'revenue' => round($revenueGrowth, 1),
                'orders' => round($ordersGrowth, 1),
                'tasks' => round($tasksGrowth, 1),
                'rev_abs' => $revThis, // This month absolute
                'ord_abs' => $ordThis,
                'task_abs' => $taskThis
            ];
        } catch (Exception $e) {
            error_log("Failed to get growth trends: " . $e->getMessage());
            return ['revenue' => 0, 'orders' => 0, 'tasks' => 0];
        }
    }
    /**
     * Export data to CSV and send to browser
     */
    public static function exportToCSV(array $data, string $filename, array $headers = []): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        if (!empty($headers)) {
            fputcsv($output, $headers);
        }

        foreach ($data as $row) {
            fputcsv($output, array_values($row));
        }

        fclose($output);
        exit;
    }
}
