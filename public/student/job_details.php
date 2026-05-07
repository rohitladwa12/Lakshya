<?php
/**
 * Student - Job Details
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$jobId = get('id');

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

// Handle application submission
$message = '';
$error = '';

// Check for existing global resume
$currentUsn = $_SESSION['user']['username'] ?? ''; // Safe fallback
$existingResumePath = 'uploads/resumes/Student_Resumes/' . $currentUsn . '_Resume.pdf';
$fullResumePath = __DIR__ . '/../../public/' . $existingResumePath;
$hasResume = file_exists($fullResumePath);

if (isPost() && isset($_POST['apply'])) {
    if ($hasApplied) {
        $error = "You have already applied for this job.";
    } elseif (!$hasResume) {
        $error = "Please build your resume in the Resume Builder before applying.";
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
                        
                        $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
                        $fileName = $userId . '_' . time() . '_' . $i . '.' . $ext;
                        
                        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $fileName)) {
                            $response['value'] = 'uploads/resumes/Custom_Uploads/' . $fileName;
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

        $result = $applicationModel->apply($jobId, $userId, [
            'cover_letter' => post('cover_letter'),
            'custom_responses' => json_encode($customResponses)
        ]);
        
        if ($result['success']) {
            $message = $result['message'];
            $hasApplied = true;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - <?php echo APP_NAME; ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #800000;
            --primary-dark: #5b1f1f;
            --accent: #e9c66f;
            --bg-light: #f4f6f9;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --white: #ffffff;
            --border-color: #e2e8f0;
            --success: #10b981;
        }

        /* Layout */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
            display: grid;
            grid-template-columns: 2fr 1fr; /* Main Content + Sidebar */
            gap: 2rem;
        }
        
        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; }
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        /* Header Section */
        .job-header {
            grid-column: 1 / -1; /* Span full width */
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding-bottom: 2rem; /* Spacing handled by card padding */
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background: #f1f5f9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            border: 1px solid var(--border-color);
        }

        .job-title-h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }
        
        .company-name {
            font-size: 1.1rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Section Styling */
        h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        h2::before {
            content: '';
            display: block;
            width: 4px;
            height: 1.25rem;
            background: var(--primary);
            border-radius: 2px;
        }

        .content-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            white-space: pre-line;
        }

        /* Sidebar Metadata */
        .meta-list {
            list-style: none;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .meta-item:last-child { border-bottom: none; }
        
        .meta-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #fffbf0; /* Light gold bg */
            color: #bfa05d; /* Dark gold text */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .meta-content {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .meta-value { font-size: 0.95rem; font-weight: 600; color: var(--text-dark); }

        /* Skills Tags */
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .skill-tag {
            background: #f1f5f9;
            color: var(--text-dark);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }
        .skill-tag.mandatory {
            background: #fff1f2; /* Light red */
            color: var(--primary);
            border-color: #fca5a5;
        }

        /* Apply Section */
        .apply-card {
            background: var(--white);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            position: sticky;
            top: 6rem; /* Sticky on scroll */
        }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; }
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.2s;
        }
        .form-group textarea:focus { outline: none; border-color: var(--primary); ring: 2px solid rgba(128,0,0,0.1); }
        
        .btn-apply {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-apply:hover { background: var(--primary-dark); }
        
        .btn-applied {
            width: 100%;
            padding: 1rem;
            background: #ecfdf5;
            color: var(--success);
            border: 1px solid #d1fae5;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            cursor: default;
        }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* SPOC */
        .spoc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .spoc-card {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .spoc-name { font-weight: 600; color: var(--text-dark); }
        .spoc-role { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .spoc-contact { font-size: 0.85rem; color: var(--text-dark); display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; }

    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container">
        <!-- Main Content Column -->
        <div class="main-content">
            
            <a href="job_details" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); text-decoration: none; margin-bottom: 1.5rem; font-weight: 500; font-size: 0.95rem; transition: color 0.2s;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Job Header Card -->
            <div class="card job-header">
                <div class="company-logo">
                    <?php echo strtoupper(substr($job['company_name'], 0, 2)); ?>
                </div>
                <div>
                    <h1 class="job-title-h1"><?php echo htmlspecialchars($job['title']); ?></h1>
                    <div class="company-name">
                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                        &bull; <span style="font-size:0.9em;color:var(--text-muted)"><?php echo htmlspecialchars($job['job_type']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="card">
                <h2>Job Description</h2>
                <div class="content-text"><?php echo htmlspecialchars($job['description']); ?></div>
            </div>

            <!-- Requirements -->
            <?php if (!empty($job['requirements'])): ?>
            <div class="card">
                <h2>Requirements</h2>
                <div class="content-text"><?php echo htmlspecialchars($job['requirements']); ?></div>
            </div>
            <?php endif; ?>

            <!-- Responsibilities -->
            <?php if (!empty($job['responsibilities'])): ?>
            <div class="card">
                <h2>Key Responsibilities</h2>
                <div class="content-text"><?php echo htmlspecialchars($job['responsibilities'] ?? ''); ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Skills -->
            <div class="card">
                <h2>Required Skills</h2>
                <div class="skills-container">
                    <?php foreach ($job['required_skills'] as $skill): ?>
                        <span class="skill-tag <?php echo $skill['is_mandatory'] ? 'mandatory' : ''; ?>">
                            <?php echo htmlspecialchars($skill['name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

             <!-- About Company -->
             <div class="card">
                <h2>About <?php echo htmlspecialchars($job['company_name'] ?? ''); ?></h2>
                <div class="content-text"><?php echo htmlspecialchars($job['company_description'] ?? ''); ?></div>
                
                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <a href="<?php echo htmlspecialchars($job['website'] ?? '#'); ?>" target="_blank" style="text-decoration:none; color:var(--primary); font-weight:500;">
                        Visit Website <i class="fas fa-external-link-alt"></i>
                    </a>
                    <?php if ($job['company_document']): ?>
                        <a href="../public/uploads/documents/docs/<?php echo $job['company_document']; ?>" target="_blank" style="text-decoration:none; color:var(--primary); font-weight:500;">
                            View Documents <i class="fas fa-file-pdf"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
             <!-- SPOCs -->
             <div class="card">
                <h2>Contacts</h2>
                <div class="spoc-grid">
                    <?php foreach ($job['spocs'] as $spoc): ?>
                        <div class="spoc-card">
                            <div class="spoc-name"><?php echo htmlspecialchars($spoc['name'] ?? 'SPOC'); ?></div>
                            <div class="spoc-role"><?php echo htmlspecialchars($spoc['designation'] ?? ''); ?></div>
                            <div class="spoc-contact"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($spoc['email'] ?? ''); ?></div>
                            <div class="spoc-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($spoc['phone'] ?? ''); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
        
        <!-- Sidebar Column -->
        <div class="sidebar">
            <!-- Job Metadata Card -->
            <div class="card">
                <h2>Job Details</h2>
                <ul class="meta-list">
                    <li class="meta-item">
                        <div class="meta-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="meta-content">
                            <span class="meta-label">Location</span>
                            <span class="meta-value"><?php echo htmlspecialchars($job['location']); ?></span>
                        </div>
                    </li>
                    <li class="meta-item">
                        <div class="meta-icon"><i class="fas fa-rupee-sign"></i></div>
                        <div class="meta-content">
                            <span class="meta-label">Salary</span>
                            <span class="meta-value">₹<?php echo number_format((float)($job['salary_min'] ?: 0)); ?> - <?php echo number_format((float)($job['salary_max'] ?: 0)); ?></span>
                        </div>
                    </li>
                    <li class="meta-item">
                        <div class="meta-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="meta-content">
                            <span class="meta-label">Apply By</span>
                            <span class="meta-value"><?php echo date('d M Y', strtotime($job['application_deadline'])); ?></span>
                        </div>
                    </li>
                    <li class="meta-item">
                        <div class="meta-icon"><i class="fas fa-briefcase"></i></div>
                        <div class="meta-content">
                            <span class="meta-label">Type</span>
                            <span class="meta-value"><?php echo htmlspecialchars($job['job_type']); ?></span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Apply Section (Sticky) -->
            <div class="apply-card">
                <?php if ($hasApplied): ?>
                    <button class="btn-applied">
                        <i class="fas fa-check-circle"></i> Applied
                    </button>
                    <p style="text-align: center; margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                        Good luck! Track status in Dashboard.
                    </p>
                <?php else: ?>
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Apply Now</h3>
                    <?php if ($hasResume): ?>
                        <div style="margin-bottom: 15px; padding: 15px; background: rgba(233, 198, 111, 0.1); border: 1px solid var(--accent); border-radius: 8px; font-size: 0.9em;">
                            <div style="display:flex; align-items:center; gap:0.5rem; color:var(--primary); font-weight:700; font-size:1rem; margin-bottom:0.5rem;">
                                <i class="fas fa-sparkles"></i> RESUME READY
                            </div>
                            Your Lakshya-built resume is linked and will be automatically submitted with your application.<br><br>
                            <a href="view_resume.php?usn=<?php echo urlencode($currentUsn); ?>" target="_blank" style="color: var(--primary); text-decoration: underline; font-weight: 600;">View Your Resume</a>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="cover_letter">Cover Letter</label>
                                <textarea id="cover_letter" name="cover_letter" placeholder="Why are you a good fit?" required></textarea>
                            </div>

                        <!-- Custom Fields Rendering -->
                         <?php if (!empty($customFields)): ?>
                            <div style="margin-bottom: 1.5rem; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
                                <h4 style="margin-bottom: 1rem; font-size: 0.95rem;">Additional Questions</h4>
                                <?php foreach ($customFields as $i => $field): ?>
                                    <div class="form-group">
                                        <label>
                                            <?php echo htmlspecialchars($field['label']); ?>
                                            <?php if (!empty($field['required'])): ?>
                                                <span style="color: red;">*</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($field['type'] === 'text'): ?>
                                            <input type="text" name="custom_response_<?php echo $i; ?>" class="form-control" style="width: 100%; padding: 0.8rem; border: 1px solid var(--border-color); border-radius: 8px;" <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                        
                                        <?php elseif ($field['type'] === 'number'): ?>
                                            <input type="number" name="custom_response_<?php echo $i; ?>" class="form-control" style="width: 100%; padding: 0.8rem; border: 1px solid var(--border-color); border-radius: 8px;" <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                        
                                        <?php elseif ($field['type'] === 'yesno'): ?>
                                            <select name="custom_response_<?php echo $i; ?>" class="form-control" style="width: 100%; padding: 0.8rem; border: 1px solid var(--border-color); border-radius: 8px;" <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                                <option value="">Select...</option>
                                                <option value="Yes">Yes</option>
                                                <option value="No">No</option>
                                            </select>
                                        
                                        <?php elseif ($field['type'] === 'file'): ?>
                                            <input type="file" name="custom_file_<?php echo $i; ?>" class="form-control" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px;" <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                            <small style="color: grey;">Upload document</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <button type="submit" name="apply" class="btn-apply">Submit Application</button>
                    </form>
                    <?php else: ?>
                        <div style="background: #fee2e2; padding: 1.2rem; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 1.5rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem; color:#991b1b; font-weight:700; font-size:1.1rem; margin-bottom:0.5rem;">
                                <i class="fas fa-exclamation-triangle"></i> Resume Required
                            </div>
                            <div style="font-size: 0.95rem; color: #7f1d1d; margin-bottom: 1rem;">
                                Before you can apply to jobs, you must build and save your central resume in Lakshya.
                            </div>
                            <a href="resume_builder.php" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #991b1b; color: white; border-radius: 8px; padding: 0.75rem; text-decoration: none; font-weight: 600; font-size: 1rem;">
                                <i class="fas fa-magic"></i> Go to Resume Builder
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 2rem; color: var(--text-muted); font-size: 0.9rem;">
                <p><strong>Headquarters:</strong><br>
                <?php echo htmlspecialchars($job['district']); ?>, <?php echo htmlspecialchars($job['state']); ?><br>
                <?php echo htmlspecialchars($job['country']); ?></p>
            </div>

        </div>
    </div>
</body>
</html>

