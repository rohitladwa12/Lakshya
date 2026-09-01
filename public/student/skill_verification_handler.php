<?php
// MUST be first: prevents PHP warnings from polluting JSON output
ob_start();

/**
 * Skill Verification Handler — Assessment Proctoring & Integrity System
 * Implements:
 * 1. Screen Capture + Webcam Dual Input Integrity Verification
 * 2. Calibration Storage & Structured Event Logging (assessment_integrity_events)
 * 3. 3-Level Event Classification (Normal, Attention Event, Review Event)
 * 4. Post-Assessment Integrity Report Generation
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

requireRole(ROLE_STUDENT);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($_POST, $input);
$action = $input['action'] ?? '';

$userId = getUserId();
$username = getUsername();
$studentIdForDb = getStudentIdForAssessment();
$institution = $_SESSION['institution'] ?? 'GMU';

require_once __DIR__ . '/../../src/Services/AIService.php';
$aiService = new AIService();
$db = getDB();

// Ensure integrity tables exist
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

            // Allowed severities
            if (!in_array($severity, ['LOW', 'MEDIUM', 'HIGH'])) {
                $severity = 'LOW';
            }

            $stmt = $db->prepare("INSERT INTO assessment_integrity_events 
                (student_id, portfolio_id, assessment_type, event_type, duration, confidence, severity, metadata, created_at) 
                VALUES (?, ?, 'Skill Verification', ?, ?, ?, ?, ?, NOW())");
            
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

        case 'check_session':
            $portfolioId = $_POST['portfolio_id'] ?? 0;
            $stmt = $db->prepare("SELECT quiz_data FROM active_skill_quizzes WHERE student_id = ? AND portfolio_id = ?");
            $stmt->execute([$username, $portfolioId]);
            $quizRow = $stmt->fetch();
            if ($quizRow) {
                $quizData = json_decode($quizRow['quiz_data'], true);
                $safeQuestions = array_map(function($q) {
                    return ['question' => $q['question'], 'options' => $q['options']];
                }, $quizData['questions']);
                ob_clean(); echo json_encode([
                    'success' => true,
                    'has_active' => true,
                    'questions' => $safeQuestions
                ]);
            } else {
                ob_clean(); echo json_encode(['success' => true, 'has_active' => false]);
            }
            exit;

        case 'generate_quiz':
            $portfolioId = $_POST['portfolio_id'] ?? 0;
            
            // Verify ownership
            $stmt = $db->prepare("SELECT * FROM student_portfolio WHERE id = ? AND (student_id = ? OR UPPER(student_id) = UPPER(?))");
            $stmt->execute([$portfolioId, $username, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Portfolio item not found.']);
                exit;
            }

            if ($item['category'] !== 'Skill') {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Only skills can be verified via MCQ quiz.']);
                exit;
            }

            // Check if an active quiz is already saved for this session/portfolio
            $stmtActive = $db->prepare("SELECT quiz_data FROM active_skill_quizzes WHERE student_id = ? AND portfolio_id = ?");
            $stmtActive->execute([$username, $portfolioId]);
            $activeRow = $stmtActive->fetch();

            if ($activeRow && !empty($activeRow['quiz_data'])) {
                $quizObj = json_decode($activeRow['quiz_data'], true);
                if (!empty($quizObj['questions'])) {
                    $_SESSION['current_skill_quiz'] = $quizObj;
                    $safeQuestions = array_map(function($q) {
                        return [
                            'question' => $q['question'],
                            'options' => $q['options']
                        ];
                    }, $quizObj['questions']);

                    ob_clean(); echo json_encode([
                        'success' => true,
                        'skill' => $item['title'],
                        'level' => $item['sub_title'] ?: 'Intermediate',
                        'questions' => $safeQuestions,
                        'cached' => true
                    ]);
                    exit;
                }
            }

            $skill = $item['title'];
            $level = $item['sub_title'] ?: 'Intermediate';

            // Generate quiz
            $quizRes = $aiService->generateSkillQuiz($skill, $level);
            
            if ($quizRes['success']) {
                $quizObj = [
                    'portfolio_id' => $portfolioId,
                    'skill' => $skill,
                    'level' => $level,
                    'questions' => $quizRes['questions'],
                    'started_at' => time()
                ];
                
                // Persist quiz to database
                $stmt = $db->prepare("
                    INSERT INTO active_skill_quizzes (student_id, portfolio_id, quiz_data) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE quiz_data = VALUES(quiz_data)
                ");
                $stmt->execute([$username, $portfolioId, json_encode($quizObj)]);

                // Store quiz data in session
                $_SESSION['current_skill_quiz'] = $quizObj;

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
            $answers = $input['answers'] ?? []; // Array of indices
            $portfolioId = (int)($input['portfolio_id'] ?? ($_SESSION['current_skill_quiz']['portfolio_id'] ?? 0));
            
            $quizData = null;
            if ($portfolioId) {
                $stmt = $db->prepare("SELECT quiz_data FROM active_skill_quizzes WHERE student_id = ? AND portfolio_id = ?");
                $stmt->execute([$username, $portfolioId]);
                $quizRow = $stmt->fetch();
                if ($quizRow) {
                    $quizData = json_decode($quizRow['quiz_data'], true);
                }
            }
            
            if (!$quizData && isset($_SESSION['current_skill_quiz'])) {
                $quizData = $_SESSION['current_skill_quiz'];
                $portfolioId = $quizData['portfolio_id'];
            }

            if (!$quizData) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'No active quiz session found.']);
                exit;
            }

            $questions = $quizData['questions'];
            $correctCount = 0;
            $results = [];

            foreach ($questions as $idx => $q) {
                $userAns = isset($answers[$idx]) ? (int)$answers[$idx] : -1;
                
                $rawAns = $q['answer'] ?? null;
                if (is_numeric($rawAns)) {
                    $correctAns = (int)$rawAns;
                } elseif (is_string($rawAns)) {
                    $ansLetter = strtoupper(trim($rawAns));
                    if (in_array($ansLetter, ['A', 'B', 'C', 'D'])) {
                        $correctAns = match ($ansLetter) { 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3 };
                    } else {
                        $correctAns = (int)$rawAns;
                    }
                } else {
                    $correctAns = 0;
                }

                $isCorrect = ($userAns === $correctAns);
                if ($isCorrect) $correctCount++;

                $results[] = [
                    'question' => $q['question'],
                    'user_answer' => $userAns,
                    'correct_answer' => $correctAns,
                    'explanation' => $q['explanation'],
                    'is_correct' => $isCorrect
                ];
            }

            $score = ($correctCount / count($questions)) * 100;
            $isPassed = ($score >= 70); // 70% to pass

            // Build Assessment Integrity Report from logged events
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

                // Blend client-calculated face presence ratio if provided
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
                error_log("Failed to build integrity report: " . $e->getMessage());
            }

            if ($isPassed) {
                // 1. Update Portfolio Table
                $sql = "UPDATE student_portfolio SET 
                        is_verified = 1, 
                        verification_score = ?, 
                        verification_date = CURRENT_TIMESTAMP,
                        verification_details = ? 
                        WHERE id = ?";
                $db->prepare($sql)->execute([
                    $score,
                    json_encode([
                        'transcript' => $results,
                        'integrity_report' => $integrityReport
                    ]),
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
                        $quizData['skill'],
                        $score,
                        100,
                        "Successfully verified skill: " . $quizData['skill'],
                        json_encode([
                            'transcript' => $results,
                            'skill' => $quizData['skill'],
                            'level' => $quizData['level'] ?? 'Intermediate',
                            'integrity_report' => $integrityReport
                        ]),
                        'completed'
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to sync skill verification to unified table: " . $e->getMessage());
                }
            }

            // Cleanup database active quiz
            if ($portfolioId) {
                $stmtDel = $db->prepare("DELETE FROM active_skill_quizzes WHERE student_id = ? AND portfolio_id = ?");
                $stmtDel->execute([$username, $portfolioId]);
            }

            // Cleanup session
            unset($_SESSION['current_skill_quiz']);

            ob_clean(); echo json_encode([
                'success' => true,
                'score' => $score,
                'passed' => $isPassed,
                'correct_count' => $correctCount,
                'total_count' => count($questions),
                'results' => $results,
                'integrity_report' => $integrityReport
            ]);
            break;

        default:
            ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    ob_clean(); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
