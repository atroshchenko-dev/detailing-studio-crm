-- Migration: Service Commissions
-- Description: Add fields to services and tasks to track executor commissions

ALTER TABLE `services` 
ADD COLUMN `commission_type` enum('percentage', 'fixed') DEFAULT 'percentage' AFTER `base_price`,
ADD COLUMN `commission_value` decimal(10,2) DEFAULT 0.00 AFTER `commission_type`;


-- Table for car category specific commissions (if needed for more complexity)
CREATE TABLE IF NOT EXISTS `service_car_category_commissions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `service_id` int(11) NOT NULL,
    `car_category` varchar(50) NOT NULL, -- e.g., 'sedan', 'suv', 'luxury'
    `commission_value` decimal(10,2) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `service_car` (`service_id`, `car_category`),
    CONSTRAINT `sccc_service_fk` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
