CREATE TABLE IF NOT EXISTS mock_ai_interview_sessions (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(10) UNSIGNED NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    difficulty_level VARCHAR(50) DEFAULT 'Medium',
    conversation_history LONGTEXT,
    overall_score TINYINT(3) UNSIGNED DEFAULT NULL,
    feedback TEXT,
    status ENUM('active', 'completed') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    CONSTRAINT fk_mock_student FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
