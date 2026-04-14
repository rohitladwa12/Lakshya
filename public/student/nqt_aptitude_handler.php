<?php
/**
 * NQT Aptitude Handler
 * Handles AJAX requests for NQT tests using specific question tables.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Ensure user is logged in
requireLogin();

$action = post('action');
$aiService = new AIService();
$studentModel = new StudentProfile();
set_time_limit(300);
ob_start();

header('Content-Type: application/json');

try {
    $db = getDB();
    switch ($action) {
        case 'get_questions':
            $mode = post('mode') ?? 'foundation';
            $numQuestions = 40; // 40 Questions for 40 Mins

            // 1. Fetch questions from nqt_aptitude_questions
            $stmt = $db->query("SELECT id, question, option_a, option_b, option_c, option_d, correct_option as answer, topic as category 
                               FROM nqt_aptitude_questions 
                               ORDER BY RAND() 
                               LIMIT 30"); // Fetch more from NQT specific table
            $nqtQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 2. If not enough, fetch from aptitude_questions
            $remaining = $numQuestions - count($nqtQuestions);
            $dbQuestions = [];
            if ($remaining > 0) {
                $stmt = $db->query("SELECT id, question, option_a, option_b, option_c, option_d, correct_option as answer, topic as category 
                                   FROM aptitude_questions 
                                   ORDER BY RAND() 
                                   LIMIT $remaining");
                $dbQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $allQuestions = array_merge($nqtQuestions, $dbQuestions);
            
            // Format Questions for Mutation
            $mutationPool = [];
            foreach ($allQuestions as $q) {
                $mutationPool[] = [
                    'id' => $q['id'],
                    'question' => $q['question'],
                    'options' => [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']],
                    'answer' => ord(strtoupper($q['answer'] ?? 'A')) - ord('A'),
                    'category' => $q['category']
                ];
            }

            // 3. Use questions directly without AI mutation to reduce fetch time
            $finalSet = [];
            foreach ($mutationPool as $q) {
                if (!isset($q['explanation'])) {
                    $q['explanation'] = "Standard NQT aptitude question.";
                }
                $finalSet[] = $q;
            }

            // Shuffle and trim to requested count
            shuffle($finalSet);
            $finalSet = array_slice($finalSet, 0, $numQuestions);

            ob_clean();
            echo json_encode(['success' => true, 'questions' => $finalSet]);
            break;

        case 'submit_test':
            $answers = json_decode($_POST['answers'] ?? '[]', true);
            $questions = json_decode($_POST['questions'] ?? '[]', true);
            $mode = post('mode') ?? 'foundation';
            $assessmentType = "NQT " . ucfirst($mode);
            $companyName = "TCS NQT Practice";

            if (empty($questions)) {
                jsonError("Incomplete test data.");
            }

            $score = 0;
            foreach ($questions as $qIdx => $q) {
                $userAnswer = isset($answers[$qIdx]) ? (int)$answers[$qIdx] : null;
                $correctAnswer = isset($q['answer']) ? (int)$q['answer'] : null;
                if ($userAnswer !== null && $correctAnswer !== null && $userAnswer === $correctAnswer) {
                    $score++;
                }
            }
            
            $percentage = ($score / count($questions)) * 100;
            $studentId = getStudentIdForAssessment();
            $inst = getInstitution();
            $student = $studentModel->getByUserId(getUserId(), $inst);

            if (!$student) jsonError("Student profile not found.");

            $detailsJson = json_encode(['questions' => $questions, 'user_answers' => $answers, 'mode' => $mode]);
            
            $sql = "INSERT INTO unified_ai_assessments 
                    (student_id, usn, student_name, branch, current_sem, assessment_type, company_name, score, total_marks, details, status, started_at, completed_at, institution) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW(), ?)";
            
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                $studentId,
                $student['usn'],
                $student['name'],
                $student['department'] ?? null,
                $student['semester'] ?? null,
                $assessmentType,
                $companyName,
                $percentage,
                100, // Standardized total marks
                $detailsJson,
                $inst
            ]);

            if ($res) {
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'score' => $percentage,
                    'correct' => $score,
                    'total' => count($questions),
                    'results' => ['questions' => $questions, 'user_answers' => $answers]
                ]);
            } else {
                jsonError("Failed to save NQT assessment results.");
            }
            break;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
