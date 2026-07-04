<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$stmt = $db->prepare("SELECT id, usn, score, details FROM unified_ai_assessments WHERE DATE(started_at) = CURDATE() AND score <= 20");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deletedAssessments = 0;
$deletedTasks = 0;

foreach ($rows as $row) {
    $id = $row['id'];
    $usn = $row['usn'];
    $details = json_decode($row['details'], true);
    
    // Check if the history has many repetitions or gibberish (usually indicated by >= 10 history items but very low score, or just today's bug).
    // Actually, any score <= 20 today is almost certainly due to the bug.
    
    $taskId = $details['task_id'] ?? null;
    
    if ($taskId) {
        $delTask = $db->prepare("DELETE FROM task_completions WHERE student_id = ? AND task_id = ? AND DATE(completed_at) = CURDATE()");
        $delTask->execute([$usn, $taskId]);
        $deletedTasks += $delTask->rowCount();
    }
    
    $del = $db->prepare("DELETE FROM unified_ai_assessments WHERE id = ?");
    $del->execute([$id]);
    $deletedAssessments++;
}

echo "Cleaned up $deletedAssessments broken assessments and $deletedTasks task completions for today.\n";
