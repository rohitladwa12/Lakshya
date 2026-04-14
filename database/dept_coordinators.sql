-- Create Department Coordinators Table
-- This table stores department coordinators who have restricted access to student reports
-- Run this in your placement_portal_v2 (or main app) database

CREATE TABLE IF NOT EXISTS dept_coordinators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'Login email (unique identifier)',
    password VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
    full_name VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL COMMENT 'Assigned department (must match discipline in student tables)',
    institution VARCHAR(50) DEFAULT NULL COMMENT 'Optional: GMU or GMIT. NULL = coordinator sees both GMU and GMIT students for their department.',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_department (department),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Department Coordinators
-- Password for all: 'password' (CHANGE IN PRODUCTION!)
INSERT INTO dept_coordinators (email, password, full_name, department, institution) VALUES
('cs.coordinator@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Computer Science Coordinator', 'Computer Science', 'GMU'),
('mech.coordinator@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mechanical Engineering Coordinator', 'Mechanical Engineering', 'GMU'),
('ece.coordinator@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ECE Department Coordinator', 'Electronics & Communication', 'GMU');

-- Add GMIT coordinators (one per department per institution)
-- INSERT INTO dept_coordinators (email, password, full_name, department, institution) VALUES
-- ('cs.coordinator@gmit.edu', '$2y$10$...', 'CS Coordinator GMIT', 'Computer Science', 'GMIT');

-- Verify
-- SELECT * FROM dept_coordinators;
