<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method: ' . $_SERVER['REQUEST_METHOD'] . '. Please ensure you are submitting a POST request.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$input = array_merge($input, $_POST);

$action = $input['action'] ?? '';
$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment(); // USN for GMIT, user_id for GMU (avoids 0 for GMIT)

if (!$userId && !getUsername()) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$db = getDB();
$aiService = new AIService();
$studentModel = new StudentProfile();

switch ($action) {
    case 'check_active':
        $institution = getInstitution() ?: 'GMU';
        $sql = "SELECT id, role_name, conversation_history FROM mock_ai_interview_sessions 
                WHERE student_id = ? AND status = 'active' AND institution = ? 
                ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentIdForDb, $institution]);
        $session = $stmt->fetch();
        
        if ($session) {
            echo json_encode([
                'success' => true,
                'has_active' => true,
                'session_id' => $session['id'],
                'role' => $session['role_name'],
                'history' => json_decode($session['conversation_history'], true) ?: []
            ]);
        } else {
            echo json_encode(['success' => true, 'has_active' => false]);
        }
        exit;

    case 'start':
        $role = $input['role'] ?? 'AI Engineer';
        $company = $input['company'] ?? 'General';
        $concept = $input['concept'] ?? '';
        $type = $input['type'] ?? 'Technical';
        $institution = getInstitution() ?: 'GMU';
        $sql = "INSERT INTO mock_ai_interview_sessions (student_id, role_name, status, institution, concept) VALUES (?, ?, 'active', ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentIdForDb, $role, $institution, $concept]);
        $sessionId = $db->lastInsertId();

        // Log session start
        trackActivity('mock_ai_start', "Started Mock AI session for $role", [
            'role' => $role,
            'institution' => $institution,
            'session_id' => $sessionId
        ], 'mock_ai_session', $sessionId);

        // Note: We might want to store company/type in the session history or a temp table if legacy doesn't have it.
        // For now, let's just add it to the first message metadata.
        
        // Initial Welcome Message
        $welcomeMsg = "Hi, welcome to your Mock AI Interview for the **$role** position. Ready to begin? If ready, type 'start' or 'yes' to start the session.";
        $aiMsg = ['role' => 'assistant', 'content' => $welcomeMsg];
        $history = [$aiMsg];
        
        // Update history in DB
        $sqlUpdate = "UPDATE mock_ai_interview_sessions SET conversation_history = ? WHERE id = ?";
        $db->prepare($sqlUpdate)->execute([json_encode($history), $sessionId]);
        
        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'message' => $welcomeMsg
        ]);
        break;

    case 'chat':
        $sessionId = $input['session_id'] ?? 0;
        $userMessage = $input['message'] ?? '';
        
        // Fetch session
        $sql = "SELECT * FROM mock_ai_interview_sessions WHERE id = ? AND student_id = ? AND status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$sessionId, $studentIdForDb]);
        $session = $stmt->fetch();
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found or already closed']);
            exit;
        }
        
        $role = $session['role_name'];
        $concept = $session['concept'] ?? '';
        $type = $input['type'] ?? 'Technical';
        $history = json_decode($session['conversation_history'], true) ?: [];
        $history[] = ['role' => 'user', 'content' => $userMessage];
        
        $profile = $studentModel->getByUserId($userId);
        
        // Fetch Portfolio Projects for HR context
        require_once __DIR__ . '/../../src/Models/Portfolio.php';
        $portfolioModel = new Portfolio();
        $institution = getInstitution() ?: ($profile['institution'] ?? 'GMU');
        $projects = $portfolioModel->getStudentPortfolio($studentIdForDb, $institution);

        // Fetch Aptitude Questions ONLY if needed for Aptitude mode or specifically requested
        $aptitudeQuestions = [];
        if (strtolower($type) === 'aptitude') {
            require_once __DIR__ . '/../../src/Models/AptitudeQuestion.php';
            $aptModel = new AptitudeQuestion();
            $aptitudeQuestions = $aptModel->getRandomQuestions(25);
        }

        session_write_close();
        $response = $aiService->getTechnicalInterviewResponse($role, $history, $profile, '', $type, $projects, $aptitudeQuestions, $concept);
        
        if ($response['success']) {
            $aiContent = $response['content'];
            $history[] = ['role' => 'assistant', 'content' => $aiContent];
            
            $isEnd = (strpos($aiContent, '[END_INTERVIEW]') !== false);
            $status = $isEnd ? 'completed' : 'active';
            $completedAt = $isEnd ? date('Y-m-d H:i:s') : null;
            $reportContent = null;
            $overallScore = null;

            // Engagement Check for Auto-End
            $userMsgs = array_filter($history, fn($m) => $m['role'] === 'user');
            $hasEngagement = count($userMsgs) >= 2;

            if ($isEnd && $hasEngagement) {
                session_write_close();
                $reportRes = $aiService->generateTechnicalInterviewReport($role, $history, $type, $concept);
                if ($reportRes['success']) {
                    $reportContent = $reportRes['content'];
                    $overallScore = $reportRes['overall_score'] ?? null;

                    // SAVE TO UNIFIED TABLE
                    try {
                        $profile = $studentModel->getByUserId($userId);
                        // We need the company name. We can try to infer it from the first prompt or pass it in chat.
                        // Since we don't have it in legacy schema, let's assume 'General' if not found.
                        // Better: The client can pass it in the final chat message or we can store it in a session.
                        $companyNameFromInput = $input['company'] ?? 'General'; 
                        $assessmentTypeFromInput = $input['type'] ?? 'Technical';

                        $sqlUnified = "INSERT INTO unified_ai_assessments (
                            student_id, institution, student_name, usn, aadhar, 
                            current_sem, branch, assessment_type, 
                            company_name, score, total_marks, 
                            feedback, details, status, completed_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                        
                        $db->prepare($sqlUnified)->execute([
                            $studentIdForDb,
                            getInstitution(),
                            $profile['name'] ?? getFullName(),
                            $profile['usn'] ?? getUsername(),
                            $profile['aadhar'] ?? null,
                            $profile['semester'] ?? null,
                            $profile['department'] ?? null,
                            $assessmentTypeFromInput ?: 'Mock AI Round',
                            $companyNameFromInput,
                            $overallScore,
                            100, // Total marks is 100 for interviews
                            "Interview Completed",
                            json_encode([
                                'transcript' => $history,
                                'report' => $reportContent,
                                'role' => $role
                            ]),
                            'completed'
                        ]);

                        // Log completion to database
                        trackActivity('mock_ai_complete', "Completed Mock AI session for $role", [
                            'score' => $overallScore,
                            'role' => $role,
                            'type' => $assessmentTypeFromInput
                        ], 'mock_ai_session', $input['session_id']);

                    } catch (Exception $e) {
                        logMessage("Failed to save to unified table: " . $e->getMessage(), 'ERROR');
                    }
                }
            }
            
            // Update DB to include overall_score
            $sqlH = "UPDATE mock_ai_interview_sessions SET conversation_history = ?, status = ?, completed_at = ?, report_content = ?, overall_score = ? WHERE id = ?";
            $db->prepare($sqlH)->execute([json_encode($history), $status, $completedAt, $reportContent, $overallScore, $sessionId]);
            
            echo json_encode([
                'success' => true,
                'message' => $aiContent,
                'is_end' => $isEnd,
                'session_id' => $sessionId
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'AI Response Failed']);
        }
        break;

    case 'evaluate_code':
        $sessionId = $input['session_id'] ?? 0;
        $code = $input['code'] ?? '';
        $language = $input['language'] ?? 'python';
        
        // Fetch session
        $sql = "SELECT * FROM mock_ai_interview_sessions WHERE id = ? AND student_id = ? AND status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$sessionId, $studentIdForDb]);
        $session = $stmt->fetch();
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found or already closed']);
            exit;
        }

        $history = json_decode($session['conversation_history'], true) ?: [];
        $role = $session['role_name'];

        // Use AI Service to evaluate code
        session_write_close();
        $evalRes = $aiService->evaluateCode($code, $language, "Technical task for role: $role");
        
        if ($evalRes['success']) {
            $evaluation = json_decode($evalRes['content'], true);
            
            // Log to history
            $history[] = ['role' => 'user', 'content' => "User ran code simulation ({$language}). Result: " . ($evaluation['passed'] ? 'PASSED' : 'FAILED') . " - Score: {$evaluation['score']}/10"];
            $history[] = ['role' => 'system', 'content' => "Code Evaluation: " . $evalRes['content']];
            
            $db->prepare("UPDATE mock_ai_interview_sessions SET conversation_history = ? WHERE id = ?")
               ->execute([json_encode($history), $sessionId]);

            echo json_encode([
                'success' => true, 
                'evaluation' => $evaluation
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Evaluation service failed']);
        }
        break;

    case 'end_session':
        $sessionId = $input['session_id'] ?? 0;
        
        // Fetch session
        $sql = "SELECT * FROM mock_ai_interview_sessions WHERE id = ? AND student_id = ? AND status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$sessionId, $studentIdForDb]);
        $session = $stmt->fetch();
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found or already closed']);
            exit;
        }

        $role = $session['role_name'];
        $concept = $session['concept'] ?? '';
        $history = json_decode($session['conversation_history'], true) ?: [];
        $completedAt = date('Y-m-d H:i:s');
        
        // Engagement Check for Manual End
        $userMsgs = array_filter($history, fn($m) => $m['role'] === 'user');
        if (count($userMsgs) < 2) {
            $db->prepare("UPDATE mock_ai_interview_sessions SET status = 'cancelled', completed_at = ? WHERE id = ?")
               ->execute([$completedAt, $sessionId]);
            echo json_encode([
                'success' => true, 
                'message' => 'Session ended, but no report was generated as it was too brief (minimum 2 responses required).',
                'is_incomplete' => true,
                'session_id' => $sessionId
            ]);
            exit;
        }

        // Generate Report
        $reportContent = null;
        $overallScore = null;
        $type = $input['type'] ?? 'Technical';
        
        $reportRes = $aiService->generateTechnicalInterviewReport($role, $history, $type, $concept);
        if ($reportRes['success']) {
            $reportContent = $reportRes['content'];
            $overallScore = $reportRes['overall_score'] ?? null;

            // SAVE TO UNIFIED TABLE
            try {
                $profile = $studentModel->getByUserId($userId);
                $companyNameFromInput = $input['company'] ?? 'General'; 
                $assessmentTypeFromInput = $input['type'] ?? 'Technical';

                $sqlUnified = "INSERT INTO unified_ai_assessments (
                    student_id, institution, student_name, usn, aadhar, 
                    current_sem, branch, assessment_type, 
                    company_name, score, total_marks, 
                    feedback, details, status, completed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                
                $db->prepare($sqlUnified)->execute([
                    $studentIdForDb,
                    getInstitution(),
                    $profile['name'] ?? getFullName(),
                    $profile['usn'] ?? getUsername(),
                    $profile['aadhar'] ?? null,
                    $profile['semester'] ?? null,
                    $profile['department'] ?? null,
                    $assessmentTypeFromInput ?: 'Mock AI Round',
                    $companyNameFromInput,
                    $overallScore,
                    100, 
                    "Interview Manually Completed",
                    json_encode([
                        'transcript' => $history,
                        'report' => $reportContent,
                        'role' => $role
                    ]),
                    'completed'
                ]);
            } catch (Exception $e) {
                logMessage("Failed to save to unified table: " . $e->getMessage(), 'ERROR');
            }
        }

        // Update session
        $sqlH = "UPDATE mock_ai_interview_sessions SET status = 'completed', completed_at = ?, report_content = ?, overall_score = ? WHERE id = ?";
        $db->prepare($sqlH)->execute([$completedAt, $reportContent, $overallScore, $sessionId]);

        echo json_encode([
            'success' => true,
            'message' => 'Session ended and report generated',
            'session_id' => $sessionId
        ]);
        break;

    case 'get_report':
        $sessionId = $input['session_id'] ?? 0;
        $sql = "SELECT report_content, role_name, conversation_history, started_at FROM mock_ai_interview_sessions WHERE id = ? AND student_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$sessionId, $studentIdForDb]);
        $session = $stmt->fetch();

        if ($session && $session['report_content']) {
            echo json_encode(['success' => true, 'report' => $session['report_content'], 'role' => $session['role_name']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Report not found or not yet generated']);
        }
        break;

    case 'save_pdf':
        if (!isset($_FILES['report_pdf'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit;
        }

        $sessionId = $_POST['session_id'] ?? null;
        $username = getUsername() ?? $studentIdForDb; // Fix: $username was undefined
        $sem = 'Sem';

        if ($sessionId) {
            // Fix: current_sem column doesn't exist in mock_ai_interview_sessions.
            // Fall back to student profile for semester info.
            $profile = $studentModel->getByUserId($userId);
            $sem = $profile['semester'] ?? 'Sem';
        } else {
            $res = $db->prepare("SELECT id FROM mock_ai_interview_sessions WHERE student_id = ? ORDER BY id DESC LIMIT 1");
            $res->execute([$studentIdForDb]);
            $s = $res->fetch();
            $sessionId = $s['id'] ?? '0';
            $profile = $studentModel->getByUserId($userId);
            $sem = $profile['semester'] ?? 'Sem';
        }

        $filename = "{$username}_{$sem}_{$sessionId}.pdf";
        $uploadDir = REPORTS_UPLOAD_PATH . '/mock_ai/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['report_pdf']['tmp_name'], $destination)) {
            $publicPath = "uploads/reports/mock_ai/" . $filename;
            echo json_encode(['success' => true, 'message' => 'Report stored successfully', 'path' => $publicPath]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save report to server']);
        }
        break;

    case 'cancel_pending':
        $sessionId = $input['session_id'] ?? 0;
        $sql = "UPDATE mock_ai_interview_sessions SET status = 'cancelled', completed_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND student_id = ? AND status = 'active'";
        $db->prepare($sql)->execute([$sessionId, $studentIdForDb]);
        echo json_encode(['success' => true, 'message' => 'Pending session retired']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action not found']);
        break;
}
