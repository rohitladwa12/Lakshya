<?php
require_once __DIR__ . '/../config/bootstrap.php';
use App\Helpers\RedisHelper;
use App\Services\QueueService;

$redisHelper = RedisHelper::getInstance();
$redis = $redisHelper->getClient();

echo "=== JOBS FOR U23E01CS037 ===\n";
// Let's scan all keys in redis starting with 'ai_job:job_U23E01CS037_'
$keys = $redis->keys('ai_job:job_U23E01CS037_*');
foreach ($keys as $key) {
    $jobId = str_replace('ai_job:', '', $key);
    $status = QueueService::getJobStatus($jobId);
    echo "Job ID: $jobId | Method: {$status['method']} | Status: {$status['status']}\n";
    if ($status['status'] === 'completed') {
        echo "Result:\n";
        print_r($status['result']);
    } else if ($status['status'] === 'failed') {
        echo "Error: {$status['error']}\n";
    }
    echo "---------------------------\n";
}
