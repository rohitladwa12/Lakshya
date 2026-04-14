<?php
/**
 * Officer - Placement Handler
 * Processes student placement and document upload
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require officer role
requireRole(ROLE_PLACEMENT_OFFICER);

if (isPost()) {
    $jobId = post('job_id');
    $studentId = post('student_id');
    $applicationId = post('application_id');
    $companyId = post('company_id');
    $companyName = post('company_name');
    $usn = post('usn');
    $institution = post('institution');
    $salary = post('salary_package');
    $placementDate = post('placement_date');

    if (!$jobId || !$studentId || !$applicationId) {
        Session::flash('error', 'Missing required placement data');
        redirect('jobs');
    }

    $db = getDB();
    $db->beginTransaction();

    try {
        $documentPath = null;

        // Handle Placement Document Upload
        if (isset($_FILES['placement_doc']) && $_FILES['placement_doc']['error'] == 0) {
            $uploadDir = DOCUMENT_UPLOAD_PATH . '/Placed_Students/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Naming Convention: USN_CompanyName.pdf
            $safeCompanyName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $companyName);
            $fileName = $usn . '_' . $safeCompanyName . '.pdf';
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['placement_doc']['tmp_name'], $targetPath)) {
                $documentPath = 'uploads/documents/Placed_Students/' . $fileName;
            } else {
                throw new Exception("Failed to upload placement document");
            }
        } else {
            throw new Exception("Placement document is required");
        }

        // 1. Record in placements table
        $placementModel = new Placement();
        $placementId = $placementModel->create([
            'job_id' => $jobId,
            'student_id' => $studentId,
            'company_id' => $companyId,
            'institution' => $institution,
            'salary_package' => $salary,
            'placement_date' => $placementDate,
            'document_path' => $documentPath,
            'status' => 'Placed'
        ]);

        if (!$placementId) {
            throw new Exception("Failed to record placement in database");
        }

        // 2. Update Application Status
        $applicationModel = new JobApplication();
        $applicationModel->update($applicationId, [
            'status' => 'Selected',
            'status_updated_at' => date('Y-m-d H:i:s'),
            'notes' => 'Student placed on ' . $placementDate
        ]);

        $db->commit();
        Session::flash('success', 'Student marked as Placed successfully');
        redirect('job_applicants?job_id=' . $jobId);
    }
} else {
    redirect('jobs');
}
