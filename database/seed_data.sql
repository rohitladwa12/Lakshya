-- ============================================
-- PLACEMENT PORTAL - SEED DATA
-- Version: 2.0
-- ============================================

USE placement_portal_v2;

-- ============================================
-- 1. USERS (Default accounts for testing)
-- ============================================

INSERT INTO users (username, email, password_hash, role, full_name, is_active, email_verified) VALUES
-- Admin account (password: admin123)
('admin', 'admin@placement.com', '$2y$10$FsCHRDouRi6DqBZMMUg4ZecMj3H7D0nPd6FeAJVe7BrwequZaZEoS', 'admin', 'System Administrator', TRUE, TRUE),

-- Placement Officer (password: placement123)
('placement_officer', 'placement@placement.com', '$2y$10$9A0eX08S.y3OKN0Jqtlx.uP7WxAtlTIEBr.lhZJIZkbQ8oKPeMP9q', 'placement_officer', 'Placement Officer', TRUE, TRUE),

-- Internship Officer (password: internship123)
('internship_officer', 'internship@placement.com', '$2y$10$kaHFgkldLXuUtb6jteOa5.YlOOHR7fBNrIhR6PiKCcehljHdlsIR2', 'internship_officer', 'Internship Coordinator', TRUE, TRUE),

-- Sample Students (password: student123)
('student1', 'student1@university.edu', '$2y$10$kdOeIrJK6KE0gU8.0OfOr.ZYDJUVQjXD/N9wIdPU0k95z8yyiy8Ga', 'student', 'Rahul Sharma', TRUE, TRUE),
('student2', 'student2@university.edu', '$2y$10$kdOeIrJK6KE0gU8.0OfOr.ZYDJUVQjXD/N9wIdPU0k95z8yyiy8Ga', 'student', 'Priya Patel', TRUE, TRUE),
('student3', 'student3@university.edu', '$2y$10$kdOeIrJK6KE0gU8.0OfOr.ZYDJUVQjXD/N9wIdPU0k95z8yyiy8Ga', 'student', 'Amit Kumar', TRUE, TRUE),
('student4', 'student4@university.edu', '$2y$10$kdOeIrJK6KE0gU8.0OfOr.ZYDJUVQjXD/N9wIdPU0k95z8yyiy8Ga', 'student', 'Sneha Reddy', TRUE, TRUE),
('student5', 'student5@university.edu', '$2y$10$kdOeIrJK6KE0gU8.0OfOr.ZYDJUVQjXD/N9wIdPU0k95z8yyiy8Ga', 'student', 'Vikram Singh', TRUE, TRUE);

-- ============================================
-- 2. STUDENT PROFILES
-- ============================================

INSERT INTO student_profiles (user_id, enrollment_number, phone, date_of_birth, gender, course, department, year_of_study, semester, cgpa, city, state) VALUES
(4, 'BCA2021001', '9876543210', '2002-05-15', 'Male', 'BCA', 'Computer Applications', 3, 6, 8.5, 'Mumbai', 'Maharashtra'),
(5, 'MCA2022001', '9876543211', '2001-08-22', 'Female', 'MCA', 'Computer Applications', 2, 4, 9.2, 'Delhi', 'Delhi'),
(6, 'BTECH2020001', '9876543212', '2001-03-10', 'Male', 'B.Tech', 'Computer Science', 4, 8, 8.8, 'Bangalore', 'Karnataka'),
(7, 'BCA2021002', '9876543213', '2002-11-30', 'Female', 'BCA', 'Computer Applications', 3, 6, 9.0, 'Hyderabad', 'Telangana'),
(8, 'BTECH2020002', '9876543214', '2001-07-18', 'Male', 'B.Tech', 'Information Technology', 4, 8, 7.9, 'Pune', 'Maharashtra');

-- ============================================
-- 3. SKILLS
-- ============================================

INSERT INTO skills (name, category) VALUES
-- Programming Languages
('Python', 'Programming'),
('Java', 'Programming'),
('JavaScript', 'Programming'),
('C++', 'Programming'),
('C', 'Programming'),
('PHP', 'Programming'),
('Ruby', 'Programming'),
('Go', 'Programming'),

