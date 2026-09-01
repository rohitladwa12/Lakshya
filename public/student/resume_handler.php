<?php
/**
 * Resume Handler
 * Handles resume save, builder operations, and PDF generation requests
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

// Determine action from GET, POST, or JSON body
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? '';

// If this is a resume builder action, delegate seamlessly to resume_builder_handler.php
$builderActions = ['save_resume', 'load_resume', 'fetch_portfolio', 'upload_external_pdf', 'analyze_ats'];
if (in_array($action, $builderActions) || isset($_POST['resume_data']) || isset($_FILES['resume_pdf'])) {
    require __DIR__ . '/resume_builder_handler.php';
    exit;
}

$userId = getUserId();

// Load models and services
require_once __DIR__ . '/../../src/Models/Resume.php';
require_once __DIR__ . '/../../src/Services/ResumePDFGenerator.php';

header('Content-Type: application/json');

try {
    $resumeModel = new Resume();
    
    switch ($action) {
        case 'save':
            // Save resume data (from JSON body or POST form)
            $resumeData = $input['resumeData'] ?? $_POST['resumeData'] ?? [];
            if (is_string($resumeData)) {
                $resumeData = json_decode($resumeData, true) ?: [];
            }
            
            if (empty($resumeData['full_name']) || empty($resumeData['email'])) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Name and email are required',
                    'error'   => 'Name and email are required'
                ]);
                exit;
            }
            
            $success = $resumeModel->saveResume($userId, $resumeData);
            
            if ($success) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Resume saved successfully',
                    'pdf_url' => ''
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to save resume',
                    'error'   => 'Failed to save resume'
                ]);
            }
            break;
            
        case 'generate_pdf':
            $resumeData = $input['resumeData'] ?? $_POST['resumeData'] ?? [];
            if (is_string($resumeData)) {
                $resumeData = json_decode($resumeData, true) ?: [];
            }
            
            if (empty($resumeData['full_name'])) {
                echo json_encode(['success' => false, 'message' => 'Resume data is incomplete', 'error' => 'Resume data is incomplete']);
                exit;
            }
            
            $html = ResumePDFGenerator::generateHTML($resumeData);
            header('Content-Type: text/html');
            echo $html;
            echo '<script>window.print();</script>';
            exit;
            
        case 'load':
            $resume = $resumeModel->getByStudentId($userId);
            
            if ($resume) {
                echo json_encode(['success' => true, 'resume' => $resume]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No resume found', 'error' => 'No resume found']);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action: ' . ($action ?: 'none specified'),
                'error'   => 'Invalid action: ' . ($action ?: 'none specified')
            ]);
    }
    
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'error' => $e->getMessage()]);
}
