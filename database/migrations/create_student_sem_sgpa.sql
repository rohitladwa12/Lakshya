-- Create table for semester-wise SGPA storage
-- This is primarily for GMIT students who don't have this in their legacy schema

CREATE TABLE IF NOT EXISTS `student_sem_sgpa` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(50) NOT NULL, -- Will store student_id (GMIT) or username
    `semester` INT NOT NULL,
    `sgpa` DECIMAL(4, 2) NOT NULL,
    `academic_year` VARCHAR(20) DEFAULT NULL,
    `institution` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`student_id`),
    UNIQUE KEY `unique_student_sem` (`student_id`, `semester`, `institution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
