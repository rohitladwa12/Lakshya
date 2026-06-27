<?php
require 'config/bootstrap.php';
$db = getDB();
$gmu = getDB('gmu');
$gmit = getDB('gmit');

$apps = $db->query('SELECT id, student_id FROM job_applications')->fetchAll(PDO::FETCH_ASSOC);
$deleted = 0;

foreach($apps as $app) {
    $id = $app['id'];
    $usn = $app['student_id'];
    
    $stmt1 = $gmu->prepare('SELECT COUNT(*) FROM ' . DB_GMU_PREFIX . 'users WHERE USER_NAME = ?');
    $stmt1->execute([$usn]);
    
    $stmt2 = $gmit->prepare('SELECT COUNT(*) FROM ' . DB_GMIT_PREFIX . 'users WHERE USER_NAME = ?');
    $stmt2->execute([$usn]);
    
    if($stmt1->fetchColumn() == 0 && $stmt2->fetchColumn() == 0) {
        $db->exec('DELETE FROM job_applications WHERE id = ' . $id);
        $deleted++;
        echo 'Deleted orphaned application for USN: ' . $usn . PHP_EOL;
    }
}
echo "Total deleted: " . $deleted . PHP_EOL;
