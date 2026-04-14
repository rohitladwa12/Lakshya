<?php
/**
 * Coding Practice Handler
 * Backend API for educational coding platform
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST);
$action = $input['action'] ?? '';

// Auth check
requireLogin();
$userId = getUserId();

$db = getDB();
$studentModel = new StudentProfile();

// Get correct student ID based on institution (same logic as career advisor)
function getStudentIdForCoding($studentProfile, $institution, $userId) {
    if ($institution === INSTITUTION_GMIT) {
        // Prioritize: enquiry_no (id) > usn > student_id (excluding 0)
        if (!empty($studentProfile['id']) && $studentProfile['id'] != 0) {
            return $studentProfile['id'];
        } else if (!empty($studentProfile['usn'])) {
            return $studentProfile['usn'];
        } else if (!empty($studentProfile['student_id']) && 
            $studentProfile['student_id'] != '0' && 
            $studentProfile['student_id'] != 0) {
            return $studentProfile['student_id'];
        } else {
            return $userId;
        }
    } else {
        // GMU: Use SL_NO (userId)
        return $userId;
    }
}

switch ($action) {
    case 'get_problems':
        // Fetch all problems with optional filters and student progress in one join
        $category = $input['category'] ?? null;
        $difficulty = $input['difficulty'] ?? null;
        $search = $input['search'] ?? null;
        
        // Get student's progress for each problem
        $studentProfile = $studentModel->getProfile($userId);
        $institution = $studentProfile['institution'] ?? INSTITUTION_GMU;
        $studentId = getStudentIdForCoding($studentProfile, $institution, $userId);

        $sql = "SELECT cp.id, cp.title, cp.category, cp.difficulty, IFNULL(scp.status, 'not_attempted') as status
                FROM coding_problems cp
                LEFT JOIN student_coding_progress scp ON cp.id = scp.problem_id 
                    AND scp.student_id = ? AND scp.institution = ?
                WHERE 1=1";
        $params = [$studentId, $institution];
        
        if ($category) {
            $sql .= " AND cp.category = ?";
            $params[] = $category;
        }
        
        if ($difficulty) {
            $sql .= " AND cp.difficulty = ?";
            $params[] = $difficulty;
        }
        
        if ($search) {
            $sql .= " AND cp.title LIKE ?";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY cp.difficulty, cp.id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $problems = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'problems' => $problems]);
        break;
        
    case 'get_problem':
        // Fetch single problem details
        $problemId = $input['problem_id'] ?? 0;
        
        $stmt = $db->prepare("SELECT * FROM coding_problems WHERE id = ?");
        $stmt->execute([$problemId]);
        $problem = $stmt->fetch();
        
        if (!$problem) {
            echo json_encode(['success' => false, 'message' => 'Problem not found']);
            exit;
        }
        
        // Get student's progress
        $studentProfile = $studentModel->getProfile($userId);
        $institution = $studentProfile['institution'] ?? INSTITUTION_GMU;
        $studentId = getStudentIdForCoding($studentProfile, $institution, $userId);
        
        $stmt = $db->prepare("SELECT * FROM student_coding_progress 
                              WHERE student_id = ? AND institution = ? AND problem_id = ?");
        $stmt->execute([$studentId, $institution, $problemId]);
        $progress = $stmt->fetch();
        
        $problem['progress'] = $progress ?: null;
        
        echo json_encode(['success' => true, 'problem' => $problem]);
        break;
        
    case 'save_progress':
        // Save student's code attempt
        $problemId = $input['problem_id'] ?? 0;
        $code = $input['code'] ?? '';
        $language = $input['language'] ?? 'JavaScript';
        $status = $input['status'] ?? 'attempted'; // attempted, solved, mastered
        
        $studentProfile = $studentModel->getProfile($userId);
        $institution = $studentProfile['institution'] ?? INSTITUTION_GMU;
        $studentId = getStudentIdForCoding($studentProfile, $institution, $userId);
        
        // Check if progress exists
        $stmt = $db->prepare("SELECT id, attempts FROM student_coding_progress 
                              WHERE student_id = ? AND institution = ? AND problem_id = ?");
        $stmt->execute([$studentId, $institution, $problemId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing progress
            $stmt = $db->prepare("UPDATE student_coding_progress 
                                  SET status = ?, attempts = attempts + 1, code_submitted = ?, 
                                      language_used = ?, last_attempt_date = CURRENT_TIMESTAMP 
                                  WHERE id = ?");
            $stmt->execute([$status, $code, $language, $existing['id']]);
        } else {
            // Insert new progress
            $stmt = $db->prepare("INSERT INTO student_coding_progress 
                                  (student_id, institution, problem_id, status, attempts, 
                                   code_submitted, language_used) 
                                  VALUES (?, ?, ?, ?, 1, ?, ?)");
            $stmt->execute([$studentId, $institution, $problemId, $status, $code, $language]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Progress saved']);
        break;
        
    case 'get_progress_stats':
        // Get student's overall progress statistics
        $studentProfile = $studentModel->getProfile($userId);
        $institution = $studentProfile['institution'] ?? INSTITUTION_GMU;
        $studentId = getStudentIdForCoding($studentProfile, $institution, $userId);
        
        // Total problems solved
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM student_coding_progress 
                              WHERE student_id = ? AND institution = ? AND status IN ('solved', 'mastered')");
        $stmt->execute([$studentId, $institution]);
        $solved = $stmt->fetchColumn();
        
        // Total problems attempted
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM student_coding_progress 
                              WHERE student_id = ? AND institution = ?");
        $stmt->execute([$studentId, $institution]);
        $attempted = $stmt->fetchColumn();
        
        // Category-wise breakdown
        $stmt = $db->prepare("SELECT cp.category, COUNT(*) as solved_count 
                              FROM student_coding_progress scp 
                              JOIN coding_problems cp ON scp.problem_id = cp.id 
                              WHERE scp.student_id = ? AND scp.institution = ? 
                                AND scp.status IN ('solved', 'mastered') 
                              GROUP BY cp.category");
        $stmt->execute([$studentId, $institution]);
        $categoryBreakdown = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_solved' => $solved,
                'total_attempted' => $attempted,
                'category_breakdown' => $categoryBreakdown
            ]
        ]);
        break;
        
    case 'generate_solution':
        // Generate AI-powered solution explanations using AIService
        $problemId = $input['problem_id'] ?? 0;
        
        $stmt = $db->prepare("SELECT * FROM coding_problems WHERE id = ?");
        $stmt->execute([$problemId]);
        $problem = $stmt->fetch();
        
        if (!$problem) {
            echo json_encode(['success' => false, 'message' => 'Problem not found']);
            exit;
        }

        // --- CACHING LOGIC ---
        // Check if we already have solutions cached in the database
        if (!empty($problem['solution_beginner']) && !empty($problem['solution_optimized'])) {
            // Solutions are stored as JSON in the database, decode them for the response
            echo json_encode([
                'success' => true, 
                'solutions' => [
                    'beginner' => json_decode($problem['solution_beginner'], true),
                    'optimized' => json_decode($problem['solution_optimized'], true)
                ],
                'cached' => true // Optional: flag to indicate this was a cached result
            ]);
            exit;
        }
        
        // Use central AI service if no cache exists
        session_write_close();
        $aiService = new AIService();
        $result = $aiService->generateCodingSolution($problem);
        
        if ($result['success'] && isset($result['solutions'])) {
            // Save the newly generated solutions to the database for future use
            $updateStmt = $db->prepare("UPDATE coding_problems SET solution_beginner = ?, solution_optimized = ? WHERE id = ?");
            $updateStmt->execute([
                json_encode($result['solutions']['beginner']),
                json_encode($result['solutions']['optimized']),
                $problemId
            ]);
        }
        
        echo json_encode($result);
        break;
        
    case 'get_categories':
        // Fetch all unique categories from coding_problems
        $stmt = $db->query("SELECT DISTINCT category FROM coding_problems ORDER BY category");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'categories' => $categories]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
