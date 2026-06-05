-- Clear previous auto-generated department coordinators to prevent duplicate accounts/fragmentation
DELETE FROM dept_coordinators WHERE email LIKE '%@placement';

-- Insert Consolidated and Unique Department Coordinators
-- Password for all: 'password'
INSERT INTO dept_coordinators (email, password, full_name, department, institution) VALUES
-- 1. Mapped Consolidated Courses
('bba.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BBA Coordinator', 'BBA', 'GMU'),
('bca.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BCA Coordinator', 'BCA', 'GMU'),
('bcom.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BCOM Coordinator', 'BCOM', 'GMU'),
('bsc.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BSC Coordinator', 'BSC', 'GMU'),
('llb.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'LLB Coordinator', 'LLB', 'GMU'),
('mba.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MBA Coordinator', 'MBA', 'GMU'),
('mca.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MCA Coordinator', 'MCA', 'GMU'),
('mcom.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MCOM Coordinator', 'MCOM', 'GMU'),
('msc.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MSC Coordinator', 'MSC', 'GMU'),
('mtech.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MTECH Coordinator', 'MTECH', 'GMU'),
('phd.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PHD Coordinator', 'PHD', 'GMU'),

-- 2. Other Unique Departments & Specializations
('b.pharm.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'B.PHARM Coordinator', 'B.PHARM', 'GMU'),
('bave.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BAVE Coordinator', 'BAVE', 'GMU'),
('bi.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BI Coordinator', 'BI', 'GMU'),
('bt.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BT Coordinator', 'BT', 'GMU'),
('ce.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CE Coordinator', 'CE', 'GMU'),
('common.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'COMMON Coordinator', 'COMMON', 'GMU'),
('commerce.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Commerce Coordinator', 'Commerce', 'GMU'),
('d.pharm.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'D.PHARM Coordinator', 'D.PHARM', 'GMU'),
('dip..cp.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DIP- CP Coordinator', 'DIP- CP', 'GMU'),
('dip..evt.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DIP- EVT Coordinator', 'DIP- EVT', 'GMU'),
('ebas.ca.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EBAS CA Coordinator', 'EBAS CA', 'GMU'),
('eld.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ELD Coordinator', 'ELD', 'GMU'),
('evt.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EVT Coordinator', 'EVT', 'GMU'),
('fdam.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'FDAM Coordinator', 'FDAM', 'GMU'),
('first.year.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'FIRST-YEAR Coordinator', 'FIRST-YEAR', 'GMU'),
('fm.hrm.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'FM/HRM Coordinator', 'FM/HRM', 'GMU'),
('fm.mm.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'FM/MM Coordinator', 'FM/MM', 'GMU'),
('md.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MD Coordinator', 'MD', 'GMU'),
('non.ca.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'NON CA Coordinator', 'NON CA', 'GMU'),
('pcmb.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PCMB Coordinator', 'PCMB', 'GMU'),
('pcmcs.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PCMCS Coordinator', 'PCMCS', 'GMU'),
('ra.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'RA Coordinator', 'RA', 'GMU'),
('science.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Science Coordinator', 'Science', 'GMU'),

-- 3. Base Engineering & Specialization Coordinators
('cs.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Computer Science Coordinator', 'CSE', 'GMU'),
('mech.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mechanical Engineering Coordinator', 'ME', 'GMU'),
('ece.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ECE Department Coordinator', 'ECE', 'GMU'),
('aiml.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'AIML Department Coordinator', 'CSE-AIML', 'GMU'),
('ai.bc.bs.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-AI-BC-BS Coordinator', 'CSE-AI-BC-BS', 'GMU'),
('bs.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-BS Coordinator', 'CSE-BS', 'GMU'),
('cc.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-CC Coordinator', 'CSE-CC', 'GMU'),
('cy.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-CY Coordinator', 'CSE-CY', 'GMU'),
('ds.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-DS Coordinator', 'CSE-DS', 'GMU'),
('iot.ai.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-IOT-AI Coordinator', 'CSE-IOT-AI', 'GMU'),
('it.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-IT Coordinator', 'CSE-IT', 'GMU'),
('iy.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE-IY Coordinator', 'CSE-IY', 'GMU'),
('ise.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ISE Coordinator', 'ISE', 'GMU'),
('eee.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EEE Coordinator', 'EEE', 'GMU'),
('test.coordinator@placement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test Coordinator', 'ALL-DEPTS', 'GMU');