<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
$stmt->execute([3342]);
$detailsJson = $stmt->fetchColumn();
$details = json_decode($detailsJson, true);

if ($details) {
    if (isset($details['questions'])) {
        foreach ($details['questions'] as $idx => $q) {
            $userAns = $details['user_answers'][$idx] ?? 'None';
            echo "Q" . ($idx + 1) . ": " . $q['question'] . "\n";
            echo "Options: " . json_encode($q['options']) . "\n";
            echo "Correct: " . $q['answer'] . " | User Answer: " . $userAns . "\n";
            echo "Explanation: " . ($q['explanation'] ?? 'None') . "\n";
            echo "----------------------------------------\n";
        }
    } else {
        print_r($details);
    }
} else {
    echo "No details found for ID 3342\n";
}
