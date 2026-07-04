<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();
$stmt = $db->query("DESC student_profiles");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
