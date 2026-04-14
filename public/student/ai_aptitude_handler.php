<?php
// MUST be first: captures all PHP warnings (e.g. module load) before they pollute JSON output
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
$aiService = new AIService();
$studentModel = new StudentProfile();
set_time_limit(300);

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_questions':
            $companyName = post('company_name');
            $taskId = post('task_id');
            
            // Check if this is a coordinator task with manual questions
            if ($taskId) {
                $db = getDB();
                $stmt = $db->prepare("SELECT question_source, company_name FROM coordinator_tasks WHERE id = ?");
                $stmt->execute([$taskId]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($task && $task['question_source'] === 'manual') {
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
                        $q['answer'] = ord(strtoupper($q['answer'])) - ord('A'); // Convert A/B/C/D to 0/1/2/3
                        $q['category'] = 'Custom Question';
                        unset($q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']);
                    }
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'questions' => $questions]);
                    break;
                }
                
                // Use task's company name if available
                if ($task && $task['company_name']) {
                    $companyName = $task['company_name'];
                }
            }
            
            if (empty($companyName)) {
                echo json_encode(['success' => false, 'message' => 'Company name is required.']);
                exit;
            }

            // 1. Fetch 36 random questions from aptitude_questions table
            $db = getDB();
            $stmt = $db->query("SELECT question, option_a, option_b, option_c, option_d, correct_option as answer, topic as category 
                               FROM aptitude_questions 
                               ORDER BY RAND() 
                               LIMIT 36");
            $dbQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format DB questions
            foreach ($dbQuestions as &$q) {
                $q['options'] = [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']];
                $q['answer'] = ord(strtoupper($q['answer'])) - ord('A'); // Convert A/B/C/D to 0/1/2/3
                unset($q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']);
                $q['explanation'] = "Standard aptitude question from database.";
            }

            // 2. Offload to AI Worker for combined Aptitude Generation (In-Memory merge for now)
            require_once ROOT_PATH . '/src/Services/QueueService.php';
            $jobId = \App\Services\QueueService::pushJob('getCompanyAptitudeQuestions', [$companyName, 4], getUserId());

            ob_clean(); echo json_encode([
                'success' => true, 
                'job_id' => $jobId,
                'db_questions' => $dbQuestions,
                'message' => 'Generating domain-specific questions...'
            ]);
            break;

        case 'submit_test':
            $companyName = post('company_name');
            $answers = json_decode(post('answers'), true);
            // error_log("Aptitude Submission Start: Student=" . getUsername());
        
            $answers = json_decode($_POST['answers'] ?? '[]', true);
            $questions = json_decode($_POST['questions'] ?? '[]', true);
            $companyName = $_POST['company_name'] ?? 'Unknown';

            // error_log("Data decoded: Answers=" . count($answers) . ", Questions=" . count($questions));

            if (empty($questions) || empty($answers)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Incomplete test data.']);
                exit;
            }

            // Calculate score: use stored 'answer' index, but if explanation explicitly says "Correct answer: X"
            // and X matches an option, use that (fixes "reason says 6 but system counted 9").
            $score = 0;
            foreach ($questions as $qIdx => $q) {
                $userAnswer = isset($answers[$qIdx]) ? (int)$answers[$qIdx] : null;
                $correctAnswer = isset($q['answer']) ? (int)$q['answer'] : null;
                $options = $q['options'] ?? [];

                // Ensure answer index is within options (0 to 3)
                if ($correctAnswer === null || $correctAnswer < 0 || $correctAnswer >= count($options)) {
                    $correctAnswer = 0;
                    $questions[$qIdx]['answer'] = 0;
                }

                // Prefer the option that appears as the result of reasoning (e.g. "= 90", "i.e. 180 km")
                // over a self-contradictory "Correct answer: X" (e.g. "Correct answer: 72. ... = 90").
                if (!empty($q['explanation']) && count($options) > 0) {
                    $optionTrimmed = array_map(function ($o) { return trim((string)$o); }, $options);
                    $resolvedIdx = null;

                    // 1) Reasoning result: value after "=" or "i.e." or "equals" that matches an option
                    if (preg_match_all('/(?:=\s*|i\.e\.\s*|equals?\s+)([^.\n,]+)/iu', $q['explanation'], $m)) {
                        foreach (array_reverse($m[1]) as $captured) {
                            $val = trim($captured);
                            foreach ($optionTrimmed as $optIdx => $optVal) {
                                if ($optVal !== '' && $val === $optVal) {
                                    $resolvedIdx = (int)$optIdx;
                                    break 2;
                                }
                            }
                        }
                    }
                    // 2) Else use explicit "Correct answer: X" or "answer is X" if no reasoning result found
                    if ($resolvedIdx === null) {
                        $stated = null;
                        if (preg_match('/\b(?:Correct answer|correct answer)\s*:\s*([^.\n,]+)/iu', $q['explanation'], $m)) {
                            $stated = trim($m[1]);
                        } elseif (preg_match('/\b(?:correct answer is|answer is)\s+([^.\n,]+)/iu', $q['explanation'], $m)) {
                            $stated = trim($m[1]);
                        }
                        if ($stated !== null && $stated !== '') {
                            foreach ($optionTrimmed as $optIdx => $optVal) {
                                if ($optVal === $stated) {
                                    $resolvedIdx = (int)$optIdx;
                                    break;
                                }
                            }
                        }
                    }
                    if ($resolvedIdx !== null) {
                        $correctAnswer = $resolvedIdx;
                        $questions[$qIdx]['answer'] = $correctAnswer;
                    }
                }

                // If explanation doesn't mention the correct option text, prepend it for display
                if (isset($q['explanation']) && isset($options[$correctAnswer])) {
                    $correctOptionText = $options[$correctAnswer];
                    $containsCorrect = preg_match('/\b' . preg_quote($correctOptionText, '/') . '\b/', $q['explanation']);
                    if (!$containsCorrect) {
                        $questions[$qIdx]['explanation'] = "Correct answer: " . $correctOptionText . ". " . ($q['explanation'] ?? '');
                    }
                }

                /* error_log("Question $qIdx: User Answer = " . var_export($userAnswer, true) .
                         ", Correct Answer = " . var_export($correctAnswer, true) .
                         ", Raw Q Answer = " . var_export($q['answer'] ?? 'missing', true) .
                         ", Raw User Answer = " . var_export($answers[$qIdx] ?? 'missing', true)); */

                if ($userAnswer !== null && $correctAnswer !== null && $userAnswer === $correctAnswer) {
                    $score++;
                }
            }
            
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
                'usn'        => $fallbackUsn,
                'name'       => getFullName() ?: $fallbackUsn,
                'aadhar'     => null,
                'semester'   => null,
                'department' => getDepartment() ?? null,
                'institution'=> $inst,
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
} catch (Exception $e) {
    error_log("Aptitude Handler Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
