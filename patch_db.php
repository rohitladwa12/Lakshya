<?php
require_once __DIR__ . '/config/bootstrap.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE campus_drives ADD COLUMN aptitude_threshold INT DEFAULT 60");
    echo "aptitude_threshold added\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
try {
    $db->exec("ALTER TABLE campus_drives ADD COLUMN technical_threshold INT DEFAULT 60");
    echo "technical_threshold added\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
try {
    $db->exec("ALTER TABLE campus_drives ADD COLUMN hr_threshold INT DEFAULT 60");
    echo "hr_threshold added\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