-- Frameworks
('React', 'Framework'),
('Angular', 'Framework'),
('Vue.js', 'Framework'),
('Node.js', 'Framework'),
('Django', 'Framework'),
('Flask', 'Framework'),
('Spring Boot', 'Framework'),
('Laravel', 'Framework'),
('Express.js', 'Framework'),

-- Databases
('MySQL', 'Database'),
('PostgreSQL', 'Database'),
('MongoDB', 'Database'),
('Redis', 'Database'),
('Oracle', 'Database'),

-- Tools
('Git', 'Tool'),
('Docker', 'Tool'),
('Kubernetes', 'Tool'),
('AWS', 'Tool'),
('Azure', 'Tool'),
('Jenkins', 'Tool'),

-- Soft Skills
('Communication', 'Soft Skill'),
('Leadership', 'Soft Skill'),
('Team Work', 'Soft Skill'),
('Problem Solving', 'Soft Skill'),
('Time Management', 'Soft Skill');

-- ============================================
-- 4. STUDENT SKILLS
-- ============================================

INSERT INTO student_skills (student_id, skill_id, proficiency_level) VALUES
-- Student 1 (Rahul) - Full Stack Developer
(4, 1, 'Advanced'),   -- Python (ID 1)
(4, 3, 'Advanced'),   -- JavaScript (ID 3)
(4, 9, 'Intermediate'), -- React (ID 9)
(4, 12, 'Intermediate'), -- Node.js (ID 12)
(4, 18, 'Intermediate'), -- MySQL (ID 18)
(4, 23, 'Beginner'),  -- Git (ID 23)

-- Student 2 (Priya) - Data Science
(5, 1, 'Expert'),     -- Python (ID 1)
(5, 13, 'Advanced'),  -- Django (ID 13)
(5, 18, 'Advanced'),  -- MySQL (ID 18)
(5, 20, 'Intermediate'), -- MongoDB (ID 20)
(5, 29, 'Advanced'),  -- Communication (ID 29)

-- Student 3 (Amit) - Backend Developer
(6, 2, 'Advanced'),   -- Java (ID 2)
(6, 15, 'Advanced'),  -- Spring Boot (ID 15)
(6, 18, 'Advanced'),  -- MySQL (ID 18)
(6, 22, 'Intermediate'), -- Oracle (ID 22)
(6, 23, 'Advanced'),  -- Git (ID 23)
(6, 24, 'Intermediate'), -- Docker (ID 24)

-- Student 4 (Sneha) - Frontend Developer
(7, 3, 'Expert'),     -- JavaScript (ID 3)
(7, 9, 'Expert'),     -- React (ID 9)
(7, 10, 'Advanced'),  -- Angular (ID 10)
(7, 11, 'Intermediate'), -- Vue.js (ID 11)
(7, 23, 'Advanced'),  -- Git (ID 23)

-- Student 5 (Vikram) - DevOps
(8, 1, 'Advanced'),   -- Python (ID 1)
(8, 23, 'Expert'),    -- Git (ID 23)
(8, 24, 'Advanced'),  -- Docker (ID 24)
(8, 25, 'Advanced'),  -- Kubernetes (ID 25)
(8, 26, 'Intermediate'), -- AWS (ID 26)
(8, 28, 'Intermediate'); -- Jenkins (ID 28)

-- ============================================
-- 5. COMPANIES
-- ============================================

