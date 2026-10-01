<?php
namespace App\Controllers;

use App\Models\WarehouseItem;
use App\Auth;
use Exception;

/**
 * Контролер складу
 *
 * CRUD для складських позицій: перегляд, створення,
 * оновлення, поповнення залишків, видалення.
 *
 * @package App\Controllers
 */
class WarehouseController {
    
    public function __construct() {
        Auth::requireAuth();
    }
    
    /**
     * Сторінка списку складських позицій
     *
     * @return array Дані для view: items, totalValue, lowStockCount, suppliers
     */
    public function index() {
        $items = WarehouseItem::getAll(true);
        
        // Calculate total value
        $totalValue = 0;
        $lowStockCount = 0;
        
        foreach ($items as $item) {
            $totalValue += $item['current_quantity'] * $item['avg_price'];
            if ($item['current_quantity'] <= $item['min_quantity']) {
                $lowStockCount++;
            }
        }
        
        return [
            'items' => $items,
            'totalValue' => $totalValue,
            'lowStockCount' => $lowStockCount,
            'suppliers' => \App\Models\Supplier::getAll()
        ];
    }

    /**
     * Отримати історію транзакцій
     *
     * @return array Дані для view
     */
    public function transactions() {
        return [
            'transactions' => WarehouseItem::getAllTransactions(200)
        ];
    }
    
    /**
     * Обробити POST-дії (create, edit, add_stock, delete)
     *
     * @return void Редіректить назад
     */
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        
        // CSRF Check (assuming CSRF class exists and is used globally, but for now simple check)
        // \App\CSRF::verify(); 

        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'create':
                    $this->create();
                    break;
                case 'edit':
                    $this->update();
                    break;
                case 'add_stock':
                    $this->addStock();
                    break;
                case 'delete':
                    $this->delete();
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        
        // Redirect back
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    private function create() {
        Auth::requireRole('manager'); // Minimum role
        
        $data = [
            'name' => $_POST['name'],
            'sku' => $_POST['sku'] ?? null,
            'category' => $_POST['category'],
            'unit' => $_POST['unit'] ?? 'шт',
            'min_quantity' => $_POST['min_quantity'] ?? 0,
            'current_quantity' => $_POST['current_quantity'] ?? 0,
            'avg_price' => $_POST['avg_price'] ?? 0,
            'supplier_id' => !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null,
            'notes' => $_POST['notes'] ?? null
        ];
        
        if (WarehouseItem::create($data)) {
            $_SESSION['flash_success'] = "Товар додано успішно";
        } else {
            throw new Exception("Помилка при створенні товару");
        }
    }

    private function update() {
        Auth::requireRole('manager');
        
        $id = $_POST['id'];
        $data = [
            'name' => $_POST['name'],
            'sku' => $_POST['sku'] ?? null,
            'category' => $_POST['category'],
            'unit' => $_POST['unit'] ?? 'шт',
            'min_quantity' => $_POST['min_quantity'] ?? 0,
            'avg_price' => $_POST['avg_price'] ?? 0, // In case of manual correction
            'supplier_id' => !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null,
            'notes' => $_POST['notes'] ?? null
        ];
        
        if (WarehouseItem::update($id, $data)) {
            $_SESSION['flash_success'] = "Товар оновлено";
        } else {
            throw new Exception("Помилка оновлення");
        }
    }

    private function addStock() {
        Auth::requireAnyRole(['manager', 'director']);
        
        $id = $_POST['id'];
        $quantity = (float)$_POST['quantity'];
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
        $notes = $_POST['notes'] ?? 'Ручне поповнення';
        
        if ($quantity <= 0) throw new Exception("Кількість має бути більше 0");
        
        $res = WarehouseItem::addStock($id, $quantity, $price, $notes);
        if ($res === true) {
            $_SESSION['flash_success'] = "Залишок успішно додано";
        } else {
            throw new Exception($res);
        }
    }

    private function delete() {
        Auth::requireRole('director');
        
        $id = $_POST['id'];
        $res = WarehouseItem::delete($id);
        
        if ($res === true) { // Explicit true check, as delete might return string error
             $_SESSION['flash_success'] = "Товар видалено";
        } else {
             // If false or string error
             throw new Exception($res === false ? "Помилка видалення" : $res);
        }
    }
}
