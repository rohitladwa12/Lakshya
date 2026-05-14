<?php
namespace App\Services;

require_once __DIR__ . '/../../src/Helpers/RedisHelper.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
require_once __DIR__ . '/../../src/Models/User.php';

use App\Helpers\RedisHelper;
use Exception;

/**
 * RemoteDataProxy Service
 * Acts as a middle-man between the application and remote institutional databases.
 * Implements caching and fail-safe mechanisms to prevent remote lag from affecting the UI.
 */
class RemoteDataProxy {
    private $redis;
    private $cacheTTL = 3600; // Cache remote data for 1 hour

    public function __construct() {
        $this->redis = RedisHelper::getInstance();
    }

    /**
     * Get Student Academic History with Caching
     * @param int $userId
     * @param string $institution (GMU/GMIT)
     * @return array
     */
    public function getAcademicHistory($userId, $institution) {
        $cacheKey = "academic_history:{$institution}:{$userId}";

        // 1. Try Cache First
        if ($this->redis->isConnected()) {
            $cached = $this->redis->get($cacheKey);
            if ($cached) return $cached;
        }

        // 2. Cache Miss: Fetch from Remote (Slow)
        $profileModel = new \StudentProfile();
        try {
            $history = $profileModel->getAcademicHistory($userId, $institution);
            
            // 3. Save to Cache
            if ($this->redis->isConnected() && !empty($history)) {
                $this->redis->set($cacheKey, $history, $this->cacheTTL);
            }

            return $history;
        } catch (Exception $e) {
            error_log("RemoteDataProxy Error: " . $e->getMessage());
            return []; // Fail gracefully
        }
    }

    /**
     * Force refresh the cache for a specific user
     */
    public function refreshCache($userId, $institution) {
        $cacheKey = "academic_history:{$institution}:{$userId}";
        if ($this->redis->isConnected()) {
            $this->redis->delete($cacheKey);
        }
        return $this->getAcademicHistory($userId, $institution);
    }
}
