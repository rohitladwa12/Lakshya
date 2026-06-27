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
$myUsn = getUsername();

// Fallback: If department is missing from session, fetch it from profile
if (empty($myDept)) {
    $studentModel = new StudentProfile();
    $profile = $studentModel->getByUserId(getUserId());
    if ($profile) {
        $myDept = $profile['department'] ?? ($profile['discipline'] ?? null);
    }
}

// Second fallback for GMIT: look up discipline from remote DB by student_id or Aadhar
if (empty($myDept) && !empty($myUsn)) {
    try {
        $remoteDB = getDB('gmit');
        if ($remoteDB) {
            $stmtDept = $remoteDB->prepare("SELECT discipline FROM ad_student_details WHERE student_id = ? OR usn = ? OR aadhar = ? LIMIT 1");
            $stmtDept->execute([$myUsn, $myUsn, $myUsn]);
            $myDept = $stmtDept->fetchColumn() ?: null;
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// Define filters
$filters = [];
if ($view === 'local') {
    // Use the robust mapper to catch GMU/GMIT variations (e.g. CSE vs CS, ECE vs EC)
    // Do NOT lock by institution — show all students of same discipline across both GMU & GMIT
    $filters['discipline'] = getCoordinatorDisciplineFilters($myDept);
    // Only lock institution if it's GMU (they have clean discipline matching).
    // GMIT students share departments with GMU so both should see each other on the local board.
    if ($myInst === INSTITUTION_GMU) {
        $filters['institution'] = INSTITUTION_GMU;
    }
    // For GMIT students: no institution filter — show cross-institutional discipline peers
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
