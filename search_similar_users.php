<?php
require_once __DIR__ . '/config/bootstrap.php';

try {
    $db = getDB();
    
    echo "=== SEARCH USERS TABLE FOR SIMILAR ===\n";
    $stmt = $db->query("SELECT SL_NO, USER_NAME, ID, AADHAR, NAME, USER_GROUP FROM users WHERE USER_NAME LIKE '%23AI12%' OR ID LIKE '%23AI12%' OR NAME LIKE '%Ayush%'");
    $users = $stmt->fetchAll();
    print_r($users);
    
    echo "\n=== SEARCH PORTFOLIO FOR SIMILAR ===\n";
    $stmt = $db->query("SELECT DISTINCT student_id, student_name, institution FROM student_portfolio WHERE student_id LIKE '%23AI12%' OR student_name LIKE '%Ayush%'");
    print_r($stmt->fetchAll());
    
    echo "\n=== SEARCH STUDENT_AI_PROFILES ===\n";
    $stmt = $db->query("SELECT student_id, student_name FROM student_ai_profiles WHERE student_id LIKE '%23AI12%'");
    print_r($stmt->fetchAll());

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
