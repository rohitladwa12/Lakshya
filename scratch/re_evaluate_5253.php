<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/Services/AIService.php';

$db = getDB();

$stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = 5253");
$stmt->execute();
$details = json_decode($stmt->fetchColumn(), true);

if (!$details) {
    die("No details found for 5253\n");
}

$history = $details['history'] ?? [];
$role = $details['role'] ?? 'Software Engineer';
$concept = $details['concept'] ?? '';

echo "Role: $role\n";
echo "Concept: $concept\n";
echo "History count: " . count($history) . "\n";

$ai = new AIService();

echo "Generating HR Report via AI...\n";
$reportRes = $ai->generateHRReport($role, $history, $concept);

echo "Success: " . ($reportRes['success'] ? 'Yes' : 'No') . "\n";
if ($reportRes['success']) {
    echo "Score: {$reportRes['overall_score']}\n";
    echo "Content Length: " . strlen($reportRes['content']) . "\n";
    echo "Content preview:\n" . substr($reportRes['content'], 0, 1000) . "\n";
} else {
    echo "Error: " . ($reportRes['message'] ?? 'Unknown') . "\n";
}
