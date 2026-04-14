-- Migration: Create coordinator_tasks table
-- Purpose: Store task assignments created by coordinators

CREATE TABLE IF NOT EXISTS coordinator_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coordinator_id INT NOT NULL,
    task_type ENUM('aptitude', 'technical', 'hr') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    company_name VARCHAR(255),
    question_source ENUM('ai', 'manual') DEFAULT 'ai' COMMENT 'AI-generated or manual questions',
    target_type ENUM('branch', 'individual', 'department') NOT NULL DEFAULT 'department',
    target_branches JSON COMMENT 'Array of branch codes within department ["CSE","ISE"]',
    target_students JSON COMMENT 'Array of student IDs',
    deadline DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_coordinator (coordinator_id),
    INDEX idx_active (is_active),
    INDEX idx_type (task_type),
    FOREIGN KEY (coordinator_id) REFERENCES dept_coordinators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
