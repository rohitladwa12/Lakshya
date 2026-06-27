<?php
/**
 * Department Coordinator - View Job/Internship Students
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

$department = getDepartment() ?: 'General';
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptGmu = $discipline_filters[0] ?? $department;
$deptGmit = $discipline_filters[1] ?? $department;
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;

require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();
$semRange = getCoordinatorSemesterFilters($department) ?: [1,8];

$overallFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semRange
];
$allStudents = $studentModel->getAllWithUsers($overallFilters);

// Get applied students
if ($type === 'job') {
    $stmt = $db->prepare("SELECT student_id, status FROM job_applications WHERE job_id = ?");
} else {
    $stmt = $db->prepare("SELECT student_id, status FROM internship_applications WHERE internship_id = ?");
}
$stmt->execute([$id]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$appliedUsns = [];
$appStatuses = [];
foreach ($applications as $app) {
    $appliedUsns[] = $app['student_id'];
    $appStatuses[$app['student_id']] = $app['status'];
}

// Ensure applied students from the coordinator's discipline are included in the pool
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

foreach ($allStudents as $stu) {
    $isApplied = false;
    $status = 'Unknown';
    
    if (in_array($stu['usn'], $appliedUsns)) {
        $isApplied = true;
        $status = $appStatuses[$stu['usn']] ?? 'Unknown';
    } elseif (!empty($stu['aadhar']) && in_array($stu['aadhar'], $appliedUsns)) {
        $isApplied = true;
        $status = $appStatuses[$stu['aadhar']] ?? 'Unknown';
    }
    
    if ($isApplied) {
        $stu['app_status'] = $status;
        $appliedStudents[] = $stu;
    } else {
        $notAppliedStudents[] = $stu;
    }
}

// Sort by USN
usort($appliedStudents, function($a, $b) { return strcmp($a['usn'], $b['usn']); });
usort($notAppliedStudents, function($a, $b) { return strcmp($a['usn'], $b['usn']); });

$activeTab = $_GET['tab'] ?? 'applied';
if (!in_array($activeTab, ['applied', 'not_applied'])) {
    $activeTab = 'applied';
}

$searchQuery = $_GET['q'] ?? '';
$semesterFilter = $_GET['semester'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$institutionFilter = $_GET['institution'] ?? '';

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

$listToDisplay = $activeTab === 'applied' ? $appliedStudents : $notAppliedStudents;
$listToDisplay = $filterStudents($listToDisplay);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students List - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: #1e293b;
        }
        
        .main-content { padding: 40px; max-width: 1400px; margin: 0 auto; }
        
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .page-header h2 { font-size: 30px; color: var(--primary-maroon); font-weight: 800; margin-bottom: 8px; }
        .page-header p { color: #64748b; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        
        .back-btn {
            background-color: white; color: #1e293b; border: 1px solid #e2e8f0;
            padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 14px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .back-btn:hover { background-color: #f1f5f9; }

        .tabs {
            display: flex; gap: 15px; margin-bottom: 25px;
        }
        
        .tab-btn {
            padding: 12px 24px; background: white; border: 1px solid #e2e8f0;
            border-radius: 12px; font-size: 15px; font-weight: 700; color: #64748b;
            text-decoration: none; display: flex; align-items: center; gap: 10px;
            box-shadow: var(--shadow); transition: 0.2s;
        }
        
        .tab-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
        
        .tab-btn.active {
            background: var(--primary-maroon); color: white; border-color: var(--primary-maroon);
        }
        
        .tab-badge {
            background: #f1f5f9; color: #1e293b; padding: 2px 8px; border-radius: 6px; font-size: 12px;
        }
        
        .tab-btn.active .tab-badge { background: white; color: var(--primary-maroon); }

        .filter-card {
            background: white; border-radius: 16px; padding: 20px; box-shadow: var(--shadow);
            margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1; padding: 12px 15px; border-radius: 10px; border: 1px solid #e2e8f0;
            font-size: 14px; font-family: 'Outfit'; outline: none; min-width: 250px;
        }
        .search-input:focus { border-color: var(--primary-maroon); }
        
        .status-select {
            padding: 12px 15px; border-radius: 10px; border: 1px solid #e2e8f0;
            font-size: 14px; font-family: 'Outfit'; outline: none; min-width: 150px;
        }
        
        .btn-filter {
            padding: 12px 24px; background: var(--primary-maroon); color: white;
            border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block;
        }
        .btn-filter:hover { background: #600000; }
        .btn-clear {
            padding: 12px 24px; background: #f1f5f9; color: #64748b;
            border: none; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-block; transition: 0.2s;
        }
        .btn-clear:hover { background: #e2e8f0; color: #1e293b; }

        .table-card {
            background: white; border-radius: 20px; box-shadow: var(--shadow); overflow: hidden;
        }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 18px 24px; background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        tr:hover td { background: rgba(128, 0, 0, 0.01); }
        
        .stu-name { font-weight: 700; color: #1e293b; font-size: 15px; margin-bottom: 4px; }
        .stu-usn { font-size: 13px; color: #64748b; font-weight: 600; font-family: monospace; }
        
        .badge { padding: 6px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-applied { background: #e0f2fe; color: #0369a1; }
        .bg-shortlisted { background: #fef08a; color: #854d0e; }
        .bg-selected { background: #bbf7d0; color: #166534; }
        .bg-rejected { background: #fecdd3; color: #9f1239; }
        .bg-unknown { background: #f1f5f9; color: #475569; }

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
            <div>
                <span class="badge bg-unknown"><?php echo ucfirst($type); ?></span>
            </div>
        </div>

        <div class="tabs">
            <a href="?id=<?php echo $id; ?>&type=<?php echo $type; ?>&tab=applied" class="tab-btn <?php echo $activeTab === 'applied' ? 'active' : ''; ?>">
                Applied Students <span class="tab-badge"><?php echo $appliedCount; ?></span>
            </a>
            <a href="?id=<?php echo $id; ?>&type=<?php echo $type; ?>&tab=not_applied" class="tab-btn <?php echo $activeTab === 'not_applied' ? 'active' : ''; ?>">
                Not Applied <span class="tab-badge"><?php echo $notAppliedCount; ?></span>
            </a>
        </div>

        <form method="GET" class="filter-card">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
            
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
            <a href="?id=<?php echo $id; ?>&type=<?php echo htmlspecialchars($type); ?>&tab=<?php echo htmlspecialchars($activeTab); ?>" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Student Details</th>
                        <th>Institution & Branch</th>
                        <th>Semester</th>
                        <?php if($activeTab === 'applied'): ?>
                        <th>Application Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(empty($listToDisplay)): 
                    ?>
                    <tr>
                        <td colspan="<?php echo $activeTab === 'applied' ? 4 : 3; ?>" style="text-align: center; padding: 40px; color: #64748b;">
                            No students found in this category.
                        </td>
                    </tr>
                    <?php else: foreach ($listToDisplay as $stu): ?>
                    <tr>
                        <td>
                            <div class="stu-name"><?php echo htmlspecialchars($stu['full_name'] ?? $stu['name'] ?? 'Unknown'); ?></div>
                            <div class="stu-usn"><?php echo htmlspecialchars($stu['usn'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($stu['institution'] ?? 'N/A'); ?></div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars(($stu['course'] ?? '') . ' - ' . ($stu['department'] ?? '')); ?></div>
                        </td>
                        <td>
                            <span style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; color: #475569;">
                                Sem <?php echo htmlspecialchars($stu['current_semester'] ?? $stu['semester'] ?? '-'); ?>
                            </span>
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
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
