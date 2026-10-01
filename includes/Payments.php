<?php
declare(strict_types=1);

namespace App;

use App\Auth;

class Payments {
    
    /**
     * Get all payments with filters
     */
    public static function getAll($filters = []) {
        $sql = "SELECT p.*, u.full_name as employee_name, creator.full_name as creator_name 
                FROM employee_payments p 
                JOIN users u ON p.user_id = u.id 
                JOIN users creator ON p.created_by = creator.id";
        
        $where = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "p.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "p.payment_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "p.payment_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";
        
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get payment by ID
     */
    public static function getById($id) {
        $stmt = db()->prepare("SELECT p.*, u.full_name as employee_name FROM employee_payments p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Create new payment
     */
    public static function create($data) {
        $stmt = db()->prepare("INSERT INTO employee_payments (user_id, amount, payment_type, payment_method, payment_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $data['user_id'],
            $data['amount'],
            $data['payment_type'],
            $data['payment_method'],
            $data['payment_date'],
            $data['notes'] ?? null,
            Auth::getUserId()
        ]);
    }
    
    /**
     * Update payment
     */
    public static function update($id, $data) {
        $stmt = db()->prepare("UPDATE employee_payments SET user_id = ?, amount = ?, payment_type = ?, payment_method = ?, payment_date = ?, notes = ? WHERE id = ?");
        
        return $stmt->execute([
            $data['user_id'],
            $data['amount'],
            $data['payment_type'],
            $data['payment_method'],
            $data['payment_date'],
            $data['notes'] ?? null,
            $id
        ]);
    }
    
    /**
     * Delete payment
     */
    public static function delete($id) {
        $stmt = db()->prepare("DELETE FROM employee_payments WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get payment types
     */
    public static function getTypes() {
        return [
            'salary' => 'Зарплата',
            'bonus' => 'Бонус',
            'advance' => 'Аванс',
            'other' => 'Інше'
        ];
    }
    
    /**
     * Get payment methods
     */
    public static function getMethods() {
        return [
            'cash' => 'Готівка',
            'card' => 'Карта',
            'fop' => 'ФОП'
        ];
    }
}
