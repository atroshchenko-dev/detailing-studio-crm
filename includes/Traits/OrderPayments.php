<?php

declare(strict_types=1);

namespace App\Traits;

use App\Auth;
use App\Audit;
use Exception;

/**
 * Order Payments and Comments
 */
trait OrderPayments
{
    /**
     * Add payment
     */
    public static function addPayment($orderId, $amount, $method, $notes = null)
    {
        try {
            $stmt = db()->prepare("INSERT INTO order_payments (order_id, amount, payment_method, payment_date, notes, created_by) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$orderId, $amount, $method, $notes, Auth::getUserId()]);

            // Update order paid amount
            $order = self::getById($orderId);
            $newPaidAmount = $order['paid_amount'] + $amount;

            $stmt = db()->prepare("UPDATE orders SET paid_amount = ? WHERE id = ?");
            $stmt->execute([$newPaidAmount, $orderId]);

            // Log payment
            Audit::logPayment($orderId, $amount, $method, $notes);

            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to add payment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get order payments
     */
    public static function getPayments($orderId)
    {
        try {
            $stmt = db()->prepare("SELECT op.*, u.full_name as created_by_name 
                                  FROM order_payments op
                                  JOIN users u ON op.created_by = u.id
                                  WHERE op.order_id = ?
                                  ORDER BY op.payment_date DESC");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get payments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get order comments
     */
    public static function getComments($orderId)
    {
        try {
            $stmt = db()->prepare("SELECT oc.*, u.full_name as user_name 
                                  FROM order_comments oc
                                  JOIN users u ON oc.user_id = u.id
                                  WHERE oc.order_id = ?
                                  ORDER BY oc.created_at DESC");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get comments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add comment
     */
    public static function addComment($orderId, $comment, $mediaLinks = null)
    {
        try {
            $stmt = db()->prepare("INSERT INTO order_comments (order_id, user_id, comment, media_links) VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, Auth::getUserId(), $comment, $mediaLinks ? json_encode($mediaLinks) : null]);

            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to add comment: " . $e->getMessage());
            return false;
        }
    }
}
