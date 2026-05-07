<?php
/**
 * NQT Technical Handler
 * Manages NQT coding sessions and evaluation.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

header('Content-Type: application/json');
ob_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    set_time_limit(300);

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $input = array_merge($input, $_POST);
    $action = $input['action'] ?? '';

    requireLogin();
    $userId = getUserId();
    $studentIdForDb = getStudentIdForAssessment();

    $db = getDB();
    $ai = new AIService();
    $studentModel = new StudentProfile();

switch ($action) {
    case 'start_session':
        $sql = "INSERT INTO unified_ai_assessments (
            student_id, institution, student_name, usn, aadhar, current_sem, branch, 
            assessment_type, company_name, status, details, started_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'NQT Technical', 'TCS NQT Practice', 'active', ?, CURRENT_TIMESTAMP)";

        $profile = $studentModel->getByUserId($userId);
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $studentIdForDb,
            getInstitution(),
            $profile['name'] ?? getFullName(),
            $profile['usn'] ?? getUsername(),
            $profile['aadhar'] ?? null,
            $profile['semester'] ?? null,
            $profile['department'] ?? null,
            json_encode([
                'role' => 'Software Engineer (NQT)', 
                'history' => [],
                'task_id' => $input['task_id'] ?? null
            ])
        ]);

        echo json_encode(['success' => true, 'session_id' => $db->lastInsertId()]);
        break;

    case 'get_question':
        $sessionId = $input['session_id'];
        
        // 1. Fetch random question from nqt_coding_questions
        $stmt = $db->query("SELECT * FROM nqt_coding_questions ORDER BY RAND() LIMIT 1");
        $baseQ = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$baseQ) {
            // Fallback to general coding questions
            $stmt = $db->query("SELECT question_text as problem_statement, title, difficulty FROM coding_problems ORDER BY RAND() LIMIT 1");
            $baseQ = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 2. Transmute the question via AI to be unique for this student
        session_write_close();
        $mutatedResponse = $ai->mutateCodingChallenge($baseQ);
        
        if ($mutatedResponse['success']) {
            $aiData = $mutatedResponse['data'];
            
            // Store the generated question for history
            $stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
            $stmt->execute([$sessionId]);
            $details = json_decode($stmt->fetchColumn(), true);
            $details['history'][] = ['role' => 'system', 'content' => "Mutated Challenge: " . json_encode($aiData)];
            
            $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
               ->execute([json_encode($details), $sessionId]);

            echo json_encode(['success' => true, 'data' => $aiData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'AI mutation failed']);
        }
        break;

    case 'submit_code':
        $code = $input['code'];
        $language = $input['language'];
        $problem = $input['problem_statement'];
        $sessionId = $input['session_id'];

        session_write_close();
        $eval = $ai->evaluateCode($code, $language, $problem);
        
        if ($eval['success']) {
            $result = json_decode($eval['content'], true);
            
            $stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
            $stmt->execute([$sessionId]);
            $details = json_decode($stmt->fetchColumn(), true);
            
            $details['history'][] = ['role' => 'user', 'content' => "Submitted Code ($language):\n$code"];
            $details['history'][] = ['role' => 'system', 'content' => "Evaluation: " . json_encode($result)];

            $db->prepare("UPDATE unified_ai_assessments SET status = 'completed', score = ?, details = ? WHERE id = ?")
               ->execute([$result['score'] * 10, json_encode($details), $sessionId]);

            echo json_encode(['success' => true, 'result' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Evaluation failed']);
        }
        break;

    case 'get_session_summary':
        $sessionId = $input['session_id'] ?? 0;
        
        $stmt = $db->prepare("SELECT details, score, started_at, completed_at FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
        $stmt->execute([$sessionId, $studentIdForDb]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            exit;
        }

        $details = json_decode($row['details'], true) ?? [];
        $history = $details['history'] ?? [];
        
        // Calculate summary from history
        $evaluations = [];
        $totalScore = 0;
        $count = 0;

        foreach ($history as $h) {
            if ($h['role'] === 'system' && strpos($h['content'], 'Evaluation:') === 0) {
                $eval = json_decode(substr($h['content'], 12), true);
                if ($eval) {
                    $evaluations[] = $eval;
                    $totalScore += $eval['score'] ?? 0;
                    $count++;
                }
            }
        }

        $avgScore = $count > 0 ? round(($totalScore / $count) * 10, 1) : $row['score'];

        // Update session as completed if it wasn't already
        $db->prepare("UPDATE unified_ai_assessments SET status = 'completed', score = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$avgScore, $sessionId]);

        echo json_encode([
            'success' => true,
            'summary' => [
                'score' => $avgScore,
                'total_questions' => $count,
                'evaluations' => $evaluations,
                'started_at' => $row['started_at'],
                'completed_at' => date('Y-m-d H:i:s')
            ]
        ]);
        break;

    case 'generate_report_data':
        $sessionId = $input['session_id'];
        
        $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            exit;
        }

        $details = json_decode($session['details'], true);
        $role = $details['role'] ?? 'Software Engineer (NQT)';
        $history = $details['history'] ?? [];

        // Generate Text Report via AI
        session_write_close();
        $reportRes = $ai->generateTechnicalInterviewReport($role, $history, 'NQT Technical');
        
        if ($reportRes['success']) {
            $reportText = $reportRes['content'];
            
            // --- STRICT PERFORMANCE SCORING ---
            // Calculate actual average score from evaluation history
            $totalScore = 0;
            $count = 0;
            foreach ($history as $h) {
                if ($h['role'] === 'system' && strpos($h['content'], 'Evaluation:') === 0) {
                    $evalData = json_decode(substr($h['content'], 12), true);
                    if ($evalData && isset($evalData['score'])) {
                        $totalScore += (int)$evalData['score'];
                        $count++;
                    }
                }
            }
            // STRICT RULE: If no technical work was evaluated, score is 0.
            // Do NOT fall back to AI hallucination.
            $performanceScore = $count > 0 ? round(($totalScore / $count) * 10) : 0;

            // Generate HTML for the PDF
            $html = generateReportHTML($session['usn'], $session['current_sem'] ?? 'N/A', $session['student_name'], $session['company_name'], $performanceScore, $reportText);
            $filename = "{$session['usn']}_NQT_" . ($session['current_sem'] ?? 'Sem') . ".pdf";

            // Update DB with Final Strict Score
            $db->prepare("UPDATE unified_ai_assessments SET score = ?, feedback = ? WHERE id = ?")
               ->execute([$performanceScore, "Report Generated", $sessionId]);

            echo json_encode(['success' => true, 'report_html' => $html, 'filename' => $filename]);
        } else {
            $errorMsg = $reportRes['message'] ?? 'AI Report Synthesis failed';
            echo json_encode(['success' => false, 'message' => "Report generation failed: " . $errorMsg]);
        }
        break;

    case 'save_pdf_report':
        $sessionId = $_POST['session_id'] ?? 0;
        
        if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $dir = REPORTS_UPLOAD_PATH . '/technical/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $filename = basename($_FILES['pdf']['name']);
            $targetPath = $dir . $filename;
            
            if (move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
                $publicPath = "uploads/reports/technical/" . $filename;
                
                // Finalize DB
                $stmt = $db->prepare("SELECT details, score FROM unified_ai_assessments WHERE id = ?");
                $stmt->execute([$sessionId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $details = json_decode($row['details'], true);
                $finalScore = $row['score'];
                
                $details['report_path'] = $publicPath;
                
                $db->prepare("UPDATE unified_ai_assessments SET status = 'completed', details = ? WHERE id = ?")
                   ->execute([json_encode($details), $sessionId]);

                // Update task completions if task_id exists
                if (isset($details['task_id']) && $details['task_id']) {
                    $taskId = $details['task_id'];
                    $stmtComp = $db->prepare("INSERT INTO task_completions 
                                          (task_id, student_id, score, time_taken) 
                                          VALUES (?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE 
                                          score = VALUES(score), 
                                          completed_at = CURRENT_TIMESTAMP");
                    $stmtComp->execute([$taskId, $studentIdForDb, $finalScore, 0]);
                }

                echo json_encode(['success' => true, 'path' => $publicPath]);
            } else {
                echo json_encode(['success' => false, 'message' => 'File save failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
        break;
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function generateReportHTML($usn, $sem, $name, $company, $score, $content) {
    // Simple Markdown-to-HTML conversion for a premium feel
    $htmlContent = htmlspecialchars($content);
    $htmlContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $htmlContent);
    $htmlContent = preg_replace('/^- (.*)$/m', '<li>$1</li>', $htmlContent);
    $htmlContent = preg_replace('/(<li>.*<\/li>)+/s', '<ul>$0</ul>', $htmlContent);
    $nlContent = nl2br($htmlContent);

    return "
    <html>
    <head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
        <style>
            body { font-family: sans-serif; padding: 40px; line-height: 1.6; color: #333; }
            h1 { color: #800000; border-bottom: 2px solid #800000; padding-bottom: 10px; margin-bottom: 20px; }
            h2 { color: #333; margin-top: 30px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
            .meta { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd; }
            .score-box { float: right; background: #800000; color: white; padding: 15px 25px; border-radius: 8px; font-size: 24px; font-weight: bold; }
            .content { font-size: 14px; }
            strong { color: #000; }
            ul { padding-left: 20px; }
            li { margin-bottom: 5px; }
        </style>
    </head>
    <body>
        <div class='score-box'>{$score}%</div>
        <h1>NQT Technical Assessment Report</h1>
        <div class='meta'>
            <p><strong>Student Name:</strong> {$name}</p>
            <p><strong>USN:</strong> {$usn}</p>
            <p><strong>Semester:</strong> {$sem}</p>
            <p><strong>Assessment Type:</strong> {$company}</p>
            <p><strong>Date:</strong> " . date('d M Y') . "</p>
        </div>
        <div class='content'>
            {$nlContent}
        </div>
    </body>
    </html>";
}
ob_end_flush();

