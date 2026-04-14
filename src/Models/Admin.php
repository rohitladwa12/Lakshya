<?php
require_once __DIR__ . '/Model.php';

class Admin extends Model {
    protected $table = 'users'; 

    public function getDashboardStats() {
        $stats = [];
        
        // Active Jobs
        $sql = "SELECT COUNT(*) as count FROM job_postings WHERE status = 'Active'";
        $stats['active_jobs'] = $this->queryOne($sql)['count'] ?? 0;

        // Active Internships 
        $sql = "SELECT COUNT(*) as count FROM internships WHERE status = 'Active'";
        try {
            $stats['active_internships'] = $this->queryOne($sql)['count'] ?? 0;
        } catch (Throwable $e) {
            $stats['active_internships'] = 0;
        }
        
        // Total Job Applications
        $sql = "SELECT COUNT(*) as count FROM job_applications";
        $stats['total_applications'] = $this->queryOne($sql)['count'] ?? 0;
        
        // Placed Students (Selected status)
        $sql = "SELECT COUNT(DISTINCT student_id) as count FROM job_applications WHERE status = 'Selected'";
        $stats['placed_students'] = $this->queryOne($sql)['count'] ?? 0;
        
        // Total Students across institutions
        try {
            $gmuDB = getDB('gmu');
            $gmuCount = ($gmuDB) ? $gmuDB->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE'")->fetchColumn() : 0;
        } catch (Throwable $e) { $gmuCount = 0; }

        try {
            $gmitDB = getDB('gmit');
            $gmitCount = ($gmitDB) ? $gmitDB->query("SELECT COUNT(*) FROM users WHERE USER_GROUP = 'STUDENT' AND STATUS = 'ACTIVE'")->fetchColumn() : 0;
        } catch (Throwable $e) { $gmitCount = 0; }

        $stats['total_students'] = (int)$gmuCount + (int)$gmitCount;

        // Total Companies
        $sql = "SELECT COUNT(*) as count FROM companies";
        $stats['total_companies'] = $this->queryOne($sql)['count'] ?? 0;

        // Active Jobs & Internships
        $sql = "SELECT job_type, COUNT(*) as count FROM job_postings WHERE status = 'Active' GROUP BY job_type";
        $jobStats = $this->query($sql);
        $stats['active_jobs'] = 0;
        $stats['active_internships'] = 0;
        foreach ($jobStats as $js) {
            if ($js['job_type'] === 'Full-time') $stats['active_jobs'] = $js['count'];
            if ($js['job_type'] === 'Internship') $stats['active_internships'] = $js['count'];
        }

        // Total Placed Students
        $sql = "SELECT COUNT(*) as count FROM job_applications WHERE status = 'Selected'";
        $stats['placed_students'] = $this->queryOne($sql)['count'] ?? 0;
        
        return $stats;
    }

