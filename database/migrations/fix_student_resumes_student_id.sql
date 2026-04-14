-- Migration: Fix student_resumes.student_id to support both GMU (integer) and GMIT (string) student IDs
-- Run this on the live database: placement_portal_v2
-- Date: 2026-04-02

USE placement_portal_v2;

-- Step 1: Drop the foreign key constraint that restricts to users(SL_NO)
--         (GMIT students have string IDs that don't exist in the GMU users table)
ALTER TABLE student_resumes DROP FOREIGN KEY student_resumes_ibfk_1;

-- Step 2: Change student_id from INT UNSIGNED to VARCHAR(100) to support
--         both GMU numeric IDs (stored as string) and GMIT string IDs like 'GMIT23CV14'
ALTER TABLE student_resumes MODIFY COLUMN student_id VARCHAR(100) NOT NULL;

-- Step 3: Verify
SELECT 
    COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'placement_portal_v2'
  AND TABLE_NAME = 'student_resumes'
  AND COLUMN_NAME = 'student_id';

SELECT 'Fix applied: student_resumes.student_id is now VARCHAR(100), FK dropped' AS status;
