<?php
/**
 * Department Coordinator - View Job/Internship Students
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_PLACEMENT_OFFICER);

$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'job';

if (!$id || !in_array($type, ['job', 'internship'])) {
    header("Location: jobs.php");
    exit;
}

$db = getDB();

// Fetch title
if ($type === 'job') {
    $stmt = $db->prepare("SELECT jp.title, jp.status, c.name as company_name FROM job_postings jp LEFT JOIN companies c ON jp.company_id = c.id WHERE jp.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("SELECT internship_title as title, status, company_name FROM internships WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$item) {
    header("Location: jobs.php");
    exit;
}

$department = 'All';
$deptLabel = 'All Departments';
$semRange = [1,2,3,4,5,6,7,8];

// Get applied students
if ($type === 'job') {
    $stmt = $db->prepare("SELECT student_id, status, resume_path, applied_semester, applied_sgpa FROM job_applications WHERE job_id = ?");
} else {
    $stmt = $db->prepare("SELECT student_id, status, resume_path, applied_semester, applied_sgpa FROM internship_applications WHERE internship_id = ?");
}
$stmt->execute([$id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$appliedUsns = [];
$appStatuses = [];
$appResumes = [];
$appSemesters = [];
$appSgpas = [];
foreach ($applications as $app) {
    $appliedUsns[] = $app['student_id'];
    $appStatuses[$app['student_id']] = $app['status'];
    $appResumes[$app['student_id']] = $app['resume_path'];
    $appSemesters[$app['student_id']] = $app['applied_semester'];
    $appSgpas[$app['student_id']] = $app['applied_sgpa'];
}

$appliedStudents = [];
if (!empty($appliedUsns)) {
    require_once __DIR__ . '/../../src/Models/StudentProfile.php';
    $studentModel = new StudentProfile();
    $overallFilters = ['usns' => array_values(array_unique($appliedUsns))];
    $allStudents = $studentModel->getAllWithUsers($overallFilters);
    
    foreach ($allStudents as $stu) {
        $isApplied = false;
        $status = 'Unknown';
        $resume = null;
        
        if (in_array($stu['usn'], $appliedUsns)) {
            $isApplied = true;
            $status = $appStatuses[$stu['usn']] ?? 'Unknown';
            $resume = $appResumes[$stu['usn']] ?? null;
        } elseif (!empty($stu['aadhar']) && in_array($stu['aadhar'], $appliedUsns)) {
            $isApplied = true;
            $status = $appStatuses[$stu['aadhar']] ?? 'Unknown';
            $resume = $appResumes[$stu['aadhar']] ?? null;
        }
        
        if ($isApplied) {
            $stu['app_status'] = $status;
            $stu['resume_path'] = $resume;
            
            $key = in_array($stu['usn'], $appliedUsns) ? $stu['usn'] : $stu['aadhar'];
            $stu['applied_semester_val'] = $appSemesters[$key] ?? null;
            $stu['applied_sgpa_val'] = $appSgpas[$key] ?? null;
            
            $appliedStudents[] = $stu;
        }
    }
}

// Sort by USN
usort($appliedStudents, function($a, $b) { return strcmp($a['usn'], $b['usn']); });

$activeTab = 'applied';

$searchQuery = $_GET['q'] ?? '';
$semesterFilter = $_GET['semester'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$institutionFilter = $_GET['institution'] ?? '';

$filterStudents = function($list) use ($searchQuery, $semesterFilter, $statusFilter, $institutionFilter) {
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
        
        if ($statusFilter) {
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

$listToDisplay = $filterStudents($appliedStudents);

// Batch fetch 10th & 12th percentages, gender, mobile, and resume email
if (!empty($listToDisplay)) {
    $gmuUsns = [];
    $gmitUsns = [];
    $allUsns = [];
    foreach ($listToDisplay as $stu) {
        $allUsns[] = $stu['usn'];
        if (($stu['institution'] ?? '') === 'GMU') $gmuUsns[] = $stu['usn'];
        else $gmitUsns[] = $stu['usn'];
    }
    
    $studentAcademics = [];
    
    if (!empty($gmuUsns)) {
        $dbGmu = getDB('gmu');
        if ($dbGmu) {
            $inQuery = implode(',', array_fill(0, count($gmuUsns), '?'));
            $stmt = $dbGmu->prepare("SELECT usn, student_id, sslc_percentage, puc_percentage, gender, student_mobile FROM ad_student_details WHERE usn IN ($inQuery) OR student_id IN ($inQuery)");
            $stmt->execute(array_merge($gmuUsns, $gmuUsns));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['usn']) $studentAcademics[$row['usn']] = $row;
                if ($row['student_id']) $studentAcademics[$row['student_id']] = $row;
            }
            
            // GMU SGPA
            $stmtSgpa = $dbGmu->prepare("SELECT usn, MAX(sgpa) as max_sgpa FROM ad_student_approved WHERE usn IN ($inQuery) AND sgpa > 0 GROUP BY usn");
            $stmtSgpa->execute($gmuUsns);
            while ($row = $stmtSgpa->fetch(PDO::FETCH_ASSOC)) {
                if (isset($studentAcademics[$row['usn']])) $studentAcademics[$row['usn']]['sgpa'] = $row['max_sgpa'];
                else $studentAcademics[$row['usn']] = ['sgpa' => $row['max_sgpa'], 'sslc_percentage' => 0, 'puc_percentage' => 0];
            }
        }
    }
    
    if (!empty($gmitUsns)) {
        $dbGmit = getDB('gmit');
        if ($dbGmit) {
            $gmitIdentifiers = [];
            $gmitMapById = [];
            foreach ($gmitUsns as $u) {
                $gmitIdentifiers[] = $u;
                $gmitMapById[$u] = $u;
            }

            $inQuery = implode(',', array_fill(0, count($gmitUsns), '?'));
            $stmt = $dbGmit->prepare("SELECT usn, student_id, aadhar, sslc_percentage, puc_percentage, gender, student_mobile FROM ad_student_details WHERE usn IN ($inQuery) OR student_id IN ($inQuery)");
            $stmt->execute(array_merge($gmitUsns, $gmitUsns));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $usnKey = $row['usn'] ?: $row['student_id'];
                if ($usnKey) {
                    $studentAcademics[$usnKey] = $row;
                }
                if (!empty($row['aadhar'])) {
                    $gmitIdentifiers[] = $row['aadhar'];
                    $gmitMapById[$row['aadhar']] = $usnKey;
                }
            }
            $gmitIdentifiers = array_values(array_unique(array_filter($gmitIdentifiers)));
            
            // GMIT SGPA
            if (!empty($gmitIdentifiers)) {
                $inQuerySgpa = implode(',', array_fill(0, count($gmitIdentifiers), '?'));
                $stmtSgpa = $db->prepare("SELECT student_id, MAX(sgpa) as max_sgpa FROM student_sem_sgpa WHERE institution = ? AND student_id IN ($inQuerySgpa) AND sgpa > 0 GROUP BY student_id");
                $stmtSgpa->execute(array_merge([INSTITUTION_GMIT], $gmitIdentifiers));
                while ($row = $stmtSgpa->fetch(PDO::FETCH_ASSOC)) {
                    $mappedUsn = $gmitMapById[$row['student_id']] ?? $row['student_id'];
                    if (isset($studentAcademics[$mappedUsn])) {
                        $studentAcademics[$mappedUsn]['sgpa'] = $row['max_sgpa'];
                    } else {
                        $studentAcademics[$mappedUsn] = ['sgpa' => $row['max_sgpa'], 'sslc_percentage' => 0, 'puc_percentage' => 0];
                    }
                }
            }
        }
    }
    
    // Fetch emails from student_resumes
    $resumeEmails = [];
    if (!empty($allUsns)) {
        $inQuery = implode(',', array_fill(0, count($allUsns), '?'));
        $stmtResume = $db->prepare("SELECT student_id, email FROM student_resumes WHERE student_id IN ($inQuery)");
        $stmtResume->execute($allUsns);
        while ($row = $stmtResume->fetch(PDO::FETCH_ASSOC)) {
            $resumeEmails[$row['student_id']] = $row['email'];
        }
    }
    
    foreach ($listToDisplay as &$stu) {
        $ac = $studentAcademics[$stu['usn']] ?? null;
        $stu['sslc_percentage'] = $ac ? ($ac['sslc_percentage'] ?? 0) : 0;
        $stu['puc_percentage'] = $ac ? ($ac['puc_percentage'] ?? 0) : 0;
        
        // Prioritize static snapshot values if student is applied and values exist
        if ($activeTab === 'applied' && isset($stu['applied_semester_val']) && $stu['applied_semester_val'] !== null) {
            $stu['current_semester'] = $stu['applied_semester_val'];
            $stu['semester'] = $stu['applied_semester_val'];
            $stu['academic_sgpa'] = $stu['applied_sgpa_val'] ?? 0.00;
        } else {
            $stu['academic_sgpa'] = $ac ? ($ac['sgpa'] ?? 0) : 0;
        }
        
        $stu['gender'] = $ac ? ($ac['gender'] ?? 'N/A') : 'N/A';
        $stu['student_mobile'] = $ac ? ($ac['student_mobile'] ?? 'N/A') : 'N/A';
        $stu['resume_email'] = $resumeEmails[$stu['usn']] ?? 'N/A';
    }
    unset($stu);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students List - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
            padding-top: 0;
            min-height: 100vh;
        }
        
        .main-content { padding: 32px 40px; max-width: 1400px; margin: 0 auto; }
        
        .page-header { 
            margin-bottom: 24px; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-end; 
            background: var(--card-bg);
            padding: 24px 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .page-header h2 { font-size: 22px; color: var(--maroon); font-weight: 700; margin-bottom: 6px; }
        .page-header p { color: var(--text-secondary); font-size: 13.5px; display: flex; align-items: center; gap: 8px; }
        
        .back-btn {
            background-color: var(--card-bg); color: var(--text-secondary); border: 1px solid var(--border-color);
            padding: 8px 16px; border-radius: var(--radius-sm); font-weight: 600; font-size: 13px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;
        }
        .back-btn:hover { background-color: #F3F4F6; color: var(--text-primary); border-color: #D1D5DB; }

        .tabs {
            display: flex; gap: 12px; margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 10px 20px; background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); font-size: 13.5px; font-weight: 600; color: var(--text-secondary);
            text-decoration: none; display: flex; align-items: center; gap: 8px;
            box-shadow: var(--shadow-sm); transition: 0.15s ease;
        }
        
        .tab-btn:hover { background: #F9FAFB; border-color: #D1D5DB; color: var(--text-primary); }
        
        .tab-btn.active {
            background: var(--maroon); color: #FFFFFF; border-color: var(--maroon);
        }
        
        .tab-badge {
            background: #F3F4F6; color: var(--text-primary); padding: 2px 8px; border-radius: 9999px; font-size: 11.5px; font-weight: 700;
        }
        
        .tab-btn.active .tab-badge { background: rgba(255, 255, 255, 0.2); color: #FFFFFF; }

        .filter-card {
            background: var(--card-bg); border-radius: var(--radius-lg); padding: 18px 24px; box-shadow: var(--shadow-sm);
            margin-bottom: 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid var(--border-color);
        }
        
        .search-input {
            flex: 1; padding: 9px 14px; border-radius: var(--radius-sm); border: 1px solid #D1D5DB;
            font-size: 13.5px; font-family: inherit; outline: none; min-width: 240px; color: var(--text-primary);
        }
        .search-input:focus { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(124, 0, 0, 0.12); }
        
        .status-select {
            padding: 9px 14px; border-radius: var(--radius-sm); border: 1px solid #D1D5DB;
            font-size: 13.5px; font-family: inherit; outline: none; min-width: 140px; color: var(--text-primary); background: #FFFFFF;
        }
        .status-select:focus { border-color: var(--maroon); }
        
        .btn-filter {
            padding: 9px 18px; background: var(--maroon); color: #FFFFFF;
            border: none; border-radius: var(--radius-sm); font-weight: 600; font-size: 13px; cursor: pointer; transition: 0.15s ease; text-decoration: none; display: inline-block;
        }
        .btn-filter:hover { background: var(--maroon-dark); }
        .btn-clear {
            padding: 9px 18px; background: #F3F4F6; color: var(--text-secondary);
            border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block; transition: 0.15s ease;
        }
        .btn-clear:hover { background: #E5E7EB; color: var(--text-primary); }

        .table-card {
            background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; border: 1px solid var(--border-color);
        }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        th { padding: 12px 16px; background: #F9FAFB; color: var(--text-secondary); font-size: 11.5px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border-color); letter-spacing: 0.04em; }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); font-size: 13.5px; vertical-align: middle; }
        tr:hover td { background: #FDFEFE; }
        
        .stu-name { font-weight: 700; color: var(--text-primary); font-size: 14px; margin-bottom: 2px; }
        .stu-usn { font-size: 12px; color: var(--text-secondary); font-weight: 600; font-family: monospace; }
        
        .badge { padding: 4px 10px; border-radius: 9999px; font-size: 11.5px; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .bg-applied { background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE; }
        .bg-shortlisted { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
        .bg-selected { background: #ECFDF5; color: #047857; border: 1px solid #D1FAE5; }
        .bg-rejected { background: #FEF2F2; color: #DC2626; border: 1px solid #FEE2E2; }
        .bg-unknown { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }

        .btn-action {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: var(--maroon); color: #FFFFFF; }
        .btn-primary:hover { background: var(--maroon-dark); }
        .btn-excel { background: #059669; color: #FFFFFF; }
        .btn-excel:hover { background: #047857; }

        .print-only { display: none; }
        @media print {
            .no-print, header, nav, .navbar, .tabs, .filter-card, .back-btn { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; padding-top: 0 !important; color: black !important; }
            .main-content { padding: 0 !important; margin: 0 !important; max-width: none !important; }
            .page-header { margin-bottom: 20px !important; }
            .table-card { border: none !important; box-shadow: none !important; background: transparent !important; overflow: visible !important; }
            table { border-collapse: collapse !important; width: 100% !important; border: 1px solid black !important; }
            th, td { 
                border: 1px solid #000 !important; 
                padding: 4px 6px !important; 
                font-size: 10px !important; 
                color: black !important;
                background: white !important;
                text-align: left !important;
            }
            th { font-weight: bold !important; }
            .stu-name, .stu-usn { margin: 0 !important; font-size: 10px !important; }
            .badge { background: transparent !important; color: black !important; border: none !important; padding: 0 !important; font-weight: normal !important; }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <a href="jobs.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Jobs</a>
        </div>
        
        <div class="page-header">
            <div>
                <h2><?php echo htmlspecialchars($item['title']); ?></h2>
                <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($item['company_name'] ?: 'Company'); ?> &nbsp;&bull;&nbsp; <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($deptLabel); ?></p>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <span class="badge bg-unknown"><?php echo ucfirst($type); ?></span>
                <button onclick="exportToExcel()" class="btn-action btn-excel no-print">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button onclick="window.print()" class="btn-action btn-primary no-print">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <div class="tabs">
            <a href="?id=<?php echo $id; ?>&type=<?php echo $type; ?>" class="tab-btn active">
                Applied Students <span class="tab-badge"><?php echo $appliedCount; ?></span>
            </a>
        </div>

        <form method="GET" class="filter-card">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name or USN..." class="search-input">
            
            <select name="semester" class="status-select">
                <option value="">All Semesters</option>
                <?php foreach($semRange as $sem): ?>
                <option value="<?php echo $sem; ?>" <?php echo $semesterFilter == $sem ? 'selected' : ''; ?>>Semester <?php echo $sem; ?></option>
                <?php endforeach; ?>
            </select>

            <select name="institution" class="status-select">
                <option value="">All Institutions</option>
                <option value="GMIT" <?php echo $institutionFilter === 'GMIT' ? 'selected' : ''; ?>>GMIT</option>
                <option value="GMU" <?php echo $institutionFilter === 'GMU' ? 'selected' : ''; ?>>GMU</option>
            </select>
            
            <?php if($activeTab === 'applied'): ?>
            <select name="status" class="status-select">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Applied / Pending</option>
                <option value="Shortlisted" <?php echo $statusFilter === 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                <option value="Selected" <?php echo $statusFilter === 'Selected' ? 'selected' : ''; ?>>Selected</option>
                <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <?php endif; ?>
            
            <button type="submit" class="btn-filter">Filter</button>
            <?php if($searchQuery || $semesterFilter || $statusFilter || $institutionFilter): ?>
            <a href="?id=<?php echo $id; ?>&type=<?php echo htmlspecialchars($type); ?>" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-card" style="overflow-x: auto;">
            <table id="exportTable">
                <thead>
                    <tr>
                        <th>Sl.No</th>
                        <th>Name</th>
                        <th>USN</th>
                        <th>Gender</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>College</th>
                        <th>Branch</th>
                        <th>Sem</th>
                        <th>SGPA</th>
                        <th>10th %</th>
                        <th>12th %</th>
                        <th>Job Name</th>
                        <?php if($activeTab === 'applied'): ?>
                        <th>Status</th>
                        <?php endif; ?>
                        <th class="no-print">Resume</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(empty($listToDisplay)): 
                    ?>
                    <tr>
                        <td colspan="<?php echo $activeTab === 'applied' ? 15 : 14; ?>" style="text-align: center; padding: 40px; color: #64748b;">
                            No students found in this category.
                        </td>
                    </tr>
                    <?php else: 
                        $slNo = 1;
                        foreach ($listToDisplay as $stu): 
                    ?>
                    <tr data-resume-link="<?php echo !empty($stu['resume_path']) ? (APP_URL . '/student/view_resume.php?usn=' . urlencode($stu['usn'])) : 'No Resume'; ?>">
                        <td><?php echo $slNo++; ?></td>
                        <td>
                            <div class="stu-name"><?php echo htmlspecialchars($stu['full_name'] ?? $stu['name'] ?? 'Unknown'); ?></div>
                        </td>
                        <td>
                            <div class="stu-usn"><?php echo htmlspecialchars($stu['usn'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 500; color: #1e293b;"><?php echo htmlspecialchars($stu['gender'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 500; color: #1e293b;"><?php echo htmlspecialchars($stu['resume_email'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 500; color: #1e293b;"><?php echo htmlspecialchars($stu['student_mobile'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($stu['institution'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; color: #1e293b;"><?php echo htmlspecialchars(($stu['course'] ?? '') . ' - ' . ($stu['department'] ?? '')); ?></div>
                        </td>
                        <td>
                            <span style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; color: #475569;">
                                <?php echo htmlspecialchars($stu['current_semester'] ?? $stu['semester'] ?? '-'); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #10b981;"><?php echo !empty($stu['academic_sgpa']) ? number_format($stu['academic_sgpa'], 2) : '-'; ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px;"><?php echo $stu['sslc_percentage'] ? round($stu['sslc_percentage']).'%' : '-'; ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px;"><?php echo $stu['puc_percentage'] ? round($stu['puc_percentage']).'%' : '-'; ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 600; color: #475569;"><?php echo htmlspecialchars($item['title'] ?? 'N/A'); ?></div>
                        </td>
                        <?php if($activeTab === 'applied'): ?>
                        <td>
                            <?php 
                                $statusClass = 'bg-unknown';
                                $st = strtolower($stu['app_status']);
                                if(strpos($st, 'applied') !== false || strpos($st, 'pending') !== false) $statusClass = 'bg-applied';
                                elseif(strpos($st, 'shortlist') !== false) $statusClass = 'bg-shortlisted';
                                elseif(strpos($st, 'select') !== false || strpos($st, 'offer') !== false) $statusClass = 'bg-selected';
                                elseif(strpos($st, 'reject') !== false) $statusClass = 'bg-rejected';
                            ?>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($stu['app_status']); ?>
                            </span>
                        </td>
                        <?php endif; ?>
                        <td class="no-print">
                            <?php if (!empty($stu['resume_path'])): ?>
                                <a href="../student/view_resume.php?usn=<?php echo urlencode($stu['usn']); ?>" target="_blank" style="color: var(--primary-maroon); text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-file-pdf"></i> View
                                </a>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: 12px;"><i class="fas fa-times-circle"></i> N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function exportToExcel() {
            const table = document.getElementById("exportTable");
            const rows = Array.from(table.rows);
            
            // Get current active tab name
            const activeTab = document.querySelector('.tab-btn.active').innerText.split('\n')[0].trim();
            const jobTitle = "<?php echo addslashes($item['title']); ?>";
            
            const rawData = rows.map((row, rowIndex) => {
                const cells = Array.from(row.cells);
                // Exclude last column (Resume) if it's the view button, but we want the link in excel
                // For print/export we skip `.no-print` columns in processing, but for Excel we want the link
                const rowData = [];
                for (let i = 0; i < cells.length; i++) {
                    if (!cells[i].classList.contains('no-print')) {
                        rowData.push(cells[i].innerText.split('\n')[0].trim());
                    }
                }
                
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
                        ws[cellAddress].s = { font: { color: { rgb: "0563C1" }, underline: true } };
                    }
                }
            }
            
            XLSX.utils.book_append_sheet(wb, ws, "Students");
            XLSX.writeFile(wb, jobTitle + "_" + activeTab + "_Students.xlsx");
        }
    </script>
</body>
</html>
