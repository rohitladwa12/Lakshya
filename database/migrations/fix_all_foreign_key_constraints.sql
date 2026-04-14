-- Comprehensive Fix for All Foreign Key Constraints Referencing users(SL_NO)
-- This migration removes ALL foreign key constraints that prevent GMIT students
-- from using various features in the placement portal.
--
-- REASON: These tables store student_id/user_id that can reference:
--   - For GMU students: SL_NO from gmu.users (server database)
--   - For GMIT students: student_id from gmit_new.ad_student_details (server database)
--
-- Since the local placement_portal_v2 database cannot have foreign keys
-- referencing multiple external server databases, we remove all these constraints
-- and rely on application-level validation instead.

USE placement_portal_v2;

-- 1. activity_logs
ALTER TABLE activity_logs DROP FOREIGN KEY activity_logs_ibfk_1;
ALTER TABLE activity_logs ADD INDEX idx_user_id (user_id);

-- 2. ai_interview_sessions
ALTER TABLE ai_interview_sessions DROP FOREIGN KEY ai_interview_sessions_ibfk_1;
ALTER TABLE ai_interview_sessions ADD INDEX idx_student_id (student_id);

-- 3. ai_resume_analyses
ALTER TABLE ai_resume_analyses DROP FOREIGN KEY ai_resume_analyses_ibfk_1;
ALTER TABLE ai_resume_analyses ADD INDEX idx_user_id (user_id);

-- 4. announcements
ALTER TABLE announcements DROP FOREIGN KEY announcements_ibfk_1;
ALTER TABLE announcements ADD INDEX idx_created_by (created_by);

-- 5. aptitude_tests (created_by)
ALTER TABLE aptitude_tests DROP FOREIGN KEY aptitude_tests_ibfk_2;
ALTER TABLE aptitude_tests ADD INDEX idx_created_by (created_by);

-- 6. events
ALTER TABLE events DROP FOREIGN KEY events_ibfk_1;
ALTER TABLE events ADD INDEX idx_created_by (created_by);

-- 7. faqs (updated_by)
ALTER TABLE faqs DROP FOREIGN KEY faqs_ibfk_3;
ALTER TABLE faqs ADD INDEX idx_updated_by (updated_by);

-- 8. internship_applications (student_id and reviewed_by)
ALTER TABLE internship_applications DROP FOREIGN KEY internship_applications_ibfk_2;
ALTER TABLE internship_applications DROP FOREIGN KEY internship_applications_ibfk_3;
ALTER TABLE internship_applications ADD INDEX idx_student_id (student_id);
ALTER TABLE internship_applications ADD INDEX idx_reviewed_by (reviewed_by);

-- 9. internship_postings (posted_by)
ALTER TABLE internship_postings DROP FOREIGN KEY internship_postings_ibfk_2;
ALTER TABLE internship_postings ADD INDEX idx_posted_by (posted_by);

-- 10. job_applications (student_id and reviewed_by)
ALTER TABLE job_applications DROP FOREIGN KEY job_applications_ibfk_2;
ALTER TABLE job_applications DROP FOREIGN KEY job_applications_ibfk_3;
ALTER TABLE job_applications ADD INDEX idx_student_id (student_id);
ALTER TABLE job_applications ADD INDEX idx_reviewed_by (reviewed_by);

-- 11. job_postings (posted_by)
ALTER TABLE job_postings DROP FOREIGN KEY job_postings_ibfk_2;
ALTER TABLE job_postings ADD INDEX idx_posted_by (posted_by);

-- 12. learning_chapters (created_by)
ALTER TABLE learning_chapters DROP FOREIGN KEY learning_chapters_ibfk_1;
ALTER TABLE learning_chapters ADD INDEX idx_created_by (created_by);

-- 13. mock_ai_interview_sessions
ALTER TABLE mock_ai_interview_sessions DROP FOREIGN KEY fk_mock_student;
ALTER TABLE mock_ai_interview_sessions ADD INDEX idx_student_id (student_id);

-- 14. saved_internships
ALTER TABLE saved_internships DROP FOREIGN KEY saved_internships_ibfk_1;
ALTER TABLE saved_internships ADD INDEX idx_student_id (student_id);

-- 15. saved_jobs
ALTER TABLE saved_jobs DROP FOREIGN KEY saved_jobs_ibfk_1;
ALTER TABLE saved_jobs ADD INDEX idx_student_id (student_id);

-- 16. student_achievements
ALTER TABLE student_achievements DROP FOREIGN KEY student_achievements_ibfk_1;
ALTER TABLE student_achievements ADD INDEX idx_student_id (student_id);

-- 17. student_aptitude_attempts
ALTER TABLE student_aptitude_attempts DROP FOREIGN KEY student_aptitude_attempts_ibfk_2;
ALTER TABLE student_aptitude_attempts ADD INDEX idx_student_id (student_id);

-- 18. student_module_progress
ALTER TABLE student_module_progress DROP FOREIGN KEY student_module_progress_ibfk_1;
ALTER TABLE student_module_progress ADD INDEX idx_student_id (student_id);

-- 19. student_projects
ALTER TABLE student_projects DROP FOREIGN KEY student_projects_ibfk_1;
ALTER TABLE student_projects ADD INDEX idx_student_id (student_id);

-- 20. student_resumes
ALTER TABLE student_resumes DROP FOREIGN KEY student_resumes_ibfk_1;
ALTER TABLE student_resumes ADD INDEX idx_student_id (student_id);

-- 21. student_skills
ALTER TABLE student_skills DROP FOREIGN KEY student_skills_ibfk_1;
ALTER TABLE student_skills ADD INDEX idx_student_id (student_id);

-- Success message
SELECT 'All foreign key constraints referencing users(SL_NO) have been removed successfully!' AS Status;
