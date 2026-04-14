<?php
/**
 * AIAnalysisCache Model
 */

require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/../Helpers/RedisHelper.php';
use App\Helpers\RedisHelper;

class AIAnalysisCache extends Model {
    protected $table = 'ai_analysis_cache';

    public function getCachedAnalysis($userId, $mode, $company, $role, $currentHash) {
        $redisKey = "ai_cache:{$userId}:{$mode}:" . hash('md5', $company . $role . $currentHash);
        $redis = RedisHelper::getInstance();
        
        if ($redis->isConnected()) {
            $cached = $redis->get($redisKey);
            if ($cached) return $cached;
        }

        $sql = "SELECT analysis_content FROM {$this->table} 
                WHERE user_id = ? AND mode = ? AND data_hash = ?";
        
        $params = [$userId, $mode, $currentHash];
        // ... (rest of search logic)

        if ($mode === 'target') {
            $sql .= " AND company = ? AND role = ?";
            $params[] = $company;
            $params[] = $role;
        } elseif ($mode === 'market') {
            if ($company) {
                $sql .= " AND company = ?";
                $params[] = $company;
            } else {
                $sql .= " AND company IS NULL";
            }
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function cacheAnalysis($userId, $mode, $company, $role, $currentHash, $content) {
        // Cache in Redis first
        $redisKey = "ai_cache:{$userId}:{$mode}:" . hash('md5', $company . $role . $currentHash);
        $redis = RedisHelper::getInstance();
        if ($redis->isConnected()) {
            $redis->set($redisKey, $content, 86400 * 7); // Cache for 7 days
        }

        // Optional: Clean up old caches for this user/mode/target
        $this->cleanup($userId, $mode, $company, $role);

        $sql = "INSERT INTO {$this->table} (user_id, mode, company, role, data_hash, analysis_content) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $mode, $company, $role, $currentHash, $content]);
    }

    private function cleanup($userId, $mode, $company, $role) {
        $sql = "DELETE FROM {$this->table} WHERE user_id = ? AND mode = ?";
        $params = [$userId, $mode];

        if ($mode === 'target') {
            $sql .= " AND company = ? AND role = ?";
            $params[] = $company;
            $params[] = $role;
        } elseif ($mode === 'market') {
            if ($company) {
                $sql .= " AND company = ?";
                $params[] = $company;
            } else {
                $sql .= " AND company IS NULL";
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}
