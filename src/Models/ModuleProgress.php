<?php
/**
 * ModuleProgress Model
 */
class ModuleProgress extends Model {
    protected $table = 'student_module_progress';
    protected $fillable = [
        'student_id', 'module_id', 'status', 'progress_percentage',
        'quiz_score', 'started_at', 'completed_at'
    ];

    /**
     * Get student's academic progress summary
     */
    public function getStudentSummary($studentId) {
        $sql = "SELECT 
                    COUNT(*) as total_modules,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_modules,
                    AVG(CASE WHEN quiz_score IS NOT NULL THEN quiz_score ELSE NULL END) as avg_quiz_score
                FROM {$this->table}
                WHERE student_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetch();
    }

    /**
     * Get recently completed topics
     */
    public function getRecentTopics($studentId, $limit = 3) {
        $sql = "SELECT m.title as module_title, c.title as chapter_title, p.completed_at
                FROM {$this->table} p
                JOIN learning_modules m ON p.module_id = m.id
                JOIN learning_chapters c ON m.chapter_id = c.id
                WHERE p.student_id = ? AND p.status = 'Completed'
                ORDER BY p.completed_at DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $limit]);
        return $stmt->fetchAll();
    }
}
