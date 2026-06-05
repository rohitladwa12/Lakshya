<?php
require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

header('Content-Type: application/json');

$userId = getUserId();
$username = getUsername();
$institution = getInstitution() ?? ((strpos($userId, 'GMU') !== false) ? 'GMU' : 'GMIT');

require_once __DIR__ . '/../../src/Services/StudentIntelligenceService.php';
require_once __DIR__ . '/../../src/Services/AIService.php';

$service = new \App\Services\StudentIntelligenceService();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'submit_challenge') {
        $challengeId = (int)($_POST['challenge_id'] ?? 0);
        
        $selectedOptions = null;
        if (isset($_POST['selected_options'])) {
            $selectedOptions = json_decode($_POST['selected_options'], true);
        } elseif (isset($_POST['selected_option'])) {
            $selectedOptions = [(int)$_POST['selected_option']];
        }

        if ($challengeId <= 0 || empty($selectedOptions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        $res = $service->submitChallengeResponse($challengeId, $username, $institution, $selectedOptions);
        echo json_encode($res);
        exit;
    } 
    
    elseif ($action === 'sync_profile') {
        $fullName = getFullName();
        $res = $service->syncStudentAIProfile($username, $institution, $fullName);
        echo json_encode($res);
        exit;
    } 
    
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
