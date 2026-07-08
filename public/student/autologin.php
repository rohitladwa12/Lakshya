<?php
require_once __DIR__ . '/../../config/bootstrap.php';

// Try to find a student in GMU database
$db = getDB('gmu');
$student = null;
if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE' LIMIT 1");
        $student = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Autologin GMU query failed: " . $e->getMessage());
    }
}

// Fallback to GMIT database
if (!$student) {
    $dbGmit = getDB('gmit');
    if ($dbGmit) {
        try {
            $stmt = $dbGmit->query("SELECT * FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE' LIMIT 1");
            $student = $stmt->fetch();
            if ($student) {
                $student['institution'] = 'GMIT';
            }
        } catch (Exception $e) {
            error_log("Autologin GMIT query failed: " . $e->getMessage());
        }
    }
}

if ($student) {
    $userId = $student['SL_NO'] ?? $student['ENQUIRY_NO'] ?? 1;
    $username = $student['USER_NAME'];
    $fullName = $student['NAME'] ?? $username;
    $inst = $student['institution'] ?? 'GMU';
    
    Session::setUser($userId, $username, ROLE_STUDENT, $fullName, $inst, 'CSE', null);
    echo "<h1>Autologin successful!</h1><p>Logging in as {$fullName} ({$username}). Redirecting...</p>";
    echo "<script>setTimeout(() => { window.location.href = 'internship_undertakings.php'; }, 1000);</script>";
} else {
    // If no students exist, create a mock local session
    Session::setUser(9999, '2GM20CS999', ROLE_STUDENT, 'Test Student', 'GMU', 'CSE', null);
    echo "<h1>Autologin (Mock Student) successful!</h1><p>Redirecting...</p>";
    echo "<script>setTimeout(() => { window.location.href = 'internship_undertakings.php'; }, 1000);</script>";
}
