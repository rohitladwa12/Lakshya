<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = getDB();

$stmt = $db->prepare("DESCRIBE unified_ai_assessments");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "{$col['Field']} - {$col['Type']}\n";
}
