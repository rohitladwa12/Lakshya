<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();
$stmt = $db->query("SHOW CREATE TABLE student_projects");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
