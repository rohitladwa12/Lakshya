-- Migration: Add student_resumes table
-- Run this SQL to add the resume generator feature

USE placement_portal_v2;

-- Create student_resumes table
CREATE TABLE IF NOT EXISTS student_resumes (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    
    -- Personal Information
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    location VARCHAR(255),
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    portfolio_url VARCHAR(255),
    professional_summary TEXT,
    
    -- Resume Data (JSON format)
    education JSON,          -- [{degree, institution, year, cgpa, location}]
    experience JSON,         -- [{title, company, duration, location, responsibilities[]}]
    projects JSON,           -- [{title, description, technologies[], link}]
    skills JSON,             -- {technical:[], soft:[], languages:[]}
    certifications JSON,     -- [{name, issuer, date, credential_url}]
    achievements JSON,       -- [{title, description, date}]
    
    -- Complete Resume Data
    resume_data JSON NOT NULL,  -- Complete structured resume data
    
    -- Metadata
    template_id VARCHAR(50) DEFAULT 'professional_ats',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify table creation
SELECT 'student_resumes table created successfully' AS status;
