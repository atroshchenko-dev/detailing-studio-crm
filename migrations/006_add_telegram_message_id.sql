-- Migration: Add telegram_message_id to telegram_notifications
ALTER TABLE `telegram_notifications` ADD COLUMN `telegram_message_id` BIGINT DEFAULT NULL AFTER `chat_id`;
