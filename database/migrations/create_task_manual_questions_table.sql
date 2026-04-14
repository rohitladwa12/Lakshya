-- Migration: Create task_manual_questions table
-- Purpose: Store manually-added questions for coordinator tasks

CREATE TABLE IF NOT EXISTS task_manual_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option CHAR(1) NOT NULL COMMENT 'A, B, C, or D',
    explanation TEXT,
    question_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES coordinator_tasks(id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_order (question_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
