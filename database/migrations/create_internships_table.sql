-- Create Internships Table
CREATE TABLE IF NOT EXISTS `internships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `internship_title` VARCHAR(255) NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `company_logo` VARCHAR(255) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `duration` VARCHAR(100) DEFAULT NULL,
    `stipend` VARCHAR(100) DEFAULT NULL,
    `mode` ENUM('Offline', 'Online', 'Hybrid') DEFAULT 'Offline',
    `targeted_students` TEXT DEFAULT NULL COMMENT 'JSON or CSV of branches',
    `description` TEXT DEFAULT NULL,
    `requirements` TEXT DEFAULT NULL,
    `responsibilities` TEXT DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `application_deadline` DATE DEFAULT NULL,
    `positions` INT DEFAULT 1,
    `description_documents` TEXT DEFAULT NULL COMMENT 'JSON array of file paths',
    `link` VARCHAR(500) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Active', 'Inactive', 'Closed') DEFAULT 'Active',
    FOREIGN KEY (`created_by`) REFERENCES `app_officers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Internship Applications Table
CREATE TABLE IF NOT EXISTS `internship_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `internship_id` INT NOT NULL,
    `student_id` VARCHAR(50) NOT NULL COMMENT 'USN or Student ID',
    `status` ENUM('Applied', 'Shortlisted', 'Selected', 'Rejected') DEFAULT 'Applied',
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `resume_path` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`internship_id`) REFERENCES `internships`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Internship Admin User (Password: admin123)
-- Using SHA256 or BCrypt depending on system. Assuming plain text or simple hash based on existing setup, 
-- but will use a placeholder hash. If simple login, it might need to vary.
-- Based on User.php, app_officers uses BCrypt or plain text. Let's use BCrypt for 'admin123'.
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi (standard laravel/bcrypt default for password)
-- Actually, let's insert a known user if not exists.

