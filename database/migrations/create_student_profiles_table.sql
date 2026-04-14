-- ============================================
-- CREATE student_profiles TABLE
-- Migration to add the student profiles table
-- ============================================

USE placement_portal_v2;

-- Drop table if exists to recreate with new structure
DROP TABLE IF EXISTS `student_profiles`;

-- Create student_profiles table (usn as primary identifier, no user_id)
CREATE TABLE IF NOT EXISTS `student_profiles` (
  `usn` VARCHAR(30) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `enrollment_number` VARCHAR(50) UNIQUE,
  `phone` VARCHAR(20),
  `date_of_birth` DATE,
  `gender` ENUM('Male', 'Female', 'Other'),
  `course` VARCHAR(50),
  `department` VARCHAR(100),
  `year_of_study` TINYINT UNSIGNED,
  `semester` TINYINT UNSIGNED,
  `cgpa` DECIMAL(4,2),
  `address` TEXT,
  `city` VARCHAR(100),
  `state` VARCHAR(100),
  `pincode` VARCHAR(10),
  `linkedin_url` VARCHAR(255),
  `github_url` VARCHAR(255),
  `portfolio_url` VARCHAR(255),
  `bio` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_enrollment (enrollment_number),
  INDEX idx_course (course),
  INDEX idx_cgpa (cgpa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- POPULATE FROM EXISTING TABLES
-- ============================================
-- Migrate data from ad_student_details and ad_student_approved

INSERT INTO student_profiles 
  (usn, name, enrollment_number, phone, date_of_birth, gender, course, department, year_of_study, semester, cgpa, address, city, state)
SELECT DISTINCT
  TRIM(ad.usn) as usn,
  TRIM(ad.name) as name,
  ad.student_id as enrollment_number,
  COALESCE(NULLIF(TRIM(ad.student_mobile), ''), NULLIF(TRIM(ad.parent_mobile), '')) as phone,
  ad.dob as date_of_birth,
  CASE 
    WHEN UPPER(ad.gender) = 'M' THEN 'Male'
    WHEN UPPER(ad.gender) = 'F' THEN 'Female'
    ELSE 'Other'
  END as gender,
  TRIM(ad.course) as course,
  TRIM(ad.discipline) as department,
  COALESCE(aa.year, 1) as year_of_study,
  COALESCE(aa.sem, 1) as semester,
  aa.sgpa as cgpa,
  ad.address,
  ad.district as city,
  ad.state
FROM ad_student_details ad
LEFT JOIN ad_student_approved aa ON TRIM(ad.usn) = TRIM(aa.usn)
WHERE ad.usn IS NOT NULL 
  AND TRIM(ad.usn) != ''
  AND ad.name IS NOT NULL
  AND TRIM(ad.name) != ''
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  enrollment_number = VALUES(enrollment_number),
  phone = VALUES(phone),
  date_of_birth = VALUES(date_of_birth),
  gender = VALUES(gender),
  course = VALUES(course),
  department = VALUES(department),
  year_of_study = VALUES(year_of_study),
  semester = VALUES(semester),
  cgpa = VALUES(cgpa),
  address = VALUES(address),
  city = VALUES(city),
  state = VALUES(state);

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'student_profiles table created/verified successfully' as status;
SELECT COUNT(*) as total_profiles_migrated FROM student_profiles;
