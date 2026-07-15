<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$stmt = $db->prepare("SELECT id, role_name, concept, difficulty, conversation_history, status FROM mock_ai_interview_sessions WHERE id = ?");
$stmt->execute([686]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "ID: " . $row['id'] . "\n";
    echo "Role: " . $row['role_name'] . "\n";
    echo "Concept: " . $row['concept'] . "\n";
    echo "Difficulty: " . $row['difficulty'] . "\n";
    echo "Status: " . $row['status'] . "\n";
    echo "\n=== CONVERSATION HISTORY ===\n";
    $history = json_decode($row['conversation_history'], true);
    if (is_array($history)) {
        foreach ($history as $m) {
            echo "[" . strtoupper($m['role']) . "]: " . $m['content'] . "\n\n";
        }
    }
}
