ALTER TABLE services ADD COLUMN auto_create_task TINYINT(1) DEFAULT 0 AFTER is_active;
