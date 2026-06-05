<?php
require_once __DIR__ . '/../config/bootstrap.php';

$username = 'U23E01AI047'; // Change to a real student USN

function ms($start) { return round((microtime(true) - $start) * 1000); }

// Step 1: Local DB connect
$t = microtime(true);
$db = getDB();
echo "[1] Local DB connect: " . ms($t) . "ms\n";

// Step 2: app_officers query (check 1)
$t = microtime(true);
$stmt = $db->prepare("SELECT * FROM app_officers WHERE username = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$stmt->fetch();
echo "[2] app_officers query #1: " . ms($t) . "ms\n";

// Step 3: app_officers query (check 2 - duplicate in authenticate())
$t = microtime(true);
$stmt = $db->prepare("SELECT * FROM app_officers WHERE username = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$stmt->fetch();
echo "[3] app_officers query #2 (duplicate): " . ms($t) . "ms\n";

// Step 4: dept_coordinators query
$t = microtime(true);
$stmt = $db->prepare("SELECT * FROM dept_coordinators WHERE email = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$stmt->fetch();
echo "[4] dept_coordinators query: " . ms($t) . "ms\n";

// Step 5: Remote DB connect
$t = microtime(true);
$remote = getDB('gmu');
echo "[5] Remote GMU DB connect: " . ms($t) . "ms\n";

// Step 6: gmu.users lookup
$t = microtime(true);
$stmt = $remote->prepare("SELECT * FROM gmu.users WHERE (USER_NAME = ? OR AADHAR = ?) AND STATUS = 'ACTIVE' LIMIT 1");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();
echo "[6] gmu.users lookup: " . ms($t) . "ms\n";

if ($user) {
    // Step 7: password_verify (bcrypt cost check)
    $t = microtime(true);
    password_verify('test_password', $user['PASSWORD'] ?? '$2y$10$abcdefghijklmnopqrstuuVGmVhgLPCCBLDuE0.t8eqFKBjpBpbq6');
    echo "[7] password_verify (bcrypt): " . ms($t) . "ms\n";

    // Step 8: enrichment query (ad_student_details)
    $t = microtime(true);
    $stmt = $remote->prepare("SELECT d.usn as actual_usn, ad.sem FROM gmu.ad_student_details d LEFT JOIN gmu.ad_student_approved ad ON d.usn = ad.usn WHERE d.usn = ? OR d.aadhar = ? ORDER BY ad.academic_year DESC, ad.sem DESC LIMIT 1");
    $stmt->execute([$username, $username]);
    $stmt->fetch();
    echo "[8] ad_student_details enrichment: " . ms($t) . "ms\n";
} else {
    echo "[7-8] User not found in gmu.users - skipped\n";
}

// Step 9: trackActivity (local DB write)
$t = microtime(true);
$db->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())")
   ->execute([$username, 'timing_test', 'Timing test entry']);
echo "[9] trackActivity (INSERT): " . ms($t) . "ms\n";
