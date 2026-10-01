<?php
declare(strict_types=1);

namespace App\Models;

use Exception;

/**
 * Модель категорій послуг
 *
 * @package App\Models
 */
class ServiceCategory {
    /**
     * Отримати всі категорії
     *
     * @return array Масив категорій відсортованих за display_order та name
     */
    public static function getAll() {
        try {
            return db()->query("SELECT * FROM service_categories ORDER BY display_order, name")->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Отримати категорію за ID
     *
     * @param int $id ID категорії
     * @return array|null Дані категорії або null
     */
    public static function getById($id) {
        try {
            $stmt = db()->prepare("SELECT * FROM service_categories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Створити нову категорію
     *
     * @param array{name: string, color_class?: string, display_order?: int} $data Дані категорії
     * @return bool true при успіху
     */
    public static function create($data) {
        try {
            $stmt = db()->prepare("INSERT INTO service_categories (name, color_class, display_order) VALUES (?, ?, ?)");
            return $stmt->execute([$data['name'], $data['color_class'] ?? 'bg-secondary', $data['display_order'] ?? 0]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Оновити категорію
     *
     * @param int   $id   ID категорії
     * @param array $data Дані для оновлення (name, color_class, display_order)
     * @return bool true при успіху
     */
    public static function update($id, $data) {
        try {
            $stmt = db()->prepare("UPDATE service_categories SET name = ?, color_class = ?, display_order = ? WHERE id = ?");
            return $stmt->execute([$data['name'], $data['color_class'], $data['display_order'], $id]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Видалити категорію
     *
     * Перевіряє чи є послуги, прив'язані до цієї категорії.
     *
     * @param int $id ID категорії
     * @return bool|string true при успіху або рядок з помилкою
     */
    public static function delete($id) {
        try {
            // Check usage
            $stmt = db()->prepare("SELECT COUNT(*) FROM services WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) return "На цю категорію посилаються послуги. Неможливо видалити.";

            $stmt = db()->prepare("DELETE FROM service_categories WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }
}
