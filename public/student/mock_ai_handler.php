<?php
ob_start(); // Buffer output to prevent stray PHP warnings from corrupting JSON
// AI calls are synchronous and can take up to ~120s (see AIService::callAPI).
// Without this the default 30s max_execution_time kills the request mid-flight,
// producing truncated/empty responses -> "next question doesn't appear".
set_time_limit(300);
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/AIService.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

// Discard any output generated during bootstrap (e.g. PHP warnings when display_errors=1)
ob_end_clean();
ob_start();

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

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}
$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment(); // USN for GMIT, user_id for GMU (avoids 0 for GMIT)

// Rate Limit only the AI-heavy actions. Lightweight/background actions
// (autosave every 20s, check_active, polling, pdf save) must NOT consume the
// AI budget, otherwise a normal session exhausts it and the next question is
// silently blocked with "Too many requests".
$aiHeavyActions = ['chat', 'evaluate_code', 'end_session', 'start'];
if (in_array($action, $aiHeavyActions, true) && !checkRateLimit("mock_ai_api_" . $userId, 30, 60)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a minute.']);
    exit;
}

$db = getDB();
$aiService = new AIService();
$studentModel = new StudentProfile();

function buildOrchestratedStep($type, $questionNum, $totalQs, $message, $aiService)
{
    $phase = strtoupper($type);

    $step = [
        "title" => $type . " Round",
        "phase" => $phase,
        "current_q" => $questionNum,
        "total_questions" => $totalQs,
        "message" => $message,
        "tts" => true,
        "voice" => false
    ];

    if ($phase === 'APTITUDE') {
        $step["ui"] = "chat";
        $step["components"] = ["chat_window", "timer"];
    } elseif ($phase === 'TECHNICAL' || $phase === 'TECHNICAL_CODING') {
        $step["ui"] = "editor";
        $step["components"] = ["chat_window", "code_editor", "timer"];
    } else {
        $step["ui"] = "chat";
        $step["components"] = ["chat_window", "voice_engine", "tts_engine"];
        $step["voice"] = true;
    }

    return $step;
}

function parseMCQOptions($text)
{
    $options = [];
    $body = $text;

    $pattern = '/\b([A-D])[\.\)]\s*(.*?)(?=\b[A-D][\.\)]|$)/is';
    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $options[] = [
                "key" => trim($match[1]),
                "text" => trim($match[2])
            ];
        }
        $body = preg_replace('/(\b([A-D])[\.\)]\s*.*)/is', '', $text);
    } else {
        $options = [
            ["key" => "A", "text" => "Option A"],
            ["key" => "B", "text" => "Option B"],
            ["key" => "C", "text" => "Option C"],
            ["key" => "D", "text" => "Option D"]
        ];
    }

    return [
        "body" => trim($body),
        "options" => $options
    ];
}

