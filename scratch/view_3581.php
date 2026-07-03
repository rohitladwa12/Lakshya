<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$assessmentId = 3581;
echo "=== DETAILS FOR ASSESSMENT: $assessmentId ===\n\n";

$stmt = $db->prepare("SELECT score, feedback, details FROM unified_ai_assessments WHERE id = ?");
$stmt->execute([$assessmentId]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Score: {$row['score']}\n";
    echo "Feedback: {$row['feedback']}\n";
    $details = json_decode($row['details'], true);
    if (!empty($details['history'])) {
        foreach ($details['history'] as $idx => $m) {
            echo "[" . ucfirst($m['role']) . "]: " . $m['content'] . "\n";
        }
    }
}
