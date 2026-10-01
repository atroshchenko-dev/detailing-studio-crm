<?php

declare(strict_types=1);

namespace App;

use Exception;
use PDO;

/**
 * Управління станом діалогів Telegram бота
 *
 * Зберігає стан покрокових діалогів (Wizards) в таблиці
 * telegram_conversations: chat_id → step + JSON data.
 * Підтримує cleanup повідомлень через _cleanup_ids.
 *
 * @package App
 */
class TelegramConversation
{
    /**
     * Get current conversation state
     */
    public static function get($chatId)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM telegram_conversations WHERE chat_id = ? AND step != 'rate_limit'");
            $stmt->execute([$chatId]);
            $conv = $stmt->fetch();

            if ($conv) {
                $decoded = json_decode($conv['data'], true);
                $conv['data'] = is_array($decoded) ? $decoded : [];
            }

            return $conv;
        } catch (Exception $e) {
            error_log("TelegramConversation::get Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Start or update conversation
     */
    public static function set($chatId, $step, $data = [], $userId = null)
    {
        try {
            // Check if exists (excluding rate_limit records)
            $current = self::get($chatId);

            if ($current) {
                // Merge data if updating
                $newData = array_merge($current['data'] ?? [], $data);

                $stmt = db()->prepare("UPDATE telegram_conversations SET step = ?, data = ?, user_id = IFNULL(?, user_id), updated_at = NOW() WHERE chat_id = ? AND step != 'rate_limit'");
                $stmt->execute([$step, json_encode($newData), $userId, $chatId]);
            } else {
                // Use INSERT ... ON DUPLICATE KEY UPDATE to handle rate_limit records that get() filters out
                $stmt = db()->prepare("INSERT INTO telegram_conversations (chat_id, user_id, step, data) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE step = VALUES(step), data = VALUES(data), user_id = IFNULL(VALUES(user_id), user_id), updated_at = NOW()");
                $stmt->execute([$chatId, $userId, $step, json_encode($data)]);
            }

            return true;
        } catch (Exception $e) {
            error_log("TelegramConversation::set Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear conversation (finish)
     */
    public static function clear($chatId)
    {
        try {
            $stmt = db()->prepare("DELETE FROM telegram_conversations WHERE chat_id = ?");
            return $stmt->execute([$chatId]);
        } catch (Exception $e) {
            error_log("TelegramConversation::clear Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store temporary data in conversation without changing step
     */
    public static function updateData($chatId, $key, $value)
    {
        $current = self::get($chatId);
        if ($current) {
            $data = $current['data'] ?? [];
            $data[$key] = $value;
            return self::set($chatId, $current['step'], $data);
        }
        return false;
    }

    /**
     * Record a message ID to be cleaned up later
     */
    public static function addMessageId($chatId, $messageId)
    {
        if (!$messageId) return false;

        $current = self::get($chatId);
        if ($current) {
            $data = $current['data'] ?? [];
            $currentList = $data['_cleanup_ids'] ?? [];

            // Avoid duplicates
            if (!in_array($messageId, $currentList)) {
                $currentList[] = $messageId;
                $data['_cleanup_ids'] = $currentList;
                return self::set($chatId, $current['step'], $data);
            }
        }
        return false;
    }

    /**
     * Get all recorded message IDs
     */
    public static function getMessages($chatId)
    {
        $current = self::get($chatId);
        if ($current) {
            return $current['data']['_cleanup_ids'] ?? [];
        }
        return [];
    }

    /**
     * Update conversation step and data
     */
    public static function update($chatId, $step, $data = [])
    {
        return self::set($chatId, $step, $data);
    }
}
