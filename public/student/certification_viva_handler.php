<?php
/**
 * Certification Viva Handler
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
            
            if (!$item || $item['category'] !== 'Certification') {
                echo json_encode(['success' => false, 'message' => 'Certification not found.']);
                exit;
            }

            session_write_close();
            $res = $aiService->generateCertificationQuestions($item['title'], $item['sub_title'] ?: 'Unknown Issuer');
            if ($res['success']) {
                $questions = is_array($res['questions']) ? $res['questions'] : json_decode($res['content'], true)['questions'];
                echo json_encode([
                    'success' => true, 
                    'questions' => $questions
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to generate questions.']);
            }
            break;

        case 'submit_viva':
            $portfolioId = $input['portfolio_id'] ?? 0;
            $history = $input['history'] ?? [];

            // Verify ownership
            $stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
            $stmt->execute([$portfolioId, $username]);
            $item = $stmt->fetch();
            
            if (!$item) {
                echo json_encode(['success' => false, 'message' => 'Certification not found.']);
                exit;
            }

            session_write_close();
            $res = $aiService->evaluateCertificationViva($item['title'], $item['sub_title'], json_encode($history));
            
            if ($res['success']) {
                $evaluation = json_decode($res['content'], true);
                $score = (int)$evaluation['score'];
                $isVerified = ($score >= 70);

                // Update database
                $sql = "UPDATE student_portfolio SET 
                        is_verified = ?, 
                        verification_score = ?, 
                        verification_date = CURRENT_TIMESTAMP,
                        verification_details = ? 
                        WHERE id = ?";
                $db = getDB();
                $db->prepare($sql)->execute([
                    $isVerified ? 1 : 0,
                    $score,
                    json_encode([
                        'transcript' => $history,
                        'feedback' => $evaluation['feedback']
                    ]),
                    $portfolioId
                ]);

                // Sync to Unified AI Assessments Table
                try {
                    require_once __DIR__ . '/../../src/Models/StudentProfile.php';
                    $studentModel = new StudentProfile();
                    $profile = $studentModel->getByUserId($userId);

                    $sqlUnified = "INSERT INTO unified_ai_assessments (
                        student_id, institution, student_name, usn, aadhar,
                        current_sem, branch, assessment_type,
                        assessment_title, score, total_marks,
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
                        'Certification Verification',
                        $item['title'], 
                        $score,
                        100,
                        $evaluation['feedback'],
                        json_encode([
                            'transcript' => $history,
                            'cert_title' => $item['title'],
                            'issuer' => $item['sub_title']
                        ]),
                        'completed'
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to sync certification verification to unified table: " . $e->getMessage());
                }

                echo json_encode([
                    'success' => true,
                    'score' => $score,
                    'feedback' => $evaluation['feedback']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'AI evaluation failed.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
