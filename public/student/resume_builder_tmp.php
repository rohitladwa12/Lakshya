<?php
/**
 * Dynamic Resume Builder – AI Powered
 * Student-facing page to generate or upload resumes.
 */

// Load configurations
require_once __DIR__ . '/includes/config.php';

// Enforce student role
require_role('student');

$mysqli = getDB('default');
$username = $_SESSION['username'] ?? $_SESSION['user_id'];
$student_name = $_SESSION['full_name'] ?? 'Student';

// Ensure table exists
ensureGeneratedResumesTable($mysqli);

// Handle Actions
$action = $_POST['action'] ?? '';
$is_edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';
$error_message = '';
$success_message = '';

// New Resume Reset
if (isset($_GET['new']) && $_GET['new'] == '1') {
    unset($_SESSION['generated_resume']);
    header('Location: resume_builder.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'generate_resume') {
        $profile = fetchStudentProfile($mysqli, $username);
        if ($profile) {
            // Attempt to fetch the predicted job role from AI Career Predictor or Target Fit analysis
            require_once __DIR__ . '/../../src/Models/AIAnalysisCache.php';
            $cacheModel = new AIAnalysisCache();
            $predictedRole = null;

            // Check career path analysis first
            $careerAnalysis = $cacheModel->getCachedAnalysis($username, 'career', null, null, null);
            if ($careerAnalysis) {
                $careerData = json_decode($careerAnalysis, true);
                if (isset($careerData['primary_path']['title'])) {
                    $predictedRole = $careerData['primary_path']['title'];
                }
            }

            // If no career role, check most recent target fit analysis
            if (!$predictedRole) {
                // We don't have the specific company/role here, so we look for any 'target' analysis for this user
                try {
                    $stmt = $mysqli->prepare("SELECT analysis_content FROM ai_analysis_cache WHERE user_id = ? AND mode = 'target' ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$username]);
                    $targetAnalysis = $stmt->fetchColumn();
                    if ($targetAnalysis) {
                        $targetData = json_decode($targetAnalysis, true);
                        if (isset($targetData['role'])) {
                            $predictedRole = $targetData['role'];
                        }
                    }
                } catch (Exception $e) {
                    // Ignore cache fetch issues
                }
            }

            if ($predictedRole) {
                $profile['predicted_role'] = $predictedRole;
            }

            $resume_html = generateResumeWithAI($profile);
            
            if ($resume_html) {
                $_SESSION['generated_resume'] = $resume_html;
                upsertResumeToDB($mysqli, $username, $profile, $resume_html);
                $success_message = "Resume generated successfully!";
            } else {
                $error_message = "Failed to generate resume with AI. Please check your OpenAI configuration.";
            }
        } else {
            $error_message = "Student profile not found. Please ensure your profile is complete.";
        }
    } elseif ($action === 'save_edited_resume') {
        $resume_content = $_POST['resume_content'] ?? '';
        $_SESSION['generated_resume'] = $resume_content;
        // Also update DB if necessary
        updateResumeInDB($mysqli, $username, $resume_content);
        $success_message = "Resume changes saved to session.";
        $is_edit_mode = false;
    } elseif ($action === 'upload_resume') {
        if (isset($_FILES['resume_pdf']) && $_FILES['resume_pdf']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['resume_pdf'];
            $file_type = mime_content_type($file['tmp_name']);
            if ($file_type === 'application/pdf') {
                if ($file['size'] <= 5 * 1024 * 1024) { // 5MB
                    $data = file_get_contents($file['tmp_name']);
                    $base64 = base64_encode($data);
                    $iframe_html = '<div id="resumeContent" style="width:100%; height:800px;"><iframe src="data:application/pdf;base64,' . $base64 . '" width="100%" height="100%" style="border:none;"></iframe></div>';
                    $_SESSION['generated_resume'] = $iframe_html;
                    upsertResumeToDB($mysqli, $username, ['upload' => true], $iframe_html);
                    $success_message = "Resume PDF uploaded successfully!";
                } else {
                    $error_message = "File size exceeds 5MB limit.";
                }
            } else {
                $error_message = "Invalid file type. Only PDF allowed.";
            }
        } else {
            $error_message = "Please select a valid PDF file to upload.";
        }
    }
}

$current_resume = $_SESSION['generated_resume'] ?? '';

if (empty($current_resume)) {
    $inst = $_SESSION['institution'] ?? 'GMU';
    $stmt = $mysqli->prepare("SELECT generated_resume FROM generated_resumes WHERE username = ? AND institution = ? LIMIT 1");
    $stmt->execute([$username, $inst]);
    $resRow = $stmt->fetch();
    if ($resRow) {
        $current_resume = $resRow['generated_resume'];
        $_SESSION['generated_resume'] = $current_resume;
    }
}

// Functions
function ensureGeneratedResumesTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS generated_resumes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        institution VARCHAR(50) NOT NULL,
        resume_json LONGTEXT,
        generated_resume LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_user_inst (username, institution)
    )";
    $pdo->exec($sql);
    
    // Ensure generated_resume column exists (if table was created without it)
    try {
        $pdo->query("SELECT generated_resume FROM generated_resumes LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE generated_resumes ADD COLUMN generated_resume LONGTEXT");
    }

    // Ensure institution column exists
    try {
        $pdo->query("SELECT institution FROM generated_resumes LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE generated_resumes ADD COLUMN institution VARCHAR(50) AFTER username");
        // Also update existing records with default if possible, or leave null
    }
}

