CREATE TABLE IF NOT EXISTS task_executors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_task_user (task_id, user_id)
);

-- Migrate existing executors if table was just created or empty
INSERT IGNORE INTO task_executors (task_id, user_id)
SELECT id, executor_id FROM tasks WHERE executor_id IS NOT NULL;
