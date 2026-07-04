<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$id = 4207; // Srusti
$stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
$stmt->execute([$id]);
$details = json_decode($stmt->fetchColumn(), true);
$history = $details['history'] ?? [];
for ($i = 0; $i < min(15, count($history)); $i++) {
    $msg = $history[$i];
    echo "Msg $i [{$msg['role']}]: " . substr($msg['content'], 0, 500) . "\n";
}
