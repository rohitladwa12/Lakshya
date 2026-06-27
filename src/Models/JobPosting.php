<?php
/**
 * JobPosting Model
 * Handles job postings and applications
 */

require_once __DIR__ . '/Model.php';

class JobPosting extends Model {
    protected $table = 'job_postings';
    protected $fillable = [
        'company_id', 'academic_year', 'title', 'description', 'requirements', 'responsibilities',
        'location', 'job_type', 'work_mode', 'salary_min', 'salary_max',
        'min_cgpa', 'eligible_courses', 'eligible_branches', 'eligible_years', 'custom_fields', 'posted_date',
        'application_deadline', 'status', 'posted_by'
    ];

    /**
     * Broadcast a notification via Redis
     */
    protected function broadcast($data) {
        try {
            $redis = \App\Helpers\RedisHelper::getInstance();
            if ($redis->isConnected()) {
                $redis->getClient()->publish('campus_feed', json_encode($data));
            }
        } catch (Exception $e) {
            error_log("Redis Broadcast Failed: " . $e->getMessage());
        }
    }

    /**
     * Override create to broadcast new job
     */
    public function create($data) {
        $id = parent::create($data);
        if ($id && isset($data['status']) && $data['status'] === 'Active') {
            $companyModel = new Company();
            $company = $companyModel->find($data['company_id']);
            $this->broadcast([
                'type' => 'job_alert',
                'id' => $id,
                'title' => 'New Job: ' . ($data['title'] ?? 'Position Open'),
                'subtitle' => ($company['name'] ?? 'A Company') . ' is hiring!',
                'link' => 'jobs.php',
                'company_logo' => $company['logo_url'] ?? null,
                'time' => date('c')
            ]);
        }
        return $id;
    }

    /**
     * Override update to broadcast when status changes to Active
     */
    public function update($id, $data) {
        $result = parent::update($id, $data);
        if ($result && isset($data['status']) && $data['status'] === 'Active') {
            $job = $this->getWithCompany($id);
            if ($job) {
                $this->broadcast([
                    'type' => 'job_alert',
                    'id' => $id,
                    'title' => 'New Job: ' . $job['title'],
                    'subtitle' => $job['company_name'] . ' is hiring!',
                    'link' => 'jobs.php',
                    'company_logo' => $job['company_logo'] ?? null,
                    'time' => date('c')
                ]);
            }
        }
        return $result;
    }
    
    /**
     * Get active jobs
     */
    public function getActiveJobs($limit = null) {
        $sql = "SELECT jp.*, c.name as company_name, c.logo_url as company_logo, 
                       c.sector, c.industry, c.description as company_description,
                       c.website, c.district, c.state, c.country
                FROM {$this->table} jp
                JOIN companies c ON jp.company_id = c.id
                WHERE jp.status = 'Active' 
                  AND jp.application_deadline >= CURDATE()
                  AND c.is_active = 1
                ORDER BY jp.posted_date DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        return $this->db->query($sql)->fetchAll();
    }
    
    /**
     * Get job with company details
     */
    public function getWithCompany($jobId) {
        $sql = "SELECT jp.*, c.name as company_name, c.industry, c.sector, c.website, 
                       c.logo_url as company_logo, c.document_url as company_document,
                       c.description as company_description, c.district, c.state, c.country
                FROM {$this->table} jp
                JOIN companies c ON jp.company_id = c.id
                WHERE jp.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        return $stmt->fetch();
    }
    
    /**
     * Get job with full company details and SPOCs
     */
    public function getWithFullDetails($jobId) {
        $job = $this->getWithCompany($jobId);
        if (!$job) return null;
        
        $companyModel = new Company();
        $job['spocs'] = $companyModel->getSpocs($job['company_id']);
        
        // Get skills too
        $sql = "SELECT s.*, jrs.is_mandatory
                FROM job_required_skills jrs
                JOIN skills s ON jrs.skill_id = s.id
                WHERE jrs.job_id = ?
                ORDER BY jrs.is_mandatory DESC, s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        $job['required_skills'] = $stmt->fetchAll();
        
        return $job;
    }
    
    /**
     * Get job with required skills
     */
    public function getWithSkills($jobId) {
        $job = $this->getWithCompany($jobId);
        if (!$job) return null;
        
        $sql = "SELECT s.*, jrs.is_mandatory
                FROM job_required_skills jrs
                JOIN skills s ON jrs.skill_id = s.id
                WHERE jrs.job_id = ?
                ORDER BY jrs.is_mandatory DESC, s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        $job['required_skills'] = $stmt->fetchAll();
        
        return $job;
    }
    
