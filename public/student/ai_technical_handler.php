<?php
// MUST be first: prevents PHP warnings from polluting JSON output
if (ob_get_level() === 0)
    ob_start();

/**
 * AI Technical Round Handler
 * Manages sessions, questions, coding evaluation, and report generation.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

// Ensure JSON response
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Increase execution time for AI processing
set_time_limit(300);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST);
$action = $input['action'] ?? '';

// Auth Check
if (!isLoggedIn()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}
session_write_close();
$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment();

// Rate Limit: 60 requests per minute
if (!checkRateLimit("ai_tech_api_" . $userId, 60, 60)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a minute.']);
    exit;
}
$db = getDB();
$ai = new AIService();
$studentModel = new StudentProfile();

// Determine if we are running in recruitment drive mode
$driveId = isset($input['drive_id']) ? (int) $input['drive_id'] : 0;
$usn = getUsername();

$isDrive = false;
if (!empty($input['session_id'])) {
    // Drive attempts and practice sessions use separate id sequences, so the same id can exist
    // in both tables. Only the student's own attempt counts, and when the page says which mode
    // it is in (drive_id is sent with every call) that decides it.
    $stmt = $db->prepare("SELECT drive_id FROM student_drive_attempts WHERE id = ? AND student_id = ?");
    $stmt->execute([(int) $input['session_id'], $usn]);
    $driveAttempt = $stmt->fetch(PDO::FETCH_ASSOC);
    $clientDriveId = isset($input['drive_id']) ? (int) $input['drive_id'] : null;
    if ($driveAttempt && ($clientDriveId === null || $clientDriveId === (int) $driveAttempt['drive_id'])) {
        $isDrive = true;
        $driveId = (int) $driveAttempt['drive_id'];
    }
}

try {
    switch ($action) {
        case 'check_active_session':
            $company = $input['company'] ?? 'General';

            // Timed technical task (1 hour, 3 problems): resume only this task's own session
            $timedTask = (!$driveId && !empty($input['task_id'])) ? TaskAssessmentService::getTask($db, (int) $input['task_id']) : null;
            if ($timedTask && TaskAssessmentService::isTimed($timedTask) && $timedTask['task_type'] === 'technical') {
                $session = findTimedTechSession($db, $studentIdForDb, (int) $timedTask['id']);
                if (!$session) {
                    ob_clean();
                    echo json_encode(['success' => true, 'has_active' => false]);
                    exit;
                }
                $attempt = TaskAssessmentService::getAttempt($db, $timedTask['id'], getUsername(), $timedTask['duration_minutes']);
                $details = json_decode($session['details'], true) ?: [];
                ob_clean();
                echo json_encode(array_merge([
                    'success' => true,
                    'has_active' => true,
                    'session_id' => $session['id'],
                    'role' => $details['role'] ?? 'Software Engineer',
                ], timedTechPayload($details, $attempt)));
                exit;
            }

            if ($driveId > 0) {
                $stmt = $db->prepare("SELECT id, details, started_at FROM student_drive_attempts
                                 WHERE student_id = ? AND drive_id = ? AND round_type = 'Technical'
                                 AND status = 'In Progress'
                                 ORDER BY started_at DESC LIMIT 1");
                $stmt->execute([$usn, $driveId]);
            } else {
                $stmt = $db->prepare("SELECT id, details, started_at FROM unified_ai_assessments
                                 WHERE student_id = ? AND assessment_type = 'Technical'
                                 AND company_name = ? AND status = 'active'
                                 ORDER BY started_at DESC LIMIT 1");
                $stmt->execute([$studentIdForDb, $company]);
            }
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                $elapsed = time() - strtotime($session['started_at']);
                // Was 20 minutes — but assigned rounds REQUIRE at least 20 minutes, so any student
                // who refreshed or reconnected after that had the whole session closed with 0%.
                // Practice/assigned chat sessions can now be resumed for up to 3 hours.
                $maxDuration = 3 * 60 * 60;

                if ($driveId > 0) {
                    // Fetch actual drive duration
                    $stmtDrive = $db->prepare("SELECT technical_duration FROM campus_drives WHERE id = ?");
                    $stmtDrive->execute([$driveId]);
                    $dDuration = $stmtDrive->fetchColumn();
                    if ($dDuration)
                        $maxDuration = $dDuration * 60;
                }

                if ($elapsed > $maxDuration) {
                    // Expired! Auto close and assign 0 score
                    $autoSubmitReason = "Session resumed after maximum allowed duration ({$maxDuration}s).";
                    $details = json_decode($session['details'] ?? '{}', true);
                    $details['auto_submit_reason'] = $autoSubmitReason;
                    $detailsJson = json_encode($details);

                    if ($driveId > 0) {
                        $db->prepare("UPDATE student_drive_attempts SET status = 'Completed', score = 0, details = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
                            ->execute([$detailsJson, $session['id']]);
                    } else {
                        $db->prepare("UPDATE unified_ai_assessments SET status = 'completed', score = 0, details = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
                            ->execute([$detailsJson, $session['id']]);
                    }
                    ob_clean();
                    echo json_encode(['success' => true, 'has_active' => false, 'expired' => true]);
                    exit;
                }

                $details = json_decode($session['details'], true);
                $elapsedSeconds = time() - strtotime($session['started_at']);
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'has_active' => true,
                    'session_id' => $session['id'],
                    'started_at' => $session['started_at'],
                    'elapsed_seconds' => $elapsedSeconds,
                    'role' => $details['role'] ?? 'Software Engineer',
                    'concept' => $details['concept'] ?? '',
                    'history' => $details['history'] ?? []
                ]);
            } else {
                ob_clean();
                echo json_encode(['success' => true, 'has_active' => false]);
            }
            exit;

        case 'start_session':
            // Strict limit for starting new sessions (2 per minute)
            if (!checkRateLimit("ai_tech_start_" . $userId, 2, 60)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Slow down! You can only start 2 sessions per minute.']);
                exit;
            }
            $role = $input['role'] ?? 'Software Engineer';
            $company = $input['company'] ?? 'General';
            $concept = $input['concept'] ?? '';

            // Timed technical task: 3 pre-generated problem statements, server-side 1-hour clock
            $timedTask = (!$driveId && !empty($input['task_id'])) ? TaskAssessmentService::getTask($db, (int) $input['task_id']) : null;
            if ($timedTask && TaskAssessmentService::isTimed($timedTask) && $timedTask['task_type'] === 'technical') {
                $timedTaskId = (int) $timedTask['id'];
                $studentIds = TaskAssessmentService::studentIdentifiers();
                if (!TaskAssessmentService::isAssignedTo($timedTask, $studentIds)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'This assessment is not assigned to you.']);
                    exit;
                }
                if (TaskAssessmentService::isCompleted($db, $timedTaskId, $studentIds)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'You have already completed this assessment.']);
                    exit;
                }
                if ($timedTask['generation_status'] !== 'ready') {
                    ob_clean();
                    echo json_encode(['success' => false, 'preparing' => true, 'message' => 'The problem statements are still being prepared. Please wait a minute and try again.']);
                    exit;
                }
                // A double click / second tab must not create a second session (lock is
                // released automatically when this request's DB connection closes)
                $db->prepare("SELECT GET_LOCK(?, 10)")->execute(['tech_task_' . $timedTaskId . '_' . md5((string) $studentIdForDb)]);
                $existingSession = findTimedTechSession($db, $studentIdForDb, $timedTaskId);
                $attempt = TaskAssessmentService::getAttempt($db, $timedTaskId, getUsername(), $timedTask['duration_minutes']);
                if ($existingSession) {
                    $details = json_decode($existingSession['details'], true) ?: [];
                    ob_clean();
                    echo json_encode(array_merge(['success' => true, 'session_id' => $existingSession['id']], timedTechPayload($details, $attempt)));
                    exit;
                }
                if (!$attempt && !empty($timedTask['deadline']) && strtotime($timedTask['deadline']) < time()) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'The deadline for this assessment has passed.']);
                    exit;
                }

                $problems = [];
                foreach (TaskAssessmentService::getCodingProblems($db, $timedTaskId) as $p) {
                    $problems[] = [
                        'title' => $p['title'],
                        'problem_statement' => $p['problem_statement'],
                        'constraints' => $p['constraints'] ?? '',
                        'example_input' => $p['example_input'] ?? '',
                        'example_output' => $p['example_output'] ?? '',
                    ];
                }
                if (empty($problems)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'No problem statements are configured for this task. Please contact your coordinator.']);
                    exit;
                }

                $attempt = TaskAssessmentService::startAttempt($db, $timedTaskId, getUsername(), $timedTask['duration_minutes']);
                $profile = $studentModel->getByUserId($userId);
                $details = [
                    'role' => $role,
                    'concept' => $timedTask['concept'] ?? $concept,
                    'difficulty' => $timedTask['difficulty'] ?? 'Medium',
                    'history' => [],
                    'task_id' => $timedTaskId,
                    'timed' => true,
                    'problems' => $problems,
                    'submissions' => [],
                ];
                $db->prepare("INSERT INTO unified_ai_assessments (
                        student_id, institution, student_name, usn, aadhar, current_sem, branch,
                        assessment_type, company_name, status, details, started_at, total_marks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Technical', ?, 'active', ?, CURRENT_TIMESTAMP, 100)")
                    ->execute([
                        $studentIdForDb,
                        getInstitution(),
                        $profile['name'] ?? getFullName(),
                        $profile['usn'] ?? getUsername(),
                        $profile['aadhar'] ?? null,
                        $profile['semester'] ?? null,
                        $profile['department'] ?? null,
                        $timedTask['company_name'] ?: $company,
                        json_encode($details)
                    ]);
                $newSessionId = $db->lastInsertId();
                ob_clean();
                echo json_encode(array_merge(['success' => true, 'session_id' => $newSessionId], timedTechPayload($details, $attempt)));
                exit;
            }

            // Pull difficulty from the assigned task if available, else from input
            $difficulty = $input['difficulty'] ?? 'Medium';
            if (!empty($input['task_id'])) {
                try {
                    $stmtDiff = $db->prepare("SELECT difficulty FROM coordinator_tasks WHERE id = ? LIMIT 1");
                    $stmtDiff->execute([$input['task_id']]);
                    $diffRow = $stmtDiff->fetchColumn();
                    if ($diffRow) $difficulty = $diffRow;
                } catch (Exception $e) {}
            }

            $profile = $studentModel->getByUserId($userId);

            if ($driveId > 0) {
                // Select next attempt number
                $stmt = $db->prepare("
                SELECT MAX(attempt_number) FROM student_drive_attempts
                WHERE drive_id = ? AND student_id = ? AND round_type = 'Technical'
            ");
                $stmt->execute([$driveId, $usn]);
                $nextAttemptNum = (int) $stmt->fetchColumn() + 1;

                try {
                    // Get student info snapshot
                    $stmt = $db->prepare("
                    SELECT ads.*, u.NAME as name, u.DISCIPLINE as branch
                    FROM ad_student_approved ads
                    JOIN users u ON ads.usn = u.ID
                    WHERE ads.usn = ?
                ");
                    $stmt->execute([$usn]);
                    $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $studentInfo = false;
                }
                if (!$studentInfo) {
                    $studentInfo = [
                        'year' => 'N/A',
                        'name' => $profile['name'] ?? getFullName(),
                        'branch' => $profile['department'] ?? 'N/A',
                        'sem' => $profile['semester'] ?? 8
                    ];
                }

                $sql = "INSERT INTO student_drive_attempts (
                drive_id, round_type, attempt_number, academic_year, student_id, student_name, branch, sem,
                status, details, started_at
            ) VALUES (?, 'Technical', ?, ?, ?, ?, ?, ?, 'In Progress', ?, CURRENT_TIMESTAMP)";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $driveId,
                    $nextAttemptNum,
                    $studentInfo['year'] ?: 'N/A',
                    $usn,
                    $studentInfo['name'],
                    $studentInfo['branch'] ?: 'N/A',
                    (int) ($studentInfo['sem'] ?: 8),
                    json_encode([
                        'role' => $role,
                        'concept' => $concept,
                        'difficulty' => $difficulty,
                        'history' => [],
                        'task_id' => null
                    ])
                ]);
            } else {
                $sql = "INSERT INTO unified_ai_assessments (
                student_id, institution, student_name, usn, aadhar, current_sem, branch,
                assessment_type, company_name, status, details, started_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Technical', ?, 'active', ?, CURRENT_TIMESTAMP)";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $studentIdForDb,
                    getInstitution(),
                    $profile['name'] ?? getFullName(),
                    $profile['usn'] ?? getUsername(),
                    $profile['aadhar'] ?? null,
                    $profile['semester'] ?? null,
                    $profile['department'] ?? null,
                    $company,
                    json_encode([
                        'role' => $role,
                        'concept' => $concept,
                        'difficulty' => $difficulty,
                        'history' => [],
                        'task_id' => $input['task_id'] ?? null
                    ])
                ]);
            }

            ob_clean();
            echo json_encode(['success' => true, 'session_id' => $db->lastInsertId()]);
            break;

        case 'get_question':
            $sessionId = $input['session_id'];
            $userMessage = $input['message'] ?? ''; // Can be empty for first question

            // Fetch session
            if ($isDrive) {
                $stmt = $db->prepare("SELECT * FROM student_drive_attempts WHERE id = ? AND student_id = ?");
                $stmt->execute([$sessionId, $usn]);
            } else {
                $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
                $stmt->execute([$sessionId, $studentIdForDb]);
            }
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Session not found']);
                exit;
            }

            $details = json_decode($session['details'], true);
            if (!empty($details['timed'])) {
                // Timed rounds serve their fixed problem statements; there is no AI chat
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'This is a timed coding round. Use the problem tabs to switch problems.']);
                exit;
            }
            $history = $details['history'] ?? [];
            $role = $details['role'];
            $concept = $details['concept'] ?? '';
            $difficulty = $details['difficulty'] ?? 'Medium';

            // Append user message if exists
            if (!empty($userMessage)) {
                $history[] = ['role' => 'user', 'content' => $userMessage];
                $details['history'] = $history;
                if ($isDrive) {
                    $db->prepare("UPDATE student_drive_attempts SET details = ? WHERE id = ?")
                        ->execute([json_encode($details), $sessionId]);
                } else {
                    $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
                        ->execute([json_encode($details), $sessionId]);
                }
                logMessage("ai_technical_handler[get_question]: Appended User Message. Session $sessionId. History Size: " . count($history), "INFO");
            }

            // Fetch Student Portfolio/Skills
            $portfolioText = "";
            try {
                $stmt = $db->prepare("SELECT title, category, description FROM student_projects WHERE usn = ?");
                $stmt->execute([$usn]);
                $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($projects)) {
                    $portfolioText = "CANDIDATE'S REGISTERED SKILLS & PROJECTS:\n";
                    foreach ($projects as $idx => $p) {
                        $num = $idx + 1;
                        $portfolioText .= "{$num}. {$p['title']} [{$p['category']}]\n   Description: {$p['description']}\n";
                    }
                }
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }

            // Fetch previously asked questions for this student to prevent repetition
            $previousQuestions = [];
            if ($isDrive) {
                $stmtPrev = $db->prepare("SELECT details FROM student_drive_attempts WHERE drive_id = ? AND student_id = ? AND round_type = 'Technical' AND id != ?");
                $stmtPrev->execute([$driveId, $usn, $sessionId]);
                while ($row = $stmtPrev->fetch(PDO::FETCH_ASSOC)) {
                    $det = json_decode($row['details'], true);
                    if (!empty($det['history'])) {
                        foreach ($det['history'] as $msg) {
                            if ($msg['role'] === 'assistant') {
                                try {
                                    $parsed = json_decode($msg['content'], true);
                                    if ($parsed && !empty($parsed['question'])) {
                                        $previousQuestions[] = $parsed['question'];
                                    } elseif ($parsed && !empty($parsed['problem_statement'])) {
                                        $previousQuestions[] = $parsed['problem_statement'];
                                    }
                                } catch (Exception $e) {
                                }
                            }
                        }
                    }
                }
            }

            // Offload to Async Queue when available; otherwise fall back to a
            // synchronous AI call so questions still load with Redis/worker down.
            require_once ROOT_PATH . '/src/Services/QueueService.php';
            $jobId = null;
            try {
                if (\App\Services\QueueService::isQueueAvailable()) {
                    $jobId = \App\Services\QueueService::pushJob('getTechnicalQuestion', [$role, $history, $concept, $portfolioText, $previousQuestions, $difficulty], $userId);
                }
            } catch (Throwable $qe) {
                error_log("Technical get_question: queue unavailable, using sync fallback: " . $qe->getMessage());
                $jobId = null;
            }

            if ($jobId) {
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'job_id' => $jobId,
                    'message' => 'Thinking...'
                ]);
            } else {
                $syncRes = $ai->getTechnicalQuestion($role, $history, $concept, $portfolioText, $previousQuestions, $difficulty);
                if (!empty($syncRes['success']) && !empty($syncRes['result'])) {
                    ob_clean();
                    echo json_encode(['success' => true, 'sync' => true, 'result' => $syncRes['result']]);
                } else {
                    error_log("Technical get_question sync fallback failed: " . ($syncRes['message'] ?? 'unknown'));
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => $syncRes['message'] ?? 'The AI service is currently unavailable. Please retry.']);
                }
            }
            exit;

        case 'submit_code':
            $code = trim((string) ($input['code'] ?? ''));
            $language = (string) ($input['language'] ?? 'python');
            $problem = (string) ($input['problem_statement'] ?? '');
            $sessionId = $input['session_id'] ?? 0;

            if ($code === '') {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'No code submitted. Please write your solution in the editor first.']);
                exit;
            }

            // Timed round: evaluate against the server's copy of the problem and record the result
            if (!$isDrive && isset($input['problem_index'])) {
                $stmt = $db->prepare("SELECT id, details, status FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
                $stmt->execute([(int) $sessionId, $studentIdForDb]);
                $tSession = $stmt->fetch(PDO::FETCH_ASSOC);
                $tDetails = $tSession ? (json_decode($tSession['details'], true) ?: []) : [];
                if (!$tSession || empty($tDetails['timed'])) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Session not found.']);
                    exit;
                }
                if ($tSession['status'] !== 'active') {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'This round has already been submitted.']);
                    exit;
                }
                $pIndex = (int) $input['problem_index'];
                if (!isset($tDetails['problems'][$pIndex])) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Invalid problem.']);
                    exit;
                }
                $tTask = TaskAssessmentService::getTask($db, (int) $tDetails['task_id']);
                $attempt = $tTask ? TaskAssessmentService::getAttempt($db, $tTask['id'], getUsername(), $tTask['duration_minutes']) : null;
                // 60s grace covers a submit clicked in the final seconds
                if (!$attempt || $attempt['elapsed'] > $attempt['duration'] + 60) {
                    ob_clean();
                    echo json_encode(['success' => false, 'time_over' => true, 'message' => 'Time is over. Submissions are closed.']);
                    exit;
                }
                if (mb_strlen($code) > 50000) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Code is too long to evaluate (max 50,000 characters).']);
                    exit;
                }

                $p = $tDetails['problems'][$pIndex];
                $fullProblem = $p['title'] . "\n\n" . $p['problem_statement']
                    . ($p['constraints'] !== '' ? "\n\nConstraints: " . $p['constraints'] : '')
                    . ($p['example_input'] !== '' ? "\n\nExample Input: " . $p['example_input'] : '')
                    . ($p['example_output'] !== '' ? "\nExample Output: " . $p['example_output'] : '');

                $evalRes = $ai->evaluateCode($code, $language, $fullProblem);
                $result = (!empty($evalRes['success']) && is_array($evalRes['result'] ?? null)) ? $evalRes['result'] : null;
                if (!$result) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Code evaluation is temporarily unavailable. Your code is safe in the editor — please click Submit again.']);
                    exit;
                }
                $evalScore = max(0, min(10, (float) ($result['score'] ?? 0)));
                $passed = !empty($result['passed']) && $result['passed'] !== 'false';
                $feedback = is_string($result['feedback'] ?? null) ? $result['feedback'] : '';

                // Read-modify-write under a row lock so concurrent requests can't drop a submission
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ? FOR UPDATE");
                    $stmt->execute([(int) $sessionId]);
                    $lockedDetails = json_decode($stmt->fetchColumn(), true) ?: [];
                    $sub = $lockedDetails['submissions'][$pIndex] ?? ['attempts' => 0, 'best_score' => 0, 'passed' => false];
                    $sub['attempts'] = (int) ($sub['attempts'] ?? 0) + 1;
                    if ($evalScore >= (float) ($sub['best_score'] ?? 0)) {
                        $sub['best_score'] = $evalScore;
                        $sub['best_code'] = $code;
                        $sub['best_language'] = $language;
                        $sub['best_feedback'] = $feedback;
                    }
                    $sub['passed'] = !empty($sub['passed']) || $passed;
                    $sub['last_score'] = $evalScore;
                    $sub['last_feedback'] = $feedback;
                    $sub['last_submitted_at'] = date('Y-m-d H:i:s');
                    $lockedDetails['submissions'][$pIndex] = $sub;
                    $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
                        ->execute([json_encode($lockedDetails), (int) $sessionId]);
                    $db->commit();
                } catch (Throwable $txe) {
                    $db->rollBack();
                    throw $txe;
                }

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'sync' => true,
                    'result' => ['score' => $evalScore, 'passed' => $passed, 'feedback' => $feedback],
                    'best_score' => $sub['best_score'],
                    'attempts' => $sub['attempts'],
                ]);
                exit;
            }

            if ($problem === '') {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'No active coding challenge found for this submission.']);
                exit;
            }

            // Offload Evaluation to Queue when available; otherwise evaluate
            // synchronously so "Run & Check" always produces a result.
            require_once ROOT_PATH . '/src/Services/QueueService.php';
            $jobId = null;
            try {
                if (\App\Services\QueueService::isQueueAvailable()) {
                    $jobId = \App\Services\QueueService::pushJob('evaluateCode', [$code, $language, $problem], $userId);
                }
            } catch (Throwable $qe) {
                error_log("Technical submit_code: queue unavailable, using sync fallback: " . $qe->getMessage());
                $jobId = null;
            }

            if ($jobId) {
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'job_id' => $jobId,
                    'message' => 'Evaluating code...'
                ]);
            } else {
                $syncRes = $ai->evaluateCode($code, $language, $problem);
                if (!empty($syncRes['success']) && isset($syncRes['result'])) {
                    appendCodeEvaluationToHistory($db, $isDrive, $sessionId, $isDrive ? $usn : $studentIdForDb, $code, $language, $syncRes['result']);
                    ob_clean();
                    echo json_encode(['success' => true, 'sync' => true, 'result' => $syncRes['result']]);
                } else {
                    error_log("Technical submit_code sync fallback failed: " . ($syncRes['message'] ?? 'unknown'));
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => $syncRes['message'] ?? 'Code evaluation service is unavailable. Please retry.']);
                }
            }
            exit;

        case 'record_evaluation':
            // The page reports a finished queued code check; the result is read from the job
            // itself (never from the browser) and must belong to this student
            $jobId = (string) ($input['job_id'] ?? '');
            $sessionId = (int) ($input['session_id'] ?? 0);
            require_once ROOT_PATH . '/src/Services/QueueService.php';
            $job = $jobId !== '' ? \App\Services\QueueService::getJobStatus($jobId) : null;
            if (!$job || ($job['method'] ?? '') !== 'evaluateCode' || ($job['status'] ?? '') !== 'completed'
                || trim((string) ($job['user_id'] ?? '')) !== trim((string) $userId)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Evaluation not found.']);
                exit;
            }
            [$jobCode, $jobLang] = array_pad((array) ($job['args'] ?? []), 2, '');
            $saved = appendCodeEvaluationToHistory($db, $isDrive, $sessionId, $isDrive ? $usn : $studentIdForDb, $jobCode, $jobLang, $job['result'] ?? null, $jobId);
            ob_clean();
            echo json_encode(['success' => (bool) $saved]);
            exit;

        case 'append_ai_history':
            $sessionId = $input['session_id'];
            $aiMessage = $input['message'];

            if (!empty($aiMessage)) {
                if ($isDrive) {
                    $stmt = $db->prepare("SELECT details FROM student_drive_attempts WHERE id = ? AND student_id = ?");
                    $stmt->execute([$sessionId, $usn]);
                } else {
                    $stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
                    $stmt->execute([$sessionId, $studentIdForDb]);
                }
                if ($session = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $details = json_decode($session['details'], true);
                    if (!isset($details['history']))
                        $details['history'] = [];
                    $details['history'][] = ['role' => 'assistant', 'content' => $aiMessage];
                    if ($isDrive) {
                        $db->prepare("UPDATE student_drive_attempts SET details = ? WHERE id = ?")
                            ->execute([json_encode($details), $sessionId]);
                    } else {
                        $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
                            ->execute([json_encode($details), $sessionId]);
                    }
                    logMessage("ai_technical_handler[append_ai_history]: Appended AI Message. Session $sessionId. History Size: " . count($details['history']), "INFO");
                } else {
                    logMessage("ai_technical_handler[append_ai_history]: Session $sessionId NOT FOUND", "ERROR");
                }
            }
            ob_clean();
            echo json_encode(['success' => true]);
            exit;

        case 'generate_report_data':
            $sessionId = $input['session_id'];

            // Ownership check: without the student_id constraint any logged-in
            // student could finalize/score another student's session (IDOR).
            if ($isDrive) {
                $stmt = $db->prepare("SELECT * FROM student_drive_attempts WHERE id = ? AND student_id = ?");
                $stmt->execute([$sessionId, $usn]);
            } else {
                $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
                $stmt->execute([$sessionId, $studentIdForDb]);
            }
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Session not found or access denied.']);
                exit;
            }

            $details = json_decode($session['details'], true);
            $role = $details['role'];
            $concept = $details['concept'] ?? '';
            $history = $details['history'];
            $taskId = $details['task_id'] ?? null;

            // A proctor auto-termination must always be able to submit, even
            // before the 20-minute minimum (it used to be rejected here).
            $proctorTerminated = false;
            $pRow = \App\Services\ProctoringService::resolveStudentProctorSession(
                $db, $input['proctor_token'] ?? '', [getUsername(), getUserId()], 'technical'
            );
            $proctorTerminated = $pRow && ($pRow['status'] === 'terminated' || (int) $pRow['strike_count'] >= 3);

            // Check Minimum Time Requirement (20 mins = 1200 seconds) for assigned tasks
            // (Timed rounds have their own fixed clock and can be ended early.)
            if ($taskId && !$isDrive && !$proctorTerminated && empty($details['timed'])) {
                $startTime = strtotime($session['started_at']);
                $elapsed = time() - $startTime;
                if ($elapsed < 1200) {
                    $rem = 1200 - $elapsed;
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => "Session duration too short. Please participate for at least 20 minutes (Remaining: " . ceil($rem / 60) . " mins)."]);
                    exit;
                }
            }

            // Check for any user interaction
            $userInteractions = 0;
            foreach ($history as $msg) {
                if ($msg['role'] === 'user' && !empty(trim($msg['content']))) {
                    $userInteractions++;
                }
            }

            if (!empty($details['timed'])) {
                // Timed coding round: score = best evaluated score per problem (each /10), as a percentage
                [$score, $timedReport] = TaskAssessmentService::scoreTimedTechnical($details);
                $reportRes = ['success' => true, 'content' => $timedReport];
            } elseif ($userInteractions === 0) {
                $score = 0;
            } else {
                // Generate Text Report via AI
                session_write_close();
                $reportRes = $ai->generateTechnicalInterviewReport($role, $history, 'Technical', $concept);

                if ($reportRes['success']) {
                    $score = $reportRes['overall_score'];
                } else {
                    error_log("Technical Report generation failed for session $sessionId: " . ($reportRes['message'] ?? 'Unknown Error'));
                    // Used to save 0% permanently on the first AI hiccup. Let the student retry
                    // (answers are already saved); only after 3 failed attempts fall back to 0.
                    $failures = (int) ($details['report_failures'] ?? 0) + 1;
                    $details['report_failures'] = $failures;
                    $saveTable = $isDrive ? 'student_drive_attempts' : 'unified_ai_assessments';
                    $db->prepare("UPDATE {$saveTable} SET details = ? WHERE id = ?")->execute([json_encode($details), $sessionId]);
                    if ($failures < 3 && !$proctorTerminated) {
                        ob_clean();
                        echo json_encode(['success' => false, 'retry' => true, 'message' => 'Your score could not be generated because the AI service is busy. Your answers are saved — please click End Session again in a few seconds.']);
                        exit;
                    }
                    $score = 0; // Fallback score so the submission can complete
                }
            }



            try {
                // Retrieve Proctoring Session & calculate authoritative penalty deduction
                $penaltyPct = 0.0;
                $strikeCount = 0;
                $isTerminated = false;

                $pSess = $pRow ?: \App\Services\ProctoringService::resolveStudentProctorSession(
                    $db, $input['proctor_token'] ?? '', [getUsername(), getUserId()], 'technical'
                );
                if ($pSess) {
                    $penaltyPct = (float)$pSess['penalty_pct'];
                    $strikeCount = (int)$pSess['strike_count'];
                    $isTerminated = ($pSess['status'] === 'terminated' || $strikeCount >= 3);
                }

                $rawScore = $score;
                // Option A: a terminated test keeps its earned marks minus the termination penalty
                // and is flagged for coordinator review (it used to be set to 0)
                // A session also ends as 'terminated' when the connection is lost for over 2 minutes
                // (no strikes) — that keeps the marks with only the strike penalties already applied
                $strikeTerminated = $isTerminated && $strikeCount >= \App\Services\ProctoringService::MAX_STRIKES;
                if ($strikeTerminated) {
                    $penaltyPct = max($penaltyPct, \App\Services\ProctoringService::PENALTY_TERMINATED);
                }
                $finalScore = round(\App\Services\ProctoringService::finalScoreAfterProctoring($rawScore, $penaltyPct, $strikeTerminated));

                if ($isDrive) {
                    // Fetch details first to securely update them
                    $stmt = $db->prepare("SELECT details FROM student_drive_attempts WHERE id = ?");
                    $stmt->execute([$sessionId]);
                    $details = json_decode($stmt->fetchColumn() ?? '{}', true);

                    if (isset($reportRes['content'])) {
                        $details['report_content'] = $reportRes['content'];
                    }
                    $details['raw_score'] = $rawScore;
                    $details['penalty_pct'] = $penaltyPct;
                    $details['strike_count'] = $strikeCount;
                    $details['final_score'] = $finalScore;
                    $details['proctor_review'] = $isTerminated;

                    // Finalize Status immediately
                    $db->prepare("UPDATE student_drive_attempts
                              SET score = ?, status = 'Completed', completed_at = CURRENT_TIMESTAMP, details = ?
                              WHERE id = ?")
                        ->execute([$finalScore, json_encode($details), $sessionId]);
                } else {
                    // Decode current details to safely append
                    $stmt = $db->prepare("SELECT details, started_at, usn FROM unified_ai_assessments WHERE id = ?");
                    $stmt->execute([$sessionId]);
                    $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
                    $details = json_decode($sessionData['details'] ?? '{}', true);

                    if (isset($reportRes['content'])) {
                        $details['report_content'] = $reportRes['content'];
                    }
                    $details['raw_score'] = $rawScore;
                    $details['penalty_pct'] = $penaltyPct;
                    $details['strike_count'] = $strikeCount;
                    $details['final_score'] = $finalScore;
                    $details['proctor_review'] = $isTerminated;

                    // Finalize Status immediately so closing the browser doesn't orphan the completion
                    $db->prepare("UPDATE unified_ai_assessments
                              SET score = ?, feedback = ?, status = 'completed', completed_at = CURRENT_TIMESTAMP, details = ?
                              WHERE id = ?")
                        ->execute([$finalScore, "Report Generated", json_encode($details), $sessionId]);

                    // Insert into task_completions immediately if it's an assigned task
                    if (isset($details['task_id']) && $details['task_id']) {
                        $taskId = $details['task_id'];
                        $studentUsn = $sessionData['usn'] ?? getUsername();
                        $timeTaken = time() - strtotime($sessionData['started_at']);
                        if (!empty($details['timed'])) {
                            // Server-side clock, capped at the round duration
                            $tTask = TaskAssessmentService::getTask($db, (int) $taskId);
                            $tAttempt = $tTask ? TaskAssessmentService::getAttempt($db, $taskId, getUsername(), $tTask['duration_minutes']) : null;
                            if ($tAttempt) {
                                $timeTaken = min($tAttempt['elapsed'], $tAttempt['duration']);
                            }
                        }

                        $stmtComp = $db->prepare("INSERT INTO task_completions
                                          (task_id, student_id, score, time_taken)
                                          VALUES (?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE
                                          score = VALUES(score),
                                          time_taken = VALUES(time_taken),
                                          completed_at = CURRENT_TIMESTAMP");
                        $stmtComp->execute([$taskId, $studentUsn, $finalScore, $timeTaken]);
                        error_log("Task completion auto-recorded for Technical round. Task: $taskId, USN: $studentUsn");
                    }
                }
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'score' => $finalScore,
                    'raw_score' => $rawScore,
                    'penalty_pct' => $penaltyPct,
                    'strike_count' => $strikeCount
                ]);

            } catch (Throwable $e) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'DB finalize failed: ' . $e->getMessage()]);
            }
            break;

        case 'save_pdf_report':
            $sessionId = $_POST['session_id'] ?? 0;

            if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
                $dir = REPORTS_UPLOAD_PATH . '/technical/';
                if (!is_dir($dir))
                    mkdir($dir, 0777, true);

                // Ownership check (IDOR guard): only the session owner may attach a PDF
                if ($isDrive) {
                    $stmtHash = $db->prepare("SELECT student_id as usn FROM student_drive_attempts WHERE id = ? AND student_id = ?");
                    $stmtHash->execute([$sessionId, $usn]);
                } else {
                    $stmtHash = $db->prepare("SELECT usn FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
                    $stmtHash->execute([$sessionId, $studentIdForDb]);
                }
                $usnForHash = $stmtHash->fetchColumn();
                if ($usnForHash === false) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Session not found or access denied.']);
                    exit;
                }
                $secureHash = sha1($usnForHash . $sessionId . 'LAKSHYA_SALT_2024');
                $filename = "report_" . $secureHash . ".pdf";
                $targetPath = $dir . $filename;

                if (move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
                    $publicPath = "uploads/reports/technical/" . $filename;

                    // Finalize DB
                    if ($isDrive) {
                        $stmt = $db->prepare("SELECT details FROM student_drive_attempts WHERE id = ?");
                        $stmt->execute([$sessionId]);
                        $details = json_decode($stmt->fetchColumn(), true);

                        $details['report_path'] = $publicPath;

                        $db->prepare("UPDATE student_drive_attempts SET status = 'Completed', details = ? WHERE id = ?")
                            ->execute([json_encode($details), $sessionId]);
                    } else {
                        $stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
                        $stmt->execute([$sessionId]);
                        $details = json_decode($stmt->fetchColumn(), true);

                        $details['report_path'] = $publicPath;

                        $db->prepare("UPDATE unified_ai_assessments SET status = 'completed', details = ? WHERE id = ?")
                            ->execute([json_encode($details), $sessionId]);

                        if (isset($details['task_id']) && $details['task_id']) {
                            $taskId = $details['task_id'];

                            // Fetch accurate score and student info
                            $stmtInfo = $db->prepare("SELECT score, usn, started_at FROM unified_ai_assessments WHERE id = ?");
                            $stmtInfo->execute([$sessionId]);
                            $sessionData = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                            $finalScore = $sessionData['score'] ?? 0;
                            $studentUsn = $sessionData['usn'] ?? getUsername();
                            $timeTaken = time() - strtotime($sessionData['started_at']);

                            $stmtComp = $db->prepare("INSERT INTO task_completions
                                              (task_id, student_id, score, time_taken)
                                              VALUES (?, ?, ?, ?)
                                              ON DUPLICATE KEY UPDATE
                                              score = VALUES(score),
                                              time_taken = VALUES(time_taken),
                                              completed_at = CURRENT_TIMESTAMP");
                            $stmtComp->execute([$taskId, $studentUsn, $finalScore, $timeTaken]);
                            error_log("Task completion recorded for technical round. Task: $taskId, USN: $studentUsn, Score: $finalScore, Time: $timeTaken");
                        }
                    }

                    ob_clean();
                    echo json_encode(['success' => true, 'path' => $publicPath]);
                } else {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'File save failed']);
                }
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            }
            break;
    }
} catch (Throwable $e) {
    error_log("Technical Handler Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}

/**
 * Allow safe HTML from AI report (bold, breaks, lists) so it renders; strip script/style.
 */
