-- Migration: Performance Hardening for AI Assessment Pipeline
-- Purpose: Support high-concurrency (10k+ students) by adding composite indexes and missing status indexes.

-- 1. Unified AI Assessments Optimization
-- Add composite index for common queries (Check Active Session & Report Retrieval)
ALTER TABLE `unified_ai_assessments` 
ADD INDEX `idx_assessments_lookup` (`student_id`, `assessment_type`, `company_name`, `status`);

-- Add index on status for quick filtering of active sessions
ALTER TABLE `unified_ai_assessments` 
ADD INDEX `idx_assessment_status` (`status`);

-- Remove redundant duplicate index (if it exists from previous manual migrations)
-- Index student_id already exists as a single-column index. 
-- Composite idx_assessments_lookup will handle queries starting with student_id + assessment_type.

-- 2. Mock AI Interview Sessions Optimization
-- Missing indexes on status and institution for large-scale filtering
ALTER TABLE `mock_ai_interview_sessions`
ADD INDEX `idx_mock_status` (`status`),
ADD INDEX `idx_mock_institution` (`institution`);

-- 3. Cleanup: Consistency checks
-- Ensure VARCHAR lengths match commonly joined columns
-- (Already verified as VARCHAR(50) for student_id in walkthrough)
