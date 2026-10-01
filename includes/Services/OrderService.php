<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Client;
use App\Models\Car;
use App\Models\Task;
use App\Models\WarehouseItem;
use App\GoogleIntegration;
use App\Validator;
use Exception;

/**
 * Сервіс управління замовленнями
 *
 * Інкапсулює бізнес-логіку: створення замовлення (з клієнтом/авто),
 * зміна статусу (з перевіркою боргу), платежі, синхронізація задач.
 * Диспатчить доменні події після commit.
 *
 * @package App\Services
 */
class OrderService
{

    /**
     * Валідувати дані замовлення
     *
     * @param array $data Дані з форми
     * @return void
     * @throws Exception При помилці валідації
     */
    public function validateOrderData(array $data): void
    {
        $validator = new Validator($data);

        if (isset($data['is_new_client']) && $data['is_new_client'] == '1') {
            $validator->required('new_client_name', 'ПІБ Клієнта')
                ->required('new_client_phone', 'Телефон');
        } else {
            // If not new client, client_id must be present?
            // Validator doesn't support conditional strict check easily without custom logic,
            // but we can check if client_id is set if is_new_client is not set.
            // For now, mirroring controller logic which was:
        }

        if (!$validator->isValid()) {
            throw new Exception('Помилка валідації: ' . implode(', ', $validator->getErrors()));
        }
    }

