<?php
/**
 * Job Attendance Page - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if (!$jobId) {
    header("Location: jobs.php");
    exit;
}

$db = getDB();

// Fetch job details
$jobModel = new JobPosting();
$job = $jobModel->find($jobId);

if (!$job) {
    header("Location: jobs.php");
    exit;
}

// Fetch company details
$stmtCompany = $db->prepare("SELECT name FROM companies WHERE id = ? LIMIT 1");
$stmtCompany->execute([$job['company_id']]);
$companyName = $stmtCompany->fetchColumn() ?: 'Company';
$job['company_name'] = $companyName;

// Handle Form Submission
$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    try {
        $db->beginTransaction();
        
        $attendance = $_POST['attendance'] ?? [];
        $studentNames = $_POST['student_names'] ?? [];
        $branches = $_POST['branches'] ?? [];
        $sems = $_POST['sems'] ?? [];
        $academicYears = $_POST['academic_years'] ?? [];
        
        $stmtInsert = $db->prepare("
            INSERT INTO job_attendance (job_id, academic_year, student_id, student_name, branch, sem, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                academic_year = VALUES(academic_year),
                student_name = VALUES(student_name),
                branch = VALUES(branch),
                sem = VALUES(sem),
                status = VALUES(status)
        ");
        
        foreach ($attendance as $usn => $status) {
            $name = $studentNames[$usn] ?? 'N/A';
            $branch = $branches[$usn] ?? 'N/A';
            $sem = (int)($sems[$usn] ?? 0);
            $ay = $academicYears[$usn] ?? 'N/A';
            
            $stmtInsert->execute([
                $jobId,
                $ay,
                $usn,
                $name,
                $branch,
                $sem,
                $status
            ]);
        }
        
        $db->commit();
        $successMsg = "Attendance saved successfully!";
    } catch (Exception $e) {
        $db->rollBack();
        $errorMsg = "Failed to save attendance: " . $e->getMessage();
    }
}

// Fetch applications for this job
$appModel = new JobApplication();
$rawApps = $appModel->getByJob($jobId);

$userModel = new User();
$students = [];
$stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'pending' => 0
];

$stmtAtt = $db->prepare("SELECT student_id, status FROM job_attendance WHERE job_id = ?");
$stmtAtt->execute([$jobId]);
$attendanceMap = $stmtAtt->fetchAll(PDO::FETCH_KEY_PAIR);

$gmuUsns = [];
$gmitUsns = [];

foreach ($rawApps as $app) {
    $usn = $app['usn'];
    if (empty($usn) || $usn === '-') $usn = $app['student_id'];
    
    if (($app['institution'] ?? 'GMU') === INSTITUTION_GMU) {
        $gmuUsns[] = $usn;
    } else {
        $gmitUsns[] = $usn;
    }
}

$studentMeta = [];

// Fetch GMU Details Batch
if (!empty($gmuUsns)) {
    try {
        $remoteDB = getDB('gmu');
        $prefix = DB_GMU_PREFIX;
        $placeholders = implode(',', array_fill(0, count($gmuUsns), '?'));
        $stmt = $remoteDB->prepare("
            SELECT a.usn, a.sem, a.academic_year, u.COURSE, u.DISCIPLINE 
            FROM {$prefix}ad_student_approved a
            LEFT JOIN {$prefix}users u ON a.usn = u.USER_NAME
            WHERE a.usn IN ($placeholders)
        ");
        $stmt->execute($gmuUsns);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // Keep the latest semester if duplicates exist
            if (!isset($studentMeta[$row['usn']]) || $row['sem'] > $studentMeta[$row['usn']]['sem']) {
                $studentMeta[$row['usn']] = [
                    'sem' => (int)$row['sem'], 
                    'academic_year' => $row['academic_year'],
                    'course' => $row['COURSE'] ?? 'BTECH',
                    'discipline' => $row['DISCIPLINE'] ?? 'N/A'
                ];
            }
        }
    } catch (Exception $e) {}
}

// Fetch GMIT Details Batch
if (!empty($gmitUsns)) {
    try {
        $placeholders = implode(',', array_fill(0, count($gmitUsns), '?'));
        
        $stmt = $db->prepare("SELECT student_id, semester FROM student_sem_sgpa WHERE institution = ? AND is_current = 1 AND student_id IN ($placeholders)");
        $params = array_merge([INSTITUTION_GMIT], $gmitUsns);
        $stmt->execute($params);
        $gmitSems = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $remoteDB = getDB('gmit');
        $prefix = DB_GMIT_PREFIX;
        $stmt = $remoteDB->prepare("
            SELECT d.student_id, d.academic_year, u.COURSE, u.DISCIPLINE 
            FROM {$prefix}ad_student_details d
            LEFT JOIN {$prefix}users u ON d.student_id = u.USER_NAME
            WHERE d.student_id IN ($placeholders) OR d.enquiry_no IN ($placeholders)
        ");
        $params = array_merge($gmitUsns, $gmitUsns);
        $stmt->execute($params);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sid = $row['student_id'];
            $studentMeta[$sid] = [
                'sem' => $gmitSems[$sid] ?? 0,
                'academic_year' => $row['academic_year'],
                'course' => $row['COURSE'] ?? 'BE',
                'discipline' => $row['DISCIPLINE'] ?? 'N/A'
            ];
        }
    } catch (Exception $e) {}
}

foreach ($rawApps as $app) {
    $usn = $app['usn'];
    if (empty($usn) || $usn === '-') {
        $usn = $app['student_id'];
    }
    
    $studentName = $app['student_name'] ?? 'Unknown';
    if ($studentName === 'Unknown' || $studentName === 'Unknown Student') {
        continue; // Skip orphaned applications
    }
    
    $branch = $app['course'] ?? 'N/A';
    
    $meta = $studentMeta[$usn] ?? [];
    $sem = $meta['sem'] ?? 0;
    
    // Always use the Job's academic year
    $academicYear = $job['academic_year'] ?? 'N/A';
    if (empty($academicYear)) $academicYear = 'N/A';
    
    $course = $meta['course'] ?? 'N/A';
    $branch = $meta['discipline'] ?? 'N/A';
    
    $attStatus = $attendanceMap[$usn] ?? null;
    
    $stats['total']++;
    if ($attStatus === 'Present') {
        $stats['present']++;
    } elseif ($attStatus === 'Absent') {
        $stats['absent']++;
    } else {
        $stats['pending']++;
    }
    
    $students[] = [
        'usn' => $usn,
        'student_name' => $studentName,
        'course' => $course,
        'branch' => $branch,
        'sem' => $sem,
        'academic_year' => $academicYear,
        'attendance_status' => $attStatus
    ];
}

$pageId = 'officer_jobs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance - <?php echo htmlspecialchars($job['title']); ?> - <?php echo APP_NAME; ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --brand: #800000;
            --brand-light: #fff5f5;
            --brand-gradient: linear-gradient(135deg, #800000 0%, #a52a2a 100%);
            --glass: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --present-color: #10b981;
            --present-bg: #ecfdf5;
            --absent-color: #ef4444;
            --absent-bg: #fef2f2;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--text-dark);
            margin: 0;
            padding-top: 80px; /* Space for Navbar */
            line-height: 1.6;
        }

        .o-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .o-head {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .o-head h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        /* Info Card */
        .info-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .job-detail-block h2 {
            margin: 0 0 5px 0;
            font-size: 20px;
            font-weight: 800;
        }

        .job-detail-block p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Stats Blocks */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }

        .stat-card .num {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-card .label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Glass */
        .filter-glass {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 15px 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .search-container {
            flex: 1;
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .search-input:focus {
            border-color: var(--brand);
            outline: none;
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05);
        }

        /* Table Card */
        .table-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            text-align: left;
            padding: 18px 24px;
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modern-table td {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .modern-table tr:hover td { background: rgba(128, 0, 0, 0.01); }

        .usn-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            display: inline-block;
        }

        /* Segmented Button for Attendance */
        .attendance-toggle {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .toggle-btn {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--text-muted);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .toggle-btn.present-active {
            background: var(--present-color);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }

        .toggle-btn.absent-active {
            background: var(--absent-color);
            color: white;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);
        }

        /* Buttons */
        .btn-action {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary { background: var(--brand-gradient); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(128, 0, 0, 0.2); }
        .btn-outline { border: 1.5px solid #e2e8f0; color: var(--text-dark); background: white; }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

        .bulk-actions {
            display: flex;
            gap: 10px;
        }

        .hidden-radio {
            display: none;
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="o-page">
        <!-- Head -->
        <div class="o-head">
            <div>
                <h1>Job Attendance</h1>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Record and track student participation in drives</p>
            </div>
            <a href="jobs.php" class="btn-action btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Jobs
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($successMsg): ?>
            <div style="background: #e3fcef; color: #00875a; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;">
                ✅ <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div style="background: #ffebe6; color: #bf2600; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;">
                ⚠️ <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <!-- Info Card -->
        <div class="info-card">
            <div class="job-detail-block">
                <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                <p>
                    <span><i class="fas fa-building"></i> <strong><?php echo htmlspecialchars($job['company_name']); ?></strong></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                    <span><i class="fas fa-calendar-alt"></i> Deadline: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></span>
                </p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left: 4px solid var(--brand);">
                <div class="num" style="color: var(--brand);"><?php echo $stats['total']; ?></div>
                <div class="label">Total Applicants</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--present-color);">
                <div class="num" style="color: var(--present-color);"><?php echo $stats['present']; ?></div>
                <div class="label">Present</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--absent-color);">
                <div class="num" style="color: var(--absent-color);"><?php echo $stats['absent']; ?></div>
                <div class="label">Absent</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #f59e0b;">
                <div class="num" style="color: #f59e0b;"><?php echo $stats['pending']; ?></div>
                <div class="label">Not Marked</div>
            </div>
        </div>

        <!-- Filter & Search Panel -->
        <div class="filter-glass">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="studentSearch" placeholder="Search by name, USN, branch, sem, or academic year..." class="search-input" oninput="filterTable()">
            </div>
            <div class="bulk-actions">
                <button type="button" class="btn-action btn-outline" style="color: var(--present-color); border-color: var(--present-color);" onclick="bulkMark('Present')">
                    <i class="fas fa-check-double"></i> Mark All Present
                </button>
                <button type="button" class="btn-action btn-outline" style="color: var(--absent-color); border-color: var(--absent-color);" onclick="bulkMark('Absent')">
                    <i class="fas fa-times-circle"></i> Mark All Absent
                </button>
            </div>
        </div>

        <!-- Attendance Form -->
        <form method="POST">
            <div class="table-card">
                <table class="modern-table" id="attendanceTable">
                    <thead>
                        <tr>
                            <th style="width: 80px;">SL. No.</th>
                            <th>Academic Year</th>
                            <th>USN</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Branch</th>
                            <th style="text-align: center;">Semester</th>
                            <th style="text-align: center; width: 260px;">Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sl = 1;
                        foreach ($students as $s): 
                            $usn = $s['usn'];
                            $currentStatus = $s['attendance_status'];
                        ?>
                        <tr class="student-row" data-search="<?php echo htmlspecialchars(strtolower(($s['academic_year'] ?? '') . ' ' . $usn . ' ' . $s['student_name'] . ' ' . $s['branch'] . ' sem ' . $s['sem'])); ?>">
                            <td><?php echo $sl++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($job['academic_year'] ?? 'N/A')); ?></strong>
                                <input type="hidden" name="academic_years[<?php echo htmlspecialchars($usn); ?>]" value="<?php echo htmlspecialchars((string)($job['academic_year'] ?? 'N/A')); ?>">
                            </td>
                            <td>
                                <span class="usn-badge"><?php echo htmlspecialchars($usn); ?></span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($s['student_name']); ?></span>
                                <input type="hidden" name="student_names[<?php echo htmlspecialchars($usn); ?>]" value="<?php echo htmlspecialchars($s['student_name']); ?>">
                            </td>
                            <td>
                                <span style="font-weight: 500; color: var(--text-muted);"><?php echo htmlspecialchars($s['course']); ?></span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--brand);"><?php echo htmlspecialchars($s['branch']); ?></span>
                                <input type="hidden" name="branches[<?php echo htmlspecialchars($usn); ?>]" value="<?php echo htmlspecialchars($s['branch']); ?>">
                            </td>
                            <td style="text-align: center; font-weight: 700;">
                                <?php echo $s['sem'] ?: '-'; ?>
                                <input type="hidden" name="sems[<?php echo htmlspecialchars($usn); ?>]" value="<?php echo $s['sem']; ?>">
                            </td>
                            <td style="text-align: center;">
                                <div class="attendance-toggle">
                                    <label class="toggle-btn <?php echo ($currentStatus === 'Present') ? 'present-active' : ''; ?>" onclick="selectStatus('<?php echo htmlspecialchars($usn); ?>', 'Present', this)">
                                        <input type="radio" class="hidden-radio" name="attendance[<?php echo htmlspecialchars($usn); ?>]" value="Present" <?php echo ($currentStatus === 'Present') ? 'checked' : ''; ?>>
                                        <i class="fas fa-check"></i> Present
                                    </label>
                                    <label class="toggle-btn <?php echo ($currentStatus === 'Absent') ? 'absent-active' : ''; ?>" onclick="selectStatus('<?php echo htmlspecialchars($usn); ?>', 'Absent', this)">
                                        <input type="radio" class="hidden-radio" name="attendance[<?php echo htmlspecialchars($usn); ?>]" value="Absent" <?php echo ($currentStatus === 'Absent') ? 'checked' : ''; ?>>
                                        <i class="fas fa-times"></i> Absent
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; if (empty($students)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                <i class="fas fa-users-slash" style="font-size: 48px; opacity: 0.1; margin-bottom: 20px; display: block;"></i>
                                No students have applied to this job yet.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($students)): ?>
            <div style="display: flex; justify-content: flex-end; gap: 15px;">
                <a href="jobs.php" class="btn-action btn-outline">Cancel</a>
                <button type="submit" name="save_attendance" class="btn-action btn-primary">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        function selectStatus(usn, status, element) {
            const parent = element.parentElement;
            
            // Remove active classes
            parent.querySelectorAll('.toggle-btn').forEach(btn => {
                btn.classList.remove('present-active');
                btn.classList.remove('absent-active');
            });
            
            // Add active class to clicked element
            if (status === 'Present') {
                element.classList.add('present-active');
            } else {
                element.classList.add('absent-active');
            }
            
            // Find and check hidden radio input
            const radio = element.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        }

        function bulkMark(status) {
            const rows = document.querySelectorAll('.student-row');
            rows.forEach(row => {
                // Skip hidden rows from active search filtering
                if (row.style.display === 'none') return;
                
                const toggle = row.querySelector('.attendance-toggle');
                if (!toggle) return;
                
                const btn = status === 'Present' 
                    ? toggle.querySelector('.toggle-btn:first-child') 
                    : toggle.querySelector('.toggle-btn:last-child');
                    
                if (btn) {
                    selectStatus('', status, btn);
                }
            });
        }

        function filterTable() {
            const searchVal = document.getElementById('studentSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.student-row');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchVal === '' || searchData.includes(searchVal)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
