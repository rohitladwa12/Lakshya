<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = getDB();

$stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = 5253");
$stmt->execute();
$details = json_decode($stmt->fetchColumn(), true);

if ($details && isset($details['report_content'])) {
    echo $details['report_content'];
} else {
    echo "No report content found.";
}
