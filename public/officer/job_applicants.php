<?php
/**
 * Officer - Job Applicants
 * Displays students who applied for a specific job
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$jobId = get('job_id');
if (!$jobId) {
    redirect('jobs.php');
}

$jobModel = new JobPosting();
$applicationModel = new JobApplication();

$job = $jobModel->getWithCompany($jobId);
if (!$job) {
    redirect('jobs.php');
}

$applicants = $applicationModel->getByJob($jobId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants for <?php echo htmlspecialchars($job['title']); ?> - <?php echo APP_NAME; ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --maroon: #7C0000;
            --maroon-dark: #5A0000;
            --maroon-light: #9B1B1B;
            --gold: #B08D2C;
            --page-bg: #F8F9FA;
            --card-bg: #FFFFFF;
            --border-color: #E5E7EB;
            --text-primary: #111827;
            --text-secondary: #4B5563;
            --text-muted: #9CA3AF;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
            --shadow-modal: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            background: var(--page-bg); 
            color: var(--text-primary); 
            padding-top: 0;
            min-height: 100vh;
        }

        .container { max-width: 1400px; margin: 0 auto; padding: 32px 40px; }
        .card { 
            background: var(--card-bg); 
            border-radius: var(--radius-lg); 
            box-shadow: var(--shadow-sm); 
            padding: 24px; 
            margin-bottom: 24px; 
            border: 1px solid var(--border-color); 
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 24px; 
            background: var(--card-bg);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .job-info h1 { margin: 0; font-size: 22px; font-weight: 700; color: var(--maroon); }
        .job-info p { color: var(--text-secondary); margin: 4px 0 0; font-size: 13.5px; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 8px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { 
            background: #F9FAFB; 
            color: var(--text-secondary); 
            text-transform: uppercase; 
            font-size: 11.5px; 
            font-weight: 700; 
            letter-spacing: 0.04em; 
            border-top: 1px solid var(--border-color);
        }
        tr:hover td { background: #FDFEFE; }
        
        .badge { padding: 4px 10px; border-radius: 9999px; font-size: 11.5px; font-weight: 600; display: inline-block; }
        .badge-applied { background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE; }
        .badge-selected { background: #ECFDF5; color: #047857; border: 1px solid #D1FAE5; }
        .badge-rejected { background: #FEF2F2; color: #DC2626; border: 1px solid #FEE2E2; }
        .badge-shortlisted { background: #F5F3FF; color: #7C3AED; border: 1px solid #EDE9FE; }
        
        .btn { 
            padding: 8px 16px; 
            border-radius: var(--radius-sm); 
            font-weight: 600; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            font-size: 13px; 
            transition: all 0.15s ease;
        }
        .btn-primary { background: var(--maroon); color: #FFFFFF; border: none; }
        .btn-primary:hover { background: var(--maroon-dark); color: #FFFFFF; }
        .btn-outline { border: 1px solid var(--border-color); color: var(--text-secondary); background: #FFFFFF; }
        .btn-outline:hover { background: #F3F4F6; color: var(--text-primary); border-color: #D1D5DB; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Modal ERP */
        .modal { 
            display: none; 
            position: fixed; 
            inset: 0; 
            z-index: 2000; 
            background: rgba(17, 24, 39, 0.45); 
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }
        .modal-content { 
            background: #FFFFFF; 
            border-radius: var(--radius-lg); 
            width: 100%; 
            max-width: 520px; 
            padding: 28px; 
            box-shadow: var(--shadow-modal); 
            border: 1px solid var(--border-color);
            margin: auto;
            position: relative;
        }
        .modal-header-erp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--border-color);
        }
        .close-btn-erp {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #F3F4F6;
            color: var(--text-secondary);
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .close-btn-erp:hover { background: #E5E7EB; color: var(--text-primary); }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: var(--text-primary); }
        .form-group input { 
            width: 100%; 
            padding: 9px 12px; 
            border: 1px solid #D1D5DB; 
            border-radius: var(--radius-sm); 
            box-sizing: border-box; 
            font-size: 13.5px;
            color: var(--text-primary);
        }
        .form-group input:focus { outline: none; border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12); }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="header">
            <div class="job-info">
                <h1>Applicants: <?php echo htmlspecialchars($job['title']); ?></h1>
                <p>
                    <?php echo htmlspecialchars($job['company_name']); ?> &bull; <?php echo htmlspecialchars($job['location']); ?>
                    <?php if (($job['application_mode'] ?? 'Internal') === 'External' && !empty($job['external_url'])): ?>
                        &bull; <span style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 11px;"><i class="fas fa-external-link-alt"></i> External Portal: <a href="<?php echo htmlspecialchars($job['external_url']); ?>" target="_blank" style="color: #1d4ed8; text-decoration: underline;"><?php echo htmlspecialchars($job['external_url']); ?></a></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="jobs" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Jobs</a>
        </div>

        <div class="card">
            <?php if (empty($applicants)): ?>
                <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                    <i class="fas fa-users-slash" style="font-size: 36px; margin-bottom: 12px; opacity: 0.5;"></i>
                    <p style="font-size: 14px;">No students have applied for this job yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student Details</th>
                            <th>SGPA</th>
                            <th>Resume</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applicants as $app): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars(($app['student_name'] ?? $app['usn']) ?? 'N/A'); ?></strong><br>
                                <span style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars(($app['student_id'] ?? $app['usn']) ?? 'N/A'); ?></span>
                            </td>
                            <td style="font-weight: 600; color: var(--text-primary);"><?php echo $app['sgpa'] ?? 'N/A'; ?></td>
                            <td>
                                <?php if ($app['resume_path']): ?>
                                    <a href="../student/view_resume.php?usn=<?php echo urlencode($app['usn'] ?? ''); ?>" target="_blank" class="btn btn-outline btn-sm">
                                        <i class="fas fa-file-pdf" style="color: var(--maroon);"></i> View Resume
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">No Resume</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($app['status']); ?>">
                                    <?php echo $app['status']; ?>
                                </span>
                                <?php if (!empty($app['notes']) && strpos($app['notes'], 'External:') !== false): ?>
                                    <div style="margin-top: 4px;">
                                        <span style="font-size: 10px; font-weight: 600; color: #1e40af; background: #eff6ff; border: 1px solid #bfdbfe; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-external-link-alt"></i> External
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($app['status'] !== 'Selected'): ?>
                                    <button onclick="openPlacementModal(<?php echo htmlspecialchars(json_encode($app)); ?>)" class="btn btn-primary btn-sm">
                                        <i class="fas fa-award"></i> Mark as Placed
                                    </button>
                                <?php else: ?>
                                    <span style="color: #047857; font-weight: 700; font-size: 12px;">ALREADY PLACED</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Placement Modal -->
    <div id="placementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header-erp">
                <div>
                    <h2 style="font-size: 18px; font-weight: 700; color: var(--maroon); margin: 0;">Mark Student as Placed</h2>
                    <p id="studentNameDisplay" style="color: var(--text-secondary); font-size: 12.5px; margin-top: 3px;"></p>
                </div>
                <button type="button" onclick="closePlacementModal()" class="close-btn-erp">&times;</button>
            </div>
            
            <form action="placement_handler" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="job_id" id="modalJobId">
                <input type="hidden" name="student_id" id="modalStudentId">
                <input type="hidden" name="application_id" id="modalApplicationId">
                <input type="hidden" name="usn" id="modalUsn">
                <input type="hidden" name="company_name" value="<?php echo htmlspecialchars($job['company_name']); ?>">
                <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($job['company_id']); ?>">
                <input type="hidden" name="institution" id="modalInstitution">

                <div class="form-group">
                    <label>Salary Package (Annual LPA)</label>
                    <input type="number" step="0.01" name="salary_package" required placeholder="e.g. 5.5">
                </div>
                
                <div class="form-group">
                    <label>Placement Date</label>
                    <input type="date" name="placement_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Placement Document (PDF only)</label>
                    <input type="file" name="placement_doc" accept=".pdf" required>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Naming: USN_CompanyName.pdf</p>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                    <button type="button" onclick="closePlacementModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Placement</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPlacementModal(app) {
            document.getElementById('modalJobId').value = app.job_id;
            document.getElementById('modalStudentId').value = app.student_id;
            document.getElementById('modalApplicationId').value = app.id;
            document.getElementById('modalUsn').value = app.usn;
            document.getElementById('modalInstitution').value = app.institution;
            document.getElementById('studentNameDisplay').innerText = "Student: " + app.student_name + " (" + app.usn + ")";
            document.getElementById('placementModal').style.display = 'flex';
        }

        function closePlacementModal() {
            document.getElementById('placementModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('placementModal')) {
                closePlacementModal();
            }
        }
    </script>
</body>
</html>

