-- ============================================
-- PLACEMENT PORTAL - COMPLETE DATABASE SCHEMA
-- Version: 2.0
-- Database: placement_portal_v2
-- ============================================

-- Drop database if exists (CAUTION: This will delete all data!)
-- DROP DATABASE IF EXISTS placement_portal_v2;

-- Create database
CREATE DATABASE IF NOT EXISTS placement_portal_v2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE placement_portal_v2;

-- ============================================
-- CORE TABLES
-- ============================================


-- 2. Student Profiles (Extended student information)
-- 1. Users (Legacy Table)
CREATE TABLE `users` (
  `SL_NO` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `COLLEGE` varchar(200) NOT NULL,
  `USER_NAME` varchar(50) NOT NULL,
  `PASSWORD` varchar(30) NOT NULL,
  `USER_GROUP` varchar(20) DEFAULT NULL,
  `ID` varchar(20) NOT NULL,
  `ENQUIRY_NO` int(11) DEFAULT NULL,
  `AADHAR` varchar(20) DEFAULT NULL,
  `NAME` varchar(50) NOT NULL,
  `DESIGNATION` varchar(50) NOT NULL,
  `FACULTY` varchar(50) DEFAULT NULL,
  `SCHOOL` varchar(20) DEFAULT NULL,
  `PROGRAMME` varchar(10) DEFAULT NULL,
  `COURSE` varchar(10) DEFAULT NULL,
  `DISCIPLINE` varchar(50) DEFAULT NULL,
  `MOBILE_NO` varchar(10) DEFAULT NULL,
  `PHOTO` longtext DEFAULT NULL,
  `CATEGORY` varchar(30) DEFAULT NULL,
  `STATUS` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `REMARKS` varchar(200) DEFAULT NULL,
  `CLUSTER` varchar(20) NOT NULL DEFAULT 'SET',
  `device_token` text DEFAULT NULL,
  `LAST_UPDATED` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`SL_NO`),
  UNIQUE KEY `USER_NAME` (`USER_NAME`),
  KEY `AADHAR` (`AADHAR`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 2. Student Details (Legacy Table - ad_student_approved)
CREATE TABLE `ad_student_approved` (
  `SL_NO` int(11) NOT NULL AUTO_INCREMENT,
  `academic_year` char(7) NOT NULL,
  `season` varchar(20) NOT NULL,
  `enquiry_no` int(11) NOT NULL,
  `NEW_USN` varchar(50) DEFAULT NULL,
  `aadhar` varchar(20) DEFAULT NULL,
  `faculty` varchar(50) NOT NULL,
  `school` varchar(50) NOT NULL,
  `programme` varchar(50) NOT NULL,
  `course` varchar(50) NOT NULL,
  `discipline` varchar(50) NOT NULL,
  `student_id` varchar(30) NOT NULL,
  `usn` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `sem` int(11) DEFAULT NULL,
  `cycle` text DEFAULT NULL,
  `section` text DEFAULT NULL,
  `scheme` int(11) DEFAULT NULL,
  `elective1` text DEFAULT NULL,
  `elective2` text DEFAULT NULL,
  `elective3` text DEFAULT NULL,
  `elective4` text DEFAULT NULL,
  `elective5` text DEFAULT NULL,
  `elective6` text DEFAULT NULL,
  `elective7` text DEFAULT NULL,
  `elective8` text DEFAULT NULL,
  `lab_batch` varchar(10) NOT NULL DEFAULT 'NA',
  `mentor_id` varchar(20) DEFAULT NULL,
  `mentor` varchar(50) NOT NULL,
  `admission_quota` varchar(20) NOT NULL,
  `remarks` text NOT NULL,
  `registered` int(11) DEFAULT NULL,
  `promoted` int(11) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `test` longtext DEFAULT NULL,
  `test_url` text DEFAULT NULL,
  `quiz` longtext DEFAULT NULL,
  `quiz_url` text DEFAULT NULL,
  `assignment` longtext DEFAULT NULL,
  `assignment_url` text DEFAULT NULL,
  `see` longtext DEFAULT NULL,
  `see_url` text DEFAULT NULL,
  `club` longtext DEFAULT NULL,
  `club_url` text DEFAULT NULL,
  `sgpa` decimal(10,2) DEFAULT NULL,
  `docflag` int(11) NOT NULL DEFAULT 0,
  `LAST_UPDATED` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`SL_NO`),
  UNIQUE KEY `academic_year` (`academic_year`,`season`,`usn`) USING BTREE,
  KEY `idx_usn` (`usn`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================
-- SKILLS MANAGEMENT
-- ============================================

-- 3. Skills (Normalized skills table)
CREATE TABLE skills (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    category ENUM('Programming', 'Framework', 'Tool', 'Database', 'Soft Skill', 'Other') DEFAULT 'Other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Student Skills (Many-to-many relationship)
CREATE TABLE student_skills (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    proficiency_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Intermediate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_skill (student_id, skill_id),
    INDEX idx_student (student_id),
    INDEX idx_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- COMPANY & JOB MANAGEMENT
-- ============================================

-- 5. Companies (Normalized company table)
CREATE TABLE companies (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    industry VARCHAR(100),
    website VARCHAR(255),
    description TEXT,
    logo_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Job Postings
CREATE TABLE job_postings (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    requirements TEXT,
    responsibilities TEXT,
    
    -- Location & Type
    location VARCHAR(255),
    job_type ENUM('Full-Time', 'Part-Time', 'Contract', 'Internship') DEFAULT 'Full-Time',
    work_mode ENUM('On-Site', 'Remote', 'Hybrid') DEFAULT 'On-Site',
    
    -- Compensation
    salary_min DECIMAL(10,2),
    salary_max DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'INR',
    
    -- Eligibility
    min_cgpa DECIMAL(3,2),
    eligible_courses TEXT,  -- JSON array: ["BCA", "MCA", "B.Tech"]
    eligible_years TEXT,    -- JSON array: ["3", "4"]
    
    -- Dates
    posted_date DATE NOT NULL,
    application_deadline DATE,
    
    -- Status
    status ENUM('Draft', 'Active', 'Closed', 'Cancelled') DEFAULT 'Active',
    
    -- Metadata
    posted_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_status (status),
    INDEX idx_deadline (application_deadline),
    INDEX idx_posted_date (posted_date),
    INDEX idx_cgpa (min_cgpa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Job Required Skills (Many-to-many)
CREATE TABLE job_required_skills (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_job_skill (job_id, skill_id),
    INDEX idx_job (job_id),
    INDEX idx_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Job Applications
CREATE TABLE job_applications (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    
    -- Application Details
    cover_letter TEXT,
    resume_path VARCHAR(255),
    match_percentage TINYINT UNSIGNED,
    
    -- Status Tracking
    status ENUM('Applied', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Selected', 'Rejected', 'Withdrawn') DEFAULT 'Applied',
    status_updated_at TIMESTAMP NULL,
    status_updated_by INT UNSIGNED NULL,
    
    -- Notes
    admin_notes TEXT,
    student_notes TEXT,
    
    -- Timestamps
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    FOREIGN KEY (status_updated_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    UNIQUE KEY unique_application (job_id, student_id),
    INDEX idx_job (job_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_applied_date (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INTERNSHIP MANAGEMENT
-- ============================================

-- 9. Internship Postings
CREATE TABLE internship_postings (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    requirements TEXT,
    
    -- Internship Specific
    duration_months TINYINT UNSIGNED,
    start_date DATE,
    stipend_min DECIMAL(10,2),
    stipend_max DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'INR',
    
    -- Location & Mode
    location VARCHAR(255),
    work_mode ENUM('On-Site', 'Remote', 'Hybrid') DEFAULT 'On-Site',
    
    -- Eligibility
    min_cgpa DECIMAL(3,2),
    eligible_courses TEXT,  -- JSON array
    eligible_years TEXT,    -- JSON array
    
    -- Dates
    posted_date DATE NOT NULL,
    application_deadline DATE,
    
    -- Status
    status ENUM('Draft', 'Active', 'Closed', 'Cancelled') DEFAULT 'Active',
    
    -- Metadata
    posted_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_status (status),
    INDEX idx_deadline (application_deadline),
    INDEX idx_start_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Internship Required Skills
CREATE TABLE internship_required_skills (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    internship_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (internship_id) REFERENCES internship_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_internship_skill (internship_id, skill_id),
    INDEX idx_internship (internship_id),
    INDEX idx_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Internship Applications
CREATE TABLE internship_applications (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    internship_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    
    cover_letter TEXT,
    resume_path VARCHAR(255),
    match_percentage TINYINT UNSIGNED,
    
    status ENUM('Applied', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Selected', 'Rejected', 'Withdrawn') DEFAULT 'Applied',
    status_updated_at TIMESTAMP NULL,
    status_updated_by INT UNSIGNED NULL,
    
    admin_notes TEXT,
    student_notes TEXT,
    
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (internship_id) REFERENCES internship_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    FOREIGN KEY (status_updated_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    UNIQUE KEY unique_application (internship_id, student_id),
    INDEX idx_internship (internship_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INTERVIEW MANAGEMENT
-- ============================================

-- 12. Interviews
CREATE TABLE interviews (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    application_type ENUM('job', 'internship') NOT NULL,
    
    -- Interview Details
    interview_date DATETIME NOT NULL,
    interview_type ENUM('Technical', 'HR', 'Aptitude', 'Group Discussion', 'Final') NOT NULL,
    round_number TINYINT UNSIGNED DEFAULT 1,
    
    -- Location/Mode
    mode ENUM('In-Person', 'Video Call', 'Phone Call') DEFAULT 'In-Person',
    location VARCHAR(255),
    meeting_link VARCHAR(500),
    
    -- Panel
    panel_members TEXT,  -- JSON array
    
    -- Status
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled', 'Rescheduled') DEFAULT 'Scheduled',
    
    -- Results
    score INT UNSIGNED,
    feedback TEXT,
    result ENUM('Selected', 'Rejected', 'On Hold', 'Pending') NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_application (application_id, application_type),
    INDEX idx_date (interview_date),
    INDEX idx_status (status),
    INDEX idx_type (interview_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AI FEATURES
-- ============================================

-- 13. AI Interview Sessions
CREATE TABLE ai_interview_sessions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    
    -- Session Details
    domain VARCHAR(100) NOT NULL,
    difficulty_level ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    
    -- Scores
    overall_score TINYINT UNSIGNED,
    communication_score TINYINT UNSIGNED,
    technical_score TINYINT UNSIGNED,
    confidence_score TINYINT UNSIGNED,
    
    -- Data
    conversation_transcript LONGTEXT,
    feedback TEXT,
    strengths TEXT,
    weaknesses TEXT,
    recommendations TEXT,
    
    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    duration_minutes INT UNSIGNED,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_domain (domain),
    INDEX idx_completed (completed_at),
    INDEX idx_score (overall_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. AI Interview Domains
CREATE TABLE interview_domains (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    domain_name VARCHAR(100) NOT NULL,
    icon VARCHAR(10) NOT NULL,
    difficulty_level ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. AI Resume Analysis
CREATE TABLE ai_resume_analyses (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    resume_path VARCHAR(255) NOT NULL,
    
    -- Analysis Results
    overall_score TINYINT UNSIGNED,
    strengths TEXT,
    weaknesses TEXT,
    suggestions TEXT,
    
    -- Detailed Scores
    formatting_score TINYINT UNSIGNED,
    content_score TINYINT UNSIGNED,
    skills_score TINYINT UNSIGNED,
    experience_score TINYINT UNSIGNED,
    
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_score (overall_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- LEARNING MANAGEMENT
-- ============================================

-- 15. Learning Chapters
CREATE TABLE learning_chapters (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    display_order INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_order (display_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Learning Modules
CREATE TABLE learning_modules (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chapter_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_url VARCHAR(500),
    pdf_url VARCHAR(500),
    content LONGTEXT,
    display_order INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (chapter_id) REFERENCES learning_chapters(id) ON DELETE CASCADE,
    INDEX idx_chapter (chapter_id),
    INDEX idx_order (display_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Student Module Progress
CREATE TABLE student_module_progress (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    
    status ENUM('Not Started', 'In Progress', 'Completed') DEFAULT 'Not Started',
    progress_percentage TINYINT UNSIGNED DEFAULT 0,
    quiz_score TINYINT UNSIGNED,
    
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    last_accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES learning_modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_module (student_id, module_id),
    INDEX idx_student (student_id),
    INDEX idx_module (module_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CONTENT MANAGEMENT
-- ============================================

-- 18. FAQs
CREATE TABLE faqs (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    chapter_id INT UNSIGNED NULL,
    module_id INT UNSIGNED NULL,
    category VARCHAR(100),
    display_order INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (chapter_id) REFERENCES learning_chapters(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES learning_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_chapter (chapter_id),
    INDEX idx_module (module_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Announcements
CREATE TABLE announcements (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    target_role ENUM('all', 'student', 'admin', 'placement_officer', 'internship_officer') DEFAULT 'all',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (created_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_role (target_role),
    INDEX idx_active (is_active),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- STUDENT PROJECTS & ACHIEVEMENTS
-- ============================================

-- 20. Student Projects
CREATE TABLE student_projects (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    technologies_used TEXT,  -- JSON array
    project_url VARCHAR(500),
    github_url VARCHAR(500),
    start_date DATE,
    end_date DATE,
    is_ongoing BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Student Achievements
CREATE TABLE student_achievements (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    achievement_type ENUM('Award', 'Certification', 'Competition', 'Publication', 'Other') DEFAULT 'Other',
    issuer VARCHAR(255),
    issue_date DATE,
    certificate_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_type (achievement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- BOOKMARKS & SAVED ITEMS
-- ============================================

-- 22. Saved Jobs
CREATE TABLE saved_jobs (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (student_id, job_id),
    INDEX idx_student (student_id),
    INDEX idx_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. Saved Internships
CREATE TABLE saved_internships (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    internship_id INT UNSIGNED NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(SL_NO) ON DELETE CASCADE,
    FOREIGN KEY (internship_id) REFERENCES internship_postings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (student_id, internship_id),
    INDEX idx_student (student_id),
    INDEX idx_internship (internship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EVENTS & CALENDAR
-- ============================================

-- 24. Events
CREATE TABLE events (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type ENUM('Placement Drive', 'Workshop', 'Seminar', 'Interview', 'Deadline', 'Other') DEFAULT 'Other',
    event_date DATETIME NOT NULL,
    end_date DATETIME NULL,
    location VARCHAR(255),
    is_all_day BOOLEAN DEFAULT FALSE,
    created_by INT UNSIGNED,
    target_role ENUM('all', 'student', 'admin', 'placement_officer', 'internship_officer') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_date (event_date),
    INDEX idx_type (event_type),
    INDEX idx_role (target_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SYSTEM TABLES
-- ============================================

-- 25. Student Resumes
CREATE TABLE student_resumes (
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

-- 26. Activity Logs
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(SL_NO) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. Resume Analysis Cache
CREATE TABLE `resume_analysis_cache` (
  `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `user_id` VARCHAR(50) NOT NULL,
  `resume_hash` VARCHAR(64) NOT NULL,
  `analysis_json` LONGTEXT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_cache` (`user_id`, `resume_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_sgpa_freeze` (
  `student_id` varchar(50) NOT NULL,
  `institution` ENUM('GMU', 'GMIT') NOT NULL,
  `is_frozen` tinyint(1) NOT NULL DEFAULT 1,
  `frozen_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`,`institution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- END OF SCHEMA
-- ============================================
