<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$stmt = $db->query("SELECT id, role_name, concept, difficulty, status, started_at, conversation_history FROM mock_ai_interview_sessions ORDER BY id DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (stripos($row['conversation_history'], '25% discount') !== false || stripos($row['conversation_history'], 'sale price of the shirt') !== false) {
        echo "Session ID: " . $row['id'] . " | Date: " . $row['started_at'] . " | Status: " . $row['status'] . "\n";
        // Print the lines in history matching the search
        $history = json_decode($row['conversation_history'], true);
        if (is_array($history)) {
            foreach ($history as $m) {
                if (stripos($m['content'], '25% discount') !== false) {
                    echo "  Content Snippet:\n  " . substr($m['content'], 0, 300) . "...\n";
                }
            }
        }
    }
}
