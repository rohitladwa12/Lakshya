<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$taskCompletionId = 1284;
$assessmentId = 3590;
$newScore = 76.00;

echo "=== UPDATING STUDENT SCORES ===\n";

try {
    $db->beginTransaction();

    // 1. Update task_completions
    $stmt1 = $db->prepare("UPDATE task_completions SET score = ? WHERE id = ?");
    $stmt1->execute([$newScore, $taskCompletionId]);
    $count1 = $stmt1->rowCount();
    echo "Updated task_completions (ID: $taskCompletionId): $count1 rows affected.\n";

    // 2. Update unified_ai_assessments
    $stmt2 = $db->prepare("UPDATE unified_ai_assessments SET score = ? WHERE id = ?");
    $stmt2->execute([$newScore, $assessmentId]);
    $count2 = $stmt2->rowCount();
    echo "Updated unified_ai_assessments (ID: $assessmentId): $count2 rows affected.\n";

    $db->commit();
    echo "Transaction committed successfully!\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error updating scores: " . $e->getMessage() . "\n";
}
