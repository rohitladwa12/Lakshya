<?php
require_once __DIR__ . '/config/bootstrap.php';
$db = getDB();
$rows = $db->query("SELECT id, student_id, full_name FROM student_resumes")->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo $r['id'] . "|" . $r['student_id'] . "|" . $r['full_name'] . "\n";
}
