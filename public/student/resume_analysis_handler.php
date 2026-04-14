<?php
/**
 * Resume Analysis Handler (AJAX)
 * Offloads heavy PDF parsing and AI refinement to the background queue.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/QueueService.php';
require_once __DIR__ . '/../../src/Services/BasicPdfParser.php';

requireRole(ROLE_STUDENT);

$userId = getUserId();
$action = post('action');

if ($action === 'submit_analysis') {
    $resumeText = '';
    $targetRole = post('target_role') ?: 'Software Engineer';
    
    // 1. Text Extraction (Done here because it's required for caching check)
    if (!empty($_FILES['resume_pdf']['name'])) {
        $fileTmpPath = $_FILES['resume_pdf']['tmp_name'];
        $fileType = $_FILES['resume_pdf']['type'];
        
        if ($fileType !== 'application/pdf') {
            echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
            exit;
        }

        try {
            $parser = new BasicPdfParser();
            $resumeText = $parser->parseFile($fileTmpPath);
            if (strlen($resumeText) < 50) {
                echo json_encode(['success' => false, 'message' => 'Could not extract sufficient text from this PDF. It might be scanned or image-based.']);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error reading PDF: ' . $e->getMessage()]);
            exit;
        }
    } else {
        $resumeText = post('resume_text');
    }

    if (empty($resumeText)) {
        echo json_encode(['success' => false, 'message' => 'Please upload a PDF or paste your resume text.']);
        exit;
    }

    // 2. Check Cache First (Save AI cost and Job time)
    require_once __DIR__ . '/../../src/Models/Resume.php';
    $resumeModel = new Resume();
    $cached = $resumeModel->getCachedAnalysis($userId, $resumeText);
    
    if ($cached) {
        $cached['metadata']['is_cached'] = true;
        echo json_encode(['success' => true, 'result' => $cached]);
        exit;
    }

    // 3. Push to Queue
    // Method: analyzeResumeSequence(int $userId, string $resumeText, string $targetRole)
    $jobId = \App\Services\QueueService::pushJob('analyzeResumeSequence', [$userId, $resumeText, $targetRole], $userId);

    if ($jobId) {
        echo json_encode(['success' => true, 'job_id' => $jobId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to queue analysis. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;
