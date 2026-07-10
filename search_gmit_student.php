<?php
require_once __DIR__ . '/config/bootstrap.php';

try {
    $db = getDB();
    
    echo "=== USER RECORD ===\n";
    $stmt = $db->prepare("SELECT * FROM users WHERE USER_NAME = ? OR ID = ? OR AADHAR = ?");
    $stmt->execute(['GMIT23AI12', 'GMIT23AI12', 'GMIT23AI12']);
    $user = $stmt->fetch();
    print_r($user);
    
    if ($user) {
        $slNo = $user['SL_NO'];
        
        echo "\n=== STUDENT_SKILLS ===\n";
        $stmt = $db->prepare("SELECT ss.*, s.name as skill_name FROM student_skills ss JOIN skills s ON ss.skill_id = s.id WHERE ss.student_id = ?");
        $stmt->execute([$slNo]);
        print_r($stmt->fetchAll());
        
        echo "\n=== STUDENT_PORTFOLIO ===\n";
        $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE student_id = ? OR student_id = ?");
        $stmt->execute([$user['USER_NAME'], $user['ID']]);
        print_r($stmt->fetchAll());
        
        echo "\n=== STUDENT_RESUMES ===\n";
        $stmt = $db->prepare("SELECT id, student_id, full_name, last_updated FROM student_resumes WHERE student_id = ?");
        $stmt->execute([$slNo]);
        print_r($stmt->fetchAll());

        echo "\n=== ACTIVITY_LOGS (Last 20) ===\n";
        $stmt = $db->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 20");
        $stmt->execute([$slNo]);
        print_r($stmt->fetchAll());
    } else {
        echo "No user found in default database.\n";
    }

    // Also check GMIT external database
    echo "\n=== GMIT DATABASE SEARCH ===\n";
    $gmitDb = getDB('gmit');
    if ($gmitDb) {
        $stmt = $gmitDb->prepare("SELECT * FROM ad_student_details WHERE usn = ? OR student_id = ?");
        $stmt->execute(['GMIT23AI12', 'GMIT23AI12']);
        print_r($stmt->fetchAll());
    } else {
        echo "GMIT Database connection not available.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
