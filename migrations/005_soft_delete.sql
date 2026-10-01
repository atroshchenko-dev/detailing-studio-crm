-- Add soft delete columns
ALTER TABLE tasks ADD COLUMN is_deleted TINYINT(1) DEFAULT 0;
ALTER TABLE orders ADD COLUMN is_deleted TINYINT(1) DEFAULT 0;
