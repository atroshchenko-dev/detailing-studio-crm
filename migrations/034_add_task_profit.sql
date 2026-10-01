-- Migration: 034_add_task_profit.sql
-- Description: Add profit_amount column to tasks table to track standalone income

ALTER TABLE `tasks` ADD COLUMN `profit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `status`;
