<?php
require_once __DIR__ . '/config/bootstrap.php';
use App\Services\QueueService;
use App\Helpers\RedisHelper;

$output = "--- Career Advisor Diagnostic ---\n";

// 1. Check Redis Jobs
$redis = RedisHelper::getInstance()->getClient();
$keys = $redis->keys('ai_job:*');
$output .= "Found " . count($keys) . " job keys in Redis.\n";

foreach ($keys as $key) {
    if (strpos($key, 'ai_job:') === false) continue;
    $jobId = str_replace('ai_job:', '', $key);
    $status = QueueService::getJobStatus($jobId);
    $output .= "Job ID: $jobId\n";
    $output .= "  User: " . ($status['user_id'] ?? 'N/A') . "\n";
    $output .= "  Method: " . ($status['method'] ?? 'N/A') . "\n";
    $output .= "  Status: " . ($status['status'] ?? 'N/A') . "\n";
    if (isset($status['args'])) {
        $output .= "  Args: " . json_encode($status['args']) . "\n";
    }
    if (isset($status['result'])) {
        $result = $status['result'];
        if (is_array($result) && isset($result['roadmap']['target_role'])) {
            $output .= "  Result Role: " . $result['roadmap']['target_role'] . "\n";
        } else {
            $output .= "  Result: " . substr(json_encode($result), 0, 100) . "...\n";
        }
    }
    $output .= "-------------------\n";
}

// 2. Check Database
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id, student_id, target_role, created_at FROM career_roadmaps ORDER BY created_at DESC LIMIT 10");
$roadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$output .= "\n--- Recent Roadmaps in Database ---\n";
foreach ($roadmaps as $r) {
    $output .= "ID: {$r['id']} | User: {$r['student_id']} | Role: {$r['target_role']} | Created: {$r['created_at']}\n";
}

file_put_contents('diag_output.txt', $output);
echo "Diagnostic complete. Output written to diag_output.txt\n";
