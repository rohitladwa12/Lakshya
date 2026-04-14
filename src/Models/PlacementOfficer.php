<?php
/**
 * PlacementOfficer Model
 * Handles logic specific to the placement officer role
 */

require_once __DIR__ . '/Model.php';

class PlacementOfficer extends Model {
    protected $table = 'users'; // Uses users table but with placement_officer role filter
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Active Jobs
        $sql = "SELECT COUNT(*) as count FROM job_postings WHERE status = 'Active' AND application_deadline >= CURDATE()";
        $stats['active_jobs'] = $this->queryOne($sql)['count'];
        
        // Total Applications
        $sql = "SELECT COUNT(*) as count FROM job_applications";
        $stats['total_applications'] = $this->queryOne($sql)['count'];

        // Pending Applications (Applied status)
        $sql = "SELECT COUNT(*) as count FROM job_applications WHERE status = 'Applied'";
        $stats['pending_applications'] = $this->queryOne($sql)['count'];
        
        // Placed Students (Selected status)
        $sql = "SELECT COUNT(DISTINCT student_id) as count FROM job_applications WHERE status = 'Selected'";
        $stats['placed_students'] = $this->queryOne($sql)['count'];
        
        // Total Students (Updated for dual legacy schema - Fetching from REMOTE)
        try {
            $gmuDB = getDB('gmu');
            $gmuCount = $gmuDB->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE'")->fetchColumn();
        } catch (Exception $e) {
            $gmuCount = 0;
            logMessage("Error fetching GMU stats: " . $e->getMessage(), 'ERROR');
        }

        try {
            $gmitDB = getDB('gmit');
            $gmitCount = $gmitDB->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE'")->fetchColumn();
        } catch (Exception $e) {
            $gmitCount = 0;
            logMessage("Error fetching GMIT stats: " . $e->getMessage(), 'ERROR');
        }

        $stats['total_students'] = $gmuCount + $gmitCount;

        // Total Companies
        $sql = "SELECT COUNT(*) as count FROM companies WHERE is_active = 1";
        $stats['total_companies'] = $this->queryOne($sql)['count'];
        
