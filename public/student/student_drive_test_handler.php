<?php
// Prevent PHP warnings from polluting JSON output
if (ob_get_level() === 0) ob_start();

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';

header('Content-Type: application/json');

// Only logged in students allowed
if (!isLoggedIn()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}
$userId = getUserId();
$usn = getUsername(); // Student USN

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST, $_GET);
$action = $input['action'] ?? '';

$db = getDB();
$ai = new AIService();

try {
    switch ($action) {
        case 'start_or_get_test':
            $driveId = (int)($input['drive_id'] ?? 0);
            $roundType = $input['round_type'] ?? '';
            
            if (!$driveId || !in_array($roundType, ['Aptitude', 'Technical', 'HR'])) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid drive or round parameters.']);
                exit;
            }

            // Fetch drive details
            $stmt = $db->prepare("
                SELECT cd.*, jp.title AS job_title, jp.id as job_id, c.name as company_name 
                FROM campus_drives cd
                JOIN job_postings jp ON cd.job_id = jp.id
                LEFT JOIN companies c ON jp.company_id = c.id
                WHERE cd.id = ?
            ");
            $stmt->execute([$driveId]);
            $drive = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$drive) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Drive not found.']);
                exit;
            }

            // Enforce deadline
            if ($drive['deadline'] && (strtotime($drive['deadline']) < time())) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Assessment deadline has passed.']);
                exit;
            }

            // Enforce student has applied
            $stmt = $db->prepare("SELECT COUNT(*) FROM job_applications WHERE job_id = ? AND student_id = ?");
            $stmt->execute([$drive['job_id'], $usn]);
            if ($stmt->fetchColumn() == 0) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Access denied. You are not registered for this job posting.']);
                exit;
            }

            try {
                // Fetch student snapshot info
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
                $stmt = $db->prepare("SELECT ID as usn, NAME as name, DISCIPLINE as branch FROM users WHERE ID = ?");
                $stmt->execute([$usn]);
                $userRec = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $studentInfo = [
                    'year' => 'N/A',
                    'usn' => $usn,
                    'name' => $userRec['name'] ?? 'Student',
                    'branch' => $userRec['branch'] ?? 'N/A',
                    'sem' => 8
                ];
            }

            // Check if there is an active in-progress attempt
            $stmt = $db->prepare("
                SELECT * FROM student_drive_attempts 
                WHERE drive_id = ? AND student_id = ? AND round_type = ? AND status = 'In Progress'
                LIMIT 1
            ");
            $stmt->execute([$driveId, $usn, $roundType]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($attempt) {
                // Return active attempt
                $details = json_decode($attempt['details'], true);
                $questions = $details['questions'] ?? [];
                
                // Calculate remaining time
                $started = strtotime($attempt['started_at']);
                $durationSec = (int)$details['duration'] * 60;
                $elapsedSec = time() - $started;
                $remainingSec = max(0, $durationSec - $elapsedSec);

                if ($remainingSec <= 0) {
                    // Auto-submit since time is up
                    $questionsCount = count($questions);
                    $answers = array_fill(0, $questionsCount, null);
                    
                    $db->prepare("
                        UPDATE student_drive_attempts 
                        SET score = 0.00, status = 'Completed', completed_at = CURRENT_TIMESTAMP, details = ? 
                        WHERE id = ?
                    ")->execute([
                        json_encode([
                            'questions' => $questions,
                            'answers' => $answers,
                            'duration' => $details['duration']
                        ]),
                        $attempt['id']
                    ]);

                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Active attempt timer expired. Attempt auto-submitted.']);
                    exit;
                }

                // Sanitize questions (remove answer key and explanations)
                $sanitized = [];
                foreach ($questions as $q) {
                    $sanitized[] = [
                        'question' => $q['question'],
                        'options' => $q['options'],
                        'category' => $q['category'] ?? ''
                    ];
                }

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'attempt_id' => $attempt['id'],
                    'questions' => $sanitized,
                    'remaining_seconds' => $remainingSec,
                    'duration_minutes' => $details['duration']
                ]);
                exit;
            }

            // If no active attempt, create a new one
            // Calculate next attempt number
            $stmt = $db->prepare("
                SELECT MAX(attempt_number) FROM student_drive_attempts 
                WHERE drive_id = ? AND student_id = ? AND round_type = ?
            ");
            $stmt->execute([$driveId, $usn, $roundType]);
            $nextAttemptNum = (int)$stmt->fetchColumn() + 1;

            // Resolve round configurations from drive details
            $topics = '';
            $qCount = 10;
            $duration = 20;

            if ($roundType === 'Aptitude') {
                $topics = $drive['aptitude_topics'] ?: 'Quantitative, Logical, Verbal';
                $qCount = (int)$drive['aptitude_questions'] ?: 10;
                $duration = (int)$drive['aptitude_duration'] ?: 20;
            } elseif ($roundType === 'Technical') {
                $topics = $drive['technical_topics'] ?: 'Java, DBMS, OOPs';
                $qCount = (int)$drive['technical_questions'] ?: 10;
                $duration = (int)$drive['technical_duration'] ?: 20;
            } elseif ($roundType === 'HR') {
                $topics = $drive['hr_topics'] ?: 'Behavioral, Problem Solving, Communication';
                $qCount = (int)$drive['hr_questions'] ?: 10;
                $duration = (int)$drive['hr_duration'] ?: 20;
            }

            // Generate questions
            $questions = [];
            $manualCount = 0;
            $aiCount = $qCount;
            
            if ($roundType === 'Aptitude' && $nextAttemptNum > 1) {
                $manualCount = ceil($qCount * 0.3);
                $aiCount = $qCount - $manualCount;
                
                // Fetch previously asked questions
                $previousQuestions = [];
                $prevStmt = $db->prepare("SELECT details FROM student_drive_attempts WHERE drive_id = ? AND student_id = ? AND round_type = 'Aptitude'");
                $prevStmt->execute([$driveId, $usn]);
                while ($row = $prevStmt->fetch(PDO::FETCH_ASSOC)) {
                    $det = json_decode($row['details'], true);
                    if (!empty($det['questions'])) {
                        foreach ($det['questions'] as $pq) {
                            $previousQuestions[] = $pq['question'];
                        }
                    }
                }
                
                // Fetch manual questions
                $excludeSql = '';
                $params = [];
                if (!empty($previousQuestions)) {
                    $placeholders = str_repeat('?,', count($previousQuestions) - 1) . '?';
                    $excludeSql = " WHERE question_text NOT IN ($placeholders) ";
                    $params = $previousQuestions;
                }
                
                $manStmt = $db->prepare("SELECT question_text as question, option_a as A, option_b as B, option_c as C, option_d as D, correct_option as answer, 'Aptitude' as category FROM manual_aptitude_questions $excludeSql ORDER BY RAND() LIMIT $manualCount");
                $manStmt->execute($params);
                while ($mq = $manStmt->fetch(PDO::FETCH_ASSOC)) {
                    $options = [$mq['A'], $mq['B'], $mq['C'], $mq['D']];
                    $ansLetter = strtoupper(trim($mq['answer']));
                    $ansIndex = match($ansLetter) { 'A'=>0, 'B'=>1, 'C'=>2, 'D'=>3, default=>0 };
                    
                    $questions[] = [
                        'question' => $mq['question'],
                        'options' => $options,
                        'answer' => $ansIndex,
                        'category' => $mq['category'],
                        'explanation' => 'Manual question.'
                    ];
                }
                
                // If we didn't get enough manual, fallback to more AI
                $aiCount += ($manualCount - count($questions));
            }

            if ($aiCount > 0) {
                // Generate questions via AI
                $aiRes = $ai->generateDriveRoundQuestions($roundType, $topics, $aiCount, $drive['company_name']);
                if (!$aiRes['success'] || empty($aiRes['questions'])) {
                    if (empty($questions)) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Failed to generate assessment questions via AI. Please try again.']);
                        exit;
                    }
                } else {
                    $questions = array_merge($questions, $aiRes['questions']);
                }
            }
            
            // Shuffle final questions
            shuffle($questions);

            // Store attempt record in In Progress status
            $details = [
                'questions' => $questions,
                'answers' => [],
                'duration' => $duration
            ];

            $stmt = $db->prepare("
                INSERT INTO student_drive_attempts 
                (drive_id, round_type, attempt_number, academic_year, student_id, student_name, branch, sem, status, details, started_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'In Progress', ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $driveId,
                $roundType,
                $nextAttemptNum,
                $studentInfo['year'] ?: 'N/A',
                $usn,
                $studentInfo['name'],
                $studentInfo['branch'] ?: 'N/A',
                (int)($studentInfo['sem'] ?: 8),
                json_encode($details)
            ]);

            $attemptId = $db->lastInsertId();

            // Sanitize questions
            $sanitized = [];
            foreach ($questions as $q) {
                $sanitized[] = [
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'category' => $q['category'] ?? ''
                ];
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'attempt_id' => $attemptId,
                'questions' => $sanitized,
                'remaining_seconds' => $duration * 60,
                'duration_minutes' => $duration
            ]);
            exit;

        case 'submit_test':
            $attemptId = (int)($input['attempt_id'] ?? 0);
            $userAnswers = $input['answers'] ?? [];
            if (is_string($userAnswers)) {
                $decoded = json_decode($userAnswers, true);
                $userAnswers = is_array($decoded) ? $decoded : [];
            }

            if (!$attemptId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Missing attempt ID.']);
                exit;
            }

            // Fetch attempt
            $stmt = $db->prepare("SELECT * FROM student_drive_attempts WHERE id = ? AND student_id = ?");
            $stmt->execute([$attemptId, $usn]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attempt) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Assessment session not found.']);
                exit;
            }

            if ($attempt['status'] === 'Completed') {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Assessment has already been submitted.']);
                exit;
            }

            $details = json_decode($attempt['details'], true);
            $questions = $details['questions'] ?? [];

            // Calculate Score
            $correctCount = 0;
            $totalQuestions = count($questions);

            foreach ($questions as $idx => $q) {
                $submittedAnswer = isset($userAnswers[$idx]) ? (int)$userAnswers[$idx] : null;
                $correctAnswer = isset($q['answer']) ? (int)$q['answer'] : null;

                if ($submittedAnswer !== null && $correctAnswer !== null && $submittedAnswer === $correctAnswer) {
                    $correctCount++;
                }
            }

            $scorePercentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

            // Update details structure with answers
            $details['answers'] = $userAnswers;
            if (!empty($input['auto_submit_reason'])) {
                $details['auto_submit_reason'] = trim(strip_tags($input['auto_submit_reason']));
            }

            // Finalize status to Completed
            $stmt = $db->prepare("
                UPDATE student_drive_attempts 
                SET score = ?, status = 'Completed', completed_at = CURRENT_TIMESTAMP, details = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $scorePercentage,
                json_encode($details),
                $attemptId
            ]);

            ob_clean();
            echo json_encode([
                'success' => true,
                'score' => $scorePercentage,
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
                'details' => $details,
                'message' => 'Your test has been successfully graded.'
            ]);
            exit;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
            exit;
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    exit;
}
