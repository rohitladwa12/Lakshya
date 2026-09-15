<?php
/**
 * Lakshya AI Proctoring - Secure Student Telemetry Handler
 * Server-Authoritative Hardened Endpoint
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Services\ProctoringService;

ob_start();
header('Content-Type: application/json');

// 1. Two-Layer Rate Limiting (Zero DB writes on burst)
$clientIp = getClientIP();
$sessionToken = trim($_POST['token'] ?? $_GET['token'] ?? '');
$rateCheck = ProctoringService::checkRateLimit($sessionToken, $clientIp);
if (!$rateCheck['allowed']) {
    ob_clean();
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => $rateCheck['error'], 'retry_after' => $rateCheck['retry_after'] ?? 5]);
    exit;
}

// 2. Authorize Student Role
requireRole(ROLE_STUDENT);

$studentId = (string)getUsername();
if (empty($studentId)) {
    $studentId = (string)getUserId();
}

$action = trim($_REQUEST['action'] ?? '');

// 3. Payload Size Limit (Max 500KB)
if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 512000) {
    ob_clean();
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'Payload too large (500KB maximum).']);
    exit;
}

try {
    switch ($action) {
        case 'get_settings':
            $assessmentType = trim($_GET['assessment_type'] ?? 'general');
            $config = ProctoringService::getClientConfig($assessmentType);
            ob_clean();
            echo json_encode(['success' => true, 'config' => $config]);
            exit;

        case 'create_session':
            $assessmentId   = (int)($_POST['assessment_id'] ?? 0);
            $assessmentType = trim($_POST['assessment_type'] ?? 'general');

            if ($assessmentId <= 0) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Invalid assessment identifier.']);
                exit;
            }

            $result = ProctoringService::createSession($studentId, $assessmentId, $assessmentType);
            ob_clean();
            echo json_encode($result);
            exit;

        case 'heartbeat':
            $token   = trim($_POST['token'] ?? '');
            $seq     = filter_var($_POST['seq'] ?? 0, FILTER_VALIDATE_INT);
            $payload = [
                'seq'           => $seq,
                'timestamp'     => filter_var($_POST['timestamp'] ?? 0, FILTER_VALIDATE_INT),
                'camera_active' => filter_var($_POST['camera_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'fullscreen'    => filter_var($_POST['fullscreen'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'tab_focused'   => filter_var($_POST['tab_focused'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];

            $result = ProctoringService::processHeartbeat($token, $studentId, $payload);
            ob_clean();
            echo json_encode($result);
            exit;

        case 'report_observation':
        case 'report_event':
            $token       = trim($_POST['token'] ?? '');
            $eventType   = trim($_POST['event_type'] ?? '');
            $durationMs  = filter_var($_POST['duration_ms'] ?? 0, FILTER_VALIDATE_INT);
            $confidence  = $_POST['confidence'] ?? $_POST['model_confidence'] ?? 1.0;
            $seq         = filter_var($_POST['seq'] ?? 0, FILTER_VALIDATE_INT);
            $snapshot    = $_POST['snapshot'] ?? null;
            $details     = $_POST['details'] ?? $_POST['metadata'] ?? [];

            $observation = [
                'seq'              => $seq,
                'event_type'       => $eventType,
                'duration_ms'      => $durationMs,
                'model_confidence' => $confidence,
                'snapshot'         => $snapshot,
                'metadata'         => $details
            ];

            $result = ProctoringService::recordObservation($token, $studentId, $observation);
            ob_clean();
            echo json_encode($result);
            exit;

        case 'end_session':
            $token  = trim($_POST['token'] ?? '');
            $result = ProctoringService::endSession($token, $studentId);
            ob_clean();
            echo json_encode($result);
            exit;

        default:
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Unrecognized action: {$action}"]);
            exit;
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error processing telemetry.']);
    exit;
}