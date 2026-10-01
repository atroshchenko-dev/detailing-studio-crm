<?php
declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Audit;
use Exception;

/**
 * DetalStudio - Studio Expenses Model
 * Модель для обліку загальних витрат студії
 */

class Expense {
    
    /**
     * Отримати довідник категорій витрат
     *
     * @return array<string, string> Мапа категорій {key => label}
     */
    public static function getCategories() {
        return [
            'rent' => 'Оренда',
            'utilities' => 'Комунальні',
            'marketing' => 'Маркетинг',
            'materials' => 'Витратні матеріали',
            'maintenance' => 'Обслуговування',
            'software' => 'IT / Софт',
            'other' => 'Інше'
        ];
    }

    /**
     * Створити нову витрату
     *
     * Автоматично прив'язує до поточного користувача та логує в audit.
     *
     * @param array{category: string, amount: float, payment_method?: string, payment_date?: string, notes?: string} $data
     * @return string|false ID створеної витрати або false
     */
    public static function create($data) {
        try {
            $stmt = db()->prepare("INSERT INTO expenses (category, amount, payment_method, payment_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['category'],
                $data['amount'],
                $data['payment_method'] ?? 'cash',
                $data['payment_date'] ?? date('Y-m-d'),
                $data['notes'] ?? null,
                Auth::getUserId()
            ]);
            
            $expenseId = db()->lastInsertId();
            Audit::log('expense_created', 'expenses', $expenseId, null, $data);
            return $expenseId;
        } catch (Exception $e) {
            error_log("Failed to create expense: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати витрату за ID з ім'ям автора
     *
     * @param int $id ID витрати
     * @return array|null Дані витрати або null
     */
    public static function getById($id) {
        try {
            $stmt = db()->prepare("SELECT e.*, u.full_name as created_by_name 
                                  FROM expenses e
                                  LEFT JOIN users u ON e.created_by = u.id
                                  WHERE e.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get expense: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отримати список витрат з фільтрами
     *
     * @param array{category?: string, date_from?: string, date_to?: string} $filters Фільтри
     * @return array Масив витрат
     */
    public static function getList($filters = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['category'])) {
                $where[] = "category = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "payment_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "payment_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereSql = $where ? "WHERE " . implode(' AND ', $where) : "";
            
            $sql = "SELECT e.*, u.full_name as created_by_name 
                    FROM expenses e
                    LEFT JOIN users u ON e.created_by = u.id
                    $whereSql
                    ORDER BY e.payment_date DESC, e.created_at DESC";
            
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get expenses: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Оновити витрату
     *
     * @param int   $id   ID витрати
     * @param array $data Масив полів для оновлення
     * @return bool true при успіху
     */
    public static function update($id, $data) {
        try {
            $allowedFields = ['category', 'amount', 'payment_method', 'payment_date', 'notes'];
            $updates = [];
            $values = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($updates)) return true;
            
            $values[] = $id;
            $sql = "UPDATE expenses SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = db()->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Failed to update expense: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Видалити витрату
     *
     * @param int $id ID витрати
     * @return bool true при успіху
     */
    public static function delete($id) {
        try {
            $stmt = db()->prepare("DELETE FROM expenses WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Failed to delete expense: " . $e->getMessage());
            return false;
        }
    }
}
