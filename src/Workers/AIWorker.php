<?php
/**
 * AI Worker
 * Run this from CLI: php src/Workers/AIWorker.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/QueueService.php';
require_once __DIR__ . '/../../src/Services/AIService.php';
require_once __DIR__ . '/../../src/Services/CareerAdvisorAI.php';

use App\Services\QueueService;

function workerLog($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}

workerLog("--- AI Worker Started ---");
workerLog("Waiting for jobs...");

$services = [
    new AIService(),
    new CareerAdvisorAI(),
];

// Identify this worker
$workerId = gethostname() . "_" . getmypid();
workerLog("Worker Identity: $workerId");

function updatePulse($id, $jobCount = 0) {
    global $redisHelper;
    if ($redisHelper && $redisHelper->isConnected()) {
        $redisHelper->getClient()->hset('ai_workers_pulse', $id, time());
        $redisHelper->getClient()->hset('ai_workers_memory', $id, round(memory_get_usage() / 1024 / 1024, 2) . 'MB');
        $redisHelper->getClient()->hset('ai_workers_jobs', $id, $jobCount);
    }
}
$jobCount = 0;
updatePulse($workerId, $jobCount);

$startTime = time();
$maxRuntime = 86400 * 30; // 30 days runtime
$maxMemory = 256 * 1024 * 1024; // 256MB limit
$maxJobs = 100000; // High job limit before restart

while (true) {
    // 0. Health & Memory Checks
    if (time() - $startTime > $maxRuntime) {
        workerLog("Max runtime reached. Restarting...");
        exit(0);
    }
    if (memory_get_usage() > $maxMemory) {
        workerLog("Memory limit (256MB) reached. Current: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB. Restarting...");
        exit(0);
    }
    if ($jobCount >= $maxJobs) {
        workerLog("Max jobs ($maxJobs) reached. Restarting...");
        exit(0);
    }

    // 1. Pop Job ID
    $jobId = QueueService::popJob(30); // Block for 30s
    
    if (!$jobId) {
        // No job, pulse and loop again
        updatePulse($workerId, $jobCount);
        continue;
    }

    workerLog("Processing Job: $jobId");

    // 2. Load Job Data
    $job = QueueService::getJobStatus($jobId);
    if (!$job) {
        workerLog("Error: Job $jobId not found in Redis.");
        continue;
    }

    // 3. Update status to processing
    $jobCount++;
    $currentUserId = $job['user_id'] ?? 0;
    $GLOBALS['AI_WORKER_USER_ID'] = $currentUserId;

    QueueService::updateJob($jobId, [
        'status' => 'processing',
        'started_at' => time(),
        'worker_id' => $workerId
    ]);

    try {
        $method = $job['method'];
        $args = $job['args'];

        // 4. Find service that has the method and dispatch
        $dispatched = false;
        foreach ($services as $svc) {
            if (method_exists($svc, $method)) {
                $result = call_user_func_array([$svc, $method], $args);
                $finalResult = isset($result['result']) ? $result['result'] : $result;

                QueueService::updateJob($jobId, [
                    'status'       => 'completed',
                    'result'       => json_encode($finalResult),
                    'completed_at' => time()
                ]);
                workerLog("Success: Job $jobId finished (" . get_class($svc) . "::$method).");
                $dispatched = true;
                break;
            }
        }
        if (!$dispatched) {
            throw new Exception("Method $method not found in any registered service");
        }
    } catch (Exception $e) {
        workerLog("Error: Job $jobId failed - " . $e->getMessage());
        QueueService::updateJob($jobId, [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'completed_at' => time()
        ]);
    }
    updatePulse($workerId, $jobCount);
}