    /**
     * Створити нове замовлення з клієнтом, авто та послугами
     *
     * Виконується в транзакції. Диспатчить OrderCreated після commit.
     *
     * @param array $data        Дані з форми (клієнт, авто, послуги, дати тощо)
     * @param array $userContext  Опціональний контекст користувача
     * @return int ID створеного замовлення
     * @throws Exception При помилці валідації або БД
     */
    public function createOrder(array $data, array $userContext = []): int
    {
        $db = db();
        try {
            // Validate first
            $this->validateOrderData($data);

            // Start Transaction
            $db->beginTransaction();

            // 1. Handle Client
            $clientId = $data['client_id'] ?? null;
            if (isset($data['is_new_client']) && $data['is_new_client'] == '1') {
                $clientId = $this->createClient($data);
            }

            // 2. Handle Car
            $carId = $data['car_id'] ?? null;
            if (isset($data['is_new_car']) && $data['is_new_car'] == '1') {
                $carId = $this->createCar($data, $clientId);
            }

            // 3. Create Order
            $totalAmount = floatval($data['total_amount'] ?? 0);
            $discountPercent = floatval($data['discount_percent'] ?? 0);
            $discountAmount = ($totalAmount * $discountPercent) / 100;

            $orderId = Order::create([
                'client_id' => $clientId,
                'car_id' => $carId,
                'status' => 'in_progress',
                'total_amount' => $totalAmount,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'notes' => $data['notes'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'stall_id' => !empty($data['stall_id']) ? (int)$data['stall_id'] : null,
                'planned_start' => !empty($data['planned_start']) ? $data['planned_start'] : null,
                'planned_end' => !empty($data['planned_end']) ? $data['planned_end'] : null
            ]);

            if (!$orderId) {
                throw new Exception("Не вдалося створити запис замовлення в БД");
            }

            $orderId = (int)$orderId;

            // 4. Add Services (Items)
            if (!empty($data['services'])) {
                foreach ($data['services'] as $serviceId => $serviceData) {
                    if (!empty($serviceData['selected'])) {
                        Order::addItem(
                            $orderId,
                            $serviceId,
                            $serviceData['quantity'] ?? 1,
                            $serviceData['price'] ?? null,
                            !empty($serviceData['executor']) ? $serviceData['executor'] : null
                        );

                        // Auto-create task if requested manually via checkbox
                        if (!empty($serviceData['create_task'])) {
                            $service = \App\Models\Service::getById($serviceId);
                            $executors = !empty($serviceData['task_executors']) ? $serviceData['task_executors'] : (!empty($serviceData['executor']) ? [$serviceData['executor']] : []);

                            $order = Order::getById($orderId);

                            Task::create([
                                'title' => $service['name'],
                                'description' => "Задача для послуги: " . $service['name'] . "\nЗамовлення: " . $order['order_number'],
                                'order_id' => $orderId,
                                'priority' => $serviceData['task_priority'] ?? 'medium',
                                'deadline' => !empty($serviceData['task_deadline']) ? $serviceData['task_deadline'] : ($data['deadline'] ?? null),
                                'executors' => $executors
                            ]);
                        }
                    }
                }
            }

            // Commit Transaction
            $db->commit();

            // 5. Dispatch Order Created Event (After Commit)
            \App\Events\EventDispatcher::dispatch(new \App\Events\OrderCreated($orderId, $data));

            return $orderId;
        } catch (Exception $e) {
            // Rollback if active
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            \App\Logger::error("OrderService::createOrder failed: " . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create Client Helper
     */
    private function createClient(array $data): int
    {
        $id = Client::create([
            'name' => $data['new_client_name'],
            'phone' => $data['new_client_phone'],
            'email' => $data['new_client_email'] ?? null,
            'notes' => $data['new_client_notes'] ?? null
        ]);
        if (!$id) throw new Exception("Failed to create client");
        return (int)$id;
    }

    /**
     * Create Car Helper
     */
    private function createCar(array $data, int $clientId): int
    {
        $notes = $data['new_car_notes'] ?? '';
        if (isset($data['new_car_restyling']) && $data['new_car_restyling'] == '1') {
            $notes .= " (Restyling)";
        }

        // Logic to determine Make Name
        $makeName = '';
        if (!empty($data['new_car_make_custom'])) {
            $makeName = trim($data['new_car_make_custom']);
        } elseif (!empty($data['new_car_make']) && $data['new_car_make'] !== 'other') {
            $stmt = \db()->prepare("SELECT name FROM car_makes WHERE id = ?");
            $stmt->execute([$data['new_car_make']]);
            $makeRes = $stmt->fetch();
            $makeName = $makeRes['name'] ?? '';
        }

        if (empty($makeName)) {
            throw new Exception("Не вказано марку автомобіля");
        }

        // Logic to determine Model Name
        $modelName = '';
        if (!empty($data['new_car_model_custom'])) {
            $modelName = trim($data['new_car_model_custom']);
        } elseif (!empty($data['new_car_model']) && $data['new_car_model'] !== 'other') {
            // If generic models are stored or just text passed. 
            // Assuming car_models table exists or we accept text?
            // Usually models are just text or linked. If linked:
            $stmt = \db()->prepare("SELECT name FROM car_models WHERE id = ?");
            $stmt->execute([$data['new_car_model']]);
            $modelRes = $stmt->fetch();
            $modelName = $modelRes['name'] ?? $data['new_car_model'];
        }

        if (empty($modelName)) {
            // Allow empty model? Maybe no.
            throw new Exception("Не вказано модель автомобіля");
        }

        $id = Car::create([
            'client_id' => $clientId,
            'make' => $makeName,
            'model' => $modelName,
            'year' => !empty($data['new_car_year']) ? $data['new_car_year'] : null,
            'license_plate' => $data['new_car_plate'] ?? null,
            'vin' => $data['new_car_vin'] ?? null,
            'notes' => $notes
        ]);
        if (!$id) throw new Exception("Failed to create car");
        return (int)$id;
    }

    /**
     * Змінити статус замовлення
     *
     * Перевіряє борг при закритті, синхронізує задачі,
     * логує в audit та диспатчить OrderStatusChanged.
     *
     * @param int    $orderId ID замовлення
     * @param string $status  Новий статус
     * @return bool true при успіху
     * @throws Exception При недопустимому статусі або борзі
     */
    public function updateStatus(int $orderId, string $status): bool
    {
        $allowedStatuses = [
            'new',
            'approved',
            'in_progress',
            'waiting_parts',
            'closed',
            'completed',
            'ready',
            'cancelled',
            'waiting_client'
        ];

        if (!in_array($status, $allowedStatuses)) {
            throw new Exception("Invalid status: $status");
        }

        // Get old status for event
        $oldOrder = Order::getById($orderId);
        $oldStatus = $oldOrder['status'] ?? 'unknown';

        // Debt Check for Closed status
        if ($status === 'closed' && $oldStatus !== 'closed') {
            $debt = ($oldOrder['total_amount'] ?? 0) - ($oldOrder['paid_amount'] ?? 0);
            if ($debt > 0.001) {
                if ($oldStatus === 'ready') {
                    // Automatically add payment if moving from 'ready'
                    $this->addPayment($orderId, $debt, 'cash', 'Автоматична оплата при закритті');
                    // Refresh order data to get updated paid_amount for audit log
                    $oldOrder = Order::getById($orderId);
                } else {
                    // Check if user allowed to close with debt? (For now, strict check)
                    // Or maybe we pass a flag?
                    // Throwing exception for now, UI should handle it.
                    throw new Exception("Не можна закрити замовлення з боргом: " . $debt . " грн. Спочатку додайте оплату.");
                }
            }
        }

        $result = Order::update($orderId, ['status' => $status]);
        if ($result) {
            \App\Audit::log('order_status_updated', 'orders', $orderId, null, ['new_status' => $status]);

            // Dispatch event for webhooks/notifications
            $freshOrder = Order::getById($orderId);
            $user = \App\Auth::getUser();

            \App\Events\EventDispatcher::dispatch(new \App\Events\OrderStatusChanged(
                $freshOrder,
                $oldStatus,
                $status,
                $user
            ));

            // Sync Tasks
            try {
                if ($status === 'closed') {
                    // Auto-complete all open tasks
                    $stmt = \db()->prepare("SELECT id FROM tasks WHERE order_id = ? AND status NOT IN ('closed', 'cancelled')");
                    $stmt->execute([$orderId]);
                    while ($row = $stmt->fetch()) {
                        // Move to closed status (final execution)
                        Task::update($row['id'], ['status' => 'closed']);
                    }
                } elseif ($status === 'cancelled') {
                    // Auto-cancel all open tasks
                    $stmt = \db()->prepare("SELECT id FROM tasks WHERE order_id = ? AND status NOT IN ('closed', 'cancelled')");
                    $stmt->execute([$orderId]);
                    while ($row = $stmt->fetch()) {
                        Task::update($row['id'], ['status' => 'cancelled']);
                    }
                }
            } catch (Exception $e) {
                \App\Logger::error("Failed to sync tasks for order $orderId: " . $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Додати платіж до замовлення
     *
     * @param int         $orderId ID замовлення
     * @param float       $amount  Сума платежу (> 0)
     * @param string      $method  Спосіб оплати (cash, card, transfer)
     * @param string|null $notes   Примітка
     * @return string|false ID платежу
     * @throws Exception При невалідних даних
     */
    public function addPayment(int $orderId, float $amount, string $method, ?string $notes = null)
    {
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }

        if (empty($method)) {
            throw new Exception("Payment method is required");
        }

        $paymentId = Order::addPayment($orderId, $amount, $method, $notes);
        return $paymentId;
    }
}
