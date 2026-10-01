<?php

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\Flash;
use App\Validator;
use App\Database;
use App\GoogleIntegration;
use App\Models\Order;
use App\Models\Client;
use App\Models\Car;
use App\Models\Service;
use App\Models\Task;
use App\Models\WarehouseItem;

/**
 * Контролер замовлень
 *
 * Обробляє HTTP-запити для списку (Kanban/таблиця),
 * перегляду та керування замовленнями.
 * Делегує бізнес-логіку в OrderService.
 *
 * @package App\Controllers
 */
class OrderController
{

    /**
     * Сторінка списку замовлень (Kanban / таблиця)
     *
     * @return array Дані для view: orders, ordersByStatus, clients, services, executors
     */
    public function index()
    {
        // Handle Create Action
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
            $this->handleCreate();
        }

        $viewType = $_GET['view'] ?? 'kanban';
        $tab = $_GET['tab'] ?? 'active';

        $orders = [];
        $ordersByStatus = [];

        // Define Filters based on Tab
        $filters = [];
        if ($tab === 'active') {
            // Updated to include 'approved' for backward compatibility but exclude finished/cancelled
            $filters['status'] = ['new', 'approved', 'in_progress', 'waiting_client', 'waiting_parts', 'ready', 'completed'];
        } elseif ($tab === 'history') {
            $filters['status'] = ['closed', 'cancelled'];
        } elseif ($tab === 'deleted') {
            $filters['is_deleted'] = 1;
        }

        // Fetch Orders
        $orders = Order::getList($filters);


        if ($viewType === 'kanban') {
            $ordersByStatus = [
                'new' => [],
                'in_progress' => [],
                'ready' => [],
                'closed' => [],
                'cancelled' => []
            ];

            foreach ($orders as $order) {
                $status = $order['status'];

                if ($status === 'new' || $status === 'approved') {
                    $ordersByStatus['new'][] = $order;
                } elseif (in_array($status, ['waiting_client', 'waiting_parts', 'in_progress'])) {
                    $ordersByStatus['in_progress'][] = $order;
                } elseif ($status === 'ready' || $status === 'completed') {
                    $ordersByStatus['ready'][] = $order;
                } elseif ($status === 'closed') {
                    $ordersByStatus['closed'][] = $order;
                } elseif ($status === 'cancelled') {
                    $ordersByStatus['cancelled'][] = $order;
                }
            }
        }

        // Common Data
        $totalOrders = Order::getCount();
        $clients = Client::getAll();
        $services = Service::getAll();
        $executors = array_filter(Auth::getAllUsers(), function ($u) {
            return in_array($u['role'], ['executor', 'manager', 'director']);
        });

