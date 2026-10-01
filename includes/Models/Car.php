<?php

declare(strict_types=1);

namespace App\Models;

use Exception;

/**
 * Модель автомобілів клієнтів
 *
 * Забезпечує CRUD операції для автомобілів та їх сервісну історію.
 * Кожен автомобіль прив'язаний до клієнта через client_id.
 *
 * @package App\Models
 */
class Car
{

    /**
     * Створити новий автомобіль
     *
     * @param array{client_id: int, make: string, model: string, year?: int, license_plate?: string, vin?: string, notes?: string} $data Дані автомобіля
     * @return string|false ID створеного автомобіля або false при помилці
     */
    public static function create($data)
    {
        try {
            $stmt = db()->prepare("INSERT INTO cars (client_id, make, model, year, license_plate, vin, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['client_id'],
                $data['make'],
                $data['model'],
                $data['year'] ?? null,
                $data['license_plate'] ?? null,
                $data['vin'] ?? null,
                $data['notes'] ?? null
            ]);

            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to create car: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати автомобіль за ID
     *
     * @param int $id ID автомобіля
     * @return array|null Дані автомобіля або null
     */
    public static function getById($id)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM cars WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get car: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отримати всі автомобілі клієнта
     *
     * @param int $clientId ID клієнта
     * @return array Масив автомобілів відсортованих за маркою та моделлю
     */
    public static function getByClient($clientId)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM cars WHERE client_id = ? ORDER BY make, model");
            $stmt->execute([$clientId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get cars: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Оновити дані автомобіля
     *
     * @param int   $id   ID автомобіля
     * @param array $data Масив полів для оновлення (make, model, year, license_plate, vin, notes)
     * @return bool true при успіху
     */
    public static function update($id, $data)
    {
        try {
            $allowedFields = ['make', 'model', 'year', 'license_plate', 'vin', 'notes'];
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
            $sql = "UPDATE cars SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";

            $stmt = db()->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Failed to update car: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Видалити автомобіль
     *
     * @param int $id ID автомобіля
     * @return bool true при успіху
     */
    public static function delete($id)
    {
        try {
            $stmt = db()->prepare("DELETE FROM cars WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Failed to delete car: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримати сервісну історію автомобіля
     *
     * Повертає всі послуги, які надавались цьому автомобілю,
     * з деталями замовлень та менеджерів.
     *
     * @param int $carId ID автомобіля
     * @return array Масив позицій сервісної історії
     */
    public static function getServiceHistory($carId)
    {
        try {
            $stmt = db()->prepare("
                SELECT oi.*, s.name as service_name, o.order_number, o.created_at as order_date, 
                       u.full_name as manager_name, o.id as order_id
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN services s ON oi.service_id = s.id
                LEFT JOIN users u ON o.manager_id = u.id
                WHERE o.car_id = ? AND o.is_deleted = 0
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$carId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get car service history: " . $e->getMessage());
            return [];
        }
    }
}
