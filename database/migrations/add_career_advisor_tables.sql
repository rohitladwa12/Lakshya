-- Migration: Add AI Career Advisor Tables
-- Run this SQL to add the career advisor feature

USE placement_portal_v2;

-- Table 1: Career Roadmaps
CREATE TABLE IF NOT EXISTS career_roadmaps (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    
    -- Career Goal
    target_role VARCHAR(255) NOT NULL,
    target_company_type VARCHAR(255),
    target_industry VARCHAR(100),
    experience_level ENUM('Entry', 'Mid', 'Senior') DEFAULT 'Entry',
    
    -- Student Context
    current_skills JSON,
    achievements JSON,
    academic_background JSON,
    cgpa DECIMAL(3,2),
    
    -- AI Generated Roadmap
    roadmap_data JSON NOT NULL COMMENT 'Contains overview, timeline, phases, required_skills',
    
    -- Metadata
    status ENUM('active', 'completed', 'archived') DEFAULT 'active',
    progress_percentage INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Career Resources (YouTube Videos)
CREATE TABLE IF NOT EXISTS career_resources (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    roadmap_id INT UNSIGNED NOT NULL,
    
    -- YouTube Video Details
    video_id VARCHAR(50) NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    channel_name VARCHAR(255),
    channel_id VARCHAR(100),
    thumbnail_url VARCHAR(1000),
    
    -- Video Metadata
    duration VARCHAR(50),
    view_count BIGINT,
    published_at TIMESTAMP,
    
    -- Skill Mapping
    related_skills JSON,
    phase_number INT,
    relevance_score DECIMAL(3,2),
    
    -- Student Interaction
    is_bookmarked BOOLEAN DEFAULT FALSE,
    is_completed BOOLEAN DEFAULT FALSE,
    completion_date TIMESTAMP NULL,
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (roadmap_id) REFERENCES career_roadmaps(id) ON DELETE CASCADE,
    INDEX idx_roadmap (roadmap_id),
    INDEX idx_video (video_id),
    INDEX idx_bookmarked (is_bookmarked),
    INDEX idx_phase (phase_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: Study Materials (PDFs, Notes, Cheatsheets)
CREATE TABLE IF NOT EXISTS study_materials (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    roadmap_id INT UNSIGNED NOT NULL,
    
    -- Material Details
    title VARCHAR(500) NOT NULL,
    description TEXT,
    source_url VARCHAR(1000) NOT NULL,
    file_type ENUM('PDF', 'DOC', 'PPT', 'Notes', 'Cheatsheet', 'Other') DEFAULT 'PDF',
    
    -- Source Information
    source_website VARCHAR(255),
    author VARCHAR(255),
    page_count INT,
    file_size VARCHAR(50),
    
    -- Content Metadata
    related_skills JSON,
    phase_number INT,
    difficulty_level ENUM('Beginner', 'Intermediate', 'Advanced'),
    relevance_score DECIMAL(3,2),
    
    -- Student Interaction
    is_bookmarked BOOLEAN DEFAULT FALSE,
    is_downloaded BOOLEAN DEFAULT FALSE,
    download_count INT DEFAULT 0,
    last_accessed TIMESTAMP NULL,
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (roadmap_id) REFERENCES career_roadmaps(id) ON DELETE CASCADE,
    INDEX idx_roadmap (roadmap_id),
    INDEX idx_file_type (file_type),
    INDEX idx_bookmarked (is_bookmarked),
    INDEX idx_phase (phase_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: Skill Progress Tracking
CREATE TABLE IF NOT EXISTS skill_progress (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    roadmap_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    
    skill_name VARCHAR(255) NOT NULL,
    skill_category VARCHAR(100),
    
    -- Progress Tracking
    current_level ENUM('None', 'Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'None',
    target_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') NOT NULL,
    progress_percentage INT DEFAULT 0,
    
    -- Learning Activities
    resources_completed INT DEFAULT 0,
    total_resources INT DEFAULT 0,
    practice_hours INT DEFAULT 0,
    
    -- Milestones
    milestones_achieved JSON,
    last_activity_date TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (roadmap_id) REFERENCES career_roadmaps(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_roadmap (roadmap_id),
    INDEX idx_student (student_id),
    INDEX idx_skill (skill_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Success message
SELECT 'AI Career Advisor tables created successfully!' AS Status;