function sanitizeHistory($history)
{
    if (!is_array($history)) {
        return [];
    }
    $sanitized = [];
    foreach ($history as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $role = (string) ($msg['role'] ?? 'user');
        $content = $msg['content'] ?? '';
        if (!is_string($content)) {
            $content = is_array($content) ? json_encode($content) : (string) $content;
        }
        $sanitized[] = [
            'role' => $role,
            'content' => $content
        ];
    }
    return $sanitized;
}

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
            $history = sanitizeHistory(json_decode($session['conversation_history'], true));
            $assistantMessages = array_filter($history, fn($m) => $m['role'] === 'assistant');
            $questionNum = max(1, count($assistantMessages));
            $lastMsg = end($history);
            $lastContent = $lastMsg ? $lastMsg['content'] : 'Welcome back';

            $checkpoint = null;
            try {
                $checkStmt = $db->prepare("SELECT checkpoint FROM mock_ai_interview_sessions WHERE id = ?");
                $checkStmt->execute([$session['id']]);
                $cRow = $checkStmt->fetch();
                if ($cRow && !empty($cRow['checkpoint'])) {
                    $checkpoint = json_decode($cRow['checkpoint'], true);
                }
            } catch (\Exception $e) {
            }

            $type = 'Technical'; // Default fallback
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $hMsg = $history[$i];
                if ($hMsg['role'] === 'assistant') {
                    $hContentLower = strtolower($hMsg['content']);
                    if (preg_match('/\b[A-D][\.\)]\s*/i', $hMsg['content'])) {
                        $type = 'Aptitude';
                        break;
                    }
                    if (stripos($hContentLower, 'starting with the aptitude') !== false || stripos($hContentLower, 'start with aptitude') !== false || stripos($hContentLower, 'welcome to the aptitude') !== false) {
                        $type = 'Aptitude';
                        break;
                    }
                    if (stripos($hContentLower, 'starting with the technical') !== false || stripos($hContentLower, 'start with technical') !== false || stripos($hContentLower, 'welcome to the technical') !== false) {
                        $type = 'Technical';
                        break;
                    }
                    if (stripos($hContentLower, 'starting with the hr') !== false || stripos($hContentLower, 'start with hr') !== false || stripos($hContentLower, 'welcome to the hr') !== false) {
                        $type = 'HR';
                        break;
                    }
                } elseif ($hMsg['role'] === 'user') {
                    $uContentLower = strtolower(trim($hMsg['content']));
                    if ($uContentLower === 'aptitude' || $uContentLower === 'switch to aptitude' || $uContentLower === 'logical') {
                        $type = 'Aptitude';
                        break;
                    }
                    if ($uContentLower === 'technical' || $uContentLower === 'switch to technical' || $uContentLower === 'coding') {
                        $type = 'Technical';
                        break;
                    }
                    if ($uContentLower === 'hr' || $uContentLower === 'switch to hr' || $uContentLower === 'behavioral') {
                        $type = 'HR';
                        break;
                    }
                }
            }

            $totalQs = ($type === 'Aptitude') ? 25 : (($type === 'Technical') ? 10 : 8);
            $step = buildOrchestratedStep($type, $questionNum, $totalQs, $lastContent, $aiService);

            echo json_encode([
                'success' => true,
                'has_active' => true,
                'session_id' => $session['id'],
                'role' => $session['role_name'],
                'history' => $history,
                'step' => $step,
                'checkpoint' => $checkpoint
            ]);
        } else {
            echo json_encode(['success' => true, 'has_active' => false]);
        }
        exit;

    case 'start':
        // Strict limit for starting new mock sessions (2 per minute)
        if (!checkRateLimit("mock_ai_start_" . $userId, 2, 60)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Slow down! You can only start 2 sessions per minute.']);
            exit;
        }
        $role = $input['role'] ?? 'AI Engineer';
        $company = $input['company'] ?? 'General';
        $concept = $input['concept'] ?? '';
        $type = $input['type'] ?? 'Technical';
        $institution = getInstitution() ?: 'GMU';

        try {
            $db->exec("ALTER TABLE mock_ai_interview_sessions ADD COLUMN IF NOT EXISTS company_name VARCHAR(255) DEFAULT 'General'");
        } catch (\Exception $e) {
        }

        $sql = "INSERT INTO mock_ai_interview_sessions (student_id, role_name, status, institution, concept, company_name) VALUES (?, ?, 'active', ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentIdForDb, $role, $institution, $concept, $company]);
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
        $welcomeMsg = "Hi, welcome to your Mock AI Interview for the **$role** position. We will start with the **$type** round. Please let me know when you are ready to begin.";
        $aiMsg = ['role' => 'assistant', 'content' => $welcomeMsg];
        $history = [$aiMsg];

        // Update history in DB
        $sqlUpdate = "UPDATE mock_ai_interview_sessions SET conversation_history = ? WHERE id = ?";
        $db->prepare($sqlUpdate)->execute([json_encode($history), $sessionId]);

        $step = buildOrchestratedStep($type, 1, 10, $welcomeMsg, $aiService);

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'message' => $welcomeMsg,
            'step' => $step
        ]);
        break;

    case 'chat':
        $sessionId = $input['session_id'] ?? 0;

        // Fetch session
        $sql = "SELECT * FROM mock_ai_interview_sessions WHERE id = ? AND student_id = ? AND status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$sessionId, $studentIdForDb]);
        $session = $stmt->fetch();

        if (!$session) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Session not found or already closed']);
            exit;
        }

        $role = (string) ($session['role_name'] ?? '');
        $concept = (string) ($session['concept'] ?? '');
        $type = (string) ($input['type'] ?? 'Technical');
        $history = sanitizeHistory(json_decode($session['conversation_history'], true));

        // Ensure $userMessage is always a plain string (never an array from json_decode)
        $userMessage = $input['message'] ?? '';
        if (!is_string($userMessage)) {
            $userMessage = is_array($userMessage) ? json_encode($userMessage) : (string) $userMessage;
        }

        $history[] = ['role' => 'user', 'content' => $userMessage];

        // Dynamically detect and update the interview round type (Aptitude, Technical, HR)
        $msgLower = strtolower(trim($userMessage));
        if ($msgLower === 'aptitude' || stripos($msgLower, 'switch to aptitude') !== false || stripos($msgLower, 'start aptitude') !== false || stripos($msgLower, 'select aptitude') !== false || $msgLower === '1' || $msgLower === 'option 1') {
            $type = 'Aptitude';
        } elseif ($msgLower === 'technical' || stripos($msgLower, 'switch to technical') !== false || stripos($msgLower, 'start technical') !== false || stripos($msgLower, 'select technical') !== false || $msgLower === '2' || $msgLower === 'option 2') {
            $type = 'Technical';
        } elseif ($msgLower === 'hr' || stripos($msgLower, 'switch to hr') !== false || stripos($msgLower, 'start hr') !== false || stripos($msgLower, 'select hr') !== false || $msgLower === '3' || $msgLower === 'option 3') {
            $type = 'HR';
        } else {
            // Scan history backwards to find the active round type
            for ($i = count($history) - 2; $i >= 0; $i--) {
                $hMsg = $history[$i];
                if ($hMsg['role'] === 'assistant') {
                    $hContentLower = strtolower($hMsg['content']);
                    // If assistant sent a message containing MCQ options, we are in Aptitude mode
                    if (preg_match('/\b[A-D][\.\)]\s*/i', $hMsg['content'])) {
                        $type = 'Aptitude';
                        break;
                    }
                    if (stripos($hContentLower, 'starting with the aptitude') !== false || stripos($hContentLower, 'start with aptitude') !== false || stripos($hContentLower, 'welcome to the aptitude') !== false) {
                        $type = 'Aptitude';
                        break;
                    }
                    if (stripos($hContentLower, 'starting with the technical') !== false || stripos($hContentLower, 'start with technical') !== false || stripos($hContentLower, 'welcome to the technical') !== false) {
                        $type = 'Technical';
                        break;
                    }
                    if (stripos($hContentLower, 'starting with the hr') !== false || stripos($hContentLower, 'start with hr') !== false || stripos($hContentLower, 'welcome to the hr') !== false) {
                        $type = 'HR';
                        break;
                    }
                } elseif ($hMsg['role'] === 'user') {
                    $uContentLower = strtolower(trim($hMsg['content']));
                    if ($uContentLower === 'aptitude' || $uContentLower === 'switch to aptitude' || $uContentLower === 'logical') {
                        $type = 'Aptitude';
                        break;
                    }
                    if ($uContentLower === 'technical' || $uContentLower === 'switch to technical' || $uContentLower === 'coding') {
                        $type = 'Technical';
                        break;
                    }
                    if ($uContentLower === 'hr' || $uContentLower === 'switch to hr' || $uContentLower === 'behavioral') {
                        $type = 'HR';
                        break;
                    }
                }
            }
        }

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

        $company = (string) ($session['company_name'] ?? 'General');

        // --- Sliding Window: trim history to prevent token overflow ---
        // The system prompt is ~3000+ tokens.  Keep the first 2 messages
        // (welcome exchange) for context and the last 20 messages (10 Q&A
        // pairs) so the AI always sees the student's latest answer.
        $trimmedHistory = $history;
        $maxMessages = 22; // 2 anchor + 20 recent
        if (count($trimmedHistory) > $maxMessages) {
            $anchor = array_slice($trimmedHistory, 0, 2);      // first welcome exchange
            $recent = array_slice($trimmedHistory, -20);        // last 20 messages
            $trimmedHistory = array_merge($anchor, [['role' => 'system', 'content' => '[Earlier conversation trimmed for brevity]']], $recent);
        }

        session_write_close();
        $response = $aiService->getTechnicalInterviewResponse($role, $trimmedHistory, $profile, '', $type, $projects, $aptitudeQuestions, $concept, $company);

        if ($response['success']) {
            $aiContent = $response['content'];
            $history[] = ['role' => 'assistant', 'content' => $aiContent];

            $isEnd = (strpos($aiContent, '[END_INTERVIEW]') !== false);
            $status = $isEnd ? 'completed' : 'active';
            $completedAt = $isEnd ? date('Y-m-d H:i:s') : null;
            $reportContent = null;
            $overallScore = null;

            // Engagement Check for Auto-End
            $userMsgs = array_filter($history, fn($m) => $m['role'] === 'user' && !empty(trim($m['content'])));
            $hasEngagement = count($userMsgs) > 0;

            if ($isEnd) {
                if (!$hasEngagement) {
                    $overallScore = 0;
                    $reportContent = "You did not participate in the interview.";
                    $status = 'completed';
                } else {
                    session_write_close();
                    $reportRes = $aiService->generateTechnicalInterviewReport($role, $history, $type, $concept);
                    if ($reportRes['success']) {
                        $reportContent = $reportRes['content'];
                        $overallScore = $reportRes['overall_score'] ?? null;
                    }
                }

                if ($reportContent !== null || !$hasEngagement) {
                    // SAVE TO UNIFIED TABLE
                    try {
                        $profile = $studentModel->getByUserId($userId);
                        // We need the company name. We can try to infer it from the first prompt or pass it in chat.
                        // Since we don't have it in legacy schema, let's assume 'General' if not found.
                        // Better: The client can pass it in the final chat message or we can store it in a session.
                        $companyNameFromInput = $input['company'] ?? 'General';
                        $assessmentTypeFromInput = $input['type'] ?? 'Technical';
                        // Map to allowed ENUM values: 'Aptitude','Technical','HR','Skill Verification','Project Defense'
                        $enumType = 'Technical';
                        if (stripos($assessmentTypeFromInput, 'HR') !== false)
                            $enumType = 'HR';
                        if (stripos($assessmentTypeFromInput, 'Aptitude') !== false)
                            $enumType = 'Aptitude';

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
                            $enumType,
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

                    } catch (\Exception $e) {
                        error_log("Failed to log unified mock ai assessment: " . $e->getMessage());
                    }
                }
            }

            // Update DB to include overall_score
            $sqlH = "UPDATE mock_ai_interview_sessions SET conversation_history = ?, status = ?, completed_at = ?, report_content = ?, overall_score = ? WHERE id = ?";
            $db->prepare($sqlH)->execute([json_encode($history), $status, $completedAt, $reportContent, $overallScore, $sessionId]);

            $assistantMessages = array_filter($history, fn($m) => $m['role'] === 'assistant');
            $questionNum = count($assistantMessages);
            $totalQs = ($type === 'Aptitude') ? 25 : (($type === 'Technical') ? 10 : 8);

            $step = buildOrchestratedStep($type, $questionNum, $totalQs, $aiContent, $aiService);

            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => $aiContent,
                'is_end' => $isEnd,
                'session_id' => $sessionId,
                'step' => $step
            ]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'AI Response Failed. Please try sending your answer again.']);
        }
        break;

    case 'autosave':
        $sessionId = $input['session_id'] ?? 0;
        $checkpoint = $input['checkpoint'] ?? [];

        $_SESSION["lar_checkpoint_{$sessionId}"] = $checkpoint;

        try {
            $db->exec("ALTER TABLE mock_ai_interview_sessions ADD COLUMN IF NOT EXISTS checkpoint TEXT NULL");
            $sql = "UPDATE mock_ai_interview_sessions SET checkpoint = ? WHERE id = ?";
            $db->prepare($sql)->execute([json_encode($checkpoint), $sessionId]);
        } catch (\Exception $e) {
            error_log("LAR autosave db write bypassed: " . $e->getMessage());
        }

        echo json_encode(['success' => true]);
        exit;

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

        $history = sanitizeHistory(json_decode($session['conversation_history'], true));
        $role = $session['role_name'];

        // Use AI Service to evaluate code
        session_write_close();
        $evalRes = $aiService->evaluateCode($code, $language, "Technical task for role: $role");

        if ($evalRes['success']) {
            // evaluateCode() returns ['result' => <already-decoded array>]
            $evaluation = $evalRes['result'];

            // Ensure we have a valid evaluation array (fallback if AI returned null)
            if (!is_array($evaluation)) {
                $evaluation = ['passed' => false, 'score' => 0, 'feedback' => 'Evaluation could not be parsed.'];
            }

            // Log to history
            $history[] = ['role' => 'user', 'content' => "User ran code simulation ({$language}). Result: " . ($evaluation['passed'] ? 'PASSED' : 'FAILED') . " - Score: {$evaluation['score']}/10"];
            $history[] = ['role' => 'system', 'content' => "Code Evaluation: " . json_encode($evaluation)];

            $db->prepare("UPDATE mock_ai_interview_sessions SET conversation_history = ? WHERE id = ?")
                ->execute([json_encode($history), $sessionId]);

            ob_clean();
            echo json_encode([
                'success' => true,
                'evaluation' => $evaluation
            ]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Evaluation service failed. Please try again.']);
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
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Session not found or already closed']);
            exit;
        }

        $role = $session['role_name'];
        $concept = $session['concept'] ?? '';
        $history = sanitizeHistory(json_decode($session['conversation_history'], true));
        $completedAt = date('Y-m-d H:i:s');

        // Engagement Check for Manual End
        $userMsgs = array_filter($history, fn($m) => $m['role'] === 'user' && !empty(trim($m['content'])));
        if (count($userMsgs) === 0) {
            $reportContent = "You did not participate in the interview.";
            $overallScore = 0;
            $type = $input['type'] ?? 'Technical';
        } else {
            // Generate Report
            $reportContent = null;
            $overallScore = null;
            $type = $input['type'] ?? 'Technical';

            $reportRes = $aiService->generateTechnicalInterviewReport($role, $history, $type, $concept);
            if ($reportRes['success']) {
                $reportContent = $reportRes['content'];
                $overallScore = $reportRes['overall_score'] ?? null;
            } else {
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'Report generation failed. Please try ending the session again.'
                ]);
                exit;
            }
        }

        // SAVE TO UNIFIED TABLE
        if ($reportContent !== null) {
            try {
                $profile = $studentModel->getByUserId($userId);
                $companyNameFromInput = $input['company'] ?? 'General';
                $assessmentTypeFromInput = $input['type'] ?? 'Technical';
                // Map to allowed ENUM values
                $enumType = 'Technical';
                if (stripos($assessmentTypeFromInput, 'HR') !== false)
                    $enumType = 'HR';
                if (stripos($assessmentTypeFromInput, 'Aptitude') !== false)
                    $enumType = 'Aptitude';

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
                    $enumType,
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

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Session ended',
            'session_id' => $sessionId,
            'score' => $overallScore
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
        $currentUser = getUsername() ?? $studentIdForDb;
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

        $filename = "{$currentUser}_{$sem}_{$sessionId}.pdf";
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
