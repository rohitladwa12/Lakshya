<?php
/**
 * Company Model
 */

require_once __DIR__ . '/Model.php';

class Company extends Model {
    protected $table = 'companies';
    protected $fillable = [
        'name', 'industry', 'sector', 'website', 'description', 
        'logo_url', 'document_url', 'district', 'state', 'country', 'is_active'
    ];
    
    /**
     * Get active companies
     */
    public function getActiveCompanies() {
        return $this->findAll(['is_active' => 1], 'name ASC');
    }
    
    /**
     * Get company with job postings
     */
    public function getWithJobs($companyId, $status = 'Active') {
        $company = $this->find($companyId);
        if (!$company) return null;
        
        $sql = "SELECT * FROM job_postings 
                WHERE company_id = ? AND status = ?
                ORDER BY posted_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$companyId, $status]);
        $company['jobs'] = $stmt->fetchAll();
        
        return $company;
    }
    
    /**
     * Get company with internship postings
     */
    public function getWithInternships($companyId, $status = 'Active') {
        $company = $this->find($companyId);
        if (!$company) return null;
        
        $sql = "SELECT * FROM internship_postings 
                WHERE company_id = ? AND status = ?
                ORDER BY posted_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$companyId, $status]);
        $company['internships'] = $stmt->fetchAll();
        
        return $company;
    }
    
    /**
     * Get company statistics
     */
    public function getStatistics($companyId) {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'Active') as active_jobs,
                    (SELECT COUNT(*) FROM internship_postings WHERE company_id = ? AND status = 'Active') as active_internships,
                    (SELECT COUNT(*) FROM job_applications ja 
                     JOIN job_postings jp ON ja.job_id = jp.id 
                     WHERE jp.company_id = ?) as total_job_applications,
                    (SELECT COUNT(*) FROM internship_applications ia 
                     JOIN internship_postings ip ON ia.internship_id = ip.id 
                     WHERE ip.company_id = ?) as total_internship_applications";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$companyId, $companyId, $companyId, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Activate company
     */
    public function activate($companyId) {
        return $this->update($companyId, ['is_active' => 1]);
    }
    
    /**
     * Deactivate company
     */
    public function deactivate($companyId) {
        return $this->update($companyId, ['is_active' => 0]);
    }
    
    /**
     * Search companies
     */
    public function search($query) {
        $sql = "SELECT * FROM {$this->table}
                WHERE name LIKE ? OR industry LIKE ?
                ORDER BY name ASC";
        
        $searchTerm = "%{$query}%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll();
    }
    /**
     * Get SPOCs for a company
     */
    public function getSpocs($companyId) {
        $sql = "SELECT * FROM company_spocs WHERE company_id = ? ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update SPOCs for a company
     * deletes existing and adds new ones to keep it simple
     */
    public function updateSpocs($companyId, $spocs) {
        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }
        
        try {
            // Delete existing
            $sql = "DELETE FROM company_spocs WHERE company_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$companyId]);
            
            // Add new ones
            if (!empty($spocs)) {
                $sql = "INSERT INTO company_spocs (company_id, name, designation, email, phone) VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                foreach ($spocs as $spoc) {
                    if (empty($spoc['name'])) continue;
                    $stmt->execute([
                        $companyId,
                        $spoc['name'],
                        $spoc['designation'] ?? '',
                        $spoc['email'] ?? '',
                        $spoc['phone'] ?? ''
                    ]);
                }
            }
            
            if (!$inTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e; // Re-throw to let handler know
        }
    }
}
