<?php
/**
 * Resume Analysis Handler (AJAX)
 * Offloads heavy PDF parsing and AI refinement to the background queue.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Services/QueueService.php';
require_once __DIR__ . '/../../src/Services/BasicPdfParser.php';
require_once __DIR__ . '/../../src/Models/Resume.php';

// Suppress non-fatal warnings so they don't corrupt JSON output
error_reporting(E_ERROR | E_PARSE);

requireRole(ROLE_STUDENT);

// Always return JSON
header('Content-Type: application/json');

$userId = getUserId();
logMessage("DEBUG: resume_analysis_handler.php - userId: " . print_r($userId, true) . " (Type: " . gettype($userId) . ")");
$action = post('action');
logMessage("DEBUG: resume_analysis_handler.php - action: " . print_r($action, true));

if ($action !== 'submit_analysis') {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

try {
    $resumeText     = '';
    $jobDescription = trim(post('job_description') ?? '');
    $targetRole     = trim(post('target_role') ?? '') ?: 'Software Engineer';
    $useSystemResume = (post('use_system_resume') === '1');

    // ──────────────────────────────────────────
    // STEP 1: Extract resume text
    // ──────────────────────────────────────────
    if ($useSystemResume) {
        $resumeModel = new Resume();
        $resumeData  = $resumeModel->getByStudentId($userId);

        if (!$resumeData) {
            echo json_encode(['success' => false, 'message' => 'No built resume found. Please build your resume first.']);
            exit;
        }

        // Safely flatten resume data to plain text for the AI
        $parts = [];
        $parts[] = "Name: " . ($resumeData['full_name'] ?? '');
        $parts[] = "Email: " . ($resumeData['email'] ?? '');

        // Skills – stored as {"technical":[...],"soft":[...]} or just [...]
        $rawSkills = $resumeData['skills'] ?? [];
        $allSkills = [];
        if (is_array($rawSkills)) {
            foreach ($rawSkills as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $item) {
                        $allSkills[] = is_array($item) ? implode(' ', array_map('strval', $item)) : (string)$item;
                    }
                } else {
                    $allSkills[] = (string)$val;
                }
            }
        }
        $parts[] = "Skills: " . implode(', ', $allSkills);

        // Education
        $edu = $resumeData['education'] ?? [];
        if (is_array($edu) && !empty($edu)) {
            $parts[] = "Education:";
            foreach ($edu as $e) {
                if (!is_array($e)) continue;
                $parts[] = "  - " . ($e['degree'] ?? '') . " from " . ($e['institution'] ?? '') . " (" . ($e['year'] ?? '') . ")";
            }
        }

        // Experience
        $exp = $resumeData['experience'] ?? [];
        if (is_array($exp) && !empty($exp)) {
            $parts[] = "Experience:";
            foreach ($exp as $job) {
                if (!is_array($job)) continue;
                $parts[] = "  - " . ($job['role'] ?? '') . " at " . ($job['company'] ?? '') . " (" . ($job['duration'] ?? '') . ")";
                $bullets = $job['bullets'] ?? $job['responsibilities'] ?? [];
                if (is_array($bullets)) {
                    foreach ($bullets as $b) {
                        $parts[] = "    * " . (is_array($b) ? implode(' ', array_map('strval', $b)) : (string)$b);
                    }
                }
            }
        }

        // Projects
        $projects = $resumeData['projects'] ?? [];
        if (is_array($projects) && !empty($projects)) {
            $parts[] = "Projects:";
            foreach ($projects as $proj) {
                if (!is_array($proj)) continue;
                $title = $proj['title'] ?? $proj['name'] ?? '';
                $desc  = $proj['description'] ?? '';
                $tech  = $proj['technologies'] ?? $proj['tech'] ?? '';
                if (is_array($tech)) $tech = implode(', ', array_map('strval', $tech));
                $parts[] = "  - $title: $desc" . ($tech ? " [Tech: $tech]" : "");
            }
        }

        // Certifications
        $certs = $resumeData['certifications'] ?? [];
        if (is_array($certs) && !empty($certs)) {
            $parts[] = "Certifications:";
            foreach ($certs as $c) {
                $parts[] = "  - " . (is_array($c) ? ($c['name'] ?? implode(' ', array_map('strval', $c))) : (string)$c);
            }
        }

        $resumeText = implode("\n", $parts);

    } elseif (!empty($_FILES['resume_pdf']['name'])) {
        $fileTmpPath = $_FILES['resume_pdf']['tmp_name'];
        $fileType    = mime_content_type($fileTmpPath) ?: $_FILES['resume_pdf']['type'];

        if (strpos($fileType, 'pdf') === false) {
            echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
            exit;
        }

        $parser = new BasicPdfParser();
        $resumeText = $parser->parseFile($fileTmpPath);

        if (strlen($resumeText) < 50) {
            echo json_encode(['success' => false, 'message' => 'Could not extract text from this PDF. It may be scanned/image-based.']);
            exit;
        }

    } else {
        $resumeText = trim(post('resume_text') ?? '');
    }

    if (empty($resumeText)) {
        echo json_encode(['success' => false, 'message' => 'No resume content found. Please upload a PDF or ensure your built resume has content.']);
        exit;
    }

    // ──────────────────────────────────────────
    // STEP 2: Check Cache
    // ──────────────────────────────────────────
    $resumeModel = new Resume();
    $cacheKey    = $resumeText . ($jobDescription ?: $targetRole);
    $cached      = $resumeModel->getCachedAnalysis($userId, $cacheKey);

    if ($cached) {
        if (isset($cached['metadata'])) $cached['metadata']['is_cached'] = true;
        echo json_encode(['success' => true, 'result' => $cached]);
        exit;
    }

    // ──────────────────────────────────────────
    // STEP 3: Push to Queue
    // ──────────────────────────────────────────
    $method = !empty($jobDescription) ? 'analyzeResumeWithJD' : 'analyzeResumeSequence';
    $args   = !empty($jobDescription) ? [$userId, $resumeText, $jobDescription] : [$userId, $resumeText, $targetRole];

    $jobId = \App\Services\QueueService::pushJob($method, $args, $userId);

    if ($jobId) {
        echo json_encode(['success' => true, 'job_id' => $jobId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to queue analysis. Please try again.']);
    }
    exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
