<?php

require_once __DIR__ . '/Model.php';

class Logger extends Model {
    protected $table = 'activity_logs';
    protected $timestamps = false; // We use database default current_timestamp()

    /**
     * Log a user activity
     * 
     * @param string $action The action performed (e.g., 'login', 'mock_ai_start')
     * @param string|null $description Human readable description
     * @param array|null $metaData Additional details in key-value pairs
     * @param string|null $entityType Type of entity involved (e.g., 'job', 'session')
     * @param int|null $entityId ID of the entity involved
     * @return int|bool ID of the log entry or false on failure
     */
    public function log($action, $description = null, $metaData = null, $entityType = null, $entityId = null) {
        try {
            $userId = Session::getUserId();
            $ip = getClientIP();
            $userAgent = getUserAgent();

            $data = [
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'meta_data' => $metaData ? json_encode($metaData) : null,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip_address' => $ip,
                'user_agent' => $userAgent
            ];

            $sql = "INSERT INTO {$this->table} (user_id, action, description, meta_data, entity_type, entity_id, ip_address, user_agent) 
                    VALUES (:user_id, :action, :description, :meta_data, :entity_type, :entity_id, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($data);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent logs with user details
     */
    public function getRecentLogs($limit = 50, $offset = 0) {
        $sql = "SELECT l.*, u.NAME as user_name, u.USER_NAME as usn, u.USER_GROUP as role 
                FROM {$this->table} l
                LEFT JOIN users u ON (l.user_id = u.SL_NO OR l.user_id = u.USER_NAME)
                ORDER BY l.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get usage statistics for the admin dashboard
     */
    public function getUsageStats() {
        $stats = [];
        
        // Active users today
        $stmt = $this->db->query("SELECT COUNT(DISTINCT user_id) as count FROM {$this->table} WHERE DATE(created_at) = CURDATE()");
        $stats['active_today'] = $stmt->fetch()['count'];

        // Total actions today
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$this->table} WHERE DATE(created_at) = CURDATE()");
        $stats['actions_today'] = $stmt->fetch()['count'];

        // Most active users last 7 days
        $sql = "SELECT u.NAME as user_name, u.USER_NAME as usn, COUNT(*) as action_count 
                FROM {$this->table} l
                JOIN users u ON (l.user_id = u.SL_NO OR l.user_id = u.USER_NAME)
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY l.user_id
                ORDER BY action_count DESC
                LIMIT 5";
        $stats['top_users'] = $this->db->query($sql)->fetchAll();

        // Action distribution
        $sql = "SELECT action, COUNT(*) as count 
                FROM {$this->table} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY action
                ORDER BY count DESC
                LIMIT 10";
        $stats['action_distribution'] = $this->db->query($sql)->fetchAll();

        return $stats;
    }
}
