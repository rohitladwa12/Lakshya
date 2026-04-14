-- Educational Coding Platform - Database Schema
-- Creates tables for coding problems and student progress tracking

-- Table: coding_problems
-- Stores all coding problems with explanations and solutions
CREATE TABLE IF NOT EXISTS coding_problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL COMMENT 'Arrays, Strings, Loops, Recursion, etc.',
    difficulty ENUM('Easy', 'Medium', 'Hard') NOT NULL,
    problem_statement TEXT NOT NULL,
    constraints TEXT,
    example_input TEXT,
    example_output TEXT,
    concept_explanation TEXT COMMENT 'Core concept explanation for learning',
    dry_run_steps JSON COMMENT 'Step-by-step execution visualization',
    solution_beginner JSON COMMENT 'Beginner approach with explanation',
    solution_optimized JSON COMMENT 'Optimized approach with explanation',
    time_complexity VARCHAR(50) COMMENT 'e.g., O(n), O(n²)',
    space_complexity VARCHAR(50) COMMENT 'e.g., O(1), O(n)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Coding problems for educational platform';

-- Table: student_coding_progress
-- Tracks student progress on coding problems
-- IMPORTANT: student_id stores the correct identifier based on institution
-- For GMU: SL_NO from users table
-- For GMIT: enquiry_no or usn (not 0)
CREATE TABLE IF NOT EXISTS student_coding_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL COMMENT 'GMU: SL_NO, GMIT: enquiry_no/usn',
    institution VARCHAR(10) NOT NULL COMMENT 'GMU or GMIT',
    problem_id INT NOT NULL,
    status ENUM('attempted', 'solved', 'mastered') DEFAULT 'attempted',
    attempts INT DEFAULT 1,
    last_attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    code_submitted TEXT COMMENT 'Last submitted code',
    language_used VARCHAR(20) COMMENT 'Python, Java, C++, JavaScript',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES coding_problems(id) ON DELETE CASCADE,
    INDEX idx_student (student_id, institution),
    INDEX idx_problem (problem_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_student_problem (student_id, institution, problem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Student progress tracking - uses correct student identifiers';

-- Add some initial sample problems (will add more later)
INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES
('Reverse a String', 'Strings', 'Easy', 
'Write a function to reverse a given string.\n\nInput: A string\nOutput: The reversed string',
'1 <= string length <= 1000\nString contains only ASCII characters',
'hello',
'olleh',
'String reversal is a fundamental operation. The key concept is to iterate from the last character to the first, or use two pointers (start and end) swapping characters until they meet in the middle.',
'O(n)',
'O(1)'),

('Find Maximum in Array', 'Arrays', 'Easy',
'Write a function to find the maximum element in an array of integers.\n\nInput: An array of integers\nOutput: The maximum value',
'1 <= array length <= 10000\n-10^9 <= array[i] <= 10^9',
'[3, 7, 2, 9, 1]',
'9',
'Finding the maximum requires comparing each element. Start with the first element as max, then iterate through the array updating max whenever you find a larger value.',
'O(n)',
'O(1)'),

('Check Palindrome', 'Strings', 'Easy',
'Write a function to check if a given string is a palindrome (reads the same forwards and backwards).\n\nInput: A string\nOutput: true if palindrome, false otherwise',
'1 <= string length <= 1000\nIgnore case and spaces',
'racecar',
'true',
'A palindrome reads the same forwards and backwards. Use two pointers approach: one at start, one at end. Compare characters while moving pointers toward center. If any mismatch, it''s not a palindrome.',
'O(n)',
'O(1)');
