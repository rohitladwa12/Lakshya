<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();
$stmt = $db->query("DESCRIBE unified_ai_assessments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
