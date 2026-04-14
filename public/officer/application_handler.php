<?php
/**
 * Application Handler - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

// Handle JSON requests
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_POST = array_merge($_POST, $input);
}

$action = post('action');
$appModel = new JobApplication();

switch ($action) {
    case 'update_status':
        $applicationId = post('application_id');
        $status = post('status');
        $notes = post('notes');
        
        if ($appModel->updateStatus($applicationId, $status, $notes)) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        break;

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
