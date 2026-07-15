<?php
// MUST be first: prevents PHP warnings from polluting JSON output
if (ob_get_level() === 0)
    ob_start();

/**
 * AI HR Round Handler
 * Manages voice-based sessions, HR questions, and report generation.
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
if (!checkRateLimit("ai_hr_api_" . $userId, 60, 60)) {
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
                                 WHERE student_id = ? AND drive_id = ? AND round_type = 'HR' 
                                 AND status = 'In Progress' 
                                 ORDER BY started_at DESC LIMIT 1");
                $stmt->execute([$usn, $driveId]);
            } else {
                $stmt = $db->prepare("SELECT id, details, started_at FROM unified_ai_assessments 
                                 WHERE student_id = ? AND assessment_type = 'HR' 
                                 AND company_name = ? AND status = 'active' 
                                 ORDER BY started_at DESC LIMIT 1");
                $stmt->execute([$studentIdForDb, $company]);
            }
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                $details = json_decode($session['details'], true);
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'has_active' => true,
                    'session_id' => $session['id'],
                    'started_at' => $session['started_at'],
                    'elapsed_seconds' => time() - strtotime($session['started_at']),
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
            if (!checkRateLimit("ai_hr_start_" . $userId, 2, 60)) {
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

            // Fetch student profile and portfolio projects
            $profile = $studentModel->getByUserId($userId);

            require_once __DIR__ . '/../../src/Models/Portfolio.php';
            $portfolioModel = new Portfolio();
            $portfolio = $portfolioModel->getStudentPortfolio($userId, getInstitution());

            // Extract only projects with their details
            $projects = [];
            foreach ($portfolio as $item) {
                if ($item['category'] === 'Project') {
                    $projects[] = [
                        'title' => $item['title'],
                        'description' => $item['description'] ?? '',
                        'tech_stack' => $item['sub_title'] ?? '',
                        'link' => $item['link'] ?? ''
                    ];
                }
            }

            if ($driveId > 0) {
                // Select next attempt number
                $stmt = $db->prepare("
                SELECT MAX(attempt_number) FROM student_drive_attempts 
                WHERE drive_id = ? AND student_id = ? AND round_type = 'HR'
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
            ) VALUES (?, 'HR', ?, ?, ?, ?, ?, ?, 'In Progress', ?, CURRENT_TIMESTAMP)";

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
                        'projects' => $projects,
                        'task_id' => null
                    ])
                ]);
            } else {
                $sql = "INSERT INTO unified_ai_assessments (
                student_id, institution, student_name, usn, aadhar, current_sem, branch, 
                assessment_type, company_name, status, details, started_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'HR', ?, 'active', ?, CURRENT_TIMESTAMP)";

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
                        'projects' => $projects,
                        'task_id' => $input['task_id'] ?? null
                    ])
                ]);
            }

            ob_clean();
            echo json_encode(['success' => true, 'session_id' => $db->lastInsertId()]);
            break;

        case 'get_question':
            $sessionId = $input['session_id'];
            $userMessage = $input['message'] ?? ''; // Voice transcript

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
            $projects = $details['projects'] ?? [];  // Extract projects

            // Append user voice transcript if exists
            $isRetry = !empty($input['retry']);
            if (!empty($userMessage)) {
                $history[] = ['role' => 'user', 'content' => $userMessage];
            } elseif (!$isRetry && count($history) > 0) {
                // User skipped or did not respond (but a question-reload retry must not
                // record a fake skip)
                $history[] = ['role' => 'user', 'content' => '[No response / skipped]'];
            }
            $details['history'] = $history;
            if ($isDrive) {
                $db->prepare("UPDATE student_drive_attempts SET details = ? WHERE id = ?")
                    ->execute([json_encode($details), $sessionId]);
            } else {
                $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
                    ->execute([json_encode($details), $sessionId]);
            }

            // Fetch previously asked questions for this student to prevent repetition
            $previousQuestions = [];

            // Helper: extract questions from a session's history array
            $extractQuestions = function ($historyArr) {
                $questions = [];
                if (!is_array($historyArr))
                    return $questions;
                foreach ($historyArr as $msg) {
                    if (($msg['role'] ?? '') === 'assistant') {
                        $content = $msg['content'] ?? '';
                        // Try JSON first (legacy format)
                        $parsed = @json_decode($content, true);
                        if ($parsed && !empty($parsed['question'])) {
                            $questions[] = $parsed['question'];
                        } elseif (!empty(trim($content))) {
                            // Plain text question (current format)
                            $questions[] = trim($content);
                        }
                    }
                }
                return $questions;
            };

            // 1. Extract from CURRENT session's own history (already asked in this sitting)
            $previousQuestions = array_merge($previousQuestions, $extractQuestions($history));

            // 2. Extract from OTHER sessions for this student
            if ($isDrive) {
                $stmtPrev = $db->prepare("SELECT details FROM student_drive_attempts WHERE drive_id = ? AND student_id = ? AND round_type = 'HR' AND id != ?");
                $stmtPrev->execute([$driveId, $usn, $sessionId]);
            } else {
                $stmtPrev = $db->prepare("SELECT details FROM unified_ai_assessments WHERE student_id = ? AND assessment_type = 'HR' AND id != ? ORDER BY started_at DESC LIMIT 5");
                $stmtPrev->execute([$studentIdForDb, $sessionId]);
            }
            while ($row = $stmtPrev->fetch(PDO::FETCH_ASSOC)) {
                $det = json_decode($row['details'], true);
                if (!empty($det['history'])) {
                    $previousQuestions = array_merge($previousQuestions, $extractQuestions($det['history']));
                }
            }

            // De-duplicate
            $previousQuestions = array_values(array_unique($previousQuestions));

            // Get AI HR Question with project context. Prefer the async queue, but if
            // Redis is down or no worker has pulsed recently, fall back to a
            // synchronous AI call — otherwise the job would sit 'pending' forever and
            // the question would never load.
            $jobId = null;
            try {
                if (\App\Services\QueueService::isQueueAvailable()) {
                    $jobId = \App\Services\QueueService::pushJob('getHRQuestion', [$role, $history, $projects, $concept, $previousQuestions, $difficulty], $userId);
                }
            } catch (Throwable $qe) {
                error_log("HR get_question: queue unavailable, using sync fallback: " . $qe->getMessage());
                $jobId = null;
            }

            if ($jobId) {
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'job_id' => $jobId,
                    'is_queued' => true,
                    'debug' => [
                        'history_count' => count($history),
                        'projects_count' => count($projects)
                    ]
                ]);
            } else {
                $syncRes = $ai->getHRQuestion($role, $history, $projects, $concept, $previousQuestions, $difficulty);
                if (!empty($syncRes['success']) && !empty($syncRes['result'])) {
                    ob_clean();
                    echo json_encode(['success' => true, 'sync' => true, 'result' => $syncRes['result']]);
                } else {
                    error_log("HR get_question sync fallback failed: " . ($syncRes['message'] ?? 'unknown'));
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => $syncRes['message'] ?? 'The AI service is currently unavailable. Please retry.']);
                }
            }
            break;

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

            $reportRes = ['success' => false, 'content' => null, 'overall_score' => 0];

            if ($userInteractions === 0) {
                $score = 0;
            } else {
                // Generate HR Report via AI
                session_write_close();
                $reportRes = $ai->generateHRReport($role, $history, $concept);

                if ($reportRes['success']) {
                    $score = (int) ($reportRes['overall_score'] ?? 0);
                    if ($score > 100)
                        $score = 100;
                    if ($score < 0)
                        $score = 0;
                } else {
                    error_log("HR Report generation failed for session $sessionId: " . ($reportRes['message'] ?? 'Unknown Error'));
                    $score = 0; // Fallback score so the submission can complete
                }
            }
            try {
                $telemetry = isset($input['telemetry']) ? json_decode($input['telemetry'], true) : null;
                if ($isDrive) {
                    // Fetch details first to securely update them
                    $stmt = $db->prepare("SELECT details FROM student_drive_attempts WHERE id = ?");
                    $stmt->execute([$sessionId]);
                    $details = json_decode($stmt->fetchColumn() ?? '{}', true);

                    if (isset($reportRes['content'])) {
                        $details['report_content'] = $reportRes['content'];
                    }
                    if ($telemetry) {
                        $details['telemetry'] = $telemetry;
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
                    if ($telemetry) {
                        $details['telemetry'] = $telemetry;
                    }

                    // Finalize Status immediately so closing the browser doesn't orphan the completion
                    $db->prepare("UPDATE unified_ai_assessments 
                              SET score = ?, feedback = ?, status = 'completed', completed_at = CURRENT_TIMESTAMP, details = ? 
                              WHERE id = ?")
                        ->execute([$score, "HR Report Generated", json_encode($details), $sessionId]);

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
                        error_log("Task completion auto-recorded for HR round. Task: $taskId, USN: $studentUsn");
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
                $dir = REPORTS_UPLOAD_PATH . '/hr/';
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
                    $publicPath = "uploads/reports/hr/" . $filename;

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
                            error_log("Task completion recorded for HR round. Task: $taskId, USN: $studentUsn, Score: $finalScore, Time: $timeTaken");
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
    error_log("HR Handler Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}

// Dead code removed: allowReportHtml() and generateHRReportHTML() were never
// called — the PDF report is generated client-side via html2pdf.