        return $stats;
    }
    
    /**
     * Get recent applications
     */
    public function getRecentApplications($limit = 5) {
        // 1. Fetch recent applications and job details (LOCAL DB only)
        $sql = "SELECT ja.*, jp.title as job_title, c.name as company_name
                FROM job_applications ja
                JOIN job_postings jp ON ja.job_id = jp.id
                JOIN companies c ON jp.company_id = c.id
                ORDER BY ja.applied_at DESC
                LIMIT ?";
        
        $applications = $this->query($sql, [(int)$limit]);
        
        // 2. Enrich with student details (REMOTE DBs)
        if (empty($applications)) return [];

        $userModel = new User();
        
        foreach ($applications as &$app) {
            // We need to find the student. We don't know the institution from job_applications 
            // unless we start storing it. But User::find can help.
            // Using a suboptimal but functional approach: try to find user by ID.
            
            // To be efficient, we really should store institution in job_applications.
            // For now, we'll brute force check via User model which handles the switch.
            $student = $userModel->find($app['student_id']);
            
            if ($student) {
                $app['student_name'] = $student['full_name'];
            } else {
                $app['student_name'] = 'Unknown Student (' . $app['student_id'] . ')';
            }
        }
        
        return $applications;
    }
    
    /**
     * Get recent jobs
     */
    public function getRecentJobs($limit = 5) {
        $sql = "SELECT jp.*, c.name as company_name
                FROM job_postings jp
                JOIN companies c ON jp.company_id = c.id
                ORDER BY jp.posted_date DESC
                LIMIT ?";
        
        return $this->query($sql, [(int)$limit]);
    }
    /**
     * Get placement distribution by department
     */
    public function getDepartmentWiseStats() {
        // 1. Get all selected student IDs from local DB
        $sql = "SELECT DISTINCT student_id FROM job_applications WHERE status = 'Selected'";
        $selectedIds = $this->query($sql, [], PDO::FETCH_COLUMN);     
        
        if (empty($selectedIds)) return [];

        $stats = [];
        $userModel = new User();
        
        // 2. Resolve department for each student
        // Note: This is N+1 query problem potential, but given placement numbers usually aren't massive, it's safer than broken Cross-DB joins.
        // Optimization: We could batch query if we knew which ID belongs to which DB.
        
        foreach ($selectedIds as $id) {
            $student = $userModel->find($id);
            if ($student && isset($student['institution'])) {
                // We need to get the department usually from ad_student_details
                // The User::find returns basic info. We might need a helper to get academic details.
                // Or we can rely on what we can fetch.
                
                // Let's do a direct lookup since User model doesn't return department/discipline
                $dept = 'Unknown';
                
                try {
                    if ($student['institution'] === INSTITUTION_GMU) {
                        $db = getDB('gmu');
                        $stmt = $db->prepare("SELECT discipline FROM ad_student_approved WHERE usn = ? LIMIT 1");
                        $stmt->execute([$student['username']]); // user_name is usually USN
                        $dept = $stmt->fetchColumn() ?: 'Unknown';
                    } else {
                        $db = getDB('gmit');
                         // GMIT uses enquiry_no or student_id
                        $stmt = $db->prepare("SELECT discipline FROM ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1");
                        $stmt->execute([$id, $student['username']]);
                        $dept = $stmt->fetchColumn() ?: 'Unknown';
                    }
                } catch(Exception $e) {
                    // ignore error
                }
                
                if (!isset($stats[$dept])) {
                    $stats[$dept] = 0;
                }
                $stats[$dept]++;
            }
        }
        
        // Format for chart/view: [['department' => 'CSE', 'count' => 10], ...]
        $result = [];
        foreach ($stats as $dept => $count) {
            $result[] = ['department' => $dept, 'count' => $count];
        }
        
        return $result;
    }

    /**
     * Get application trends over the last 6 months
     */
    public function getApplicationTrends() {
        $sql = "SELECT DATE_FORMAT(applied_at, '%b %Y') as month, COUNT(*) as count,
                       MIN(applied_at) as sort_date
                FROM job_applications
                WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY month
                ORDER BY sort_date ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Get top companies by placement
     */
    public function getTopCompaniesByPlacement($limit = 5) {
        $sql = "SELECT c.name, COUNT(*) as count
                FROM job_applications ja
                JOIN job_postings jp ON ja.job_id = jp.id
                JOIN companies c ON jp.company_id = c.id
                WHERE ja.status = 'Selected'
                GROUP BY c.id
                ORDER BY count DESC
                LIMIT ?";
        return $this->query($sql, [(int)$limit]);
    }
    /**
     * Get detailed AI Interview performance reports
     */
    public function getAIInterviewReports() {
        $sql = "SELECT ais.* FROM ai_interview_sessions ais
                WHERE ais.completed_at IS NOT NULL
                ORDER BY ais.completed_at DESC";
        $reports = $this->query($sql);
        
        $userModel = new User();
        
        foreach ($reports as &$report) {
            $studentId = $report['student_id'];
            // Institution is stored in ais table
            $institution = $report['institution'] ?? null; 
            
            // Defaults
            $report['full_name'] = 'Unknown';
            $report['department'] = 'N/A';
            $report['course'] = 'N/A';
            $report['cgpa'] = 0.0;

            if ($studentId && $institution) {
                 $student = $userModel->find($studentId, $institution);
                 
                 if ($student) {
                     $report['full_name'] = $student['full_name'];
                     $usn = $student['username']; // Use USN/Enquiry for academic details lookup
                     
                     // Helper or direct query for academic details
                     try {
                         if ($institution === INSTITUTION_GMU) {
                             $db = getDB('gmu');
                             $stmt = $db->prepare("SELECT discipline, course, sgpa FROM ad_student_approved WHERE usn = ? LIMIT 1");
                             $stmt->execute([$usn]);
                             $details = $stmt->fetch();
                             if ($details) {
                                 $report['department'] = $details['discipline'] ?? 'N/A';
                                 $report['course'] = $details['course'] ?? 'N/A';
                                 $report['cgpa'] = $details['sgpa'] ?? 0.0;
                             }
                         } else {
                             $db = getDB('gmit');
                             $stmt = $db->prepare("SELECT discipline, course FROM ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1");
                             $stmt->execute([$studentId, $usn]);
                             $details = $stmt->fetch();
                             if ($details) {
                                 $report['department'] = $details['discipline'] ?? 'N/A';
                                 $report['course'] = $details['course'] ?? 'N/A';
                             }
                             // GMIT: sgpa from student_sem_sgpa (current sem)
                             try {
                                 $stmtSgpa = $this->db->prepare("SELECT sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                                 $stmtSgpa->execute([$usn, INSTITUTION_GMIT]);
                                 $sgpaRow = $stmtSgpa->fetch();
                                 if ($sgpaRow) {
                                     $report['cgpa'] = (float) $sgpaRow['sgpa'];
                                 }
                             } catch (Exception $e) { /* ignore */ }
                         }
                     } catch (Exception $e) { /* ignore */ }
                 }
            }
        }
        
        return $reports;
    }

    /**
     * Get reports for Mock Technical AI Interviews
     */
    public function getMockAIInterviewReports() {
        $sql = "SELECT m.* FROM mock_ai_interview_sessions m
                WHERE m.status = 'completed'
                ORDER BY m.completed_at DESC";
        $sessions = $this->query($sql);
        
        $userModel = new User();
        
        foreach ($sessions as &$session) {
             $studentId = $session['student_id'];
             $institution = $session['institution'] ?? null;
             
             $session['full_name'] = 'Unknown';
             $session['usn'] = 'N/A';
             $session['academic_year'] = 'N/A';
             $session['branch'] = 'N/A';
             $session['current_sem'] = 0;
             
             if ($studentId) {
                 // Use institution from session if available, else try to find
                 $student = $userModel->find($studentId, $institution);
                 if ($student) {
                     $session['full_name'] = $student['full_name'];
                     $session['usn'] = $student['username'];
                     $usn = $student['username'];
                     $institution = $student['institution'] ?? $institution;

                     try {
                        if ($institution === INSTITUTION_GMU) {
                            $dbGmu = getDB('gmu');
                            if ($dbGmu) {
                                $stmt = $dbGmu->prepare("SELECT academic_year, discipline, sem FROM ad_student_approved WHERE usn = ? LIMIT 1");
                                $stmt->execute([$usn]);
                                $details = $stmt->fetch();
                                if ($details) {
                                    $session['branch'] = $details['discipline'] ?? 'N/A';
                                    $session['academic_year'] = $details['academic_year'] ?? 'N/A';
                                    $session['current_sem'] = $details['sem'] ?? 0;
                                }
                            }
                        } else {
                            $dbGmit = getDB('gmit');
                            if ($dbGmit) {
                                $stmt = $dbGmit->prepare("SELECT academic_year, discipline FROM ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1");
                                $stmt->execute([$studentId, $usn]);
                                $details = $stmt->fetch();
                                if ($details) {
                                    $session['branch'] = $details['discipline'] ?? 'N/A';
                                    $session['academic_year'] = $details['academic_year'] ?? 'N/A';
                                }
                            }
                            // GMIT: current sem and sgpa from student_sem_sgpa (local)
                            try {
                                $stmtLocal = $this->db->prepare("SELECT semester, sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                                $stmtLocal->execute([$usn, INSTITUTION_GMIT]);
                                $sgpaRow = $stmtLocal->fetch();
                                if ($sgpaRow) {
                                    $session['current_sem'] = $sgpaRow['semester'];
                                }
                            } catch (Exception $e) { /* ignore */ }
                        }
                    } catch (Exception $e) { /* ignore */ }
                 }
             }
        }
        return $sessions;
    }

    /**
     * Get Unified AI Assessment Reports
     * @param array $filters Optional ['department' => string, 'institution' => string] for coordinator scope
     */
    public function getUnifiedAIReports($filters = []) {
        $sql = "SELECT u.* FROM unified_ai_assessments u";
        $assessments = $this->query($sql);
        
        // Fetch verified portfolio items and map them as "assessments" (Rounds)
        try {
            // Check if is_verified column exists
            $stmtCol = $this->db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_portfolio' AND COLUMN_NAME = 'is_verified'");
            $stmtCol->execute();
            if ((int)$stmtCol->fetchColumn() > 0) {
                $portfolioSql = "SELECT id, student_id, institution, category, title, 
                                 'completed' as status, created_at as started_at, 100 as score
                                 FROM student_portfolio 
                                 WHERE is_verified = 1 AND category IN ('Skill', 'Project')";
                $portfolioItems = $this->db->query($portfolioSql)->fetchAll();
                foreach ($portfolioItems as $item) {
                    $assessments[] = [
                        'id' => 'p' . $item['id'],
                        'student_id' => $item['student_id'],
                        'institution' => $item['institution'],
                        'assessment_type' => $item['category'] . ' Verify',
                        'company_name' => $item['title'],
                        'score' => $item['score'],
                        'status' => $item['status'],
                        'started_at' => $item['started_at'],
                        'usn' => $item['student_id'], // USN is used as student_id in portfolio
                        'is_portfolio' => true
                    ];
                }
            }

            // Include Mock AI Sessions (Technical/HR round results stored in sessions table)
            $mockWhere = "WHERE overall_score IS NOT NULL";
            $mockParams = [];
            if (!empty($filters['usn']) || !empty($filters['student_id'])) {
                $targetId = $filters['usn'] ?? $filters['student_id'];
                $mockWhere .= " AND student_id = ?";
                $mockParams[] = $targetId;
            }
            
            $mockSql = "SELECT id, student_id, institution, role_name as company_name, 
                               'Mock AI' as assessment_type, overall_score as score, 
                               'completed' as status, started_at
                        FROM mock_ai_interview_sessions 
                        $mockWhere";
            $stmtMock = $this->db->prepare($mockSql);
            $stmtMock->execute($mockParams);
            $mockItems = $stmtMock->fetchAll();
            
            foreach ($mockItems as $item) {
                // Deduplicate: check if this session already exists in $assessments (e.g. from unified table)
                $isDuplicate = false;
                foreach ($assessments as $existing) {
                    $timeDiff = abs(strtotime($existing['started_at'] ?? '0') - strtotime($item['started_at'] ?? '0'));
                    
                    if ($existing['student_id'] == $item['student_id']) {
                        // Match if same start time or if this is a completion record for the same session (within 4 hours)
                        if ($timeDiff < 60 || ($timeDiff < 14400 && $item['assessment_type'] === 'Mock AI')) {
                            $isDuplicate = true;
                            break;
                        }
                    }
                }
                if ($isDuplicate) continue;

                $assessments[] = [
                    'id' => 'm' . $item['id'],
                    'student_id' => $item['student_id'],
                    'institution' => $item['institution'],
                    'assessment_type' => $item['assessment_type'], // Keep "Mock AI" or specific type
                    'company_name' => $item['company_name'],
                    'score' => $item['score'],
                    'status' => $item['status'],
                    'started_at' => $item['started_at'],
                    'usn' => is_numeric($item['student_id']) ? null : $item['student_id'], 
                    'is_mock_session' => true
                ];
            }
        } catch (Exception $e) { 
            logMessage("Error including portfolio/mock rounds in AI reports: " . $e->getMessage(), 'DEBUG');
        }

        // 3. Resolve student details and USN for identifying/filtering
        $userModel = new User();
        
        foreach ($assessments as &$assessment) {
            $studentId = $assessment['student_id'];
            $institution = $assessment['institution'] ?? null;
            
            // Skip if USN and name already set
            if (!empty($assessment['usn']) && !empty($assessment['full_name']) && $assessment['full_name'] !== 'Unknown') continue;

            // Resolve details via lookup
            if ($studentId) {
                // If institution is missing (older records), try to find the student in both DBs
                $student = $userModel->find($studentId, $institution);
                if ($student) {
                    $assessment['full_name'] = $student['full_name'];
                    $assessment['student_name'] = $student['full_name']; // For view compatibility
                    $assessment['usn'] = $student['username'];
                    $assessment['institution'] = $student['institution']; // Backfill if missing
                    $usn = $student['username'];
                    $institution = $student['institution'];

                    try {
                        $dbGmu = getDB('gmu');
                        $dbGmit = getDB('gmit');
                        if ($institution === INSTITUTION_GMU && $dbGmu) {
                            $stmt = $dbGmu->prepare("SELECT discipline, sem FROM ad_student_approved WHERE usn = ? LIMIT 1");
                            $stmt->execute([$usn]);
                            $d = $stmt->fetch();
                            if ($d) {
                                $assessment['branch'] = $d['discipline'] ?? 'N/A';
                                $assessment['current_sem'] = $assessment['current_sem'] ?? ($d['sem'] ?? '-');
                            }
                        } elseif ($institution === INSTITUTION_GMIT && $dbGmit) {
                            $stmt = $dbGmit->prepare("SELECT discipline FROM ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1");
                            $stmt->execute([$studentId, $usn]);
                            $d = $stmt->fetch();
                            if ($d) {
                                $assessment['branch'] = $d['discipline'] ?? 'N/A';
                            }
                            // GMIT: current sem and sgpa from student_sem_sgpa (local)
                            try {
                                $stmtLocal = $this->db->prepare("SELECT semester, sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                                $stmtLocal->execute([$usn, INSTITUTION_GMIT]);
                                $sgpaRow = $stmtLocal->fetch();
                                if ($sgpaRow) {
                                    $assessment['current_sem'] = $sgpaRow['semester'];
                                }
                            } catch (Exception $e) { /* ignore */ }
                        }
                    } catch (Exception $e) { /* ignore */ }
                }
            }
        }
        unset($assessment);
        
        // Coordinator: filter by department or USN
        if (!empty($filters['usn']) || !empty($filters['student_id']) || !empty($filters['department'])) {
            $targetUsn = $filters['usn'] ?? ($filters['student_id'] ?? null);
            $deptFilters = !empty($filters['department']) ? getCoordinatorDisciplineFilters($filters['department']) : [];
            
            $assessments = array_values(array_filter($assessments, function ($a) use ($filters, $deptFilters, $targetUsn) {
                // Individual Student Filter
                if ($targetUsn) {
                    $matchUsn = strcasecmp(($a['usn'] ?? ''), $targetUsn) === 0 || strcasecmp(($a['student_id'] ?? ''), $targetUsn) === 0;
                    if (!$matchUsn) return false;
                }

                // Department Filter (for bulk reports)
                if (!empty($filters['department'])) {
                    $branch = trim($a['branch'] ?? '');
                    if (!in_array($branch, $deptFilters, true)) {
                        return false;
                    }
                }

                // Institution Filter
                if (!empty($filters['institution']) && ($a['institution'] ?? '') !== $filters['institution']) {
                    return false;
                }

                // Semester Filter
                if (!empty($filters['semesters']) && is_array($filters['semesters'])) {
                    $curSem = isset($a['current_sem']) ? (int) $a['current_sem'] : 0;
                    return in_array($curSem, $filters['semesters'], true);
                }
                return true;
            }));
        }
        
        // Sort by date descending
        usort($assessments, function($a, $b) {
            $t1 = strtotime($a['started_at'] ?? 'now');
            $t2 = strtotime($b['started_at'] ?? 'now');
            return $t2 - $t1;
        });
        
        return $assessments;
    }
}
