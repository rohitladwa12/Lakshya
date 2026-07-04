<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$id = 4207; // Srusti
$stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
$stmt->execute([$id]);
$details = json_decode($stmt->fetchColumn(), true);
$history = $details['history'] ?? [];
foreach ($history as $idx => $msg) {
    echo "Msg $idx [{$msg['role']}]: {$msg['content']}\n";
}
