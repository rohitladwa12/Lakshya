<?php
require_once __DIR__ . '/config/bootstrap.php';
$db = getDB();

$stmt = $db->prepare("SELECT id, score, details FROM unified_ai_assessments WHERE id = ?");
$stmt->execute([6023]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "ID: {$row['id']} | Score: {$row['score']}\n";
    $details = json_decode($row['details'], true);
    echo "Report Content:\n";
    echo $details['report_content'] ?? "No report content\n";
} else {
    echo "Session not found.\n";
}