INSERT INTO companies (name, industry, website, description, is_active) VALUES
('Google', 'Technology', 'https://www.google.com', 'Leading technology company specializing in Internet-related services and products', TRUE),
('Microsoft', 'Technology', 'https://www.microsoft.com', 'Multinational technology corporation producing computer software, consumer electronics', TRUE),
('Amazon', 'E-Commerce & Cloud', 'https://www.amazon.com', 'E-commerce and cloud computing company', TRUE),
('IBM', 'Technology', 'https://www.ibm.com', 'International technology and consulting corporation', TRUE),
('TCS', 'IT Services', 'https://www.tcs.com', 'Indian multinational IT services and consulting company', TRUE),
('Infosys', 'IT Services', 'https://www.infosys.com', 'Indian multinational IT company providing business consulting and software services', TRUE),
('Wipro', 'IT Services', 'https://www.wipro.com', 'Indian multinational corporation providing IT services', TRUE),
('Accenture', 'Consulting', 'https://www.accenture.com', 'Global professional services company', TRUE),
('Cognizant', 'IT Services', 'https://www.cognizant.com', 'American multinational IT services and consulting company', TRUE),
('Capgemini', 'Consulting', 'https://www.capgemini.com', 'French multinational IT services and consulting company', TRUE),
('Flipkart', 'E-Commerce', 'https://www.flipkart.com', 'Indian e-commerce company', TRUE),
('Paytm', 'Fintech', 'https://www.paytm.com', 'Indian digital payments and financial services company', TRUE);

-- ============================================
-- 6. JOB POSTINGS
-- ============================================

INSERT INTO job_postings (company_id, title, description, requirements, location, job_type, work_mode, salary_min, salary_max, min_cgpa, eligible_courses, eligible_years, posted_date, application_deadline, status, posted_by) VALUES
-- Google
(1, 'Software Engineer', 'Develop scalable software systems and applications', 'Strong programming skills in Java/Python, Data Structures, Algorithms', 'Bangalore', 'Full-Time', 'Hybrid', 1500000, 2500000, 8.0, '["B.Tech", "MCA", "M.Tech"]', '["4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Active', 2),

