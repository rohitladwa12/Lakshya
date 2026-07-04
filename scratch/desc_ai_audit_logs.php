<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$stmt = $db->query("DESCRIBE ai_audit_logs");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
