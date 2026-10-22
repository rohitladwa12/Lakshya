<?php
/**
 * Placement Officer - CASP Placement Policy & Opt-Out Undertakings Intelligence
 * Detailed Table Format with 100% Separate Columns for Every Field
 * Strictly Displays Students Who Have Responded / Completed Decision
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

require_once ROOT_PATH . '/src/Helpers/PlacementInterestHelper.php';
require_once ROOT_PATH . '/src/Helpers/SessionFilterHelper.php';
use App\Helpers\PlacementInterestHelper;
use App\Helpers\SessionFilterHelper;

$pageId = 'officer_casp_undertakings';

// 1. Handle Filter Form Submissions (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    if (isset($_POST['reset_filters'])) {
        SessionFilterHelper::clearFilters($pageId);
    } else {
        $updates = $_POST;
        if (!isset($updates['page'])) {
            $updates['page'] = 1;
        }
        SessionFilterHelper::updateFilters($pageId, $updates);
    }
    header("Location: casp_undertakings.php");
    exit;
}

$filters = SessionFilterHelper::getFilters($pageId);

// Handle GET state updates for direct links or pagination
if (isset($_GET['status'])) {
    $filters['placement_status'] = $_GET['status'];
    $filters['page'] = 1;
    SessionFilterHelper::setFilters($pageId, $filters);
}
if (isset($_GET['inst'])) {
    $filters['institution'] = $_GET['inst'];
    $filters['page'] = 1;
    SessionFilterHelper::setFilters($pageId, $filters);
}
if (isset($_GET['page'])) {
    $filters['page'] = max(1, (int)$_GET['page']);
    SessionFilterHelper::setFilters($pageId, $filters);
}

$page = max(1, (int)($filters['page'] ?? 1));
$limit = 25;

$officerModel = new PlacementOfficer();

// 2. Fetch Disciplines for dropdown
try {
    $disciplines = $officerModel->getDisciplines() ?: [];
} catch (\Throwable $e) {
    error_log("Error fetching disciplines: " . $e->getMessage());
    $disciplines = [];
}

// 3. Load CASP Placement Registry
$placementStatusMap = PlacementInterestHelper::getStatusMap();

// Calculate Global KPI Summary Counters (Only Students Who Responded / Done It)
$countPolicySigned = 0;
$countOptOut = 0;
$countPolicyPending = 0;

$policySignedUsns = [];
$optOutUsns = [];
$policyPendingUsns = [];
$allRespondedUsns = [];

foreach ($placementStatusMap as $u => $st) {
    $allRespondedUsns[] = $u;
    if (!empty($st['opt_out'])) {
        $countOptOut++;
        $optOutUsns[] = $u;
    } elseif (!empty($st['has_policy_pdf'])) {
        $countPolicySigned++;
        $policySignedUsns[] = $u;
    } elseif (!empty($st['interested'])) {
        $countPolicyPending++;
        $policyPendingUsns[] = $u;
    }
}

$totalResponded = count($allRespondedUsns);

// 4. Build Query Filters for Database
$queryFilters = [];
if (!empty($filters['institution'])) {
    $queryFilters['institution'] = $filters['institution'];
}
if (!empty($filters['discipline'])) {
    $queryFilters['discipline'] = $filters['discipline'];
}
if (!empty($filters['semester'])) {
    $queryFilters['semester'] = (int)$filters['semester'];
}
if (!empty($filters['search'])) {
    $queryFilters['search'] = trim($filters['search']);
}

// Placement Status Filter - DEFAULT: Strictly show ONLY students who have done/responded
$statusFilter = $filters['placement_status'] ?? 'done';

if ($statusFilter === 'policy_signed') {
    $queryFilters['usn_list'] = !empty($policySignedUsns) ? $policySignedUsns : ['__NONE__'];
} elseif ($statusFilter === 'opt_out') {
    $queryFilters['usn_list'] = !empty($optOutUsns) ? $optOutUsns : ['__NONE__'];
} elseif ($statusFilter === 'policy_pending') {
    $queryFilters['usn_list'] = !empty($policyPendingUsns) ? $policyPendingUsns : ['__NONE__'];
} elseif ($statusFilter === 'all_college') {
    // Show all students in the college database (including non-responders)
} else {
    // Default 'done' / all responded: ONLY students who have responded/completed decision
    $queryFilters['usn_list'] = !empty($allRespondedUsns) ? $allRespondedUsns : ['__NONE__'];
}

// 5. Handle Excel Export (Matching 100% Separate Columns)
if (isset($filters['export']) && $filters['export'] == '1') {
    SessionFilterHelper::updateFilters($pageId, ['export' => null]);
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="casp_undertakings_report_' . date('Y-m-d') . '.xls"');
    
    $exportData = $officerModel->getStudentsPaged($queryFilters, 1, 10000);
    $exportStudents = $exportData['data'] ?? [];
    
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr style="background:#7C0000; color:#FFFFFF; font-weight:bold;">';
    echo '<th>#</th>';
    echo '<th>Institution</th>';
    echo '<th>USN</th>';
    echo '<th>Student Name</th>';
    echo '<th>Branch / Discipline</th>';
    echo '<th>Semester</th>';
    echo '<th>Placement Status</th>';
    echo '<th>Undertaking Link</th>';
    echo '<th>Resume Link</th>';
    echo '<th>10th %</th>';
    echo '<th>12th %</th>';
    echo '<th>Student Mobile</th>';
    echo '<th>Student Email</th>';
    echo '<th>Father Name</th>';
    echo '<th>Mother Name</th>';
    echo '<th>Parent Mobile</th>';
    echo '<th>Submission Date</th>';
    echo '</tr>';
    echo '</thead><tbody>';

    $exportSl = 1;
    foreach ($exportStudents as $s) {
        $uUpper = strtoupper(trim($s['usn'] ?? ''));
        $st = $placementStatusMap[$uUpper] ?? null;

        if ($st && !empty($st['opt_out'])) {
            $statusText = 'Not Interested (Opt-Out)';
            $statusColor = 'background:#FEE2E2; color:#991B1B; font-weight:bold;';
        } elseif ($st && !empty($st['has_policy_pdf'])) {
            $statusText = 'Interested (Policy Signed)';
            $statusColor = 'background:#ECFDF5; color:#065F46; font-weight:bold;';
        } elseif ($st && !empty($st['interested'])) {
            $statusText = 'Interested (Policy Pending)';
            $statusColor = 'background:#EFF6FF; color:#1E40AF; font-weight:600;';
        } else {
            $statusText = 'Active / Unspecified';
            $statusColor = 'color:#6B7280;';
        }

        $token = function_exists('generateResumeToken') ? generateResumeToken($s['usn']) : '';
        $resumeUrl = APP_URL . '/student/view_resume.php?usn=' . urlencode($s['usn']) . ($token ? '&token=' . $token : '');
        $resumeLink = '=HYPERLINK("' . $resumeUrl . '", "View Resume")';

        $undertakingLink = 'No Undertaking';
        if ($st && (!empty($st['has_opt_out_pdf']) || !empty($st['has_policy_pdf']))) {
            $uType = !empty($st['has_policy_pdf']) ? 'policy' : 'opt_out';
            $undertakingUrl = APP_URL . '/student/view_undertaking.php?usn=' . urlencode($s['usn']) . '&type=' . $uType . ($token ? '&token=' . $token : '');
            $undertakingLink = '=HYPERLINK("' . $undertakingUrl . '", "' . (!empty($st['has_policy_pdf']) ? 'View Signed Policy' : 'View Opt-Out') . '")';
        }

        $submittedDate = !empty($st['updated_at']) ? date('Y-m-d H:i', strtotime($st['updated_at'])) : '-';

        echo '<tr>';
        echo '<td>' . $exportSl++ . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['institution'] ?? '')) . '</td>';
        echo '<td style="mso-number-format:\'@\';">' . htmlspecialchars((string)($s['usn'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['discipline'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['sem'] ?? '')) . '</td>';
        echo '<td style="' . $statusColor . '">' . $statusText . '</td>';
        echo '<td>' . $undertakingLink . '</td>';
        echo '<td>' . $resumeLink . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['sslc_percentage'] ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['puc_percentage'] ?? '-')) . '</td>';
        echo '<td style="mso-number-format:\'@\';">' . htmlspecialchars((string)($s['mobile'] ?? ($s['phone'] ?? '-'))) . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['email'] ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['father_name'] ?? '-')) . '</td>';
        echo '<td>' . htmlspecialchars((string)($s['mother_name'] ?? '-')) . '</td>';
        echo '<td style="mso-number-format:\'@\';">' . htmlspecialchars((string)($s['parent_mobile'] ?? '-')) . '</td>';
        echo '<td>' . $submittedDate . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
}

// 6. Fetch Paged Results
$data = $officerModel->getStudentsPaged($queryFilters, $page, $limit);
$students = $data['data'] ?? [];
$totalRecords = $data['total'] ?? 0;
$totalPages = max(1, (int)ceil($totalRecords / $limit));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASP Undertakings &amp; Placement Policy Intelligence - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --brand: #7C0000;
            --brand-dark: #5B0000;
            --brand-light: #FDF2F2;
            --gold: #B08D2C;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --border: #E2E8F0;
            --border-subtle: #F1F5F9;
            --surface: #FFFFFF;
            --bg: #F8FAFC;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07);
        }

        *, *::before, *::after { box-sizing: border-box; }
        body {
            background-color: var(--bg);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }

        .main-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 24px 30px 60px;
        }

        /* Top Header Box */
        .page-header {
            background: var(--surface);
            padding: 20px 24px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title-box h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 21px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: var(--brand);
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title-box p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .btn-export {
            background: #059669;
            color: #FFFFFF;
            padding: 9px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2);
        }
        .btn-export:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        /* KPI Metric Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: var(--surface);
            padding: 16px 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.15s ease;
        }
        .kpi-card:hover {
            border-color: #CBD5E1;
            box-shadow: var(--shadow-md);
        }

        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            flex-shrink: 0;
        }

        .kpi-icon--maroon { background: #FDF2F2; color: #7C0000; border: 1px solid #FECACA; }
        .kpi-icon--green  { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon--blue   { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .kpi-icon--red    { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

        .kpi-details {
            display: flex;
            flex-direction: column;
        }

        .kpi-val {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
        }

        .kpi-lbl {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-secondary);
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .kpi-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Filter Card */
        .filter-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }

        .filter-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-select, .filter-input {
            padding: 7px 11px;
            font-size: 13px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: #FFFFFF;
            color: var(--text-primary);
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s;
        }

        .filter-select:focus, .filter-input:focus {
            border-color: var(--brand);
        }

        .filter-btn-search {
            background: var(--brand);
            color: #FFFFFF;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-btn-search:hover { background: var(--brand-dark); }

        .filter-btn-reset {
            background: #F1F5F9;
            color: var(--text-secondary);
            padding: 7px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-btn-reset:hover { background: #E2E8F0; color: var(--text-primary); }

        /* Data Table - 100% Separate Columns */
        .table-wrap {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            overflow-x: auto;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            text-align: left;
            min-width: 1750px;
            white-space: nowrap;
        }

        .report-table th {
            background: #F8FAFC;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            border-right: 1px solid var(--border-subtle);
        }

        .report-table td {
            padding: 11px 14px;
            border-bottom: 1px solid #F1F5F9;
            border-right: 1px solid #F8FAFC;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .report-table tr:hover td {
            background: #F8FAFC;
        }

        .candidate-usn {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 700;
            color: #0F172A;
        }

        /* Institution Tags */
        .inst-tag {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
            display: inline-block;
        }
        .inst-tag--gmu  { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .inst-tag--gmit { background: #F5F3FF; color: #5B21B6; border: 1px solid #DDD6FE; }

        /* Status Badges */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
        }
        .badge-status--signed  { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .badge-status--optout  { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .badge-status--pending { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .badge-status--gray    { background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

        /* Action Buttons */
        .btn-post-action {
            padding: 4px 10px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 5px;
            border: 1px solid var(--border);
            background: #FFFFFF;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
        }
        .btn-post-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn-post-action--policy {
            background: #ECFDF5;
            color: #065F46;
            border-color: #A7F3D0;
        }
        .btn-post-action--policy:hover {
            background: #D1FAE5;
        }

        .btn-post-action--optout {
            background: #FEF2F2;
            color: #991B1B;
            border-color: #FECACA;
        }
        .btn-post-action--optout:hover {
            background: #FEE2E2;
        }

        .btn-post-action--resume {
            color: #1E293B;
            border-color: #CBD5E1;
        }
        .btn-post-action--resume:hover {
            background: #F1F5F9;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 8px 4px;
        }

        .pagination-info {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-link-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            background: #FFFFFF;
            color: var(--text-primary);
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s;
        }
        .page-link-btn:hover { background: #F1F5F9; }
        .page-link-btn.active {
            background: var(--brand);
            color: #FFFFFF;
            border-color: var(--brand);
        }

        .empty-box {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-muted);
        }
        .empty-box i { font-size: 36px; margin-bottom: 10px; opacity: 0.6; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="main-container">
    <!-- Top Header Box -->
    <div class="page-header">
        <div class="header-title-box">
            <h1><i class="fas fa-file-contract"></i> CASP Placement Policy &amp; Undertakings</h1>
            <p>Direct tracking and verification of student undertaking submissions &amp; opt-out declarations</p>
        </div>
        <div>
            <form method="POST" action="casp_undertakings.php" style="margin: 0;">
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="institution" value="<?php echo htmlspecialchars($filters['institution'] ?? ''); ?>">
                <input type="hidden" name="discipline" value="<?php echo htmlspecialchars($filters['discipline'] ?? ''); ?>">
                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($filters['semester'] ?? ''); ?>">
                <input type="hidden" name="placement_status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                <button type="submit" class="btn-export">
                    <i class="fas fa-file-excel"></i> Export to Excel (.xls)
                </button>
            </form>
        </div>
    </div>

    <!-- KPI Metric Cards Grid - Only Submissions / Done Counts -->
    <div class="kpi-grid">
        <!-- 1. Total Responded / Submitted -->
        <div class="kpi-card" style="border-left: 4px solid var(--brand);">
            <div class="kpi-icon kpi-icon--maroon"><i class="fas fa-clipboard-check"></i></div>
            <div class="kpi-details">
                <div class="kpi-val"><?php echo number_format($totalResponded); ?></div>
                <div class="kpi-lbl">Total Submissions</div>
                <div class="kpi-sub">Students who completed action</div>
            </div>
        </div>

        <!-- 2. Policy Signed (Interested) -->
        <div class="kpi-card" style="border-left: 4px solid #059669;">
            <div class="kpi-icon kpi-icon--green"><i class="fas fa-file-signature"></i></div>
            <div class="kpi-details">
                <div class="kpi-val" style="color: #065F46;"><?php echo number_format($countPolicySigned); ?></div>
                <div class="kpi-lbl">Policy Signed (Interested)</div>
                <div class="kpi-sub">Document signed &amp; verified</div>
            </div>
        </div>

        <!-- 3. Policy Pending -->
        <div class="kpi-card" style="border-left: 4px solid #2563EB;">
            <div class="kpi-icon kpi-icon--blue"><i class="fas fa-clock"></i></div>
            <div class="kpi-details">
                <div class="kpi-val" style="color: #1E40AF;"><?php echo number_format($countPolicyPending); ?></div>
                <div class="kpi-lbl">Policy Pending</div>
                <div class="kpi-sub">Opted in, signature pending</div>
            </div>
        </div>

        <!-- 4. Opt-Out Declarations -->
        <div class="kpi-card" style="border-left: 4px solid #DC2626;">
            <div class="kpi-icon kpi-icon--red"><i class="fas fa-ban"></i></div>
            <div class="kpi-details">
                <div class="kpi-val" style="color: #991B1B;"><?php echo number_format($countOptOut); ?></div>
                <div class="kpi-lbl">Opt-Out Declarations</div>
                <div class="kpi-sub">Non-participation declared</div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="POST" action="casp_undertakings.php" class="filter-form">
            <!-- Placement Status Filter -->
            <select name="placement_status" class="filter-select" onchange="this.form.submit()">
                <option value="done" <?php echo $statusFilter === 'done' ? 'selected' : ''; ?>>All Submissions / Responded (<?php echo $totalResponded; ?>)</option>
                <option value="policy_signed" <?php echo $statusFilter === 'policy_signed' ? 'selected' : ''; ?>>Policy Signed (<?php echo $countPolicySigned; ?>)</option>
                <option value="policy_pending" <?php echo $statusFilter === 'policy_pending' ? 'selected' : ''; ?>>Policy Pending (<?php echo $countPolicyPending; ?>)</option>
                <option value="opt_out" <?php echo $statusFilter === 'opt_out' ? 'selected' : ''; ?>>Opt-Out Declarations (<?php echo $countOptOut; ?>)</option>
                <option value="all_college" <?php echo $statusFilter === 'all_college' ? 'selected' : ''; ?>>All College Students (Include Inactive)</option>
            </select>

            <!-- Institution Filter -->
            <select name="institution" class="filter-select" onchange="this.form.submit()">
                <option value="">All Institutions</option>
                <option value="GMU" <?php echo ($filters['institution'] ?? '') === 'GMU' ? 'selected' : ''; ?>>GMU</option>
                <option value="GMIT" <?php echo ($filters['institution'] ?? '') === 'GMIT' ? 'selected' : ''; ?>>GMIT</option>
            </select>

            <!-- Discipline Filter -->
            <select name="discipline" class="filter-select" style="max-width: 220px;" onchange="this.form.submit()">
                <option value="">All Disciplines / Branches</option>
                <?php foreach ($disciplines as $disc): ?>
                    <option value="<?php echo htmlspecialchars($disc); ?>" <?php echo ($filters['discipline'] ?? '') === $disc ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($disc); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Semester Filter -->
            <select name="semester" class="filter-select" onchange="this.form.submit()">
                <option value="">All Semesters</option>
                <?php foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $sm): ?>
                    <option value="<?php echo $sm; ?>" <?php echo (int)($filters['semester'] ?? 0) === $sm ? 'selected' : ''; ?>>
                        Semester <?php echo $sm; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Search Field -->
            <input type="text" name="search" class="filter-input" placeholder="Search USN, Name, Mobile..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" style="width: 220px;">

            <button type="submit" class="filter-btn-search">
                <i class="fas fa-search"></i> Filter
            </button>

            <button type="submit" name="reset_filters" value="1" class="filter-btn-reset">
                <i class="fas fa-rotate-left"></i> Reset
            </button>
        </form>
    </div>

    <!-- Detailed Table - 100% Separate Columns for Every Item -->
    <div class="table-wrap">
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th style="width: 70px;">Inst</th>
                    <th style="width: 120px;">USN</th>
                    <th>Student Name</th>
                    <th>Branch</th>
                    <th style="width: 50px; text-align: center;">Sem</th>
                    <th style="width: 180px;">Placement Status</th>
                    <th style="width: 130px; text-align: center;">Undertaking PDF</th>
                    <th style="width: 110px; text-align: center;">Resume</th>
                    <th style="width: 65px; text-align: center;">10th %</th>
                    <th style="width: 65px; text-align: center;">12th %</th>
                    <th>Student Mobile</th>
                    <th>Student Email</th>
                    <th>Father Name</th>
                    <th>Mother Name</th>
                    <th>Parent Mobile</th>
                    <th style="width: 130px;">Submitted On</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): 
                    $rowSl = ($page - 1) * $limit + 1;
                    foreach ($students as $s): 
                        $uUpper = strtoupper(trim($s['usn'] ?? ''));
                        $st = $placementStatusMap[$uUpper] ?? null;
                        $isGmit = ($s['institution'] ?? '') === INSTITUTION_GMIT;
                        $submittedOn = (!empty($st['updated_at'])) ? date('d M Y, H:i', strtotime($st['updated_at'])) : '-';
                ?>
                <tr>
                    <!-- 1. Serial Number -->
                    <td style="text-align: center; color: var(--text-muted); font-size: 11.5px;"><?php echo $rowSl++; ?></td>

                    <!-- 2. Institution -->
                    <td>
                        <span class="inst-tag <?php echo $isGmit ? 'inst-tag--gmit' : 'inst-tag--gmu'; ?>">
                            <?php echo htmlspecialchars($s['institution'] ?? 'GMU'); ?>
                        </span>
                    </td>

                    <!-- 3. USN -->
                    <td>
                        <span class="candidate-usn"><?php echo htmlspecialchars($uUpper); ?></span>
                    </td>

                    <!-- 4. Student Name -->
                    <td style="font-weight: 700;">
                        <?php echo htmlspecialchars((string)($s['name'] ?? '-')); ?>
                    </td>

                    <!-- 5. Branch / Discipline -->
                    <td style="font-weight: 600; color: #334155;">
                        <?php echo htmlspecialchars((string)($s['discipline'] ?? '-')); ?>
                    </td>

                    <!-- 6. Semester -->
                    <td style="text-align: center; font-weight: 700;">
                        <?php echo htmlspecialchars((string)($s['sem'] ?? '-')); ?>
                    </td>

                    <!-- 7. Placement Status -->
                    <td>
                        <?php if ($st && !empty($st['opt_out'])): ?>
                            <span class="badge-status badge-status--optout">
                                <i class="fas fa-ban"></i> Opt-Out Declared
                            </span>
                        <?php elseif ($st && !empty($st['has_policy_pdf'])): ?>
                            <span class="badge-status badge-status--signed">
                                <i class="fas fa-file-signature"></i> Policy Signed
                            </span>
                        <?php elseif ($st && !empty($st['interested'])): ?>
                            <span class="badge-status badge-status--pending">
                                <i class="fas fa-clock"></i> Policy Pending
                            </span>
                        <?php else: ?>
                            <span class="badge-status badge-status--gray">
                                Active / Unspecified
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- 8. Undertaking PDF (POST) -->
                    <td style="text-align: center;">
                        <?php if ($st && (!empty($st['has_opt_out_pdf']) || !empty($st['has_policy_pdf']))): 
                            $uType = !empty($st['has_policy_pdf']) ? 'policy' : 'opt_out';
                            $btnClass = $uType === 'policy' ? 'btn-post-action--policy' : 'btn-post-action--optout';
                        ?>
                            <form method="POST" action="../student/view_undertaking.php" target="_blank" style="margin: 0; display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <input type="hidden" name="usn" value="<?php echo htmlspecialchars($uUpper); ?>">
                                <input type="hidden" name="type" value="<?php echo $uType; ?>">
                                <button type="submit" class="btn-post-action <?php echo $btnClass; ?>" title="View Signed <?php echo $uType === 'policy' ? 'Policy' : 'Opt-Out'; ?> PDF (POST)">
                                    <i class="fas fa-file-pdf"></i> View PDF
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: #94A3B8; font-size: 11.5px; font-style: italic;">Pending</span>
                        <?php endif; ?>
                    </td>

                    <!-- 9. Resume PDF (POST) -->
                    <td style="text-align: center;">
                        <form method="POST" action="../student/view_resume.php" target="_blank" style="margin: 0; display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="hidden" name="usn" value="<?php echo htmlspecialchars($uUpper); ?>">
                            <button type="submit" class="btn-post-action btn-post-action--resume" title="View Student Resume (POST)">
                                <i class="fas fa-file-lines" style="color: #64748B;"></i> Resume
                            </button>
                        </form>
                    </td>

                    <!-- 10. 10th % -->
                    <td style="text-align: center; font-weight: 600;">
                        <?php echo !empty($s['sslc_percentage']) ? round((float)$s['sslc_percentage'], 1) . '%' : '-'; ?>
                    </td>

                    <!-- 11. 12th % -->
                    <td style="text-align: center; font-weight: 600;">
                        <?php echo !empty($s['puc_percentage']) ? round((float)$s['puc_percentage'], 1) . '%' : '-'; ?>
                    </td>

                    <!-- 12. Student Mobile -->
                    <td style="font-family: 'Courier New', monospace; font-weight: 600; color: #1E293B;">
                        <?php echo htmlspecialchars((string)($s['mobile'] ?? ($s['phone'] ?? '-'))); ?>
                    </td>

                    <!-- 13. Student Email -->
                    <td style="color: #2563EB;">
                        <?php echo htmlspecialchars((string)($s['email'] ?? '-')); ?>
                    </td>

                    <!-- 14. Father Name -->
                    <td>
                        <?php echo htmlspecialchars((string)($s['father_name'] ?? '-')); ?>
                    </td>

                    <!-- 15. Mother Name -->
                    <td>
                        <?php echo htmlspecialchars((string)($s['mother_name'] ?? '-')); ?>
                    </td>

                    <!-- 16. Parent Mobile -->
                    <td style="font-family: 'Courier New', monospace; color: #475569;">
                        <?php echo htmlspecialchars((string)($s['parent_mobile'] ?? '-')); ?>
                    </td>

                    <!-- 17. Submission Date -->
                    <td style="color: #64748B; font-size: 11.5px; white-space: nowrap;">
                        <?php echo htmlspecialchars($submittedOn); ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="17">
                        <div class="empty-box">
                            <i class="far fa-folder-open"></i>
                            <p style="font-size: 14px; margin: 0;">No student submissions found matching the selected filter.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Showing Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> (<?php echo number_format($totalRecords); ?> responded students)
        </div>
        <div class="pagination-nav">
            <?php if ($page > 1): ?>
                <a href="casp_undertakings.php?page=<?php echo $page - 1; ?>" class="page-link-btn">
                    <i class="fas fa-chevron-left"></i> Prev
                </a>
            <?php endif; ?>

            <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
            ?>
                <a href="casp_undertakings.php?page=<?php echo $p; ?>" class="page-link-btn <?php echo $p === $page ? 'active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="casp_undertakings.php?page=<?php echo $page + 1; ?>" class="page-link-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
