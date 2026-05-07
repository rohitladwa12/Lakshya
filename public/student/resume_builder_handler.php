<?php
/**
 * Resume Builder – AJAX Handler
 * Actions: load_resume | save_resume
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

header('Content-Type: application/json');

$userId   = getUserId();   // integer student_id from users table
$username = getUsername(); // USN string

require_once __DIR__ . '/../../src/Models/Resume.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
require_once __DIR__ . '/../../src/Models/Portfolio.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── LOAD ────────────────────────────────────────────────────────────────────
if ($action === 'load_resume') {

    $resumeModel  = new Resume();
    $existing     = $resumeModel->getByStudentId($userId);

    if ($existing) {
        echo json_encode(['success' => true, 'resume' => $existing]);
        exit;
    }

    // No saved resume yet → auto-populate from profile + portfolio
    $studentModel = new StudentProfile();
    $institution  = $_SESSION['institution'] ?? 'GMU';
    $profile      = $studentModel->getByUserId($username, $institution);

    $portfolioModel = new Portfolio();
    $portfolioItems = $portfolioModel->getStudentPortfolio($username, $institution);

    $technicalSkills = [];
    $projects        = [];
    $certifications  = [];

    foreach ($portfolioItems as $item) {
        if ($item['category'] === 'Skill') {
            $technicalSkills[] = $item['title'];
        } elseif ($item['category'] === 'Project') {
            $projects[] = [
                'title'        => $item['title'],
                'description'  => $item['description'] ?? '',
                'technologies' => [],
                'link'         => $item['link'] ?? '',
                'start_date'   => (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') ? substr($item['start_date'], 0, 7) : '',
                'end_date'     => (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00') ? substr($item['end_date'], 0, 7) : '',
                'ongoing'      => (empty($item['end_date']) || $item['end_date'] === '0000-00-00') && (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00'),
            ];
        } elseif ($item['category'] === 'Certification') {
            $certsInRow = json_decode($item['certificate_attachments'] ?? '[]', true);
            if (!empty($certsInRow) && is_array($certsInRow)) {
                foreach ($certsInRow as $c) {
                    $certifications[] = [
                        'name'           => $c['title'] ?? $item['title'],
                        'issuer'         => $c['sub_title'] ?? $item['sub_title'] ?? '',
                        'date'           => !empty($c['added_at']) ? substr($c['added_at'], 0, 7) : '',
                        'credential_url' => $c['link'] ?? $item['link'] ?? '',
                        'description'    => $c['description'] ?? $item['description'] ?? '',
                    ];
                }
            } elseif ($item['title'] !== 'My Certifications') {
                $certifications[] = [
                    'name'           => $item['title'],
                    'issuer'         => $item['sub_title'] ?? '',
                    'date'           => !empty($item['date_completed']) ? substr($item['date_completed'], 0, 7) : '',
                    'credential_url' => $item['link'] ?? '',
                    'description'    => $item['description'] ?? '',
                ];
            }
        }
    }

    // ── Build education entries from profile ───────────────────────────────
    $education = [];

    // 1) Current degree (college / university)
    $degreeName = trim(($profile['programme'] ?? '') . ' ' . ($profile['department'] ?? ''));
    if (!empty($degreeName)) {
        $instName = (strtoupper($institution) === 'GMIT') ? 'GM Institute of Technology' : 'GM University';
        $education[] = [
            'degree'      => $degreeName,
            'institution' => $instName,
            'location'    => '',
            'start_date'  => '',
            'end_date'    => '',
            'ongoing'     => true,
            'gpa'         => !empty($profile['cgpa']) ? (string)$profile['cgpa'] : '',
        ];
    }

    // 2) PUC / 12th grade
    $pucPct = $profile['puc_percentage'] ?? 0;
    if ($pucPct > 0) {
        $education[] = [
            'degree'      => 'Pre-University Course (PUC / 12th)',
            'institution' => '',
            'location'    => '',
            'start_date'  => '',
            'end_date'    => '',
            'ongoing'     => false,
            'cgpa'        => $pucPct . '%',
        ];
    }

    // 3) SSLC / 10th grade
    $sslcPct = $profile['sslc_percentage'] ?? 0;
    if ($sslcPct > 0) {
        $education[] = [
            'degree'      => 'SSLC / 10th Grade',
            'institution' => '',
            'location'    => '',
            'start_date'  => '',
            'end_date'    => '',
            'ongoing'     => false,
            'cgpa'        => $sslcPct . '%',
        ];
    }

    $autofill = [
        'full_name'            => $profile['name']           ?? '',
        'email'                => $profile['email']          ?? '',
        'phone'                => $profile['student_mobile'] ?? $profile['phone'] ?? '',
        'location'             => '',
        'linkedin_url'         => '',
        'github_url'           => '',
        'portfolio_url'        => '',
        'professional_summary' => '',
        'template_id'          => 'professional_ats',
        'education'            => $education,
        'experience'           => [],
        'projects'             => $projects,
        'skills'               => [
            'technical' => $technicalSkills,
            'soft'      => [],
            'languages' => [],
        ],
        'certifications' => $certifications,
        'achievements'   => [],
    ];

    echo json_encode(['success' => true, 'resume' => $autofill, 'autofilled' => true]);
    exit;
}

// ─── FETCH PORTFOLIO (FOR SYNC) ──────────────────────────────────────────────
if ($action === 'fetch_portfolio') {
    $institution  = $_SESSION['institution'] ?? 'GMU';
    
    $portfolioModel = new Portfolio();
    $items = $portfolioModel->getStudentPortfolio($username, $institution);
    
    $skills = [];
    $projects = [];
    $certs = [];
    
    foreach ($items as $item) {
        if ($item['category'] === 'Skill') {
            $skills[] = $item['title'];
        } elseif ($item['category'] === 'Project') {
            $projects[] = [
                'title'        => $item['title'],
                'description'  => $item['description'] ?? '',
                'technologies' => [],
                'link'         => $item['link'] ?? '',
                'start_date'   => (!empty($item['start_date']) && $item['start_date'] !== '0000-00-00') ? substr($item['start_date'], 0, 7) : '',
                'end_date'     => (!empty($item['end_date']) && $item['end_date'] !== '0000-00-00') ? substr($item['end_date'], 0, 7) : '',
            ];
        } elseif ($item['category'] === 'Certification') {
            $certsInRow = json_decode($item['certificate_attachments'] ?? '[]', true);
            if (!empty($certsInRow) && is_array($certsInRow)) {
                foreach ($certsInRow as $c) {
                    $certs[] = [
                        'name'           => $c['title'] ?? $item['title'],
                        'issuer'         => $c['sub_title'] ?? $item['sub_title'] ?? '',
                        'date'           => !empty($c['added_at']) ? substr($c['added_at'], 0, 7) : '',
                        'credential_url' => $c['link'] ?? $item['link'] ?? '',
                        'description'    => $c['description'] ?? $item['description'] ?? '',
                    ];
                }
            } elseif ($item['title'] !== 'My Certifications') {
                $certs[] = [
                    'name'           => $item['title'],
                    'issuer'         => $item['sub_title'] ?? '',
                    'date'           => !empty($item['date_completed']) ? substr($item['date_completed'], 0, 7) : '',
                    'credential_url' => $item['link'] ?? '',
                    'description'    => $item['description'] ?? '',
                ];
            }
        }
    }
    
    echo json_encode([
        'success'        => true, 
        'skills'         => $skills, 
        'projects'       => $projects, 
        'certifications' => $certs
    ]);
    exit;
}

// ─── UPLOAD MANUAL PDF ────────────────────────────────────────────────────────
if ($action === 'upload_external_pdf') {
    if (!isset($_FILES['resume_pdf']) || $_FILES['resume_pdf']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or error during upload']);
        exit;
    }

    $file = $_FILES['resume_pdf'];
    if ($file['type'] !== 'application/pdf') {
        echo json_encode(['success' => false, 'error' => 'Only PDF files allowed']);
        exit;
    }

    $uploadDir = UPLOADS_PATH . '/resumes/Student_Resumes';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);
    $fileName = strtoupper($safeName) . '_Resume.pdf';
    $destPath = $uploadDir . '/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Internal server error saving file']);
    }
    exit;
}

// ─── SAVE ────────────────────────────────────────────────────────────────────
if ($action === 'save_resume' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Data is coming as FormData now: $_POST['resume_data'] and $_FILES['resume_pdf']
        $jsonString = $_POST['resume_data'] ?? '';
        $input = json_decode($jsonString, true);

        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
            exit;
        }

        // Whitelist the fields we accept
        $allowed = [
            'full_name', 'email', 'phone', 'location', 'gender', 'address',
            'linkedin_url', 'github_url', 'portfolio_url', 'professional_summary',
            'education', 'experience', 'projects', 'skills',
            'certifications', 'achievements', 'template_id',
        ];

        $resumeData = [];
        foreach ($allowed as $key) {
            if (isset($input[$key])) {
                $resumeData[$key] = $input[$key];
            }
        }

        if (empty($resumeData['full_name']) || empty($resumeData['email'])) {
            echo json_encode(['success' => false, 'error' => 'Name and email are required']);
            exit;
        }

        $resumeModel = new Resume();
        $ok = $resumeModel->saveResume($userId, $resumeData);

        // --- SYNCHRONIZE WITH PORTFOLIO ---
        if ($ok) {
            $institution = $_SESSION['institution'] ?? 'GMU';
            $portfolioModel = new Portfolio();
            
            // 1. Sync Projects
            if (!empty($resumeData['projects'])) {
                $portfolioModel->syncProjects($username, $institution, $resumeData['projects']);
            }
            
            // 2. Sync Certifications
            if (!empty($resumeData['certifications'])) {
                $portfolioModel->syncCertifications($username, $institution, $resumeData['certifications']);
            }
            
            // 3. Sync Skills (Technical ONLY, mapping to expected format)
            if (!empty($resumeData['skills']['technical'])) {
                // Resume Builder format: [{"category": "Languages", "items": ["Java", "C++"]}]
                // Expected Portfolio format: [{"category": "Languages", "items": ["Java", "C++"]}]
                $portfolioModel->syncSkills($username, $institution, $resumeData['skills']['technical']);
            }
        }
        // ----------------------------------

        $pdfUrl = '';
        // Save PDF if sent
        if ($ok && isset($_FILES['resume_pdf']) && $_FILES['resume_pdf']['error'] === UPLOAD_ERR_OK) {
            // Defined in config/constants.php as UPLOADS_PATH
            $uploadDir = UPLOADS_PATH . '/resumes/Student_Resumes';
            
            // Ensure directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // e.g. "GMU_12345_CV.pdf" or "ROHIT_gm20cs001_CV.pdf"
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);
            $fileName = strtoupper($safeName) . '_Resume.pdf';
            $destPath = $uploadDir . '/' . $fileName;

            if (move_uploaded_file($_FILES['resume_pdf']['tmp_name'], $destPath)) {
                // Success - construct SECURE proxy URL
                $pdfUrl = "view_resume.php?usn=" . urlencode($username);
            } else {
                logMessage("Failed to move uploaded resume PDF to $destPath", 'ERROR');
            }
        }

        echo json_encode(['success' => (bool)$ok, 'pdf_url' => $pdfUrl]);
    } catch (\Throwable $e) {
        $debugInfo = $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString();
        logMessage($debugInfo, 'FATAL_RESUME_SAVE');
        http_response_code(200); // Override 500 so JSON can be parsed by the frontend
        echo json_encode(['success' => false, 'error' => $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
