<?php
/**
 * Task question generation endpoint (coordinator)
 *
 * POST action=generate  task_id  -> generates (parallel AI batches) and stores the
 *                                   questions/problems for a timed task. Long-running.
 * POST action=status    task_id  -> current generation status + progress (for polling)
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

header('Content-Type: application/json');

$coordinatorId = getUserId();
$action = $_POST['action'] ?? '';
$taskId = (int) ($_POST['task_id'] ?? 0);

// Release the session lock: generation runs for 1-3 minutes and the coordinator's
// status polls (and other pages) must not queue behind it.
session_write_close();

try {
    $db = getDB();
    TaskAssessmentService::ensureSchema($db);

    $stmt = $db->prepare("SELECT id, task_type, question_count, generation_status, generation_progress, generation_note
                          FROM coordinator_tasks WHERE id = ? AND coordinator_id = ?");
    $stmt->execute([$taskId, $coordinatorId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found.']);
        exit;
    }

    if ($action === 'generate') {
        $res = TaskAssessmentService::generate($db, $taskId);
        echo json_encode($res);
        exit;
    }

    if ($action === 'status') {
        // A generation whose PHP process died is reported as failed so the coordinator can retry
        $status = $task['generation_status'];
        if ($status === 'generating') {
            $stale = $db->prepare("SELECT generation_started_at < NOW() - INTERVAL " . TaskAssessmentService::STALE_GENERATION_MINUTES . " MINUTE FROM coordinator_tasks WHERE id = ?");
            $stale->execute([$taskId]);
            if ((int) $stale->fetchColumn() === 1) {
                $status = 'failed';
            }
        }
        echo json_encode([
            'success' => true,
            'task_type' => $task['task_type'],
            'status' => $status,
            'progress' => (int) $task['generation_progress'],
            'total' => (int) $task['question_count'],
            'note' => $task['generation_note'],
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    error_log("task_generation.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please retry.']);
}