    /**
     * Get jobs for student (with eligibility check tags)
     */
    public function getJobsForStudent($studentId) {
        // Get student profile
        $profileModel = new StudentProfile();
        $profile = $profileModel->getByUserId($studentId);
        
        if (!$profile) return [];
        
        $sql = "SELECT jp.*, c.name as company_name, c.logo_url as company_logo,
                       (SELECT COUNT(*) FROM job_applications 
                        WHERE job_id = jp.id AND student_id = ?) as has_applied
                FROM {$this->table} jp
                JOIN companies c ON jp.company_id = c.id
                WHERE jp.status IN ('Active', 'Closed')
                  AND c.is_active = 1
                ORDER BY jp.posted_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        $jobs = $stmt->fetchAll();
        
        // Tag with eligibility
        foreach ($jobs as &$job) {
            $job['is_eligible'] = true;
            $job['ineligibility_reasons'] = [];
            
            // Strict SGPA Check (All semesters above threshold)
            if ($job['min_cgpa'] > 0) {
                $check = $profileModel->isEligibleStrict($studentId, $job['min_cgpa'], $job);
                if (!$check['eligible']) {
                    $job['is_eligible'] = false;
                    $job['ineligibility_reasons'] = array_merge($job['ineligibility_reasons'], $check['reasons']);
                }
            } else {
                // Check Course and Branches anyway if no SGPA requirement
                $courses = json_decode($job['eligible_courses'] ?: '', true) ?: [];
                if (!empty($courses) && !in_array($profile['course'], $courses)) {
                    $job['is_eligible'] = false;
                    $job['ineligibility_reasons'][] = "Open to " . implode(', ', $courses) . " only";
                }

                // Check Branches if specified
                $branches = json_decode($job['eligible_branches'] ?: '', true) ?: [];
                if (!empty($branches) && !empty($profile['department'])) {
                    $studentBranch = strtoupper(trim($profile['department']));
                    $equivalentBranches = getEquivalentBranches($studentBranch);
                    $matchFound = false;
                    foreach ($equivalentBranches as $eqBranch) {
                        if (in_array($eqBranch, $branches)) {
                            $matchFound = true;
                            break;
                        }
                    }
                    if (!$matchFound) {
                        $job['is_eligible'] = false;
                        $job['ineligibility_reasons'][] = "Open to branches: " . implode(', ', $branches);
                    }
                }

                
                // Check Year
                $years = json_decode($job['eligible_years'] ?: '', true) ?: [];
                if (!empty($years) && !in_array($profile['year_of_study'], $years)) {
                    $job['is_eligible'] = false;
                    $job['ineligibility_reasons'][] = "Open to year(s) " . implode(', ', $years) . " only";
                }
            }
        }
        
        return $jobs;
    }
    
    /**
     * Get application count for job
     */
    public function getApplicationCount($jobId) {
        $sql = "SELECT COUNT(*) as count FROM job_applications WHERE job_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId]);
        $result = $stmt->fetch();
        return $result['count'];
    }
    
    /**
     * Close job posting
     */
    public function closeJob($jobId) {
        return $this->update($jobId, ['status' => 'Closed']);
    }
    
    /**
     * Search jobs
     */
    public function search($query, $filters = []) {
        $sql = "SELECT jp.*, c.name as company_name, c.logo_url as company_logo
                FROM {$this->table} jp
                JOIN companies c ON jp.company_id = c.id
                WHERE jp.status = 'Active'
                  AND (jp.title LIKE ? OR jp.description LIKE ? OR c.name LIKE ?)";
        
        $params = ["%{$query}%", "%{$query}%", "%{$query}%"];
        
        if (isset($filters['location'])) {
            $sql .= " AND jp.location LIKE ?";
            $params[] = "%{$filters['location']}%";
        }
        
        if (isset($filters['job_type'])) {
            $sql .= " AND jp.job_type = ?";
            $params[] = $filters['job_type'];
        }
        
        if (isset($filters['work_mode'])) {
            $sql .= " AND jp.work_mode = ?";
            $params[] = $filters['work_mode'];
        }
        
        $sql .= " ORDER BY jp.posted_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    /**
     * Get all jobs with company name
     */
    public function getAllWithCompany($orderBy = null) {
        $sql = "SELECT jp.*, c.name as company_name,
                       (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = jp.id) as application_count
                FROM {$this->table} jp
                JOIN companies c ON jp.company_id = c.id";
        
        if ($orderBy) {
             $sql .= " ORDER BY " . $orderBy;
        } else {
             $sql .= " ORDER BY jp.posted_date DESC";
        }
        
        return $this->db->query($sql)->fetchAll();
    }
}
