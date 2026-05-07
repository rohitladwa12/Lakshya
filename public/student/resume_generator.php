<?php
/**
 * Student - Resume Generator
 * Multi-step resume builder with PDF export
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Load models
require_once __DIR__ . '/../../src/Models/Resume.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

$resumeModel = new Resume();
$studentModel = new StudentProfile();

// Get existing resume if any
$existingResume = $resumeModel->getByStudentId($userId);
$studentProfile = $studentModel->getByUserId($userId);

// Pre-fill data from student profile
$prefillData = [
    'full_name' => $studentProfile['name'] ?? $fullName,
    'email' => $studentProfile['email'] ?? '',
    'phone' => $studentProfile['phone'] ?? '',
    'location' => ($studentProfile['city'] ?? '') . ($studentProfile['state'] ? ', ' . $studentProfile['state'] : ''),
];

// Fetch Portfolio Data to pre-populate sections
require_once __DIR__ . '/../../src/Models/Portfolio.php';
$portfolioModel = new Portfolio();
$institution = (strpos($userId, 'GMU') !== false) ? 'GMU' : 'GMIT';
$portfolioItems = $portfolioModel->getStudentPortfolio($userId, $institution);

$portfolioData = [
    'projects' => [],
    'skills' => ['technical' => [], 'soft' => [], 'languages' => []],
    'certifications' => []
];

foreach ($portfolioItems as $item) {
    $isVerified = (int)$item['is_verified'] === 1;
    if ($item['category'] === 'Project') {
        $portfolioData['projects'][] = [
            'title' => $item['title'],
            'description' => $item['description'],
            'start_date' => $item['start_date'] ? date('Y-m', strtotime($item['start_date'])) : null,
            'end_date' => $item['end_date'] ? date('Y-m', strtotime($item['end_date'])) : null,
            'ongoing' => empty($item['end_date']) && !empty($item['start_date']),
            'link' => $item['link'],
            'is_verified' => $isVerified,
            'technologies' => [] // Could parse from description if needed
        ];
    } elseif ($item['category'] === 'Skill') {
        $portfolioData['skills']['technical'][] = [
            'name' => $item['title'],
            'level' => $item['sub_title'],
            'is_verified' => $isVerified
        ];
    } elseif ($item['category'] === 'Certification') {
        $portfolioData['certifications'][] = [
            'name' => $item['title'],
            'issuer' => $item['sub_title'],
            'date' => $item['date_completed'] ? date('Y-m', strtotime($item['date_completed'])) : null,
            'credential_url' => $item['link'],
            'is_verified' => $isVerified
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Generator - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --success: #28a745;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light-gray);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #5b1f1f 100%);
            color: var(--white);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 { font-size: 24px; }
        .navbar a { color: var(--white); text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; }
        .navbar a:hover { background: rgba(255,255,255,0.1); }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Progress Indicator */
        .progress-container {
            background: var(--white);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 10px;
        }
        
        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e0e0e0;
            z-index: 0;
        }
        
        .progress-line-fill {
            height: 100%;
            background: var(--primary-maroon);
            transition: width 0.3s;
            width: 0%;
        }
        
        .step {
            position: relative;
            z-index: 1;
            text-align: center;
            flex: 1;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .step.active .step-circle {
            background: var(--primary-maroon);
            color: var(--white);
            transform: scale(1.1);
        }
        
        .step.completed .step-circle {
            background: var(--success);
            color: var(--white);
        }
        
        .step-label {
            font-size: 12px;
            color: #666;
        }
        
        .step.active .step-label {
            color: var(--primary-maroon);
            font-weight: 600;
        }
        
        /* Form Container */
        .form-container {
            background: var(--white);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            min-height: 500px;
        }
        
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .step-title {
            font-size: 24px;
            color: var(--primary-maroon);
            margin-bottom: 10px;
        }
        
        .step-description {
            color: #666;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-maroon);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .required {
            color: #ff4444;
        }
        
        /* Dynamic List Items */
        .list-items {
            margin-bottom: 20px;
        }
        
        .list-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
            border: 1px solid #e0e0e0;
        }
        
        .remove-item-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .add-item-btn {
            background: var(--primary-gold);
            color: #333;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .add-item-btn:hover {
            background: #d4b05f;
            transform: translateY(-2px);
        }
        
        /* Tag Input */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            min-height: 50px;
        }
        
        .tag {
            background: var(--primary-maroon);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tag-remove {
            cursor: pointer;
            font-weight: bold;
        }
        
        .tag-input {
            border: none;
            outline: none;
            flex: 1;
            min-width: 150px;
            padding: 6px;
        }
        
        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--primary-maroon);
            color: white;
        }
        
        .btn-primary:hover {
            background: #5b1f1f;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        /* Preview */
        .preview-container {
            background: white;
            padding: 30px 35px 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .preview-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📄 Resume Generator</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Progress Indicator -->
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line">
                    <div class="progress-line-fill" id="progressFill"></div>
                </div>
                <div class="step active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Education</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Experience</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-circle">4</div>
                    <div class="step-label">Projects</div>
                </div>
                <div class="step" data-step="5">
                    <div class="step-circle">5</div>
                    <div class="step-label">Skills</div>
                </div>
                <div class="step" data-step="6">
                    <div class="step-circle">6</div>
                    <div class="step-label">Certifications</div>
                </div>
                <div class="step" data-step="7">
                    <div class="step-circle">7</div>
                    <div class="step-label">Templates</div>
                </div>
                <div class="step" data-step="8">
                    <div class="step-circle">8</div>
                    <div class="step-label">Preview</div>
                </div>
            </div>
        </div>

        <?php if ($existingResume): ?>
        <div class="alert alert-info">
            ℹ️ You have an existing resume. You can edit it or start fresh.
        </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container">
            <form id="resumeForm">
                <!-- Step 1: Personal Information -->
                <div class="form-step active" data-step="1">
                    <h2 class="step-title">Personal Information</h2>
                    <p class="step-description">Let's start with your basic contact details</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($existingResume['full_name'] ?? $prefillData['full_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($existingResume['email'] ?? $prefillData['email']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($existingResume['phone'] ?? $prefillData['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Location (City, State)</label>
                            <input type="text" name="location" placeholder="e.g., Bangalore, Karnataka" value="<?php echo htmlspecialchars($existingResume['location'] ?? $prefillData['location']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>LinkedIn URL</label>
                            <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/yourprofile" value="<?php echo htmlspecialchars($existingResume['linkedin_url'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>GitHub URL</label>
                            <input type="url" name="github_url" placeholder="https://github.com/yourusername" value="<?php echo htmlspecialchars($existingResume['github_url'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Portfolio URL</label>
                        <input type="url" name="portfolio_url" placeholder="https://yourportfolio.com" value="<?php echo htmlspecialchars($existingResume['portfolio_url'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Professional Summary</label>
                        <textarea name="professional_summary" placeholder="A brief 2-3 line summary highlighting your key strengths and career objectives..."><?php echo htmlspecialchars($existingResume['professional_summary'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Step 2: Education -->
                <div class="form-step" data-step="2">
                    <h2 class="step-title">Education</h2>
                    <p class="step-description">Add your educational qualifications</p>
                    
                    <div id="educationList" class="list-items">
                        <!-- Education items will be added here dynamically -->
                    </div>
                    
                    <button type="button" class="add-item-btn" onclick="addEducation()">+ Add Education</button>
                </div>

                <!-- Step 3: Work Experience -->
                <div class="form-step" data-step="3">
                    <h2 class="step-title">Work Experience</h2>
                    <p class="step-description">Add your professional work experience (optional for freshers)</p>
                    
                    <div id="experienceList" class="list-items">
                        <!-- Experience items will be added here dynamically -->
                    </div>
                    
                    <button type="button" class="add-item-btn" onclick="addExperience()">+ Add Experience</button>
                </div>

                <!-- Step 4: Projects -->
                <div class="form-step" data-step="4">
                    <h2 class="step-title">Projects</h2>
                    <p class="step-description">Showcase your academic or personal projects</p>
                    
                    <div id="projectsList" class="list-items">
                        <!-- Project items will be added here dynamically -->
                    </div>
                    
                    <button type="button" class="add-item-btn" onclick="addProject()">+ Add Project</button>
                </div>

                <!-- Step 5: Skills -->
                <div class="form-step" data-step="5">
                    <h2 class="step-title">Skills</h2>
                    <p class="step-description">List your technical and soft skills</p>
                    
                    <div class="form-group">
                        <label>Technical Skills</label>
                        <div class="tags-container" id="technicalSkillsTags" onclick="focusTagInput(this)">
                            <input type="text" class="tag-input" placeholder="Type skill and press Enter..." onkeydown="handleTagInput(event, 'technical')">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Soft Skills</label>
                        <div class="tags-container" id="softSkillsTags" onclick="focusTagInput(this)">
                            <input type="text" class="tag-input" placeholder="Type skill and press Enter..." onkeydown="handleTagInput(event, 'soft')">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Languages</label>
                        <div class="tags-container" id="languagesTags" onclick="focusTagInput(this)">
                            <input type="text" class="tag-input" placeholder="Type language and press Enter..." onkeydown="handleTagInput(event, 'languages')">
                        </div>
                    </div>
                </div>

                <!-- Step 6: Certifications & Achievements -->
                <div class="form-step" data-step="6">
                    <h2 class="step-title">Certifications & Achievements</h2>
                    <p class="step-description">Add your certifications and notable achievements</p>
                    
                    <h3 style="margin-bottom: 15px;">Certifications</h3>
                    <div id="certificationsList" class="list-items">
                        <!-- Certification items will be added here dynamically -->
                    </div>
                    <button type="button" class="add-item-btn" onclick="addCertification()">+ Add Certification</button>
                    
                    <h3 style="margin: 30px 0 15px;">Achievements</h3>
                    <div id="achievementsList" class="list-items">
                        <!-- Achievement items will be added here dynamically -->
                    </div>
                    <button type="button" class="add-item-btn" onclick="addAchievement()">+ Add Achievement</button>
                </div>

                <!-- Step 7: Template Selection -->
                <div class="form-step" data-step="7">
                    <h2 class="step-title">Choose a Template</h2>
                    <p class="step-description">Select a professional layout for your resume</p>
                    
                    <div class="template-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div class="template-card active" data-template="professional_ats" onclick="selectTemplate('professional_ats', this)" style="border: 2px solid #800000; border-radius: 12px; padding: 10px; cursor: pointer; background: white; transition: transform 0.3s; position: relative;">
                            <div style="background: #f0f0f0; height: 200px; border-radius: 8px; margin-bottom: 10px; padding: 15px; overflow: hidden; display: flex; flex-direction: column; gap: 5px;">
                                <div style="height: 15px; background: #800000; width: 60%; margin: 0 auto;"></div>
                                <div style="height: 10px; background: #ddd; width: 80%; margin: 5px auto;"></div>
                                <div style="height: 3px; background: #333; width: 100%; margin-top: 10px;"></div>
                                <div style="height: 8px; background: #eee; width: 70%;"></div>
                                <div style="height: 8px; background: #eee; width: 90%;"></div>
                                <div style="height: 3px; background: #333; width: 100%; margin-top: 5px;"></div>
                                <div style="height: 8px; background: #eee; width: 80%;"></div>
                                <div style="height: 8px; background: #eee; width: 60%;"></div>
                            </div>
                            <div style="text-align: center; font-weight: 600;">Professional ATS</div>
                            <div style="text-align: center; font-size: 12px; color: #666;">Standard & Reliable</div>
                        </div>

                        <div class="template-card" data-template="modern_creative" onclick="selectTemplate('modern_creative', this)" style="border: 2px solid #ddd; border-radius: 12px; padding: 10px; cursor: pointer; background: white; transition: transform 0.3s; position: relative;">
                            <div style="background: #f0f0f0; height: 200px; border-radius: 8px; margin-bottom: 10px; display: flex;">
                                <div style="width: 35%; background: #333; height: 100%; padding: 10px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #555; margin-bottom: 10px;"></div>
                                    <div style="height: 5px; background: #555; width: 100%; margin-bottom: 5px;"></div>
                                    <div style="height: 5px; background: #555; width: 80%;"></div>
                                </div>
                                <div style="width: 65%; padding: 10px; display: flex; flex-direction: column; gap: 5px;">
                                    <div style="height: 12px; background: #800000; width: 100%;"></div>
                                    <div style="height: 5px; background: #ddd; width: 100%; margin-top: 5px;"></div>
                                    <div style="height: 5px; background: #ddd; width: 100%;"></div>
                                    <div style="height: 5px; background: #ddd; width: 90%;"></div>
                                </div>
                            </div>
                            <div style="text-align: center; font-weight: 600;">Modern Creative</div>
                            <div style="text-align: center; font-size: 12px; color: #666;">Two-column Layout</div>
                        </div>

                        <div class="template-card" data-template="minimal_clean" onclick="selectTemplate('minimal_clean', this)" style="border: 2px solid #ddd; border-radius: 12px; padding: 10px; cursor: pointer; background: white; transition: transform 0.3s; position: relative;">
                            <div style="background: #f0f0f0; height: 200px; border-radius: 8px; margin-bottom: 10px; padding: 20px; display: flex; flex-direction: column; gap: 8px;">
                                <div style="height: 10px; background: #333; width: 40%;"></div>
                                <div style="height: 1px; background: #ccc; width: 100%;"></div>
                                <div style="height: 6px; background: #eee; width: 100%;"></div>
                                <div style="height: 6px; background: #eee; width: 100%;"></div>
                                <div style="height: 1px; background: #ccc; width: 100%; margin-top: 5px;"></div>
                                <div style="height: 6px; background: #eee; width: 80%;"></div>
                                <div style="height: 6px; background: #eee; width: 100%;"></div>
                            </div>
                            <div style="text-align: center; font-weight: 600;">Minimal Clean</div>
                            <div style="text-align: center; font-size: 12px; color: #666;">Elegant Simplicity</div>
                        </div>
                    </div>
                    <input type="hidden" name="template_id" id="template_id" value="<?php echo htmlspecialchars($existingResume['template_id'] ?? 'professional_ats'); ?>">
                </div>

                <!-- Step 8: Preview & Download -->
                <div class="form-step" data-step="8">
                    <h2 class="step-title">Preview & Download</h2>
                    <p class="step-description">Review your resume and download as PDF</p>
                    
                    <div id="resumePreview" class="preview-container">
                        <!-- Preview will be generated here -->
                    </div>
                    
                    <div class="preview-actions">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">← Edit Resume</button>
                        <button type="button" class="btn btn-success" onclick="downloadPDF()">📥 Download PDF</button>
                        <button type="submit" class="btn btn-primary">💾 Save Resume</button>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="prevStep()" style="display: none;">← Previous</button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()">Next →</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Initialize existing resume data
        const existingResumeData = <?php echo $existingResume ? json_encode([
            'education' => $existingResume['education'] ?? [],
            'experience' => $existingResume['experience'] ?? [],
            'projects' => $existingResume['projects'] ?? [],
            'skills' => $existingResume['skills'] ?? ['technical' => [], 'soft' => [], 'languages' => []],
            'certifications' => $existingResume['certifications'] ?? [],
            'achievements' => $existingResume['achievements'] ?? []
        ]) : 'null'; ?>;

        // Portfolio data for auto-populate
        const portfolioData = <?php echo json_encode($portfolioData); ?>;
    </script>
    <script src="../public/student/resume_generator.js?v=<?php echo time(); ?>"></script>
</body>
</html>


