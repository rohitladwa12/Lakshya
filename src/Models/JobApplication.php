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
        'status', 'applied_at', 'status_updated_at', 'notes', 'applied_semester', 'applied_sgpa'
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
        $user = $userModel->findByUsername($studentId) ?: $userModel->find($studentId);
        $usn = $user ? $user['username'] : $studentId; // USN
        
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
        
        // Resolve student's current semester and SGPA to store as a static snapshot
        $applied_semester = null;
        $applied_sgpa = 0.00;
        
        if ($user) {
            $inst = $user['institution'];
            $db = $this->getDB();
            
            if ($inst === INSTITUTION_GMU) {
                $prefix = DB_GMU_PREFIX;
                $remoteDB = getDB('gmu');
                if ($remoteDB) {
                    $stmtSem = $remoteDB->prepare("SELECT sem FROM {$prefix}ad_student_approved WHERE usn = ? ORDER BY academic_year DESC, sem DESC LIMIT 1");
                    $stmtSem->execute([$user['username']]);
                    $applied_semester = $stmtSem->fetchColumn();

                    $stmtSgpa = $remoteDB->prepare("SELECT sgpa FROM {$prefix}ad_student_approved WHERE usn = ? AND sgpa IS NOT NULL AND sgpa > 0 ORDER BY academic_year DESC, sem DESC LIMIT 1");
                    $stmtSgpa->execute([$user['username']]);
                    $applied_sgpa = $stmtSgpa->fetchColumn() ?: 0.00;
                }
            } else {
                $stmtSem = $db->prepare("SELECT semester FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                $stmtSem->execute([$user['username'], INSTITUTION_GMIT]);
                $applied_semester = $stmtSem->fetchColumn();

                $stmtSgpa = $db->prepare("SELECT sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND sgpa > 0 ORDER BY semester DESC LIMIT 1");
                $stmtSgpa->execute([$user['username'], INSTITUTION_GMIT]);
                $applied_sgpa = $stmtSgpa->fetchColumn() ?: 0.00;
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
            'applied_at' => date('Y-m-d H:i:s'),
            'applied_semester' => $applied_semester,
            'applied_sgpa' => $applied_sgpa
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
        
        // Comprehensive multi-institution query:
        // Derives student keys from ad_student_details, ad_student_approved, and users
        $sqlUsers = "SELECT 
                        k.student_key as usn,
                        COALESCE(d.name, ap.name, u.NAME, k.student_key) as full_name,
                        COALESCE(u.USER_NAME, d.email_id, k.student_key) as email,
                        COALESCE(u.MOBILE_NO, d.student_mobile, '-') as phone,
                        COALESCE(ap.course, d.course, 'BTECH') as course,
                        COALESCE(ap.sgpa, 0.00) as cgpa,
                        d.puc_percentage, d.sslc_percentage,
                        '" . INSTITUTION_GMU . "' as institution
                    FROM (
                        SELECT usn as student_key FROM {$gmuPrefix}ad_student_details WHERE usn IN ($placeholders) OR student_id IN ($placeholders)
                        UNION
                        SELECT usn as student_key FROM {$gmuPrefix}ad_student_approved WHERE usn IN ($placeholders)
                        UNION
                        SELECT USER_NAME as student_key FROM {$gmuPrefix}users WHERE USER_NAME IN ($placeholders)
                    ) k
                    LEFT JOIN {$gmuPrefix}ad_student_details d ON (k.student_key = d.usn OR k.student_key = d.student_id)
                    LEFT JOIN (
                        SELECT a.* FROM {$gmuPrefix}ad_student_approved a
                        JOIN (SELECT usn, MAX(sem) as max_sem FROM {$gmuPrefix}ad_student_approved GROUP BY usn) b 
                        ON a.usn = b.usn AND a.sem = b.max_sem
                    ) ap ON (k.student_key = ap.usn)
                    LEFT JOIN {$gmuPrefix}users u ON (k.student_key = u.USER_NAME)
                    
                    UNION ALL
                    
                    SELECT u.USER_NAME as usn, 
                           COALESCE(gmit_d.name, u.NAME) as full_name, 
                           u.USER_NAME as email, 
                           u.MOBILE_NO as phone,
                           gmit_d.course as course, 
                           0.00 as cgpa, 
                           gmit_d.puc_percentage, gmit_d.sslc_percentage,
                           '" . INSTITUTION_GMIT . "' as institution
                    FROM {$gmitPrefix}users u
                    LEFT JOIN {$gmitPrefix}ad_student_details gmit_d 
                         ON (u.USER_NAME = gmit_d.student_id OR u.ENQUIRY_NO = gmit_d.enquiry_no)
                    WHERE u.USER_NAME IN ($placeholders)";
        
        $params = array_merge($studentIds, $studentIds, $studentIds, $studentIds, $studentIds);
        
        $stmtRemote = $remoteDB->prepare($sqlUsers);
        $stmtRemote->execute($params);
        $userDetails = $stmtRemote->fetchAll(PDO::FETCH_ASSOC);
        
        // Map user details by USN/Username
        $userMap = [];
        foreach ($userDetails as $user) {
            $userMap[$user['usn']] = $user;
            $userMap[strtoupper($user['usn'])] = $user;
            $userMap[strtolower($user['usn'])] = $user;
        }
        
        foreach ($applications as &$app) {
            $sid = $app['student_id'];
            $u = $userMap[$sid] ?? ($userMap[strtoupper($sid)] ?? ($userMap[strtolower($sid)] ?? null));
            if ($u) {
                $app['student_name'] = !empty($u['full_name']) ? $u['full_name'] : $sid;
                $app['full_name'] = $app['student_name'];
                $app['email'] = $u['email'];
                $app['phone'] = $u['phone'];
                $app['usn'] = $u['usn'];
                $app['course'] = !empty($u['course']) ? $u['course'] : 'N/A';
                $app['sgpa'] = $u['cgpa'];
                $app['cgpa'] = $u['cgpa'];
                $app['puc_percentage'] = $u['puc_percentage'] ?? 'N/A';
                $app['sslc_percentage'] = $u['sslc_percentage'] ?? 'N/A';
                $app['institution'] = $u['institution'];
            } else {
                $app['student_name'] = $sid;
                $app['full_name'] = $sid;
                $app['email'] = $sid;
                $app['phone'] = '-';
                $app['usn'] = $sid;
                $app['course'] = '-';
                $app['sgpa'] = '-';
                $app['cgpa'] = '-';
                $app['puc_percentage'] = '-';
                $app['sslc_percentage'] = '-';
                $app['institution'] = (strpos(strtoupper($sid), '4GM') === 0 || strpos(strtoupper($sid), 'GMIT') === 0) ? INSTITUTION_GMIT : INSTITUTION_GMU;
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
