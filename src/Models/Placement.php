<?php
/**
 * Placement Model
 * Handles placement records
 */

require_once __DIR__ . '/Model.php';

class Placement extends Model {
    protected $table = 'placements';
    protected $fillable = [
        'job_id', 'student_id', 'company_id', 'institution',
        'salary_package', 'placement_date', 'document_path', 'status'
    ];
    
    /**
     * Get placement by job and student
     */
    public function getByJobAndStudent($jobId, $studentId) {
        $sql = "SELECT * FROM {$this->table} WHERE job_id = ? AND student_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId, $studentId]);
        return $stmt->fetch();
    }
    
    /**
     * Get student's placement status
     */
    public function getStudentPlacements($studentId) {
        $sql = "SELECT p.*, jp.title as job_title, c.name as company_name 
                FROM {$this->table} p
                JOIN job_postings jp ON p.job_id = jp.id
                JOIN companies c ON p.company_id = c.id
                WHERE p.student_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
}
