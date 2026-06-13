<?php
// MUST be first: prevents PHP warnings from polluting JSON output
ob_start();

/**
 * Skill Verification Handler
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

requireRole(ROLE_STUDENT);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST);
$action = $input['action'] ?? '';

$userId = getUserId();
$username = getUsername();
$studentIdForDb = getStudentIdForAssessment();
$institution = $_SESSION['institution'] ?? 'GMU';

require_once __DIR__ . '/../../src/Services/AIService.php';
$aiService = new AIService();

try {
    switch ($action) {
        case 'check_session':
            $portfolioId = $_POST['portfolio_id'] ?? 0;
            $stmt = getDB()->prepare("SELECT quiz_data FROM active_skill_quizzes WHERE student_id = ? AND portfolio_id = ?");
            $stmt->execute([$username, $portfolioId]);
            $quizRow = $stmt->fetch();
            if ($quizRow) {
                $quizData = json_decode($quizRow['quiz_data'], true);
                $safeQuestions = array_map(function($q) {
                    return ['question' => $q['question'], 'options' => $q['options']];
                }, $quizData['questions']);
                ob_clean(); echo json_encode([
                    'success' => true,
                    'has_active' => true,
                    'questions' => $safeQuestions
                ]);
            } else {
                ob_clean(); echo json_encode(['success' => true, 'has_active' => false]);
            }
            exit;

        case 'generate_quiz':
            $portfolioId = $_POST['portfolio_id'] ?? 0;
            
            // Verify ownership
            $stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
            $stmt->execute([$portfolioId, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Portfolio item not found.']);
                exit;
            }

            if ($item['category'] !== 'Skill') {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Only skills can be verified via MCQ quiz.']);
                exit;
            }

            $skill = $item['title'];
            $level = $item['sub_title'] ?: 'Intermediate';

            // Generate quiz (AI call — this is synchronous and may take a few seconds)
            $quizRes = $aiService->generateSkillQuiz($skill, $level);
            
            if ($quizRes['success']) {
                $quizObj = [
                    'portfolio_id' => $portfolioId,
                    'skill' => $skill,
                    'level' => $level,
                    'questions' => $quizRes['questions'],
                    'started_at' => time()
                ];
                
                // Persist quiz to database
                $stmt = getDB()->prepare("
                    INSERT INTO active_skill_quizzes (student_id, portfolio_id, quiz_data) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE quiz_data = VALUES(quiz_data)
                ");
                $stmt->execute([$username, $portfolioId, json_encode($quizObj)]);

                // Store quiz data in session
                $_SESSION['current_skill_quiz'] = $quizObj;

                // Return questions WITHOUT the answer index for safety
                $safeQuestions = array_map(function($q) {
                    return [
                        'question' => $q['question'],
                        'options' => $q['options']
                    ];
                }, $quizRes['questions']);

                ob_clean(); echo json_encode([
                    'success' => true,
                    'skill' => $skill,
                    'level' => $level,
                    'questions' => $safeQuestions
                ]);
            } else {
                ob_clean(); echo json_encode(['success' => false, 'message' => $quizRes['message'] ?? 'AI failed to generate quiz.']);
            }
            break;

        case 'submit_quiz':
            $answers = $input['answers'] ?? []; // Array of indices
            $portfolioId = $input['portfolio_id'] ?? ($_SESSION['current_skill_quiz']['portfolio_id'] ?? 0);
            
            $quizData = null;
            if ($portfolioId) {
                $stmt = getDB()->prepare("SELECT quiz_data FROM active_skill_quizzes WHERE student_id = ? AND portfolio_id = ?");
                $stmt->execute([$username, $portfolioId]);
                $quizRow = $stmt->fetch();
                if ($quizRow) {
                    $quizData = json_decode($quizRow['quiz_data'], true);
                }
            }
            
            if (!$quizData && isset($_SESSION['current_skill_quiz'])) {
                $quizData = $_SESSION['current_skill_quiz'];
                $portfolioId = $quizData['portfolio_id'];
            }

            if (!$quizData) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'No active quiz session found.']);
                exit;
            }

            $questions = $quizData['questions'];
            $correctCount = 0;
            $results = [];

            foreach ($questions as $idx => $q) {
                $userAns = isset($answers[$idx]) ? (int)$answers[$idx] : -1;
                $isCorrect = ($userAns === (int)$q['answer']);
                if ($isCorrect) $correctCount++;

                $results[] = [
                    'question' => $q['question'],
                    'user_answer' => $userAns,
                    'correct_answer' => (int)$q['answer'],
                    'explanation' => $q['explanation'],
                    'is_correct' => $isCorrect
                ];
            }

            $score = ($correctCount / count($questions)) * 100;
            $isPassed = ($score >= 70); // 70% to pass

            if ($isPassed) {
                // 1. Update Portfolio Table
                $sql = "UPDATE student_portfolio SET 
                        is_verified = 1, 
                        verification_score = ?, 
                        verification_date = CURRENT_TIMESTAMP,
                        verification_details = ? 
                        WHERE id = ?";
                $db = getDB();
                $db->prepare($sql)->execute([
                    $score,
                    json_encode($results),
                    $portfolioId
                ]);

                // 2. Sync to Unified AI Assessments Table
                try {
                    require_once __DIR__ . '/../../src/Models/StudentProfile.php';
                    $studentModel = new StudentProfile();
                    $profile = $studentModel->getByUserId($userId);

                    $sqlUnified = "INSERT INTO unified_ai_assessments (
                        student_id, institution, student_name, usn, aadhar,
                        current_sem, branch, assessment_type,
                        company_name, score, total_marks,
                        feedback, details, status, completed_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

                    $db->prepare($sqlUnified)->execute([
                        $studentIdForDb,
                        $institution,
                        $profile['name'] ?? getFullName(),
                        $profile['usn'] ?? getUsername(),
                        $profile['aadhar'] ?? null,
                        $profile['semester'] ?? null,
                        $profile['department'] ?? null,
                        'Skill Verification',
                        $quizData['skill'], // Using skill name as "Company Name" for tracking
                        $score,
                        100,
                        "Successfully verified skill: " . $quizData['skill'],
                        json_encode([
                            'transcript' => $results,
                            'skill' => $quizData['skill'],
                            'level' => $quizData['level'] ?? 'Intermediate'
                        ]),
                        'completed'
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to sync skill verification to unified table: " . $e->getMessage());
                }
            }

            // Cleanup database active quiz
            if ($portfolioId) {
                $stmtDel = getDB()->prepare("DELETE FROM active_skill_quizzes WHERE student_id = ? AND portfolio_id = ?");
                $stmtDel->execute([$username, $portfolioId]);
            }

            // Cleanup session
            unset($_SESSION['current_skill_quiz']);

            ob_clean(); echo json_encode([
                'success' => true,
                'score' => $score,
                'passed' => $isPassed,
                'correct_count' => $correctCount,
                'total_count' => count($questions),
                'results' => $results
            ]);
            break;

        default:
            ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    ob_clean(); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
