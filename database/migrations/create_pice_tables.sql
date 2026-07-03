-- PICE (Placement Intelligence & Career Analysis Engine) Schema Migration
-- Table: student_personality_profiles (AMPI FFM personality scores)
CREATE TABLE IF NOT EXISTS student_personality_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL, -- USN for GMIT, SL_NO/USN for GMU
    institution ENUM('GMU', 'GMIT') NOT NULL,
    extraversion_level ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    extraversion_z DECIMAL(5,4) DEFAULT 0.0000,
    agreeableness_level ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    agreeableness_z DECIMAL(5,4) DEFAULT 0.0000,
    conscientiousness_level ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    conscientiousness_z DECIMAL(5,4) DEFAULT 0.0000,
    neuroticism_level ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    neuroticism_z DECIMAL(5,4) DEFAULT 0.0000,
    openness_level ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    openness_z DECIMAL(5,4) DEFAULT 0.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_personality (student_id, institution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: student_monthly_metrics (For historical trend snapshots)
CREATE TABLE IF NOT EXISTS student_monthly_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    institution ENUM('GMU', 'GMIT') NOT NULL,
    month_year VARCHAR(7) NOT NULL, -- "YYYY-MM"
    coding_score DECIMAL(5,2) DEFAULT 0.00,
    project_score DECIMAL(5,2) DEFAULT 0.00,
    communication_score DECIMAL(5,2) DEFAULT 0.00,
    behavioral_score DECIMAL(5,2) DEFAULT 0.00,
    career_readiness_score DECIMAL(5,2) DEFAULT 0.00,
    git_score DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_month (student_id, institution, month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: student_placement_reports (For caching the final explained report)
CREATE TABLE IF NOT EXISTS student_placement_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    institution ENUM('GMU', 'GMIT') NOT NULL,
    report_data JSON NOT NULL COMMENT 'Calculated engine metrics',
    report_text LONGTEXT NOT NULL COMMENT 'LLM explained markdown report',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_report (student_id, institution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
