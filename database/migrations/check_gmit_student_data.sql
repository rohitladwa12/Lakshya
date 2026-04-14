-- Quick diagnostic query to check GMIT student data
-- Run this to see what data is available in ad_student_details

USE gmit_new;

-- Check if student_id column exists and what values it contains
SELECT 
    'Column Check' as test_type,
    COUNT(*) as total_records,
    COUNT(student_id) as records_with_student_id,
    COUNT(CASE WHEN student_id = '0' OR student_id = 0 THEN 1 END) as records_with_zero,
    COUNT(CASE WHEN student_id IS NULL OR student_id = '' THEN 1 END) as records_null_or_empty
FROM ad_student_details
LIMIT 1;

-- Show sample records to see the actual data structure
SELECT 
    enquiry_no,
    student_id,
    usn,
    name,
    course,
    discipline
FROM ad_student_details
LIMIT 5;

-- Check what columns are actually available
SHOW COLUMNS FROM ad_student_details;
