-- Fix Mock AI Interview Foreign Key Constraint Issue
-- Problem: GMIT students don't exist in local users table, causing foreign key violation
-- Solution: Drop the foreign key constraint to allow any student_id

USE placement_portal_v2;

-- Drop the foreign key constraint
ALTER TABLE mock_ai_interview_sessions 
DROP FOREIGN KEY IF EXISTS fk_mock_student;

-- Verify the constraint is removed
SHOW CREATE TABLE mock_ai_interview_sessions;
