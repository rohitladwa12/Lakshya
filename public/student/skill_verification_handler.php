<?php
// MUST be first: prevents PHP warnings from polluting JSON output
ob_start();

/**
 * Skill Verification Handler
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? ''; // Restricted to POST for state-changing safety
requireRole(ROLE_STUDENT);

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
            if (isset($_SESSION['current_skill_quiz']) && $_SESSION['current_skill_quiz']['portfolio_id'] == $portfolioId) {
                ob_clean(); echo json_encode([
                    'success' => true,
                    'has_active' => true,
                    'questions' => $_SESSION['current_skill_quiz']['questions']
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

            session_write_close();
            $quizRes = $aiService->generateSkillQuiz($skill, $level);
            
            if ($quizRes['success']) {
                // Re-open session to store questions
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $_SESSION['current_skill_quiz'] = [
                    'portfolio_id' => $portfolioId,
                    'skill' => $skill,
                    'questions' => $quizRes['questions'],
                    'started_at' => time()
                ];

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
            $answers = $_POST['answers'] ?? []; // Array of indices
            $quizData = $_SESSION['current_skill_quiz'] ?? null;

            if (!$quizData) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'No active quiz session found.']);
                exit;
            }

            $questions = $quizData['questions'];
            $portfolioId = $quizData['portfolio_id'];
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
