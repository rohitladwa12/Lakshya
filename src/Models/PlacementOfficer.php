<?php
/**
 * PlacementOfficer Model
 * Handles logic specific to the placement officer role
 */

require_once __DIR__ . '/Model.php';

class PlacementOfficer extends Model {
    protected $table = 'users'; 
    protected $remoteDB;
    
    public function __construct() {
        parent::__construct();
        try {
            $this->remoteDB = getDB('gmu');
        } catch (Exception $e) {
            $this->remoteDB = $this->db; // Fallback to default
        }
    }
    
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
     * Get distinct disciplines (branches) for filtering
     */
    public function getDisciplines() {
        if (!$this->remoteDB) return [];
        try {
            $gmuPrefix = DB_GMU_PREFIX;
            $gmitPrefix = DB_GMIT_PREFIX;
            
            $sql = "(SELECT DISTINCT discipline FROM {$gmuPrefix}ad_student_approved WHERE discipline IS NOT NULL AND discipline != '')
                    UNION
                    (SELECT DISTINCT discipline FROM {$gmitPrefix}ad_student_details WHERE discipline IS NOT NULL AND discipline != '')
                    ORDER BY discipline ASC";
            
            $stmt = $this->remoteDB->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($results)) {
                // Try branch if discipline is empty
                $sql = "(SELECT DISTINCT branch as discipline FROM {$gmuPrefix}ad_student_approved WHERE branch IS NOT NULL AND branch != '')
                        UNION
                        (SELECT DISTINCT branch as discipline FROM {$gmitPrefix}ad_student_details WHERE branch IS NOT NULL AND branch != '')
                        ORDER BY discipline ASC";
                $stmt = $this->remoteDB->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            return array_filter($results);
        } catch (Exception $e) {
            error_log("Error fetching disciplines: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all USNs/Student IDs belonging to a specific discipline
     */
    public function getUsnsByDiscipline($discipline) {
        $usns = [];
        if (!$this->remoteDB) return [];
        try {
            $gmuPrefix = DB_GMU_PREFIX;
            $gmitPrefix = DB_GMIT_PREFIX;
            
            // Try both discipline and branch columns for robustness
            $sql = "(SELECT usn FROM {$gmuPrefix}ad_student_approved WHERE discipline = ? OR branch = ?)
                    UNION
                    (SELECT student_id as usn FROM {$gmitPrefix}ad_student_details WHERE discipline = ? OR branch = ?)
                    UNION
                    (SELECT enquiry_no as usn FROM {$gmitPrefix}ad_student_details WHERE discipline = ? OR branch = ?)";
            
            $stmt = $this->remoteDB->prepare($sql);
            $stmt->execute([$discipline, $discipline, $discipline, $discipline, $discipline, $discipline]);
            $usns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error fetching USNs by discipline: " . $e->getMessage());
        }
        return array_filter($usns);
    }

    /**
     * Get USNs/IDs by student name search across databases
     */
    public function getUsnsByName($name) {
        if (!$this->remoteDB) return [];
        try {
            $gmuPrefix = DB_GMU_PREFIX;
            $gmitPrefix = DB_GMIT_PREFIX;
            $term = "%$name%";
            
            $sql = "(SELECT usn FROM {$gmuPrefix}ad_student_approved WHERE name LIKE ?)
                    UNION
                    (SELECT student_id as usn FROM {$gmitPrefix}ad_student_details WHERE name LIKE ?)
                    UNION
                    (SELECT enquiry_no as usn FROM {$gmitPrefix}ad_student_details WHERE name LIKE ?)";
            
            $stmt = $this->remoteDB->prepare($sql);
            $stmt->execute([$term, $term, $term]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
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
     * Get recent placements (Selected students) - PAGED for Stats tab
     */
    public function getRecentPlacementsPaged($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $countSql = "SELECT COUNT(*) FROM job_applications WHERE status = 'Selected'";
        $total = $this->db->query($countSql)->fetchColumn();
        
        $total_pages = ceil($total / $limit);
        if ($total_pages > 0 && $page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $limit;
        }

        $sql = "SELECT ja.*, jp.title as job_title, c.name as company_name 
                FROM job_applications ja
                JOIN job_postings jp ON ja.job_id = jp.id
                JOIN companies c ON jp.company_id = c.id
                WHERE ja.status = 'Selected'
                ORDER BY ja.applied_at DESC
                LIMIT $offset, $limit";
        $placements = $this->query($sql);
        
        $userModel = new User();
        foreach ($placements as &$p) {
            $student = $userModel->find($p['student_id']);
            $p['student_name'] = $student ? ($student['full_name'] ?? 'Unknown') : 'Unknown';
            $p['usn'] = $student ? ($student['username'] ?? $p['student_id']) : ($p['student_id'] ?: '');
            $p['discipline'] = $student ? ($student['DISCIPLINE'] ?? '-') : '-';
        }

        return [
            'data' => $placements,
            'total' => $total,
            'page' => $page,
            'total_pages' => $total_pages
        ];
    }

    /**
     * Get all portfolio items (PAGED & FILTERED)
     */
    public function getAllPortfolioItemsPaged($filters = [], $page = 1, $limit = 15) {
        $offset = ($page - 1) * $limit;
        
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['institution'])) {
            $where[] = "institution = ?";
            $params[] = $filters['institution'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = "is_verified = ?";
            $params[] = (int)$filters['status'];
        }

        if (!empty($filters['discipline'])) {
            $usns = $this->getUsnsByDiscipline($filters['discipline']);
            if (!empty($usns)) {
                $placeholders = implode(',', array_fill(0, count($usns), '?'));
                $where[] = "student_id IN ($placeholders)";
                $params = array_merge($params, $usns);
            } else {
                $where[] = "1=0"; // No students found in this branch
            }
        }

        if (!empty($filters['search'])) {
            $searchTerm = "%{$filters['search']}%";
            $subWhere = ["student_id LIKE ?", "title LIKE ?", "description LIKE ?"];
            $subParams = [$searchTerm, $searchTerm, $searchTerm];
            
            // Search by student name
            $nameUsns = $this->getUsnsByName($filters['search']);
            if (!empty($nameUsns)) {
                $ph = implode(',', array_fill(0, count($nameUsns), '?'));
                $subWhere[] = "student_id IN ($ph)";
                $subParams = array_merge($subParams, $nameUsns);
            }
            
            $where[] = "(" . implode(" OR ", $subWhere) . ")";
            $params = array_merge($params, $subParams);
        }

        $whereStr = implode(" AND ", $where);
        
        $countSql = "SELECT COUNT(*) FROM student_portfolio WHERE $whereStr";
        try {
            $stmtCount = $this->db->prepare($countSql);
            $stmtCount->execute($params);
            $total = $stmtCount->fetchColumn();
        } catch (Exception $e) {
            error_log("Error counting portfolio items: " . $e->getMessage());
            $total = 0;
        }
        
        $total_pages = ceil($total / $limit);
        if ($total_pages > 0 && $page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $limit;
        }

        $sql = "SELECT id, student_id, institution, category, title, sub_title, description, link, created_at, is_verified 
                FROM student_portfolio 
                WHERE $whereStr
                ORDER BY created_at DESC 
                LIMIT $offset, $limit";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching portfolio items: " . $e->getMessage());
            $items = [];
        }
        
        $userModel = new User();
        foreach ($items as &$item) {
            $student = $userModel->find($item['student_id'], $item['institution']);
            $item['student_name'] = $student ? ($student['full_name'] ?? 'Unknown') : 'Unknown';
            $item['usn'] = $student ? ($student['username'] ?? $item['student_id']) : ($item['student_id'] ?: '');
            $item['discipline'] = $student ? ($student['DISCIPLINE'] ?? '-') : '-';
        }

        // Summary stats for the filtered context
        $summarySql = "SELECT 
                        COUNT(*) as total, 
                        SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified
                      FROM student_portfolio WHERE $whereStr";
        $stmtSum = $this->db->prepare($summarySql);
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);
        
        return [
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'total_pages' => $total_pages,
            'summary' => $summary
        ];
    }

    /**
     * Get Unified AI Assessment Reports (PAGED)
     */
    public function getUnifiedAIReportsPaged($filters = [], $page = 1, $limit = 15) {
        $offset = ($page - 1) * $limit;

        // Due to the complex UNION of local tables (unified_ai_assessments, student_portfolio, mock_sessions),
        // we'll fetch the combined list first, then resolve names for the slice.
        // Optimization: In a real high-scale system, we'd unified these into a single table.
        // For now, we'll keep the logic but wrap it in a paged structure.

        $assessments = [];

        // 1. Unified Assessments Table (Aptitude, Tech, HR, etc)
        // Exclude Skill and Project related assessments as requested
        $stmtU = $this->db->query("SELECT *, 'assessment' as source FROM unified_ai_assessments 
                                   WHERE assessment_type NOT LIKE '%Skill%' 
                                   AND assessment_type NOT LIKE '%Project%'");
        while ($r = $stmtU->fetch(PDO::FETCH_ASSOC)) $assessments[] = $r;

        // 3. Mock Sessions
        try {
            $mockSql = "SELECT id, student_id, institution, role_name as company_name, 'Mock AI' as assessment_type, overall_score as score, 'completed' as status, started_at, 'mock' as source FROM mock_ai_interview_sessions WHERE overall_score IS NOT NULL";
            $mockItems = $this->db->query($mockSql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($mockItems as $item) $assessments[] = $item;
        } catch (Exception $e) {}

        // Sort by date descending
        usort($assessments, function($a, $b) {
            return strtotime($b['started_at'] ?? 'now') - strtotime($a['started_at'] ?? 'now');
        });

        $total = count($assessments);
        
        $total_pages = ceil($total / $limit);
        if ($total_pages > 0 && $page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $limit;
        }
        
        $slice = array_slice($assessments, $offset, $limit);

        // Resolve names and filter by discipline for the ENTIRE list before slicing
        
        $userModel = new User();
        
        $eligibleUsns = [];
        if (!empty($filters['discipline'])) {
            $eligibleUsns = $this->getUsnsByDiscipline($filters['discipline']);
        }

        $searchUsns = [];
        if (!empty($filters['search'])) {
            $searchUsns = $this->getUsnsByName($filters['search']);
        }

        $filteredAssessments = [];
        $excludeCategories = ['Skill Verification', 'Project Defense', 'Project Device'];
        
        $instCache = [];

        foreach ($assessments as $a) {
            // 0. Pre-resolve institution if missing (Critical for filtering)
            if (empty($a['institution'])) {
                $sid = $a['student_id'];
                if (!isset($instCache[$sid])) {
                    $studentInfo = $userModel->find($sid);
                    $instCache[$sid] = $studentInfo['institution'] ?? 'UNKNOWN';
                }
                $a['institution'] = $instCache[$sid];
            }

            // 1. Filter by search (USN or Name)
            if (!empty($filters['search'])) {
                $match = false;
                if (stripos($a['student_id'] ?? '', $filters['search']) !== false) $match = true;
                if (in_array($a['student_id'], $searchUsns)) $match = true;
                if (!$match) continue;
            }
            // Filter by assessment category (check multiple possible column names)
            $cat = $a['category'] ?? $a['assessment_type'] ?? '';
            $isExcluded = false;
            foreach ($excludeCategories as $ex) {
                if (stripos($cat, $ex) !== false) {
                    $isExcluded = true;
                    break;
                }
            }
            if ($isExcluded) continue;
            
            // Filter by discipline (branch) if set
            if (!empty($eligibleUsns) && !in_array($a['student_id'], $eligibleUsns)) {
                continue;
            }

            // Filter by institution if set
            if (!empty($filters['institution']) && strtoupper((string)$a['institution']) !== strtoupper((string)$filters['institution'])) {
                continue;
            }
            
            $filteredAssessments[] = $a;
        }

        $total = count($filteredAssessments);
        
        $total_pages = ceil($total / $limit);
        if ($total_pages > 0 && $page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $limit;
        }
        
        $slice = array_slice($filteredAssessments, $offset, $limit);

        // Resolve details ONLY for the slice
        foreach ($slice as &$a) {
            $student = $userModel->find($a['student_id'], $a['institution'] ?? null);
            $a['full_name'] = $student ? ($student['full_name'] ?? 'Unknown') : 'Unknown';
            $a['usn'] = $student ? ($student['username'] ?? $a['student_id']) : ($a['student_id'] ?: '');
            $a['discipline'] = $student ? ($student['DISCIPLINE'] ?? '-') : '-';
        }

        // Summary stats
        $avgScore = 0;
        if (!empty($filteredAssessments)) {
            $scores = array_column($filteredAssessments, 'score');
            $avgScore = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
        }

        return [
            'data' => $slice,
            'total' => $total,
            'page' => $page,
            'total_pages' => $total_pages,
            'summary' => [
                'total_assessments' => count($assessments),
                'avg_score' => round($avgScore, 1),
                'filtered_count' => count(array_unique(array_column($filteredAssessments, 'student_id')))
            ]
        ];
    }

    /**
     * Update portfolio verification status
     */
    public function verifyPortfolioItem($id, $status = 1) {
        $sql = "UPDATE student_portfolio SET is_verified = ? WHERE id = ?";
        return $this->db->prepare($sql)->execute([$status, $id]);
    }

    /**
     * Get paged and filtered student list (Academic Hub)
     */
    public function getStudentsPaged($filters, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        
        // 1. Fetch eligible GMIT Student IDs from LOCAL DB (lakshya)
        $eligibleGmitUsns = [];
        try {
            $gmitSems = !empty($filters['semester']) ? [(int)$filters['semester']] : [5, 6, 7, 8];
            $semPlaceholders = implode(',', array_fill(0, count($gmitSems), '?'));
            
            $sqlGmitIds = "SELECT DISTINCT student_id FROM student_sem_sgpa 
                           WHERE institution = ? AND semester IN ($semPlaceholders) AND is_current = 1";
            $stmtGmitIds = $this->db->prepare($sqlGmitIds);
            $stmtGmitIds->execute(array_merge([INSTITUTION_GMIT], $gmitSems));
            $eligibleGmitUsns = $stmtGmitIds->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Warning: Permission denied or table missing for student_sem_sgpa: " . $e->getMessage());
            // Fallback: Continue without GMIT semester filter if denied
        }

        $gmitFilterSql = "1=0"; // Default: no GMIT students if no sem records
        $gmitParams = [];
        if (!empty($eligibleGmitUsns)) {
            $ph = implode(',', array_fill(0, count($eligibleGmitUsns), '?'));
            $gmitFilterSql = "ad.student_id IN ($ph)";
            $gmitParams = $eligibleGmitUsns;
        }

        // 2. Base combined query on REMOTE DB
        $gmuSemFilter = !empty($filters['semester']) ? "AND ad.sem = " . (int)$filters['semester'] : "AND ad.sem IN (5,6,7,8)";

        $combinedSql = "
            (SELECT ad.usn, ad.name, ad.aadhar, ad.faculty, ad.school, ad.programme, ad.course, ad.discipline, ad.year, ad.sem, ad.sgpa, ad.registered, ad.usn as student_id_map, 
                    det.sslc_percentage, det.puc_percentage, det.father_name, det.mother_name, det.address,
                    '" . INSTITUTION_GMU . "' as institution 
             FROM {$gmuPrefix}ad_student_approved ad
             LEFT JOIN {$gmuPrefix}ad_student_details det ON ad.usn = det.usn
             INNER JOIN (
                SELECT usn, MAX(SL_NO) as max_sl FROM {$gmuPrefix}ad_student_approved GROUP BY usn
             ) latest ON ad.usn = latest.usn AND ad.SL_NO = latest.max_sl
             WHERE ad.registered = 1 $gmuSemFilter
             UNION ALL
             SELECT ad.student_id as usn, ad.name, ad.aadhar, ad.college as faculty, ad.college as school, ad.programme, ad.course, ad.discipline, 0 as year, 0 as sem, 0.0 as sgpa, 1 as registered, ad.student_id as student_id_map,
                    ad.sslc_percentage, ad.puc_percentage, ad.father_name, ad.mother_name, ad.address,
                    '" . INSTITUTION_GMIT . "' as institution 
             FROM {$gmitPrefix}ad_student_details ad
             WHERE $gmitFilterSql)
        ";

        $where = ["1=1"];
        $params = $gmitParams;

        if (!empty($filters['institution'])) {
            $where[] = "institution = ?";
            $params[] = $filters['institution'];
        }

        // Specific sub-semester filter is already baked into $gmuSemFilter and $gmitFilterSql
        // But we keep it in where for safety if passed differently, though usually redundant now.

        if (!empty($filters['search'])) {
            $where[] = "(name LIKE ? OR usn LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        if (!empty($filters['discipline'])) {
            $disciplines = is_array($filters['discipline']) ? $filters['discipline'] : [$filters['discipline']];
            $placeholders = implode(',', array_fill(0, count($disciplines), '?'));
            $where[] = "discipline IN ($placeholders)";
            foreach ($disciplines as $d) $params[] = $d;
        }

        $whereStr = implode(" AND ", $where);
        
        // Count total
        $countSql = "SELECT COUNT(*) FROM ($combinedSql) as t WHERE $whereStr";
        $stmtCount = $this->remoteDB->prepare($countSql);
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();
        
        $total_pages = ceil($total / $limit);
        if ($total_pages > 0 && $page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $limit;
        }

        // Get paged results
        $dataSql = "SELECT * FROM ($combinedSql) as t WHERE $whereStr ORDER BY name ASC LIMIT $offset, $limit";
        $stmtData = $this->remoteDB->prepare($dataSql);
        $stmtData->execute($params);
        $students = $stmtData->fetchAll();

        // 3. Enrich with local data (GMIT SGPA/Sem)
        foreach ($students as &$s) {
            if ($s['institution'] === INSTITUTION_GMIT) {
                try {
                    $stmtSgpa = $this->db->prepare("SELECT semester, sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                    $stmtSgpa->execute([$s['usn'], INSTITUTION_GMIT]);
                    $row = $stmtSgpa->fetch();
                    if ($row) {
                        $s['sem'] = $row['semester'];
                        $s['sgpa'] = $row['sgpa'];
                    }
                } catch (Exception $e) {}
            }
        }

        return [
            'data' => $students,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
}
