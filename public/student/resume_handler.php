<?php
/**
 * Resume Handler
 * Handles resume save and PDF generation requests
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();

// Load models and services
require_once __DIR__ . '/../../src/Models/Resume.php';
require_once __DIR__ . '/../../src/Services/ResumePDFGenerator.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

header('Content-Type: application/json');

try {
    $resumeModel = new Resume();
    
    switch ($action) {
        case 'save':
            // Save resume data
            $resumeData = $input['resumeData'] ?? [];
            
            if (empty($resumeData['full_name']) || empty($resumeData['email'])) {
                echo json_encode(['success' => false, 'message' => 'Name and email are required']);
                exit;
            }
            
            $success = $resumeModel->saveResume($userId, $resumeData);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Resume saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save resume']);
            }
            break;
            
        case 'generate_pdf':
            // Generate PDF
            $resumeData = $input['resumeData'] ?? [];
            
            if (empty($resumeData['full_name'])) {
                echo json_encode(['success' => false, 'message' => 'Resume data is incomplete']);
                exit;
            }
            
            // Generate HTML
            $html = ResumePDFGenerator::generateHTML($resumeData);
            
            // For now, we'll use browser's print-to-PDF functionality
            // Return HTML that can be opened in a new window and printed
            header('Content-Type: text/html');
            echo $html;
            echo '<script>window.print();</script>';
            exit;
            
        case 'load':
            // Load existing resume
            $resume = $resumeModel->getByStudentId($userId);
            
            if ($resume) {
                echo json_encode(['success' => true, 'resume' => $resume]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No resume found']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
