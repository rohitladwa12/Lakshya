<?php
/**
 * Leaderboard Handler - Coordinator
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require coordinator role
requireRole(ROLE_DEPT_COORDINATOR);

// Handle JSON requests
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_POST = array_merge($_POST, $input);
}

$action = post('action');

switch ($action) {
    case 'get_academic_history':
        $studentId = post('student_id'); // This is the USN
        if (!$studentId) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            break;
        }

        $profileModel = new StudentProfile();
        $history = $profileModel->getAcademicHistory($studentId);
        
        if ($history) {
            echo json_encode(['success' => true, 'history' => $history]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Academic history not found']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
