<?php
/**
 * Internship Application Model
 */

require_once __DIR__ . '/Model.php';

class InternshipApplication extends Model {
    protected $table = 'internship_applications';
    protected $timestamps = false;
    protected $fillable = [
        'internship_id', 'student_id', 'status', 'applied_at', 'resume_path', 'applied_semester', 'applied_sgpa'
    ];

    /**
     * Apply for internship
     */
    public function apply($internshipId, $studentId, $resumePath) {
        if ($this->hasApplied($internshipId, $studentId)) {
            return ['success' => false, 'message' => 'Already applied'];
        }
        
        $internshipModel = new Internship();
        $internship = $internshipModel->find($internshipId);
        if ($internship) {
            $today = date('Y-m-d');
            if ($internship['status'] === 'Closed' || ($internship['application_deadline'] && $internship['application_deadline'] < $today)) {
                return ['success' => false, 'message' => 'Application deadline has passed'];
            }
        }
        
        // Resolve student's current semester and SGPA to store as a static snapshot
        $userModel = new User();
        $user = $userModel->findByUsername($studentId) ?: $userModel->find($studentId);
        
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
        
        $id = $this->create([
            'internship_id' => $internshipId,
            'student_id' => $studentId,
            'status' => 'Applied',
            'applied_at' => date('Y-m-d H:i:s'),
            'resume_path' => $resumePath,
            'applied_semester' => $applied_semester,
            'applied_sgpa' => $applied_sgpa
        ]);
        
        return $id ? ['success' => true, 'id' => $id] : ['success' => false, 'message' => 'Failed to apply'];
    }

