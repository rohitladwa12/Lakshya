<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = getDB();

function inspectRow($id) {
    global $db;
    $stmt = $db->prepare("SELECT id, student_id, usn, student_name, details FROM unified_ai_assessments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "=== unified_ai_assessments ID: $id ===\n";
        echo "id: {$row['id']}\n";
        echo "student_id: {$row['student_id']}\n";
        echo "usn: {$row['usn']}\n";
        echo "student_name: {$row['student_name']}\n";
        $details = json_decode($row['details'], true);
        if ($details) {
            echo "details keys: " . implode(', ', array_keys($details)) . "\n";
            echo "history count: " . count($details['history'] ?? []) . "\n";
        }
    } else {
        echo "Row $id not found in unified_ai_assessments\n";
    }
}

inspectRow(5111);
inspectRow(5608);
inspectRow(5606);
