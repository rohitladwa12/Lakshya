<?php
/**
 * Internship Model
 * Handles internship postings
 */

require_once __DIR__ . '/Model.php';

class Internship extends Model {
    protected $table = 'internships';
    protected $timestamps = false; // Disable auto timestamps - table only has created_at
    protected $fillable = [
        'internship_title', 'company_name', 'company_logo', 'location', 
        'duration', 'stipend', 'mode', 'targeted_students', 
        'description', 'requirements', 'responsibilities', 
        'start_date', 'end_date', 'application_deadline', 
        'positions', 'description_documents', 'link',
        'created_by', 'created_at', 'status'
    ];

    /**
     * Get internship with application count
     */
    public function getWithStats($status = null) {
        $sql = "SELECT i.*, 
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id) as application_count,
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status = 'Shortlisted') as shortlisted_count,
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status = 'Selected') as selected_count
                FROM {$this->table} i";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE i.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY i.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get active internships for students (only from this portal)
     */
    public function getActiveInternships() {
        $sql = "SELECT i.*, 
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id) as application_count,
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status = 'Shortlisted') as shortlisted_count,
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status = 'Selected') as selected_count
                FROM {$this->table} i
                WHERE i.status = 'Active' 
                  AND i.created_by IS NOT NULL
                ORDER BY i.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get all internships from this portal only (for officer dashboard)
     */
    public function getPortalInternships($status = null) {
        $sql = "SELECT i.*, 
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id) as application_count,
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status = 'Shortlisted') as shortlisted_count,
                       (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.id AND ia.status = 'Selected') as selected_count
                FROM {$this->table} i
                WHERE i.created_by IS NOT NULL";
        
        $params = [];
        if ($status) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY i.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
