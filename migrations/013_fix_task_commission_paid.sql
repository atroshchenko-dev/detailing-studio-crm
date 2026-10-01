-- Migration: Fix Task Commission Paid Column
-- Description: Add is_commission_paid separately because previous migration failed due to partial duplication

ALTER TABLE `tasks`
ADD COLUMN `is_commission_paid` tinyint(1) DEFAULT 0 AFTER `commission_amount`;
