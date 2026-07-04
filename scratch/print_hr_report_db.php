<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$ids = [4207, 4216];

foreach ($ids as $id) {
    echo "=== Record $id ===\n";
    $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Score: {$row['score']}\n";
        echo "Feedback: {$row['feedback']}\n";
        $details = json_decode($row['details'], true);
        echo "Report Path: " . ($details['report_path'] ?? 'none') . "\n";
        echo "Role: " . ($details['role'] ?? 'none') . "\n";
        echo "Task ID: " . ($details['task_id'] ?? 'none') . "\n";
        
        // Print first few lines of history and the last assistant message
        if (isset($details['history']) && is_array($details['history'])) {
            $history = $details['history'];
            echo "History Count: " . count($history) . "\n";
            $lastAssistant = null;
            foreach (array_reverse($history) as $msg) {
                if ($msg['role'] === 'assistant') {
                    $lastAssistant = $msg['content'];
                    break;
                }
            }
            echo "Last Assistant Msg: " . substr($lastAssistant, 0, 300) . "...\n";
        }
    }
    echo "\n";
}
