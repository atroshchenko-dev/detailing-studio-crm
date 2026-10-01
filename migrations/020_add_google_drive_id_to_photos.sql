-- Migration: Add Google Drive ID to order photos
-- Date: 2026-01-26

ALTER TABLE order_photos ADD COLUMN google_drive_id VARCHAR(255) DEFAULT NULL AFTER file_size;
ALTER TABLE order_photos ADD INDEX idx_google_drive_id (google_drive_id);
