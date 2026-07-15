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
    $stmt = $db->prepare("SELECT drive_id FROM student_drive_attempts WHERE id = ?");
    $stmt->execute([(int) $input['session_id']]);
    $driveAttempt = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($driveAttempt) {
        $isDrive = true;
        $driveId = (int) $driveAttempt['drive_id'];
    }
}

try {
    switch ($action) {
        case 'check_active_session':
            $company = $input['company'] ?? 'General';

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
                $maxDuration = 20 * 60; // 20 mins default

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
                    ob_clean();
                    echo json_encode(['success' => true, 'sync' => true, 'result' => $syncRes['result']]);
                } else {
                    error_log("Technical submit_code sync fallback failed: " . ($syncRes['message'] ?? 'unknown'));
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => $syncRes['message'] ?? 'Code evaluation service is unavailable. Please retry.']);
                }
            }
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

            // Check Minimum Time Requirement (20 mins = 1200 seconds) for assigned tasks
            if ($taskId && !$isDrive) {
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

            if ($userInteractions === 0) {
                $score = 0;
            } else {
                // Generate Text Report via AI
                session_write_close();
                $reportRes = $ai->generateTechnicalInterviewReport($role, $history, 'Technical', $concept);

                if ($reportRes['success']) {
                    $score = $reportRes['overall_score'];
                } else {
                    error_log("Technical Report generation failed for session $sessionId: " . ($reportRes['message'] ?? 'Unknown Error'));
                    $score = 0; // Fallback score so the submission can complete
                }
            }



            try {
                if ($isDrive) {
                    // Fetch details first to securely update them
                    $stmt = $db->prepare("SELECT details FROM student_drive_attempts WHERE id = ?");
                    $stmt->execute([$sessionId]);
                    $details = json_decode($stmt->fetchColumn() ?? '{}', true);

                    if (isset($reportRes['content'])) {
                        $details['report_content'] = $reportRes['content'];
                    }

                    // Finalize Status immediately
                    $db->prepare("UPDATE student_drive_attempts 
                              SET score = ?, status = 'Completed', completed_at = CURRENT_TIMESTAMP, details = ? 
                              WHERE id = ?")
                        ->execute([$score, json_encode($details), $sessionId]);
                } else {
                    // Decode current details to safely append
                    $stmt = $db->prepare("SELECT details, started_at, usn FROM unified_ai_assessments WHERE id = ?");
                    $stmt->execute([$sessionId]);
                    $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
                    $details = json_decode($sessionData['details'] ?? '{}', true);

                    if (isset($reportRes['content'])) {
                        $details['report_content'] = $reportRes['content'];
                    }

                    // Finalize Status immediately so closing the browser doesn't orphan the completion
                    $db->prepare("UPDATE unified_ai_assessments 
                              SET score = ?, feedback = ?, status = 'completed', completed_at = CURRENT_TIMESTAMP, details = ? 
                              WHERE id = ?")
                        ->execute([$score, "Report Generated", json_encode($details), $sessionId]);

                    // Insert into task_completions immediately if it's an assigned task
                    if (isset($details['task_id']) && $details['task_id']) {
                        $taskId = $details['task_id'];
                        $studentUsn = $sessionData['usn'] ?? getUsername();
                        $timeTaken = time() - strtotime($sessionData['started_at']);

                        $stmtComp = $db->prepare("INSERT INTO task_completions 
                                          (task_id, student_id, score, time_taken) 
                                          VALUES (?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE 
                                          score = VALUES(score),
                                          time_taken = VALUES(time_taken), 
                                          completed_at = CURRENT_TIMESTAMP");
                        $stmtComp->execute([$taskId, $studentUsn, $score, $timeTaken]);
                        error_log("Task completion auto-recorded for Technical round. Task: $taskId, USN: $studentUsn");
                    }
                }
                ob_clean();
                echo json_encode(['success' => true, 'score' => $score]);
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