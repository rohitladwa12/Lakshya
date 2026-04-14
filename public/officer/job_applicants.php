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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants for <?php echo htmlspecialchars($job['title']); ?> - <?php echo APP_NAME; ?></title>
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
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg-light); color: var(--text-dark); margin: 0; }
        .navbar { background: var(--primary); color: var(--white); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
        .card { background: var(--white); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 2rem; margin-bottom: 2rem; border: 1px solid var(--border-color); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .job-info h1 { margin: 0; font-size: 1.5rem; }
        .job-info p { color: var(--text-muted); margin: 0.5rem 0 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background: #f8fafc; color: var(--text-muted); text-transform: uppercase; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.05em; }
        
        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-applied { background: #dbeafe; color: #1e40af; }
        .badge-selected { background: #dcfce7; color: #166534; }
        
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-outline { border: 1px solid var(--border-color); color: var(--text-dark); background: white; }
        
        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; border-radius: 12px; width: 500px; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🎓 LAKSHYA - Officer</h1>
    </div>
    
    <div class="container">
        <div class="header">
            <div class="job-info">
                <h1>Applicants for: <?php echo htmlspecialchars($job['title']); ?></h1>
                <p><?php echo htmlspecialchars($job['company_name']); ?> &bull; <?php echo htmlspecialchars($job['location']); ?></p>
            </div>
            <a href="jobs" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Jobs</a>
        </div>

        <div class="card">
            <?php if (empty($applicants)): ?>
                <p style="text-align: center; color: var(--text-muted);">No students have applied for this job yet.</p>
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
                                <strong><?php echo htmlspecialchars(($app['student_name'] ?? $app['usn']) ?? 'N/A'); ?></strong><br>
                                <span style="font-size: 0.85rem; color: var(--text-muted)"><?php echo htmlspecialchars(($app['student_id'] ?? $app['usn']) ?? 'N/A'); ?></span>
                            </td>
                            <td><?php echo $app['sgpa'] ?? 'N/A'; ?></td>
                            <td>
                                <?php if ($app['resume_path']): ?>
                                    <a href="../<?php echo $app['resume_path']; ?>" target="_blank" class="btn btn-outline btn-sm">
                                        <i class="fas fa-file-pdf"></i> View Resume
                                    </a>
                                <?php else: ?>
                                    No Resume
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($app['status']); ?>">
                                    <?php echo $app['status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($app['status'] !== 'Selected'): ?>
                                    <button onclick="openPlacementModal(<?php echo htmlspecialchars(json_encode($app)); ?>)" class="btn btn-primary btn-sm">
                                        <i class="fas fa-award"></i> Mark as Placed
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--success); font-weight: 600;">ALREADY PLACED</span>
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
            <h2 style="margin-top: 0;">Mark Student as Placed</h2>
            <p id="studentNameDisplay" style="color: var(--text-muted); margin-bottom: 2rem;"></p>
            
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
            document.getElementById('placementModal').style.display = 'block';
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
