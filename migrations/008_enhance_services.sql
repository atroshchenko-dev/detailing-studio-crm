-- Migration: Enhance services with categories and addons
-- Description: Create service_categories and service_addons tables, update services table

CREATE TABLE IF NOT EXISTS `service_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `color_class` varchar(50) DEFAULT 'bg-secondary',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed initial categories based on current usage
INSERT INTO `service_categories` (name, color_class) VALUES 
('Зовнішній детейлінг', 'bg-info'),
('Внутрішній детейлінг', 'bg-primary'),
('Захист', 'bg-success'),
('Додаткові послуги', 'bg-warning');

-- Add category_id to services
ALTER TABLE `services` ADD COLUMN `category_id` int(11) DEFAULT NULL AFTER `category`;

-- Migration data from string category to category_id
UPDATE services s SET category_id = (SELECT id FROM service_categories sc WHERE sc.name = s.category OR (s.category = 'Зовнішнє детейлінг' AND sc.name = 'Зовнішній детейлінг') OR (s.category = 'Внутрішнє детейлінг' AND sc.name = 'Внутрішній детейлінг')) WHERE category_id IS NULL;

-- Create service_addons table
CREATE TABLE IF NOT EXISTS `service_addons` (
  `service_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  PRIMARY KEY (`service_id`, `addon_id`),
  CONSTRAINT `fk_addon_parent` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_addon_child` FOREIGN KEY (`addon_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
