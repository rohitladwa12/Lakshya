<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireLogin();
if (Session::getRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../../src/Helpers/RedisHelper.php';
use App\Helpers\RedisHelper;

$redisHelper = RedisHelper::getInstance();
$cacheKey    = 'ai_monitor:rpm';
$cached      = $redisHelper->get($cacheKey);

if ($cached !== null && $cached !== false) {
    echo json_encode(['rpm' => (float)$cached]);
    exit;
}

$db  = getDB();
$row = $db->query(
    "SELECT COUNT(*) AS cnt
       FROM ai_audit_logs
      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
)->fetch(PDO::FETCH_ASSOC);

$rpm = (float)($row['cnt'] ?? 0);

// Cache for 5 seconds so rapid page-refreshes don't hammer the DB
$redisHelper->set($cacheKey, $rpm, 5);

echo json_encode(['rpm' => $rpm]);
