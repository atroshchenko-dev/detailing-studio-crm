-- Migration: Make audit_log.user_id nullable and update foreign key
-- Date: 2026-02-12

ALTER TABLE `audit_log` DROP FOREIGN KEY `fk_audit_user`;
ALTER TABLE `audit_log` MODIFY `user_id` int(11) NULL;
ALTER TABLE `audit_log` ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
