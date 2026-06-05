<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();

$stmt = $db->query("SELECT id, email, full_name, department, institution FROM dept_coordinators ORDER BY id ASC");
$coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($coordinators as $c) {
    echo "{$c['id']}. {$c['full_name']} ({$c['email']}) - Dept: {$c['department']} [{$c['institution']}]\n";
}
