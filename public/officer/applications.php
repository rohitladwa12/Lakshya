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
    $student = $userModel->find($app['student_id']);
    
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
                $stmtAc = $remoteDB->prepare("SELECT a.sgpa, a.course, d.puc_percentage, d.sslc_percentage, a.sem, d.gender
                                           FROM {$prefix}ad_student_approved a
                                           LEFT JOIN {$prefix}ad_student_details d ON a.usn = d.student_id 
                                           WHERE a.usn = ? LIMIT 1");
                $stmtAc->execute([$app['usn']]);
                $ac = $stmtAc->fetch();
                if ($ac) {
                    $app['academic_sgpa'] = $ac['sgpa'];
                    $app['course'] = $ac['course'];
                    $app['puc_percentage'] = $ac['puc_percentage'];
                    $app['sslc_percentage'] = $ac['sslc_percentage'];
                    $app['current_semester'] = $ac['sem'];
                    if (!empty($ac['gender'])) $app['gender'] = $ac['gender'];
                }
            } else {
                // GMIT: Fetch academic history from local SGPA tracker
                $stmtAc = $appModel->getDB()->prepare("SELECT sgpa, semester FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                $stmtAc->execute([$app['usn'], INSTITUTION_GMIT]);
                $ac = $stmtAc->fetch();
                if ($ac) {
                    $app['academic_sgpa'] = $ac['sgpa'];
                    $app['current_semester'] = $ac['semester'];
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
$studentUsns = array_unique(array_filter(array_column($applications, 'usn')));
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
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --sidebar-width: 260px;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        body { font-family: 'Inter', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; display: flex; flex-direction: column; min-height: 100vh; }
        
        .main-content {
            /* Layout handled by navbar.php */
        }
        table { width: 100%; border-collapse: collapse; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .filters-bar { background: var(--white); padding: 20px; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 12px; font-weight: bold; color: #666; }
        .form-control { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }

        .table-card { background: var(--white); border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 24px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1300px; }
        th { text-align: left; padding: 12px 15px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 13px; color: #334155; }

        .student-name { font-weight: 600; color: var(--primary-maroon); font-size: 14px; }
        
        .status-select { width: 110px; padding: 6px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; border: 1px solid #e2e8f0; }
        .status-Applied { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .status-Shortlisted { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .status-Rejected { background: #fef2f2; color: #991b1b; border-color: #fee2e2; }
        .status-Selected { background: #fffbeb; color: #92400e; border-color: #fde68a; }

        .btn-action { padding: 5px 10px; border: 1px solid var(--primary-maroon); border-radius: 6px; text-decoration: none; color: var(--primary-maroon); font-size: 11px; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; background: white; cursor: pointer; }
        .btn-action:hover { background: var(--primary-maroon); color: white; }

        .pagination { display: flex; justify-content: center; align-items: center; padding: 20px; gap: 5px; }
        .page-link { padding: 8px 16px; min-width: 40px; text-align: center; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: #475569; font-size: 14px; background: white; transition: all 0.2s; font-weight: 500; }
        .page-link:hover { background: #f8fafc; border-color: var(--primary-maroon); color: var(--primary-maroon); }
        .page-link.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); box-shadow: 0 4px 12px rgba(128,0,0,0.2); }
        .page-link.disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; pointer-events: none; }


        /* SGPA Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 500px; max-width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
        .sgpa-table { width: 100%; margin-top: 15px; }
        .sgpa-table th { background: #f8f9fa; font-size: 12px; }
        .sgpa-table td { font-size: 14px; text-align: center; }
        .view-sgpa-btn { color: var(--primary-maroon); cursor: pointer; text-decoration: underline; font-weight: 600; }

        @media print {
            .filters-bar, .btn-action, .view-sgpa-btn, .status-select, .close-modal, .header a, .includes-navbar, nav, .sidebar {
                display: none !important;
            }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .main-content { padding: 0; margin: 0; width: 100%; max-width: 100%; }
            .table-card { box-shadow: none; border: 1px solid #eee; }
            th, td { border-bottom: 1px solid #eee !important; }
            body { background: white; }
            .header h2 { margin-bottom: 20px; color: black; }
        }
        .print-only { display: none; }

        /* Print Responses Styling */
        .print-responses { display: none; }
        @media print {
            .print-responses { display: block !important; }
            .btn-view-responses { display: none !important; }
        }

        /* Responses Modal Specific */
        .response-item {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        .response-item:last-child { border-bottom: none; }
        .response-label {
            font-size: 12px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .response-value {
            font-size: 14px;
            color: var(--text-dark);
            font-weight: 500;
        }
        .btn-download-small {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            background: #fff5f5;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header">
            <div>
                <h2>Track Applications</h2>
                <div style="font-size: 14px; color: #666;">Total: <?php echo count($applications); ?></div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="exportToExcel()" class="btn-action" style="background: #27ae60; color: white; border: none; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
                <button onclick="window.print()" class="btn-action" style="background: var(--primary-maroon); color: white; border: none; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <form method="POST" class="filters-bar" style="flex-wrap: wrap; gap: 15px;">
            <div class="filter-group">
                <label>Sem</label>
                <select name="semester" class="form-control" style="width: 80px;" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php for($i=1; $i<=8; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $semester == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Company</label>
                <select name="company_id" class="form-control" style="width: 150px;" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($allCompanies as $comp): ?>
                    <option value="<?php echo $comp['id']; ?>" <?php echo $companyId == $comp['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($comp['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Job Role</label>
                <select name="job_id" class="form-control" style="width: 150px;" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($allJobs as $job): ?>
                    <option value="<?php echo $job['id']; ?>" <?php echo $jobId == $job['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($job['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Min SGPA</label>
                <input type="number" step="0.01" name="min_sgpa" value="<?php echo htmlspecialchars($minSgpa); ?>" class="form-control" style="width: 80px;" placeholder="0.00">
            </div>
            <div class="filter-group">
                <label>Min 10th %</label>
                <input type="number" step="0.01" name="min_sslc" value="<?php echo htmlspecialchars($minSslc); ?>" class="form-control" style="width: 80px;" placeholder="0">
            </div>
            <div class="filter-group">
                <label>Min 12th %</label>
                <input type="number" step="0.01" name="min_puc" value="<?php echo htmlspecialchars($minPuc); ?>" class="form-control" style="width: 80px;" placeholder="0">
            </div>
            <div class="filter-group" style="flex-direction: row; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn-action" style="padding: 10px 15px; background: #eee; color: #444; border-color: #ddd;">Apply</button>
                <button type="submit" name="reset_filters" value="1" class="btn-action" style="padding: 10px 15px; border: none; color: #888; background: transparent;">Clear</button>
            </div>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 120px;">USN</th>
                        <th style="width: 180px;">Student Name</th>
                        <th style="width: 80px;">Gender</th>
                        <th style="width: 60px;">Inst.</th>
                        <th style="width: 60px;">Sem</th>
                        <th style="width: 120px;">Course</th>
                        <th style="width: 150px;">Company</th>
                        <th style="width: 150px;">Job Role</th>
                        <th style="width: 80px;">10th %</th>
                        <th style="width: 80px;">12th %</th>
                        <th style="width: 120px;">Applied Date</th>
                        <th style="width: 130px;">Status</th>
                        <th style="width: 150px;">Actions</th>
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
                    ?>
                    <tr data-sems='<?php echo json_encode($allSgpas[$app['usn']] ?? []); ?>'>
                        <td style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($app['usn'] ?? 'N/A'); ?></td>
                        <td><div class="student-name"><?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?></div></td>
                        <td><?php echo htmlspecialchars($app['gender'] ?? '-'); ?></td>
                        <td><span class="round-tag" style="font-size: 10px;"><?php echo $app['institution']; ?></span></td>
                        <td style="font-weight: 700; color: var(--primary-maroon);"><?php echo $currentSems[$app['usn']] ?? 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($app['course'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($app['job_title'] ?? 'N/A'); ?></td>
                        <td style="font-weight: 600; color: #059669;"><?php echo $app['sslc_percentage'] ? $app['sslc_percentage'].'%' : '-'; ?></td>
                        <td style="font-weight: 600; color: #059669;"><?php echo $app['puc_percentage'] ? $app['puc_percentage'].'%' : '-'; ?></td>
                        <td><?php echo date('d M Y', strtotime($app['applied_at'])); ?></td>
                        <td>
                            <select class="status-select status-<?php echo $app['status']; ?>" onchange="updateStatus(<?php echo $app['id']; ?>, this.value)">
                                <option value="Applied" <?php echo $app['status'] == 'Applied' ? 'selected' : ''; ?>>Applied</option>
                                <option value="Shortlisted" <?php echo $app['status'] == 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="Rejected" <?php echo $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="Selected" <?php echo $app['status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                            </select>
                        </td>
                        <td>
                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                <?php if ($app['resume_path']): ?>
                                    <a href="../<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn-action" title="View Resume">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php endif; ?>
                                <button class="btn-action" onclick="showSgpaDetails('<?php echo $app['usn']; ?>', '<?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?>')" title="Visual SGPAs">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                                <?php if (!empty($app['custom_responses'])): ?>
                                    <button class="btn-action no-print" onclick='showResponses(<?php echo json_encode($app['custom_responses']); ?>, "<?php echo addslashes($app['student_name'] ?? ''); ?>")' title="View Answers">
                                        <i class="fas fa-list-check"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($applications)): ?>
                    <tr><td colspan="13" style="text-align: center; padding: 40px; color: #94a3b8;">No applications found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

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

    <!-- SGPA Details Modal -->
    <div id="sgpaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalStudentName">Semester-wise SGPA</h3>
                <button class="close-modal" onclick="closeSgpaModal()">&times;</button>
            </div>
            <div id="sgpaLoading" style="text-align: center; padding: 20px; display: none;">
                <div class="spinner">Loading...</div>
            </div>
            <div id="sgpaContent">
                <table class="sgpa-table">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>SGPA</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="sgpaTableBody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Custom Responses Modal -->
    <div id="responsesModal" class="modal">
        <div class="modal-content" style="width: 600px;">
            <div class="modal-header">
                <h3 id="responseStudentName">Application Details</h3>
                <button class="close-modal" onclick="closeResponsesModal()">&times;</button>
            </div>
            <div id="responsesContainer" style="max-height: 400px; overflow-y: auto;">
                <!-- Responses will be loaded here -->
            </div>
            <div style="margin-top: 25px; text-align: right;">
                <button class="btn-action" onclick="closeResponsesModal()">Close</button>
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
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Failed to update status.');
            }
        }

        async function showSgpaDetails(usn, name) {
            const modal = document.getElementById('sgpaModal');
            const tbody = document.getElementById('sgpaTableBody');
            const nameDisplay = document.getElementById('modalStudentName');
            
            nameDisplay.innerText = "Academic History: " + name;
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
                        const row = `
                            <tr>
                                <td>Semester ${sem.semester}</td>
                                <td><strong>${sem.sgpa}</strong></td>
                                <td>Success</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3">No history found for this student.</td></tr>';
                }
            } catch (err) {
                document.getElementById('sgpaLoading').style.display = 'none';
                document.getElementById('sgpaContent').style.display = 'block';
                tbody.innerHTML = '<tr><td colspan="3">Error loading academic history.</td></tr>';
            }
        }

        function closeSgpaModal() {
            document.getElementById('sgpaModal').style.display = 'none';
        }

        function showResponses(json, name) {
            const modal = document.getElementById('responsesModal');
            const container = document.getElementById('responsesContainer');
            const nameHeader = document.getElementById('responseStudentName');
            
            nameHeader.innerText = "Custom Answers: " + name;
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
                            extra = `<br><a href="../${item.value}" target="_blank" class="btn-download-small"><i class="fas fa-download"></i> Download File</a>`;
                        } else if (item.type === 'yesno') {
                             val = `<span style="color: ${item.value === 'Yes' ? '#00875a' : '#de350b'}; font-weight: bold;">${item.value}</span>`;
                        }

                        container.innerHTML += `
                            <div class="response-item">
                                <div class="response-label">${item.label}</div>
                                <div class="response-value">${val}${extra}</div>
                            </div>
                        `;
                    });
                }
            } catch (e) {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #de350b;">Error parsing responses.</div>';
            }
            
            modal.style.display = 'flex';
        }

        function closeResponsesModal() {
            document.getElementById('responsesModal').style.display = 'none';
        }

        function exportToExcel() {
            const table = document.querySelector("table");
            const rows = Array.from(table.rows);
            
            // Step 1: Prepare Raw Data (with all 8 semesters)
            const rawData = rows.map((row, rowIndex) => {
                const cells = Array.from(row.cells);
                
                // Keep base columns (skipping Actions at index 12)
                let rowData = cells.slice(0, -1).map((cell, cellIndex) => {
                    let val = cell.innerText.trim();
                    if (rowIndex > 0 && cellIndex === 11) { // Status select
                        const select = cell.querySelector('select');
                        val = select ? select.options[select.selectedIndex].text : val;
                    }
                    return val;
                });

                // Insert All 8 Semester Values after index 4 (before Sem column)
                if (rowIndex === 0) {
                    rowData.splice(5, 0, "Sem 1", "Sem 2", "Sem 3", "Sem 4", "Sem 5", "Sem 6", "Sem 7", "Sem 8");
                } else {
                    const semsJson = row.getAttribute('data-sems');
                    let sems = {};
                    try { sems = JSON.parse(semsJson) || {}; } catch(e) {}
                    const semValues = [];
                    for (let i = 1; i <= 8; i++) {
                        let sVal = sems[i] || "";
                        if (sVal == "0.00" || sVal == 0 || sVal == "N/A" || sVal == "na") sVal = "";
                        semValues.push(sVal);
                    }
                    rowData.splice(5, 0, ...semValues);
                }
                return rowData;
            });

            // Step 2: Determine which semester columns have ANY data
            const activeSemIndices = [];
            for (let i = 0; i < 8; i++) {
                const colIndex = 6 + i;
                let hasRealData = false;
                for (let r = 1; r < rawData.length; r++) {
                    const val = rawData[r][colIndex];
                    if (val && val !== "na" && val !== "") {
                        hasRealData = true;
                        break;
                    }
                }
                if (hasRealData) activeSemIndices.push(i);
            }

            // Step 3: Filter Raw Data to include only active semesters
            const finalData = rawData.map(row => {
                const prefix = row.slice(0, 6);
                const semesters = activeSemIndices.map(idx => row[6 + idx]);
                const suffix = row.slice(14);
                return [...prefix, ...semesters, ...suffix];
            });

            // Create Workbook
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(finalData);
            
            // Set Dynamic Column Widths
            const wscols = [
                {wch: 15}, {wch: 25}, {wch: 6}, {wch: 12}, {wch: 20}, {wch: 25} // Base
            ];
            activeSemIndices.forEach(() => wscols.push({wch: 8})); // Sems
            wscols.push({wch: 8}, {wch: 8}, {wch: 15}, {wch: 15}, {wch: 40}); // Suffix
            ws['!cols'] = wscols;

            XLSX.utils.book_append_sheet(wb, ws, "Applications");
            const filename = `Placement_Applications_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, filename);
        }

        window.onclick = function(event) {
            const sgpaModal = document.getElementById('sgpaModal');
            const respModal = document.getElementById('responsesModal');
            if (event.target == sgpaModal) closeSgpaModal();
            if (event.target == respModal) closeResponsesModal();
        }
    </script>
</body>
</html>
