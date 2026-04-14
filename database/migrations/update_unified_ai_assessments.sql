-- Migration to add institution column to unified_ai_assessments
ALTER TABLE unified_ai_assessments ADD COLUMN institution VARCHAR(50) DEFAULT 'GMU' AFTER student_id;

-- Update existing records if possible (assume GMU for legacy data)
UPDATE unified_ai_assessments SET institution = 'GMU' WHERE institution IS NULL;
