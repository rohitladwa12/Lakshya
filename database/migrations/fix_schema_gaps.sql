-- Fix Schema Gaps Found During Audit (2026-04-10)
-- Running this is safe: all ADD COLUMN IF NOT EXISTS or equivalent

-- 1. mock_ai_interview_sessions is missing columns the PHP code uses:
--    - institution (used by 'check_active' & 'start' action)
--    - report_content (used by 'chat' & 'end_session' action)
--    - current_sem (used by 'save_pdf' action — queried from this table)

ALTER TABLE mock_ai_interview_sessions
    ADD COLUMN IF NOT EXISTS institution VARCHAR(20) DEFAULT 'GMU' AFTER student_id,
    ADD COLUMN IF NOT EXISTS report_content LONGTEXT NULL AFTER feedback,
    ADD COLUMN IF NOT EXISTS current_sem TINYINT(2) UNSIGNED NULL AFTER feedback;

-- 2. mock_ai_interview_sessions status ENUM is missing 'cancelled'
--    The 'end_session' action sets status to 'cancelled' for short sessions.
ALTER TABLE mock_ai_interview_sessions
    MODIFY COLUMN status ENUM('active', 'completed', 'cancelled') DEFAULT 'active';
