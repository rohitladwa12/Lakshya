-- Fix Career Roadmaps Foreign Key Constraint
-- This migration removes the foreign key constraint that prevents GMIT students
-- from using the career advisor feature.
--
-- REASON: career_roadmaps.student_id stores:
--   - For GMU students: SL_NO from gmu.users (server database)
--   - For GMIT students: student_id from gmit_new.ad_student_details (server database)
--
-- Since the local placement_portal_v2 database cannot have a foreign key
-- referencing multiple external server databases, we remove the constraint
-- and rely on application-level validation instead.

USE placement_portal_v2;

-- Drop the existing foreign key constraint
ALTER TABLE career_roadmaps 
DROP FOREIGN KEY career_roadmaps_ibfk_1;

-- Add an index on student_id for performance (since we lost the FK index)
ALTER TABLE career_roadmaps 
ADD INDEX idx_student_id (student_id);

-- Optional: Add a comment to the table to document this design decision
ALTER TABLE career_roadmaps 
COMMENT = 'student_id references either gmu.users.SL_NO or gmit_new.ad_student_details.student_id depending on institution';

-- Fix skill_progress table (same issue)
ALTER TABLE skill_progress 
DROP FOREIGN KEY skill_progress_ibfk_2;

-- Add an index on student_id for performance
ALTER TABLE skill_progress 
ADD INDEX idx_student_id (student_id);

-- Add a comment to document this design decision
ALTER TABLE skill_progress 
COMMENT = 'student_id references either gmu.users.SL_NO or gmit_new.ad_student_details.student_id depending on institution';