    public function getRecentActivity($limit = 5) {
        $sql = "SELECT * FROM (
                    SELECT ja.applied_at as activity_date, ja.status, jp.title as role, c.name as company_name, 'Job Application' as type
                    FROM job_applications ja
                    JOIN job_postings jp ON ja.job_id = jp.id
                    JOIN companies c ON jp.company_id = c.id
                    
                    UNION ALL
                    
                    SELECT created_at as activity_date, status, assessment_title as role, 'AI Evaluator' as company_name, assessment_type as type
                    FROM unified_ai_assessments
                ) AS combined
                ORDER BY activity_date DESC
                LIMIT ?";
        try {
            return $this->query($sql, [(int)$limit]);
        } catch (Throwable $e) { return []; }
    }

    public function getResumeCompletionStats() {
        $stats = [
            'total_built' => 0,
            'percentage' => 0,
            'by_department' => []
        ];

        try {
            $sql = "SELECT DISTINCT student_id FROM student_resumes";
            $builtIds = $this->query($sql, [], PDO::FETCH_COLUMN);
            $stats['total_built'] = count($builtIds);

            // Calculate overall percentage
            $dashboardStats = $this->getDashboardStats();
            if ($dashboardStats['total_students'] > 0) {
                $stats['percentage'] = round(($stats['total_built'] / $dashboardStats['total_students']) * 100, 1);
            }

            $userModel = new User();
            $deptCounts = [];

            foreach ($builtIds as $sid) {
                // Find student in local portal users
                $student = $userModel->find($sid);
                $resolvedDept = 'Unknown';
                $possibleInstitutions = [];

                if ($student && !empty($student['institution']) && $student['institution'] !== 'Unknown') {
                    $possibleInstitutions[] = strtolower($student['institution']);
                } else {
                    // Exhaustive search - try both if unknown
                    $possibleInstitutions = ['gmu', 'gmit'];
                }

                foreach ($possibleInstitutions as $inst) {
                    try {
                        $conn = getDB($inst);
                        if ($conn instanceof PDO) {
                            $prefix = ($inst === 'gmit') ? DB_GMIT_PREFIX : DB_GMU_PREFIX;
                            $table = ($inst === 'gmit') ? 'ad_student_details' : 'ad_student_approved';
                            $usnToFind = ($student && isset($student['username'])) ? $student['username'] : $sid;

                            // Try USN, student_id, or Aadhar
                            $stmt = $conn->prepare("SELECT discipline FROM {$prefix}{$table} WHERE usn = ? OR student_id = ? OR aadhar = ? LIMIT 1");
                            $stmt->execute([$usnToFind, $usnToFind, $usnToFind]);
                            $resolvedDept = $stmt->fetchColumn();
                            
                            // Fallback for GMU - second table
                            if (!$resolvedDept && $inst === 'gmu') {
                                $stmt = $conn->prepare("SELECT discipline FROM {$prefix}ad_student_details WHERE usn = ? OR student_id = ? OR aadhar = ? LIMIT 1");
                                $stmt->execute([$usnToFind, $usnToFind, $usnToFind]);
                                $resolvedDept = $stmt->fetchColumn();
                            }

                            if ($resolvedDept) break; // Found it!
                        }
                    } catch(Throwable $e) {}
                }

                $resolvedDept = $resolvedDept ?: 'Unknown';
                $deptCounts[$resolvedDept] = ($deptCounts[$resolvedDept] ?? 0) + 1;
            }
            
            // Format for UI
            arsort($deptCounts);
            foreach ($deptCounts as $deptName => $count) {
                $stats['by_department'][] = [
                    'department' => $deptName, 
                    'count' => $count
                ];
            }

        } catch (Throwable $e) {
            logMessage("Admin Stats Critical Error: " . $e->getMessage(), 'ERROR');
        }

        return $stats;
    }

    /**
     * Get a detailed list of all students who have built resumes
     */
    public function getDetailedResumeList() {
        $list = [];
        try {
            $sql = "SELECT id, student_id, full_name, created_at FROM student_resumes ORDER BY created_at DESC";
            $resumes = $this->query($sql);

            $userModel = new User();

            foreach ($resumes as $r) {
                $sid = $r['student_id'];
                $student = $userModel->find($sid);
                
                $resolvedDept = 'Unknown';
                $institution = 'Unknown';
                
                $possibleInstitutions = [];
                if ($student && !empty($student['institution']) && $student['institution'] !== 'Unknown') {
                    $institution = $student['institution'];
                    $possibleInstitutions[] = strtolower($institution);
                } else {
                    $possibleInstitutions = ['gmu', 'gmit'];
                }

                foreach ($possibleInstitutions as $inst) {
                    try {
                        $conn = getDB($inst);
                        if ($conn instanceof PDO) {
                            $prefix = ($inst === 'gmit') ? DB_GMIT_PREFIX : DB_GMU_PREFIX;
                            $table = ($inst === 'gmit') ? 'ad_student_details' : 'ad_student_approved';
                            $usnToFind = ($student && isset($student['username'])) ? $student['username'] : $sid;

                            $stmt = $conn->prepare("SELECT usn, discipline FROM {$prefix}{$table} WHERE usn = ? OR student_id = ? OR aadhar = ? LIMIT 1");
                            $stmt->execute([$usnToFind, $usnToFind, $usnToFind]);
                            $remoteUser = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$remoteUser && $inst === 'gmu') {
                                $stmt = $conn->prepare("SELECT usn, discipline FROM {$prefix}ad_student_details WHERE usn = ? OR student_id = ? OR aadhar = ? LIMIT 1");
                                $stmt->execute([$usnToFind, $usnToFind, $usnToFind]);
                                $remoteUser = $stmt->fetch(PDO::FETCH_ASSOC);
                            }

                            if ($remoteUser) {
                                $resolvedDept = $remoteUser['discipline'];
                                $institution = strtoupper($inst);
                                if (!empty($remoteUser['usn'])) {
                                    $sid = $remoteUser['usn']; // Use the real USN for PDF path and display
                                }
                                break;
                            }
                        }
                    } catch(Throwable $e) {}
                }

                // Construct PDF path using resolved USN
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sid);
                $fileName = strtoupper($safeName) . '_Resume.pdf';
                $pdfPath = "uploads/resumes/Student_Resumes/" . $fileName;

                $list[] = [
                    'id' => $r['id'],
                    'student_id' => $sid, // Now guaranteed to be the USN if found
                    'full_name' => $r['full_name'],
                    'institution' => $institution,
                    'department' => $resolvedDept ?: 'Unknown',
                    'built_at' => $r['created_at'],
                    'pdf_path' => $pdfPath
                ];
            }
        } catch (Throwable $e) {
            logMessage("Admin Detailed Resume List Error: " . $e->getMessage(), 'ERROR');
        }

        return $list;
    }
    /**
     * Get a list of all users in the portal (Mainly staff and admins)
     */
    public function getUsersList() {
        $sql = "SELECT id, user_name, email, role, institution, is_active, created_at FROM app_officers ORDER BY created_at DESC";
        try {
            return $this->query($sql);
        } catch (Throwable $e) {
            logMessage("Admin Users List Error: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Get a list of all companies in the portal
     */
    public function getCompaniesList() {
        $sql = "SELECT * FROM companies ORDER BY created_at DESC";
        try {
            return $this->query($sql);
        } catch (Throwable $e) {
            logMessage("Admin Companies List Error: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
}