function allowReportHtml($content)
{
    $allowed = '<b><strong><br><p><ul><ol><li><em><i><h2><h3><h4><span>';
    $cleaned = strip_tags($content, $allowed);
    return $cleaned ?: htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8');
}

function generateReportHTML($usn, $sem, $name, $role, $score, $content)
{
    // Basic Markdown Conversion
    $htmlContent = allowReportHtml($content);
    $htmlContent = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $htmlContent);
    $htmlContent = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $htmlContent);
    $htmlContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $htmlContent);
    $htmlContent = preg_replace('/^- (.*$)/m', '<li>$1</li>', $htmlContent);
    $htmlContent = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $htmlContent);
    $htmlContent = str_replace('</ul><ul>', '', $htmlContent);

    // Score label
    $label = 'BEGINNER';
    $color = '#ef4444';
    if ($score >= 80) {
        $label = 'EXCEPTIONAL';
        $color = '#10b981';
    } elseif ($score >= 60) {
        $label = 'READY FOR HIRE';
        $color = '#10b981';
    } elseif ($score >= 40) {
        $label = 'NEEDS PRACTICE';
        $color = '#f59e0b';
    }

    return "
    <html>
    <head>
        <meta charset='UTF-8'>
        <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
        <style>
            :root { --primary-maroon: #800000; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #fff; color: #333; line-height: 1.6; margin: 0; padding: 40px; }
            .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 8px; }
            .header { border-bottom: 3px solid #800000; padding-bottom: 20px; margin-bottom: 30px; }
            .header h1 { color: #800000; margin: 0; font-size: 2rem; text-transform: uppercase; }
            .header p { margin: 2px 0; font-weight: 600; color: #666; }
            .score-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 30px; border-radius: 12px; margin-bottom: 35px; border-left: 5px solid #800000; display: flex; justify-content: space-between; align-items: center; }
            .score-card h3 { margin: 0; color: #444; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
            .score-card p { margin: 5px 0 0; color: #666; font-size: 0.85rem; }
            .score-value { text-align: right; }
            .score-num { font-size: 3rem; font-weight: 900; color: #800000; line-height: 1; }
            .score-label { font-size: 0.75rem; font-weight: 800; margin-top: 5px; color: {$color}; }
            .report-body strong { color: #800000; }
            .report-body ul { padding-left: 20px; }
            .report-body li { margin-bottom: 8px; }
            .report-body h2 { color: #800000; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 25px; font-size: 1.5rem; }
            .report-body h3 { color: #444; margin-top: 20px; font-size: 1.2rem; }
            .footer { margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; font-size: 0.8rem; color: #999; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <table width='100%'>
                    <tr>
                        <td align='left'>
                            <h1>GM UNIVERSITY</h1>
                            <p>Training & Placement Cell</p>
                        </td>
                        <td align='right'>
                            <p>Student: {$name}</p>
                            <p>USN: {$usn}</p>
                            <p>Role: {$role}</p>
                            <p>Date: " . date('d M Y') . "</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class='score-card'>
                <table width='100%'>
                    <tr>
                        <td align='left'>
                            <h3>Technical Assessment Score</h3>
                            <p>Based on technical accuracy, communication, and problem-solving.</p>
                        </td>
                        <td align='right' class='score-value'>
                            <div class='score-num'>{$score}<span style='font-size: 1.2rem; color: #999;'>/100</span></div>
                            <div class='score-label'>{$label}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class='report-body'>
                {$htmlContent}
            </div>

            <div class='footer'>
                This is an AI-generated assessment report by GM University Placement Portal.<br>
                It is intended for student evaluation and preparation purposes only.
            </div>
        </div>
    </body>
    </html>
    ";
}
// Remove old function
function old_generatePDFReport()
{
}
/**
 * The student's still-active session for a timed technical task, if any.
 */
function findTimedTechSession(PDO $db, $studentIdForDb, $taskId)
{
    $stmt = $db->prepare("SELECT id, details, started_at FROM unified_ai_assessments
                          WHERE student_id = ? AND assessment_type = 'Technical' AND status = 'active'
                            AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.task_id')) = ?
                          ORDER BY id DESC");
    $stmt->execute([$studentIdForDb, (string) (int) $taskId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = json_decode($row['details'], true);
        if (!empty($d['timed'])) {
            return $row;
        }
    }
    return null;
}

/**
 * What the page needs to (re)build a timed round: problems, per-problem progress and
 * the remaining time from the server-side clock. Submitted code is included so a
 * refresh restores the student's work.
 */
function timedTechPayload(array $details, $attempt)
{
    $submissions = [];
    foreach (($details['problems'] ?? []) as $i => $_) {
        $s = $details['submissions'][$i] ?? null;
        $submissions[$i] = $s ? [
            'attempts' => (int) ($s['attempts'] ?? 0),
            'best_score' => (float) ($s['best_score'] ?? 0),
            'passed' => !empty($s['passed']),
            'last_feedback' => $s['last_feedback'] ?? '',
            'best_code' => $s['best_code'] ?? '',
            'best_language' => $s['best_language'] ?? '',
        ] : null;
    }
    return [
        'timed' => true,
        'problems' => array_values($details['problems'] ?? []),
        'submissions' => $submissions,
        'duration_seconds' => $attempt['duration'] ?? TaskAssessmentService::TECHNICAL_DURATION * 60,
        'time_remaining' => $attempt['remaining'] ?? 0,
    ];
}

/**
 * Saves a code submission and its evaluation into the chat-round transcript.
 * Code answers used to be invisible to the final report (only a passed submission left a
 * one-line note), so students who answered in the code editor scored 0 "for no answers".
 * Uses the "Evaluation: {...}" system-message format generateTechnicalInterviewReport reads.
 * $jobKey de-duplicates queue results that the page may report more than once.
 */
function appendCodeEvaluationToHistory(PDO $db, $isDrive, $sessionId, $ownerId, $code, $language, $result, $jobKey = null)
{
    if (!is_array($result)) {
        return false;
    }
    $table = $isDrive ? 'student_drive_attempts' : 'unified_ai_assessments';
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT details FROM {$table} WHERE id = ? AND student_id = ? FOR UPDATE");
        $stmt->execute([(int) $sessionId, (string) $ownerId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            $db->rollBack();
            return false;
        }
        $details = json_decode($raw, true) ?: [];
        if ($jobKey !== null && in_array($jobKey, $details['recorded_jobs'] ?? [], true)) {
            $db->rollBack();
            return true;
        }
        $score = max(0, min(10, (float) ($result['score'] ?? 0)));
        $passed = !empty($result['passed']) && $result['passed'] !== 'false';
        $feedback = is_string($result['feedback'] ?? null) ? $result['feedback'] : '';

        $details['history'][] = ['role' => 'user', 'content' => "[Code submission in {$language}]\n" . mb_substr((string) $code, 0, 8000)];
        $details['history'][] = ['role' => 'system', 'content' => 'Evaluation: ' . json_encode([
            'score' => $score,
            'passed' => $passed,
            'feedback' => mb_substr($feedback, 0, 1500),
        ])];
        if ($jobKey !== null) {
            $details['recorded_jobs'][] = $jobKey;
        }
        $db->prepare("UPDATE {$table} SET details = ? WHERE id = ?")->execute([json_encode($details), (int) $sessionId]);
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("appendCodeEvaluationToHistory failed for session $sessionId: " . $e->getMessage());
        return false;
    }
}
