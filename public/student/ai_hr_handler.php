<?php
// MUST be first: prevents PHP warnings from polluting JSON output
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
requireLogin();
session_write_close();
$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment();

$db = getDB();
$ai = new AIService();
$studentModel = new StudentProfile();

switch ($action) {
    case 'check_active_session':
        $company = $input['company'] ?? 'General';
        
        $stmt = $db->prepare("SELECT id, details, started_at FROM unified_ai_assessments 
                             WHERE student_id = ? AND assessment_type = 'HR' 
                             AND company_name = ? AND status = 'active' 
                             ORDER BY started_at DESC LIMIT 1");
        $stmt->execute([$studentIdForDb, $company]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $details = json_decode($session['details'], true);
            ob_clean(); echo json_encode([
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
            ob_clean(); echo json_encode(['success' => true, 'has_active' => false]);
        }
        exit;

    case 'start_session':
        $role = $input['role'] ?? 'Software Engineer';
        $company = $input['company'] ?? 'General';
        $concept = $input['concept'] ?? '';
        
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
                'history' => [],
                'projects' => $projects,
                'task_id' => $input['task_id'] ?? null
            ])
        ]);

        ob_clean(); echo json_encode(['success' => true, 'session_id' => $db->lastInsertId()]);
        break;

    case 'get_question':
        $sessionId = $input['session_id'];
        $userMessage = $input['message'] ?? ''; // Voice transcript
        
        // Fetch session
        $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
        $stmt->execute([$sessionId, $studentIdForDb]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            ob_clean(); echo json_encode(['success' => false, 'message' => 'Session not found']);
            exit;
        }

        $details = json_decode($session['details'], true);
        $history = $details['history'] ?? [];
        $role = $details['role'];
        $concept = $details['concept'] ?? '';
        $projects = $details['projects'] ?? [];  // Extract projects

        // Append user voice transcript if exists
        if (!empty($userMessage)) {
            $history[] = ['role' => 'user', 'content' => $userMessage];
            $details['history'] = $history;
            $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
               ->execute([json_encode($details), $sessionId]);
        }

        // Get AI HR Question with project context using Queue
        $jobId = \App\Services\QueueService::pushJob('getHRQuestion', [$role, $history, $projects, $concept], $userId);
        
        // Return job_id to the frontend
        ob_clean(); echo json_encode([
            'success' => true, 
            'job_id' => $jobId,
            'is_queued' => true,
            'debug' => [
                'history_count' => count($history),
                'projects_count' => count($projects)
            ]
        ]);
        break;

    case 'append_ai_history':
        $sessionId = $input['session_id'];
        $aiMessage = $input['message'];
        
        if (!empty($aiMessage)) {
            $stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
            $stmt->execute([$sessionId, $studentIdForDb]);
            if ($session = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $details = json_decode($session['details'], true);
                if (!isset($details['history'])) $details['history'] = [];
                $details['history'][] = ['role' => 'assistant', 'content' => $aiMessage];
                $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
                   ->execute([json_encode($details), $sessionId]);
            }
        }
        ob_clean(); echo json_encode(['success' => true]);
        exit;

    case 'generate_report_data':
        $sessionId = $input['session_id'];
        
        $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $details = json_decode($session['details'], true);
        $role = $details['role'];
        $concept = $details['concept'] ?? '';
        $history = $details['history'];
        $taskId = $details['task_id'] ?? null;
        
        // Check Minimum Time Requirement (20 mins = 1200 seconds) for assigned tasks
        if ($taskId) {
            $startTime = strtotime($session['started_at']);
            $elapsed = time() - $startTime;
            if ($elapsed < 1200) {
                // Return descriptive error
                $rem = 1200 - $elapsed;
                ob_clean(); echo json_encode(['success' => false, 'message' => "Session duration too short. Please participate for at least 20 minutes (Remaining: " . ceil($rem/60) . " mins)."]);
                exit;
            }
        }
        
        // Generate HR Report via AI
        session_write_close();
        $reportRes = $ai->generateHRReport($role, $history, $concept);
        
        if ($reportRes['success']) {
            // Strip markdown JSON block if AI hallucinated it
            $contentStr = $reportRes['content'];
            if (preg_match('/```json\s*(.*?)\s*```/s', $contentStr, $matches)) {
                $contentStr = $matches[1];
            }

            $aiData = json_decode($contentStr, true);
            
            // Fallback for truncated/malformed JSON: Try regex extraction
            if (!$aiData) {
                // Try to extract overall_score
                if (preg_match('/"overall_score":\s*"?(\d+)"?/i', $contentStr, $m)) {
                    $rawScore = $m[1];
                } else {
                    // Try to extract from the footer line requested in prompt
                    if (preg_match('/Overall Performance Score:\s*(\d+)%/i', $contentStr, $m)) {
                        $rawScore = $m[1];
                    } else {
                        $rawScore = 0;
                    }
                }
                
                // Try to extract content (everything between "content": " and the end or ")
                if (preg_match('/"content":\s*"(.*?)"\s*(?:,|\}$)/s', $contentStr, $m)) {
                    $reportText = stripcslashes($m[1]);
                } else if (preg_match('/"content":\s*"(.*)/s', $contentStr, $m)) {
                    // Truncated content: take everything from the start of content
                    $reportText = stripcslashes($m[1]);
                } else {
                    $reportText = $contentStr; 
                }
            } else {
                $reportText = $aiData['content'] ?? ($aiData['report'] ?? "Report content missing.");
                $rawScore = $aiData['overall_score'] ?? 0;
            }
            
            // Final numeric score extraction (take first number found)
            preg_match('/\d+/', (string)$rawScore, $m);
            $score = (int)($m[0] ?? 0);
            if ($score > 100) $score = 100; 
            if ($score < 0) $score = 0;

            // Generate HTML for PDF
            $html = generateHRReportHTML($session['usn'], $session['current_sem'] ?? 'N/A', $session['student_name'], $session['company_name'], $score, $reportText);
            $filename = "{$session['usn']}_" . ($session['current_sem'] ?? 'Sem') . "_{$sessionId}.pdf";

            // Decode current details to safely append
            $stmt = $db->prepare("SELECT details, started_at, usn FROM unified_ai_assessments WHERE id = ?");
            $stmt->execute([$sessionId]);
            $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
            $details = json_decode($sessionData['details'] ?? '{}', true);

            // Finalize Status immediately so closing the browser doesn't orphan the completion
            $db->prepare("UPDATE unified_ai_assessments 
                          SET score = ?, feedback = ?, status = 'completed', completed_at = CURRENT_TIMESTAMP 
                          WHERE id = ?")
               ->execute([$score, "HR Report Generated (Pending PDF Save)", $sessionId]);
               
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

            ob_clean(); echo json_encode(['success' => true, 'report_html' => $html, 'filename' => $filename]);
        } else {
            ob_clean(); echo json_encode(['success' => false, 'message' => 'Report generation failed']);
        }
        break;

    case 'save_pdf_report':
        $sessionId = $_POST['session_id'] ?? 0;
        
        if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $dir = REPORTS_UPLOAD_PATH . '/hr/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $filename = basename($_FILES['pdf']['name']);
            $targetPath = $dir . $filename;
            
            if (move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
                $publicPath = "uploads/reports/hr/" . $filename;
                
                // Finalize DB
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

                ob_clean(); echo json_encode(['success' => true, 'path' => $publicPath]);
            } else {
            ob_clean(); echo json_encode(['success' => false, 'message' => 'File save failed']);
            }
        } else {
            ob_clean(); echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
        break;
}

