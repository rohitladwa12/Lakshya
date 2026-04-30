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

echo "--- AI Worker Started ---\n";
echo "Waiting for jobs...\n";

// Service registry — worker will try each in order until method is found
$services = [
    new AIService(),
    new CareerAdvisorAI(),
];

$startTime = time();
$maxRuntime = 1800; // Auto-restart every 30 minutes to clear memory/cache

while (true) {
    // Check if we need to restart for memory clearance
    if (time() - $startTime > $maxRuntime) {
        echo "[".date('Y-m-d H:i:s')."] Worker reaching max runtime of 30 minutes. Exiting gracefully to clear memory...\n";
        exit(0);
    }

    // 1. Pop Job ID
    $jobId = QueueService::popJob(30); // Block for 30s
    
    if (!$jobId) {
        // No job, loop again (or check for termination signal)
        continue;
    }

    echo "[".date('Y-m-d H:i:s')."] Processing Job: $jobId\n";

    // 2. Load Job Data
    $job = QueueService::getJobStatus($jobId);
    if (!$job) {
        echo "Error: Job $jobId not found in Redis.\n";
        continue;
    }

    // 3. Update status to processing
    QueueService::updateJob($jobId, [
        'status' => 'processing',
        'started_at' => time()
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
                echo "Success: Job $jobId finished (" . get_class($svc) . "::$method).\n";
                $dispatched = true;
                break;
            }
        }
        if (!$dispatched) {
            throw new Exception("Method $method not found in any registered service");
        }
    } catch (Exception $e) {
        echo "Error: Job $jobId failed - " . $e->getMessage() . "\n";
        QueueService::updateJob($jobId, [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'completed_at' => time()
        ]);
    }
}