    /**
     * Check if already applied
     */
    public function hasApplied($internshipId, $studentId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE internship_id = ? AND student_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$internshipId, $studentId]);
        return $stmt->fetch()['count'] > 0;
    }

    /**
     * Get applications for an internship
     */
    public function getByInternship($internshipId) {
        $sql = "SELECT ia.*, i.internship_title, i.company_name
                FROM {$this->table} ia
                JOIN internships i ON ia.internship_id = i.id
                WHERE ia.internship_id = ?
                ORDER BY ia.applied_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$internshipId]);
        $applications = $stmt->fetchAll();
        
        if (empty($applications)) return [];
        
        // Enrich with Student Data (Similar to JobApplication)
        // Reuse JobApplication logic or duplicate here?
        // Let's create a helper in User model or re-implement fetching logic locally.
        // For now, I'll return raw applications and let the Controller/View fetch user details 
        // OR implement the fetch logic here (better encapsulation).
        
        return $this->enrichWithStudentData($applications);
    }

    /**
     * Get applications by student
     */
    public function getByStudent($studentId) {
        $sql = "SELECT ia.*, i.internship_title, i.company_name, i.location, i.stipend, i.duration
                FROM {$this->table} ia
                JOIN internships i ON ia.internship_id = i.id
                WHERE ia.student_id = ?
                ORDER BY ia.applied_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    /**
     * Private helper to fetch remote student data
     */
    private function enrichWithStudentData($applications) {
        if (empty($applications)) return [];

        $studentIds = array_unique(array_column($applications, 'student_id'));
        if (empty($studentIds)) return $applications;
        
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        
        $remoteDB = getDB('gmu');
        $localDB = getDB(); // For student_sem_sgpa
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        
        // Comprehensive multi-institution query:
        // Derives student keys from ad_student_details, ad_student_approved, and users
        // so that students missing from gmu_users (such as lateral entry or newly admitted students)
        // are fully resolved with their full name, course, branch, sem, and email.
        $sqlUsers = "SELECT 
                        k.student_key as usn,
                        COALESCE(d.name, ap.name, u.NAME, k.student_key) as full_name,
                        COALESCE(u.USER_NAME, d.email_id, k.student_key) as email,
                        COALESCE(u.MOBILE_NO, d.student_mobile, '-') as phone,
                        COALESCE(ap.course, d.course, 'BTECH') as course,
                        COALESCE(ap.sgpa, 0.00) as cgpa,
                        COALESCE(ap.sem, 1) as sem,
                        '" . INSTITUTION_GMU . "' as institution,
                        COALESCE(ap.discipline, d.discipline, 'N/A') as branch
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
                           0 as sem, 
                           '" . INSTITUTION_GMIT . "' as institution, 
                           gmit_d.discipline as branch
                    FROM {$gmitPrefix}users u
                    LEFT JOIN {$gmitPrefix}ad_student_details gmit_d 
                         ON (u.USER_NAME = gmit_d.student_id OR u.ENQUIRY_NO = gmit_d.enquiry_no)
                    WHERE u.USER_NAME IN ($placeholders)";
        
        $params = array_merge($studentIds, $studentIds, $studentIds, $studentIds, $studentIds);
        
        $users = [];
        if ($remoteDB) {
            try {
                $stmt = $remoteDB->prepare($sqlUsers);
                $stmt->execute($params);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error enriching internship applications with student data: " . $e->getMessage());
            }
        }
        
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u['usn']] = $u;
            $userMap[strtoupper($u['usn'])] = $u;
            $userMap[strtolower($u['usn'])] = $u;
        }
        
        // Fetch GMIT current semester and SGPA from student_sem_sgpa
        $gmitSgpaMap = [];
        $gmitStudents = array_filter($users, function($u) { return $u['institution'] === INSTITUTION_GMIT; });
        if (!empty($gmitStudents)) {
            $gmitUsns = array_column($gmitStudents, 'usn');
            $gmitPlaceholders = implode(',', array_fill(0, count($gmitUsns), '?'));
            
            $sqlGmitSgpa = "SELECT student_id, semester, sgpa 
                           FROM student_sem_sgpa 
                           WHERE institution = ? AND student_id IN ($gmitPlaceholders) AND is_current = 1";
            $stmtGmit = $localDB->prepare($sqlGmitSgpa);
            $stmtGmit->execute(array_merge([INSTITUTION_GMIT], $gmitUsns));
            
            while ($row = $stmtGmit->fetch(PDO::FETCH_ASSOC)) {
                $gmitSgpaMap[$row['student_id']] = [
                    'semester' => $row['semester'],
                    'sgpa' => $row['sgpa']
                ];
                $gmitSgpaMap[strtoupper($row['student_id'])] = $gmitSgpaMap[$row['student_id']];
            }
        }
        
        // Fetch ALL semester SGPAs for both GMU and GMIT
        $allSemesterSgpa = []; // student_id => [1 => sgpa, 2 => sgpa, ...]
        
        // GMU: Fetch from ad_student_approved
        $gmuStudents = array_filter($users, function($u) { return $u['institution'] === INSTITUTION_GMU; });
        if (!empty($gmuStudents) && $remoteDB) {
            $gmuUsns = array_column($gmuStudents, 'usn');
            $gmuPlaceholders = implode(',', array_fill(0, count($gmuUsns), '?'));
            
            $sqlGmuAllSgpa = "SELECT usn, sem, sgpa FROM {$gmuPrefix}ad_student_approved WHERE usn IN ($gmuPlaceholders) ORDER BY usn, sem";
            $stmtGmuAll = $remoteDB->prepare($sqlGmuAllSgpa);
            $stmtGmuAll->execute($gmuUsns);
            
            while ($row = $stmtGmuAll->fetch(PDO::FETCH_ASSOC)) {
                $usnKey = $row['usn'];
                if (!isset($allSemesterSgpa[$usnKey])) {
                    $allSemesterSgpa[$usnKey] = array_fill(1, 8, null);
                }
                $allSemesterSgpa[$usnKey][$row['sem']] = $row['sgpa'];
                $allSemesterSgpa[strtoupper($usnKey)] = &$allSemesterSgpa[$usnKey];
            }
        }
        
        // GMIT: Fetch from student_sem_sgpa
        if (!empty($gmitStudents)) {
            $gmitUsns = array_column($gmitStudents, 'usn');
            $gmitPlaceholders = implode(',', array_fill(0, count($gmitUsns), '?'));
            
            $sqlGmitAllSgpa = "SELECT student_id, semester, sgpa FROM student_sem_sgpa WHERE institution = ? AND student_id IN ($gmitPlaceholders) ORDER BY student_id, semester";
            $stmtGmitAll = $localDB->prepare($sqlGmitAllSgpa);
            $stmtGmitAll->execute(array_merge([INSTITUTION_GMIT], $gmitUsns));
            
            while ($row = $stmtGmitAll->fetch(PDO::FETCH_ASSOC)) {
                $sidKey = $row['student_id'];
                if (!isset($allSemesterSgpa[$sidKey])) {
                    $allSemesterSgpa[$sidKey] = array_fill(1, 8, null);
                }
                $allSemesterSgpa[$sidKey][$row['semester']] = $row['sgpa'];
                $allSemesterSgpa[strtoupper($sidKey)] = &$allSemesterSgpa[$sidKey];
            }
        }
        
        foreach ($applications as &$app) {
            $sid = $app['student_id'];
            $u = $userMap[$sid] ?? ($userMap[strtoupper($sid)] ?? ($userMap[strtolower($sid)] ?? null));
            
            if ($u) {
                $app['student_name'] = !empty($u['full_name']) ? $u['full_name'] : $sid;
                $app['usn'] = $u['usn'];
                $app['student_id'] = $u['usn'];
                $app['email'] = $u['email'];
                $app['phone'] = $u['phone'];
                $app['course'] = !empty($u['course']) ? $u['course'] : 'N/A';
                $app['branch'] = !empty($u['branch']) ? $u['branch'] : 'N/A';
                $app['institution'] = $u['institution'];
                
                // Prioritize frozen snapshot fields if populated
                if (isset($app['applied_semester']) && $app['applied_semester'] !== null && (int)$app['applied_semester'] > 0) {
                    $app['sem'] = $app['applied_semester'];
                    $app['cgpa'] = $app['applied_sgpa'] ?? 0.00;
                } else {
                    // For GMIT students, use student_sem_sgpa data
                    if ($u['institution'] === INSTITUTION_GMIT && isset($gmitSgpaMap[$sid])) {
                        $app['cgpa'] = $gmitSgpaMap[$sid]['sgpa'];
                        $app['sem'] = $gmitSgpaMap[$sid]['semester'];
                    } else {
                        $app['cgpa'] = $u['cgpa'] ?? 0.00;
                        $app['sem'] = !empty($u['sem']) ? $u['sem'] : 1;
                    }
                }
                
                // Add all semester SGPAs
                $app['sem_sgpa_all'] = $allSemesterSgpa[$sid] ?? ($allSemesterSgpa[strtoupper($sid)] ?? array_fill(1, 8, null));
            } else {
                // Smart fallback if user record is missing in ERP tables
                $app['student_name'] = $sid;
                $app['usn'] = $sid;
                $app['student_id'] = $sid;
                $app['institution'] = (strpos(strtoupper($sid), '4GM') === 0 || strpos(strtoupper($sid), 'GMIT') === 0) ? INSTITUTION_GMIT : INSTITUTION_GMU;
                $app['branch'] = 'N/A';
                $app['course'] = 'N/A';
                $app['sem'] = $app['applied_semester'] ?? 1;
                $app['cgpa'] = $app['applied_sgpa'] ?? 0.00;
                $app['sem_sgpa_all'] = array_fill(1, 8, null);
            }
        }
        
        return $applications;
    }
}
