-- Migration: Create task_completions table
-- Purpose: Track student completion of coordinator-assigned tasks

CREATE TABLE IF NOT EXISTS task_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    score DECIMAL(5,2) COMMENT 'Percentage score (0-100)',
    time_taken INT COMMENT 'Time in seconds',
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_completion (task_id, student_id),
    FOREIGN KEY (task_id) REFERENCES coordinator_tasks(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_task (task_id),
    INDEX idx_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
