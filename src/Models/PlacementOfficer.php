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
        $sql = "SELECT ja.*, jp.title as job_title, jp.application_mode, c.name as company_name
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
     * Backward compatibility wrapper for getUnifiedAIReports
     */
    public function getUnifiedAIReports($filters = []) {
        // Redirect to paged version with a high limit to mimic legacy behavior
        return $this->getUnifiedAIReportsPaged($filters, 1, 1000);
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
        
        $hasSpecificSem = !empty($filters['semester']);
        $hasUsnList = !empty($filters['usn_list']);
        $allowAllSems = !empty($filters['all_semesters']) || ($hasUsnList && !$hasSpecificSem);

        // 1. Fetch eligible GMIT Student IDs from LOCAL DB (lakshya)
        $eligibleGmitUsns = [];
        $gmitFilterSql = "1=0";
        $gmitParams = [];

        if ($hasUsnList && !$hasSpecificSem) {
            $gmitFilterSql = "1=1";
        } elseif ($allowAllSems) {
            $gmitFilterSql = "1=1";
        } else {
            try {
                $gmitSems = $hasSpecificSem ? [(int)$filters['semester']] : [5, 6, 7, 8];
                $semPlaceholders = implode(',', array_fill(0, count($gmitSems), '?'));
                
                $sqlGmitIds = "SELECT DISTINCT student_id FROM student_sem_sgpa 
                               WHERE institution = ? AND semester IN ($semPlaceholders) AND is_current = 1";
                $stmtGmitIds = $this->db->prepare($sqlGmitIds);
                $stmtGmitIds->execute(array_merge([INSTITUTION_GMIT], $gmitSems));
                $eligibleGmitUsns = $stmtGmitIds->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                error_log("Warning: Permission denied or table missing for student_sem_sgpa: " . $e->getMessage());
            }

            if (!empty($eligibleGmitUsns)) {
                $ph = implode(',', array_fill(0, count($eligibleGmitUsns), '?'));
                $gmitFilterSql = "ad.student_id IN ($ph)";
                $gmitParams = $eligibleGmitUsns;
            }
        }

        // 2. Base combined query on REMOTE DB
        $gmuSemFilter = "";
        if ($hasSpecificSem) {
            $gmuSemFilter = "AND ad.sem = " . (int)$filters['semester'];
        } elseif (!$allowAllSems) {
            $gmuSemFilter = "AND ad.sem IN (5,6,7,8)";
        }

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

        if (isset($filters['usn_list'])) {
            $uList = is_array($filters['usn_list']) ? $filters['usn_list'] : [$filters['usn_list']];
            $uList = array_values(array_filter($uList));
            if (!empty($uList)) {
                $placeholders = implode(',', array_fill(0, count($uList), '?'));
                $where[] = "usn IN ($placeholders)";
                foreach ($uList as $u) $params[] = $u;
            } else {
                $where[] = "1=0";
            }
        }

        if (isset($filters['exclude_usn_list'])) {
            $exList = is_array($filters['exclude_usn_list']) ? $filters['exclude_usn_list'] : [$filters['exclude_usn_list']];
            $exList = array_values(array_filter($exList));
            if (!empty($exList)) {
                $placeholders = implode(',', array_fill(0, count($exList), '?'));
                $where[] = "usn NOT IN ($placeholders)";
                foreach ($exList as $u) $params[] = $u;
            }
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

        // 3. Enrich with local data (GMIT SGPA/Sem & student_resumes email/phone)
        if (!empty($students)) {
            $studentIds = array_unique(array_column($students, 'usn'));
            $resMap = [];
            if (!empty($studentIds)) {
                $ph = implode(',', array_fill(0, count($studentIds), '?'));
                try {
                    $stmtRes = $this->db->prepare("SELECT student_id, email, phone FROM student_resumes WHERE student_id IN ($ph)");
                    $stmtRes->execute($studentIds);
                    while ($rRow = $stmtRes->fetch(PDO::FETCH_ASSOC)) {
                        $resMap[$rRow['student_id']] = $rRow;
                        $resMap[strtoupper($rRow['student_id'])] = $rRow;
                    }
                } catch (Exception $e) {}
            }

            foreach ($students as &$s) {
                $sid = $s['usn'];
                $res = $resMap[$sid] ?? ($resMap[strtoupper($sid)] ?? null);
                
                if (!empty($res['email']) && strpos($res['email'], '@') !== false) {
                    $s['email'] = $res['email'];
                } else {
                    $s['email'] = 'N/A';
                }

                if (!empty($res['phone']) && $res['phone'] !== '-') {
                    $s['mobile'] = $res['phone'];
                    $s['phone'] = $res['phone'];
                } else {
                    $s['mobile'] = 'N/A';
                    $s['phone'] = 'N/A';
                }

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
        }

        return [
            'data' => $students,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * Search and rank students by target role / skills using AI matching
     */
    public function searchStudentsByRoleAI($role, $filters = [], $page = 1, $limit = 25) {
        $aiService = new \AIService();
        $aiProfile = $aiService->expandRoleSkills($role);

        $coreSkills = $aiProfile['core_skills'] ?? [];
        $relatedSkills = $aiProfile['related_skills'] ?? [];
        $projectKeywords = $aiProfile['project_keywords'] ?? [];

        // Build list of all search terms for DB query
        $allSkills = array_values(array_unique(array_filter(array_merge($coreSkills, $relatedSkills))));
        $allKeywords = array_values(array_unique(array_filter(array_merge($coreSkills, $relatedSkills, $projectKeywords))));

        if (empty($allKeywords)) {
            $allKeywords = [trim($role)];
        }

        $roleTokens = array_filter(preg_split('/[\s,\-\/]+/', strtolower(trim((string)$role))), fn($t) => strlen($t) >= 3);

        // 1. Find matching portfolio items from student_portfolio (local DB)
        $portfolioMatches = [];
        try {
            $skillConditions = [];
            $skillParams = [];
            foreach ($allSkills as $s) {
                $skillConditions[] = "title LIKE ? OR sub_title LIKE ?";
                $skillParams[] = "%$s%";
                $skillParams[] = "%$s%";
            }

            $projectConditions = [];
            $projectParams = [];
            foreach ($allKeywords as $k) {
                $projectConditions[] = "title LIKE ? OR sub_title LIKE ? OR description LIKE ?";
                $projectParams[] = "%$k%";
                $projectParams[] = "%$k%";
                $projectParams[] = "%$k%";
            }

            $condSql = [];
            $execParams = [];
            if (!empty($skillConditions)) {
                $condSql[] = "(" . implode(' OR ', $skillConditions) . ")";
                $execParams = array_merge($execParams, $skillParams);
            }
            if (!empty($projectConditions)) {
                $condSql[] = "(" . implode(' OR ', $projectConditions) . ")";
                $execParams = array_merge($execParams, $projectParams);
            }

            if (!empty($condSql)) {
                $sqlPortfolio = "SELECT id, student_id, institution, category, title, sub_title, description, link, is_verified, verification_score 
                                 FROM student_portfolio 
                                 WHERE " . implode(' OR ', $condSql);
                
                $stmtPort = $this->db->prepare($sqlPortfolio);
                $stmtPort->execute($execParams);
                while ($pRow = $stmtPort->fetch(PDO::FETCH_ASSOC)) {
                    $sid = strtoupper(trim((string)$pRow['student_id']));
                    if (!isset($portfolioMatches[$sid])) {
                        $portfolioMatches[$sid] = ['skills' => [], 'projects' => []];
                    }
                    if ($pRow['category'] === 'Skill') {
                        $portfolioMatches[$sid]['skills'][] = $pRow;
                    } else {
                        $portfolioMatches[$sid]['projects'][] = $pRow;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error in portfolio search: " . $e->getMessage());
        }

        // 2. Also search student_resumes (local DB) for matching skills & projects
        $resumeMatches = [];
        try {
            $resumeConds = [];
            $resumeParams = [];
            foreach ($allKeywords as $k) {
                $resumeConds[] = "skills LIKE ? OR projects LIKE ? OR professional_summary LIKE ?";
                $resumeParams[] = "%$k%";
                $resumeParams[] = "%$k%";
                $resumeParams[] = "%$k%";
            }

            if (!empty($resumeConds)) {
                $sqlResume = "SELECT student_id, full_name, email, phone, skills, projects, professional_summary, resume_data 
                              FROM student_resumes 
                              WHERE " . implode(' OR ', $resumeConds);
                $stmtRes = $this->db->prepare($sqlResume);
                $stmtRes->execute($resumeParams);
                while ($rRow = $stmtRes->fetch(PDO::FETCH_ASSOC)) {
                    $sid = strtoupper(trim((string)$rRow['student_id']));
                    $resumeMatches[$sid] = $rRow;
                }
            }
        } catch (\Exception $e) {
            error_log("Error in resume search: " . $e->getMessage());
        }

        // Combined pool of matching student USNs/IDs
        $candidateIds = array_values(array_unique(array_merge(array_keys($portfolioMatches), array_keys($resumeMatches))));

        if (empty($candidateIds)) {
            return [
                'data' => [],
                'all_data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => 0,
                'ai_profile' => $aiProfile
            ];
        }

        // 3. Multi-source Student Details Resolution (Remote DB + Local users + student_resumes + unified_ai_assessments)
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        $students = [];

        // A. Local users table lookup
        $localUsersMap = [];
        try {
            $phUsers = implode(',', array_fill(0, count($candidateIds), '?'));
            $stmtUsers = $this->db->prepare("SELECT USER_NAME as usn, NAME as name, DISCIPLINE as discipline, MOBILE_NO as phone, COLLEGE as college FROM users WHERE USER_NAME IN ($phUsers)");
            $stmtUsers->execute($candidateIds);
            while ($uRow = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
                $localUsersMap[strtoupper(trim((string)$uRow['usn']))] = $uRow;
            }
        } catch (\Exception $e) {}

        // B. Local student_resumes lookup for all candidate IDs
        $allResumesMap = [];
        try {
            $phRes = implode(',', array_fill(0, count($candidateIds), '?'));
            $stmtResAll = $this->db->prepare("SELECT student_id, full_name, email, phone FROM student_resumes WHERE student_id IN ($phRes)");
            $stmtResAll->execute($candidateIds);
            while ($rRow = $stmtResAll->fetch(PDO::FETCH_ASSOC)) {
                $allResumesMap[strtoupper(trim((string)$rRow['student_id']))] = $rRow;
            }
        } catch (\Exception $e) {}

        // C. Local unified_ai_assessments lookup (Demographics & Anti-Fake Proof of Work Data)
        $localAssessMap = [];
        $proofOfWorkMap = [];
        try {
            $phAss = implode(',', array_fill(0, count($candidateIds), '?'));
            $stmtAss = $this->db->prepare("SELECT usn, student_id, student_name, branch, current_sem, assessment_type, score, total_marks, feedback 
                                          FROM unified_ai_assessments 
                                          WHERE usn IN ($phAss) OR student_id IN ($phAss)");
            $stmtAss->execute(array_merge($candidateIds, $candidateIds));
            while ($aRow = $stmtAss->fetch(PDO::FETCH_ASSOC)) {
                $uKey = strtoupper(trim((string)($aRow['usn'] ?: $aRow['student_id'])));
                if (!isset($localAssessMap[$uKey])) {
                    $localAssessMap[$uKey] = $aRow;
                }
                if (!isset($proofOfWorkMap[$uKey])) {
                    $proofOfWorkMap[$uKey] = [
                        'viva_score' => null,
                        'viva_feedback' => '',
                        'skills_verified_count' => 0,
                        'technical_score' => null,
                        'aptitude_score' => null
                    ];
                }

                $type = trim((string)($aRow['assessment_type'] ?? ''));
                $sc = is_numeric($aRow['score']) ? (float)$aRow['score'] : null;

                if ($type === 'Project Defense' && $sc !== null) {
                    if ($proofOfWorkMap[$uKey]['viva_score'] === null || $sc > $proofOfWorkMap[$uKey]['viva_score']) {
                        $proofOfWorkMap[$uKey]['viva_score'] = round($sc, 1);
                        $proofOfWorkMap[$uKey]['viva_feedback'] = trim((string)($aRow['feedback'] ?? ''));
                    }
                } elseif ($type === 'Skill Verification') {
                    $proofOfWorkMap[$uKey]['skills_verified_count']++;
                } elseif ($type === 'Technical' && $sc !== null) {
                    if ($proofOfWorkMap[$uKey]['technical_score'] === null || $sc > $proofOfWorkMap[$uKey]['technical_score']) {
                        $proofOfWorkMap[$uKey]['technical_score'] = round($sc, 1);
                    }
                } elseif ($type === 'Aptitude' && $sc !== null) {
                    if ($proofOfWorkMap[$uKey]['aptitude_score'] === null || $sc > $proofOfWorkMap[$uKey]['aptitude_score']) {
                        $proofOfWorkMap[$uKey]['aptitude_score'] = round($sc, 1);
                    }
                }
            }
        } catch (\Exception $e) {}

        // D. Remote DB lookup
        try {
            $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));

            if ($this->remoteDB) {
                // Query GMU students
                $sqlGmu = "SELECT ad.usn, ad.name, COALESCE(ad.discipline, ad.branch, '') as discipline, ad.sem, det.mobile as phone, det.email, '" . INSTITUTION_GMU . "' as institution
                           FROM {$gmuPrefix}ad_student_approved ad
                           LEFT JOIN {$gmuPrefix}ad_student_details det ON ad.usn = det.usn
                           WHERE ad.usn IN ($placeholders)";
                $stmtGmu = $this->remoteDB->prepare($sqlGmu);
                $stmtGmu->execute($candidateIds);
                while ($row = $stmtGmu->fetch(PDO::FETCH_ASSOC)) {
                    $students[strtoupper(trim((string)$row['usn']))] = $row;
                }

                // Query GMIT students
                $sqlGmit = "SELECT ad.student_id as usn, ad.name, COALESCE(ad.discipline, ad.branch, '') as discipline, 0 as sem, '' as phone, '' as email, '" . INSTITUTION_GMIT . "' as institution
                            FROM {$gmitPrefix}ad_student_details ad
                            WHERE ad.student_id IN ($placeholders) OR ad.enquiry_no IN ($placeholders)";
                $stmtGmit = $this->remoteDB->prepare($sqlGmit);
                $stmtGmit->execute(array_merge($candidateIds, $candidateIds));
                while ($row = $stmtGmit->fetch(PDO::FETCH_ASSOC)) {
                    $usnKey = strtoupper(trim((string)$row['usn']));
                    if (!isset($students[$usnKey])) {
                        $students[$usnKey] = $row;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error fetching candidate student details from remote: " . $e->getMessage());
        }

        // Branch code dictionary to decode branch from USN when missing
        $branchCodeMap = [
            'CS' => 'Computer Science & Engineering',
            'CY' => 'Cyber Security',
            'AI' => 'AI & Machine Learning',
            'IS' => 'Information Science & Engineering',
            'IT' => 'Information Technology',
            'IO' => 'Computer Science & Engineering (IT)',
            'IY' => 'Computer Science & Engineering (Cyber)',
            'EC' => 'Electronics & Communication',
            'EE' => 'Electrical & Electronics',
            'ME' => 'Mechanical Engineering',
            'CV' => 'Civil Engineering',
            'BT' => 'Biotechnology',
            'RO' => 'Robotics & Automation',
        ];

        // 4. Consolidate and resolve Name, Institution, Department, Semester, Phone, Email
        foreach ($candidateIds as $cid) {
            $u = $localUsersMap[$cid] ?? [];
            $r = $allResumesMap[$cid] ?? [];
            $a = $localAssessMap[$cid] ?? [];
            $remote = $students[$cid] ?? [];

            // A. Institution
            $inst = !empty($remote['institution']) ? $remote['institution']
                  : ((strpos($cid, 'GMIT') !== false || preg_match('/^4GM/i', $cid)) ? INSTITUTION_GMIT : INSTITUTION_GMU);

            // B. Resolve Name (Strictly avoid showing USN as Name)
            $resolvedName = '';
            $candidates = [
                $r['full_name'] ?? '',
                $remote['name'] ?? '',
                $u['name'] ?? '',
                $a['student_name'] ?? ''
            ];
            foreach ($candidates as $cand) {
                $candTrim = trim((string)$cand);
                if ($candTrim !== '' && strtoupper($candTrim) !== $cid && strpos($candTrim, '@') === false) {
                    $resolvedName = $candTrim;
                    break;
                }
            }
            if ($resolvedName === '') {
                $resolvedName = $cid; // Ultimate fallback
            }

            // C. Resolve Department / Discipline
            $resolvedDept = '';
            if (!empty($remote['discipline']) && $remote['discipline'] !== '-') {
                $resolvedDept = $remote['discipline'];
            } elseif (!empty($u['discipline']) && $u['discipline'] !== '-') {
                $resolvedDept = $u['discipline'];
            } elseif (!empty($a['branch']) && $a['branch'] !== '-') {
                $resolvedDept = $a['branch'];
            } else {
                // Decode from USN pattern (e.g. U23E01CY023 -> CY -> Cyber Security)
                if (preg_match('/(?:[A-Z0-9]{2,3}E01([A-Z]{2})|GMIT\d{2}([A-Z]{2})|4GM\d{2}([A-Z]{2}))/i', $cid, $m)) {
                    $code = strtoupper($m[1] ?: ($m[2] ?: ($m[3] ?? '')));
                    if (isset($branchCodeMap[$code])) {
                        $resolvedDept = $branchCodeMap[$code];
                    }
                }
            }
            if ($resolvedDept === '') $resolvedDept = '-';

            // D. Resolve Semester
            $resolvedSem = '-';
            if (!empty($remote['sem']) && (int)$remote['sem'] > 0) {
                $resolvedSem = (int)$remote['sem'];
            } elseif (!empty($a['current_sem']) && (int)$a['current_sem'] > 0) {
                $resolvedSem = (int)$a['current_sem'];
            } else {
                // Check local student_sem_sgpa
                try {
                    $stmtSgpa = $this->db->prepare("SELECT semester FROM student_sem_sgpa WHERE (student_id = ? OR student_id = ?) AND is_current = 1 LIMIT 1");
                    $stmtSgpa->execute([$cid, $cid]);
                    $semRow = $stmtSgpa->fetch();
                    if ($semRow && !empty($semRow['semester'])) {
                        $resolvedSem = (int)$semRow['semester'];
                    }
                } catch (\Exception $e) {}
            }

            // Fallback semester inference by batch year (e.g. 2023 batch in 2026 -> 7th sem)
            if ($resolvedSem === '-' || $resolvedSem === 0) {
                if (preg_match('/(?:U23|GMIT23|4GM22)/i', $cid)) {
                    $resolvedSem = 7;
                } elseif (preg_match('/(?:U24|GMIT24|4GM23)/i', $cid)) {
                    $resolvedSem = 5;
                }
            }

            // E. Resolve Phone & Email
            $resolvedPhone = !empty($r['phone']) && $r['phone'] !== 'N/A' ? $r['phone']
                           : (!empty($remote['phone']) && $remote['phone'] !== 'N/A' ? $remote['phone']
                           : (!empty($u['phone']) && $u['phone'] !== 'N/A' ? $u['phone'] : ''));

            $resolvedEmail = !empty($r['email']) && $r['email'] !== 'N/A' ? $r['email']
                           : (!empty($remote['email']) && $remote['email'] !== 'N/A' ? $remote['email'] : '');

            $students[$cid] = [
                'usn' => $cid,
                'name' => $resolvedName,
                'institution' => $inst,
                'discipline' => $resolvedDept,
                'sem' => $resolvedSem,
                'phone' => $resolvedPhone,
                'email' => $resolvedEmail
            ];
        }

        // 5. Filter students by institution and department if specified
        $filteredStudents = [];
        foreach ($students as $usnKey => $s) {
            if (!empty($filters['institution']) && strtoupper((string)$s['institution']) !== strtoupper((string)$filters['institution'])) {
                continue;
            }
            if (!empty($filters['discipline']) && strtolower(trim((string)$s['discipline'])) !== strtolower(trim((string)$filters['discipline']))) {
                continue;
            }
            if (!empty($filters['semester']) && (int)$s['sem'] !== (int)$filters['semester']) {
                continue;
            }
            $filteredStudents[$usnKey] = $s;
        }

        // 6. Calculate AI Match Score & extract matching skills and projects
        $scoredList = [];
        foreach ($filteredStudents as $usnKey => $s) {
            $matchedSkills = [];
            $matchedProjects = [];
            $score = 0;
            $hasDirectDomainProject = false;
            $hasVerifiedDomainProject = false;
            $directDomainProjectCount = 0;
            $coreSkillsFoundCount = 0;

            // A. Portfolio Skills
            $portItems = $portfolioMatches[$usnKey] ?? ['skills' => [], 'projects' => []];
            foreach ($portItems['skills'] as $sk) {
                $skTitle = $sk['title'];
                $isCore = false;
                foreach ($coreSkills as $cs) {
                    if (stripos($skTitle, $cs) !== false) {
                        $isCore = true;
                        $coreSkillsFoundCount++;
                        break;
                    }
                }
                $matchedSkills[] = [
                    'name' => $skTitle,
                    'level' => $sk['sub_title'] ?? '',
                    'is_verified' => (int)($sk['is_verified'] ?? 0),
                    'is_core' => $isCore
                ];
                if ($isCore) {
                    $score += 12;
                    if (!empty($sk['is_verified'])) $score += 6;
                } else {
                    $score += 2;
                }
            }

            // B. Portfolio Projects
            foreach ($portItems['projects'] as $pr) {
                $prTitle = $pr['title'];
                $prDesc = $pr['description'] ?? '';
                $prSub = $pr['sub_title'] ?? '';
                $combinedPr = $prTitle . ' ' . $prSub . ' ' . $prDesc;

                // Check direct relevance to target role
                $isDirect = false;
                foreach ($coreSkills as $cs) {
                    if (stripos($combinedPr, $cs) !== false) {
                        $isDirect = true;
                        break;
                    }
                }
                if (!$isDirect) {
                    foreach ($roleTokens as $rt) {
                        if (strlen($rt) >= 4 && stripos($combinedPr, $rt) !== false) {
                            $isDirect = true;
                            break;
                        }
                    }
                }

                $isVerified = (int)($pr['is_verified'] ?? 0);
                if ($isDirect) {
                    $hasDirectDomainProject = true;
                    $directDomainProjectCount++;
                    if ($isVerified) $hasVerifiedDomainProject = true;
                    $score += 30;
                    if ($isVerified) $score += 12;
                } else {
                    // Peripheral / unrelated project
                    $score += 3;
                }

                $matchedProjects[] = [
                    'title' => $prTitle,
                    'tech' => $prSub,
                    'summary' => $prDesc,
                    'link' => $pr['link'] ?? '',
                    'is_verified' => $isVerified,
                    'score' => $pr['verification_score'] ?? null,
                    'is_direct' => $isDirect
                ];
            }

            // C. Resume Skills and Projects
            if (isset($resumeMatches[$usnKey])) {
                $r = $resumeMatches[$usnKey];
                $rSkillsText = $r['skills'] ?? '';
                if (!empty($rSkillsText)) {
                    $skillsList = json_decode($rSkillsText, true);
                    if (is_array($skillsList)) {
                        foreach ($skillsList as $rSk) {
                            $rName = is_array($rSk) ? ($rSk['name'] ?? '') : (string)$rSk;
                            if (empty($rName)) continue;
                            foreach ($coreSkills as $cs) {
                                if (stripos($rName, $cs) !== false && !in_array($rName, array_column($matchedSkills, 'name'))) {
                                    $matchedSkills[] = ['name' => $rName, 'level' => '', 'is_verified' => 0, 'is_core' => true];
                                    $score += 10;
                                    $coreSkillsFoundCount++;
                                    break;
                                }
                            }
                        }
                    }
                }

                $rProjText = $r['projects'] ?? '';
                if (!empty($rProjText)) {
                    $projList = json_decode($rProjText, true);
                    if (is_array($projList)) {
                        foreach ($projList as $rPr) {
                            $pTitle = $rPr['title'] ?? ($rPr['project_title'] ?? '');
                            $pDesc = $rPr['description'] ?? '';
                            $pTech = $rPr['technologies'] ?? ($rPr['tech_stack'] ?? '');
                            if (empty($pTitle)) continue;

                            $combinedPr = $pTitle . ' ' . (is_array($pTech) ? implode(' ', $pTech) : $pTech) . ' ' . (is_array($pDesc) ? implode(' ', $pDesc) : $pDesc);
                            
                            $isDirect = false;
                            foreach ($coreSkills as $cs) {
                                if (stripos($combinedPr, $cs) !== false) {
                                    $isDirect = true;
                                    break;
                                }
                            }
                            if (!$isDirect) {
                                foreach ($roleTokens as $rt) {
                                    if (strlen($rt) >= 4 && stripos($combinedPr, $rt) !== false) {
                                        $isDirect = true;
                                        break;
                                    }
                                }
                            }

                            $isRelevant = $isDirect;
                            if (!$isRelevant) {
                                foreach ($allKeywords as $kw) {
                                    if (stripos($combinedPr, $kw) !== false) {
                                        $isRelevant = true;
                                        break;
                                    }
                                }
                            }

                            if ($isRelevant && !in_array($pTitle, array_column($matchedProjects, 'title'))) {
                                if ($isDirect) {
                                    $hasDirectDomainProject = true;
                                    $directDomainProjectCount++;
                                    $score += 26;
                                } else {
                                    $score += 3;
                                }

                                $matchedProjects[] = [
                                    'title' => $pTitle,
                                    'tech' => is_array($pTech) ? implode(', ', $pTech) : (string)$pTech,
                                    'summary' => is_array($pDesc) ? implode(' ', $pDesc) : (string)$pDesc,
                                    'link' => $rPr['link'] ?? '',
                                    'is_verified' => 0,
                                    'score' => null,
                                    'is_direct' => $isDirect
                                ];
                            }
                        }
                    }
                }
            }

            // Deduplicate matched skills and put core skills first
            $uniqueSkills = [];
            $seenSkillNames = [];
            foreach ($matchedSkills as $sk) {
                $lower = strtolower(trim((string)$sk['name']));
                if (!isset($seenSkillNames[$lower])) {
                    $seenSkillNames[$lower] = true;
                    $uniqueSkills[] = $sk;
                }
            }
            usort($uniqueSkills, function($sa, $sb) {
                $ca = !empty($sa['is_core']) ? 1 : 0;
                $cb = !empty($sb['is_core']) ? 1 : 0;
                if ($ca !== $cb) return $cb <=> $ca;
                $va = !empty($sa['is_verified']) ? 1 : 0;
                $vb = !empty($sb['is_verified']) ? 1 : 0;
                return $vb <=> $va;
            });

            // Sort matched projects: Direct domain projects ALWAYS first, verified first
            usort($matchedProjects, function($pa, $pb) {
                $da = !empty($pa['is_direct']) ? 1 : 0;
                $db = !empty($pb['is_direct']) ? 1 : 0;
                if ($da !== $db) return $db <=> $da;
                $va = !empty($pa['is_verified']) ? 1 : 0;
                $vb = !empty($pb['is_verified']) ? 1 : 0;
                return $vb <=> $va;
            });

            // Assemble Proof-of-Work & Authenticity Data
            $powData = $proofOfWorkMap[$usnKey] ?? [
                'viva_score' => null,
                'viva_feedback' => '',
                'skills_verified_count' => 0,
                'technical_score' => null,
                'aptitude_score' => null
            ];

            // Check if portfolio has verified viva scores
            $bestViva = $powData['viva_score'];
            foreach ($matchedProjects as $pr) {
                if (!empty($pr['is_verified']) && !empty($pr['score']) && is_numeric($pr['score'])) {
                    $bestViva = max($bestViva ?? 0, (float)$pr['score']);
                }
            }

            $isVivaDefended = ($bestViva !== null && $bestViva >= 60);
            $hasTestVerified = ($powData['skills_verified_count'] > 0 || ($powData['technical_score'] !== null && $powData['technical_score'] >= 60));

            if ($isVivaDefended) {
                $powBadge = 'Verified Viva (' . round($bestViva) . '%)';
                $powStatus = 'VERIFIED_VIVA';
                $powSummary = 'Oral Project Defense Passed (' . round($bestViva) . '/100)';
                $isPoWVerified = true;
            } elseif ($hasTestVerified) {
                $powBadge = 'Tests Verified';
                $powStatus = 'VERIFIED_TESTS';
                $powSummary = $powData['skills_verified_count'] > 0 
                    ? "{$powData['skills_verified_count']} Skill Test(s) Passed" 
                    : "Technical Round: " . round($powData['technical_score']) . "%";
                $isPoWVerified = true;
            } else {
                $powBadge = 'Self-Reported';
                $powStatus = 'SELF_REPORTED';
                $powSummary = 'Self-Reported Claim (No viva defense or tests on file)';
                $isPoWVerified = false;
            }

            $proofOfWorkInfo = [
                'is_verified' => $isPoWVerified,
                'badge' => $powBadge,
                'status' => $powStatus,
                'summary' => $powSummary,
                'viva_score' => $bestViva,
                'viva_feedback' => $powData['viva_feedback'],
                'skills_verified_count' => $powData['skills_verified_count'],
                'technical_score' => $powData['technical_score'],
                'aptitude_score' => $powData['aptitude_score']
            ];

            // ANTI-FAKE STRICT SCORING & CAPPING:
            // 1. Both Verified Viva + Direct Domain Projects + Core Skills: Top Tier Fit (88% - 98%)
            // 2. Direct Domain Projects + Core Skills but UNVERIFIED (Self-reported claims): Cap at 78% (Strong Fit)
            // 3. Tests Verified + Core Skills (no direct project): Cap at 65% (Moderate Fit)
            // 4. Self-reported claim only (unverified projects/skills): Cap at 45% (Potential)
            if ($isPoWVerified && $hasDirectDomainProject && $coreSkillsFoundCount > 0) {
                $finalScore = min(98, max(88, $score + 10));
                $fitLevel = 'Top Tier Fit';
            } elseif ($hasDirectDomainProject && $coreSkillsFoundCount > 0) {
                // Good projects & skills claimed, but NOT verified in viva defense
                $finalScore = min(78, max(68, $score));
                $fitLevel = 'Strong Fit';
            } elseif ($isPoWVerified && $coreSkillsFoundCount > 0) {
                $finalScore = min(68, max(52, $score));
                $fitLevel = 'Moderate Fit';
            } elseif ($hasDirectDomainProject) {
                $finalScore = min(65, max(50, $score));
                $fitLevel = 'Moderate Fit';
            } else {
                $finalScore = min(45, max(30, $score));
                $fitLevel = 'Potential';
            }

            // Generate precise reasoning
            $coreSkillsFound = array_filter($uniqueSkills, fn($sk) => !empty($sk['is_core']));
            $coreNames = implode(', ', array_slice(array_column($coreSkillsFound, 'name'), 0, 3));

            if ($directDomainProjectCount > 0 && count($coreSkillsFound) > 0) {
                $proofPrefix = $isPoWVerified ? "Verified Proof-of-Work: " : "Self-Reported Claim: ";
                $reasoning = "{$proofPrefix}{$directDomainProjectCount} project(s) in {$role} stack + hands-on {$coreNames}.";
            } elseif ($directDomainProjectCount > 0) {
                $reasoning = "Practical project match: {$directDomainProjectCount} direct domain project(s) identified.";
            } elseif (count($coreSkillsFound) > 0) {
                $reasoning = "Skill match: Verified in {$coreNames}, but lacking end-to-end domain projects.";
            } else {
                $reasoning = "Peripheral foundation: Basic programming background; no direct {$role} projects.";
            }

            // Resume file existence check
            $resumeFile = strtoupper((string)$s['usn']) . '_Resume.pdf';
            $resumePath = UPLOADS_PATH . '/resumes/Student_Resumes/' . $resumeFile;
            $hasResume = file_exists($resumePath);
            $token = function_exists('generateResumeToken') ? generateResumeToken($s['usn']) : '';
            $resumeUrl = APP_URL . '/student/view_resume.php?usn=' . urlencode($s['usn']) . ($token ? '&token=' . $token : '');

            $s['matched_skills'] = $uniqueSkills;
            $s['matched_projects'] = $matchedProjects;
            $s['match_score'] = $finalScore;
            $s['fit_level'] = $fitLevel;
            $s['proof_of_work'] = $proofOfWorkInfo;
            $s['ai_reasoning'] = $reasoning;
            $s['has_resume'] = $hasResume;
            $s['resume_url'] = $resumeUrl;

            $scoredList[] = $s;
        }

        // Filter by AI Fit & Match Score if specified
        if (!empty($filters['ai_fit'])) {
            $fitFilter = strtolower(trim((string)$filters['ai_fit']));
            $scoredList = array_values(array_filter($scoredList, function($cand) use ($fitFilter) {
                $score = (int)($cand['match_score'] ?? 0);
                if ($fitFilter === 'top' || $fitFilter === 'top_tier') {
                    return $score >= 75;
                } elseif ($fitFilter === 'strong') {
                    return $score >= 60 && $score < 75;
                } elseif ($fitFilter === 'moderate' || $fitFilter === 'potential') {
                    return $score < 60;
                } elseif ($fitFilter === 'verified' || $fitFilter === 'verified_projects' || $fitFilter === 'pow' || $fitFilter === 'viva') {
                    return !empty($cand['proof_of_work']['is_verified']);
                }
                return true;
            }));
        }

        // Filter by AI Reasoning & Competency keywords if specified
        if (!empty($filters['reasoning'])) {
            $rQuery = strtolower(trim((string)$filters['reasoning']));
            $scoredList = array_values(array_filter($scoredList, function($cand) use ($rQuery) {
                if (stripos((string)($cand['ai_reasoning'] ?? ''), $rQuery) !== false) return true;
                foreach ($cand['matched_skills'] as $sk) {
                    if (stripos((string)($sk['name'] ?? ''), $rQuery) !== false) return true;
                }
                foreach ($cand['matched_projects'] as $pr) {
                    $combinedPr = ($pr['title'] ?? '') . ' ' . ($pr['tech'] ?? '') . ' ' . ($pr['summary'] ?? '');
                    if (stripos($combinedPr, $rQuery) !== false) return true;
                }
                return false;
            }));
        }

        // Sort descending by match score, then verified viva score, then name ascending
        usort($scoredList, function($a, $b) {
            if ($b['match_score'] !== $a['match_score']) {
                return $b['match_score'] <=> $a['match_score'];
            }
            $vivaB = (float)($b['proof_of_work']['viva_score'] ?? 0);
            $vivaA = (float)($a['proof_of_work']['viva_score'] ?? 0);
            if ($vivaB !== $vivaA) {
                return $vivaB <=> $vivaA;
            }
            return strcmp((string)$a['name'], (string)$b['name']);
        });

        $total = count($scoredList);
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;
        $offset = ($page - 1) * $limit;
        $pagedSlice = array_slice($scoredList, $offset, $limit);

        // Deep AI Technical Evaluation for displayed candidates
        if (!empty($pagedSlice)) {
            $deepEvaluations = $aiService->evaluateCandidatesBatchAI($role, $pagedSlice);
            if (!empty($deepEvaluations)) {
                foreach ($pagedSlice as &$cand) {
                    $uKey = strtoupper(trim((string)$cand['usn']));
                    if (isset($deepEvaluations[$uKey])) {
                        $ev = $deepEvaluations[$uKey];
                        if (!empty($ev['technical_appraisal'])) {
                            $cand['ai_reasoning'] = $ev['technical_appraisal'];
                        }
                        if (!empty($ev['ai_score'])) {
                            $cand['match_score'] = (int)$ev['ai_score'];
                        }
                        if (!empty($ev['fit_level'])) {
                            $cand['fit_level'] = $ev['fit_level'];
                        }
                        if (!empty($ev['standout_project'])) {
                            $cand['standout_project'] = $ev['standout_project'];
                        }
                    }
                }
                unset($cand);

                // CRITICAL: Re-sort current displayed slice strictly descending by match_score & verified proof
                usort($pagedSlice, function($a, $b) {
                    if ($b['match_score'] !== $a['match_score']) {
                        return $b['match_score'] <=> $a['match_score'];
                    }
                    $vivaB = (float)($b['proof_of_work']['viva_score'] ?? 0);
                    $vivaA = (float)($a['proof_of_work']['viva_score'] ?? 0);
                    if ($vivaB !== $vivaA) {
                        return $vivaB <=> $vivaA;
                    }
                    return strcmp((string)$a['name'], (string)$b['name']);
                });
            }
        }

        return [
            'data' => $pagedSlice,
            'all_data' => $scoredList,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'ai_profile' => $aiProfile
        ];
    }
}
