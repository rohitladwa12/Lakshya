<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$studentId = 'GMIT23AI12';
$taskId = 918;
$score = 75.00;
$timeTaken = 1321; // 20 minutes default
$completedAt = '2026-07-02 12:31:26';

echo "=== Checking if task_completions record already exists ===\n";
$stmt = $db->prepare("SELECT * FROM task_completions WHERE task_id = ? AND student_id = ?");
$stmt->execute([$taskId, $studentId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($existing);

if (!$existing) {
    echo "\n=== Inserting into task_completions ===\n";
    $stmtInsert = $db->prepare("INSERT INTO task_completions (task_id, student_id, score, time_taken, completed_at) VALUES (?, ?, ?, ?, ?)");
    $res = $stmtInsert->execute([$taskId, $studentId, $score, $timeTaken, $completedAt]);
    echo "Insertion result: " . ($res ? "SUCCESS" : "FAILED") . "\n";
    
    // Let's verify by fetching again
    $stmt->execute([$taskId, $studentId]);
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} else {
    echo "Record already exists, no need to insert.\n";
}
