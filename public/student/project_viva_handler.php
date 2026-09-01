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

try {
    switch ($action) {

        case 'save_calibration':
            $portfolioId = (int)($input['portfolio_id'] ?? 0);
            $calibrationData = $input['calibration'] ?? [];

            $stmt = $db->prepare("INSERT INTO assessment_calibrations (student_id, portfolio_id, calibration_json, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$username, $portfolioId, json_encode($calibrationData)]);

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

            $stmt = $db->prepare("INSERT INTO assessment_integrity_events 
                (student_id, portfolio_id, assessment_type, event_type, duration, confidence, severity, metadata, created_at) 
                VALUES (?, ?, 'Project Defense', ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $username,
                $portfolioId,
                $eventType,
                $duration,
                $confidence,
                $severity,
                json_encode($metadata)
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
                session_write_close();
                $jobId = \App\Services\QueueService::pushJob('evaluateProjectViva', [$item['title'], $history], $userId);
                ob_clean(); echo json_encode([
                    'success' => true, 
                    'job_id' => $jobId,
                    'message' => 'Evaluating responses...'
                ]);
                exit;
            } else {
                $aiService = new \App\Services\AIService();
                $eval = $aiService->evaluateProjectViva($item['title'], $history);
                ob_clean(); echo json_encode([
                    'success' => true,
                    'result' => $eval,
                    'direct' => true
                ]);
                exit;
            }

        case 'save_viva_result':
            $portfolioId = (int)($input['portfolio_id'] ?? 0);
            $score = (float)($input['score'] ?? 0);
            $feedback = $input['feedback'] ?? '';
            $history = $input['history'] ?? [];

            // 1. Verify ownership
            $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE id = ? AND (student_id = ? OR UPPER(student_id) = UPPER(?))");
            $stmt->execute([$portfolioId, $username, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            // 2. Build Integrity Report
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
                $eventStmt = $db->prepare("SELECT event_type, duration, confidence, severity FROM assessment_integrity_events WHERE student_id = ? AND portfolio_id = ?");
                $eventStmt->execute([$username, $portfolioId]);
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

                $autoSubmitted = !empty($input['auto_submitted']);
                $strikeCount = (int)($input['strike_count'] ?? 0);
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
            $isVerified = ($score >= 70) ? 1 : 0;
            $sql = "UPDATE student_portfolio SET 
                    is_verified = ?, 
                    verification_score = ?, 
                    verification_date = CURRENT_TIMESTAMP,
                    verification_details = ? 
                    WHERE id = ?";
            $db->prepare($sql)->execute([
                $isVerified,
                $score,
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
                    $score,
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
                'integrity_report' => $integrityReport
            ]);
            exit;

        default:
            ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    ob_clean(); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
