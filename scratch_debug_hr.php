<?php
require_once __DIR__ . '/config/bootstrap.php';
$db = getDB();

echo "=== UNIFIED AI ASSESSMENTS (HR ROUND) ===\n";
$stmt = $db->query("SELECT id, student_name, score, details, started_at, completed_at FROM unified_ai_assessments WHERE assessment_type = 'HR' ORDER BY completed_at DESC LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $details = json_decode($row['details'], true);
    $history = $details['history'] ?? [];
    $userCount = 0;
    foreach ($history as $msg) {
        if ($msg['role'] === 'user') $userCount++;
    }
    echo "ID: {$row['id']} | Student: {$row['student_name']} | Score: {$row['score']} | Total turns: " . count($history) . " | User turns: {$userCount}\n";
    if ($row['score'] <= 20) {
        echo "Transcript:\n";
        foreach ($history as $msg) {
            echo "  - {$msg['role']}: {$msg['content']}\n";
        }
        echo "----------------------------------------\n";
    }
}

echo "\n=== STUDENT DRIVE ATTEMPTS (HR ROUND) ===\n";
$stmt = $db->query("SELECT id, student_name, score, details, started_at, completed_at FROM student_drive_attempts WHERE round_type = 'HR' ORDER BY completed_at DESC LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $details = json_decode($row['details'], true);
    $history = $details['history'] ?? [];
    $userCount = 0;
    foreach ($history as $msg) {
        if ($msg['role'] === 'user') $userCount++;
    }
    echo "ID: {$row['id']} | Student: {$row['student_name']} | Score: {$row['score']} | Total turns: " . count($history) . " | User turns: {$userCount}\n";
    if ($row['score'] <= 20) {
        echo "Transcript:\n";
        foreach ($history as $msg) {
            echo "  - {$msg['role']}: {$msg['content']}\n";
        }
        echo "----------------------------------------\n";
    }
}
