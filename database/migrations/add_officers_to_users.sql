-- Add Officer Users to users table
-- This consolidates all authentication into one table

USE placement_portal_v2;

-- Add Placement Officer
INSERT INTO users (
    COLLEGE, USER_NAME, PASSWORD, NAME, DESIGNATION, 
    USER_GROUP, STATUS
) VALUES (
    'GM University', 
    'placement_officer', 
    'placement123', 
    'Placement Officer', 
    'Placement Officer',
    'placement_officer', 
    'ACTIVE'
) ON DUPLICATE KEY UPDATE USER_NAME=USER_NAME;

-- Add Internship Officer
INSERT INTO users (
    COLLEGE, USER_NAME, PASSWORD, NAME, DESIGNATION, 
    USER_GROUP, STATUS
) VALUES (
    'GM University', 
    'internship_officer', 
    'internship123', 
    'Internship Officer', 
    'Internship Officer',
    'internship_officer', 
    'ACTIVE'
) ON DUPLICATE KEY UPDATE USER_NAME=USER_NAME;

-- Add Administrator
INSERT INTO users (
    COLLEGE, USER_NAME, PASSWORD, NAME, DESIGNATION, 
    USER_GROUP, STATUS
) VALUES (
    'GM University', 
    'admin', 
    'admin123', 
    'System Administrator', 
    'Administrator',
    'admin', 
    'ACTIVE'
) ON DUPLICATE KEY UPDATE USER_NAME=USER_NAME;

-- Verify
SELECT USER_NAME, NAME, DESIGNATION, USER_GROUP, STATUS 
FROM users 
WHERE USER_GROUP IN ('placement_officer', 'internship_officer', 'admin');
