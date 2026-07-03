<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$assessmentId = 3590;
echo "=== DETAILS FOR ASSESSMENT: $assessmentId ===\n\n";

$stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
$stmt->execute([$assessmentId]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $details = json_decode($row['details'], true);
    if (!empty($details['history'])) {
        echo "History turns: " . count($details['history']) . "\n";
        foreach ($details['history'] as $idx => $m) {
            $role = ucfirst($m['role']);
            $content = $m['content'];
            echo "[$idx] $role: $content\n";
            echo "---------------------------------\n";
        }
    } else {
        echo "No history found in details.\n";
        print_r($details);
    }
} else {
    echo "Assessment $assessmentId not found.\n";
}
