<?php
/**
 * JobApplication Model
 * Handles job applications
 */

require_once __DIR__ . '/Model.php';

class JobApplication extends Model {
    protected $table = 'job_applications';
    protected $timestamps = false;
    protected $fillable = [
        'job_id', 'student_id', 'cover_letter', 'custom_responses', 'resume_path',
        'status', 'applied_at', 'status_updated_at', 'notes'
    ];
    
    /**
     * Submit application
     */
    public function apply($jobId, $studentId, $data = []) {
        // Check if already applied
        if ($this->hasApplied($jobId, $studentId)) {
            return ['success' => false, 'message' => 'You have already applied to this job'];
        }
        
        // Check if job is still active
        $jobModel = new JobPosting();
        $job = $jobModel->find($jobId);
        
        if (!$job || $job['status'] !== 'Active') {
            return ['success' => false, 'message' => 'This job is no longer accepting applications'];
        }
        
        if ($job['application_deadline'] < date('Y-m-d')) {
            return ['success' => false, 'message' => 'Application deadline has passed'];
        }
        
        $resume_path = $data['resume_path'] ?? null;
        
        // Handle Resume Logic (Global Resume)
        $userModel = new User();
        $user = $userModel->find($studentId);
        $usn = $user['username']; // USN
        
        $uploadDir = RESUME_UPLOAD_PATH . '/Student_Resumes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = $usn . '_Resume.pdf';
        $targetPath = $uploadDir . $fileName;
        $dbPath = 'uploads/resumes/Student_Resumes/' . $fileName;

        if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
            // New file uploaded -> Overwrite existing
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetPath)) {
                $resume_path = $dbPath;
            } else {
                return ['success' => false, 'message' => 'Failed to upload resume'];
            }
        } else {
            // No file uploaded -> Check for existing
            if (file_exists($targetPath)) {
                 $resume_path = $dbPath;
            } else {
                 return ['success' => false, 'message' => 'Resume is required. Please upload a PDF resume.'];
            }
        }
        
        // Create application
        $applicationData = [
            'job_id' => $jobId,
            'student_id' => $studentId,
            'cover_letter' => $data['cover_letter'] ?? null,
            'custom_responses' => $data['custom_responses'] ?? null,
            'resume_path' => $resume_path,
            'status' => 'Applied',
            'applied_at' => date('Y-m-d H:i:s')
        ];
        
        $applicationId = $this->create($applicationData);
        
        if ($applicationId) {
            return ['success' => true, 'message' => 'Application submitted successfully', 'id' => $applicationId];
        }
        
        return ['success' => false, 'message' => 'Failed to submit application'];
    }
    
    /**
     * Check if student has applied
     */
    public function hasApplied($jobId, $studentId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE job_id = ? AND student_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$jobId, $studentId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Get student's applications
     */
    public function getByStudent($studentId) {
        $sql = "SELECT ja.*, jp.title as job_title, jp.location, jp.job_type,
                       c.name as company_name, c.logo_url as company_logo
                FROM {$this->table} ja
                JOIN job_postings jp ON ja.job_id = jp.id
                JOIN companies c ON jp.company_id = c.id
                WHERE ja.student_id = ?
                ORDER BY ja.applied_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get applications for a job
     */
    /**
     * Get applications for a job
     */
    public function getByJob($jobId, $status = null) {
        // 1. Fetch Applications (LOCAL DB)
        $sql = "SELECT ja.*, jp.title as job_title, c.name as company_name 
                FROM {$this->table} ja 
                JOIN job_postings jp ON ja.job_id = jp.id
                JOIN companies c ON jp.company_id = c.id
                WHERE ja.job_id = ?";
        $params = [$jobId];

        if ($status) {
            $sql .= " AND ja.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY ja.applied_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll();
        
        if (empty($applications)) {
            return [];
        }
        
        // 2. Collect Student IDs
        $studentIds = array_column($applications, 'student_id');
        
        // 3. Fetch User Details (REMOTE DB)
        // We need to fetch details for these IDs. 
        // We can check both GMU and GMIT users.
        
        $remoteDB = getDB('gmu'); // Remote connection
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        
        // Prepare placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        
        // Fetch Users + Profile info
        // Note: Joining Users + AD Tables remotely is fine.
        $sqlUsers = "SELECT u.SL_NO, u.NAME as full_name, u.USER_NAME as email, u.MOBILE_NO as phone,
                            sp.usn as enrollment_number, sp.course, sp.sgpa as cgpa
                     FROM (
                        SELECT SL_NO, NAME, USER_NAME, MOBILE_NO, institution FROM (
                            SELECT SL_NO, NAME, USER_NAME, MOBILE_NO, 'GMU' as institution FROM {$gmuPrefix}users
                            UNION ALL
                            SELECT SL_NO, NAME, USER_NAME, MOBILE_NO, 'GMIT' as institution FROM {$gmitPrefix}users
                        ) as u_combined WHERE SL_NO IN ($placeholders)
                     ) u
                     LEFT JOIN (
                        SELECT usn, course, sgpa, usn as student_id FROM {$gmuPrefix}ad_student_approved
                        UNION ALL
                        SELECT usn, course, 0.0 as sgpa, student_id FROM {$gmitPrefix}ad_student_details
                     ) sp ON ( (u.email = sp.student_id AND u.institution = 'GMIT') OR (u.email = sp.student_id AND u.institution = 'GMU') )";
                     // WAIT: JOIN condition in original was:
                     // (u.USER_NAME = sp.student_id AND u.institution = 'GMIT') OR (u.USER_NAME = sp.usn AND u.institution = 'GMU')
                     // I aliased USER_NAME as email in step 1, but inside subquery it is USER_NAME.
        
        // Simplified Query: Fetch by USER_NAME because job_applications.student_id now stores USN
        $sqlUsers = "SELECT u.SL_NO, u.NAME as full_name, u.USER_NAME as email, u.USER_NAME as usn, u.MOBILE_NO as phone, u.institution,
                            sp.course, sp.sgpa as cgpa, sp.puc_percentage, sp.sslc_percentage
                     FROM (
                        SELECT SL_NO, NAME, USER_NAME, MOBILE_NO, '" . INSTITUTION_GMU . "' as institution 
                        FROM {$gmuPrefix}users 
                        WHERE USER_NAME IN ($placeholders)
                        
                        UNION ALL
                        
                        SELECT u.ENQUIRY_NO as SL_NO, COALESCE(d.name, u.NAME) as NAME, u.USER_NAME, u.MOBILE_NO, '" . INSTITUTION_GMIT . "' as institution 
                        FROM {$gmitPrefix}users u
                        LEFT JOIN {$gmitPrefix}ad_student_details d ON u.USER_NAME = d.student_id
                        WHERE u.USER_NAME IN ($placeholders)
                     ) u
                     LEFT JOIN (
                        SELECT a.usn, a.course, a.sgpa, d.puc_percentage, d.sslc_percentage, a.usn as student_id 
                        FROM {$gmuPrefix}ad_student_approved a
                        LEFT JOIN {$gmuPrefix}ad_student_details d ON a.usn = d.student_id
                        
                        UNION ALL
                        
                        SELECT usn, course, 0.0 as sgpa, puc_percentage, sslc_percentage, student_id 
                        FROM {$gmitPrefix}ad_student_details
                     ) sp ON u.USER_NAME = sp.student_id";
        
        // Optimization: We are querying ALL users in placeholders. 
        // IDs are unique across SL_NO?
        // Wait, SL_NO in GMU and ENQUIRY_NO in GMIT might collide?
        // User model `find` handles this using Institution context.
        // `job_applications.student_id` stores the User ID.
        // If IDs collide, we have a problem.
        // `User.php` mapToAppUser uses SL_NO or ENQUIRY_NO.
        // Ideally, `job_applications` should store `institution` too, or use a Globally Unique ID (UUID).
        // If `student_id` is just an integer, and GMU User 1 and GMIT User 1 both exist, who applied?
        // Current system likely assumes unique IDs or relies on Context.
        // BUT `job_applications` table structure (seen in `JobApplication.php`) has `student_id`.
        // If we assume IDs are unique enough or handled.
        // For this refactor, I will fetch ALL matching IDs from both tables.
        
        // NOTE: The placeholders array needs to be duplicated for the two SELECTs in UNION if strict.
        // But simpler: just run query for each and merge?
        // Or construct IN clause.
        
        // Let's use the provided params twice because of UNION.
        $params = array_merge($studentIds, $studentIds);
        
        $stmtRemote = $remoteDB->prepare($sqlUsers);
        $stmtRemote->execute($params);
        $userDetails = $stmtRemote->fetchAll(PDO::FETCH_ASSOC);
        
        // Map user details by USN/Username
        $userMap = [];
        foreach ($userDetails as $user) {
            $userMap[$user['usn']] = $user;
        }
        
        foreach ($applications as &$app) {
            $sid = $app['student_id'];
            if (isset($userMap[$sid])) {
                $u = $userMap[$sid];
                $app['student_name'] = $u['full_name'];
                $app['full_name'] = $u['full_name']; // For consistency
                $app['email'] = $u['email'];
                $app['phone'] = $u['phone'];
                $app['usn'] = $u['usn'];
                $app['course'] = $u['course'];
                $app['sgpa'] = $u['cgpa'];
                $app['cgpa'] = $u['cgpa']; // Backward compatibility
                $app['puc_percentage'] = $u['puc_percentage'] ?? 'N/A';
                $app['sslc_percentage'] = $u['sslc_percentage'] ?? 'N/A';
                $app['institution'] = $u['institution'];
            } else {
                $app['student_name'] = 'Unknown';
                $app['full_name'] = 'Unknown';
                $app['email'] = 'Unknown';
                $app['phone'] = '-';
                $app['usn'] = '-';
                $app['course'] = '-';
                $app['sgpa'] = '-';
                $app['cgpa'] = '-';
                $app['puc_percentage'] = '-';
                $app['sslc_percentage'] = '-';
                $app['institution'] = '-';
            }
        }
        
        return $applications;
    }
    
    /**
     * Update application status
     */
    public function updateStatus($applicationId, $status, $notes = null) {
        $data = [
            'status' => $status,
            'status_updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($notes) {
            $data['admin_notes'] = $notes;
        }
        
        return $this->update($applicationId, $data);
    }
    
    /**
     * Withdraw application
     */
    public function withdraw($applicationId, $studentId) {
        $application = $this->find($applicationId);
        
        if (!$application || $application['student_id'] != $studentId) {
            return ['success' => false, 'message' => 'Application not found'];
        }
        
        if ($application['status'] === 'Selected') {
            return ['success' => false, 'message' => 'Cannot withdraw a selected application'];
        }
        
        $this->updateStatus($applicationId, 'Withdrawn');
        return ['success' => true, 'message' => 'Application withdrawn successfully'];
    }
    
    /**
     * Get application statistics
     */
    public function getStatistics($studentId = null) {
        $sql = "SELECT 
                    status as application_status,
                    COUNT(*) as count
                FROM {$this->table}";
        
        $params = [];
        if ($studentId) {
            $sql .= " WHERE student_id = ?";
            $params[] = $studentId;
        }
        
        $sql .= " GROUP BY application_status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
