<?php
require_once __DIR__ . '/config/bootstrap.php';
$db = getDB();

echo "=== UNIFIED AI ASSESSMENTS (NQT HR ROUND) ===\n";
$stmt = $db->query("SELECT id, student_name, score, details, started_at, completed_at FROM unified_ai_assessments WHERE assessment_type = 'NQT HR' ORDER BY id DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Student: {$row['student_name']} | Score: {$row['score']} | Details length: " . strlen($row['details']) . "\n";
    $details = json_decode($row['details'], true);
    $history = $details['history'] ?? [];
    echo "Turns: " . count($history) . "\n";
    foreach ($history as $idx => $msg) {
        echo "  - {$msg['role']}: " . substr(json_encode($msg['content']), 0, 150) . "\n";
    }
    echo "----------------------------------------\n";
}
