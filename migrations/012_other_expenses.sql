-- Migration: 012_other_expenses.sql
-- Description: Create expenses table for general studio costs (rent, utilities, etc.)

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(50) NOT NULL, -- rent, utilities, marketing, materials, maintenance, other
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('cash', 'card', 'fop') NOT NULL DEFAULT 'cash',
    `payment_date` DATE NOT NULL,
    `notes` TEXT,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for reporting
CREATE INDEX `idx_expenses_date` ON `expenses`(`payment_date`);
CREATE INDEX `idx_expenses_category` ON `expenses`(`category`);
