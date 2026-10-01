<?php
declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Audit;
use Exception;
use PDO;

/**
 * Purchase Requests Model
 */
class PurchaseRequest {
    
    /**
     * Create purchase request
     */
    public static function create($data) {
        try {
            $requestNumber = generate_purchase_request_number();
            
            $stmt = db()->prepare("INSERT INTO purchase_requests (request_number, supplier_id, requested_by, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $requestNumber,
                $data['supplier_id'],
                Auth::getUserId(),
                $data['notes'] ?? null
            ]);
            
            $requestId = db()->lastInsertId();
            
            // Add items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    self::addItem($requestId, $item['item_id'], $item['quantity'], $item['unit_price'] ?? 0);
                }
            }
            
            // Log action
            Audit::log('purchase_created', 'purchase_requests', $requestId, null, ['status' => 'pending']);
            
            return $requestId;
        } catch (Exception $e) {
            error_log("Failed to create purchase request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get purchase request by ID
     */
    public static function getById($id) {
        try {
            $stmt = db()->prepare("SELECT pr.*, 
                                  s.name as supplier_name,
                                  req.full_name as requested_by_name,
                                  app.full_name as approved_by_name
                                  FROM purchase_requests pr
                                  JOIN suppliers s ON pr.supplier_id = s.id
                                  JOIN users req ON pr.requested_by = req.id
                                  LEFT JOIN users app ON pr.approved_by = app.id
                                  WHERE pr.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get purchase request: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get purchase requests with filters
     */
    public static function getList($filters = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['status'])) {
                $where[] = "pr.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['supplier_id'])) {
                $where[] = "pr.supplier_id = ?";
                $params[] = $filters['supplier_id'];
            }
            
            if (!Auth::isDirector()) {
                $where[] = "pr.requested_by = ?";
                $params[] = Auth::getUserId();
            }
            
            $whereSql = $where ? "WHERE " . implode(' AND ', $where) : "";
            
            $sql = "SELECT pr.*, 
                    s.name as supplier_name,
                    req.full_name as requested_by_name
                    FROM purchase_requests pr
                    JOIN suppliers s ON pr.supplier_id = s.id
                    JOIN users req ON pr.requested_by = req.id
                    $whereSql
                    ORDER BY pr.created_at DESC";
            
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get purchase requests: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update purchase request
     */
    public static function update($id, $data) {
        try {
            $oldRequest = self::getById($id);
            if (!$oldRequest) return false;
            
            $allowedFields = ['status', 'notes'];
            $updates = [];
            $values = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            // Handle status changes
            if (isset($data['status'])) {
                switch ($data['status']) {
                    case 'approved':
                        $updates[] = "approved_by = ?";
                        $updates[] = "approved_at = NOW()";
                        $values[] = Auth::getUserId();
                        break;
                    case 'ordered':
                        $updates[] = "ordered_at = NOW()";
                        break;
                    case 'received':
                        $updates[] = "received_at = NOW()";
                        break;
                }
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $values[] = $id;
            $sql = "UPDATE purchase_requests SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = db()->prepare($sql);
            $result = $stmt->execute($values);
            
            // Log changes
            if (isset($data['status']) && $data['status'] != $oldRequest['status']) {
                Audit::logPurchaseStatusChange($id, $oldRequest['status'], $data['status']);
                
                // Auto-receive items if status changed to received
                if ($data['status'] == 'received') {
                    self::receiveItems($id);
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Failed to update purchase request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add item to purchase request
     */
    public static function addItem($requestId, $itemId, $quantity, $unitPrice) {
        try {
            $totalPrice = $quantity * $unitPrice;
            
            $stmt = db()->prepare("INSERT INTO purchase_request_items (request_id, item_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$requestId, $itemId, $quantity, $unitPrice, $totalPrice]);
            
            // Recalculate total
            self::recalculateTotal($requestId);
            
            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to add purchase request item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get purchase request items
     */
    public static function getItems($requestId) {
        try {
            $stmt = db()->prepare("SELECT pri.*, wi.name as item_name, wi.unit, wi.sku
                                  FROM purchase_request_items pri
                                  JOIN warehouse_items wi ON pri.item_id = wi.id
                                  WHERE pri.request_id = ?");
            $stmt->execute([$requestId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get purchase request items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Recalculate total
     */
    private static function recalculateTotal($requestId) {
        try {
            $stmt = db()->prepare("SELECT SUM(total_price) as total FROM purchase_request_items WHERE request_id = ?");
            $stmt->execute([$requestId]);
            $result = $stmt->fetch();
            
            $total = $result['total'] ?? 0;
            
            $stmt = db()->prepare("UPDATE purchase_requests SET total_amount = ? WHERE id = ?");
            return $stmt->execute([$total, $requestId]);
        } catch (Exception $e) {
            error_log("Failed to recalculate total: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Receive items (auto-add to warehouse)
     */
    private static function receiveItems($requestId) {
        try {
            $items = self::getItems($requestId);
            $request = self::getById($requestId);
            
            foreach ($items as $item) {
                WarehouseItem::addStock(
                    $item['item_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    "Отримано по заявці {$request['request_number']}",
                    $requestId
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to receive items: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete purchase request with stock reversal
     */
    public static function delete($id) {
        try {
            $db = db();
            $request = self::getById($id);
            if (!$request) {
                return "Заявку не знайдено";
            }

            $inTransaction = $db->inTransaction();
            if (!$inTransaction) {
                $db->beginTransaction();
            }

            // If status is received, we must reverse stock additions
            if ($request['status'] === 'received') {
                $items = self::getItems($id);
                foreach ($items as $item) {
                    // WarehouseItem::addStock with negative quantity to reverse
                    $result = WarehouseItem::addStock(
                        $item['item_id'],
                        -$item['quantity'],
                        (float)$item['unit_price'], 
                        "Скасування отримання по заявці {$request['request_number']}",
                        $id
                    );
                    
                    if ($result !== true) {
                        throw new Exception($result);
                    }
                }
            }

            // Delete items
            $stmt = $db->prepare("DELETE FROM purchase_request_items WHERE request_id = ?");
            $stmt->execute([$id]);

            // Delete request
            $stmt = $db->prepare("DELETE FROM purchase_requests WHERE id = ?");
            $stmt->execute([$id]);

            if (!$inTransaction) {
                $db->commit();
            }
            
            // Log action
            Audit::log('purchase_deleted', 'purchase_requests', $id, $request, null);
            
            return true;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Failed to delete purchase request: " . $e->getMessage());
            return $e->getMessage();
        }
    }
}
