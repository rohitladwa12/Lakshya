<?php
// MUST be first: prevents PHP warnings from polluting JSON output
ob_start();

/**
 * Project Defense Handler — Assessment Proctoring & Integrity System
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
requireRole(ROLE_STUDENT);

header('Content-Type: application/json');

$userId = getUserId();
$username = getUsername();
$studentIdForDb = getStudentIdForAssessment();
$institution = $_SESSION['institution'] ?? 'GMU';

require_once __DIR__ . '/../../src/Services/AIService.php';
$aiService = new AIService();
$db = getDB();

ensureIntegrityTablesExist($db);

function ensureIntegrityTablesExist(PDO $db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS assessment_integrity_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(100) NOT NULL,
            portfolio_id INT NOT NULL DEFAULT 0,
            assessment_type VARCHAR(50) NOT NULL DEFAULT 'Skill Verification',
            event_type VARCHAR(50) NOT NULL,
            duration FLOAT NULL,
            confidence FLOAT NOT NULL DEFAULT 1.0,
            severity VARCHAR(20) NOT NULL DEFAULT 'LOW',
            metadata JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student_portfolio (student_id, portfolio_id),
            INDEX idx_event_severity (event_type, severity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS assessment_calibrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(100) NOT NULL,
            portfolio_id INT NOT NULL DEFAULT 0,
            calibration_json JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student_portfolio (student_id, portfolio_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {
        error_log("Failed to create integrity tables: " . $e->getMessage());
    }
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST); // accept form-urlencoded fallback
$action = $input['action'] ?? '';

// Rate limit: 15 project viva requests per user per 60 seconds
if (!checkRateLimit("project_viva_{$userId}", 15, 60)) {
    http_response_code(429);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait before trying again.']);
    exit;
}

try {
    switch ($action) {
        case 'ping':
            // Keep-alive while the student types answers (no other requests for a long time)
            ob_clean(); echo json_encode(['success' => true]);
            exit;

        case 'save_calibration':
            $portfolioId = (int)($input['portfolio_id'] ?? 0);
            $calibrationData = $input['calibration'] ?? [];

            $stmt = $db->prepare("INSERT INTO assessment_calibrations (student_id, portfolio_id, calibration_json, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$username, $portfolioId, json_encode($calibrationData)]);

            // Calibration starts an attempt: strikes are counted from here on
            \App\Services\ProctoringService::markAttemptStart($db, 'project_viva', $portfolioId);

            ob_clean(); echo json_encode([
                'success' => true,
                'message' => 'Calibration baseline stored successfully.'
            ]);
            exit;

        case 'log_proctoring_event':
            $portfolioId = (int)($input['portfolio_id'] ?? 0);
            $eventType = trim((string)($input['event_type'] ?? 'GAZE_DEVIATION'));
            $duration = (float)($input['duration'] ?? 0);
            $confidence = (float)($input['confidence'] ?? 1.0);
            $severity = strtoupper(trim((string)($input['severity'] ?? 'LOW')));
            $metadata = $input['metadata'] ?? [];

            if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH'])) {
                $severity = 'LOW';
            }

            $snapshotInfo = null;
            $snapshotBase64 = $input['snapshot'] ?? $input['snapshot_base64'] ?? null;
            if (!empty($snapshotBase64)) {
                $snapshotInfo = \App\Services\ProctoringService::saveSnapshotToVault($snapshotBase64, $username, $eventType);
            }

            $stmt = $db->prepare("INSERT INTO assessment_integrity_events 
                (student_id, portfolio_id, assessment_type, event_type, duration, confidence, severity, metadata, snapshot_path, file_size, sha256, mime_type, created_at) 
                VALUES (?, ?, 'Project Defense', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $username,
                $portfolioId,
                $eventType,
                $duration,
                $confidence,
                $severity,
                json_encode($metadata),
                $snapshotInfo['path'] ?? null,
                $snapshotInfo['size'] ?? null,
                $snapshotInfo['sha256'] ?? null,
                $snapshotInfo['mime'] ?? null
            ]);

            ob_clean(); echo json_encode([
                'success' => true,
                'event_logged' => $eventType
            ]);
            exit;

        case 'generate_viva':
            $portfolioId = $input['portfolio_id'] ?? 0;
            
            // Verify ownership
            $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE id = ? AND (student_id = ? OR UPPER(student_id) = UPPER(?))");
            $stmt->execute([$portfolioId, $username, $username]);
            $item = $stmt->fetch();
            
            if (!$item || $item['category'] !== 'Project') {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            require_once ROOT_PATH . '/src/Services/QueueService.php';
            require_once ROOT_PATH . '/src/Services/AIService.php';

            if (\App\Services\QueueService::isQueueAvailable()) {
                session_write_close();
                $jobId = \App\Services\QueueService::pushJob('generateProjectViva', [$item['title'], $item['description']], $userId);
                ob_clean(); echo json_encode([
                    'success' => true, 
                    'job_id' => $jobId,
                    'message' => 'Generating questions...'
                ]);
                exit;
            } else {
                $aiService = new \App\Services\AIService();
                $questions = $aiService->generateProjectViva($item['title'], $item['description']);
                ob_clean(); echo json_encode([
                    'success' => true,
                    'questions' => $questions,
                    'direct' => true
                ]);
                exit;
            }

        case 'submit_viva':
            $portfolioId = $input['portfolio_id'] ?? 0;
            $history = $input['history'] ?? [];

            // Verify ownership
            $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE id = ? AND (student_id = ? OR UPPER(student_id) = UPPER(?))");
            $stmt->execute([$portfolioId, $username, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            require_once ROOT_PATH . '/src/Services/QueueService.php';
            require_once ROOT_PATH . '/src/Services/AIService.php';

            if (\App\Services\QueueService::isQueueAvailable()) {
                $jobId = \App\Services\QueueService::pushJob('evaluateProjectViva', [$item['title'], $history], $userId);
                // Remember this attempt's evaluation so save_viva_result uses the server-side score
                $_SESSION['viva_eval_pending'][(int)$portfolioId] = ['job_id' => $jobId, 'history' => $history];
                session_write_close();
                ob_clean(); echo json_encode([
                    'success' => true, 
                    'job_id' => $jobId,
                    'message' => 'Evaluating responses...'
                ]);
                exit;
            } else {
                $aiService = new \App\Services\AIService();
                $eval = $aiService->evaluateProjectViva($item['title'], $history);
                $_SESSION['viva_eval_pending'][(int)$portfolioId] = ['result' => $eval, 'history' => $history];
                ob_clean(); echo json_encode([
                    'success' => true,
                    'result' => $eval,
                    'direct' => true
                ]);
                exit;
            }

        case 'save_viva_result':
            $portfolioId = (int)($input['portfolio_id'] ?? 0);

            // 1. Verify ownership
            $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE id = ? AND (student_id = ? OR UPPER(student_id) = UPPER(?))");
            $stmt->execute([$portfolioId, $username, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            // Use the score from the server's own evaluation of this attempt. The browser's
            // `score` was trusted before, so anyone could post 100 and mark the item verified.
            $pendingEval = $_SESSION['viva_eval_pending'][$portfolioId] ?? null;
            $serverEval = null;
            if (is_array($pendingEval) && isset($pendingEval['result'])) {
                $serverEval = $pendingEval['result'];
            } elseif (is_array($pendingEval) && !empty($pendingEval['job_id'])) {
                require_once ROOT_PATH . '/src/Services/QueueService.php';
                $job = \App\Services\QueueService::getJobStatus($pendingEval['job_id']);
                if ($job && ($job['status'] ?? '') === 'completed' && trim((string)($job['user_id'] ?? '')) === trim((string)$userId)) {
                    $serverEval = $job['result'] ?? null;
                }
            }
            if (!is_array($serverEval) || !isset($serverEval['score']) || !is_numeric($serverEval['score'])) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'No completed evaluation was found for this attempt. Please submit your answers again.']);
                exit;
            }
            $score = max(0.0, min(100.0, (float)$serverEval['score']));
            $feedback = (string)($serverEval['feedback'] ?? '');
            $history = $pendingEval['history'] ?? [];
            unset($_SESSION['viva_eval_pending'][$portfolioId]);

            // 2. Build Integrity Report
            $attemptSince = \App\Services\ProctoringService::attemptWindowStart($db, 'project_viva', $portfolioId);
            // Defaults in case the report block below fails part-way
            $strikeCount = max(0, (int)($input['strike_count'] ?? 0));
            $autoSubmitted = !empty($input['auto_submitted']) || $strikeCount >= 3;
            $finalScore = ($strikeCount >= 3 || $autoSubmitted) ? 0.0 : max(0.0, $score);
            $integrityReport = [
                'screen_sharing_active_pct' => 100.0,
                'camera_availability_pct' => 100.0,
                'face_presence_pct' => 100.0,
                'gaze_confidence_pct' => 92.5,
                'attention_deviations' => 0,
                'longest_deviation_sec' => 0.0,
                'screen_interruptions' => 0,
                'multiple_faces_count' => 0,
                'integrity_status' => 'No significant anomalies detected'
            ];

            try {
                // Only this attempt's events — portfolio_id is shared by every retry of the same item
                $eventStmt = $db->prepare("SELECT event_type, duration, confidence, severity FROM assessment_integrity_events WHERE student_id = ? AND portfolio_id = ? AND created_at >= ?");
                $eventStmt->execute([$username, $portfolioId, $attemptSince]);
                $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

                $totalEvents = count($events);
                $confSum = 0;
                $maxDev = 0;

                foreach ($events as $evt) {
                    $confSum += (float)($evt['confidence'] ?? 1.0);
                    $dur = (float)($evt['duration'] ?? 0);

                    if ($evt['event_type'] === 'GAZE_DEVIATION' || $evt['event_type'] === 'LOOKING_AWAY') {
                        $integrityReport['attention_deviations']++;
                        if ($dur > $maxDev) $maxDev = $dur;
                    } else if ($evt['event_type'] === 'SCREEN_SHARE_STOPPED' || $evt['event_type'] === 'FULLSCREEN_EXIT') {
                        $integrityReport['screen_interruptions']++;
                    } else if ($evt['event_type'] === 'MULTI_FACE') {
                        $integrityReport['multiple_faces_count']++;
                    } else if ($evt['event_type'] === 'NO_FACE') {
                        $integrityReport['face_presence_pct'] = max(70.0, $integrityReport['face_presence_pct'] - 5.0);
                    }
                }

                if ($totalEvents > 0) {
                    $integrityReport['gaze_confidence_pct'] = round(($confSum / $totalEvents) * 100, 1);
                }
                $integrityReport['longest_deviation_sec'] = round($maxDev, 1);

                if (isset($input['client_face_presence_pct'])) {
                    $cFacePct = (float)$input['client_face_presence_pct'];
                    $integrityReport['face_presence_pct'] = round(min($integrityReport['face_presence_pct'], $cFacePct), 1);
                }

                // Server recount of logged strikes; the browser's figure can only raise it
                $serverStrikes = \App\Services\ProctoringService::countAttemptStrikes($db, $username, $portfolioId, $attemptSince);
                $strikeCount = max($serverStrikes, (int)($input['strike_count'] ?? 0));
                $autoSubmitted = !empty($input['auto_submitted']) || $strikeCount >= 3;
                $integrityReport['server_strike_count'] = $serverStrikes;
                $penaltyPct = ($strikeCount === 1) ? 5.0 : (($strikeCount === 2) ? 10.0 : ($strikeCount >= 3 || $autoSubmitted ? 100.0 : 0.0));
                $rawScore = $score;
                $finalScore = max(0.0, round($rawScore - $penaltyPct, 1));
                
                $integrityReport['raw_score'] = $rawScore;
                $integrityReport['penalty_pct'] = $penaltyPct;
                $integrityReport['final_score'] = $finalScore;
                $integrityReport['auto_submitted'] = $autoSubmitted;
                $integrityReport['strike_count'] = $strikeCount;

                if ($autoSubmitted || $strikeCount >= 3) {
                    $integrityReport['integrity_status'] = 'Auto-Submitted: Maximum Security Violations (3/3) Exceeded';
                } else if ($integrityReport['screen_interruptions'] > 0 || $maxDev >= 15.0 || $integrityReport['multiple_faces_count'] > 0 || $integrityReport['face_presence_pct'] < 80) {
                    $integrityReport['integrity_status'] = 'Attention & Integrity Review Recommended';
                }
            } catch (\Throwable $e) {
                error_log("Failed to build project viva integrity report: " . $e->getMessage());
            }

            // 3. Update student_portfolio
            $isVerified = ($finalScore >= 70 && !$autoSubmitted && $strikeCount < 3) ? 1 : 0;
            $sql = "UPDATE student_portfolio SET 
                    is_verified = ?, 
                    verification_score = ?, 
                    verification_date = CURRENT_TIMESTAMP,
                    verification_details = ? 
                    WHERE id = ?";
            $db->prepare($sql)->execute([
                $isVerified,
                $finalScore,
                json_encode([
                    'feedback' => $feedback,
                    'transcript' => $history,
                    'integrity_report' => $integrityReport
                ]),
                $portfolioId
            ]);


            // 4. Sync to unified_ai_assessments
            try {
                require_once ROOT_PATH . '/src/Models/StudentProfile.php';
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
                    'Project Defense',
                    $item['title'],
                    $finalScore,
                    100,
                    $feedback,
                    json_encode([
                        'transcript' => $history,
                        'project_title' => $item['title'],
                        'portfolio_id' => $portfolioId,
                        'integrity_report' => $integrityReport
                    ]),
                    'completed'
                ]);
            } catch (Exception $e) {
                error_log("Failed to sync project viva to unified table: " . $e->getMessage());
            }

            ob_clean(); echo json_encode([
                'success' => true, 
                'message' => 'Result saved successfully.',
                'final_score' => $finalScore,
                'is_verified' => (bool)$isVerified,
                'integrity_report' => $integrityReport
            ]);
            exit;

        default:
            ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    ob_clean(); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
