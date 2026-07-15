<?php
// MUST be first: captures all PHP warnings (e.g. module load) before they pollute JSON output
if (ob_get_level() === 0)
    ob_start();

/**
 * AI Aptitude Handler
 * Handles AJAX requests for starting and submitting AI-generated aptitude tests
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Ensure user is logged in
requireLogin();

// Release session lock to prevent blocking concurrent requests from the same user
session_write_close();

$action = post('action');

// Rate Limit: 10 requests per minute
if (!checkRateLimit("ai_aptitude_api_" . getUserId(), 10, 60)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a minute.']);
    exit;
}
$aiService = new AIService();
$studentModel = new StudentProfile();
set_time_limit(300);

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_questions':
            // Limit test generation, but allow enough headroom for a legitimate
            // retry after a transient failure (1/min made any failed first
            // attempt lock the student out for a full minute).
            if (!checkRateLimit("ai_aptitude_gen_" . getUserId(), 3, 60)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Too many test-generation attempts. Please wait 60 seconds and retry.']);
                exit;
            }
            $companyName = post('company_name');
            $taskId = post('task_id');
            $concept = trim(post('concept') ?? '');
            $isAITask = false;

            // Check if this is a coordinator task with manual questions
            if ($taskId) {
                $db = getDB();
                $stmt = $db->prepare("SELECT question_source, company_name, concept FROM coordinator_tasks WHERE id = ?");
                $stmt->execute([$taskId]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($task) {
                    if ($task['question_source'] === 'manual') {
                        // Fetch manual questions
                        $stmt = $db->prepare("SELECT question_text as question,
                                                     option_a, option_b, option_c, option_d,
                                                     correct_option as answer,
                                                     explanation
                                              FROM task_manual_questions
                                              WHERE task_id = ?
                                              ORDER BY question_order");
                        $stmt->execute([$taskId]);
                        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Format for AI test interface
                        foreach ($questions as &$q) {
                            $q['options'] = [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']];
                            $ansLetter = strtoupper(trim($q['answer'] ?? 'A'));
                            $q['answer'] = match ($ansLetter) { 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, default => 0};
                            $q['category'] = 'Custom Question';
                            unset($q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']);
                        }

                        if (empty($questions)) {
                            ob_clean();
                            echo json_encode(['success' => false, 'message' => 'This task has no questions configured. Please contact your coordinator.']);
                            exit;
                        }

                        // Session lock was released at the top of this script — the
                        // session MUST be re-opened or this write is silently lost
                        // and grading falls back to client-supplied answers.
                        if (session_status() !== PHP_SESSION_ACTIVE) {
                            session_start();
                        }
                        $_SESSION['aptitude_db_questions'] = $questions;
                        $_SESSION['aptitude_ai_questions'] = [];
                        session_write_close();

                        ob_clean();
                        echo json_encode(['success' => true, 'questions' => $questions]);
                        break;
                    } elseif ($task['question_source'] === 'ai') {
                        $isAITask = true;
                        $concept = trim($task['concept'] ?? '');
                        if ($task['company_name']) {
                            $companyName = $task['company_name'];
                        }
                    }
                }
            } else {
                if (!empty($concept)) {
                    $isAITask = true;
                }
            }

            if (empty($companyName)) {
                $companyName = 'General';
            }

            if ($isAITask) {
                if (empty($concept)) {
                    $concept = 'General Aptitude and Logic';
                }

                // Push AI job to Queue if queue is available
                require_once __DIR__ . '/../../src/Services/QueueService.php';
                if (\App\Services\QueueService::isQueueAvailable()) {
                    $jobId = \App\Services\QueueService::pushJob(
                        'generateDriveRoundQuestions',
                        ['Aptitude', $concept, 40, $companyName],
                        getUserId()
                    );
                    if ($jobId) {
                        // Fetch the 40 random questions as fallback in case AI worker fails
                        $db = getDB();
                        $stmt = $db->query("SELECT question, option_a, option_b, option_c, option_d, correct_option as answer, topic as category 
                                           FROM aptitude_questions 
                                           ORDER BY RAND() 
                                           LIMIT 40");
                        $dbQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($dbQuestions as &$q) {
                            $q['options'] = [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']];
                            $ansLetter = strtoupper(trim($q['answer'] ?? 'A'));
                            $q['answer'] = match ($ansLetter) { 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, default => 0};
                            unset($q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']);
                            $q['explanation'] = "Standard aptitude question from database.";
                        }

                        if (session_status() !== PHP_SESSION_ACTIVE) {
                            session_start();
                        }
                        $_SESSION['aptitude_db_questions'] = $dbQuestions;
                        $_SESSION['aptitude_ai_questions'] = [];
                        session_write_close();

                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'job_id' => $jobId,
                            'db_questions' => $dbQuestions,
                            'message' => 'Queued AI question generation based on the concept: ' . $concept
                        ]);
                        break;
                    }
                }

                // Fallback to synchronous generation if Queue is not available
                try {
                    require_once __DIR__ . '/../../src/Services/AIService.php';
                    $aiService = new AIService();
                    $res = $aiService->generateDriveRoundQuestions('Aptitude', $concept, 40, $companyName);
                    if ($res['success'] && !empty($res['questions'])) {
                        $aiQuestions = $res['questions'];
                        foreach ($aiQuestions as &$q) {
                            if (isset($q['answer'])) {
                                if (is_string($q['answer'])) {
                                    $ansLetter = strtoupper(trim($q['answer']));
                                    if (in_array($ansLetter, ['A', 'B', 'C', 'D'])) {
                                        $q['answer'] = match($ansLetter) { 'A'=>0, 'B'=>1, 'C'=>2, 'D'=>3 };
                                    } else {
                                        $q['answer'] = (int)$q['answer'];
                                    }
                                }
                            } else {
                                $q['answer'] = 0;
                            }
                        }

                        if (session_status() !== PHP_SESSION_ACTIVE) {
                            session_start();
                        }
                        $_SESSION['aptitude_db_questions'] = [];
                        $_SESSION['aptitude_ai_questions'] = $aiQuestions;
                        session_write_close();

                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'questions' => $aiQuestions,
                            'message' => 'Generated questions synchronously with AI based on the concept: ' . $concept
                        ]);
                        break;
                    }
                } catch (Exception $e) {
                    error_log("Aptitude handler AI generation failed: " . $e->getMessage());
                }
            }

            // 1. Fetch 40 random questions from aptitude_questions table
            $db = getDB();
            $stmt = $db->query("SELECT question, option_a, option_b, option_c, option_d, correct_option as answer, topic as category 
                               FROM aptitude_questions 
                               ORDER BY RAND() 
                               LIMIT 40");
            $dbQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($dbQuestions)) {
                error_log("Aptitude get_questions: aptitude_questions table returned 0 rows");
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'No aptitude questions are available in the question bank. Please contact your coordinator.']);
                exit;
            }

            // Format DB questions
            foreach ($dbQuestions as &$q) {
                $q['options'] = [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']];
                $ansLetter = strtoupper(trim($q['answer'] ?? 'A'));
                $q['answer'] = match ($ansLetter) { 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, default => 0};
                unset($q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']);
                $q['explanation'] = "Standard aptitude question from database.";
            }

            // Re-open the session before writing (lock was released at startup;
            // without this the answer registry is never persisted).
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['aptitude_db_questions'] = $dbQuestions;
            $_SESSION['aptitude_ai_questions'] = [];
            session_write_close();

            ob_clean();
            echo json_encode([
                'success' => true,
                'questions' => $dbQuestions,
                'message' => 'Loaded questions from database.'
            ]);
            break;

        case 'submit_test':
            $answers = json_decode($_POST['answers'] ?? '[]', true);
            $questions = json_decode($_POST['questions'] ?? '[]', true);
            $companyName = $_POST['company_name'] ?? 'Unknown';

            if (empty($questions) || !is_array($questions)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Incomplete test data.']);
                exit;
            }
            // An empty answer set is valid (e.g. timer expired before the student
            // answered anything) — it simply grades to 0.
            if (!is_array($answers)) {
                $answers = [];
            }

            // Rebuild the lookup map from session data
            $correctAnswersMap = [];
            $explanationsMap = [];

            $normalizeKey = function ($text) {
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = str_replace(['"', '“', '”', '‘', '’', "'"], '', $text);
                $text = preg_replace('/\s+/', ' ', $text);
                return strtolower(trim($text));
            };

            $parseAnswerIndex = function ($val) {
                if (is_numeric($val)) {
                    return (int) $val;
                }
                if (is_string($val)) {
                    $val = strtoupper(trim($val));
                    if (in_array($val, ['A', 'B', 'C', 'D'])) {
                        return match ($val) { 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3};
                    }
                }
                return 0;
            };

            if (isset($_SESSION['aptitude_db_questions']) && is_array($_SESSION['aptitude_db_questions'])) {
                foreach ($_SESSION['aptitude_db_questions'] as $sq) {
                    $key = $normalizeKey($sq['question']);
                    $correctAnswersMap[$key] = $sq['answer'];
                    $explanationsMap[$key] = $sq['explanation'] ?? '';
                }
            }
            if (isset($_SESSION['aptitude_ai_questions']) && is_array($_SESSION['aptitude_ai_questions'])) {
                foreach ($_SESSION['aptitude_ai_questions'] as $sq) {
                    $key = $normalizeKey($sq['question']);
                    $correctAnswersMap[$key] = $sq['answer'];
                    $explanationsMap[$key] = $sq['explanation'] ?? '';
                }
            }

            $score = 0;
            $gradedQuestions = [];
            foreach ($questions as $qIdx => $q) {
                $userAnswer = isset($answers[$qIdx]) ? (int) $answers[$qIdx] : null;
                $qText = trim($q['question'] ?? '');
                $options = $q['options'] ?? [];

                // Retrieve correct answer from session lookup map
                $normalizedQText = $normalizeKey($qText);
                if (isset($correctAnswersMap[$normalizedQText])) {
                    $correctAnswer = $parseAnswerIndex($correctAnswersMap[$normalizedQText]);
                    $explanation = $explanationsMap[$normalizedQText] ?? ($q['explanation'] ?? '');
                } else {
                    // Fallback to client data but log a warning
                    error_log("Aptitude grading warning: Question not found in session registry. Question: " . substr($qText, 0, 100));
                    $correctAnswer = isset($q['answer']) ? $parseAnswerIndex($q['answer']) : 0;
                    $explanation = $q['explanation'] ?? '';
                }

                // Validate correct answer index
                if ($correctAnswer < 0 || $correctAnswer >= count($options)) {
                    error_log("Invalid correct answer index for question: " . json_encode($q));
                    $correctAnswer = 0;
                }

                // If explanation doesn't mention the correct option text, prepend it for display
                if (!empty($explanation) && isset($options[$correctAnswer])) {
                    $correctOptionText = $options[$correctAnswer];
                    $containsCorrect = preg_match('/\b' . preg_quote($correctOptionText, '/') . '\b/', $explanation);
                    if (!$containsCorrect) {
                        $explanation = "Correct answer: " . $correctOptionText . ". " . $explanation;
                    }
                }

                if ($userAnswer !== null && $correctAnswer === $userAnswer) {
                    $score++;
                }

                // Overwrite the client-sent answer and explanation in the returned results with secure verified values
                $q['answer'] = $correctAnswer;
                $q['explanation'] = $explanation;
                $gradedQuestions[$qIdx] = $q;
            }

            // Clean up session after test submission (re-open first — the lock
            // was released at startup, so an unset on the closed session would
            // never be persisted and stale questions would leak into the next test)
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            unset($_SESSION['aptitude_db_questions']);
            unset($_SESSION['aptitude_ai_questions']);
            session_write_close();
            $questions = $gradedQuestions;

            $percentage = ($score / count($questions)) * 100;
            error_log("Score calculated: $score/" . count($questions) . " ($percentage%)");

            // Use standard student identifier helper
            $studentId = getStudentIdForAssessment();
            $inst = getInstitution();

            error_log("Fetching student profile for: $studentId ($inst)");
            // Re-initialize studentModel if it was not available in this scope or if needed
            $studentModel = new StudentProfile();
            $student = $studentModel->getByUserId(getUserId(), $inst);

            if (!$student) {
                // Build a minimal fallback profile from session for unregistered students
                $fallbackUsn = getUsername();
                $student = [
                    'usn' => $fallbackUsn,
                    'name' => getFullName() ?: $fallbackUsn,
                    'aadhar' => null,
                    'semester' => null,
                    'department' => getDepartment() ?? null,
                    'institution' => $inst,
                ];
                error_log("Aptitude Submission: using fallback profile for unregistered USN: $fallbackUsn");
            }

            // Store result in unified_ai_assessments
            error_log("Preparing DB insertion into unified_ai_assessments");

            $detailsJson = json_encode(['questions' => $questions, 'user_answers' => $answers]);

            $db = getDB();
            $sql = "INSERT INTO unified_ai_assessments 
                    (student_id, usn, student_name, assessment_type, company_name, score, total_marks, details, status, started_at, completed_at, institution) 
                    VALUES (?, ?, ?, 'Aptitude', ?, ?, ?, ?, 'completed', NOW(), NOW(), ?)";

            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                $studentId,
                $student['usn'],
                $student['name'],
                $companyName,
                $percentage,
                count($questions),
                $detailsJson,
                $inst
            ]);

            error_log("DB Insertion " . ($res ? "SUCCESS" : "FAILED"));

            if ($res) {
                error_log("Aptitude Submission Completed successfully for " . $student['usn']);

                // Check if this is a coordinator task and record completion
                $taskId = $_POST['task_id'] ?? null;
                if ($taskId) {
                    $timeTaken = $_POST['time_taken'] ?? 0;
                    $stmt = $db->prepare("INSERT INTO task_completions 
                                          (task_id, student_id, score, time_taken) 
                                          VALUES (?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE 
                                          score = VALUES(score), 
                                          time_taken = VALUES(time_taken),
                                          completed_at = CURRENT_TIMESTAMP");
                    $stmt->execute([$taskId, $student['usn'], $percentage, $timeTaken]);
                    error_log("Task completion recorded for USN: {$student['usn']}, Task: $taskId");
                }

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'score' => $percentage,
                    'correct' => $score,
                    'total' => count($questions),
                    'results' => [
                        'questions' => $questions, // Use corrected questions with fixed explanations
                        'user_answers' => $answers
                    ],
                    'message' => 'Assessment completed successfully.'
                ]);
            } else {
                error_log("Aptitude Submission Error: DB Execute returned false");
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to save assessment results. Please try again.']);
                exit;
            }
            break;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
} catch (Throwable $e) {
    error_log("Aptitude Handler Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}