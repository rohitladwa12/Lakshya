<?php
// MUST be the very first lines to catch startup output
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

/**
 * AI Job Status Poller
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/QueueService.php';

use App\Services\QueueService;

header('Content-Type: application/json');

// Release session lock immediately. Polling shouldn't block other requests.
session_write_close();

$jobId = $_GET['job_id'] ?? ($_POST['job_id'] ?? '');

if (empty($jobId)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing Job ID']);
    exit;
}

$status = QueueService::getJobStatus($jobId);

if (!$status) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Job not found']);
    exit;
}

// Security: Ownership check
if (isset($status['user_id'])) {
    $jobOwnerId = trim($status['user_id']);
    $currentUser = trim((string)getUserId());
    
    if ($jobOwnerId !== $currentUser) {
        $resolved = false;
        $db = getDB();
        try {
            $gmuPrefix = DB_GMU_PREFIX;
            $gmitPrefix = DB_GMIT_PREFIX;
            
            // Get usn and aadhar for current user
            $stmt = $db->prepare("
                SELECT usn, aadhar FROM (
                    SELECT usn, aadhar, usn as student_id_map FROM {$gmuPrefix}ad_student_approved
                    UNION ALL
                    SELECT IFNULL(NULLIF(usn, ''), student_id) as usn, aadhar, student_id as student_id_map FROM {$gmitPrefix}ad_student_details
                ) asa WHERE asa.usn = ? OR asa.aadhar = ? OR asa.student_id_map = ? LIMIT 1
            ");
            $stmt->execute([$currentUser, $currentUser, $currentUser]);
            $currRow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currRow) {
                $currUsn = $currRow['usn'];
                $currAadhar = $currRow['aadhar'];
                
                // Get usn and aadhar for job owner
                $stmt->execute([$jobOwnerId, $jobOwnerId, $jobOwnerId]);
                $ownerRow = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ownerRow) {
                    $ownerUsn = $ownerRow['usn'];
                    $ownerAadhar = $ownerRow['aadhar'];
                    
                    if (($currUsn && $currUsn === $ownerUsn) || ($currAadhar && $currAadhar === $ownerAadhar)) {
                        $resolved = true;
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
        
        if (!$resolved) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Unauthorized access to job status.']);
            exit;
        }
    }
}

if ($status['status'] === 'completed' && !empty($status['result'])) {
    // Re-open session to store the dynamically generated questions
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $resultPayload = $status['result'];
    $aiQuestions = [];
    if (is_array($resultPayload)) {
        if (isset($resultPayload['questions']) && is_array($resultPayload['questions'])) {
            $aiQuestions = $resultPayload['questions'];
        } else if (isset($resultPayload['result']) && is_array($resultPayload['result'])) {
            $aiQuestions = $resultPayload['result'];
        } else {
            $aiQuestions = $resultPayload;
        }
    } else if (is_string($resultPayload)) {
        $decoded = json_decode($resultPayload, true);
        $aiQuestions = $decoded['questions'] ?? $decoded['result'] ?? $decoded ?? [];
    }

    // Format AI questions: convert 'answer' index securely
    if (is_array($aiQuestions) && !empty($aiQuestions) && isset($aiQuestions[0]) && is_array($aiQuestions[0])) {
        foreach ($aiQuestions as &$q) {
            if (isset($q['answer'])) {
                // Ensure it is mapped to 0-3 index if it was returned as a character or invalid
                if (is_string($q['answer'])) {
                    $ansLetter = strtoupper(trim($q['answer']));
                    if (in_array($ansLetter, ['A', 'B', 'C', 'D'])) {
                        $q['answer'] = match($ansLetter) { 'A'=>0, 'B'=>1, 'C'=>2, 'D'=>3 };
                    } else {
                        $q['answer'] = (int)$q['answer'];
                    }
                }
            } else {
                $q['answer'] = 0;
            }
        }
        $_SESSION['aptitude_ai_questions'] = $aiQuestions;
    }
    session_write_close();
}

// Return status and result
ob_clean();
echo json_encode([
    'success' => true,
    'status' => $status['status'],
    'result' => $status['result'] ?? null,
    'error' => $status['error'] ?? null
]);
