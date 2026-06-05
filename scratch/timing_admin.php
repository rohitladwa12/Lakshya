<?php
require_once __DIR__ . '/../config/bootstrap.php';

function ms($start) { return round((microtime(true) - $start) * 1000); }

// Check Redis
$t = microtime(true);
$redis = \App\Helpers\RedisHelper::getInstance();
echo "Redis connect: " . ms($t) . "ms\n";

$t = microtime(true);
$cached = $redis->get('admin:dashboard_stats');
echo "admin:dashboard_stats cache: " . ($cached ? "HIT (" . ms($t) . "ms)" : "MISS (" . ms($t) . "ms)") . "\n";

$t = microtime(true);
$cached2 = $redis->get('admin:resume_completion_stats');
echo "admin:resume_completion_stats cache: " . ($cached2 ? "HIT (" . ms($t) . "ms)" : "MISS (" . ms($t) . "ms)") . "\n";

// Time the checkConnection calls
$t = microtime(true);
$gmuStatus = Database::checkConnection('gmu');
echo "checkConnection('gmu'): " . ms($t) . "ms - " . ($gmuStatus['ok'] ? 'OK' : 'FAIL') . "\n";

$t = microtime(true);
$gmitStatus = Database::checkConnection('gmit');
echo "checkConnection('gmit'): " . ms($t) . "ms - " . ($gmitStatus['ok'] ? 'OK' : 'FAIL') . "\n";

// Time getDashboardStats (will use cache if available)
$t = microtime(true);
$admin = new Admin();
$stats = $admin->getDashboardStats();
echo "getDashboardStats: " . ms($t) . "ms\n";

// Time getResumeCompletionStats
$t = microtime(true);
$resumeStats = $admin->getResumeCompletionStats();
echo "getResumeCompletionStats: " . ms($t) . "ms\n";
