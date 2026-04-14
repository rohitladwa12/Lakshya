CREATE TABLE IF NOT EXISTS `resume_analysis_cache` (
    `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` VARCHAR(50) NOT NULL,
    `resume_hash` VARCHAR(64) NOT NULL,
    `analysis_json` LONGTEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_cache` (`user_id`, `resume_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
