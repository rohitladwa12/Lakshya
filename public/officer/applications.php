<?php
/**
 * Applications Management Page - Placement Officer
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$pageId = 'officer_applications';

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SessionFilterHelper::handlePostToSession($pageId, $_POST);
    header("Location: applications.php");
    exit;
}

// Retrieve from Session
$filters = SessionFilterHelper::getFilters($pageId);
$appModel = new JobApplication();
$jobModel = new JobPosting();

$jobId = $filters['job_id'] ?? null;
$statusFilter = $filters['status'] ?? null;
$companyId = $filters['company_id'] ?? null;
$semester = $filters['semester'] ?? null;
$minSgpa = $filters['min_sgpa'] ?? null;
$minSslc = $filters['min_sslc'] ?? null;
$minPuc = $filters['min_puc'] ?? null;

// Get all applications with detailed info for filtering
// 1. Fetch applications with basic job/company info from LOCAL DB
$sql = "SELECT ja.*, jp.title as job_title, c.name as company_name, c.id as company_id
        FROM job_applications ja
        JOIN job_postings jp ON ja.job_id = jp.id
        JOIN companies c ON jp.company_id = c.id";

$where = [];
$params = [];
if ($jobId) {
    $where[] = "ja.job_id = ?";
    $params[] = (int)$jobId;
}
if ($statusFilter) {
    $where[] = "ja.status = ?";
    $params[] = $statusFilter;
}
if ($companyId) {
    $where[] = "jp.company_id = ?";
    $params[] = (int)$companyId;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY ja.applied_at DESC";
$stmt = $appModel->getDB()->prepare($sql);
$stmt->execute($params);
$rawApps = $stmt->fetchAll();

// 2. Enrich with student details from REMOTE/LOCAL DBs and apply academic filters
$userModel = new User();
$applications = [];

foreach ($rawApps as $app) {
    // Fetch student info using User model which handles remote switches
    $student = $userModel->findByUsername($app['student_id']) ?: $userModel->find($app['student_id']);
    
    if ($student) {
        $app['student_name'] = $student['full_name'];
        $app['usn'] = $student['username'];
        $app['institution'] = $student['institution'];
        $app['gender'] = $student['gender'] ?? 'N/A';
        
        // Fetch academic details (Remote or Local fallback)
        $inst = $student['institution'];
        $prefix = ($inst === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        
        // Basic enrichment defaults
        $app['academic_sgpa'] = 0.0;
        $app['course'] = 'N/A';
        $app['puc_percentage'] = 0.0;
        $app['sslc_percentage'] = 0.0;
        $app['current_semester'] = null;

        try {
            if ($inst === INSTITUTION_GMU) {
                // GMU: Fetch from remote
                $remoteDB = getDB('gmu');
                
                // Get current semester
                $stmtSem = $remoteDB->prepare("SELECT sem FROM {$prefix}ad_student_approved WHERE usn = ? ORDER BY academic_year DESC, sem DESC LIMIT 1");
                $stmtSem->execute([$app['usn']]);
                $semRow = $stmtSem->fetch();
                $app['current_semester'] = $semRow ? $semRow['sem'] : null;

                // Get details and latest non-null/non-zero SGPA
                $stmtAc = $remoteDB->prepare("SELECT a.sgpa, a.course, d.puc_percentage, d.sslc_percentage, d.gender
                                           FROM {$prefix}ad_student_approved a
                                           LEFT JOIN {$prefix}ad_student_details d ON (a.usn = d.student_id OR a.usn = d.usn)
                                           WHERE a.usn = ? AND a.sgpa IS NOT NULL AND a.sgpa > 0.00
                                           ORDER BY a.academic_year DESC, a.sem DESC LIMIT 1");
                $stmtAc->execute([$app['usn']]);
                $ac = $stmtAc->fetch();
                if ($ac) {
                    $app['academic_sgpa'] = $ac['sgpa'];
                    $app['course'] = $ac['course'];
                    $app['puc_percentage'] = $ac['puc_percentage'];
                    $app['sslc_percentage'] = $ac['sslc_percentage'];
                    if (!empty($ac['gender'])) $app['gender'] = $ac['gender'];
                } else {
                    // Fallback to any record (even if SGPA is null/0) to get course/percentages
                    $stmtFallback = $remoteDB->prepare("SELECT a.sgpa, a.course, d.puc_percentage, d.sslc_percentage, d.gender
                                                FROM {$prefix}ad_student_approved a
                                                LEFT JOIN {$prefix}ad_student_details d ON (a.usn = d.student_id OR a.usn = d.usn)
                                                WHERE a.usn = ? 
                                                ORDER BY a.academic_year DESC, a.sem DESC LIMIT 1");
                    $stmtFallback->execute([$app['usn']]);
                    $fb = $stmtFallback->fetch();
                    if ($fb) {
                        $app['academic_sgpa'] = $fb['sgpa'] ?: 0.00;
                        $app['course'] = $fb['course'];
                        $app['puc_percentage'] = $fb['puc_percentage'];
                        $app['sslc_percentage'] = $fb['sslc_percentage'];
                        if (!empty($fb['gender'])) $app['gender'] = $fb['gender'];
                    }
                }
            } else {
                // GMIT: Fetch academic history from local SGPA tracker
                // First, get the current semester
                $stmtCurr = $appModel->getDB()->prepare("SELECT semester FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                $stmtCurr->execute([$app['usn'], INSTITUTION_GMIT]);
                $currSemRow = $stmtCurr->fetch();
                $app['current_semester'] = $currSemRow ? $currSemRow['semester'] : null;

                // Then get the latest semester SGPA that is > 0
                $stmtAc = $appModel->getDB()->prepare("SELECT sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND sgpa > 0.00 ORDER BY semester DESC LIMIT 1");
                $stmtAc->execute([$app['usn'], INSTITUTION_GMIT]);
                $ac = $stmtAc->fetch();
                if ($ac) {
                    $app['academic_sgpa'] = $ac['sgpa'];
                } else {
                    // Fallback to current sem SGPA if all are 0
                    $stmtAcFallback = $appModel->getDB()->prepare("SELECT sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                    $stmtAcFallback->execute([$app['usn'], INSTITUTION_GMIT]);
                    $acFallback = $stmtAcFallback->fetch();
                    $app['academic_sgpa'] = $acFallback ? $acFallback['sgpa'] : 0.00;
                }
                
                // Fetch puc/sslc from remote GMIT details
                $remoteDB = getDB('gmit');
                $stmtDet = $remoteDB->prepare("SELECT puc_percentage, sslc_percentage, course, gender FROM {$prefix}ad_student_details WHERE enquiry_no = ? OR student_id = ? LIMIT 1");
                $stmtDet->execute([$app['student_id'], $app['usn']]);
                $det = $stmtDet->fetch();
                if ($det) {
                    $app['puc_percentage'] = $det['puc_percentage'];
                    $app['sslc_percentage'] = $det['sslc_percentage'];
                    $app['course'] = $det['course'];
                    if (!empty($det['gender'])) $app['gender'] = $det['gender'];
                }
            }
        } catch (Exception $e) { /* ignore detail fetch errors */ }

        // Apply PHP-side academic filters
        if ($semester && $app['current_semester'] != $semester) continue;
        if ($minSgpa && $app['academic_sgpa'] < $minSgpa) continue;
        if ($minSslc && $app['sslc_percentage'] < $minSslc) continue;
        if ($minPuc && $app['puc_percentage'] < $minPuc) continue;

        $applications[] = $app;
    } else {
        // Student not found in either DB - possibly orphaned application
        $app['student_name'] = 'Unknown Student';
        $app['usn'] = $app['student_id'];
        $app['institution'] = 'N/A';
        
        // Skip if academic filters are active
        if ($semester || $minSgpa || $minSslc || $minPuc) continue;
        
        $applications[] = $app;
    }
}

