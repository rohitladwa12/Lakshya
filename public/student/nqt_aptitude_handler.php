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

$userId = getUserId();
// Rate limit: 10 NQT aptitude requests per user per 60 seconds
if (!checkRateLimit("nqt_aptitude_{$userId}", 10, 60)) {
    http_response_code(429);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait before trying again.']);
    exit;
}

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
                    'answer' => match(strtoupper(trim($q['answer'] ?? 'A'))) { 'A'=>0, 'B'=>1, 'C'=>2, 'D'=>3, default=>0 },
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

            // Store in session for secure verification on submission
            $_SESSION['nqt_questions'] = $finalSet;

            // The browser gets question text + options only; the answer key stays in the session
            $publicSet = array_map(fn($q) => array_diff_key($q, ['answer' => true, 'explanation' => true]), $finalSet);

            ob_clean();
            echo json_encode(['success' => true, 'questions' => array_values($publicSet)]);
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

            $correctAnswersMap = [];
            $explanationsMap = [];
            if (isset($_SESSION['nqt_questions']) && is_array($_SESSION['nqt_questions'])) {
                foreach ($_SESSION['nqt_questions'] as $sq) {
                    $correctAnswersMap[trim($sq['question'])] = $sq['answer'];
                    $explanationsMap[trim($sq['question'])] = $sq['explanation'] ?? '';
                }
            }
            // Without the server's answer key the only answers available would be the browser's own
            if (empty($correctAnswersMap)) {
                jsonError("Your test session has expired. Please start the test again.");
            }
            $issuedCount = count($correctAnswersMap);
            $seenKeys = [];

            $score = 0;
            $gradedQuestions = [];
            foreach ($questions as $qIdx => $q) {
                $userAnswer = isset($answers[$qIdx]) ? (int)$answers[$qIdx] : null;
                $qText = trim($q['question'] ?? '');

                $isKnownQuestion = isset($correctAnswersMap[$qText]) && !isset($seenKeys[$qText]);
                if ($isKnownQuestion) {
                    $seenKeys[$qText] = true;
                    $correctAnswer = (int)$correctAnswersMap[$qText];
                    $q['explanation'] = $explanationsMap[$qText] ?? '';
                } else {
                    // Not a question we issued (or a duplicate): never score it from client data
                    error_log("NQT grading warning: Question not found in session registry: " . substr($qText, 0, 100));
                    $correctAnswer = -1;
                    $q['explanation'] = 'This question could not be verified and was not scored.';
                }

                if ($isKnownQuestion && $userAnswer !== null && $correctAnswer === $userAnswer) {
                    $score++;
                }

                $q['answer'] = $correctAnswer;
                $gradedQuestions[$qIdx] = $q;
            }

            // Clean up session
            unset($_SESSION['nqt_questions']);
            $questions = $gradedQuestions;

            // Out of every question issued, not only the ones the browser chose to send back
            $totalQuestions = max(count($questions), $issuedCount);
            $percentage = $totalQuestions > 0 ? ($score / $totalQuestions) * 100 : 0;
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
                    'total' => $totalQuestions,
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
