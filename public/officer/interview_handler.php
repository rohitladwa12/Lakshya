<?php
/**
 * Interview Handler - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$db = getDB();

// Handle JSON requests
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_POST = array_merge($_POST, $input);
}

$action = post('action');

switch ($action) {
    case 'schedule':
        $appId = post('application_id');
        $type = post('interview_type');
        $date = post('interview_date');
        $mode = post('mode');
        $location = post('location');
        
        $sql = "INSERT INTO interviews (application_id, application_type, interview_date, interview_type, mode, location, status)
                VALUES (?, 'job', ?, ?, ?, ?, 'Scheduled')";
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute([$appId, $date, $type, $mode, $location])) {
            // Update application status to "Interview Scheduled"
            $appModel = new JobApplication();
            $appModel->updateStatus($appId, 'Interview Scheduled');
            
            Session::flash('success', 'Interview scheduled successfully');
        } else {
            Session::flash('error', 'Failed to schedule interview');
        }
        redirect('interviews.php');
        break;

    case 'complete':
        $interviewId = post('interview_id');
        $feedback = post('feedback');
        
        // Simple heuristic to determine result from prompt
        $result = 'Pending';
        if (stripos($feedback, 'Selected') !== false) $result = 'Selected';
        elseif (stripos($feedback, 'Rejected') !== false) $result = 'Rejected';

        $sql = "UPDATE interviews SET status = 'Completed', feedback = ?, result = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute([$feedback, $result, $interviewId])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to complete interview']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
