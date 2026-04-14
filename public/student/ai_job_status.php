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
if (isset($status['user_id']) && $status['user_id'] != getUserId()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access to job status.']);
    exit;
}

// Return status and result
ob_clean();
echo json_encode([
    'success' => true,
    'status' => $status['status'],
    'result' => $status['result'] ?? null,
    'error' => $status['error'] ?? null
]);
