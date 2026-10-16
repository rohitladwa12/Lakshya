<?php
/**
 * Student-side safety net for timed task generation.
 *
 * Normally the coordinator's browser generates the questions right after assigning.
 * If that never ran (page closed, network drop) or failed, the first student who opens
 * the task triggers it here. The DB claim in TaskAssessmentService guarantees only one
 * generation runs per task no matter how many students open it.
 *
 * POST action=status|generate  task_id
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireLogin();
header('Content-Type: application/json');
session_write_close();

$taskId = (int) ($_POST['task_id'] ?? 0);
$action = $_POST['action'] ?? 'status';

try {
    $db = getDB();
    TaskAssessmentService::ensureSchema($db);
    $task = TaskAssessmentService::getTask($db, $taskId);

    // Only students the task was assigned to may trigger anything
    $assigned = $task && TaskAssessmentService::isAssignedTo($task, TaskAssessmentService::studentIdentifiers());
    if (!$task || !$assigned || !TaskAssessmentService::isTimed($task)) {
        echo json_encode(['success' => false, 'message' => 'Task not found.']);
        exit;
    }

    $status = $task['generation_status'];

    if ($action === 'generate' && in_array($status, ['pending', 'failed', 'generating'], true)) {
        // Failed generations are retried at most every 5 minutes from the student side
        $canRetry = $status === 'pending'
            || ($status === 'failed' && (empty($task['generation_started_at']) || strtotime($task['generation_started_at']) < time() - 300))
            || $status === 'generating'; // claimGeneration() only takes over a stale (crashed) run
        if ($canRetry) {
            if (!checkRateLimit("task_prepare_" . getUserId(), 3, 60)) {
                echo json_encode(['success' => true, 'status' => $status]);
                exit;
            }
            $res = TaskAssessmentService::generate($db, $taskId);
            $status = $res['status'] ?? $status;
        }
    }

    echo json_encode(['success' => true, 'status' => $status]);
} catch (Throwable $e) {
    error_log("task_prepare.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
