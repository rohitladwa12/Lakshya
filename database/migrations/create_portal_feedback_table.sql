-- Create Portal Feedback Table (2026-06-11)
CREATE TABLE IF NOT EXISTS portal_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    institution VARCHAR(20) NOT NULL DEFAULT 'GMU',
    current_sem TINYINT(2) NULL,
    branch VARCHAR(100) NULL,
    
    -- General comments and suggestions
    general_comments TEXT NULL,
    
    -- New feature idea
    new_feature_title VARCHAR(255) NULL,
    new_feature_description TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
