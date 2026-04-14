-- ============================================
-- CREATE master_users TABLE FOR OFFICERS
-- Separate login table for Placement Officers and Internship Officers
-- Students login via 'users' table, Officers login via 'master_users' table
-- ============================================

USE placement_portal_v2;

-- Create master_users table for officers
CREATE TABLE IF NOT EXISTS `master_users` (
  `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  
  -- Login Credentials
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,  -- Hashed password
  `email` VARCHAR(100) UNIQUE NOT NULL,
  
  -- Personal Information
  `full_name` VARCHAR(100) NOT NULL,
  `employee_id` VARCHAR(50) UNIQUE,
  `mobile` VARCHAR(15),
  `department` VARCHAR(100),
  
  -- Role & Permissions
  `role` ENUM('placement_officer', 'internship_officer', 'admin', 'super_admin') NOT NULL,
  `permissions` JSON,  -- Additional granular permissions if needed
  
  -- Status
  `is_active` BOOLEAN DEFAULT TRUE,
  `is_verified` BOOLEAN DEFAULT FALSE,
  `email_verified_at` TIMESTAMP NULL,
  
  -- Security
  `last_login` TIMESTAMP NULL,
  `last_login_ip` VARCHAR(45),
  `failed_login_attempts` INT DEFAULT 0,
  `locked_until` TIMESTAMP NULL,
  `password_changed_at` TIMESTAMP NULL,
  `must_change_password` BOOLEAN DEFAULT FALSE,
  
  -- Session Management
  `remember_token` VARCHAR(100),
  `api_token` VARCHAR(100),
  
  -- Metadata
  `created_by` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Indexes
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_active (is_active),
  INDEX idx_employee_id (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT ADMIN USERS
-- ============================================

-- Default Placement Officer (password: placement123)
INSERT INTO `master_users` 
(`username`, `password`, `email`, `full_name`, `employee_id`, `role`, `is_active`, `is_verified`) 
VALUES 
('placement_officer', 'placement123', 'placement@gmu.ac.in', 'Placement Officer', 'PO001', 'placement_officer', TRUE, TRUE)
ON DUPLICATE KEY UPDATE username=username;

-- Default Internship Officer (password: internship123)
INSERT INTO `master_users` 
(`username`, `password`, `email`, `full_name`, `employee_id`, `role`, `is_active`, `is_verified`) 
VALUES 
('internship_officer', 'internship123', 'internship@gmu.ac.in', 'Internship Officer', 'IO001', 'internship_officer', TRUE, TRUE)
ON DUPLICATE KEY UPDATE username=username;

-- Default Super Admin (password: admin123)
INSERT INTO `master_users` 
(`username`, `password`, `email`, `full_name`, `employee_id`, `role`, `is_active`, `is_verified`) 
VALUES 
('admin', 'admin123', 'admin@gmu.ac.in', 'System Administrator', 'ADMIN001', 'super_admin', TRUE, TRUE)
ON DUPLICATE KEY UPDATE username=username;

-- ============================================
-- CREATE ACTIVITY LOG TABLE FOR OFFICERS
-- ============================================

CREATE TABLE IF NOT EXISTS `master_user_activity_logs` (
  `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES master_users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'master_users table created successfully' as status;
SELECT COUNT(*) as total_officers FROM master_users;
