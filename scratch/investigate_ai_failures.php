<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$stmt = $db->query("SELECT * FROM ai_audit_logs WHERE status = 'failure' ORDER BY created_at DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($rows);
