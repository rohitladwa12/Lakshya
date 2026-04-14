-- Fix: GMIT student_id was stored as 0 because USN (string) was cast to INT.
-- Store student_id as VARCHAR so we can save USN for GMIT and numeric id as string for GMU.
-- Run this on your placement_portal_v2 (main app) database.

-- unified_ai_assessments
ALTER TABLE unified_ai_assessments MODIFY COLUMN student_id VARCHAR(100) NOT NULL DEFAULT '';

-- mock_ai_interview_sessions  
ALTER TABLE mock_ai_interview_sessions MODIFY COLUMN student_id VARCHAR(100) NOT NULL DEFAULT '';

-- Backfill existing GMIT rows where student_id is 0: set student_id = usn so reports and coordinator filter work
UPDATE unified_ai_assessments SET student_id = usn WHERE institution = 'GMIT' AND (student_id = '0' OR student_id = '' OR student_id IS NULL);
-- mock_ai_interview_sessions has no usn column; existing GMIT rows with 0 will still be resolved in code via usn fallback in getUnifiedAIReports. New rows will store USN.
