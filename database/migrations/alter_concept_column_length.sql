-- Migration: Increase length of concept column in coordinator_tasks and mock_ai_interview_sessions
-- Purpose: Prevent SQLSTATE[22001]: String data, right truncated when coordinators assign long concept lists
-- Date: 2026-07-15

ALTER TABLE coordinator_tasks MODIFY COLUMN concept TEXT NULL;
ALTER TABLE mock_ai_interview_sessions MODIFY COLUMN concept TEXT NULL;
