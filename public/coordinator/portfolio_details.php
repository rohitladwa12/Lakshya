<?php
/**
 * Department Coordinator - Student Portfolio Details (AJAX)
 * Returns Skills & Projects added by a student + verified status.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_DEPT_COORDINATOR);

header('Content-Type: application/json');

$usn = trim((string)($_POST['usn'] ?? $_GET['usn'] ?? ''));
$institution = trim((string)($_POST['institution'] ?? $_GET['institution'] ?? ''));

if ($usn === '' || !in_array($institution, [INSTITUTION_GMU, INSTITUTION_GMIT], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Authorize: coordinator can only view students of their department (with GMU/GMIT discipline mapping)
$allowedDisciplines = array_values(array_unique(getCoordinatorDisciplineFilters(getDepartment())));

try {
    $remote = getDB('gmu'); // can read both prefixed DBs
    if ($institution === INSTITUTION_GMU) {
        $stmt = $remote->prepare("SELECT discipline FROM " . DB_GMU_PREFIX . "ad_student_approved WHERE usn = ? ORDER BY academic_year DESC, sem DESC LIMIT 1");
        $stmt->execute([$usn]);
    } else {
        // GMIT
        $stmt = $remote->prepare("SELECT discipline FROM " . DB_GMIT_PREFIX . "ad_student_details WHERE usn = ? OR student_id = ? LIMIT 1");
        $stmt->execute([$usn, $usn]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $disc = trim((string)($row['discipline'] ?? ''));
    if ($disc === '' || !in_array($disc, $allowedDisciplines, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to authorize student.']);
    exit;
}

// Fetch portfolio items from local DB
try {
    $db = getDB();

    // Check if optional verification columns exist
    $stmtCol = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_portfolio' AND COLUMN_NAME = 'is_verified'");
    $stmtCol->execute();
    $hasIsVerified = ((int)$stmtCol->fetchColumn() > 0);

    // AI Reports from Placement Officer logic
    $officerModel = new PlacementOfficer();
    $aiData = $officerModel->getUnifiedAIReports(['usn' => $usn, 'institution' => $institution]);
    
    // Find physical PDF reports from various possible directories
    $findPdfs = function($u) {
        $pdfs = [];
        $searchPaths = [
            'HR' => ['uploads/reports/hr/'],
            'Technical' => ['uploads/reports/technical/'],
            'Mock AI' => ['uploads/reports/mock_ai/'],
            'Resume' => ['uploads/resumes/Student_Resumes/']
        ];
        
        foreach ($searchPaths as $type => $dirs) {
            foreach ($dirs as $dir) {
                $fullDir = __DIR__ . '/../' . $dir;
                if (is_dir($fullDir)) {
                    // Search for files starting with USN
                    $files = glob($fullDir . $u . '_*.pdf') ?: [];
                    foreach ($files as $f) {
                        $pdfs[] = [
                            'type' => $type, 
                            'path' => $dir . basename($f), 
                            'filename' => basename($f)
                        ];
                    }
                }
            }
        }
        return $pdfs;
    };
    $studentPdfs = $findPdfs($usn);

    // Enrich AI data with physical PDF links and track matched PDFs
    $enrichedAI = [];
    $matchedPdfPaths = [];
    
    foreach ($aiData as $rep) {
        // Filter out reports with invalid/zero scores
        $rawScore = $rep['score'] ?? 0;
        
        // Normalize strings before checking
        $checkScore = isset($rawScore) ? strtolower(trim((string)$rawScore)) : '';
        
        // Exclude specific invalid values
        if ($checkScore === 'nan' || $checkScore === 'nan%' || strpos($checkScore, 'overall') !== false || $checkScore === '') {
            continue;
        }

        // Parse numeric value
        if (strpos($checkScore, '%') !== false) {
             $numericScore = (float)str_replace('%', '', $checkScore);
        } else {
             $numericScore = (float)$checkScore;
        }

        if ($numericScore <= 0) {
            continue;
        }

        $type = strtolower($rep['assessment_type'] ?? '');
        $repPdfs = [];
        foreach ($studentPdfs as $pdf) {
            $isMatch = false;
            if ($pdf['type'] === 'HR' && (strpos($type, 'hr') !== false)) $isMatch = true;
            if ($pdf['type'] === 'Technical' && (strpos($type, 'technical') !== false)) $isMatch = true;
            if ($pdf['type'] === 'Mock AI' && (strpos($type, 'mock') !== false || strpos($type, 'technical') !== false || strpos($type, 'hr') !== false)) $isMatch = true;
            
            if ($isMatch) {
                $repPdfs[] = $pdf;
                $matchedPdfPaths[] = $pdf['path'];
            }
        }
        $rep['pdf_reports'] = $repPdfs;
        $enrichedAI[] = $rep;
    }

    // Add unmatched PDFs as standalone report entries
    foreach ($studentPdfs as $pdf) {
        if (!in_array($pdf['path'], $matchedPdfPaths)) {
            $isMock = (strpos(strtolower($pdf['type']), 'mock') !== false);
            $enrichedAI[] = [
                'company_name' => $isMock ? 'Mock AI Assessment' : $pdf['type'] . ' Report',
                'assessment_type' => $pdf['type'],
                'score' => 'N/A',
                'started_at' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/../' . $pdf['path'])),
                'pdf_reports' => [$pdf]
            ];
        }
    }

    $selectVerified = $hasIsVerified ? "is_verified" : "0 as is_verified";
    $stmt = $db->prepare("
        SELECT id, category, title, sub_title, description, link, {$selectVerified} as is_verified
        FROM student_portfolio
        WHERE student_id = ? AND institution = ?
          AND category IN ('Skill','Project','Certification')
        ORDER BY category ASC, created_at DESC, id DESC
    ");
    $stmt->execute([$usn, $institution]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $skills = [];
    $projects = [];
    $certifications = [];
    foreach ($items as $it) {
        $payload = [
            'id' => $it['id'],
            'category' => $it['category'],
            'title' => $it['title'],
            'sub_title' => $it['sub_title'],
            'description' => $it['description'],
            'link' => $it['link'],
            'is_verified' => (int)($it['is_verified'] ?? 0),
        ];
        if (($it['category'] ?? '') === 'Skill') $skills[] = $payload;
        elseif (($it['category'] ?? '') === 'Project') $projects[] = $payload;
        elseif (($it['category'] ?? '') === 'Certification') $certifications[] = $payload;
    }

    echo json_encode([
        'success' => true,
        'skills' => $skills,
        'projects' => $projects,
        'certifications' => $certifications,
        'ai_reports' => $enrichedAI,
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load portfolio.']);
    exit;
}

