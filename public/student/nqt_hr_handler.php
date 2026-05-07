<?php
/**
 * NQT HR Round Handler
 * Manages NQT behavioral interview sessions.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
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
        $profile = $studentModel->getByUserId($userId);
        
        // Fetch projects for context
        require_once __DIR__ . '/../../src/Models/Portfolio.php';
        $portfolioModel = new Portfolio();
        $portfolio = $portfolioModel->getStudentPortfolio($userId, getInstitution());
        
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
            student_id, institution, student_name, usn, branch, current_sem, assessment_type, company_name, status, details, started_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'NQT HR', 'TCS NQT Practice', 'active', ?, CURRENT_TIMESTAMP)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $studentIdForDb,
            getInstitution(),
            $profile['name'] ?? getFullName(),
            $profile['usn'] ?? getUsername(),
            $profile['department'] ?? null,
            $profile['semester'] ?? null,
            json_encode([
                'role' => 'TCS Graduate Candidate', 
                'history' => [],
                'projects' => $projects,
                'task_id' => $input['task_id'] ?? null
            ])
        ]);

        echo json_encode(['success' => true, 'session_id' => $db->lastInsertId()]);
        break;

    case 'get_question':
        $sessionId = $input['session_id'];
        $userMessage = $input['message'] ?? '';
        
        $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        $details = json_decode($session['details'], true);
        $history = $details['history'] ?? [];
        $projects = $details['projects'] ?? [];

        if (!empty($userMessage)) {
            $history[] = ['role' => 'user', 'content' => $userMessage];
        }

        session_write_close();
        $response = $ai->getHRQuestion('TCS NQT Candidate', $history, $projects);
        
        if ($response['success']) {
            $aiData = json_decode($response['content'], true);
            $history[] = ['role' => 'assistant', 'content' => $response['content']];
            
            $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
               ->execute([json_encode([
                   'role' => 'TCS Candidate', 
                   'history' => $history,
                   'projects' => $projects
               ]), $sessionId]);

            echo json_encode(['success' => true, 'data' => $aiData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'AI failed']);
        }
        break;

    case 'submit_interview':
        $sessionId = $input['session_id'];
        $db->prepare("UPDATE unified_ai_assessments SET status = 'completed', completed_at = NOW() WHERE id = ?")
           ->execute([$sessionId]);
        echo json_encode(['success' => true]);
        break;

    case 'generate_report_data':
        $sessionId = $input['session_id'] ?? 0;
        
        $stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
        $stmt->execute([$sessionId, $studentIdForDb]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            exit;
        }

        $details = json_decode($session['details'], true);
        $role = $details['role'] ?? 'Candidate';
        $history = $details['history'] ?? [];

        // STRICT SCORING: Count actual user contributions
        $userMessages = 0;
        foreach ($history as $msg) {
            if ($msg['role'] === 'user' && strlen(trim($msg['content'])) > 5) {
                $userMessages++;
            }
        }

        // Generate Text Report via AI
        session_write_close();
        $reportRes = $ai->generateHRReport($role, $history);
        
        if ($reportRes['success']) {
            $reportText = $reportRes['content'];
            $aiScore = $reportRes['overall_score'] ?? 0;

            // Enforcement: If less than 3 meaningful responses, score is 0.
            $performanceScore = $userMessages >= 3 ? $aiScore : 0;

            // Generate HTML for the PDF
            $html = generateReportHTML($session['usn'], $session['current_sem'] ?? 'N/A', $session['student_name'], "Behavioral Assessment (HR)", $performanceScore, $reportText);
            $filename = "{$session['usn']}_NQT_HR_" . ($session['current_sem'] ?? 'Sem') . ".pdf";

            // Update DB with Final Strict Score
            $db->prepare("UPDATE unified_ai_assessments SET score = ?, feedback = ? WHERE id = ?")
               ->execute([$performanceScore, "HR Report Generated", $sessionId]);

            echo json_encode(['success' => true, 'report_html' => $html, 'filename' => $filename]);
        } else {
            $errorMsg = $reportRes['message'] ?? 'AI Report Synthesis failed';
            echo json_encode(['success' => false, 'message' => "Report generation failed: " . $errorMsg]);
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
                
                $stmt = $db->prepare("SELECT details FROM unified_ai_assessments WHERE id = ?");
                $stmt->execute([$sessionId]);
                $details = json_decode($stmt->fetchColumn(), true);
                
                $details['report_path'] = $publicPath;
                
                $db->prepare("UPDATE unified_ai_assessments SET details = ? WHERE id = ?")
                   ->execute([json_encode($details), $sessionId]);

                echo json_encode(['success' => true, 'path' => $publicPath]);
            } else {
                echo json_encode(['success' => false, 'message' => 'File save failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
        break;
}

function generateReportHTML($usn, $sem, $name, $type, $score, $content) {
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
        </style>
    </head>
    <body>
        <div class='score-box'>{$score}%</div>
        <h1>NQT HR Behavioral Report</h1>
        <div class='meta'>
            <p><strong>Student Name:</strong> {$name}</p>
            <p><strong>USN:</strong> {$usn}</p>
            <p><strong>Semester:</strong> {$sem}</p>
            <p><strong>Assessment Type:</strong> {$type}</p>
            <p><strong>Date:</strong> " . date('d M Y') . "</p>
        </div>
        <div class='content'>
            {$content}
        </div>
    </body>
    </html>";
}

