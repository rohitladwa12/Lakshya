<?php
/**
 * Leaderboard Handler - Coordinator
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require coordinator role
requireRole(ROLE_DEPT_COORDINATOR);

// Handle JSON requests
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_POST = array_merge($_POST, $input);
}

$action = post('action');

switch ($action) {
    case 'get_academic_history':
        $studentId = post('student_id'); // This is the USN
        if (!$studentId) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            break;
        }

        $profileModel = new StudentProfile();
        $history = $profileModel->getAcademicHistory($studentId);
        
        if ($history) {
            echo json_encode(['success' => true, 'history' => $history]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Academic history not found']);
        }
        break;

    case 'push_to_pool':
        $usns = post('usns');
        if (empty($usns) || !is_array($usns)) {
            echo json_encode(['success' => false, 'message' => 'No students selected']);
            break;
        }

        $coordinatorId = $_SESSION['user_id'] ?? 0;
        if (!$coordinatorId) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
            break;
        }

        $db = getDB();
        $gmuDB = getDB('gmu');
        $gmuPrefix = DB_GMU_PREFIX;

        // Determine Academic Year (Allow override from frontend)
        $academicYear = post('academic_year');
        $company_name = post('company_name');
        
        if (!$academicYear) {
            $ayStmt = $gmuDB->query("SELECT MAX(ACADEMIC_YEAR) FROM {$gmuPrefix}ad_student_approved");
            $academicYear = $ayStmt->fetchColumn() ?: date('Y') . '-' . (date('y') + 1);
        }

        // 2. Fetch full rankings for these USNs to get calculated scores
        // We use LeaderboardService to reuse the scoring logic
        $rankings = \App\Services\LeaderboardService::getRankings(['usns' => $usns]);
        
        // 3. Get Student Details (for SSLC/PUC which aren't in rankings)
        $studentModel = new StudentProfile();
        $allDetails = []; // Map USN to details

        $count = 0;
        foreach ($rankings as $r) {
            $usn = $r['usn'];
            
            // Get extra details (SSLC, PUC, Mobile, Email) from Profile
            $profile = $studentModel->getByUserId($usn, $r['institution']);
            
            // Prepare Insert (email_id removed as per schema update, student_mobile removed as requested)
            $sql = "INSERT INTO placement_ready_pool (
                coordinator_id, usn, institution, academic_year, name, company_name, 
                branch, semester, sgpa_1, sgpa_2, sgpa_3, sgpa_4, sgpa_5, sgpa_6, sgpa_7, sgpa_8, 
                sslc_percentage, puc_percentage, skills, projects,
                aptitude_score, technical_score, hr_score, total_score
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            
            $skillsStr = implode(', ', (array)($r['skills'] ?? []));
            
            // Fetch projects explicitly if not in ranking
            $projStmt = $db->prepare("SELECT title FROM student_portfolio WHERE student_id = ? AND category = 'Project' AND is_verified = 1");
            $projStmt->execute([$usn]);
            $projects = $projStmt->fetchAll(PDO::FETCH_COLUMN);
            $projectsStr = implode(', ', $projects);

            $stmt->execute([
                $coordinatorId,
                $usn,
                $r['institution'],
                $academicYear,
                $r['name'],
                $company_name, // New column
                $r['discipline'],
                $profile['semester'] ?? 0,
                $r['academic_history'][1]['sgpa'] ?? 0,
                $r['academic_history'][2]['sgpa'] ?? 0,
                $r['academic_history'][3]['sgpa'] ?? 0,
                $r['academic_history'][4]['sgpa'] ?? 0,
                $r['academic_history'][5]['sgpa'] ?? 0,
                $r['academic_history'][6]['sgpa'] ?? 0,
                $r['academic_history'][7]['sgpa'] ?? 0,
                $r['academic_history'][8]['sgpa'] ?? 0,
                $profile['sslc_percentage'] ?? 0,
                $profile['puc_percentage'] ?? 0,
                $skillsStr,
                $projectsStr,
                $r['aptitude'],
                $r['technical'],
                $r['hr'],
                $r['total']
            ]);
            $count++;
        }

        echo json_encode(['success' => true, 'count' => $count]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
