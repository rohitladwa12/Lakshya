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
     * @param int|string $userId
     * @param string $institution (GMU/GMIT)
     * @return array
     */
    public function getAcademicHistory($userId, $institution) {
        $u = trim((string)$userId);
        $inst = strtoupper(trim((string)$institution));
        $cacheKey = "academic_history:{$inst}:{$u}";

        // 1. Try Cache First
        if ($this->redis->isConnected()) {
            $cached = $this->redis->get($cacheKey);
            if ($cached) return $cached;
        }

        // 2. Cache Miss: Fetch from Remote (Slow) or Local DB
        $profileModel = new \StudentProfile();
        try {
            $history = $profileModel->getAcademicHistory($userId, $institution);
            
            // 3. Save to Cache
            if ($this->redis->isConnected() && !empty($history)) {
                // For GMIT, academic records are in local DB; use short 60s TTL to prevent stale state on mobile
                $ttl = ($inst === 'GMIT') ? 60 : $this->cacheTTL;
                $this->redis->set($cacheKey, $history, $ttl);
            }

            return $history;
        } catch (Exception $e) {
            error_log("RemoteDataProxy Error: " . $e->getMessage());
            return []; // Fail gracefully
        }
    }

    /**
     * Clear all cached keys for a specific user and institution
     * Supports single ID or array of IDs (USN, Aadhar, application ID)
     */
    public function clearCache($userId, $institution = null) {
        if (!$this->redis->isConnected()) return;

        $identifiers = is_array($userId) ? $userId : [$userId];
        $institutions = $institution ? [$institution, strtoupper($institution), strtolower($institution)] : ['GMIT', 'GMU', 'gmit', 'gmu'];
        $institutions = array_unique($institutions);

        foreach ($identifiers as $id) {
            $u = trim((string)$id);
            if (empty($u)) continue;

            $idVariants = array_unique([$u, strtoupper($u), strtolower($u)]);
            foreach ($institutions as $inst) {
                foreach ($idVariants as $iv) {
                    $this->redis->delete("academic_history:{$inst}:{$iv}");
                }
            }
        }
    }

    /**
     * Force refresh the cache for a specific user
     */
    public function refreshCache($userId, $institution) {
        $this->clearCache($userId, $institution);
        return $this->getAcademicHistory($userId, $institution);
    }
}
