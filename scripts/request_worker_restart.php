<?php
/**
 * Asks running AI workers to exit gracefully after their current job.
 * Workers started after this call ignore the request (see AIWorker.php).
 * Usage: php scripts/request_worker_restart.php
 */
require_once __DIR__ . '/../config/bootstrap.php';

$redisHelper = \App\Helpers\RedisHelper::getInstance();
if (!$redisHelper->isConnected()) {
    echo "Redis not connected - cannot request graceful restart.\n";
    exit(1);
}
$redisHelper->getClient()->set('ai_workers_restart_at', time());
echo "Graceful worker restart requested at " . date('Y-m-d H:i:s') . "\n";
