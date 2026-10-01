-- Migration: Service Resource Templates
-- Description: Create a table to define warehouse items required for specific services

CREATE TABLE IF NOT EXISTS `service_resource_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `service_id` int(11) NOT NULL,
    `warehouse_item_id` int(11) NOT NULL,
    `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
    `notes` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `service_id` (`service_id`),
    KEY `warehouse_item_id` (`warehouse_item_id`),
    CONSTRAINT `srt_service_fk` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
    CONSTRAINT `srt_item_fk` FOREIGN KEY (`warehouse_item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