        return [
            'viewType' => $viewType,
            'tab' => $tab,
            'orders' => $orders,
            'ordersByStatus' => $ordersByStatus,
            'totalOrders' => $totalOrders,
            'clients' => $clients,
            'services' => $services,
            'executors' => $executors
        ];
    }

    /**
     * Обробити POST-запит на створення замовлення
     *
     * @return void Редіректить на сторінку замовлення або назад з помилкою
     */
    private function handleCreate()
    {
        Csrf::verify();
        Auth::requireAnyRole(['manager', 'director']);

        try {
            $service = new \App\Services\OrderService();
            // Validation is now inside createOrder (or called explicitly if separate)
            // But since we integrated it into createOrder, we can just call createOrder.
            $orderId = $service->createOrder($_POST);

            $_SESSION['success'] = 'Замовлення створено успішно';
            redirect("/orders/view.php?id=$orderId");
        } catch (\Exception $e) {
            Flash::error($e->getMessage());
            redirect('/orders/');
        }
    }

    /**
     * Сторінка перегляду замовлення
     *
     * @param int $orderId ID замовлення
     * @return array|null Дані для view або null якщо не знайдено
     */
    public function view($orderId)
    {
        $order = Order::getById($orderId);

        if (!$order) {
            return null; // Controller will handle 404 in view
        }

        // Handle POST Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            Csrf::verify();
            try {
                $this->handleViewActions($orderId, $order);
            } catch (\Exception $e) {
                Flash::error($e->getMessage());
            }
            // If action redirect didn't happen (it usually does), we continue to render
            $order = Order::getById($orderId); // Refresh data
        }

        // Get details
        $orderItems = Order::getItems($orderId);
        $payments = Order::getPayments($orderId);
        $comments = Order::getComments($orderId);
        $relatedTasks = Task::getList(['order_id' => $orderId]);

        // Additional info for view
        $client = Client::getById($order['client_id']);
        $car = Car::getById($order['car_id']);
        $debt = $order['total_amount'] - $order['paid_amount'];

        return [
            'order' => $order,
            'orderItems' => $orderItems,
            'payments' => $payments,
            'comments' => $comments,
            'relatedTasks' => $relatedTasks,
            'client' => $client,
            'car' => $car,
            'debt' => $debt
        ];
    }

    /**
     * Обробити POST-дії зі сторінки перегляду
     *
     * Підтримує: create_task, update_status, add_payment, add_comment,
     * create_folder, soft_delete, restore, permanent_delete, process_resources.
     *
     * @param int   $orderId ID замовлення
     * @param array $order   Дані замовлення
     * @return void
     */
    private function handleViewActions($orderId, $order)
    {
        $action = $_POST['action'];

        switch ($action) {
            case 'create_task':
                $taskData = $_POST;
                $taskData['order_id'] = $orderId;
                (new \App\Services\TaskService())->createTask($taskData);

                $_SESSION['success'] = 'Задачу створено';
                redirect("/orders/view.php?id=$orderId");
                break;

            case 'update_status':
                if (Auth::hasAnyRole(['manager', 'director'])) {
                    $status = $_POST['status'];
                    if ($status === 'approved') $status = 'in_progress';
                    (new \App\Services\OrderService())->updateStatus($orderId, $status);
                    $_SESSION['success'] = 'Статус замовлення оновлено';
                    redirect("/orders/view.php?id=$orderId");
                }
                break;

            case 'add_payment':
                if (Auth::hasAnyRole(['manager', 'director'])) {
                    (new \App\Services\OrderService())->addPayment($orderId, floatval($_POST['amount']), $_POST['payment_method'], $_POST['notes'] ?? null);
                    $_SESSION['success'] = 'Платіж додано';
                    redirect("/orders/view.php?id=$orderId");
                }
                break;

            case 'add_comment':
                $commentId = Order::addComment($orderId, $_POST['comment']);
                if ($commentId) {
                    $_SESSION['success'] = 'Коментар додано';
                    redirect("/orders/view.php?id=$orderId#comments");
                }
                break;

            case 'create_folder':
                if (GoogleIntegration::isConfigured()) {
                    // Need to fetch client/car names if not passed
                    // Assuming GoogleIntegration handles it or we pass it
                    // The original code passed local $client and $car variables which were available in scope.
                    // We need to fetch them here.
                    $client = Client::getById($order['client_id']);
                    $car = Car::getById($order['car_id']);

                    $folderId = GoogleIntegration::createOrderFolder($orderId, $client['name'], $car['make'] . ' ' . $car['model']);
                    if ($folderId !== false) {
                        $_SESSION['success'] = 'Запит на створення папки додано в чергу. Вона з\'явиться на Google Drive протягом хвилини.';
                        redirect("/orders/view.php?id=$orderId");
                    }
                }
                break;

            case 'soft_delete':
                if (Auth::hasAnyRole(['manager', 'director'])) {
                    Order::softDelete($orderId);
                    $_SESSION['success'] = 'Замовлення видалено';
                    redirect("/orders/");
                }
                break;

            case 'restore':
                if (Auth::hasAnyRole(['manager', 'director'])) {
                    Order::restore($orderId);
                    $_SESSION['success'] = 'Замовлення відновлено';
                    redirect("/orders/view.php?id=$orderId");
                }
                break;

            case 'permanent_delete':
                if (Auth::hasAnyRole(['manager', 'director'])) {
                    Order::hardDelete($orderId);
                    $_SESSION['success'] = 'Замовлення остаточно видалено';
                    redirect("/orders/");
                }
                break;

            case 'process_resources':
                if (Auth::hasAnyRole(['manager', 'director'])) {
                    if (\App\Models\Setting::get('feature_warehouse', '1') != '1') {
                        $_SESSION['error'] = 'Модуль складу вимкнено';
                        redirect("/orders/view.php?id=$orderId");
                    }

                    $itemId = $_POST['warehouse_item_id'];
                    $quantity = floatval($_POST['quantity']);
                    $notes = $_POST['notes'] ?? '';

                    $result = WarehouseItem::removeStock($itemId, $quantity, $orderId, null, $notes);
                    if ($result === true) {
                        $_SESSION['success'] = 'Матеріал списано зі складу';
                    } else {
                        $_SESSION['error'] = 'Помилка при списанні: ' . $result;
                    }
                    redirect("/orders/view.php?id=$orderId");
                }
                break;
        }
    }
}
