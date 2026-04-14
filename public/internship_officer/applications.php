<?php
/**
 * Internship Applications View (Overhauled)
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

$applicationModel = new InternshipApplication();
$applications = $applicationModel->getByInternship($internshipId);

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="internship_applications_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Determine the maximum semester with actual data across all students
    $maxSemester = 0;
    foreach ($applications as $app) {
        $semSgpa = $app['sem_sgpa_all'] ?? [];
        for ($i = 8; $i >= 1; $i--) {
            // Skip null, empty, and 0.00 values
            if (isset($semSgpa[$i]) && $semSgpa[$i] !== null && $semSgpa[$i] !== '' && floatval($semSgpa[$i]) > 0) {
                $maxSemester = max($maxSemester, $i);
                break;
            }
        }
    }
    
    // If no semester data found, don't add semester columns
    $hasSemesterData = ($maxSemester > 0);
    
    // CSV Headers
    $headers = [
        'Sl No', 'Name', 'Student ID', 'Institution', 'Course', 'Branch', 'Current Sem'
    ];
    
    // Only add semester columns if there's data
    if ($hasSemesterData) {
        for ($i = 1; $i <= $maxSemester; $i++) {
            $headers[] = "Sem $i SGPA";
        }
    }
    
    $headers = array_merge($headers, ['Company', 'Role', 'Applied On', 'Status', 'Email', 'Phone']);
    fputcsv($output, $headers);
    
    // CSV Data
    $slNo = 1;
    foreach ($applications as $app) {
        $row = [
            $slNo++,
            $app['student_name'] ?? 'Unknown',
            $app['student_id'] ?? 'N/A',
            $app['institution'] ?? 'N/A',
            $app['course'] ?? 'N/A',
            $app['branch'] ?? 'N/A',
            $app['sem'] ?? 'N/A'
        ];
        
        // Add semester SGPAs only if there's data
        if ($hasSemesterData) {
            $semSgpa = $app['sem_sgpa_all'] ?? array_fill(1, 8, null);
            for ($i = 1; $i <= $maxSemester; $i++) {
                // Skip null, empty, and 0.00 values
                if (isset($semSgpa[$i]) && $semSgpa[$i] !== null && $semSgpa[$i] !== '' && floatval($semSgpa[$i]) > 0) {
                    $row[] = number_format($semSgpa[$i], 2);
                } else {
                    $row[] = '-';
                }
            }
        }
        
        $row = array_merge($row, [
            $internship['company_name'],
            $internship['internship_title'],
            date('d M Y h:i A', strtotime($app['applied_at'])),
            $app['status'],
            $app['email'] ?? 'N/A',
            $app['phone'] ?? 'N/A'
        ]);
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Handle Status Updates
if (isPost() && isset($_POST['update_status'])) {
    $appId = post('app_id');
    $status = post('status');
    $applicationModel->update($appId, ['status' => $status]);
    // Refresh
    redirect("applications.php?id=$internshipId");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applicants - <?php echo htmlspecialchars($internship['internship_title']); ?></title>
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
            --blue: #3b82f6;
            --green: #22c55e;
            --amber: #f59e0b;
        }

        body { background: var(--bg-body); font-family: 'Inter', sans-serif; margin: 0; color: var(--text-dark); }
        
        .container { max-width: 1400px; margin: 2.5rem auto; padding: 0 2rem; }
        
        /* Header Section */
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem; 
            flex-wrap: wrap;
            gap: 1rem;
        }
        .header-title h1 { 
            font-size: 1.75rem; 
            font-weight: 800; 
            margin: 0 0 0.5rem 0; 
            letter-spacing: -0.025em; 
        }
        .header-title p { color: var(--text-muted); font-size: 0.95rem; margin: 0; }
        
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }
        
        .export-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transform: translateY(-1px);
        }
        
        .export-btn i {
            font-size: 1.1rem;
        }
        
        .back-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            text-decoration: none; 
            color: var(--text-muted); 
            font-weight: 600; 
            font-size: 0.9rem;
            transition: color 0.2s;
            margin-bottom: 1rem;
        }
        .back-link:hover { color: var(--primary); }

        /* Container */
        .container {
            max-width: 95%;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Card Layout */
        .content-card {
            background: var(--white);
            border-radius: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 2rem;
            border: 1px solid var(--border);
        }

        /* Professional Table */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .professional-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .professional-table thead {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid var(--border);
        }

        .professional-table th {
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            border-right: 1px solid #e8ecef;
        }

        .professional-table th:last-child {
            border-right: none;
        }

        .professional-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.2s;
        }

        .professional-table tbody tr:hover {
            background-color: #fafbfc;
        }

        .professional-table td {
            padding: 1rem 1rem;
            color: var(--text-dark);
            vertical-align: middle;
            border-right: 1px solid #f8fafc;
        }

        .professional-table td:last-child {
            border-right: none;
        }

        .name-cell {
            font-weight: 600;
            color: var(--primary);
        }

        .sgpa-cell {
            text-align: center;
        }

        .btn-view-sgpa {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-view-sgpa:hover {
            background: #fde68a;
            border-color: #d97706;
        }

        .sem-cell {
            font-weight: 600;
            color: var(--primary);
        }

        .date-cell {
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* SGPA Modal Styles */
        .sgpa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1rem 0;
        }

        .sgpa-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.2s;
        }

        .sgpa-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.1);
        }

        .sem-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .sem-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #cbd5e1;
        }

        .sem-value.has-value {
            color: var(--amber);
        }

        .time-text {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .action-cell {
            text-align: center;
        }

        .btn-view-resume {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-view-resume:hover {
            background: #fecaca;
            border-color: #dc2626;
        }

        .status-cell {
            min-width: 150px;
        }

        .inline-form {
            margin: 0;
        }

        .status-dropdown {
            width: 100%;
            padding: 0.6rem 2rem 0.6rem 0.75rem;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            background: white;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1rem;
            transition: all 0.2s;
        }

        .status-dropdown:hover {
            border-color: var(--primary);
        }

        .status-dropdown:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        .text-muted {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Modal Overhaul */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; left: 0; 
            width: 100%; height: 100%; 
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(4px);
            align-items: center; justify-content: center; 
            z-index: 2000; 
        }
        .modal-content { 
            background: white; 
            padding: 0; 
            border-radius: 24px; 
            max-width: 800px; 
            width: 90%; 
            max-height: 85vh; 
            overflow: hidden; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
        }
        .modal-header { 
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .modal-header h3 { margin: 0; font-weight: 800; font-size: 1.25rem; letter-spacing: -0.025em; }
        .modal-body { padding: 2rem; overflow-y: auto; }
        .close-modal { 
            cursor: pointer; 
            font-size: 1.25rem; 
            color: #94a3b8; 
            transition: color 0.2s;
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px;
        }
        .close-modal:hover { color: #0f172a; background: #e2e8f0; }

        /* Role Cell Truncation */
        .role-cell {
            max-width: 250px;
            position: relative;
        }
        .role-text-container {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.4;
            font-size: 0.85rem;
            color: var(--text-dark);
        }
        .btn-read-more {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 0;
            cursor: pointer;
            text-decoration: underline;
            display: inline-block;
            margin-top: 4px;
            transition: color 0.2s;
        }
        .btn-read-more:hover {
            color: #600000;
        }

        .empty-state { text-align: center; padding: 5rem 2rem; }
    </style>
</head>
<body>
    
    <?php include 'navbar.php'; ?>

    <div class="container">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <div class="page-header">
            <div class="header-title">
                <h1>Candidates for <?php echo htmlspecialchars($internship['internship_title']); ?></h1>
                <p><?php echo htmlspecialchars($internship['company_name']); ?> • <?php echo count($applications); ?> Applicants found</p>
            </div>
            <a href="applications.php?id=<?php echo $internshipId; ?>&export=excel" class="export-btn">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
        </div>

        <div class="content-card">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash" style="font-size: 4rem; opacity: 0.1; margin-bottom: 1.5rem;"></i>
                    <h2 style="font-size: 1.5rem; font-weight: 700;">No applications yet</h2>
                    <p style="color: var(--text-muted);">Applications will appear here once students start applying.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="professional-table">
                        <thead>
                            <tr>
                                <th>Sl No</th>
                                <th>Name</th>
                                <th>Student ID</th>
                                <th>Institution</th>
                                <th>Course</th>
                                <th>Branch</th>
                                <th>Current Sem</th>
                                <th>SGPA</th>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Applied On</th>
                                <th>View Resume</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $slNo = 1;
                            foreach ($applications as $app): 
                            ?>
                                <tr>
                                    <td><?php echo $slNo++; ?></td>
                                    <td class="name-cell"><?php echo htmlspecialchars($app['student_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($app['student_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['institution'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['course'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['branch'] ?? 'N/A'); ?></td>
                                    <td class="sem-cell"><?php echo htmlspecialchars($app['sem'] ?? 'N/A'); ?></td>
                                    <td class="sgpa-cell">
                                        <?php 
                                        $studentName = htmlspecialchars($app['student_name'] ?? 'Student', ENT_QUOTES);
                                        $studentId = htmlspecialchars($app['student_id'], ENT_QUOTES);
                                        $semSgpaJson = htmlspecialchars(json_encode($app['sem_sgpa_all'] ?? array_fill(1, 8, null)), ENT_QUOTES);
                                        ?>
                                        <button class="btn-view-sgpa" onclick='viewSGPA("<?php echo $studentName; ?>", "<?php echo $studentId; ?>", <?php echo $semSgpaJson; ?>)'>
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                    <td><?php echo htmlspecialchars($internship['company_name']); ?></td>
                                    <td class="role-cell">
                                        <?php 
                                        $fullRole = $internship['internship_title'];
                                        $displayRole = htmlspecialchars($fullRole);
                                        $isLong = strlen($fullRole) > 60;
                                        ?>
                                        <div class="role-text-container" title="<?php echo $displayRole; ?>">
                                            <?php echo $displayRole; ?>
                                        </div>
                                        <?php if ($isLong): ?>
                                            <button class="btn-read-more" onclick="viewFullRole('<?php echo addslashes($displayRole); ?>')">Read More</button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="date-cell">
                                        <?php echo date('d M Y', strtotime($app['applied_at'])); ?><br>
                                        <span class="time-text"><?php echo date('h:i A', strtotime($app['applied_at'])); ?></span>
                                    </td>
                                    <td class="action-cell">
                                        <?php if (!empty($app['resume_path'])): ?>
                                            <a href="../<?php echo $app['resume_path']; ?>" target="_blank" class="btn-view-resume">
                                                <i class="fas fa-file-pdf"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="status-cell">
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" class="status-dropdown" onchange="this.form.submit()">
                                                <option value="Applied" <?php echo $app['status'] == 'Applied' ? 'selected' : ''; ?>>Applied</option>
                                                <option value="Shortlisted" <?php echo $app['status'] == 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                <option value="Selected" <?php echo $app['status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                                <option value="Rejected" <?php echo $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SGPA Modal -->
    <div id="sgpaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="sgpaModalTitle">Semester-wise SGPA</h3>
                <span class="close-modal" onclick="closeSGPAModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="sgpa-grid" id="sgpaGrid">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Role Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Full Internship Role</h3>
                <span class="close-modal" onclick="closeRoleModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="fullRoleText" style="line-height: 1.6; color: var(--text-dark); font-weight: 500;"></p>
            </div>
        </div>
    </div>

    <script>
        function viewFullRole(text) {
            document.getElementById('fullRoleText').textContent = text;
            document.getElementById('roleModal').style.display = 'flex';
        }

        function closeRoleModal() {
            document.getElementById('roleModal').style.display = 'none';
        }

        function viewSGPA(studentName, studentId, semSgpaData) {
            document.getElementById('sgpaModalTitle').textContent = studentName + ' (' + studentId + ') - Semester-wise SGPA';
            
            const grid = document.getElementById('sgpaGrid');
            grid.innerHTML = '';
            
            // Only show semesters that have data
            let hasData = false;
            for (let sem = 1; sem <= 8; sem++) {
                const sgpa = semSgpaData[sem];
                
                // Skip if no data for this semester (null, undefined, or 0.00)
                if (sgpa === null || sgpa === undefined || parseFloat(sgpa) === 0) {
                    continue;
                }
                
                hasData = true;
                const sgpaValue = parseFloat(sgpa).toFixed(2);
                
                const cell = document.createElement('div');
                cell.className = 'sgpa-item';
                cell.innerHTML = `
                    <div class="sem-label">Semester ${sem}</div>
                    <div class="sem-value has-value">${sgpaValue}</div>
                `;
                grid.appendChild(cell);
            }
            
            // If no data at all, show a message
            if (!hasData) {
                grid.innerHTML = '<div style="text-align: center; padding: 2rem; color: #94a3b8;">No semester SGPA data available</div>';
            }
            
            document.getElementById('sgpaModal').style.display = 'flex';
        }
        
        function closeSGPAModal() {
            document.getElementById('sgpaModal').style.display = 'none';
        }
        
        // Close SGPA modal when clicking outside (using addEventListener to avoid conflicts)
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('sgpaModal').addEventListener('click', function(event) {
                if (event.target === this) {
                    closeSGPAModal();
                }
            });
            document.getElementById('roleModal').addEventListener('click', function(event) {
                if (event.target === this) {
                    closeRoleModal();
                }
            });
        });
    </script>


    <!-- History Modal -->
    <div id="historyModal" class="modal" onclick="if(event.target==this) closeModal()">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-history" style="color: var(--primary); margin-right: 0.5rem;"></i> <span id="modalTitle">Application History</span></h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                    <p style="margin-top: 1rem; color: var(--text-muted);">Fetching history...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewHistory(studentId, name) {
            document.getElementById('modalTitle').innerText = 'History: ' + name;
            document.getElementById('historyModal').style.display = 'flex';
            document.getElementById('modalBody').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i><p style="margin-top: 1rem; color: var(--text-muted);">Fetching history...</p></div>';
            
            fetch('get_student_history.php?student_id=' + studentId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalBody').innerHTML = html;
                })
                .catch(err => {
                    document.getElementById('modalBody').innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i><p style="margin-top: 1rem;">Failed to load history.</p></div>';
                });
        }
        
        function closeModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
