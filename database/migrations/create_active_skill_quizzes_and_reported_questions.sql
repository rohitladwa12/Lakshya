-- Migration: Create active_skill_quizzes and reported_questions tables
-- Database: placement_portal_v2

CREATE TABLE IF NOT EXISTS `active_skill_quizzes` (
  `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `student_id` VARCHAR(50) NOT NULL,
  `portfolio_id` INT UNSIGNED NOT NULL,
  `quiz_data` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_portfolio` (`student_id`, `portfolio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reported_questions` (
  `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `student_id` VARCHAR(50) NOT NULL,
  `student_name` VARCHAR(100) DEFAULT NULL,
  `test_type` VARCHAR(50) NOT NULL COMMENT 'mock_ai, skill_quiz, nqt, campus_drive',
  `test_id` VARCHAR(50) DEFAULT NULL COMMENT 'e.g. drive_id, task_id, or portfolio_id',
  `question_text` TEXT NOT NULL,
  `options` TEXT DEFAULT NULL COMMENT 'JSON string of choices/options',
  `correct_answer` VARCHAR(255) DEFAULT NULL COMMENT 'Correct option index or value',
  `user_answer` VARCHAR(255) DEFAULT NULL COMMENT 'Student option index or value selected',
  `issue_type` VARCHAR(50) NOT NULL COMMENT 'incorrect_key, no_correct_option, typo, other',
  `comment` TEXT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, resolved, dismissed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