function fetchStudentProfile($pdo, $username) {
    try {
        $spModel = new StudentProfile();
        $institution = $_SESSION['institution'] ?? null;
        $profile = $spModel->getByUserId($username, $institution);
        
        if (!$profile) {
            return false;
        }

        // Fetch from student_portfolio
        $portfolioSql = "SELECT * FROM student_portfolio WHERE student_id = ? OR student_id = ?";
        $stmtPbt = $pdo->prepare($portfolioSql);
        $stmtPbt->execute([$username, $profile['usn'] ?? $username]);
        $portfolioItems = $stmtPbt->fetchAll(PDO::FETCH_ASSOC);
        
        $profile['portfolio_projects'] = [];
        $profile['portfolio_skills'] = [];
        $profile['portfolio_certifications'] = [];
        
        foreach ($portfolioItems as $item) {
            if ($item['category'] === 'Project') {
                $profile['portfolio_projects'][] = $item;
            } elseif ($item['category'] === 'Skill') {
                $profile['portfolio_skills'][] = $item;
            } elseif ($item['category'] === 'Certification') {
                $profile['portfolio_certifications'][] = $item;
            }
        }

        // Add legacy Skills if portfolio skills are empty
        if (empty($profile['portfolio_skills'])) {
            $skillsSql = "SELECT s.name as skill_name, ss.proficiency_level 
                          FROM student_skills ss 
                          JOIN skills s ON ss.skill_id = s.id 
                          WHERE ss.student_id = ?
                          ORDER BY ss.proficiency_level DESC";
            $stmtSkills = $pdo->prepare($skillsSql);
            $stmtSkills->execute([$profile['user_id'] ?? $profile['id']]);
            $profile['skills'] = $stmtSkills->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $profile['skills'] = $profile['portfolio_skills'];
        }

        // Add legacy Projects if portfolio projects are empty
        if (empty($profile['portfolio_projects'])) {
            $projSql = "SELECT * FROM student_projects WHERE username = ? ORDER BY is_ongoing DESC, start_date DESC";
            $stmtProj = $pdo->prepare($projSql);
            $stmtProj->execute([$username]);
            $profile['projects'] = $stmtProj->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $profile['projects'] = $profile['portfolio_projects'];
        }
        
        // Ensure certifications is set
        $profile['certifications'] = $profile['portfolio_certifications'];

        return $profile;
    } catch (Exception $e) {
        error_log("Resume Builder Profile Fetch Error: " . $e->getMessage());
        return false;
    }
}

function upsertResumeToDB($pdo, $username, $profile, $html) {
    $json = json_encode($profile);
    $institution = $_SESSION['institution'] ?? 'GMU';
    $stmt = $pdo->prepare("INSERT INTO generated_resumes (username, institution, resume_json, generated_resume) VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE resume_json = VALUES(resume_json), generated_resume = VALUES(generated_resume)");
    $stmt->execute([$username, $institution, $json, $html]);
}

