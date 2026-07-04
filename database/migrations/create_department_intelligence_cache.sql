-- Department Intelligence Cache for HOD Placement Intelligence
CREATE TABLE IF NOT EXISTS department_intelligence_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(50) NOT NULL,
    institution VARCHAR(10) NOT NULL,
    total_students INT DEFAULT 0,
    cache_data JSON NOT NULL COMMENT 'Aggregated department metrics',
    student_scores JSON NOT NULL COMMENT 'Per-student score array',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dept_cache (department, institution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
