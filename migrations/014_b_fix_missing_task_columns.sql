-- Migration: Fix missing columns in tasks
-- Description: Add service_id to tasks

ALTER TABLE `tasks` ADD COLUMN `service_id` int(11) DEFAULT NULL AFTER `order_id`;
ALTER TABLE `tasks` ADD CONSTRAINT `fk_task_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;
