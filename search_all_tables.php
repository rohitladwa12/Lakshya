<?php
require_once __DIR__ . '/config/bootstrap.php';

try {
    $db = getDB();
    
    echo "=== SEARCH IN LOCAL DATABASE ===\n";
    
    // 1. Search users by name or part of username
    echo "\n--- Users table partial match: ---\n";
    $stmt = $db->prepare("SELECT * FROM users WHERE USER_NAME LIKE ? OR ID LIKE ? OR NAME LIKE ?");
    $stmt->execute(['%GMIT23AI12%', '%GMIT23AI12%', '%AYUSH%']);
    $users = $stmt->fetchAll();
    print_r($users);
    
    // 2. Search student_portfolio for any records with student_id = GMIT23AI12
    echo "\n--- student_portfolio records: ---\n";
    $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE student_id LIKE ?");
    $stmt->execute(['%GMIT23AI12%']);
    print_r($stmt->fetchAll());
    
    // 3. Search student_resumes for student_id or full_name containing Ayush or GMIT23AI12
    echo "\n--- student_resumes records: ---\n";
    $stmt = $db->prepare("SELECT id, student_id, full_name, last_updated FROM student_resumes WHERE student_id LIKE ? OR full_name LIKE ?");
    $stmt->execute(['%GMIT23AI12%', '%AYUSH%']);
    print_r($stmt->fetchAll());
    
    // 4. Search student_skills for student_id matching users matching Ayush
    echo "\n--- student_skills records: ---\n";
    $stmt = $db->prepare("SELECT ss.*, s.name as skill_name FROM student_skills ss JOIN skills s ON ss.skill_id = s.id JOIN users u ON ss.student_id = u.SL_NO WHERE u.NAME LIKE ? OR u.USER_NAME LIKE ?");
    $stmt->execute(['%AYUSH%', '%GMIT23AI12%']);
    print_r($stmt->fetchAll());

    // 5. Search student_ai_profiles
    echo "\n--- student_ai_profiles records: ---\n";
    $stmt = $db->prepare("SELECT * FROM student_ai_profiles WHERE student_id LIKE ?");
    $stmt->execute(['%GMIT23AI12%']);
    print_r($stmt->fetchAll());

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
