<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

echo "=== UNIQUE DISCIPLINES IN AD_STUDENT_DETAILS ===\n";
try {
    $stmt = $db->query("SELECT DISTINCT discipline FROM ad_student_details ORDER BY discipline ASC");
    $disciplines = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($disciplines as $idx => $d) {
        echo ($idx + 1) . ". " . ($d ?: '[NULL/EMPTY]') . "\n";
    }
} catch (Exception $e) {
    echo "Error querying ad_student_details: " . $e->getMessage() . "\n";
}
