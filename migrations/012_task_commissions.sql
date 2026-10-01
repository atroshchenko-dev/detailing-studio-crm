-- Migration: Task Commissions
-- Description: Add fields to tasks to track executor commissions

ALTER TABLE `tasks`
ADD COLUMN `commission_amount` decimal(10,2) DEFAULT 0.00 AFTER `status`,
ADD COLUMN `is_commission_paid` tinyint(1) DEFAULT 0 AFTER `commission_amount`;
