<?php
declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Audit;
use Exception;
use PDO;

/**
 * Warehouse Items Model
 */
class WarehouseItem {
    
    /**
     * Create warehouse item
     */
    public static function create($data) {
        try {
            $stmt = db()->prepare("INSERT INTO warehouse_items (name, sku, category, unit, min_quantity, current_quantity, avg_price, supplier_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['sku'] ?? null,
                $data['category'],
                $data['unit'] ?? 'шт',
                $data['min_quantity'] ?? 0,
                $data['current_quantity'] ?? 0,
                $data['avg_price'] ?? 0,
                $data['supplier_id'] ?? null,
                $data['notes'] ?? null
            ]);
            
            $itemId = db()->lastInsertId();
            
            // Log action
            Audit::log('warehouse_in', 'warehouse_items', $itemId, null, ['quantity' => $data['current_quantity'] ?? 0]);
            
            return $itemId;
        } catch (Exception $e) {
            error_log("Failed to create warehouse item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get item by ID
     */
    public static function getById($id) {
        try {
            $stmt = db()->prepare("SELECT wi.*, s.name as supplier_name 
                                  FROM warehouse_items wi
                                  LEFT JOIN suppliers s ON wi.supplier_id = s.id
                                  WHERE wi.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get warehouse item: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all items
     */
    public static function getAll($activeOnly = true) {
        try {
            $sql = "SELECT wi.*, s.name as supplier_name 
                    FROM warehouse_items wi
                    LEFT JOIN suppliers s ON wi.supplier_id = s.id";
            
            if ($activeOnly) {
                $sql .= " WHERE wi.is_active = 1";
            }
            
            $sql .= " ORDER BY wi.category, wi.name";
            
            $stmt = db()->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get warehouse items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get low stock items
     */
    public static function getLowStock() {
        try {
            $stmt = db()->query("SELECT * FROM v_warehouse_balance WHERE is_low_stock = 1");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get low stock items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update item
     */
    public static function update($id, $data) {
        try {
            $allowedFields = ['name', 'sku', 'category', 'unit', 'min_quantity', 'avg_price', 'supplier_id', 'notes', 'is_active'];
            $updates = [];
            $values = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $values[] = $id;
            $sql = "UPDATE warehouse_items SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = db()->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Failed to update warehouse item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add stock (Purchase) - with Transaction
     */
    public static function addStock($itemId, $quantity, $price = null, $notes = null, $purchaseId = null) {
        try {
            $inTransaction = db()->inTransaction();
            if (!$inTransaction) {
                db()->beginTransaction();
            }

            $item = self::getById($itemId);
            if (!$item) {
                throw new Exception("Item not found");
            }
            
            // Calculate new average price
            $currentValue = $item['current_quantity'] * $item['avg_price'];
            $newValue = $quantity * ($price ?? $item['avg_price']);
            $newQuantity = $item['current_quantity'] + $quantity;
            $newAvgPrice = $newQuantity > 0 ? ($currentValue + $newValue) / $newQuantity : 0;
            
            // Update item
            $stmt = db()->prepare("UPDATE warehouse_items SET current_quantity = ?, avg_price = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $newAvgPrice, $itemId]);
            
            // Add transaction
            $stmt = db()->prepare("INSERT INTO warehouse_transactions (item_id, type, quantity, price, purchase_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, 'in', $quantity, $price, $purchaseId, $notes, Auth::getUserId()]);
            
            // Log action
            Audit::logWarehouseTransaction($itemId, 'in', $quantity, null, null, $purchaseId);
            
            if (!$inTransaction) {
                db()->commit();
            }
            return true;
        } catch (Exception $e) {
            if (!$inTransaction && db()->inTransaction()) {
                db()->rollBack();
            }
            error_log("Failed to add stock: " . $e->getMessage());
            return "Помилка при додаванні залишку: " . $e->getMessage();
        }
    }
    
    /**
     * Remove stock (Usage/Sale) - with Transaction
     */
    public static function removeStock($itemId, $quantity, $orderId = null, $taskId = null, $notes = null) {
        try {
            $inTransaction = db()->inTransaction();
            if (!$inTransaction) {
                db()->beginTransaction();
            }

            $item = self::getById($itemId);
            if (!$item) {
                throw new Exception("Item not found");
            }
            
            if ($item['current_quantity'] < $quantity) {
                throw new Exception("Недостатньо залишку на складі");
            }
            
            $newQuantity = $item['current_quantity'] - $quantity;
            
            // Update item
            $stmt = db()->prepare("UPDATE warehouse_items SET current_quantity = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $itemId]);
            
            // Add transaction
            $stmt = db()->prepare("INSERT INTO warehouse_transactions (item_id, type, quantity, order_id, task_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, 'out', $quantity, $orderId, $taskId, $notes, Auth::getUserId()]);
            
            // Log action
            Audit::logWarehouseTransaction($itemId, 'out', $quantity, $orderId, $taskId);
            
            if (!$inTransaction) {
                db()->commit();
            }
            return true;
        } catch (Exception $e) {
            if (!$inTransaction && db()->inTransaction()) {
                db()->rollBack();
            }
            error_log("Failed to remove stock: " . $e->getMessage());
            return $e->getMessage();
        }
    }
    
    /**
     * Adjust stock (Inventory) - with Transaction
     */
    public static function adjustStock($itemId, $realQuantity, $notes = null) {
        try {
            $inTransaction = db()->inTransaction();
            if (!$inTransaction) {
                db()->beginTransaction();
            }

            $item = self::getById($itemId);
            if (!$item) {
                throw new Exception("Item not found");
            }
            
            $diffCallback = $realQuantity - $item['current_quantity'];
            if ($diffCallback == 0) return true; // No change
            
            // Update item
            $stmt = db()->prepare("UPDATE warehouse_items SET current_quantity = ? WHERE id = ?");
            $stmt->execute([$realQuantity, $itemId]);
            
            // Add transaction
            $stmt = db()->prepare("INSERT INTO warehouse_transactions (item_id, type, quantity, notes, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, 'adjust', abs($diffCallback), $notes, Auth::getUserId()]);
            
            // Log action
            Audit::logWarehouseTransaction($itemId, 'adjust', abs($diffCallback));
            
            if (!$inTransaction) {
                db()->commit();
            }
            return true;
        } catch (Exception $e) {
            if (!$inTransaction && db()->inTransaction()) {
                db()->rollBack();
            }
            error_log("Failed to adjust stock: " . $e->getMessage());
            return "Помилка при коригуванні: " . $e->getMessage();
        }
    }
    
    /**
     * Get transaction history for an item
     */
    public static function getTransactions($itemId, $limit = 50) {
        try {
            $stmt = db()->prepare("SELECT wt.*, 
                                  u.full_name as created_by_name,
                                  o.order_number,
                                  t.title as task_title,
                                  pr.request_number as purchase_number
                                  FROM warehouse_transactions wt
                                  LEFT JOIN users u ON wt.created_by = u.id
                                  LEFT JOIN orders o ON wt.order_id = o.id
                                  LEFT JOIN tasks t ON wt.task_id = t.id
                                  LEFT JOIN purchase_requests pr ON wt.purchase_id = pr.id
                                  WHERE wt.item_id = ?
                                  ORDER BY wt.created_at DESC
                                  LIMIT ?");
            $stmt->execute([$itemId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get transactions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get ALL transactions history
     */
    public static function getAllTransactions($limit = 100) {
        try {
            $stmt = db()->prepare("SELECT wt.*, 
                                  wi.name as item_name, wi.unit,
                                  u.full_name as created_by_name,
                                  o.order_number,
                                  t.title as task_title,
                                  pr.request_number as purchase_number
                                  FROM warehouse_transactions wt
                                  JOIN warehouse_items wi ON wt.item_id = wi.id
                                  LEFT JOIN users u ON wt.created_by = u.id
                                  LEFT JOIN orders o ON wt.order_id = o.id
                                  LEFT JOIN tasks t ON wt.task_id = t.id
                                  LEFT JOIN purchase_requests pr ON wt.purchase_id = pr.id
                                  ORDER BY wt.created_at DESC
                                  LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get all transactions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete warehouse item (Soft delete)
     */
    public static function delete($id) {
        try {
            // Check if item has non-zero quantity
            $item = self::getById($id);
            if ($item && $item['current_quantity'] > 0) {
                return "Неможливо видалити товар із залишком на складі. Спишіть його спочатку.";
            }

            $stmt = db()->prepare("UPDATE warehouse_items SET is_active = 0 WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Failed to delete warehouse item: " . $e->getMessage());
            return false;
        }
    }
}
