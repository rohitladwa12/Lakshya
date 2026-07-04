<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();
$stmt = $db->query("DESC student_coding_progress");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
