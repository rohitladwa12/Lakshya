<?php
$startTotal = microtime(true);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/Models/Logger.php';

$logger = new Logger();
$db = getDB();
$gmit = getDB('gmit');
$gmu = getDB('gmu');

function profile($name, $callback) {
    $start = microtime(true);
    $callback();
    $end = microtime(true);
    echo "$name: " . round(($end - $start) * 1000, 2) . " ms\n";
}

profile("Total Admin Count", function() use ($db) {
    $db->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'admin'")->fetchColumn();
});

profile("Total Faculty Count", function() use ($db) {
    $db->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'coordinator'")->fetchColumn();
});

profile("GMIT Student Details Count", function() use ($gmit) {
    if ($gmit) {
        $gmit->query("SELECT COUNT(*) FROM ad_student_details")->fetchColumn();
    }
});

profile("GMU Student Approved Count", function() use ($gmu) {
    if ($gmu) {
        $gmu->query("SELECT COUNT(*) FROM ad_student_approved WHERE sem IN (5, 6, 7, 8)")->fetchColumn();
    }
});

profile("Logins Today Count", function() use ($db) {
    $db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND DATE(created_at) = CURDATE()")->fetchColumn();
});

profile("Logins This Week Count", function() use ($db) {
    $db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
});

profile("Logins This Month Count", function() use ($db) {
    $db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
});

profile("Active Users Count", function() use ($db) {
    $db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetchColumn();
});

profile("Failed Logins Count", function() use ($db) {
    $db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed'")->fetchColumn();
});

profile("Unique Logins Count", function() use ($db) {
    $db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE action = 'login'")->fetchColumn();
});

profile("GMIT Sem USNs Fetch", function() use ($db, &$gmitSemUsns) {
    $gmitSemUsns = $db->query("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE semester IN (5, 6, 7, 8) AND is_current = 1")->fetchAll(PDO::FETCH_COLUMN);
});

profile("Login Counts Mapping", function() use ($db) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
    $stmt = $db->prepare("SELECT user_id, COUNT(*) as count FROM activity_logs WHERE action = 'login' AND DATE(created_at) >= :start AND DATE(created_at) <= :end GROUP BY user_id");
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $stmt->fetchAll();
});

profile("GMIT Loop Query (524 USNs)", function() use ($gmit, $gmitSemUsns) {
    if ($gmit && !empty($gmitSemUsns)) {
        $chunks = array_chunk($gmitSemUsns, 500);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmtGmit = $gmit->prepare("SELECT DISTINCT usn, name, discipline FROM ad_student_details WHERE usn IN ($placeholders)");
            $stmtGmit->execute($chunk);
            $stmtGmit->fetchAll();
        }
    }
});

profile("GMU Query (All Sem 5-8)", function() use ($gmu) {
    if ($gmu) {
        $stmtGmu = $gmu->query("SELECT DISTINCT usn, name, discipline FROM ad_student_approved WHERE sem IN (5, 6, 7, 8)");
        $stmtGmu->fetchAll();
    }
});

profile("Audit Trail query (100 rows)", function() use ($db) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
    $auditParams = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];
    $auditSql = "SELECT l.*, u.NAME as user_name, u.USER_NAME as usn 
                 FROM activity_logs l
                 LEFT JOIN users u ON (l.user_id = u.SL_NO OR l.user_id = u.USER_NAME)
                 WHERE l.created_at >= :start AND l.created_at <= :end
                 ORDER BY l.created_at DESC LIMIT 100";
    $auditStmt = $db->prepare($auditSql);
    $auditStmt->execute($auditParams);
    $auditStmt->fetchAll();
});

$endTotal = microtime(true);
echo "Total Time: " . round(($endTotal - $startTotal) * 1000, 2) . " ms\n";
