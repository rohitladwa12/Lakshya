<?php
/**
 * Student - Job Details
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$jobId = get('id');
if (!$jobId && get('code')) {
    $jobId = decryptJobId(get('code'));
}

if (!$jobId) {
    redirect('jobs.php');
}

$jobModel = new JobPosting();
$applicationModel = new JobApplication();

$job = $jobModel->getWithFullDetails($jobId);

// Parse Custom Fields
$customFields = [];
if (!empty($job['custom_fields'])) {
    $customFields = json_decode($job['custom_fields'], true) ?: [];
}

if (!$job) {
    redirect('jobs.php');
}

$hasApplied = $applicationModel->hasApplied($jobId, $userId);
$isEnded = ($job['status'] === 'Closed' || strtotime($job['application_deadline']) < time());

// 1. Check student academic profile completion
$profileModel = new StudentProfile();
$institution = $_SESSION['institution'] ?? '';
$hasAcademicProfile = $profileModel->hasCompletedAcademicProfile($userId, $institution);

if (!$hasAcademicProfile) {
    $_SESSION['redirect_after_sgpa'] = $_SERVER['REQUEST_URI'];
    Session::flash('error', 'Please update your Academic Profile (Semester & SGPA) before viewing or applying for job opportunities.');
    redirect('sgpa_entry.php');
}

// 2. Check student resume completion
require_once __DIR__ . '/../../src/Models/Resume.php';
$resumeModel = new Resume();
$currentUsn = $_SESSION['username'] ?? getUsername();
$hasResume = $resumeModel->hasResume([$currentUsn, $userId]);

if (!$hasResume) {
    $_SESSION['redirect_after_resume'] = $_SERVER['REQUEST_URI'];
    Session::flash('error', 'Please create and save your resume in the Resume Builder before viewing or applying for job opportunities.');
    redirect('resume_builder.php');
}

// Check student eligibility
$eligibilityCheck = $profileModel->isEligibleStrict($userId, $job['min_cgpa'], $job);
$isEligible = $eligibilityCheck['eligible'];
$ineligibilityReasons = $eligibilityCheck['reasons'];

// Handle application submission
$message = '';
$error = '';

if (isPost() && isset($_POST['apply'])) {
    if ($isEnded) {
        $error = "This job is no longer accepting applications.";
    } elseif (!$hasAcademicProfile) {
        $error = "Please update your Academic Profile (Semester & SGPA) before applying.";
    } elseif (!$hasResume) {
        $error = "Please build your resume in the Resume Builder before applying.";
    } elseif (!$isEligible) {
        $error = "You are not eligible to apply for this job. Reason: " . implode(', ', $ineligibilityReasons);
    } elseif ($hasApplied) {
        $error = "You have already applied for this job.";
    } else {
        // Process Custom Responses
        $customResponses = [];
        if (!empty($customFields)) {
            foreach ($customFields as $i => $field) {
                $response = [
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'value' => null
                ];

                if ($field['type'] === 'file') {
                    // Handle File Upload
                    $fileKey = 'custom_file_' . $i;
                    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
                        $uploadDir = RESUME_UPLOAD_PATH . '/Custom_Uploads/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                        
                        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
                            $fileName = $userId . '_' . time() . '_' . $i . '.' . $ext;
                            
                            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $fileName)) {
                                $response['value'] = 'uploads/resumes/Custom_Uploads/' . $fileName;
                            }
                        } else {
                            $error = "Invalid file type for custom file upload. Allowed: PDF, DOC, DOCX, JPG, PNG.";
                            break;
                        }
                    }
                } else {
                    // Handle Text/Number/Select
                    $inputKey = 'custom_response_' . $i;
                    if (isset($_POST[$inputKey])) {
                        $response['value'] = $_POST[$inputKey];
                    }
                }
                $customResponses[] = $response;
            }
        }

        $isExternalApply = (($job['application_mode'] ?? 'Internal') === 'External' && !empty($job['external_url']));
        if (empty($error)) {
            try {
                $applyData = [
                    'cover_letter' => post('cover_letter') ?: ($isExternalApply ? 'Applied via External Company Portal' : ''),
                    'custom_responses' => json_encode($customResponses),
                    'notes' => $isExternalApply ? ('External: ' . $job['external_url']) : null
                ];
                $result = $applicationModel->apply($jobId, $userId, $applyData);
                
                if ($result['success']) {
                    $hasApplied = true;
                    if ($isExternalApply) {
                        $message = "Your application was registered on Lakshya! Please complete the compulsory company website application.";
                        $showExternalModal = true;
                        $externalRedirectUrl = $job['external_url'];
                    } else {
                        $message = $result['message'];
                    }
                } else {
                    $error = $result['message'];
                }
            } catch (\Throwable $e) {
                error_log("Job application failed: " . $e->getMessage());
                $error = "Failed to submit application: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #800000; --brand-dark: #5b1f1f;
            --brand-grad: linear-gradient(135deg,#800000 0%,#a52a2a 100%);
            --brand-light: #fff5f5;
            --green: #059669; --green-light: #ecfdf5;
            --amber: #d97706; --amber-light: #fffbeb;
            --blue: #2563eb; --blue-light: #eff6ff;
            --text-dark: #0f172a; --text-mid: #475569; --text-muted: #94a3b8;
            --border: #e2e8f0; --surface: #fff; --bg: #f1f5f9;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,.08);
            --radius: 16px;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text-dark);padding-top:80px;min-height:100vh;}

        .page-wrap{max-width:1200px;margin:0 auto;padding:32px 24px 60px;}

        /* Back link */
        .back-link{display:inline-flex;align-items:center;gap:8px;color:var(--text-muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:24px;transition:color .2s;}
        .back-link:hover{color:var(--brand);}

        /* Alerts */
        .alert{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-weight:500;font-size:14px;display:flex;align-items:center;gap:10px;}
        .alert-success{background:var(--green-light);color:#065f46;border:1px solid #a7f3d0;}
        .alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}

        /* Layout */
        .layout{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;}
        @media(max-width:900px){.layout{grid-template-columns:1fr;}}

        /* Cards */
        .card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow-sm);margin-bottom:20px;overflow:hidden;}
        .card-pad{padding:24px;}
        .section-title{font-size:14px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
        .section-title::before{content:'';display:block;width:3px;height:14px;background:var(--brand-grad);border-radius:2px;flex-shrink:0;}

        /* Hero card */
        .hero-card{background:var(--brand-grad);border-radius:var(--radius);padding:28px;margin-bottom:20px;position:relative;overflow:hidden;}
        .hero-card::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/svg%3E") repeat;}
        .hero-inner{position:relative;display:flex;align-items:flex-start;gap:18px;}
        .hero-avatar{flex-shrink:0;width:64px;height:64px;border-radius:14px;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#fff;letter-spacing:-1px;}
        .hero-title{font-size:24px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:6px;}
        .hero-company{font-size:14px;color:rgba(255,255,255,.8);font-weight:500;display:flex;align-items:center;gap:6px;}
        .hero-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;}
        .hero-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:50px;font-size:12px;font-weight:600;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);}

        /* Content text */
        .content-text{font-size:14px;color:var(--text-mid);line-height:1.75;white-space:pre-line;}

        /* Skills */
        .skills-wrap{display:flex;flex-wrap:wrap;gap:8px;}
        .skill-tag{padding:5px 12px;border-radius:8px;font-size:13px;font-weight:500;background:var(--bg);color:var(--text-dark);border:1px solid var(--border);}
        .skill-tag.mandatory{background:var(--brand-light);color:var(--brand);border-color:rgba(128,0,0,.15);}

        /* SPOC */
        .spoc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;}
        .spoc-card{background:var(--bg);padding:14px;border-radius:12px;border:1px solid var(--border);}
        .spoc-name{font-weight:700;font-size:14px;color:var(--text-dark);margin-bottom:2px;}
        .spoc-role{font-size:12px;color:var(--text-muted);margin-bottom:8px;}
        .spoc-row{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-mid);margin-top:3px;}
        .spoc-row i{width:14px;text-align:center;color:var(--text-muted);}

        /* Sidebar meta */
        .meta-list{display:flex;flex-direction:column;gap:0;}
        .meta-item{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);}
        .meta-item:last-child{border-bottom:none;padding-bottom:0;}
        .meta-icon{width:38px;height:38px;border-radius:10px;background:var(--brand-light);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
        .meta-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
        .meta-value{font-size:14px;font-weight:700;color:var(--text-dark);margin-top:2px;}

        /* Sticky apply sidebar */
        .apply-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow-md);padding:24px;position:sticky;top:90px;}
        .apply-title{font-size:16px;font-weight:700;color:var(--text-dark);margin-bottom:16px;}

        /* Eligibility states */
        .elig-box{border-radius:12px;padding:16px;margin-bottom:18px;font-size:13px;line-height:1.6;}
        .elig-box.not{background:var(--amber-light);border:1px solid #fde68a;color:#92400e;}
        .elig-box.ok{background:var(--green-light);border:1px solid #a7f3d0;color:#065f46;}
        .elig-box-title{font-weight:700;font-size:14px;display:flex;align-items:center;gap:7px;margin-bottom:8px;}
        .elig-box ul{padding-left:16px;margin:0;}
        .elig-box li{margin-bottom:3px;}

        /* Form */
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:7px;}
        .form-control{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:14px;color:var(--text-dark);transition:border-color .2s;background:#fff;}
        .form-control:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(128,0,0,.06);}
        textarea.form-control{resize:vertical;min-height:110px;}

        /* Buttons */
        .btn-submit{width:100%;padding:13px;background:var(--brand-grad);color:#fff;border:none;border-radius:12px;font-weight:700;font-size:15px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;}
        .btn-submit:hover{box-shadow:0 6px 20px rgba(128,0,0,.3);transform:translateY(-1px);}
        .btn-applied-state{width:100%;padding:13px;background:var(--green-light);color:var(--green);border:1px solid #a7f3d0;border-radius:12px;font-weight:700;font-size:14px;text-align:center;cursor:default;}
        .btn-builder{display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;background:var(--brand-grad);color:#fff;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px;transition:all .2s;}
        .btn-builder:hover{box-shadow:0 6px 20px rgba(128,0,0,.3);}

        .resume-ready-box{background:var(--green-light);border:1px solid #a7f3d0;border-radius:12px;padding:14px;margin-bottom:16px;font-size:13px;color:#065f46;}
        .resume-ready-box strong{display:block;margin-bottom:4px;font-size:14px;}
        .resume-ready-box a{color:var(--brand);font-weight:600;}

        .divider{border:none;border-top:1px dashed var(--border);margin:16px 0;}
        .custom-q-title{font-size:13px;font-weight:700;color:var(--text-dark);margin-bottom:12px;}

        /* External Application Warning Modal */
        .ext-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .ext-modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }
        .ext-modal-dialog {
            background: #ffffff;
            border-radius: 20px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transform: scale(0.92) translateY(12px);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            text-align: center;
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ext-modal-backdrop.active .ext-modal-dialog {
            transform: scale(1) translateY(0);
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.92) translateY(16px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .ext-modal-header {
            padding: 32px 28px 20px;
            background: linear-gradient(180deg, #fffbeb 0%, #ffffff 100%);
            border-bottom: 1px solid #fef3c7;
            position: relative;
        }
        .ext-modal-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: #fef3c7;
            border: 4px solid #fde68a;
            color: #d97706;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.2);
        }
        .ext-modal-badge {
            display: inline-block;
            background: #dc2626;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 4px 14px;
            border-radius: 50px;
            margin-bottom: 12px;
        }
        .ext-modal-title {
            font-size: 21px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .ext-modal-subtitle {
            font-size: 13.5px;
            color: #64748b;
            line-height: 1.5;
        }
        .ext-modal-body {
            padding: 22px 28px 16px;
            text-align: left;
        }
        .ext-modal-warning-card {
            background: #fef2f2;
            border: 1.5px solid #fecaca;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 18px;
        }
        .ext-modal-warning-card strong {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #991b1b;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .ext-modal-warning-card p {
            font-size: 13px;
            color: #7f1d1d;
            line-height: 1.65;
            margin: 0;
        }
        .ext-modal-job-summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .ext-modal-footer {
            padding: 10px 28px 28px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .ext-modal-btn-open {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: #ffffff !important;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease;
        }
        .ext-modal-btn-open:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
        }
        .ext-modal-btn-close {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            color: #475569;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .ext-modal-btn-close:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }
        
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="page-wrap">

        <a href="jobs.php" class="back-link"><i class="fas fa-arrow-left"></i> All Opportunities</a>

        <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Hero -->
        <div class="hero-card">
            <div class="hero-inner">
                <div class="hero-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($job['company_logo'])): ?>
                        <img src="<?php echo (strpos($job['company_logo'], 'http') === 0) ? htmlspecialchars($job['company_logo']) : APP_URL . '/uploads/company_images/' . htmlspecialchars($job['company_logo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($job['company_name'], 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="hero-title"><?php echo htmlspecialchars($job['title']); ?></div>
                    <div class="hero-company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?></div>
                    <div class="hero-chips">
                        <?php if (!empty($job['location'])): ?><span class="hero-chip"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($job['location']); ?></span><?php endif; ?>
                        <?php if (!empty($job['job_type'])): ?><span class="hero-chip"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($job['job_type']); ?></span><?php endif; ?>
                        <?php if (!empty($job['work_mode'])): ?><span class="hero-chip"><i class="fas fa-laptop-house"></i> <?php echo htmlspecialchars($job['work_mode']); ?></span><?php endif; ?>
                        <?php if (!empty($job['salary_min'])): ?><span class="hero-chip"><i class="fas fa-indian-rupee-sign"></i> ₹<?php echo number_format($job['salary_min']/100000,1); ?>L – ₹<?php echo number_format($job['salary_max']/100000,1); ?>L/yr</span><?php endif; ?>
                        <?php if (!empty($job['min_cgpa']) && $job['min_cgpa'] > 0): ?><span class="hero-chip"><i class="fas fa-graduation-cap"></i> Min SGPA <?php echo $job['min_cgpa']; ?>+</span><?php endif; ?>
                        <?php if (!empty($job['application_deadline'])): ?>
                            <span class="hero-chip" style="<?php echo ($isEnded) ? 'background:rgba(239,68,68,0.25);border-color:rgba(239,68,68,0.4);' : ''; ?>">
                                <i class="fas fa-clock"></i> Deadline: <?php echo date('d M Y, h:i A', strtotime($job['application_deadline'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="layout">
            <!-- LEFT -->
            <div>
                <?php if (!empty($job['description'])): ?>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-align-left"></i> Job Description</div>
                    <div class="content-text"><?php echo htmlspecialchars($job['description']); ?></div>
                </div></div><?php endif; ?>

                <?php if (!empty($job['requirements'])): ?>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-list-check"></i> Requirements</div>
                    <div class="content-text"><?php echo htmlspecialchars($job['requirements']); ?></div>
                </div></div><?php endif; ?>

                <?php if (!empty($job['responsibilities'])): ?>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-tasks"></i> Key Responsibilities</div>
                    <div class="content-text"><?php echo htmlspecialchars($job['responsibilities']); ?></div>
                </div></div><?php endif; ?>

                <?php if (!empty($job['required_skills'])): ?>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-star"></i> Required Skills</div>
                    <div class="skills-wrap">
                        <?php foreach ($job['required_skills'] as $skill): ?>
                        <span class="skill-tag <?php echo $skill['is_mandatory'] ? 'mandatory' : ''; ?>"><?php echo htmlspecialchars($skill['name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div></div><?php endif; ?>

                <?php 
                $jobAttachments = [];
                if (!empty($job['attachment_url'])) {
                    $rawAtt = $job['attachment_url'];
                    $decodedAtt = json_decode($rawAtt, true);
                    if (is_array($decodedAtt)) {
                        foreach ($decodedAtt as $d) {
                            if (is_array($d) && !empty($d['path'])) {
                                $jobAttachments[] = [
                                    'name' => $d['name'] ?? basename($d['path']),
                                    'path' => $d['path']
                                ];
                            } elseif (is_string($d) && trim($d) !== '') {
                                $jobAttachments[] = [
                                    'name' => basename($d),
                                    'path' => trim($d)
                                ];
                            }
                        }
                    } elseif (is_string($rawAtt) && trim($rawAtt) !== '') {
                        $jobAttachments[] = [
                            'name' => 'Official Job Attachment',
                            'path' => trim($rawAtt)
                        ];
                    }
                }
                ?>
                <?php if (!empty($jobAttachments)): ?>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-paperclip"></i> Job Documents & Attachments (<?php echo count($jobAttachments); ?>)</div>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <?php foreach ($jobAttachments as $att): 
                            $attPath = $att['path'];
                            $attUrl = (strpos($attPath, 'http') === 0) ? $attPath : APP_URL . '/' . ltrim($attPath, '/');
                            $attExt = strtolower(pathinfo($attPath, PATHINFO_EXTENSION));
                            $iconClass = match($attExt) {
                                'pdf' => 'fa-file-pdf text-danger',
                                'doc', 'docx' => 'fa-file-word text-primary',
                                'ppt', 'pptx' => 'fa-file-powerpoint text-warning',
                                'xls', 'xlsx' => 'fa-file-excel text-success',
                                'zip' => 'fa-file-zipper text-secondary',
                                default => 'fa-file-lines text-secondary'
                            };
                            $badgeStyle = match($attExt) {
                                'pdf' => 'background:#fee2e2;color:#dc2626;',
                                'doc', 'docx' => 'background:#e0e7ff;color:#4338ca;',
                                'ppt', 'pptx' => 'background:#fef3c7;color:#d97706;',
                                'xls', 'xlsx' => 'background:#dcfce7;color:#15803d;',
                                default => 'background:#f1f5f9;color:#475569;'
                            };
                        ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:14px 18px;border-radius:12px;border:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;">
                            <div style="display:flex;align-items:center;gap:14px;min-width:0;flex:1;">
                                <div style="width:40px;height:40px;border-radius:10px;<?php echo $badgeStyle; ?>display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
                                    <i class="fas <?php echo $iconClass; ?>"></i>
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-weight:700;font-size:14px;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($att['name']); ?>"><?php echo htmlspecialchars($att['name']); ?></div>
                                    <div style="font-size:12px;color:#64748b;"><?php echo strtoupper($attExt); ?> Document • Review complete roles, brochure & guidelines</div>
                                </div>
                            </div>
                            <a href="<?php echo htmlspecialchars($attUrl); ?>" target="_blank" download class="btn-builder" style="background:var(--brand);color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:6px;flex-shrink:0;">
                                <i class="fas fa-download"></i> View / Download
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div></div>
                <?php endif; ?>

                <?php if (!empty($job['company_description'])): ?>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-building"></i> About <?php echo htmlspecialchars($job['company_name'] ?? ''); ?></div>
                    <div class="content-text" style="margin-bottom:16px;"><?php echo htmlspecialchars($job['company_description']); ?></div>
                    <div style="display:flex;gap:14px;flex-wrap:wrap;">
                        <?php if (!empty($job['website']) && $job['website'] !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank" style="font-size:13px;color:var(--brand);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-external-link-alt"></i> Visit Website</a>
                        <?php endif; ?>
                        <?php if (!empty($job['company_document'])): ?>
                        <a href="../public/uploads/documents/docs/<?php echo $job['company_document']; ?>" target="_blank" style="font-size:13px;color:var(--brand);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-file-pdf"></i> View Documents</a>
                        <?php endif; ?>
                    </div>
                </div></div><?php endif; ?>

                <?php if (!empty($job['spocs'])): ?>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-address-card"></i> Contacts</div>
                    <div class="spoc-grid">
                        <?php foreach ($job['spocs'] as $spoc): ?>
                        <div class="spoc-card">
                            <div class="spoc-name"><?php echo htmlspecialchars($spoc['name'] ?? 'SPOC'); ?></div>
                            <div class="spoc-role"><?php echo htmlspecialchars($spoc['designation'] ?? ''); ?></div>
                            <?php if (!empty($spoc['email'])): ?><div class="spoc-row"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($spoc['email']); ?></div><?php endif; ?>
                            <?php if (!empty($spoc['phone'])): ?><div class="spoc-row"><i class="fas fa-phone"></i><?php echo htmlspecialchars($spoc['phone']); ?></div><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div></div><?php endif; ?>
            </div>

            <!-- RIGHT: Sidebar -->
            <div>
                <div class="card"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-info-circle"></i> Job Details</div>
                    <ul class="meta-list">
                        <li class="meta-item"><div class="meta-icon"><i class="fas fa-map-marker-alt"></i></div><div><div class="meta-label">Location</div><div class="meta-value"><?php echo htmlspecialchars($job['location'] ?? '—'); ?></div></div></li>
                        <li class="meta-item"><div class="meta-icon"><i class="fas fa-indian-rupee-sign"></i></div><div><div class="meta-label">Salary</div><div class="meta-value"><?php echo !empty($job['salary_min']) ? '₹'.number_format($job['salary_min']/100000,1).'L – ₹'.number_format($job['salary_max']/100000,1).'L/yr' : '—'; ?></div></div></li>
                        <li class="meta-item">
                            <div class="meta-icon"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="meta-label">Application Deadline</div>
                                <div class="meta-value" style="display:flex; flex-direction:column; gap:2px;">
                                    <span><?php echo date('d M Y, h:i A', strtotime($job['application_deadline'])); ?></span>
                                    <?php 
                                        $dlTimestamp = strtotime($job['application_deadline']);
                                        $diff = $dlTimestamp - time();
                                        if ($diff > 0): 
                                            $days = floor($diff / 86400);
                                            $hours = floor(($diff % 86400) / 3600);
                                            $mins = floor(($diff % 3600) / 60);
                                            if ($days > 1) {
                                                $remainingText = $days . ' days left';
                                                $remBadgeClass = 'color: #059669; font-size: 11.5px; font-weight: 600;';
                                            } elseif ($days == 1) {
                                                $remainingText = '1 day, ' . $hours . ' hrs left';
                                                $remBadgeClass = 'color: #d97706; font-size: 11.5px; font-weight: 600;';
                                            } elseif ($hours > 0) {
                                                $remainingText = $hours . ' hrs, ' . $mins . ' mins left';
                                                $remBadgeClass = 'color: #dc2626; font-size: 11.5px; font-weight: 700;';
                                            } else {
                                                $remainingText = $mins . ' mins left (Closing Soon)';
                                                $remBadgeClass = 'color: #dc2626; font-size: 11.5px; font-weight: 700;';
                                            }
                                    ?>
                                        <span style="<?php echo $remBadgeClass; ?>"><i class="fas fa-stopwatch"></i> <?php echo $remainingText; ?></span>
                                    <?php else: ?>
                                        <span style="color: #64748b; font-size: 11.5px; font-weight: 600;"><i class="fas fa-lock"></i> Applications Closed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <li class="meta-item"><div class="meta-icon"><i class="fas fa-briefcase"></i></div><div><div class="meta-label">Type</div><div class="meta-value"><?php echo htmlspecialchars($job['job_type'] ?? '—'); ?></div></div></li>
                        <li class="meta-item"><div class="meta-icon"><i class="fas fa-graduation-cap"></i></div><div><div class="meta-label">Min SGPA</div><div class="meta-value"><?php echo $job['min_cgpa'] > 0 ? htmlspecialchars($job['min_cgpa']) . '+ (Avg of completed sems)' : 'Any'; ?></div></div></li>
                        <li class="meta-item"><div class="meta-icon"><i class="fas fa-venus-mars"></i></div><div><div class="meta-label">Gender</div><div class="meta-value"><?php echo (!empty($job['eligible_gender']) && $job['eligible_gender'] !== 'Both') ? htmlspecialchars($job['eligible_gender']) . ' Only' : 'All (Male & Female)'; ?></div></div></li>
                        <li class="meta-item"><div class="meta-icon"><i class="fas fa-paper-plane"></i></div><div><div class="meta-label">Apply Via</div><div class="meta-value"><?php echo (($job['application_mode'] ?? 'Internal') === 'External') ? 'Company Website' : 'Lakshya Portal'; ?></div></div></li>
                    </ul>
                </div></div>

                <div class="card" style="margin-top: 20px;"><div class="card-pad">
                    <div class="section-title"><i class="fas fa-share-alt"></i> Share Opportunity</div>
                    <?php
                    $shareUrl = APP_URL . '/student/job_details.php?code=' . encryptJobId($job['id']);
                    $branches = json_decode($job['eligible_branches'] ?? '[]', true) ?: [];
                    $branchesStr = !empty($branches) ? implode(', ', $branches) : 'All Branches';

                    $salaryStr = 'Not Specified';
                    if (!empty($job['salary_min']) && !empty($job['salary_max'])) {
                        $salaryStr = '₹' . number_format($job['salary_min'] / 100000, 1) . 'L – ₹' . number_format($job['salary_max'] / 100000, 1) . 'L per year';
                    }

                    $deadlineStr = date('M d, Y \a\t h:i A', strtotime($job['application_deadline']));

                    $genderNote = '';
                    if (!empty($job['eligible_gender']) && $job['eligible_gender'] !== 'Both') {
                        $genderNote = "*Gender:* " . $job['eligible_gender'] . " Only\n";
                    }

                    $waMessage = "*📢 New Placement Opportunity!*\n\n"
                               . "*Company:* " . ($job['company_name'] ?? 'Company') . "\n"
                               . "*Role:* " . $job['title'] . "\n"
                               . "*Location:* " . $job['location'] . "\n"
                               . "*Salary:* " . $salaryStr . "\n"
                               . "*Min SGPA:* " . ($job['min_cgpa'] ?: 'Any') . "+\n"
                               . "*Eligible Branches:* " . $branchesStr . "\n"
                               . $genderNote
                               . "*Deadline:* " . $deadlineStr . "\n\n"
                               . "*Apply here:* " . $shareUrl . "\n\n"
                               . "_Lakshya Placement Portal_";

                    $waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMessage);
                    ?>
                    <a href="<?php echo $waUrl; ?>" target="_blank" class="btn-submit" style="background: #25D366; text-decoration: none; color: white;">
                        <i class="fab fa-whatsapp"></i> Share on WhatsApp
                    </a>
                </div></div>

                <!-- Apply Card -->
                <div class="apply-card">
                    <?php if ($hasApplied): ?>
                        <div class="btn-applied-state"><i class="fas fa-check-circle"></i> Application Submitted</div>
                        <?php if (($job['application_mode'] ?? 'Internal') === 'External' && !empty($job['external_url'])): ?>
                            <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 12px; padding: 12px; margin-top: 14px; text-align: left;">
                                <div style="font-weight: 700; color: #991b1b; font-size: 12px; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                    <i class="fas fa-exclamation-triangle"></i> Compulsory Step
                                </div>
                                <div style="font-size: 11.5px; color: #7f1d1d; line-height: 1.5;">
                                    You must compulsorily apply on the official company site. If not completed there, your application will not be considered.
                                </div>
                            </div>
                            <button type="button" onclick="openExternalModal()" class="btn-builder" style="background:#2563eb;color:#fff;margin-top:10px;width:100%;cursor:pointer;border:none;">
                                <i class="fas fa-external-link-alt"></i> Complete on Company Portal
                            </button>
                        <?php else: ?>
                            <p style="text-align:center;margin-top:12px;font-size:13px;color:var(--text-muted);">Track your status in the Dashboard.</p>
                        <?php endif; ?>

                    <?php elseif ($isEnded): ?>
                        <div class="elig-box not" style="background:#f1f5f9; border-color:#e2e8f0; color:#64748b;">
                            <div class="elig-box-title"><i class="fas fa-calendar-times"></i> Application Closed</div>
                            This job is no longer accepting applications. The deadline was <strong><?php echo date('d M Y, h:i A', strtotime($job['application_deadline'])); ?></strong>.
                        </div>

                    <?php elseif (!$hasAcademicProfile): ?>
                        <div class="elig-box not" style="background:#fffbeb;border-color:#fde68a;color:#b45309;">
                            <div class="elig-box-title"><i class="fas fa-graduation-cap"></i> Academic Details Required</div>
                            Please update your semester & SGPA details before applying.
                        </div>
                        <a href="sgpa_entry.php" class="btn-builder" style="background:var(--brand);color:#fff;"><i class="fas fa-edit"></i> Update Academic Details</a>

                    <?php elseif (!$hasResume): ?>
                        <div class="elig-box not" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;">
                            <div class="elig-box-title"><i class="fas fa-file-circle-xmark"></i> Resume Required</div>
                            Build your Lakshya resume before applying.
                        </div>
                        <a href="resume_builder.php" class="btn-builder"><i class="fas fa-magic"></i> Go to Resume Builder</a>

                    <?php elseif (!$isEligible): ?>
                        <div class="elig-box not">
                            <div class="elig-box-title"><i class="fas fa-triangle-exclamation"></i> Not Eligible</div>
                            You do not meet the criteria:
                            <ul style="margin-top:8px;"><?php foreach ($ineligibilityReasons as $r): ?><li><?php echo htmlspecialchars($r); ?></li><?php endforeach; ?></ul>
                            <?php if (!empty($eligibilityCheck['completed_sems_count']) && (float)($job['min_cgpa'] ?? 0) > 0): ?>
                                <div style="margin-top: 10px; padding: 10px 12px; background: rgba(220, 38, 38, 0.08); border-radius: 8px; font-size: 12px; border: 1px dashed rgba(220, 38, 38, 0.25);">
                                    <div style="font-weight: 700; color: #991b1b; margin-bottom: 3px;"><i class="fas fa-calculator"></i> SGPA Calculation Breakdown:</div>
                                    <div style="color: #7f1d1d;">
                                        Total SGPA (<?php echo number_format($eligibilityCheck['total_sgpa'], 2); ?>) / <?php echo $eligibilityCheck['completed_sems_count']; ?> completed sems = <strong><?php echo number_format($eligibilityCheck['average_sgpa'], 2); ?> Average SGPA</strong>
                                        <br><span style="opacity: 0.9;">Required Minimum: <strong><?php echo number_format((float)$job['min_cgpa'], 2); ?></strong></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif (($job['application_mode'] ?? 'Internal') === 'External' && !empty($job['external_url'])): ?>
                        <div class="elig-box ok">
                            <div class="elig-box-title"><i class="fas fa-circle-check"></i> You're Eligible!</div>
                            <?php if (!empty($eligibilityCheck['completed_sems_count']) && (float)($job['min_cgpa'] ?? 0) > 0): ?>
                                <div style="font-size: 12px; margin-top: 8px; padding: 8px 12px; background: rgba(22, 163, 74, 0.12); border-radius: 8px; color: #166534; border: 1px solid rgba(22, 163, 74, 0.25);">
                                    <i class="fas fa-calculator"></i> <strong>Academic Average:</strong> Total SGPA (<?php echo number_format($eligibilityCheck['total_sgpa'], 2); ?>) / <?php echo $eligibilityCheck['completed_sems_count']; ?> completed sems = <strong><?php echo number_format($eligibilityCheck['average_sgpa'], 2); ?> Average SGPA</strong> (Min Required: <?php echo number_format((float)$job['min_cgpa'], 2); ?>)
                                </div>
                            <?php endif; ?>
                            <div style="font-size:12px; margin-top:4px; opacity:0.95;">
                                <i class="fas fa-clock"></i> Deadline: <strong><?php echo date('d M Y, h:i A', strtotime($job['application_deadline'])); ?></strong>
                            </div>
                        </div>
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
                            <div style="color: #1e40af; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                                <i class="fas fa-globe"></i> Apply on Company Portal
                            </div>
                            <div style="font-size: 12px; color: #1e3a8a; line-height: 1.5;">
                                This company requires applications directly on their official recruitment portal. Clicking below will log your application on Lakshya and immediately open the company application portal in a new tab.
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="apply_mode" value="external">
                            <button type="submit" name="apply" class="btn-submit" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                                Apply on Company Website <i class="fas fa-external-link-alt" style="margin-left: 6px;"></i>
                            </button>
                        </form>

                    <?php else: ?>
                        <div class="elig-box ok">
                            <div class="elig-box-title"><i class="fas fa-circle-check"></i> You're Eligible!</div>
                            <?php if (!empty($eligibilityCheck['completed_sems_count']) && (float)($job['min_cgpa'] ?? 0) > 0): ?>
                                <div style="font-size: 12px; margin-top: 8px; padding: 8px 12px; background: rgba(22, 163, 74, 0.12); border-radius: 8px; color: #166534; border: 1px solid rgba(22, 163, 74, 0.25);">
                                    <i class="fas fa-calculator"></i> <strong>Academic Average:</strong> Total SGPA (<?php echo number_format($eligibilityCheck['total_sgpa'], 2); ?>) / <?php echo $eligibilityCheck['completed_sems_count']; ?> completed sems = <strong><?php echo number_format($eligibilityCheck['average_sgpa'], 2); ?> Average SGPA</strong> (Min Required: <?php echo number_format((float)$job['min_cgpa'], 2); ?>)
                                </div>
                            <?php endif; ?>
                            <div style="font-size:12px; margin-top:4px; opacity:0.95;">
                                <i class="fas fa-clock"></i> Apply before: <strong><?php echo date('d M Y, h:i A', strtotime($job['application_deadline'])); ?></strong>
                            </div>
                        </div>
                        <div class="resume-ready-box">
                            <strong><i class="fas fa-file-pdf"></i> Resume Ready</strong>
                            Your Lakshya resume will be auto-submitted. <a href="view_resume.php?usn=<?php echo urlencode($currentUsn); ?>" target="_blank">Preview it</a>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="cover_letter">Cover Letter</label>
                                <textarea id="cover_letter" name="cover_letter" class="form-control" placeholder="Why are you a great fit for this role?" required></textarea>
                            </div>
                            <?php if (!empty($customFields)): ?>
                                <hr class="divider">
                                <div class="custom-q-title">Additional Questions</div>
                                <?php foreach ($customFields as $i => $field): ?>
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars($field['label']); ?><?php if (!empty($field['required'])): ?> <span style="color:#dc2626;">*</span><?php endif; ?></label>
                                    <?php if ($field['type']==='text'): ?><input type="text" name="custom_response_<?php echo $i; ?>" class="form-control" <?php echo !empty($field['required'])?'required':''; ?>>
                                    <?php elseif ($field['type']==='number'): ?><input type="number" name="custom_response_<?php echo $i; ?>" class="form-control" <?php echo !empty($field['required'])?'required':''; ?>>
                                    <?php elseif ($field['type']==='yesno'): ?><select name="custom_response_<?php echo $i; ?>" class="form-control" <?php echo !empty($field['required'])?'required':''; ?>><option value="">Select...</option><option>Yes</option><option>No</option></select>
                                    <?php elseif ($field['type']==='file'): ?><input type="file" name="custom_file_<?php echo $i; ?>" class="form-control" <?php echo !empty($field['required'])?'required':''; ?>>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <button type="submit" name="apply" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Application</button>
                        </form>
                    <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <!-- External Application Compulsory Warning Modal -->
    <?php if (($job['application_mode'] ?? 'Internal') === 'External' && !empty($job['external_url'])): ?>
    <div class="ext-modal-backdrop <?php echo !empty($showExternalModal) ? 'active' : ''; ?>" id="externalApplyModal">
        <div class="ext-modal-dialog">
            <div class="ext-modal-header">
                <div class="ext-modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ext-modal-badge">
                    <i class="fas fa-bell"></i> Compulsory Step Required
                </div>
                <h3 class="ext-modal-title">Apply on Company Website</h3>
                <p class="ext-modal-subtitle">Your application is logged on Lakshya. Complete the final mandatory step.</p>
            </div>
            
            <div class="ext-modal-body">
                <div class="ext-modal-warning-card">
                    <strong>
                        <i class="fas fa-triangle-exclamation"></i> Action Required
                    </strong>
                    <p>
                        After applying in Lakshya, you <strong>MUST compulsorily apply on the official company recruitment website</strong>.
                        If you do not submit your application on the company's portal, your application <strong>will NOT be considered or shortlisted</strong>.
                    </p>
                </div>

                <div class="ext-modal-job-summary">
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:13.5px;"><?php echo htmlspecialchars($job['title']); ?></div>
                        <div style="color:#64748b; font-size:12px; margin-top:2px;"><i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?></div>
                    </div>
                    <span style="background:#e0f2fe; color:#0369a1; padding:4px 9px; border-radius:6px; font-size:11px; font-weight:700;">External Portal</span>
                </div>
            </div>

            <div class="ext-modal-footer">
                <a href="<?php echo htmlspecialchars($job['external_url']); ?>" target="_blank" class="ext-modal-btn-open" id="modalOpenExternalBtn" onclick="markExternalOpened()">
                    <span>Apply on Company Website</span>
                    <i class="fas fa-external-link-alt"></i>
                </a>
                <button type="button" class="ext-modal-btn-close" onclick="closeExternalModal()">
                    <i class="fas fa-check-circle" style="color:#059669; margin-right:4px;"></i> I Have Applied / Understood
                </button>
            </div>
        </div>
    </div>

    <script>
        function openExternalModal() {
            const modal = document.getElementById('externalApplyModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        function closeExternalModal() {
            const modal = document.getElementById('externalApplyModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        function markExternalOpened() {
            setTimeout(function() {
                const btn = document.getElementById('modalOpenExternalBtn');
                if (btn) {
                    btn.innerHTML = '<span>Re-open Company Website</span> <i class="fas fa-external-link-alt"></i>';
                }
            }, 600);
        }
        // If modal is active on page load, lock scroll
        if (document.getElementById('externalApplyModal') && document.getElementById('externalApplyModal').classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        }
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeExternalModal();
        });
        // Close on clicking backdrop outside dialog
        const modalBackdrop = document.getElementById('externalApplyModal');
        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', function(e) {
                if (e.target === this) closeExternalModal();
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>