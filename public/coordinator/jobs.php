<?php
/**
 * Department Coordinator - Jobs & Internships Directory
 * Professional structured view with overview KPIs, advanced filtering, and application metrics
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$department = getDepartment() ?: 'General';
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptGmu = $discipline_filters[0] ?? $department;
$deptGmit = $discipline_filters[1] ?? $department;
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;
if (!$deptLabel) $deptLabel = 'General';

require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();
$semRange = getCoordinatorSemesterFilters($department) ?: [1, 8];

$overallFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semRange
];
$students = $studentModel->getAllWithUsers($overallFilters);
$usns = array_values(array_filter(array_column($students, 'usn')));
$totalDeptStudents = count($usns);

$db = getDB();

// URL Filters
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

// Fetch Job Postings
$sqlJobs = "SELECT jp.id, jp.title, jp.job_type, jp.location, jp.work_mode, jp.salary_min, jp.salary_max, jp.currency,
                   jp.min_cgpa, jp.application_deadline as deadline, jp.status, c.name as company_name, c.logo_url as company_logo,
                   'job' as source_type, jp.posted_date as created_at
            FROM job_postings jp 
            LEFT JOIN companies c ON jp.company_id = c.id
            WHERE 1=1";

$paramsJobs = [];
if ($statusFilter) {
    if ($statusFilter === 'Active') {
        $sqlJobs .= " AND jp.status = 'Active' AND (jp.application_deadline IS NULL OR jp.application_deadline > NOW())";
    } elseif ($statusFilter === 'Closed') {
        $sqlJobs .= " AND (jp.status != 'Active' OR (jp.application_deadline IS NOT NULL AND jp.application_deadline <= NOW()))";
    } else {
        $sqlJobs .= " AND jp.status = ?";
        $paramsJobs[] = $statusFilter;
    }
}
if ($searchQuery) {
    $sqlJobs .= " AND (jp.title LIKE ? OR c.name LIKE ? OR jp.location LIKE ?)";
    $paramsJobs[] = "%$searchQuery%";
    $paramsJobs[] = "%$searchQuery%";
    $paramsJobs[] = "%$searchQuery%";
}

$jobsList = [];
if ($typeFilter !== 'internship') {
    $stmtJobs = $db->prepare($sqlJobs);
    $stmtJobs->execute($paramsJobs);
    $jobsList = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Internships
$sqlInt = "SELECT i.id, i.internship_title as title, 'Internship' as job_type, i.location, i.mode as work_mode, 
                  NULL as salary_min, NULL as salary_max, 'INR' as currency,
                  NULL as min_cgpa, i.application_deadline as deadline, i.status, i.company_name, i.company_logo,
                  i.duration, i.stipend, 'internship' as source_type, i.created_at
           FROM internships i
           WHERE 1=1";

$paramsInt = [];
if ($statusFilter) {
    if ($statusFilter === 'Active') {
        $sqlInt .= " AND i.status = 'Active' AND (i.application_deadline IS NULL OR i.application_deadline > NOW())";
    } elseif ($statusFilter === 'Closed') {
        $sqlInt .= " AND (i.status != 'Active' OR (i.application_deadline IS NOT NULL AND i.application_deadline <= NOW()))";
    } else {
        $sqlInt .= " AND i.status = ?";
        $paramsInt[] = $statusFilter;
    }
}
if ($searchQuery) {
    $sqlInt .= " AND (i.internship_title LIKE ? OR i.company_name LIKE ? OR i.location LIKE ?)";
    $paramsInt[] = "%$searchQuery%";
    $paramsInt[] = "%$searchQuery%";
    $paramsInt[] = "%$searchQuery%";
}

$internshipsList = [];
if ($typeFilter !== 'job') {
    $stmtInt = $db->prepare($sqlInt);
    $stmtInt->execute($paramsInt);
    $internshipsList = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
}

// Merge and sort newest first
$allItems = array_merge($jobsList, $internshipsList);
usort($allItems, function($a, $b) {
    return strtotime($b['created_at'] ?? 'now') - strtotime($a['created_at'] ?? 'now');
});

// Normalize dynamic status
foreach ($allItems as &$item) {
    if ($item['status'] === 'Active' && !empty($item['deadline'])) {
        if (strtotime($item['deadline']) <= time()) {
            $item['status'] = 'Ended';
        }
    }
}
unset($item);

// Batch query application counts for high performance
$jobIds = [];
$internshipIds = [];
foreach ($allItems as $item) {
    if ($item['source_type'] === 'job') {
        $jobIds[] = (int)$item['id'];
    } else {
        $internshipIds[] = (int)$item['id'];
    }
}

$jobAppCounts = [];
$intAppCounts = [];

if (!empty($usns)) {
    $usnPlaceholders = implode(',', array_fill(0, count($usns), '?'));

    if (!empty($jobIds)) {
        $jobPlaceholders = implode(',', array_fill(0, count($jobIds), '?'));
        $stmtJobApps = $db->prepare("SELECT job_id, COUNT(DISTINCT student_id) as cnt 
                                     FROM job_applications 
                                     WHERE job_id IN ($jobPlaceholders) AND student_id IN ($usnPlaceholders) 
                                     GROUP BY job_id");
        $stmtJobApps->execute(array_merge($jobIds, $usns));
        while ($row = $stmtJobApps->fetch(PDO::FETCH_ASSOC)) {
            $jobAppCounts[(int)$row['job_id']] = (int)$row['cnt'];
        }
    }

    if (!empty($internshipIds)) {
        $intPlaceholders = implode(',', array_fill(0, count($internshipIds), '?'));
        $stmtIntApps = $db->prepare("SELECT internship_id, COUNT(DISTINCT student_id) as cnt 
                                     FROM internship_applications 
                                     WHERE internship_id IN ($intPlaceholders) AND student_id IN ($usnPlaceholders) 
                                     GROUP BY internship_id");
        $stmtIntApps->execute(array_merge($internshipIds, $usns));
        while ($row = $stmtIntApps->fetch(PDO::FETCH_ASSOC)) {
            $intAppCounts[(int)$row['internship_id']] = (int)$row['cnt'];
        }
    }
}

// Compute metrics & item stats
$totalOpportunities = count($allItems);
$activeOpportunities = 0;
$closedOpportunities = 0;
$totalApplicationsSubmitted = 0;

foreach ($allItems as &$item) {
    $isJob = ($item['source_type'] === 'job');
    $applied = $isJob ? ($jobAppCounts[(int)$item['id']] ?? 0) : ($intAppCounts[(int)$item['id']] ?? 0);
    
    $item['applied_count'] = $applied;
    $item['total_dept_students'] = $totalDeptStudents;
    $item['not_applied_count'] = max(0, $totalDeptStudents - $applied);
    $item['participation_rate'] = ($totalDeptStudents > 0) ? round(($applied / $totalDeptStudents) * 100, 1) : 0;

    $totalApplicationsSubmitted += $applied;
    if ($item['status'] === 'Active') {
        $activeOpportunities++;
    } else {
        $closedOpportunities++;
    }
}
unset($item);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs & Internships — <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-maroon-dark: #630000;
            --primary-maroon-light: #991b1b;
            --primary-gold: #D4AF37;
            --primary-gold-dark: #b59228;
            
            --bg-body: #f8fafc;
            --surface-card: #ffffff;
            --surface-subtle: #f1f5f9;
            --surface-hover: #f8fafc;
            
            --text-dark: #0f172a;
            --text-regular: #334155;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            
            --border-subtle: #e2e8f0;
            --border-card: #cbd5e1;
            
            --status-active-bg: #ecfdf5;
            --status-active-color: #047857;
            --status-active-border: #a7f3d0;
            
            --status-ended-bg: #fef2f2;
            --status-ended-color: #b91c1c;
            --status-ended-border: #fecaca;
            
            --status-draft-bg: #f8fafc;
            --status-draft-color: #64748b;
            --status-draft-border: #e2e8f0;
            
            --badge-job-bg: #eef2ff;
            --badge-job-color: #4338ca;
            --badge-int-bg: #fdf4ff;
            --badge-int-color: #a21caf;
            
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.04), 0 1px 2px rgba(15, 23, 42, 0.02);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.03);
            --shadow-lg: 0 10px 25px -5px rgba(15, 23, 42, 0.06), 0 8px 10px -6px rgba(15, 23, 42, 0.03);
            --shadow-card: 0 10px 30px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0, 0, 0, 0.02);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --radius-card: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased;
        }

        .main-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 32px 28px 80px 28px;
        }

        /* Top Breadcrumb & Action Row */
        .top-action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .back-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--surface-card);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            color: var(--text-regular);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .back-nav-link:hover {
            background: var(--surface-subtle);
            color: var(--primary-maroon);
            border-color: #cbd5e1;
            transform: translateX(-2px);
        }

        .dept-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(128, 0, 0, 0.05);
            color: var(--primary-maroon);
            border: 1px solid rgba(128, 0, 0, 0.12);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .dept-indicator i {
            color: var(--primary-gold-dark);
        }

        /* Hero Header Section */
        .page-header-card {
            background: var(--surface-card);
            border-radius: var(--radius-card);
            padding: 28px 32px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .page-header-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: linear-gradient(180deg, var(--primary-maroon) 0%, var(--primary-gold) 100%);
        }

        .header-title-wrapper h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title-wrapper h1 i {
            color: var(--primary-maroon);
            font-size: 24px;
        }

        .header-title-wrapper p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 6px;
            font-weight: 400;
        }

        .header-stats-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Overview KPI Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: var(--surface-card);
            border-radius: var(--radius-card);
            padding: 20px 22px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            position: relative;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .kpi-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }

        .kpi-icon-container {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .kpi-icon-total { background: #f1f5f9; color: #475569; }
        .kpi-icon-active { background: #ecfdf5; color: #059669; }
        .kpi-icon-closed { background: #fef2f2; color: #dc2626; }
        .kpi-icon-applied { background: #eff6ff; color: #2563eb; }

        .kpi-details {
            flex: 1;
            min-width: 0;
        }

        .kpi-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.1;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }

        .kpi-subtitle {
            font-size: 12px;
            color: var(--text-subtle);
            margin-top: 4px;
        }

        /* Filter Panel */
        .filter-panel {
            background: var(--surface-card);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1.2fr auto auto;
            gap: 14px;
            align-items: center;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-subtle);
            font-size: 14px;
            pointer-events: none;
        }

        .search-field {
            width: 100%;
            height: 44px;
            padding: 0 16px 0 40px;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            font-size: 14px;
            font-family: inherit;
            background: var(--surface-card);
            color: var(--text-dark);
            outline: none;
            transition: var(--transition);
        }

        .search-field:focus,
        .select-field:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.08);
            background: #fff;
        }

        .select-field {
            width: 100%;
            height: 44px;
            padding: 0 32px 0 14px;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            font-size: 13.5px;
            font-family: inherit;
            background-color: var(--surface-card);
            color: var(--text-dark);
            outline: none;
            cursor: pointer;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
        }

        .btn-apply-filter {
            height: 44px;
            padding: 0 20px;
            background: var(--primary-maroon);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-apply-filter:hover {
            background: var(--primary-maroon-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
        }

        .btn-reset-filter {
            height: 44px;
            padding: 0 16px;
            background: var(--surface-subtle);
            color: var(--text-regular);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-reset-filter:hover {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        /* Results Card & Table */
        .content-card {
            background: var(--surface-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-meta-bar {
            padding: 16px 24px;
            background: #ffffff;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .table-meta-bar strong {
            color: var(--text-dark);
            font-weight: 700;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .jobs-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
            min-width: 980px;
        }

        .jobs-table th {
            padding: 14px 20px;
            background: #f8fafc;
            color: #475569;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid var(--border-subtle);
            white-space: nowrap;
        }

        .jobs-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            vertical-align: middle;
            color: var(--text-regular);
        }

        .jobs-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .jobs-table tbody tr:hover {
            background-color: #fafbfd;
        }

        .jobs-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Company & Title styling */
        .opportunity-cell {
            display: flex;
            align-items: center;
            gap: 14px;
            max-width: 320px;
        }

        .company-avatar {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: var(--primary-maroon);
            font-size: 16px;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: inset 0 0 0 1px rgba(0,0,0,0.02);
        }

        .company-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .opportunity-text {
            min-width: 0;
        }

        .opportunity-title {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 14.5px;
            line-height: 1.3;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .opportunity-meta {
            font-size: 12.5px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .opportunity-meta i {
            color: var(--text-subtle);
            font-size: 11px;
        }

        /* Type Badges */
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .type-badge.badge-job {
            background: var(--badge-job-bg);
            color: var(--badge-job-color);
            border: 1px solid rgba(67, 56, 202, 0.15);
        }

        .type-badge.badge-internship {
            background: var(--badge-int-bg);
            color: var(--badge-int-color);
            border: 1px solid rgba(162, 28, 175, 0.15);
        }

        .details-subtext {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Deadline Display */
        .deadline-badge {
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
        }

        .deadline-date {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .deadline-time {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .deadline-urgent {
            color: #dc2626 !important;
        }

        /* Metrics Progress Bars */
        .metrics-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 140px;
        }

        .metrics-numeric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .applied-pill {
            font-weight: 800;
            color: #0369a1;
            background: #e0f2fe;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
        }

        .not-applied-pill {
            font-weight: 700;
            color: #475569;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
        }

        .participation-bar-bg {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
        }

        .participation-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%);
            border-radius: 9999px;
            transition: width 0.4s ease;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .status-active {
            background: var(--status-active-bg);
            color: var(--status-active-color);
            border: 1px solid var(--status-active-border);
        }
        .status-active .status-dot { background: #10b981; }

        .status-ended,
        .status-closed {
            background: var(--status-ended-bg);
            color: var(--status-ended-color);
            border: 1px solid var(--status-ended-border);
        }
        .status-ended .status-dot,
        .status-closed .status-dot { background: #ef4444; }

        .status-draft {
            background: var(--status-draft-bg);
            color: var(--status-draft-color);
            border: 1px solid var(--status-draft-border);
        }
        .status-draft .status-dot { background: #94a3b8; }

        /* Action Buttons */
        .btn-table-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            background: #ffffff;
            color: var(--primary-maroon);
            border: 1px solid rgba(128, 0, 0, 0.2);
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-table-action:hover {
            background: var(--primary-maroon);
            color: #ffffff;
            border-color: var(--primary-maroon);
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        /* Empty State */
        .empty-state {
            padding: 64px 20px;
            text-align: center;
        }

        .empty-icon-wrap {
            width: 68px;
            height: 68px;
            background: #f8fafc;
            border: 1px solid var(--border-subtle);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--text-subtle);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .empty-state p {
            color: var(--text-muted);
            font-size: 13.5px;
            max-width: 420px;
            margin: 0 auto 20px auto;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 640px) {
            .main-container {
                padding: 20px 16px 60px 16px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .filter-form {
                grid-template-columns: 1fr;
            }
            .page-header-card {
                padding: 20px;
            }
            .header-title-wrapper h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-container">
        
        <!-- Top Nav & Scope Breadcrumb -->
        <div class="top-action-bar">
            <a href="dashboard.php" class="back-nav-link">
                <i class="fas fa-arrow-left"></i> Dashboard Overview
            </a>

            <div class="dept-indicator" title="Department Filtering Scope">
                <i class="fas fa-university"></i>
                <span>Department Scope: <strong><?php echo htmlspecialchars($deptLabel); ?></strong></span>
            </div>
        </div>

        <!-- Page Header Card -->
        <div class="page-header-card">
            <div class="header-title-wrapper">
                <h1><i class="fas fa-briefcase"></i> Jobs & Internships Directory</h1>
                <p>Monitor opportunities, inspect student eligibility, and analyze department application ratios in real-time.</p>
            </div>

            <div class="header-stats-group">
                <div style="font-size: 12.5px; color: var(--text-muted); background: #f8fafc; border: 1px solid var(--border-subtle); padding: 8px 16px; border-radius: 10px;">
                    <i class="fas fa-user-graduate" style="color: var(--primary-maroon); margin-right: 6px;"></i>
                    Registered Dept Students: <strong><?php echo number_format($totalDeptStudents); ?></strong>
                </div>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="stats-grid">
            <div class="kpi-card">
                <div class="kpi-icon-container kpi-icon-total">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="kpi-details">
                    <div class="kpi-label">Total Postings</div>
                    <div class="kpi-value"><?php echo number_format($totalOpportunities); ?></div>
                    <div class="kpi-subtitle">Combined Jobs & Internships</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon-container kpi-icon-active">
                    <i class="fas fa-circle-check"></i>
                </div>
                <div class="kpi-details">
                    <div class="kpi-label">Open / Active</div>
                    <div class="kpi-value"><?php echo number_format($activeOpportunities); ?></div>
                    <div class="kpi-subtitle">Accepting submissions</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon-container kpi-icon-closed">
                    <i class="fas fa-clock-rotate-left"></i>
                </div>
                <div class="kpi-details">
                    <div class="kpi-label">Closed / Ended</div>
                    <div class="kpi-value"><?php echo number_format($closedOpportunities); ?></div>
                    <div class="kpi-subtitle">Past application deadline</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon-container kpi-icon-applied">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="kpi-details">
                    <div class="kpi-label">Total Dept Applications</div>
                    <div class="kpi-value"><?php echo number_format($totalApplicationsSubmitted); ?></div>
                    <div class="kpi-subtitle">Submitted by your department</div>
                </div>
            </div>
        </div>

        <!-- Structured Search & Filter Bar -->
        <div class="filter-panel">
            <form method="GET" class="filter-form">
                <div class="input-group">
                    <i class="fas fa-search input-icon"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                           placeholder="Search role title, company name, or location..." 
                           class="search-field">
                </div>

                <div class="input-group">
                    <select name="type" class="select-field">
                        <option value="">All Opportunity Types</option>
                        <option value="job" <?php echo $typeFilter === 'job' ? 'selected' : ''; ?>>Full-Time / Jobs Only</option>
                        <option value="internship" <?php echo $typeFilter === 'internship' ? 'selected' : ''; ?>>Internships Only</option>
                    </select>
                </div>

                <div class="input-group">
                    <select name="status" class="select-field">
                        <option value="">All Lifecycle Statuses</option>
                        <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active (Currently Open)</option>
                        <option value="Closed" <?php echo $statusFilter === 'Closed' ? 'selected' : ''; ?>>Closed / Ended</option>
                    </select>
                </div>

                <button type="submit" class="btn-apply-filter">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>

                <?php if ($searchQuery || $typeFilter || $statusFilter): ?>
                <a href="jobs.php" class="btn-reset-filter" title="Clear All Filters">
                    <i class="fas fa-times"></i> Reset
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Structured Data Table Card -->
        <div class="content-card">
            <div class="table-meta-bar">
                <div>
                    Showing <strong><?php echo count($allItems); ?></strong> of <strong><?php echo $totalOpportunities; ?></strong> opportunities
                    <?php if ($statusFilter || $typeFilter || $searchQuery): ?>
                        <span style="color: var(--primary-maroon); font-weight: 600; margin-left: 6px;">(Filtered)</span>
                    <?php endif; ?>
                </div>

                <div style="font-size: 12px; color: var(--text-subtle);">
                    <i class="fas fa-info-circle"></i> Click "View Students" to inspect applicant lists and not-applied rosters.
                </div>
            </div>

            <div class="table-responsive">
                <table class="jobs-table">
                    <thead>
                        <tr>
                            <th style="width: 32%;">Opportunity & Organization</th>
                            <th style="width: 16%;">Role Category</th>
                            <th style="width: 16%;">Deadline</th>
                            <th style="width: 18%;">Dept Participation</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 8%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allItems)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon-wrap">
                                        <i class="fas fa-folder-open"></i>
                                    </div>
                                    <h3>No Matching Opportunities Found</h3>
                                    <p>We couldn't find any job or internship postings matching your active filters. Try adjusting your search query or reset filters.</p>
                                    <?php if ($searchQuery || $typeFilter || $statusFilter): ?>
                                    <a href="jobs.php" class="btn-apply-filter" style="display: inline-flex;">
                                        <i class="fas fa-rotate-left"></i> Reset All Filters
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($allItems as $item): 
                                $isJob = ($item['source_type'] === 'job');
                                $deadlineTime = !empty($item['deadline']) ? strtotime($item['deadline']) : null;
                                $isEnded = $deadlineTime && ($deadlineTime <= time());
                                $isUrgent = $deadlineTime && !$isEnded && (($deadlineTime - time()) < (48 * 3600));
                                $companyInitial = strtoupper(substr($item['company_name'] ?: 'C', 0, 1));
                            ?>
                            <tr>
                                <!-- Opportunity & Company -->
                                <td>
                                    <div class="opportunity-cell">
                                        <div class="company-avatar">
                                            <?php if (!empty($item['company_logo']) && file_exists(ROOT_PATH . '/public/uploads/company_images/' . $item['company_logo'])): ?>
                                                <img src="<?php echo APP_URL . '/uploads/company_images/' . htmlspecialchars($item['company_logo']); ?>" alt="Logo">
                                            <?php else: ?>
                                                <span><?php echo $companyInitial; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="opportunity-text">
                                            <div class="opportunity-title" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </div>
                                            <div class="opportunity-meta">
                                                <i class="fas fa-building"></i>
                                                <span><?php echo htmlspecialchars($item['company_name'] ?: 'Corporate Partner'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category & Mode -->
                                <td>
                                    <span class="type-badge <?php echo $isJob ? 'badge-job' : 'badge-internship'; ?>">
                                        <i class="fas <?php echo $isJob ? 'fa-briefcase' : 'fa-graduation-cap'; ?>"></i>
                                        <?php echo htmlspecialchars($item['job_type']); ?>
                                    </span>
                                    
                                    <div class="details-subtext">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($item['location'] ?: 'Not Specified'); ?></span>
                                        <?php if (!empty($item['work_mode'])): ?>
                                            <span style="color: var(--text-subtle);">•</span>
                                            <span><?php echo htmlspecialchars($item['work_mode']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Deadline -->
                                <td>
                                    <?php if ($deadlineTime): ?>
                                    <div class="deadline-badge">
                                        <span class="deadline-date <?php echo $isUrgent ? 'deadline-urgent' : ''; ?>">
                                            <?php echo date('M d, Y', $deadlineTime); ?>
                                            <?php if ($isUrgent): ?>
                                                <span style="font-size: 11px; background: #fee2e2; color: #b91c1c; padding: 1px 6px; border-radius: 4px; margin-left: 4px;">Closing Soon</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="deadline-time">
                                            <i class="far fa-clock" style="font-size: 10px;"></i> <?php echo date('h:i A', $deadlineTime); ?>
                                        </span>
                                    </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-subtle); font-size: 12.5px;">No Expiry Specified</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Dept Participation -->
                                <td>
                                    <div class="metrics-stack">
                                        <div class="metrics-numeric-row">
                                            <span class="applied-pill" title="Students who applied from your department">
                                                <i class="fas fa-check" style="font-size: 10px;"></i> <?php echo $item['applied_count']; ?> Applied
                                            </span>
                                            <span class="not-applied-pill" title="Students who have not applied">
                                                <?php echo $item['not_applied_count']; ?> Remaining
                                            </span>
                                        </div>

                                        <div class="participation-bar-bg" title="Participation Rate: <?php echo $item['participation_rate']; ?>%">
                                            <div class="participation-bar-fill" style="width: <?php echo min(100, $item['participation_rate']); ?>%;"></div>
                                        </div>

                                        <div style="font-size: 11px; color: var(--text-subtle); text-align: right;">
                                            <?php echo $item['participation_rate']; ?>% Dept Turnout
                                        </div>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td>
                                    <?php
                                    $st = $item['status'] ?: 'Draft';
                                    $stClass = match(strtolower($st)) {
                                        'active' => 'status-active',
                                        'ended', 'closed' => 'status-ended',
                                        default => 'status-draft'
                                    };
                                    ?>
                                    <span class="status-badge <?php echo $stClass; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo htmlspecialchars($st); ?>
                                    </span>
                                </td>

                                <!-- Action -->
                                <td style="text-align: right;">
                                    <a href="job_students.php?id=<?php echo $item['id']; ?>&type=<?php echo $item['source_type']; ?>" 
                                       class="btn-table-action"
                                       title="View list of applied and non-applied department candidates">
                                        <span>View Students</span>
                                        <i class="fas fa-chevron-right" style="font-size: 11px;"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
