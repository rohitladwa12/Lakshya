<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = getDB();
$gmu = getDB('gmu');
$gmit = getDB('gmit');

echo "=== PROFILING activity_logs.php ===\n";

$t = microtime(true);
$db->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'admin'")->fetchColumn();
echo "users count (admin): " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'coordinator'")->fetchColumn();
echo "users count (coordinator): " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
try {
    $db->query("SELECT COUNT(DISTINCT student_id) FROM student_sem_sgpa WHERE semester IN (5, 6, 7, 8) AND is_current = 1")->fetchColumn();
} catch (Throwable $e) { echo "gmitSemsCount failed: " . $e->getMessage() . "\n"; }
echo "student_sem_sgpa seniors count: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
try {
    if ($gmu) {
        $gmu->query("SELECT COUNT(DISTINCT usn) FROM ad_student_approved WHERE sem IN (5, 6, 7, 8)")->fetchColumn();
    } else {
        echo "gmu connection skipped\n";
    }
} catch (Throwable $e) { echo "gmuSemsCount failed: " . $e->getMessage() . "\n"; }
echo "gmu ad_student_approved seniors count: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND DATE(created_at) = CURDATE()")->fetchColumn();
echo "loginsToday count: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
echo "loginsThisWeek count: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
echo "loginsThisMonth count: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetchColumn();
echo "activeUsersCount: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed'")->fetchColumn();
echo "failedLoginAttempts count: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE action = 'login'")->fetchColumn();
echo "uniqueUsersLogged count: " . round((microtime(true) - $t)*1000) . "ms\n";


echo "\n=== PROFILING ai_monitor.php ===\n";

$t = microtime(true);
$db->query("SELECT 
    COUNT(*) as total_requests,
    SUM(prompt_tokens) as total_prompt_tokens,
    SUM(completion_tokens) as total_completion_tokens,
    SUM(total_tokens) as total_tokens,
    AVG(latency_ms) as avg_latency,
    SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failures
FROM ai_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
echo "ai_audit_logs 24h stats: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT 
    SUM(prompt_tokens) as total_prompt_tokens,
    SUM(completion_tokens) as total_completion_tokens
FROM ai_audit_logs")->fetch();
echo "ai_audit_logs total cost stats: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT 
    service_method, 
    COUNT(*) as count, 
    SUM(total_tokens) as tokens,
    AVG(latency_ms) as latency
FROM ai_audit_logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY service_method 
ORDER BY tokens DESC")->fetchAll();
echo "ai_audit_logs service stats (7D): " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT COUNT(*) FROM ai_audit_logs")->fetchColumn();
echo "ai_audit_logs total row count: " . round((microtime(true) - $t)*1000) . "ms\n";

$t = microtime(true);
$db->query("SELECT * FROM ai_audit_logs ORDER BY created_at DESC LIMIT 20 OFFSET 0")->fetchAll();
echo "ai_audit_logs recent logs: " . round((microtime(true) - $t)*1000) . "ms\n";
