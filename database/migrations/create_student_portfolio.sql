-- Create Student Portfolio Table
-- This table stores Projects, Skills, and Certifications in a single unified structure

CREATE TABLE IF NOT EXISTS student_portfolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL COMMENT 'USN or Username of student',
    institution VARCHAR(50) NOT NULL COMMENT 'GMU or GMIT',
    category ENUM('Project', 'Skill', 'Certification') NOT NULL,
    title VARCHAR(255) NOT NULL COMMENT 'Project Name, Skill Name, or Certificate Title',
    description TEXT COMMENT 'Detailed description or technologies used',
    link VARCHAR(255) COMMENT 'GitHub, Portfolio link, or Certificate URL',
    sub_title VARCHAR(255) COMMENT 'Organization, Issuer, or Role',
    date_completed DATE COMMENT 'Completion or Issuance date',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_student_inst (student_id, institution),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