-- Microsoft
(2, 'Cloud Engineer', 'Build and maintain cloud infrastructure on Azure', 'Azure, Docker, Kubernetes, Python, DevOps experience', 'Hyderabad', 'Full-Time', 'Hybrid', 1200000, 1800000, 7.5, '["B.Tech", "MCA"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'Active', 2),

-- Amazon
(3, 'Backend Developer', 'Develop backend services for e-commerce platform', 'Java, Spring Boot, AWS, Microservices, REST API', 'Bangalore', 'Full-Time', 'On-Site', 1000000, 1500000, 7.0, '["B.Tech", "MCA", "BCA"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'Active', 2),

-- TCS
(5, 'Data Analyst', 'Analyze business data and create insights', 'SQL, Excel, Python, Data Visualization, Tableau', 'Pune', 'Full-Time', 'On-Site', 600000, 800000, 6.5, '["BCA", "MCA", "B.Tech"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'Active', 2),

-- Infosys
(6, 'ML Engineer', 'Build ML models for enterprise solutions', 'Python, Machine Learning, Deep Learning, PyTorch, TensorFlow', 'Hyderabad', 'Full-Time', 'Hybrid', 700000, 1000000, 7.0, '["B.Tech", "MCA", "M.Tech"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 28 DAY), 'Active', 2),

-- Wipro
(7, 'Full Stack Developer', 'Build full-stack web applications', 'React, Node.js, MongoDB, JavaScript, HTML, CSS', 'Chennai', 'Full-Time', 'Remote', 500000, 700000, 6.0, '["BCA", "MCA", "B.Tech"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 22 DAY), 'Active', 2),

-- Accenture
(8, 'Business Analyst', 'Analyze business requirements and processes', 'SQL, Excel, Business Intelligence, Communication', 'Mumbai', 'Full-Time', 'Hybrid', 600000, 900000, 6.5, '["BCA", "MCA", "B.Tech", "MBA"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Active', 2),

-- Cognizant
(9, 'Java Developer', 'Develop enterprise Java applications', 'Java, Spring, Hibernate, MySQL, REST API', 'Bangalore', 'Full-Time', 'On-Site', 550000, 750000, 6.5, '["B.Tech", "MCA", "BCA"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 26 DAY), 'Active', 2);

-- ============================================
-- 7. JOB REQUIRED SKILLS
-- ============================================

INSERT INTO job_required_skills (job_id, skill_id, is_mandatory) VALUES
-- Google Software Engineer
(1, 1, TRUE),  -- Python (ID 1)
(1, 2, TRUE),  -- Java (ID 2)
(1, 32, TRUE), -- Problem Solving (ID 32)

-- Microsoft Cloud Engineer
(2, 1, TRUE),  -- Python (ID 1)
(2, 24, TRUE), -- Docker (ID 24)
(2, 25, TRUE), -- Kubernetes (ID 25)
(2, 27, TRUE), -- Azure (ID 27)

-- Amazon Backend Developer
(3, 2, TRUE),  -- Java (ID 2)
(3, 15, TRUE), -- Spring Boot (ID 15)
(3, 26, TRUE), -- AWS (ID 26)
(3, 18, TRUE), -- MySQL (ID 18)

-- TCS Data Analyst
(4, 1, TRUE),  -- Python (ID 1)
(4, 18, TRUE), -- MySQL (ID 18)
(4, 29, TRUE), -- Communication (ID 29)

-- Infosys ML Engineer
(5, 1, TRUE),  -- Python (ID 1)
(5, 13, FALSE), -- Django (ID 13)
(5, 14, FALSE), -- Flask (ID 14)

-- Wipro Full Stack
(6, 3, TRUE),  -- JavaScript (ID 3)
(6, 9, TRUE),  -- React (ID 9)
(6, 12, TRUE), -- Node.js (ID 12)
(6, 20, TRUE), -- MongoDB (ID 20)

-- Accenture Business Analyst
(7, 18, TRUE), -- MySQL (ID 18)
(7, 29, TRUE), -- Communication (ID 29)
(7, 30, FALSE), -- Leadership (ID 30)

-- Cognizant Java Developer
(8, 2, TRUE),  -- Java (ID 2)
(8, 15, TRUE), -- Spring Boot (ID 15)
(8, 18, TRUE); -- MySQL (ID 18)

-- ============================================
-- 8. INTERNSHIP POSTINGS
-- ============================================

INSERT INTO internship_postings (company_id, title, description, duration_months, start_date, stipend_min, stipend_max, location, work_mode, min_cgpa, eligible_courses, eligible_years, posted_date, application_deadline, status, posted_by) VALUES
-- Google
(1, 'Software Engineering Intern', '3-month internship with potential for full-time conversion', 3, DATE_ADD(CURDATE(), INTERVAL 60 DAY), 80000, 120000, 'Bangalore', 'Hybrid', 7.0, '["B.Tech", "MCA"]', '["2", "3"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'Active', 3),

-- Microsoft
(2, 'Data Science Intern', '6-month internship working on real ML projects', 6, DATE_ADD(CURDATE(), INTERVAL 45 DAY), 60000, 100000, 'Hyderabad', 'Hybrid', 7.0, '["B.Tech", "MCA", "M.Tech"]', '["2", "3"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'Active', 3),

-- Amazon
(3, 'Web Development Intern', '4-month internship building web applications', 4, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 50000, 80000, 'Bangalore', 'On-Site', 6.5, '["BCA", "MCA", "B.Tech"]', '["2", "3"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 18 DAY), 'Active', 3),

-- IBM
(4, 'AI Research Intern', '6-month research internship in AI lab', 6, DATE_ADD(CURDATE(), INTERVAL 90 DAY), 70000, 100000, 'Pune', 'Hybrid', 7.5, '["B.Tech", "M.Tech", "MCA"]', '["3", "4"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'Active', 3),

-- Flipkart
(11, 'Product Management Intern', '3-month internship in product team', 3, DATE_ADD(CURDATE(), INTERVAL 40 DAY), 40000, 60000, 'Bangalore', 'On-Site', 6.5, '["BCA", "MCA", "B.Tech", "MBA"]', '["2", "3"]', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 22 DAY), 'Active', 3);

-- ============================================
-- 9. INTERNSHIP REQUIRED SKILLS
-- ============================================

INSERT INTO internship_required_skills (internship_id, skill_id, is_mandatory) VALUES
-- Google SWE Intern
(1, 1, TRUE),  -- Python (ID 1)
(1, 2, FALSE), -- Java (ID 2)
(1, 3, FALSE), -- JavaScript (ID 3)

-- Microsoft DS Intern
(2, 1, TRUE),  -- Python (ID 1)
(2, 18, TRUE), -- MySQL (ID 18)

-- Amazon Web Dev Intern
(3, 3, TRUE),  -- JavaScript (ID 3)
(3, 9, TRUE),  -- React (ID 9)
(3, 12, FALSE), -- Node.js (ID 12)

-- IBM AI Intern
(4, 1, TRUE),  -- Python (ID 1)
(4, 13, FALSE), -- Django (ID 13)

-- Flipkart PM Intern
(5, 29, TRUE), -- Communication (ID 29)
(5, 30, FALSE), -- Leadership (ID 30)
(5, 32, TRUE); -- Problem Solving (ID 32)

-- ============================================
-- 10. LEARNING CHAPTERS
-- ============================================

INSERT INTO learning_chapters (title, description, display_order, is_active, created_by) VALUES
('Introduction to Programming', 'Fundamentals of programming and computational thinking', 1, TRUE, 1),
('Data Structures', 'Essential data structures for efficient programming', 2, TRUE, 1),
('Algorithms', 'Common algorithms and problem-solving techniques', 3, TRUE, 1),
('Web Development', 'Building modern web applications', 4, TRUE, 1),
('Database Management', 'Relational and NoSQL database concepts', 5, TRUE, 1),
('Software Engineering', 'Best practices in software development', 6, TRUE, 1);

-- ============================================
-- 11. LEARNING MODULES
-- ============================================

INSERT INTO learning_modules (chapter_id, title, description, display_order, is_active) VALUES
-- Introduction to Programming
(1, 'Variables and Data Types', 'Understanding variables, data types, and type conversion', 1, TRUE),
(1, 'Control Structures', 'If-else statements, loops, and conditional logic', 2, TRUE),
(1, 'Functions and Scope', 'Creating reusable code with functions', 3, TRUE),

-- Data Structures
(2, 'Arrays and Lists', 'Working with sequential data structures', 1, TRUE),
(2, 'Stacks and Queues', 'LIFO and FIFO data structures', 2, TRUE),
(2, 'Trees and Graphs', 'Hierarchical and network data structures', 3, TRUE),

-- Algorithms
(3, 'Sorting Algorithms', 'Bubble sort, merge sort, quick sort', 1, TRUE),
(3, 'Searching Algorithms', 'Linear search, binary search', 2, TRUE),
(3, 'Dynamic Programming', 'Optimization techniques', 3, TRUE),

-- Web Development
(4, 'HTML & CSS Basics', 'Structure and styling of web pages', 1, TRUE),
(4, 'JavaScript Fundamentals', 'Client-side scripting', 2, TRUE),
(4, 'React Framework', 'Building interactive UIs', 3, TRUE),

-- Database Management
(5, 'SQL Basics', 'Querying relational databases', 1, TRUE),
(5, 'Database Design', 'Normalization and schema design', 2, TRUE),
(5, 'NoSQL Databases', 'MongoDB and document databases', 3, TRUE);

-- ============================================
-- 12. ANNOUNCEMENTS
-- ============================================

INSERT INTO announcements (title, content, target_role, priority, is_active, created_by) VALUES
('Welcome to Placement Portal 2.0', 'We are excited to launch the new and improved placement portal with enhanced features!', 'all', 'High', TRUE, 1),
('Upcoming Placement Drive - Google', 'Google will be conducting on-campus interviews next month. Eligible students please apply.', 'student', 'Urgent', TRUE, 2),
('Resume Building Workshop', 'Join us for a comprehensive resume building workshop this Friday at 3 PM.', 'student', 'Medium', TRUE, 1);

-- ============================================
-- END OF SEED DATA
-- ============================================
