-- Order Photos Table for Before/After Gallery
CREATE TABLE IF NOT EXISTS `order_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `photo_type` enum('before','after','process') NOT NULL DEFAULT 'before',
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `google_drive_id` varchar(255) DEFAULT NULL COMMENT 'Google Drive file ID',
  `caption` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `photo_type` (`photo_type`),
  CONSTRAINT `fk_photo_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_photo_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
