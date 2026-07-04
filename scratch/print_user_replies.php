<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$ids = [4207, 4216, 4196];

foreach ($ids as $id) {
    echo "=== History for ID $id ===\n";
    $stmt = $db->prepare("SELECT student_name, details FROM unified_ai_assessments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $details = json_decode($row['details'], true);
        $history = $details['history'] ?? [];
        foreach ($history as $idx => $msg) {
            if ($msg['role'] === 'user') {
                echo "  User Msg " . ($idx + 1) . ": " . $msg['content'] . "\n";
            }
        }
    }
    echo "\n";
}
