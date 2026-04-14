<?php
// MUST be first: prevents PHP warnings from polluting JSON output
ob_start();

/**
 * Project Viva Handler
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$userId = getUserId();
$username = getUsername();
$studentIdForDb = getStudentIdForAssessment();
$institution = $_SESSION['institution'] ?? 'GMU';

require_once __DIR__ . '/../../src/Services/AIService.php';
$aiService = new AIService();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'generate_viva':
            $portfolioId = $input['portfolio_id'] ?? 0;
            
            // Verify ownership
            $stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
            $stmt->execute([$portfolioId, $username]);
            $item = $stmt->fetch();
            
            if (!$item || $item['category'] !== 'Project') {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            session_write_close();
            require_once ROOT_PATH . '/src/Services/QueueService.php';
            $jobId = \App\Services\QueueService::pushJob('generateProjectViva', [$item['title'], $item['description']], $userId);
            
            ob_clean(); echo json_encode([
                'success' => true, 
                'job_id' => $jobId,
                'message' => 'Generating questions...'
            ]);
            exit;
        case 'submit_viva':
            $portfolioId = $input['portfolio_id'] ?? 0;
            $history = $input['history'] ?? [];

            // Verify ownership
            $stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
            $stmt->execute([$portfolioId, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            session_write_close();
            require_once ROOT_PATH . '/src/Services/QueueService.php';
            $jobId = \App\Services\QueueService::pushJob('evaluateProjectViva', [$item['title'], $history], $userId);
            
            ob_clean(); echo json_encode([
                'success' => true, 
                'job_id' => $jobId,
                'message' => 'Evaluating responses...'
            ]);
            exit;
        case 'save_viva_result':
            $portfolioId = $input['portfolio_id'] ?? 0;
            $score = $input['score'] ?? 0;
            $feedback = $input['feedback'] ?? '';
            $history = $input['history'] ?? [];

            // 1. Verify ownership
            $stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
            $stmt->execute([$portfolioId, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                ob_clean(); echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            // 2. Update student_portfolio
            $isVerified = ($score >= 70) ? 1 : 0;
            $sql = "UPDATE student_portfolio SET 
                    is_verified = ?, 
                    verification_score = ?, 
                    verification_date = CURRENT_TIMESTAMP,
                    verification_details = ? 
                    WHERE id = ?";
            getDB()->prepare($sql)->execute([
                $isVerified,
                $score,
                json_encode(['feedback' => $feedback, 'transcript' => $history]),
                $portfolioId
            ]);

            // 3. Sync to unified_ai_assessments
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

                getDB()->prepare($sqlUnified)->execute([
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
                        'portfolio_id' => $portfolioId
                    ]),
                    'completed'
                ]);
            } catch (Exception $e) {
                error_log("Failed to sync project viva to unified table: " . $e->getMessage());
            }

            ob_clean(); echo json_encode(['success' => true, 'message' => 'Result saved successfully.']);
            exit;
        default:
            ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    ob_clean(); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
