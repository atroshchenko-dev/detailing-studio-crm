-- Remove obsolete task statuses "paused" and "revision"
-- Update tasks table enum
ALTER TABLE `tasks` MODIFY COLUMN `status` ENUM('created', 'accepted', 'in_progress', 'completed') NOT NULL DEFAULT 'created';

-- Update v_task_overview view to remove "revision" from overdue logic
CREATE OR REPLACE VIEW `v_task_overview` AS
SELECT 
    t.id,
    t.title,
    t.status,
    t.priority,
    t.deadline,
    creator.full_name as creator_name,
    executor.full_name as executor_name,
    o.order_number,
    CASE 
        WHEN t.deadline < NOW() AND t.status NOT IN ('completed') THEN 1
        ELSE 0
    END as is_overdue
FROM tasks t
LEFT JOIN users creator ON t.creator_id = creator.id
LEFT JOIN users executor ON t.executor_id = executor.id
LEFT JOIN orders o ON t.order_id = o.id;