$allCompanies = $appModel->getDB()->query("SELECT * FROM companies ORDER BY name ASC")->fetchAll();

$allJobs = $jobModel->getAllWithCompany('title ASC');
$fullName = getFullName();

// Fetch all semester SGPAs & Current Sem for the applications
$allSgpas = [];
$currentSems = [];
$studentUsns = array_values(array_unique(array_filter(array_column($applications, 'usn'))));
if (!empty($studentUsns)) {
    $placeholders = implode(',', array_fill(0, count($studentUsns), '?'));
    $sqlSgpa = "SELECT student_id, semester, sgpa, is_current FROM student_sem_sgpa WHERE student_id IN ($placeholders)";
    $stmtSgpa = $appModel->getDB()->prepare($sqlSgpa);
    $stmtSgpa->execute($studentUsns);
    $sgpaRaw = $stmtSgpa->fetchAll();
    foreach ($sgpaRaw as $row) {
        $allSgpas[$row['student_id']][$row['semester']] = $row['sgpa'];
        if ($row['is_current']) {
            $currentSems[$row['student_id']] = $row['semester'];
        }
    }
}

// Fetch drive scores for each application if it's tied to a drive
$driveScores = [];
if (!empty($applications)) {
    // Collect unique job IDs to find which ones are drives
    $jobIds = array_values(array_unique(array_column($applications, 'job_id')));
    if (!empty($jobIds)) {
        $inJobIds = implode(',', array_fill(0, count($jobIds), '?'));
        $stmtDrives = $appModel->getDB()->prepare("SELECT id, job_id FROM campus_drives WHERE job_id IN ($inJobIds)");
        $stmtDrives->execute($jobIds);
        $drivesMap = []; // job_id => drive_id
        while ($row = $stmtDrives->fetch()) {
            $drivesMap[$row['job_id']] = $row['id'];
        }

        if (!empty($drivesMap)) {
            $driveIds = array_values($drivesMap);
            $inDriveIds = implode(',', array_fill(0, count($driveIds), '?'));
            
            // Get all scores per round per student for these drives
            $sqlScores = "
                SELECT drive_id, student_id, round_type, score, attempt_number
                FROM student_drive_attempts
                WHERE drive_id IN ($inDriveIds) AND score IS NOT NULL
                ORDER BY attempt_number ASC
            ";
            $stmtScores = $appModel->getDB()->prepare($sqlScores);
            $stmtScores->execute($driveIds);
            while ($row = $stmtScores->fetch()) {
                $driveScores[$row['drive_id']][$row['student_id']][$row['round_type']][] = $row['score'];
            }
        }
        
        // Attach scores to applications
        foreach ($applications as &$app) {
            $jId = $app['job_id'];
            $app['drive_scores'] = [];
            if (isset($drivesMap[$jId])) {
                $dId = $drivesMap[$jId];
                if (isset($driveScores[$dId][$app['usn']])) {
                    $app['drive_scores'] = $driveScores[$dId][$app['usn']];
                }
            }
        }
        unset($app);
    }
}

