<?php
require_once __DIR__ . '/config/bootstrap.php';

try {
    $db = getDB();
    
    echo "=== SYSTEM USER SEARCH FOR GMIT23AI12 ===\n";
    $stmt = $db->prepare("SELECT * FROM users WHERE USER_NAME = ?");
    $stmt->execute(['gmit23ai12']);
    $user = $stmt->fetch();
    if ($user) {
        echo "User found in users table:\n";
        print_r($user);
        
        $slNo = $user['SL_NO'];
        
        // Check student_resumes
        echo "\n--- student_resumes table: ---\n";
        $stmt = $db->prepare("SELECT id, student_id, full_name, last_updated, resume_data FROM student_resumes WHERE student_id = ?");
        $stmt->execute([$slNo]);
        $resumes = $stmt->fetchAll();
        foreach ($resumes as $r) {
            echo "Resume ID: {$r['id']}, Student ID: {$r['student_id']}, Name: {$r['full_name']}, Updated: {$r['last_updated']}\n";
            // Decode resume data
            $resData = json_decode($r['resume_data'], true);
            echo "Skills in resume JSON:\n";
            print_r($resData['skills'] ?? 'No skills key');
        }
        
        // Check student_skills
        echo "\n--- student_skills table: ---\n";
        $stmt = $db->prepare("SELECT ss.*, s.name as skill_name FROM student_skills ss JOIN skills s ON ss.skill_id = s.id WHERE ss.student_id = ?");
        $stmt->execute([$slNo]);
        print_r($stmt->fetchAll());
        
        // Check student_portfolio
        echo "\n--- student_portfolio table: ---\n";
        $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE student_id = ?");
        $stmt->execute(['GMIT23AI12']);
        print_r($stmt->fetchAll());

        // Check student_projects
        echo "\n--- student_projects table: ---\n";
        $stmt = $db->prepare("SELECT * FROM student_projects WHERE student_id = ?");
        $stmt->execute([$slNo]);
        print_r($stmt->fetchAll());

        // Check student_achievements
        echo "\n--- student_achievements table: ---\n";
        $stmt = $db->prepare("SELECT * FROM student_achievements WHERE student_id = ?");
        $stmt->execute([$slNo]);
        print_r($stmt->fetchAll());
        
    } else {
        echo "User 'gmit23ai12' NOT found in users table.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
