<?php
namespace App\Services;

use App\Helpers\RedisHelper;

class QueueService {
    private static $queueName = 'ai_job_queue';
    private static $statusPrefix = 'ai_job:';

    /**
     * Push a new job to the queue
     * 
     * @param string $serviceMethod The method in AIService to call (e.g., 'getTechnicalInterviewResponse')
     * @param array $args Arguments for the method
     * @param int $userId ID of the student
     * @return string Job ID
     */
    public static function pushJob($serviceMethod, $args, $userId) {
        $redisHelper = RedisHelper::getInstance();
        if (!$redisHelper->isConnected()) {
            throw new \Exception("Redis is not connected. Cannot push job to queue.");
        }
        $redis = $redisHelper->getClient();
        $jobId = uniqid('job_' . $userId . '_', true);

        $jobData = [
            'job_id' => $jobId,
            'user_id' => $userId,
            'method' => $serviceMethod,
            'args' => json_encode($args),
            'status' => 'pending',
            'created_at' => time()
        ];

        // Store status in a hash
        $redis->hmset(self::$statusPrefix . $jobId, $jobData);
        $redis->expire(self::$statusPrefix . $jobId, 3600); // 1 hour TTL

        // Push ID to queue
        $redis->lpush(self::$queueName, [$jobId]);

        return $jobId;
    }

    /**
     * Get job status and result
     * 
     * @param string $jobId
     * @return array|null
     */
    public static function getJobStatus($jobId) {
        $redisHelper = RedisHelper::getInstance();
        if (!$redisHelper->isConnected()) return null;
        
        $redis = $redisHelper->getClient();
        $data = $redis->hgetall(self::$statusPrefix . $jobId);
        
        if (empty($data)) return null;

        // Decode arguments if requested
        if (isset($data['args'])) {
            $data['args'] = json_decode($data['args'], true);
        }
        
        if (isset($data['result'])) {
            $data['result'] = json_decode($data['result'], true);
        }

        return $data;
    }
    
    /**
     * Update job status/result
     * (Used by Worker)
     */
    public static function updateJob($jobId, $updateData) {
        $redisHelper = RedisHelper::getInstance();
        if (!$redisHelper->isConnected()) return false;
        
        $redis = $redisHelper->getClient();
        $redis->hmset(self::$statusPrefix . $jobId, $updateData);
    }
    
    /**
     * Block while waiting for a job from the queue
     * (Used by Worker)
     */
    public static function popJob($timeout = 30) {
        $redisHelper = RedisHelper::getInstance();
        if (!$redisHelper->isConnected()) return null;
        
        $redis = $redisHelper->getClient();
        $result = $redis->brpop(self::$queueName, $timeout);
        return $result ? $result[1] : null;
    }
}