/**
 * Allow safe HTML from AI report (bold, breaks, lists) so it renders; strip script/style.
 */
function allowReportHtml($content) {
    $allowed = '<b><strong><br><p><ul><ol><li><em><i><h2><h3><h4><span>';
    $cleaned = strip_tags($content, $allowed);
    return $cleaned ?: htmlspecialchars((string)$content, ENT_QUOTES, 'UTF-8');
}

function generateHRReportHTML($usn, $sem, $name, $role, $score, $content) {
    return "
    <html>
    <head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
        <style>
            body { font-family: 'Helvetica', sans-serif; padding: 40px; line-height: 1.6; color: #333; }
            h1 { color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; margin-bottom: 20px; }
            h2 { color: #34495e; margin-top: 30px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
            .meta { background: #ecf0f1; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #bdc3c7; }
            .score-box { float: right; background: #2c3e50; color: white; padding: 15px 25px; border-radius: 8px; font-size: 24px; font-weight: bold; }
            .content { font-size: 14px; }
            strong { color: #000; }
            .metric { display: flex; justify-content: space-between; border-bottom: 1px dashed #ccc; padding: 5px 0; }
        </style>
    </head>
    <body>
        <div class='score-box'>{$score}/100</div>
        <h1>HR Assessment Report</h1>
        <div class='meta'>
            <p><strong>Student Name:</strong> {$name}</p>
            <p><strong>USN:</strong> {$usn}</p>
            <p><strong>Semester:</strong> {$sem}</p>
            <p><strong>Target Role:</strong> {$role}</p>
            <p><strong>Assessment Type:</strong> Behavioral & Culture Fit</p>
            <p><strong>Date:</strong> " . date('d M Y') . "</p>
        </div>
        <div class='content'>
            " . allowReportHtml($content) . "
        </div>
    </body>
    </html>";
}

