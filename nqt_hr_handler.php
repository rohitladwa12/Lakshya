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
}