function updateResumeInDB($pdo, $username, $html) {
    $institution = $_SESSION['institution'] ?? 'GMU';
    $stmt = $pdo->prepare("UPDATE generated_resumes SET generated_resume = ? WHERE username = ? AND institution = ?");
    $stmt->execute([$html, $username, $institution]);
}

function createResumePrompt($profile) {
    if (!$profile) return "No profile data available.";
    
    $name = $profile['name'] ?? $profile['usn'] ?? 'Student';
    $email = $profile['email'] ?? $profile['usn'] ?? 'N/A';
    $phone = $profile['student_mobile'] ?? 'N/A';
    
    $prompt = "Generate a professional, high-density one-page resume in raw HTML (div only) with inline CSS. 
    COLOR THEME: Use Dark Maroon (#5b1f1f) for all headings, horizontal rules, and key accents.
    FONT: Modern, readable sans-serif (e.g., Inter, Arial).
    
    DETAILED LAYOUT RULES (MATCH ARUN K GUDAGI FORMAT):
    
    1. HEADER:
       - Name: Large, bold, uppercase, centered ($name).
       - Subtitle: Centered below name ().
       - Contact Row: Centered line with icons: [Email] $email | [LinkedIn] / LinkedIn Link | [GitHub] / GitHub Link | [Phone] $phone
    
    2. SECTION HEADERS:
       - Large, bold, Dark Maroon (#5b1f1f).
       - A full-width horizontal rule (1px solid #5b1f1f) immediately below the text.
    
    3. EDUCATION (TOP SECTION):
       - Header: Education
       - Row: [Degree] [Majors/Course] --- [Institution] (Left Aligned) | [CGPA/Percentage] [Year] (Right Aligned)
    
    4. PROFESSIONAL EXPERIENCE:
       - Header: Professional Experience
       - Row 1: [Job Title] @ [Company/Organization] (Left, Bold) | [Date Range] (Right)
       - Row 2+: Bullet points for responsibilities/achievements.
    
    5. PROJECTS:
       - Header: Projects
       - Row 1: [Project Title] (Bold) | [Date/Status] (Right)
    
    6. TECHNICAL SKILLS:
       - Header: Technical Skills
       - Body: Grouped (e.g., 'Languages:', 'Frameworks:').
    
    7. SOFT SKILLS:
       - Centered horizontally separated by bullets (•).
    
    DATA:
    " . json_encode($profile) . "
    
    Return ONLY the HTML <div> content. No markdown wrappers.";
    
    return $prompt;
}

function generateResumeWithAI($profile) {
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    $model = defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini';
    
    if (empty($apiKey)) return false;
    
    $prompt = createResumePrompt($profile);
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) return false;
    curl_close($ch);
    
    $result = json_decode($response, true);
    $html = $result['choices'][0]['message']['content'] ?? '';
    
    // Clean potential markdown wrappers
    $html = trim($html);
    if (strpos($html, '```html') === 0) {
        $html = substr($html, 7);
        if (strrpos($html, '```') === strlen($html) - 3) {
            $html = substr($html, 0, -3);
        }
    } elseif (strpos($html, '```') === 0) {
        $html = substr($html, 3);
        if (strrpos($html, '```') === strlen($html) - 3) {
            $html = substr($html, 0, -3);
        }
    }
    
    return trim($html);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Resume Builder – AI Powered</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root {
            --primary-maroon: #5b1f1f;
            --dark-maroon: #3d1515;
            --bg-color: #f4f7f6;
            --card-bg: #ffffff;
            --text-color: #333;
            --accent-color: #800000;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        header {
            background: linear-gradient(135deg, var(--primary-maroon), var(--dark-maroon));
            color: white;
            padding: 1.5rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .back-link { color: white; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 5px; opacity: 0.9; transition: opacity 0.2s; }
        .back-link:hover { opacity: 1; }

        .main-container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .control-panel {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: center;
            justify-content: space-between;
        }

        .action-buttons { display: flex; gap: 1rem; flex-wrap: wrap; }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .btn-primary { background-color: var(--primary-maroon); color: white; }
        .btn-primary:hover { background-color: var(--dark-maroon); transform: translateY(-1px); }
        .btn-secondary { background-color: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background-color: #cbd5e0; }
        .btn-success { background-color: #2f855a; color: white; }
        .btn-success:hover { background-color: #276749; }
        .btn-outline { border: 2px solid var(--primary-maroon); color: var(--primary-maroon); background: transparent; }
        .btn-outline:hover { background: var(--primary-maroon); color: white; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .upload-form { display: flex; align-items: center; gap: 10px; border-left: 1px solid #e2e8f0; padding-left: 1.5rem; }
        .upload-form input[type="file"] { display: none; }
        .upload-label { cursor: pointer; color: var(--primary-maroon); font-weight: 600; display: flex; align-items: center; gap: 5px; }

        .resume-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            min-height: 600px;
            padding: 2.5rem;
            position: relative;
        }

        #resumeContent {
            margin: 0 auto;
            background: white;
            min-height: 297mm; /* A4 Ratio */
            width: 210mm;
            padding: 1.5rem;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 400px;
            color: #718096;
            text-align: center;
        }
        .empty-state i { font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.3; }

        /* Edit Mode Styles */
        .tabs { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem; }
        .tab { padding: 0.75rem 1.5rem; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; font-weight: 600; color: #718096; }
        .tab.active { border-color: var(--primary-maroon); color: var(--primary-maroon); }

        #visualEditor { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; min-height: 600px; outline: none; }
        #htmlEditor { width: 100%; min-height: 600px; font-family: 'Courier New', monospace; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }

        .edit-toolbar {
            position: sticky;
            top: 75px;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            display: flex;
            gap: 10px;
            z-index: 50;
        }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fff5f5; color: #c53030; border-left: 4px solid #c53030; }
        .alert-success { background: #f0fff4; color: #2f855a; border-left: 4px solid #2f855a; }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.8);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .spinner { width: 50px; height: 50px; border: 5px solid #e2e8f0; border-top: 5px solid var(--primary-maroon); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        @media print {
            header, .control-panel, .edit-toolbar, .tabs, .action-buttons { display: none !important; }
            body { background: white; }
            .main-container { margin: 0; padding: 0; width: 100%; }
            .resume-container { box-shadow: none; padding: 0; }
            #resumeContent { border: none; box-shadow: none; width: 100%; }
        }

        /* Dark Maroon horizontal rules for resume content */
        .resume-section-title {
            border-bottom: 2px solid var(--primary-maroon);
            margin-top: 15px;
            margin-bottom: 10px;
            color: var(--primary-maroon);
            text-transform: uppercase;
            font-weight: bold;
        }
    </style>
</head>
<body>\n    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <!-- <header>
        <h1><i class="fas fa-file-invoice"></i> Dynamic Resume Builder</h1>
        <a href="profile.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Profile</a>
    </header> -->

    <div class="main-container">
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!OPENAI_API_KEY): ?>
            <div class="alert alert-error">
                <i class="fas fa-lock"></i> <strong>OpenAI Not Configured:</strong> Please set <code>OPENAI_API_KEY</code> to enable AI Resume Generation.
            </div>
        <?php endif; ?>

        <div class="control-panel">
            <div class="action-buttons">
                <form method="POST" id="generateForm">
                    <input type="hidden" name="action" value="generate_resume">
                    <button type="submit" class="btn btn-primary" <?php echo !OPENAI_API_KEY ? 'disabled' : ''; ?> onclick="showLoading('Generating your AI Resume...')">
                        <i class="fas fa-magic"></i> Generate AI Resume
                    </button>
                </form>

                <?php if ($current_resume): ?>
                    <a href="?edit=1" class="btn btn-outline"><i class="fas fa-edit"></i> Edit Resume</a>
                    <button class="btn btn-success" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF</button>
                    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                    <a href="?new=1" class="btn btn-secondary" onclick="return confirm('Clear current resume and start fresh?')">
                        <i class="fas fa-sync"></i> Generate New
                    </a>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="action" value="upload_resume">
                <span style="font-size: 0.9rem; color: #718096;">or upload existing:</span>
                <label for="resume_pdf" class="upload-label">
                    <i class="fas fa-cloud-upload-alt"></i> Choose PDF
                </label>
                <input type="file" name="resume_pdf" id="resume_pdf" accept="application/pdf" onchange="this.form.submit(); showLoading('Uploading PDF...')">
            </form>
        </div>

        <div class="resume-container">
            <?php if ($is_edit_mode): ?>
                <div class="edit-mode-container">
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('visual')">Visual Editor</div>
                        <div class="tab" onclick="switchTab('html')">HTML Editor</div>
                    </div>

                    <div id="visualTab">
                        <div id="visualEditor" contenteditable="true">
                            <?php echo $current_resume; ?>
                        </div>
                    </div>

                    <div id="htmlTab" style="display:none;">
                        <textarea id="htmlEditor"><?php echo $current_resume; ?></textarea>
                    </div>

                    <div class="edit-toolbar" style="margin-top: 20px; justify-content: flex-end;">
                        <button class="btn btn-secondary" onclick="window.location.href='resume_builder.php'"><i class="fas fa-times"></i> Cancel</button>
                        <form method="POST" id="saveEditForm" style="display:inline;">
                            <input type="hidden" name="action" value="save_edited_resume">
                            <input type="hidden" name="resume_content" id="save_resume_content">
                            <button type="button" class="btn btn-success" onclick="saveResume()"><i class="fas fa-save"></i> Save Changes</button>
                        </form>
                        <button class="btn btn-primary" onclick="downloadPDF()"><i class="fas fa-download"></i> Download as PDF</button>
                    </div>
                </div>
            <?php elseif ($current_resume): ?>
                <div id="resumeContent">
                    <?php echo $current_resume; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Resume Generated</h3>
                    <p>Click the <strong>Generate</strong> button or <strong>Upload</strong> a PDF to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <h3 id="loadingText" style="margin-top: 20px; color: var(--primary-maroon);">Working on it...</h3>
    </div>

    <script>
        function showLoading(text) {
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('loadingText').innerText = text;
        }

        function switchTab(type) {
            const visualTab = document.getElementById('visualTab');
            const htmlTab = document.getElementById('htmlTab');
            const visualEditor = document.getElementById('visualEditor');
            const htmlEditor = document.getElementById('htmlEditor');
            const tabs = document.querySelectorAll('.tab');

            tabs.forEach(t => t.classList.remove('active'));

            if (type === 'visual') {
                visualTab.style.display = 'block';
                htmlTab.style.display = 'none';
                tabs[0].classList.add('active');
                visualEditor.innerHTML = htmlEditor.value;
            } else {
                visualTab.style.display = 'none';
                htmlTab.style.display = 'block';
                tabs[1].classList.add('active');
                htmlEditor.value = visualEditor.innerHTML;
            }
        }

        function saveResume() {
            const isVisual = document.getElementById('visualTab').style.display !== 'none';
            const content = isVisual ? document.getElementById('visualEditor').innerHTML : document.getElementById('htmlEditor').value;
            document.getElementById('save_resume_content').value = content;
            document.getElementById('saveEditForm').submit();
        }

        async function downloadPDF() {
            showLoading('Preparing PDF...');
            const { jsPDF } = window.jspdf;
            const element = document.getElementById('resumeContent');
            
            // If in edit mode, sync content to resumeContent for capture
            if (document.getElementById('visualEditor')) {
                const isVisual = document.getElementById('visualTab').style.display !== 'none';
                const content = isVisual ? document.getElementById('visualEditor').innerHTML : document.getElementById('htmlEditor').value;
                
                // Temporary hidden container for capture if needed, or just use the current one if visible
                if (!element) {
                    const temp = document.createElement('div');
                    temp.id = 'resumeContent';
                    temp.style.position = 'fixed';
                    temp.style.left = '-10000px';
                    temp.innerHTML = content;
                    document.body.appendChild(temp);
                } else {
                    element.innerHTML = content;
                }
            }

            const canvas = await html2canvas(element, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            });

            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgProps = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save('<?php echo str_replace(" ", "_", $student_name); ?>_Resume.pdf');
            
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    </script>
</body>
</html>
