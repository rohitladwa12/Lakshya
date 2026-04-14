<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE mock_ai_interview_sessions ADD COLUMN overall_score INT DEFAULT 0 AFTER status");
    echo "Migration successful\n";
} catch(Exception $e) {
    echo "Migration info: " . $e->getMessage() . "\n";
}