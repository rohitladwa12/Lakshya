-- ============================================
-- AI APTITUDE TEST SYSTEM - DATABASE MIGRATION
-- ============================================
-- This migration adds tables for AI-generated aptitude tests
-- Student information (name, USN, branch) comes from users/ad_student_approved tables via foreign key

USE placement_portal_v2;

-- ============================================
-- 1. APTITUDE TESTS (Test Configuration)
-- ============================================
CREATE TABLE IF NOT EXISTS aptitude_tests (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    company_id INT UNSIGNED,  -- NULL for general/practice tests
    
    -- Test Details
    test_name VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT DEFAULT 'Answer all questions to the best of your ability. Each question has only one correct answer.',
    
    -- Configuration
    duration_minutes INT UNSIGNED DEFAULT 30,
    total_questions INT UNSIGNED DEFAULT 20,
    passing_percentage DECIMAL(5,2) DEFAULT 60.00,
    
    -- Question Distribution (per category)
    quantitative_count INT UNSIGNED DEFAULT 5,
    logical_count INT UNSIGNED DEFAULT 5,
    verbal_count INT UNSIGNED DEFAULT 5,
    technical_count INT UNSIGNED DEFAULT 5,
    
    -- Settings
    randomize_questions BOOLEAN DEFAULT TRUE,
    show_results_immediately BOOLEAN DEFAULT TRUE,
    allow_retake BOOLEAN DEFAULT FALSE,
    max_attempts INT UNSIGNED DEFAULT 1,
    
    -- Availability
    start_date DATETIME,
    end_date DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Metadata
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. STUDENT APTITUDE ATTEMPTS
-- ============================================
-- References users(SL_NO) for student information
-- Join with users/ad_student_approved to get name, USN, branch, etc.
CREATE TABLE IF NOT EXISTS student_aptitude_attempts (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    test_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,  -- FK to users(SL_NO)
    
    -- Timing
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    submit_time DATETIME,
    duration_seconds INT UNSIGNED,
    
    -- Scoring
    total_score INT UNSIGNED DEFAULT 0,
    total_questions INT UNSIGNED DEFAULT 20,
    correct_answers INT UNSIGNED DEFAULT 0,
    wrong_answers INT UNSIGNED DEFAULT 0,
    unattempted INT UNSIGNED DEFAULT 0,
    percentage DECIMAL(5,2),
    
    -- Category-wise Scores (JSON format)
    -- Example: {"quantitative": {"score": 4, "total": 5}, "logical": {"score": 5, "total": 5}, ...}
    category_scores JSON,
    
    -- Status
    status ENUM('Not Started', 'In Progress', 'Completed', 'Expired', 'Submitted') DEFAULT 'In Progress',
    result ENUM('Pass', 'Fail', 'Pending') DEFAULT 'Pending',
    
    -- AI Analysis
    ai_report LONGTEXT,  -- AI-generated performance analysis
    strengths TEXT,
    weaknesses TEXT,
    recommendations TEXT,
    
    -- Metadata
    ip_address VARCHAR(45),
    browser_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (test_id) REFERENCES aptitude_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_test (test_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_result (result),
    INDEX idx_score (total_score),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. APTITUDE TEST RESPONSES
-- ============================================
-- Stores individual question-answer pairs for each attempt
CREATE TABLE IF NOT EXISTS aptitude_test_responses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT UNSIGNED NOT NULL,
    question_number INT UNSIGNED NOT NULL,
    
    -- Question Data
    question_text TEXT NOT NULL,
    options JSON NOT NULL,  -- {"A": "Option A text", "B": "Option B text", "C": "...", "D": "..."}
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    explanation TEXT,  -- Optional explanation for the correct answer
    
    -- Student Response
    selected_answer ENUM('A', 'B', 'C', 'D', 'UNATTEMPTED') DEFAULT 'UNATTEMPTED',
    is_correct BOOLEAN DEFAULT FALSE,
    
    -- Categorization
    category ENUM('Quantitative', 'Logical', 'Verbal', 'Technical') NOT NULL,
    difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    
    -- Timing
    time_taken_seconds INT UNSIGNED DEFAULT 0,
    answered_at TIMESTAMP NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (attempt_id) REFERENCES student_aptitude_attempts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt_question (attempt_id, question_number),
    INDEX idx_attempt (attempt_id),
    INDEX idx_category (category),
    INDEX idx_correct (is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DATA (Optional - for testing)
-- ============================================

-- Insert a sample general aptitude test
INSERT INTO aptitude_tests (
    test_name, 
    description, 
    duration_minutes, 
    total_questions,
    passing_percentage,
    is_active
) VALUES (
    'General Aptitude Test',
    'A comprehensive aptitude test covering quantitative, logical, verbal, and technical skills.',
    30,
    20,
    60.00,
    TRUE
);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Query to get student attempt with full student information:
-- SELECT 
--     saa.id,
--     saa.total_score,
--     saa.percentage,
--     saa.result,
--     saa.status,
--     u.NAME as student_name,
--     u.ID as usn,
--     u.DISCIPLINE as branch,
--     u.PROGRAMME,
--     u.COURSE,
--     ads.year,
--     ads.sem,
--     at.test_name,
--     c.name as company_name
-- FROM student_aptitude_attempts saa
-- JOIN users u ON saa.student_id = u.SL_NO
-- LEFT JOIN ad_student_approved ads ON u.ID = ads.usn
-- LEFT JOIN aptitude_tests at ON saa.test_id = at.id
-- LEFT JOIN companies c ON at.company_id = c.id
-- WHERE saa.id = ?;

-- ============================================
-- END OF MIGRATION
-- ============================================
