<?php
/**
 * InternshipPosting Model
 * Handles internship postings and applications
 */

require_once __DIR__ . '/Model.php';

class InternshipPosting extends Model {
    protected $table = 'internship_postings';
    protected $fillable = [
        'company_id', 'title', 'description', 'requirements', 'responsibilities',
        'duration_months', 'start_date', 'stipend_min', 'stipend_max',
        'location', 'work_mode', 'min_cgpa', 'eligible_courses', 'eligible_years',
        'posted_date', 'application_deadline', 'status', 'posted_by'
    ];
    
    /**
     * Get active internships
     */
    public function getActiveInternships($limit = null) {
        $sql = "SELECT ip.*, c.name as company_name, c.logo_url as company_logo
                FROM {$this->table} ip
                JOIN companies c ON ip.company_id = c.id
                WHERE ip.status = 'Active' 
                  AND ip.application_deadline >= CURDATE()
                  AND c.is_active = 1
                ORDER BY ip.posted_date DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        return $this->db->query($sql)->fetchAll();
    }
    
    /**
     * Get internship with company details
     */
    public function getWithCompany($internshipId) {
        $sql = "SELECT ip.*, c.name as company_name, c.industry, c.website,
                       c.logo_url as company_logo, c.description as company_description
                FROM {$this->table} ip
                JOIN companies c ON ip.company_id = c.id
                WHERE ip.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$internshipId]);
        return $stmt->fetch();
    }
    
    /**
     * Get internship with required skills
     */
    public function getWithSkills($internshipId) {
        $internship = $this->getWithCompany($internshipId);
        if (!$internship) return null;
        
        $sql = "SELECT s.*, irs.is_mandatory
                FROM internship_required_skills irs
                JOIN skills s ON irs.skill_id = s.id
                WHERE irs.internship_id = ?
                ORDER BY irs.is_mandatory DESC, s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$internshipId]);
        $internship['required_skills'] = $stmt->fetchAll();
        
        return $internship;
    }
    
    /**
     * Get internships for student
     */
    public function getInternshipsForStudent($studentId) {
        $profileModel = new StudentProfile();
        $profile = $profileModel->getByUserId($studentId);
        
        if (!$profile) return [];
        
        $sql = "SELECT ip.*, c.name as company_name, c.logo_url as company_logo,
                       (SELECT COUNT(*) FROM internship_applications 
                        WHERE internship_id = ip.id AND student_id = ?) as has_applied
                FROM {$this->table} ip
                JOIN companies c ON ip.company_id = c.id
                WHERE ip.status = 'Active' 
                  AND ip.application_deadline >= CURDATE()
                  AND c.is_active = 1
                  AND ip.min_cgpa <= ?
                ORDER BY ip.posted_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $profile['cgpa']]);
        $internships = $stmt->fetchAll();
        
        // Filter by course and year
        $eligible = [];
        foreach ($internships as $internship) {
            $courses = json_decode($internship['eligible_courses'], true);
            $years = json_decode($internship['eligible_years'], true);
            
            if (in_array($profile['course'], $courses) && in_array($profile['year_of_study'], $years)) {
                $eligible[] = $internship;
            }
        }
        
        return $eligible;
    }
    
    /**
     * Get application count
     */
    public function getApplicationCount($internshipId) {
        $sql = "SELECT COUNT(*) as count FROM internship_applications WHERE internship_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$internshipId]);
        $result = $stmt->fetch();
        return $result['count'];
    }
    
    /**
     * Close internship
     */
    public function closeInternship($internshipId) {
        return $this->update($internshipId, ['status' => 'Closed']);
    }
}
