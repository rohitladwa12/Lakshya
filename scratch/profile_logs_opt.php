<?php
$startTotal = microtime(true);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/Models/Logger.php';

$db = getDB();

function profile($name, $callback) {
    $start = microtime(true);
    $callback();
    $end = microtime(true);
    echo "$name: " . round(($end - $start) * 1000, 2) . " ms\n";
}

profile("Optimized Audit Trail query", function() use ($db) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
    $auditParams = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];
    
    // Split the OR join into two clean LEFT JOINs!
    $auditSql = "SELECT l.*, 
                        COALESCE(u1.NAME, u2.NAME) as user_name, 
                        COALESCE(u1.USER_NAME, u2.USER_NAME) as usn 
                 FROM activity_logs l
                 LEFT JOIN users u1 ON l.user_id = u1.SL_NO
                 LEFT JOIN users u2 ON l.user_id = u2.USER_NAME
                 WHERE l.created_at >= :start AND l.created_at <= :end
                 ORDER BY l.created_at DESC LIMIT 100";
                 
    $auditStmt = $db->prepare($auditSql);
    $auditStmt->execute($auditParams);
    $auditStmt->fetchAll();
});

$endTotal = microtime(true);
echo "Total Time: " . round(($endTotal - $startTotal) * 1000, 2) . " ms\n";
