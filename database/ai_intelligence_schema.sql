-- AI Intelligence Layer for Lakshya Placement Portal
-- This schema handles personalization, weakness tracking, and micro-challenges.

CREATE TABLE IF NOT EXISTS `student_ai_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_name` VARCHAR(255),
    `student_id` VARCHAR(50) NOT NULL, -- USN or Enquiry No
    `institution` ENUM('GMU', 'GMIT') NOT NULL,
    `predicted_role` VARCHAR(100),
    `confidence_score` DECIMAL(3,2) DEFAULT 0.00,
    `detected_interests` JSON, -- Array of strings
    `personality_pref` VARCHAR(50) DEFAULT 'Professional', -- Supportive, Brutal, etc.
    `ai_summary` TEXT, -- High-level memory for the AI
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_student` (`student_id`, `institution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_topic_mastery` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_name` VARCHAR(255),
    `student_id` VARCHAR(50) NOT NULL,
    `institution` ENUM('GMU', 'GMIT') NOT NULL,
    `topic_name` VARCHAR(100) NOT NULL, -- e.g., "SQL Joins", "Recursion"
    `category` VARCHAR(50), -- Aptitude, Technical, HR
    `mastery_level` INT DEFAULT 0, -- 0 to 100
    `attempts_count` INT DEFAULT 0,
    `last_tested_at` DATETIME,
    `is_high_priority` TINYINT(1) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_topic` (`student_id`, `institution`, `topic_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_micro_challenges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_name` VARCHAR(255),
    `student_id` VARCHAR(50) NOT NULL,
    `institution` ENUM('GMU', 'GMIT') NOT NULL,
    `topic_name` VARCHAR(100) NOT NULL,
    `question_json` JSON NOT NULL, -- {question, options, answer, explanation}
    `status` ENUM('pending', 'completed', 'skipped', 'expired') DEFAULT 'pending',
    `performance_result` TINYINT(1) DEFAULT NULL, -- 1 for correct, 0 for wrong
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME,
    INDEX `idx_student_status` (`student_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_ai_insights` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_name` VARCHAR(255),
    `student_id` VARCHAR(50) NOT NULL,
    `institution` ENUM('GMU', 'GMIT') NOT NULL,
    `insight_type` VARCHAR(50), -- Goal_Match, Warning, Achievement, Career_Tip
    `message` TEXT NOT NULL,
    `action_link` VARCHAR(255),
    `priority` INT DEFAULT 1, -- 1 to 5
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_student_unread` (`student_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
