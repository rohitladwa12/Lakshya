<?php
/**
 * Internship Application Model
 */

require_once __DIR__ . '/Model.php';

class InternshipApplication extends Model {
    protected $table = 'internship_applications';
    protected $timestamps = false;
    protected $fillable = [
        'internship_id', 'student_id', 'status', 'applied_at', 'resume_path'
    ];

    /**
     * Apply for internship
     */
    public function apply($internshipId, $studentId, $resumePath) {
        if ($this->hasApplied($internshipId, $studentId)) {
            return ['success' => false, 'message' => 'Already applied'];
        }
        
        $id = $this->create([
            'internship_id' => $internshipId,
            'student_id' => $studentId,
            'status' => 'Applied',
            'applied_at' => date('Y-m-d H:i:s'),
            'resume_path' => $resumePath
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
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        
        // Using User model logic might be cleaner but here we need bulk fetch.
        // Copied logic from JobApplication.php
        $remoteDB = getDB('gmu');
        $localDB = getDB(); // For student_sem_sgpa
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        
        $sqlUsers = "SELECT u.USER_NAME as usn, u.NAME as full_name, u.USER_NAME as email, u.MOBILE_NO as phone,
                            sp.course, sp.sgpa as cgpa, sp.sem, sp.institution, sp.branch
                     FROM (
                        SELECT NAME, USER_NAME, MOBILE_NO, '" . INSTITUTION_GMU . "' as institution FROM {$gmuPrefix}users WHERE USER_NAME IN ($placeholders)
                        UNION ALL
                        SELECT COALESCE(d.name, u.NAME) as NAME, u.USER_NAME, u.MOBILE_NO, '" . INSTITUTION_GMIT . "' as institution 
                        FROM {$gmitPrefix}users u
                        LEFT JOIN {$gmitPrefix}ad_student_details d ON u.USER_NAME = d.student_id
                        WHERE u.USER_NAME IN ($placeholders)
                     ) u
                     LEFT JOIN (
                        SELECT usn, course, discipline as branch, sgpa, sem, usn as student_id, '" . INSTITUTION_GMU . "' as institution 
                        FROM (
                            SELECT a.* FROM {$gmuPrefix}ad_student_approved a
                            JOIN (SELECT usn, MAX(sem) as max_sem FROM {$gmuPrefix}ad_student_approved GROUP BY usn) b 
                            ON a.usn = b.usn AND a.sem = b.max_sem
                        ) as gmu_max_sem
                        UNION ALL
                        SELECT student_id as usn, course, discipline as branch, 0.0 as sgpa, 0 as sem, student_id, '" . INSTITUTION_GMIT . "' as institution FROM {$gmitPrefix}ad_student_details
                     ) sp ON u.USER_NAME = sp.student_id AND u.institution = sp.institution";
        
        // Fix: UNION params need duplication
        $params = array_merge($studentIds, $studentIds);
        
        $stmt = $remoteDB->prepare($sqlUsers);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $userMap = [];
        foreach ($users as $u) $userMap[$u['usn']] = $u;
        
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
            }
        }
        
        // Fetch ALL semester SGPAs for both GMU and GMIT
        $allSemesterSgpa = []; // student_id => [1 => sgpa, 2 => sgpa, ...]
        
        // GMU: Fetch from ad_student_approved
        $gmuStudents = array_filter($users, function($u) { return $u['institution'] === INSTITUTION_GMU; });
        if (!empty($gmuStudents)) {
            $gmuUsns = array_column($gmuStudents, 'usn');
            $gmuPlaceholders = implode(',', array_fill(0, count($gmuUsns), '?'));
            
            $sqlGmuAllSgpa = "SELECT usn, sem, sgpa FROM {$gmuPrefix}ad_student_approved WHERE usn IN ($gmuPlaceholders) ORDER BY usn, sem";
            $stmtGmuAll = $remoteDB->prepare($sqlGmuAllSgpa);
            $stmtGmuAll->execute($gmuUsns);
            
            while ($row = $stmtGmuAll->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($allSemesterSgpa[$row['usn']])) {
                    $allSemesterSgpa[$row['usn']] = array_fill(1, 8, null);
                }
                $allSemesterSgpa[$row['usn']][$row['sem']] = $row['sgpa'];
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
                if (!isset($allSemesterSgpa[$row['student_id']])) {
                    $allSemesterSgpa[$row['student_id']] = array_fill(1, 8, null);
                }
                $allSemesterSgpa[$row['student_id']][$row['semester']] = $row['sgpa'];
            }
        }
        
        foreach ($applications as &$app) {
            $sid = $app['student_id'];
            if (isset($userMap[$sid])) {
                $u = $userMap[$sid];
                $app['student_name'] = $u['full_name'];
                $app['usn'] = $u['usn'];
                $app['email'] = $u['email'];
                $app['phone'] = $u['phone'];
                $app['course'] = $u['course'];
                $app['branch'] = $u['branch'] ?? 'N/A';
                $app['institution'] = $u['institution'];
                
                // For GMIT students, use student_sem_sgpa data
                if ($u['institution'] === INSTITUTION_GMIT && isset($gmitSgpaMap[$sid])) {
                    $app['cgpa'] = $gmitSgpaMap[$sid]['sgpa'];
                    $app['sem'] = $gmitSgpaMap[$sid]['semester'];
                } else {
                    $app['cgpa'] = $u['cgpa'];
                    $app['sem'] = $u['sem'] ?? 0;
                }
                
                // Add all semester SGPAs
                $app['sem_sgpa_all'] = $allSemesterSgpa[$sid] ?? array_fill(1, 8, null);
            } else {
                $app['student_name'] = 'Unknown';
                $app['institution'] = 'N/A';
                $app['branch'] = 'N/A';
                $app['sem_sgpa_all'] = array_fill(1, 8, null);
            }
        }
        
        return $applications;
    }
}
