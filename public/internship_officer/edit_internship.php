<?php
/**
 * Edit Internship (Overhauled UI)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole('internship_officer');

$internshipId = get('id');
if (!$internshipId) {
    redirect('dashboard.php');
}

$internshipModel = new Internship();
$internship = $internshipModel->find($internshipId);

if (!$internship) {
    die("Internship not found.");
}

$userId = getUserId();
$message = '';
$error = '';

if (isPost()) {
    $title = post('internship_title');
    $company = post('company_name');
    
    $updateData = [
        'internship_title' => $title,
        'company_name' => $company,
        'location' => post('location'),
        'duration' => post('duration'),
        'stipend' => post('stipend'),
        'mode' => post('mode'),
        'targeted_students' => post('targeted_students'),
        'description' => post('description'),
        'requirements' => post('requirements'),
        'responsibilities' => post('responsibilities'),
        'start_date' => post('start_date'),
        'end_date' => post('end_date'),
        'application_deadline' => post('application_deadline'),
        'positions' => post('positions'),
        'link' => post('link'),
        'status' => post('status')
    ];

    // File Upload: Logo
    if (!empty($_FILES['company_logo']['name'])) {
        $ext = pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
        $logoName = 'logo_' . time() . '.' . $ext;
        $uploadDir = 'uploads/internships/logo/';
        $fullUploadDir = __DIR__ . '/../../public/' . $uploadDir;
        if (!is_dir($fullUploadDir)) mkdir($fullUploadDir, 0777, true);
        
        if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $fullUploadDir . $logoName)) {
            $updateData['company_logo'] = $uploadDir . $logoName;
        }
    }
    
    // File Upload: Documents (Simple append for now, or replace)
    if (!empty($_FILES['description_documents']['name'][0])) {
        $files = $_FILES['description_documents'];
        $count = count($files['name']);
        $docUploadDir = 'uploads/internships/document/';
        $fullDocDir = __DIR__ . '/../../public/' . $docUploadDir;
        if (!is_dir($fullDocDir)) mkdir($fullDocDir, 0777, true);
        
        $docPaths = []; // If we want to replace existing docs
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] == 0) {
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $docName = 'doc_' . time() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($files['tmp_name'][$i], $fullDocDir . $docName)) {
                    $docPaths[] = $docUploadDir . $docName;
                }
            }
        }
        if (!empty($docPaths)) {
            $updateData['description_documents'] = json_encode($docPaths);
        }
    }
    
    $success = $internshipModel->update($internshipId, $updateData);
    
    if ($success) {
        $message = "Internship updated successfully!";
        $internship = $internshipModel->find($internshipId); // Refresh data
    } else {
        $error = "Failed to update internship.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Internship - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #800000;
            --primary-soft: #ffecec;
            --bg-body: #f8fafc;
            --white: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --input-focus: #80000020;
        }

        body { background: var(--bg-body); font-family: 'Inter', sans-serif; margin: 0; color: var(--text-dark); }
        
        .container { max-width: 900px; margin: 3rem auto; padding: 0 2rem; }
        
        .card { 
            background: var(--white); 
            padding: 3rem; 
            border-radius: 24px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 20px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }
        
        .form-header { margin-bottom: 2.5rem; text-align: center; }
        .form-header h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 0.5rem 0; letter-spacing: -0.025em; }
        .form-header p { color: var(--text-muted); font-size: 0.95rem; margin: 0; }
        
        .form-section { 
            margin-bottom: 2.5rem; 
            padding-bottom: 2rem; 
            border-bottom: 1px solid #f1f5f9;
        }
        .section-title { 
            font-size: 1.1rem; 
            font-weight: 700; 
            margin-bottom: 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem;
            color: var(--primary);
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .full-width { grid-column: 1 / -1; }
        
        .form-group { margin-bottom: 0.5rem; }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 600; 
            font-size: 0.85rem; 
            color: #475569;
        }
        
        .form-control { 
            width: 100%; 
            padding: 0.85rem 1rem; 
            border: 1.5px solid var(--border); 
            border-radius: 12px; 
            font-family: inherit; 
            font-size: 0.95rem; 
            box-sizing: border-box; 
            transition: all 0.2s;
            background: #fcfcfc;
        }
        
        .form-control:focus { 
            border-color: var(--primary); 
            background: white;
            outline: none; 
            box-shadow: 0 0 0 4px var(--input-focus);
        }
        
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .btn-submit { 
            background: var(--primary); 
            color: white; 
            padding: 1.2rem; 
            border: none; 
            border-radius: 14px; 
            font-weight: 700; 
            cursor: pointer; 
            width: 100%; 
            font-size: 1rem;
            box-shadow: 0 8px 16px -4px rgba(128, 0, 0, 0.3);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .btn-submit:hover { 
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -4px rgba(128, 0, 0, 0.4);
            filter: brightness(1.1);
        }
        
        .alert { 
            padding: 1.25rem; 
            border-radius: 16px; 
            margin-bottom: 2rem; 
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
        }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        
        .back-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            margin-bottom: 1.5rem; 
            text-decoration: none; 
            color: var(--text-muted); 
            font-weight: 600; 
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary); }

        .file-input-wrapper {
            background: #f8fafc;
            border: 2px dashed var(--border);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            transition: all 0.2s;
        }
        .file-input-wrapper:hover { border-color: var(--primary); background: var(--primary-soft); }
        .file-input-wrapper i { font-size: 1.5rem; color: #94a3b8; margin-bottom: 0.5rem; display: block; }

        .current-asset {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    
    <?php include 'navbar.php'; ?>

    <div class="container">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <?php echo $message; ?> 
                    <a href="dashboard.php" style="color: inherit; font-weight: 700; text-decoration: underline; margin-left: 10px;">Go to Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="form-header">
                <h1>Edit Internship</h1>
                <p>Update the details for the internship posting.</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                
                <!-- Section 1: Basic Information -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-info-circle"></i> Basic Information</div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Internship Title</label>
                            <input type="text" name="internship_title" class="form-control" value="<?php echo htmlspecialchars($internship['internship_title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($internship['company_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($internship['location']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Company Logo</label>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                            <?php if ($internship['company_logo']): ?>
                                <div class="current-asset">
                                    <i class="fas fa-image"></i> Current: <a href="../<?php echo $internship['company_logo']; ?>" target="_blank">View Logo</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                             <label>Target Group</label>
                            <select class="form-control" name="targeted_students" required>
                                <option value="">-- Select Target Group --</option>
                                <?php 
                                    $options = [
                                        'UG' => 'UG (Undergraduate)',
                                        'PG' => 'PG (Postgraduate)',
                                        'Diploma' => 'Diploma',
                                        'UG,PG' => 'UG & PG',
                                        'UG,Diploma' => 'UG & Diploma',
                                        'PG,Diploma' => 'PG & Diploma',
                                        'UG,PG,Diploma' => 'All (UG, PG & Diploma)'
                                    ];
                                    foreach ($options as $val => $lbl) {
                                        $selected = $internship['targeted_students'] == $val ? 'selected' : '';
                                        echo "<option value=\"$val\" $selected>$lbl</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Compensation & Schedule -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-wallet"></i> Compensation & Schedule</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Stipend / Benefits</label>
                            <input type="text" name="stipend" class="form-control" value="<?php echo htmlspecialchars($internship['stipend']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Work Mode</label>
                            <select name="mode" class="form-control" required>
                                <option value="Offline" <?php echo $internship['mode'] == 'Offline' ? 'selected' : ''; ?>>Offline (On-site)</option>
                                <option value="Online" <?php echo $internship['mode'] == 'Online' ? 'selected' : ''; ?>>Online (Remote)</option>
                                <option value="Hybrid" <?php echo $internship['mode'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Duration</label>
                            <input type="text" name="duration" class="form-control" value="<?php echo htmlspecialchars($internship['duration']); ?>" required>
                        </div>

                        <div class="form-group">
                             <label>Number of Positions</label>
                            <input type="number" name="positions" class="form-control" value="<?php echo $internship['positions']; ?>" min="1">
                        </div>

                        <div class="form-group">
                            <label>Start Date (Approx)</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $internship['start_date']; ?>">
                        </div>

                        <div class="form-group">
                            <label>Application Deadline</label>
                            <input type="date" name="application_deadline" class="form-control" value="<?php echo $internship['application_deadline']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Listing Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Active" <?php echo $internship['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $internship['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Closed" <?php echo $internship['status'] == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Detailed Description -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-align-left"></i> Job Details</div>
                    <div class="form-group full-width">
                        <label>Role Description</label>
                        <textarea name="description" class="form-control" required><?php echo htmlspecialchars($internship['description']); ?></textarea>
                    </div>

                    <div class="form-group full-width" style="margin-top: 1rem;">
                        <label>Requirements / Prerequisites</label>
                        <textarea name="requirements" class="form-control"><?php echo htmlspecialchars($internship['requirements']); ?></textarea>
                    </div>
                </div>

                <!-- Section 4: Attachments & Links -->
                <div class="form-section" style="border-bottom: none;">
                    <div class="section-title"><i class="fas fa-paperclip"></i> Attachments & Links</div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Update Documents (Replaces existing)</label>
                            <div class="file-input-wrapper">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <input type="file" name="description_documents[]" multiple style="width: 100%;">
                                <span style="font-size: 0.8rem; color: #94a3b8;">Click to upload or drag files here</span>
                            </div>
                            <?php if ($internship['description_documents']): ?>
                                <div class="current-asset">
                                    <i class="fas fa-file-pdf"></i> <?php echo count(json_decode($internship['description_documents'])); ?> documents already attached.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group full-width">
                            <label>External Link (Optional)</label>
                            <input type="url" name="link" class="form-control" value="<?php echo htmlspecialchars($internship['link']); ?>" placeholder="https://company.career/job-details">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</body>
</html>
