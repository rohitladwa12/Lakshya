<?php
/**
 * Department Coordinator - View Job/Internship Students
 * Fully professionalized with real-time participation metrics, rich filtering, and High-Fidelity PDF Export
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'job';

if (!$id || !in_array($type, ['job', 'internship'])) {
    header("Location: jobs.php");
    exit;
}

$db = getDB();

// Fetch Opportunity Details
if ($type === 'job') {
    $stmt = $db->prepare("SELECT jp.id, jp.title, jp.status, jp.job_type, jp.location, jp.work_mode, 
                                 jp.application_deadline as deadline, c.name as company_name, c.logo_url as company_logo 
                          FROM job_postings jp 
                          LEFT JOIN companies c ON jp.company_id = c.id 
                          WHERE jp.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("SELECT id, internship_title as title, status, 'Internship' as job_type, location, mode as work_mode, 
                                 application_deadline as deadline, company_name, company_logo 
                          FROM internships 
                          WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$item) {
    header("Location: jobs.php");
    exit;
}

$department = getDepartment() ?: 'General';
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptGmu = $discipline_filters[0] ?? $department;
$deptGmit = $discipline_filters[1] ?? $department;
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;

require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();
$semRange = getCoordinatorSemesterFilters($department) ?: [1, 8];

$overallFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semRange
];
$allStudents = $studentModel->getAllWithUsers($overallFilters);

// Get applied students with application timestamp
if ($type === 'job') {
    $stmt = $db->prepare("SELECT student_id, status, applied_at FROM job_applications WHERE job_id = ?");
} else {
    $stmt = $db->prepare("SELECT student_id, status, applied_at FROM internship_applications WHERE internship_id = ?");
}
$stmt->execute([$id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$appliedUsns = [];
$appStatuses = [];
$appliedDates = [];
foreach ($applications as $app) {
    $stuKey = (string)$app['student_id'];
    $appliedUsns[] = $stuKey;
    $appStatuses[$stuKey] = $app['status'] ?: 'Applied';
    $appliedDates[$stuKey] = $app['applied_at'] ?? null;
}

// Ensure applied students from coordinator's discipline are included
if (!empty($appliedUsns)) {
    $appliedProfiles = $studentModel->getAllWithUsers(['usns' => $appliedUsns]);
    $existingUsns = array_column($allStudents, 'usn');
    foreach ($appliedProfiles as $p) {
        if (in_array($p['department'], $discipline_filters)) {
            if (!in_array($p['usn'], $existingUsns)) {
                $allStudents[] = $p;
                $existingUsns[] = $p['usn'];
            }
        }
    }
}

$appliedStudents = [];
$notAppliedStudents = [];

// Breakdown metrics
$statusCounts = [
    'Pending' => 0,
    'Shortlisted' => 0,
    'Selected' => 0,
    'Rejected' => 0
];

foreach ($allStudents as $stu) {
    $isApplied = false;
    $status = 'Unknown';
    $appliedAt = null;
    $stuUsn = (string)($stu['usn'] ?? '');
    $stuAadhar = (string)($stu['aadhar'] ?? '');
    
    if (in_array($stuUsn, $appliedUsns)) {
        $isApplied = true;
        $status = $appStatuses[$stuUsn] ?? 'Applied';
        $appliedAt = $appliedDates[$stuUsn] ?? null;
    } elseif ($stuAadhar !== '' && in_array($stuAadhar, $appliedUsns)) {
        $isApplied = true;
        $status = $appStatuses[$stuAadhar] ?? 'Applied';
        $appliedAt = $appliedDates[$stuAadhar] ?? null;
    }
    
    if ($isApplied) {
        $stu['app_status'] = $status;
        $stu['applied_at'] = $appliedAt;
        $appliedStudents[] = $stu;

        $stLower = strtolower($status);
        if (strpos($stLower, 'select') !== false || strpos($stLower, 'offer') !== false) {
            $statusCounts['Selected']++;
        } elseif (strpos($stLower, 'shortlist') !== false) {
            $statusCounts['Shortlisted']++;
        } elseif (strpos($stLower, 'reject') !== false) {
            $statusCounts['Rejected']++;
        } else {
            $statusCounts['Pending']++;
        }
    } else {
        $notAppliedStudents[] = $stu;
    }
}

// Sort lists by USN
usort($appliedStudents, function($a, $b) { return strcmp($a['usn'] ?? '', $b['usn'] ?? ''); });
usort($notAppliedStudents, function($a, $b) { return strcmp($a['usn'] ?? '', $b['usn'] ?? ''); });

$activeTab = $_GET['tab'] ?? 'applied';
if (!in_array($activeTab, ['applied', 'not_applied'])) {
    $activeTab = 'applied';
}

$searchQuery = trim($_GET['q'] ?? '');
$semesterFilter = trim($_GET['semester'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$institutionFilter = trim($_GET['institution'] ?? '');

$filterStudents = function($list) use ($searchQuery, $semesterFilter, $statusFilter, $institutionFilter, $activeTab) {
    $result = [];
    foreach ($list as $stu) {
        if ($searchQuery) {
            $name = strtolower($stu['full_name'] ?? $stu['name'] ?? '');
            $usn = strtolower($stu['usn'] ?? '');
            $aadhar = strtolower($stu['aadhar'] ?? '');
            $q = strtolower($searchQuery);
            if (strpos($name, $q) === false && strpos($usn, $q) === false && strpos($aadhar, $q) === false) {
                continue;
            }
        }
        
        if ($semesterFilter && ($stu['current_semester'] ?? $stu['semester'] ?? '') != $semesterFilter) {
            continue;
        }

        if ($institutionFilter && ($stu['institution'] ?? '') != $institutionFilter) {
            continue;
        }
        
        if ($activeTab === 'applied' && $statusFilter) {
            $appStatus = strtolower($stu['app_status'] ?? '');
            if ($statusFilter === 'Pending' && strpos($appStatus, 'applied') === false && strpos($appStatus, 'pending') === false) continue;
            if ($statusFilter === 'Shortlisted' && strpos($appStatus, 'shortlist') === false) continue;
            if ($statusFilter === 'Selected' && strpos($appStatus, 'select') === false && strpos($appStatus, 'offer') === false) continue;
            if ($statusFilter === 'Rejected' && strpos($appStatus, 'reject') === false) continue;
        }
        
        $result[] = $stu;
    }
    return $result;
};

$appliedCount = count($appliedStudents);
$notAppliedCount = count($notAppliedStudents);
$totalDeptCount = $appliedCount + $notAppliedCount;
$turnoutPct = ($totalDeptCount > 0) ? round(($appliedCount / $totalDeptCount) * 100, 1) : 0;

$listToDisplay = ($activeTab === 'applied') ? $appliedStudents : $notAppliedStudents;
$filteredList = $filterStudents($listToDisplay);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> — Student Roster | <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Client-side High-Fidelity PDF Generation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous"></script>

    <style>
        :root {
            --primary-maroon: #800000;
            --primary-maroon-dark: #600000;
            --primary-gold: #D4AF37;
            --primary-gold-dark: #b59228;
            
            --bg-body: #f8fafc;
            --surface-card: #ffffff;
            --surface-subtle: #f1f5f9;
            --surface-hover: #fafbfd;
            
            --text-dark: #0f172a;
            --text-regular: #334155;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            
            --border-subtle: #e2e8f0;
            --border-card: #cbd5e1;
            
            --badge-applied: #e0f2fe;
            --badge-applied-text: #0369a1;
            
            --badge-shortlisted: #fef3c7;
            --badge-shortlisted-text: #92400e;
            
            --badge-selected: #dcfce7;
            --badge-selected-text: #166534;
            
            --badge-rejected: #fee2e2;
            --badge-rejected-text: #991b1b;

            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.04), 0 1px 2px rgba(15, 23, 42, 0.02);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.03);
            --shadow-lg: 0 10px 25px -5px rgba(15, 23, 42, 0.06);
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

        /* Top Action Bar */
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

        .action-group-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-export-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            background: #ffffff;
            color: var(--primary-maroon);
            border: 1.5px solid var(--primary-maroon);
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .btn-export-pdf:hover {
            background: var(--primary-maroon);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
            transform: translateY(-1px);
        }

        .dept-scope-pill {
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
        }

        /* Hero Opportunity Card */
        .opportunity-hero-card {
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
            gap: 24px;
            position: relative;
            overflow: hidden;
        }

        .opportunity-hero-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: linear-gradient(180deg, var(--primary-maroon) 0%, var(--primary-gold) 100%);
        }

        .hero-info-section {
            display: flex;
            align-items: center;
            gap: 20px;
            min-width: 0;
        }

        .hero-logo-box {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            color: var(--primary-maroon);
            flex-shrink: 0;
            overflow: hidden;
        }

        .hero-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-text-block h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.02em;
            line-height: 1.25;
            margin-bottom: 6px;
        }

        .hero-meta-items {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13.5px;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .hero-meta-items span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .hero-badge-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .badge-type {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid rgba(67, 56, 202, 0.15);
        }

        /* KPI Quick Metrics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .kpi-stat-card {
            background: var(--surface-card);
            border-radius: var(--radius-card);
            padding: 20px 22px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 18px;
            transition: var(--transition);
        }

        .kpi-stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }

        .kpi-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            flex-shrink: 0;
        }

        .stat-icon-applied { background: #e0f2fe; color: #0284c7; }
        .stat-icon-not { background: #f1f5f9; color: #64748b; }
        .stat-icon-turnout { background: #ecfdf5; color: #059669; }
        .stat-icon-selected { background: #dcfce7; color: #166534; }

        .stat-info-wrap {
            flex: 1;
            min-width: 0;
        }

        .stat-label-title {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 3px;
        }

        .stat-numeric-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.1;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }

        .stat-sub-text {
            font-size: 12px;
            color: var(--text-subtle);
            margin-top: 3px;
        }

        /* Navigation Segmented Tabs */
        .tabs-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .segmented-tabs {
            display: inline-flex;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            padding: 4px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }

        .tab-nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .tab-nav-btn:hover {
            color: var(--text-dark);
        }

        .tab-nav-btn.active {
            background: var(--primary-maroon);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.2);
        }

        .tab-counter-badge {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 800;
            background: rgba(0, 0, 0, 0.06);
            color: inherit;
        }

        .tab-nav-btn.active .tab-counter-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        /* Filter Panel */
        .filter-panel-card {
            background: var(--surface-card);
            border-radius: var(--radius-card);
            padding: 18px 22px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .filter-form-grid {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1.2fr <?php echo ($activeTab === 'applied') ? '1.2fr' : ''; ?> auto auto;
            gap: 12px;
            align-items: center;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 14px;
            color: var(--text-subtle);
            font-size: 13.5px;
            pointer-events: none;
        }

        .form-input-box {
            width: 100%;
            height: 42px;
            padding: 0 16px 0 38px;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            font-size: 13.5px;
            font-family: inherit;
            background: var(--surface-card);
            color: var(--text-dark);
            outline: none;
            transition: var(--transition);
        }

        .form-input-box:focus,
        .form-select-box:focus {
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.08);
            background: #fff;
        }

        .form-select-box {
            width: 100%;
            height: 42px;
            padding: 0 30px 0 14px;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            font-size: 13px;
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
            background-size: 13px;
        }

        .btn-filter-submit {
            height: 42px;
            padding: 0 18px;
            background: var(--primary-maroon);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-filter-submit:hover {
            background: var(--primary-maroon-dark);
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .btn-filter-clear {
            height: 42px;
            padding: 0 14px;
            background: var(--surface-subtle);
            color: var(--text-regular);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            font-size: 13px;
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

        .btn-filter-clear:hover {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        /* Results Roster Card & Table */
        .roster-card {
            background: var(--surface-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .roster-header-meta {
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

        .roster-header-meta strong {
            color: var(--text-dark);
            font-weight: 700;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .roster-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
            min-width: 900px;
        }

        .roster-table th {
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

        .roster-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            vertical-align: middle;
            color: var(--text-regular);
        }

        .roster-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .roster-table tbody tr:hover {
            background-color: var(--surface-hover);
        }

        .roster-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Student Identity Column */
        .student-flex-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .student-avatar-initial {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: var(--primary-maroon);
            font-weight: 800;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .student-names-wrap {
            min-width: 0;
        }

        .student-full-name {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.25;
            margin-bottom: 3px;
        }

        .student-usn-text {
            font-size: 12px;
            font-family: 'Inter', monospace;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.3px;
        }

        /* Academic Tags */
        .inst-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .branch-subtext {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
            font-weight: 500;
        }

        .sem-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f8fafc;
            border: 1px solid var(--border-subtle);
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-regular);
        }

        /* Application Status Badges */
        .status-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .status-dot-sm {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .pill-pending {
            background: var(--badge-applied);
            color: var(--badge-applied-text);
        }
        .pill-pending .status-dot-sm { background: #0284c7; }

        .pill-shortlisted {
            background: var(--badge-shortlisted);
            color: var(--badge-shortlisted-text);
        }
        .pill-shortlisted .status-dot-sm { background: #d97706; }

        .pill-selected {
            background: var(--badge-selected);
            color: var(--badge-selected-text);
        }
        .pill-selected .status-dot-sm { background: #16a34a; }

        .pill-rejected {
            background: var(--badge-rejected);
            color: var(--badge-rejected-text);
        }
        .pill-rejected .status-dot-sm { background: #dc2626; }

        /* Empty State */
        .empty-roster-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon-circle {
            width: 64px;
            height: 64px;
            background: #f8fafc;
            border: 1px solid var(--border-subtle);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--text-subtle);
            margin-bottom: 14px;
        }

        .empty-roster-state h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .empty-roster-state p {
            color: var(--text-muted);
            font-size: 13.5px;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Printable PDF Dedicated Container (Off-Screen Template) */
        #pdfExportTemplate {
            display: none;
            background: #ffffff;
            color: #0f172a;
            padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .pdf-header {
            border-bottom: 2px solid #800000;
            padding-bottom: 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .pdf-header h2 {
            font-size: 20px;
            color: #800000;
            margin-bottom: 4px;
            font-weight: 800;
        }

        .pdf-header p {
            font-size: 12px;
            color: #475569;
            margin: 2px 0;
        }

        .pdf-meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            font-size: 11px;
        }

        .pdf-meta-item strong {
            display: block;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .pdf-table th {
            background: #f1f5f9;
            color: #1e293b;
            font-weight: 700;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .pdf-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: middle;
        }

        .pdf-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .pdf-footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-form-grid {
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
            .filter-form-grid {
                grid-template-columns: 1fr;
            }
            .opportunity-hero-card {
                padding: 20px;
            }
            .hero-text-block h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-container">
        
        <!-- Top Nav & Export Row -->
        <div class="top-action-bar">
            <a href="jobs.php" class="back-nav-link">
                <i class="fas fa-arrow-left"></i> Back to Opportunities
            </a>

            <div class="action-group-right">
                <div class="dept-scope-pill" title="Coordinator Department Filtering Scope">
                    <i class="fas fa-university" style="color: var(--primary-gold-dark);"></i>
                    <span>Scope: <strong><?php echo htmlspecialchars($deptLabel); ?></strong></span>
                </div>

                <button type="button" class="btn-export-pdf" onclick="generateStudentReportPDF()">
                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i> Export PDF Roster
                </button>
            </div>
        </div>

        <!-- Opportunity Hero Information Card -->
        <div class="opportunity-hero-card">
            <div class="hero-info-section">
                <div class="hero-logo-box">
                    <?php if (!empty($item['company_logo']) && file_exists(ROOT_PATH . '/public/uploads/company_images/' . $item['company_logo'])): ?>
                        <img src="<?php echo APP_URL . '/uploads/company_images/' . htmlspecialchars($item['company_logo']); ?>" alt="Company Logo">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($item['company_name'] ?: 'C', 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="hero-text-block">
                    <h1><?php echo htmlspecialchars($item['title']); ?></h1>
                    <div class="hero-meta-items">
                        <span><i class="fas fa-building" style="color: var(--text-subtle);"></i> <?php echo htmlspecialchars($item['company_name'] ?: 'Corporate Partner'); ?></span>
                        <span>&bull;</span>
                        <span><i class="fas fa-map-marker-alt" style="color: var(--text-subtle);"></i> <?php echo htmlspecialchars($item['location'] ?: 'Not Specified'); ?></span>
                        <?php if (!empty($item['work_mode'])): ?>
                            <span>&bull;</span>
                            <span><i class="fas fa-laptop-house" style="color: var(--text-subtle);"></i> <?php echo htmlspecialchars($item['work_mode']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['deadline'])): ?>
                            <span>&bull;</span>
                            <span><i class="far fa-clock" style="color: #dc2626;"></i> Deadline: <?php echo date('M d, Y - h:i A', strtotime($item['deadline'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="hero-badge-group">
                <span class="badge-type">
                    <i class="fas <?php echo ($type === 'job') ? 'fa-briefcase' : 'fa-graduation-cap'; ?>"></i>
                    <?php echo ucfirst($type); ?> Posting
                </span>
            </div>
        </div>

        <!-- KPI Quick Metrics Grid -->
        <div class="stats-grid">
            <div class="kpi-stat-card">
                <div class="kpi-stat-icon stat-icon-applied">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info-wrap">
                    <div class="stat-label-title">Applied Students</div>
                    <div class="stat-numeric-value"><?php echo number_format($appliedCount); ?></div>
                    <div class="stat-sub-text">Submitted to this role</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon stat-icon-not">
                    <i class="fas fa-user-xmark"></i>
                </div>
                <div class="stat-info-wrap">
                    <div class="stat-label-title">Not Applied</div>
                    <div class="stat-numeric-value"><?php echo number_format($notAppliedCount); ?></div>
                    <div class="stat-sub-text">Pending action</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon stat-icon-turnout">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-info-wrap">
                    <div class="stat-label-title">Dept Turnout</div>
                    <div class="stat-numeric-value"><?php echo $turnoutPct; ?>%</div>
                    <div class="stat-sub-text"><?php echo $appliedCount; ?> of <?php echo $totalDeptCount; ?> students</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon stat-icon-selected">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-info-wrap">
                    <div class="stat-label-title">Selected / Offers</div>
                    <div class="stat-numeric-value"><?php echo number_format($statusCounts['Selected']); ?></div>
                    <div class="stat-sub-text">Shortlisted: <?php echo $statusCounts['Shortlisted']; ?></div>
                </div>
            </div>
        </div>

        <!-- Segmented Tab Nav -->
        <div class="tabs-container">
            <div class="segmented-tabs">
                <a href="?id=<?php echo $id; ?>&type=<?php echo $type; ?>&tab=applied" 
                   class="tab-nav-btn <?php echo ($activeTab === 'applied') ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Applied Students</span>
                    <span class="tab-counter-badge"><?php echo $appliedCount; ?></span>
                </a>
                
                <a href="?id=<?php echo $id; ?>&type=<?php echo $type; ?>&tab=not_applied" 
                   class="tab-nav-btn <?php echo ($activeTab === 'not_applied') ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    <span>Not Applied</span>
                    <span class="tab-counter-badge"><?php echo $notAppliedCount; ?></span>
                </a>
            </div>

            <div style="font-size: 13px; color: var(--text-muted);">
                Showing <strong><?php echo count($filteredList); ?></strong> student records
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-panel-card">
            <form method="GET" class="filter-form-grid">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">

                <div class="input-wrapper">
                    <i class="fas fa-search input-icon-left"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                           placeholder="Search by student name or USN..." 
                           class="form-input-box">
                </div>

                <div class="input-wrapper">
                    <select name="semester" class="form-select-box">
                        <option value="">All Semesters</option>
                        <?php foreach($semRange as $sem): ?>
                        <option value="<?php echo $sem; ?>" <?php echo $semesterFilter == $sem ? 'selected' : ''; ?>>Semester <?php echo $sem; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-wrapper">
                    <select name="institution" class="form-select-box">
                        <option value="">All Campuses / Institutions</option>
                        <option value="GMIT" <?php echo $institutionFilter === 'GMIT' ? 'selected' : ''; ?>>GMIT</option>
                        <option value="GMU" <?php echo $institutionFilter === 'GMU' ? 'selected' : ''; ?>>GMU</option>
                    </select>
                </div>

                <?php if ($activeTab === 'applied'): ?>
                <div class="input-wrapper">
                    <select name="status" class="form-select-box">
                        <option value="">All Application Statuses</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Applied / Under Review</option>
                        <option value="Shortlisted" <?php echo $statusFilter === 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="Selected" <?php echo $statusFilter === 'Selected' ? 'selected' : ''; ?>>Selected / Offered</option>
                        <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-filter-submit">
                    <i class="fas fa-filter"></i> Filter
                </button>

                <?php if ($searchQuery || $semesterFilter || $statusFilter || $institutionFilter): ?>
                <a href="?id=<?php echo $id; ?>&type=<?php echo htmlspecialchars($type); ?>&tab=<?php echo htmlspecialchars($activeTab); ?>" class="btn-filter-clear">
                    <i class="fas fa-times"></i> Reset
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Student Roster Data Table -->
        <div class="roster-card">
            <div class="roster-header-meta">
                <div>
                    Current Roster: <strong><?php echo ($activeTab === 'applied') ? 'Applied Candidates' : 'Eligible Pending Candidates'; ?></strong>
                </div>
                <div style="font-size: 12px; color: var(--text-subtle);">
                    <i class="fas fa-shield-alt"></i> Data filtered specifically to your coordinator department privileges.
                </div>
            </div>

            <div class="table-responsive">
                <table class="roster-table">
                    <thead>
                        <tr>
                            <th style="width: 32%;">Student Identity</th>
                            <th style="width: 25%;">Academic Division</th>
                            <th style="width: 15%;">Semester</th>
                            <?php if ($activeTab === 'applied'): ?>
                                <th style="width: 15%;">Application Status</th>
                                <th style="width: 13%;">Submission Date</th>
                            <?php else: ?>
                                <th style="width: 28%;">Action Required</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filteredList)): ?>
                        <tr>
                            <td colspan="<?php echo ($activeTab === 'applied') ? 5 : 4; ?>">
                                <div class="empty-roster-state">
                                    <div class="empty-icon-circle">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                    <h3>No Students Found</h3>
                                    <p>No candidates match your current filter parameters. Try adjusting your search query or reset the filters.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($filteredList as $stu): 
                                $initial = strtoupper(substr($stu['full_name'] ?? $stu['name'] ?? 'S', 0, 1));
                                $sem = $stu['current_semester'] ?? $stu['semester'] ?? '-';
                                $inst = $stu['institution'] ?? 'N/A';
                                $prog = trim(($stu['course'] ?? '') . ' - ' . ($stu['department'] ?? ''));
                            ?>
                            <tr>
                                <!-- Student Name & USN -->
                                <td>
                                    <div class="student-flex-cell">
                                        <div class="student-avatar-initial">
                                            <?php echo $initial; ?>
                                        </div>
                                        <div class="student-names-wrap">
                                            <div class="student-full-name">
                                                <?php echo htmlspecialchars($stu['full_name'] ?? $stu['name'] ?? 'Unknown Student'); ?>
                                            </div>
                                            <div class="student-usn-text">
                                                <i class="far fa-id-badge" style="font-size: 11px;"></i>
                                                <?php echo htmlspecialchars($stu['usn'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Academic Division -->
                                <td>
                                    <span class="inst-badge"><?php echo htmlspecialchars($inst); ?></span>
                                    <div class="branch-subtext"><?php echo htmlspecialchars($prog ?: 'General Engineering'); ?></div>
                                </td>

                                <!-- Semester -->
                                <td>
                                    <span class="sem-pill">
                                        <i class="fas fa-graduation-cap" style="font-size: 11px; color: var(--text-muted);"></i>
                                        Sem <?php echo htmlspecialchars($sem); ?>
                                    </span>
                                </td>

                                <!-- Application Status / Not Applied Action -->
                                <?php if ($activeTab === 'applied'): 
                                    $stRaw = $stu['app_status'] ?? 'Applied';
                                    $stLower = strtolower($stRaw);
                                    
                                    $badgeClass = 'pill-pending';
                                    if (strpos($stLower, 'select') !== false || strpos($stLower, 'offer') !== false) {
                                        $badgeClass = 'pill-selected';
                                    } elseif (strpos($stLower, 'shortlist') !== false) {
                                        $badgeClass = 'pill-shortlisted';
                                    } elseif (strpos($stLower, 'reject') !== false) {
                                        $badgeClass = 'pill-rejected';
                                    }
                                ?>
                                <td>
                                    <span class="status-pill-badge <?php echo $badgeClass; ?>">
                                        <span class="status-dot-sm"></span>
                                        <?php echo htmlspecialchars($stRaw); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12.5px; color: var(--text-muted);">
                                        <?php echo !empty($stu['applied_at']) ? date('M d, Y', strtotime($stu['applied_at'])) : 'Recorded'; ?>
                                    </span>
                                </td>
                                <?php else: ?>
                                <td>
                                    <span style="font-size: 12.5px; color: #b45309; background: #fef3c7; padding: 4px 10px; border-radius: 6px; font-weight: 600;">
                                        <i class="fas fa-exclamation-triangle" style="font-size: 11px;"></i> Eligible • Not Applied
                                    </span>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Hidden High-Fidelity Printable PDF Template (Rendered client-side on export) -->
    <div id="pdfExportTemplate">
        <div class="pdf-header">
            <div>
                <h2>GM University — Training & Placement Cell</h2>
                <p><strong>Department Candidate Performance & Application Roster</strong></p>
                <p>Generated on <?php echo date('F d, Y - h:i A'); ?></p>
            </div>
            <div style="text-align: right;">
                <p style="font-size: 13px; font-weight: 800; color: #800000;"><?php echo APP_NAME; ?></p>
                <p style="font-size: 11px; color: #64748b;">Coordinator Portal</p>
            </div>
        </div>

        <div class="pdf-meta-box">
            <div class="pdf-meta-item">
                <strong>Opportunity Title</strong>
                <span><?php echo htmlspecialchars($item['title']); ?></span>
            </div>
            <div class="pdf-meta-item">
                <strong>Company / Partner</strong>
                <span><?php echo htmlspecialchars($item['company_name'] ?: 'Corporate Partner'); ?></span>
            </div>
            <div class="pdf-meta-item">
                <strong>Department Scope</strong>
                <span><?php echo htmlspecialchars($deptLabel); ?></span>
            </div>
            <div class="pdf-meta-item">
                <strong>Roster Category</strong>
                <span><?php echo ($activeTab === 'applied') ? 'Applied Candidates (' . $appliedCount . ')' : 'Not Applied Candidates (' . $notAppliedCount . ')'; ?></span>
            </div>
        </div>

        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">Student Name</th>
                    <th style="width: 20%;">USN</th>
                    <th style="width: 15%;">Institution</th>
                    <th style="width: 15%;">Branch / Dept</th>
                    <th style="width: 10%;">Semester</th>
                    <?php if ($activeTab === 'applied'): ?>
                        <th style="width: 10%;">Status</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                foreach ($filteredList as $row): 
                ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['full_name'] ?? $row['name'] ?? 'N/A'); ?></strong></td>
                    <td style="font-family: monospace;"><?php echo htmlspecialchars($row['usn'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['institution'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                    <td>Sem <?php echo htmlspecialchars($row['current_semester'] ?? $row['semester'] ?? '-'); ?></td>
                    <?php if ($activeTab === 'applied'): ?>
                        <td><?php echo htmlspecialchars($row['app_status'] ?? 'Applied'); ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pdf-footer">
            <span>Confidential Document — For Department Academic Evaluation Only</span>
            <span>Total Records in Export: <?php echo count($filteredList); ?></span>
        </div>
    </div>

    <!-- PDF Generation Script -->
    <script>
        function generateStudentReportPDF() {
            const template = document.getElementById('pdfExportTemplate');
            const jobTitle = "<?php echo addslashes(preg_replace('/[^a-zA-Z0-9_-]/', '_', $item['title'])); ?>";
            const tabName = "<?php echo $activeTab; ?>";
            const dateStr = "<?php echo date('Ymd'); ?>";
            
            // Temporarily reveal template for DOM capture
            template.style.display = 'block';

            const opt = {
                margin:       [10, 10, 10, 10],
                filename:     `Roster_${jobTitle}_${tabName}_${dateStr}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };

            // Generate and trigger download
            html2pdf().set(opt).from(template).save().then(() => {
                template.style.display = 'none';
            }).catch((err) => {
                console.error("PDF Export Error:", err);
                template.style.display = 'none';
                alert("Failed to generate PDF. Please try again.");
            });
        }
    </script>
</body>
</html>
