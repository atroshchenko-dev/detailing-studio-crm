<?php
/**
 * DetalStudio - Export Reports
 * Експорт звітів у CSV
 */

require_once __DIR__ . '/../config/config.php';
use App\Reports;
use App\Auth;

Auth::requireAuth();
Auth::requireRole('director');

$type = $_GET['type'] ?? 'financial';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

try {
    $data = [];
    $filename = "report_{$type}_{$dateFrom}_{$dateTo}.csv";

    $headers = [];

    switch ($type) {
        case 'financial':
            // Reports::getRevenueByPeriod columns: period, orders_count, revenue, paid, avg_order
            $headers = ['Період', 'Кількість замовлень', 'Виручка', 'Оплачено', 'Середній чек'];
            $data = Reports::getRevenueByPeriod('day', $dateFrom, $dateTo);
            break;

        case 'orders':
            $stmt = db()->prepare("
                SELECT 
                    o.order_number, 
                    o.created_at, 
                    c.name as client_name, 
                    CONCAT(cr.make, ' ', cr.model) as car,
                    o.total_amount, 
                    o.status 
                FROM orders o
                LEFT JOIN clients c ON o.client_id = c.id
                LEFT JOIN cars cr ON o.car_id = cr.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Номер замовлення', 'Дата створення', 'Клієнт', 'Автомобіль', 'Сума', 'Статус'];
            break;

        case 'clients':
            // Reports::getTopClients columns: id, name, phone, orders_count, total_spent, avg_order
            $headers = ['ID', 'Ім\'я', 'Телефон', 'Кількість замовлень', 'Всього витрачено', 'Середній чек'];
            $data = Reports::getTopClients(1000, $dateFrom, $dateTo);
            break;
            
        case 'services':
            // Reports::getTopServices columns: service_name, category, times_ordered, total_quantity, total_revenue
            $headers = ['Назва послуги', 'Категорія', 'Разів замовлено', 'Кількість', 'Виручка'];
            $data = Reports::getTopServices(1000, $dateFrom, $dateTo);
            break;

        default:
            throw new Exception("Unknown report type");
    }

    if (empty($data)) {
        // Handle empty data strictly or just export headers?
        // Reports::exportToCSV handles empty array gracefully but let's add a fallback row or msg if needed
        // For CSV, usually headers + no rows is fine.
    }

    Reports::exportToCSV($data, $filename, $headers);

} catch (Exception $e) {
    die("Export Failed: " . $e->getMessage());
}
