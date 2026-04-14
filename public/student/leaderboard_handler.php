<?php
/**
 * Leaderboard Handler - AJAX API
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Services\LeaderboardService;

// Prevent PHP warnings from polluting JSON payload
ob_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$view = isset($_REQUEST['view']) ? $_REQUEST['view'] : 'local';
$myDept = getDepartment();
$myInst = getInstitution();

// Fallback: If department is missing from session, fetch it from profile
if (empty($myDept)) {
    $studentModel = new StudentProfile();
    $profile = $studentModel->getByUserId(getUserId());
    if ($profile) {
        $myDept = $profile['department'] ?? ($profile['discipline'] ?? null);
    }
}

// Define filters
$filters = [];
if ($view === 'local') {
    // Use the robust mapper to catch GMU/GMIT variations (e.g. CSE vs CS)
    $filters['discipline'] = getCoordinatorDisciplineFilters($myDept);
    $filters['institution'] = $myInst;
}

try {
    $rankings = LeaderboardService::getRankingsWithHistory($filters);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'rankings' => $rankings
    ]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