function formatResponsesForPrint($json) {
    if (empty($json)) return '';
    $data = json_decode($json, true);
    if (empty($data)) return '';
    $out = '<div class="print-responses" style="margin-top: 5px; border-top: 1px dashed #ccc; padding-top: 5px;">';
    $out .= '<div style="font-weight: bold; font-size: 11px; color: #666; margin-bottom: 3px;">CUSTOM RESPONSES:</div>';
    foreach ($data as $resp) {
        $val = $resp['value'] ?? 'N/A';
        if ($resp['type'] === 'file' && !empty($val)) $val = '[Document Uploaded]';
        $out .= '<div style="font-size: 11px; margin-bottom: 2px;">';
        $out .= '<strong>' . htmlspecialchars($resp['label']) . ':</strong> ' . htmlspecialchars($val);
        $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications - <?php echo APP_NAME; ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--text-dark);
            margin: 0;
            padding-top: 100px;
            line-height: 1.6;
        }

        .o-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .o-head {
            margin-bottom: 40px;
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

        /* Filter Glass */
        .filter-glass {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-item label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-item select, .filter-item input {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            background: #fff;
        }

        .filter-item select:focus, .filter-item input:focus {
            border-color: var(--brand);
            outline: none;
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05);
        }

        .filter-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        /* Table Design */
        .table-card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
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
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .modern-table tr:last-child td { border: none; }
        .modern-table tr:hover td { background: rgba(128, 0, 0, 0.01); }

        .job-title { font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .comp-name { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        /* Status Pills */
        .status-pill {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .st-applied { background: #eff6ff; color: #1e40af; }
        .st-shortlisted { background: #ecfdf5; color: #059669; }
        .st-selected { background: #fff7ed; color: #c2410c; }
        .st-rejected { background: #fef2f2; color: #dc2626; }

        .status-select {
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            font-weight: 600;
            background: #fff;
            cursor: pointer;
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
        .btn-excel { background: #10b981; color: white; }
        .btn-view { background: var(--brand-light); color: var(--brand); }
        .btn-view:hover { background: var(--brand); color: white; }

        .usn-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 700;
            font-size: 12px;
        }

        .pagination { display: flex; justify-content: center; gap: 8px; padding: 30px; }
        .page-link {
            padding: 10px 18px;
            border-radius: 12px;
            background: white;
            border: 1.5px solid #e2e8f0;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .page-link.active { background: var(--brand-gradient); color: white; border-color: transparent; }
        .page-link:hover:not(.active) { border-color: var(--brand); color: var(--brand); }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: white; border-radius: 24px; padding: 40px; width: 100%; max-width: 600px; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .close-modal { position: absolute; top: 25px; right: 25px; font-size: 24px; cursor: pointer; color: var(--text-muted); }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .o-page { padding: 0; }
            .table-card { border: 1px solid #eee; box-shadow: none; }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="o-page">
        <div class="o-head">
            <div>
                <h1>Application Tracker</h1>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Managing <strong><?php echo count($applications); ?></strong> student applications</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button onclick="exportToExcel()" class="btn-action btn-excel no-print">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button onclick="window.print()" class="btn-action btn-primary no-print">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <form method="POST" class="filter-glass no-print">
            <div class="filter-grid">
                <div class="filter-item">
                    <label>Semester</label>
                    <select name="semester" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                        <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $semester == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Company</label>
                    <select name="company_id" onchange="this.form.submit()">
                        <option value="">All Companies</option>
                        <?php foreach ($allCompanies as $comp): ?>
                        <option value="<?php echo $comp['id']; ?>" <?php echo $companyId == $comp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($comp['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Job Role</label>
                    <select name="job_id" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <?php foreach ($allJobs as $job): ?>
                        <option value="<?php echo $job['id']; ?>" <?php echo $jobId == $job['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($job['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Min SGPA</label>
                    <input type="number" step="0.01" name="min_sgpa" value="<?php echo htmlspecialchars($minSgpa); ?>" placeholder="e.g. 7.5">
                </div>
                <div class="filter-item">
                    <label>Min 10th %</label>
                    <input type="number" step="0.01" name="min_sslc" value="<?php echo htmlspecialchars($minSslc); ?>" placeholder="0">
                </div>
                <div class="filter-item">
                    <label>Min 12th %</label>
                    <input type="number" step="0.01" name="min_puc" value="<?php echo htmlspecialchars($minPuc); ?>" placeholder="0">
                </div>
            </div>
            <div class="filter-footer">
                <div style="font-size: 13px; color: var(--text-muted);">
                    Showing <strong><?php echo count($applications); ?></strong> applications
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="reset_filters" value="1" class="btn-action" style="background: transparent; color: var(--text-muted);">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit" class="btn-action btn-primary">
                        <i class="fas fa-check"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>

        <div class="table-card">
            <div style="overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>USN</th>
                            <th>Inst</th>
                            <th>Course</th>
                            <th>Sem</th>
                            <th>Job Role</th>
                            <th>Company</th>
                            <th>SGPA</th>
                            <th>10th %</th>
                            <th>12th %</th>
                            <th>Aptitude</th>
                            <th>Tech</th>
                            <th>HR</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $limit = 10;
                        $total_records = count($applications);
                        $total_pages = ceil($total_records / $limit);
                        $page = $filters['page'] ?? 1;
                        if ($page < 1) $page = 1;
                        $offset = ($page - 1) * $limit;
                        $pagedApps = array_slice($applications, $offset, $limit);

                        foreach ($pagedApps as $app): 
                            $statusClass = 'st-' . strtolower($app['status']);
                        ?>
                        <tr data-sems='<?php echo json_encode($allSgpas[$app['usn']] ?? []); ?>' data-resume-link="<?php echo $app['resume_path'] ? (APP_URL . '/student/view_resume.php?usn=' . urlencode($app['usn'])) : ''; ?>">
                            <td>
                                <div class="job-title"><?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div class="usn-badge"><?php echo htmlspecialchars($app['usn'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($app['institution'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($app['course'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($app['current_semester'] ?? '?'); ?></div>
                            </td>
                            <td>
                                <div class="job-title" style="font-size: 13px;"><?php echo htmlspecialchars($app['job_title'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div class="comp-name"><?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #10b981;"><?php echo $app['academic_sgpa'] ? number_format($app['academic_sgpa'], 2) : '-'; ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px;"><?php echo $app['sslc_percentage'] ? round($app['sslc_percentage']).'%' : '-'; ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px;"><?php echo $app['puc_percentage'] ? round($app['puc_percentage']).'%' : '-'; ?></div>
                            </td>
                            <td>
                                <div style="display: flex; justify-content: center;">
                                    <?php if (isset($app['drive_scores']['Aptitude'])): ?>
                                        <button type="button" class="btn-action btn-view" onclick="showAttempts('Aptitude', '<?php echo addslashes($app['student_name'] ?? 'Student'); ?>', '<?php echo htmlspecialchars(json_encode($app['drive_scores']['Aptitude']), ENT_QUOTES, 'UTF-8'); ?>')" title="View Attempts">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; justify-content: center;">
                                    <?php if (isset($app['drive_scores']['Technical'])): ?>
                                        <button type="button" class="btn-action btn-view" onclick="showAttempts('Technical', '<?php echo addslashes($app['student_name'] ?? 'Student'); ?>', '<?php echo htmlspecialchars(json_encode($app['drive_scores']['Technical']), ENT_QUOTES, 'UTF-8'); ?>')" title="View Attempts">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; justify-content: center;">
                                    <?php if (isset($app['drive_scores']['HR'])): ?>
                                        <button type="button" class="btn-action btn-view" onclick="showAttempts('HR', '<?php echo addslashes($app['student_name'] ?? 'Student'); ?>', '<?php echo htmlspecialchars(json_encode($app['drive_scores']['HR']), ENT_QUOTES, 'UTF-8'); ?>')" title="View Attempts">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 500;"><?php echo date('d M Y', strtotime($app['applied_at'])); ?></div>
                            </td>
                            <td>
                                <select class="status-select <?php echo $statusClass; ?>" onchange="updateStatus(<?php echo $app['id']; ?>, this.value)">
                                    <option value="Applied" <?php echo $app['status'] == 'Applied' ? 'selected' : ''; ?>>Applied</option>
                                    <option value="Shortlisted" <?php echo $app['status'] == 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                    <option value="Rejected" <?php echo $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="Selected" <?php echo $app['status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                </select>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <?php if ($app['resume_path']): ?>
                                        <a href="../student/view_resume.php?usn=<?php echo urlencode($app['usn']); ?>" target="_blank" class="btn-action btn-view" title="Resume">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn-action btn-view" onclick="showSgpaDetails('<?php echo $app['usn']; ?>', '<?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?>')" title="Trend">
                                        <i class="fas fa-chart-line"></i>
                                    </button>
                                    <?php if (!empty($app['custom_responses'])): ?>
                                        <button class="btn-action btn-view no-print" onclick='showResponses(<?php echo json_encode($app['custom_responses']); ?>, "<?php echo addslashes($app['student_name'] ?? ''); ?>")' title="Answers">
                                            <i class="fas fa-list-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($applications)): ?>
                        <tr><td colspan="16" style="text-align: center; padding: 60px; color: var(--text-muted);">No applications found. Try adjusting filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <form method="POST" id="paginationForm" style="display:none;"><input type="hidden" name="page" id="pageNum"></form>
            <div class="pagination">
                <?php
                if ($page > 1) { echo '<a href="javascript:void(0)" onclick="goToPage('.($page-1).')" class="page-link">&laquo;</a>'; }
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<a href="javascript:void(0)" onclick="goToPage('.$i.')" class="page-link '.$active.'">'.$i.'</a>';
                }
                if ($page < $total_pages) { echo '<a href="javascript:void(0)" onclick="goToPage('.($page+1).')" class="page-link">&raquo;</a>'; }
                ?>
            </div>
            <script>
                function goToPage(n) {
                    document.getElementById('pageNum').value = n;
                    document.getElementById('paginationForm').submit();
                }
            </script>
            <?php endif; ?>
        </div>
    </div>

            </div>
        </div>
    </div>

    <!-- Attempts Modal -->
    <div id="attemptsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="margin-bottom: 25px;">
                <h3 id="modalAttemptStudentName" style="font-weight: 800; text-transform: uppercase;">ASSESSMENT ATTEMPTS</h3>
                <span class="close-modal" onclick="closeAttemptsModal()">&times;</span>
            </div>
            <div id="attemptsContent">
                <table class="modern-table" style="box-shadow: none; border-radius: 12px; border: 1px solid #f1f5f9;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th style="font-size: 11px; letter-spacing: 1px;">ATTEMPT</th>
                            <th style="font-size: 11px; letter-spacing: 1px;">SCORE</th>
                            <th style="font-size: 11px; letter-spacing: 1px;">STATUS</th>
                        </tr>
                    </thead>
                    <tbody id="attemptsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SGPA Details Modal -->
    <div id="sgpaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="margin-bottom: 25px;">
                <h3 id="modalStudentName" style="font-weight: 800;">Academic History</h3>
                <span class="close-modal" onclick="closeSgpaModal()">&times;</span>
            </div>
            <div id="sgpaLoading" style="text-align: center; padding: 40px; display: none;">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 32px; color: var(--brand);"></i>
            </div>
            <div id="sgpaContent">
                <table class="modern-table" style="box-shadow: none; border-radius: 12px; border: 1px solid #f1f5f9;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th>Semester</th>
                            <th>SGPA</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="sgpaTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Custom Responses Modal -->
    <div id="responsesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="margin-bottom: 25px;">
                <h3 id="responseStudentName" style="font-weight: 800;">Form Responses</h3>
                <span class="close-modal" onclick="closeResponsesModal()">&times;</span>
            </div>
            <div id="responsesContainer" style="max-height: 450px; overflow-y: auto; padding-right: 10px;"></div>
            <div style="margin-top: 30px; text-align: right;">
                <button class="btn-action btn-view" onclick="closeResponsesModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        async function updateStatus(appId, newStatus) {
            try {
                const res = await fetch('application_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', application_id: appId, status: newStatus })
                });
                const data = await res.json();
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            } catch (err) {
                alert('Failed to update status.');
            }
        }

        async function showSgpaDetails(usn, name) {
            const modal = document.getElementById('sgpaModal');
            const tbody = document.getElementById('sgpaTableBody');
            document.getElementById('modalStudentName').innerText = name;
            tbody.innerHTML = '';
            modal.style.display = 'flex';
            document.getElementById('sgpaLoading').style.display = 'block';
            document.getElementById('sgpaContent').style.display = 'none';

            try {
                const res = await fetch('application_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_academic_history', student_id: usn })
                });
                const data = await res.json();
                document.getElementById('sgpaLoading').style.display = 'none';
                document.getElementById('sgpaContent').style.display = 'block';

                if (data.success && data.history) {
                    data.history.forEach(sem => {
                        tbody.innerHTML += `
                            <tr>
                                <td style="font-weight: 600;">Semester ${sem.semester}</td>
                                <td style="color: var(--brand); font-weight: 700;">${sem.sgpa}</td>
                                <td><span style="color: #059669; font-size: 11px; font-weight: 700;">VERIFIED</span></td>
                            </tr>`;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No history found.</td></tr>';
                }
            } catch (err) {
                document.getElementById('sgpaLoading').style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">Error loading history.</td></tr>';
            }
        }

        function closeSgpaModal() { document.getElementById('sgpaModal').style.display = 'none'; }

        function showResponses(json, name) {
            const modal = document.getElementById('responsesModal');
            const container = document.getElementById('responsesContainer');
            document.getElementById('responseStudentName').innerText = name;
            container.innerHTML = '';
            
            try {
                const data = JSON.parse(json);
                if (!data || data.length === 0) {
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No custom responses found.</div>';
                } else {
                    data.forEach(item => {
                        let val = item.value || '<span style="color: #ccc;">N/A</span>';
                        let extra = '';
                        if (item.type === 'file' && item.value) {
                            val = '[Document Uploaded]';
                            extra = `<br><a href="../${item.value}" target="_blank" class="btn-action btn-view" style="font-size: 11px; margin-top: 8px;"><i class="fas fa-download"></i> Download</a>`;
                        }
                        container.innerHTML += `
                            <div style="padding: 15px; border-bottom: 1px solid #f1f5f9;">
                                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">${item.label}</div>
                                <div style="font-size: 14px; color: var(--text-dark); font-weight: 500; margin-top: 4px;">${val}${extra}</div>
                            </div>`;
                    });
                }
            } catch (e) {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc2626;">Error parsing responses.</div>';
            }
            modal.style.display = 'flex';
        }

        function closeResponsesModal() { document.getElementById('responsesModal').style.display = 'none'; }

        function showAttempts(type, studentName, scoresJson) {
            const scores = JSON.parse(scoresJson);
            const tbody = document.getElementById('attemptsTableBody');
            document.getElementById('modalAttemptStudentName').innerText = studentName;
            
            tbody.innerHTML = '';
            
            if (scores.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No attempts found.</td></tr>';
            } else {
                scores.forEach((score, index) => {
                    const scoreNum = parseFloat(score);
                    const color = scoreNum >= 60 ? 'var(--brand)' : '#800000';
                    tbody.innerHTML += `
                        <tr>
                            <td style="font-weight: 600; color: #334155;">Attempt ${index + 1}</td>
                            <td style="color: ${color}; font-weight: 800; font-size: 14px;">${scoreNum.toFixed(2)}%</td>
                            <td><span style="color: #059669; font-size: 11px; font-weight: 700;">COMPLETED</span></td>
                        </tr>`;
                });
            }
            
            document.getElementById('attemptsModal').style.display = 'flex';
        }
        
        function closeAttemptsModal() {
            document.getElementById('attemptsModal').style.display = 'none';
        }

        function exportToExcel() {
            const table = document.querySelector(".modern-table");
            const rows = Array.from(table.rows);
            const rawData = rows.map((row, rowIndex) => {
                const cells = Array.from(row.cells);
                const rowData = cells.map(cell => cell.innerText.split('\n')[0].trim());
                if (rowIndex === 0) {
                    rowData.push("Resume Link");
                } else {
                    const resumeLink = row.getAttribute("data-resume-link") || "";
                    rowData.push(resumeLink);
                }
                return rowData;
            });
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(rawData);
            
            // Format resume links as clickable hyperlinks
            const colIndex = rawData[0].length - 1;
            for (let r = 1; r < rawData.length; r++) {
                const url = rawData[r][colIndex];
                if (url && url !== "No Resume" && url.startsWith("http")) {
                    const cellAddress = XLSX.utils.encode_cell({ c: colIndex, r: r });
                    if (ws[cellAddress]) {
                        ws[cellAddress].l = { Target: url, Tooltip: "Click to open resume" };
                    }
                }
            }
            
            XLSX.utils.book_append_sheet(wb, ws, "Applications");
            XLSX.writeFile(wb, `Applications_${new Date().toISOString().split('T')[0]}.xlsx`);
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeSgpaModal();
                closeResponsesModal();
                closeAttemptsModal();
            }
        }
    </script>
</body>
</html>
</body>
</html>

