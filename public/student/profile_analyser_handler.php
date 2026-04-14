<?php
require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$username = getUsername();
$institution = getInstitution() ?? ((strpos($userId, 'GMU') !== false) ? 'GMU' : 'GMIT');
$mode = $_POST['mode'] ?? $_GET['mode'] ?? 'market'; // market, target, career

// Load Models
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
require_once __DIR__ . '/../../src/Models/Portfolio.php';
require_once __DIR__ . '/../../src/Services/AIService.php';

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../src/Models/AIAnalysisCache.php';
    $studentModel = new StudentProfile();
    $portfolioModel = new Portfolio();
    $aiService = new AIService();
    $cacheModel = new AIAnalysisCache();

    // 1. Fetch Student Data
    $profile = $studentModel->getByUserId($userId);
    $portfolio = $portfolioModel->getStudentPortfolio($username, $institution);
    
    // Group portfolio by category
    $skills = [];
    $projects = [];
    $certs = [];
    foreach ($portfolio as $item) {
        if ($item['category'] === 'Skill') {
            $skills[] = [
                'name' => $item['title'],
                'proficiency' => $item['sub_title'] ?: 'Not Specified'
            ];
        }
        elseif ($item['category'] === 'Project') {
            $projects[] = [
                'title' => $item['title'],
                'description' => $item['description']
            ];
        }
        elseif ($item['category'] === 'Certification') {
            $certs[] = [
                'name' => $item['title'],
                'issuer' => $item['sub_title']
            ];
        }
    }

    $studentData = [
        'name' => $profile['name'],
        'cgpa' => $profile['cgpa'],
        'course' => $profile['course'],
        'department' => $profile['department'],
        'technical_skills' => $skills,
        'projects' => $projects,
        'certifications' => $certs
    ];

    // 2. Generate Data Hash (to detect changes in skills/projects/profile)
    $dataHash = hash('sha256', json_encode($studentData));

    // 3. Prepare Context Vars
    $role = $_POST['role'] ?? $_GET['role'] ?? null;
    $company = $_POST['company'] ?? $_GET['company'] ?? null;
    if ($mode === 'target' && !$role) $role = 'Software Engineer';
    if ($mode === 'target' && !$company) $company = 'Google';

    // 4. Check Cache
    $cachedResult = $cacheModel->getCachedAnalysis($userId, $mode, $company, $role, $dataHash);
    if ($cachedResult) {
        echo json_encode([
            'success' => true,
            'analysis' => json_decode($cachedResult, true),
            'cached' => true
        ]);
        exit;
    }

    // 5. Call AI Service based on mode (Cache Miss)
    session_write_close();
    $response = null;
    if ($mode === 'target') {
        $response = $aiService->analyzeTargetFit($studentData, $role, $company);
    } elseif ($mode === 'career') {
        $response = $aiService->predictCareerPath($studentData);
    } else {
        $response = $aiService->analyzeProfileMatch($studentData, $company);
    }

    if ($response && $response['success']) {
        // Save to cache
        $cacheModel->cacheAnalysis($userId, $mode, $company, $role, $dataHash, $response['content']);

        echo json_encode([
            'success' => true,
            'analysis' => json_decode($response['content'], true),
            'cached' => false
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $response['message'] ?? 'AI Analysis failed']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
