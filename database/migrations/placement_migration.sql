CREATE TABLE IF NOT EXISTS `placements` (
    `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT(10) UNSIGNED NOT NULL,
    `student_id` INT(10) UNSIGNED NOT NULL,
    `company_id` INT(10) UNSIGNED NOT NULL,
    `institution` VARCHAR(50) NOT NULL,
    `salary_package` DECIMAL(15, 2) DEFAULT NULL,
    `placement_date` DATE NOT NULL,
    `document_path` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT 'Placed',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `job_postings`(`id`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
