<?php
/**
 * Job Handler - Processes Job & Company CRUD for Officers
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

// Handle JSON requests (for AJAX actions)
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_POST = array_merge($_POST, $input);
}

$action = post('action');
$jobModel = new JobPosting();
$companyModel = new Company();

switch ($action) {
    case 'create':
    case 'update':
        $db = getDB();
        $db->beginTransaction();

        try {
            // 1. Handle Company Details
            $companyId = post('company_id');
            $companyData = [
                'name' => post('company_name'),
                'sector' => post('company_sector'),
                'industry' => post('company_industry') ?: post('company_sector'),
                'website' => post('company_website') ?: null,
                'district' => post('company_district') ?: null,
                'state' => post('company_state') ?: null,
                'country' => post('company_country') ?: 'India',
                'description' => post('company_description') ?: null
            ];

            // File Upload: Logo
            if (!empty($_FILES['company_logo']['name'])) {
                $ext = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
                $logoName = 'logo_' . time() . '.' . $ext;
                $uploadDir = PHOTO_UPLOAD_PATH . '/logos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadDir . $logoName)) {
                    $companyData['logo_url'] = $logoName;
                }
            }

            // File Upload: Document
            if (!empty($_FILES['company_doc']['name'])) {
                $ext = pathinfo($_FILES['company_doc']['name'], PATHINFO_EXTENSION);
                $docName = 'doc_' . time() . '.' . $ext;
                $uploadDir = DOCUMENT_UPLOAD_PATH . '/docs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                if (move_uploaded_file($_FILES['company_doc']['tmp_name'], $uploadDir . $docName)) {
                    $companyData['document_url'] = $docName;
                }
            }

            if (empty($companyId)) {
                // Check if company name already exists, if so use that
                $existing = $db->prepare("SELECT id FROM companies WHERE name = ?");
                $existing->execute([$companyData['name']]);
                $found = $existing->fetchColumn();
                
                if ($found) {
                    $companyId = $found;
                    $companyModel->update($companyId, $companyData);
                } else {
                    // Create new company
                    $companyId = $companyModel->create($companyData);
                }
            } else {
                // Update existing company
                $companyModel->update($companyId, $companyData);
            }

            // 2. Handle SPOCs
            $spocNames = $_POST['spoc_name'] ?? [];
            $spocDesignations = $_POST['spoc_designation'] ?? [];
            $spocEmails = $_POST['spoc_email'] ?? [];
            $spocPhones = $_POST['spoc_phone'] ?? [];

            $spocs = [];
            for ($i = 0; $i < count($spocNames); $i++) {
                if (empty($spocNames[$i])) continue;
                $spocs[] = [
                    'name' => $spocNames[$i],
                    'designation' => $spocDesignations[$i] ?? '',
                    'email' => $spocEmails[$i] ?? '',
                    'phone' => $spocPhones[$i] ?? ''
                ];
            }
            $companyModel->updateSpocs($companyId, $spocs);

            // 3. Handle Job Details
            $jobData = [
                'company_id' => $companyId,
                'title' => post('title'),
                'description' => post('description'),
                'requirements' => post('requirements') ?: null,
                'responsibilities' => post('responsibilities') ?: null,
                'location' => post('location'),
                'job_type' => post('job_type'),
                'work_mode' => post('work_mode'),
                'salary_min' => post('salary_min') !== '' ? post('salary_min') : null,
                'salary_max' => post('salary_max') !== '' ? post('salary_max') : null,
                'currency' => post('currency') ?: 'INR',
                'min_cgpa' => post('min_cgpa') !== '' ? post('min_cgpa') : 0.00,
                'eligible_courses' => json_encode($_POST['eligible_courses'] ?? []),
                'eligible_branches' => json_encode($_POST['eligible_branches'] ?? []),
                'eligible_branches' => json_encode($_POST['eligible_branches'] ?? []),
                'eligible_years' => json_encode($_POST['eligible_years'] ?? []),
                'custom_fields' => (function() {
                    $labels = $_POST['custom_q_text'] ?? [];
                    $types = $_POST['custom_q_type'] ?? [];
                    $required = $_POST['custom_q_required'] ?? [];
                    
                    $fields = [];
                    for ($i = 0; $i < count($labels); $i++) {
                        if (trim($labels[$i]) === '') continue;
                        $fields[] = [
                            'label' => $labels[$i],
                            'type' => $types[$i] ?? 'text',
                            'required' => !empty($required[$i]) // '1' becomes true
                        ];
                    }
                    return json_encode($fields);
                })(),
                'application_deadline' => post('application_deadline'),
                'status' => 'Active',
                'posted_by' => getUserId()
            ];

            if ($action === 'create') {
                // Check for duplicate job entry
                $dupCheck = $db->prepare("SELECT id FROM job_postings WHERE company_id = ? AND title = ? AND status = 'Active'");
                $dupCheck->execute([$companyId, $jobData['title']]);
                if ($dupCheck->fetchColumn()) {
                    throw new Exception("A job with this title already exists for this company and is currently active.");
                }

                $jobData['posted_date'] = date('Y-m-d');
                $id = $jobModel->create($jobData);
                
                $db->commit();
                
                Session::flash('success', 'Job and Company details saved successfully. You can now see applicants below.');
                redirect('job_applicants?job_id=' . $id);
            } else {
                $jobId = post('job_id');
                $jobModel->update($jobId, $jobData);
                
                $db->commit();
                
                Session::flash('success', 'Job and Company details updated successfully');
                redirect('job_applicants?job_id=' . $jobId);
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Job Handler Error: " . $e->getMessage());
            Session::flash('error', 'Error: ' . $e->getMessage());
        }

        redirect('jobs');
        break;

    case 'close':
        $jobId = post('job_id');
        if ($jobModel->closeJob($jobId)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to close job']);
        }
        break;

    default:
        redirect('jobs');
        break;
}
