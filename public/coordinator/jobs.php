<?php
/**
 * Department Coordinator - Jobs & Internships
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
$semRange = getCoordinatorSemesterFilters($department) ?: [1,8];

$overallFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semRange
];
$students = $studentModel->getAllWithUsers($overallFilters);
$usns = array_column($students, 'usn');

$db = getDB();

$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['q'] ?? '';

// Fetch Jobs
$sqlJobs = "SELECT jp.id, jp.title, jp.job_type, jp.location, jp.application_deadline as deadline, jp.status, c.name as company_name, 'job' as source_type, jp.posted_date as created_at
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
    $sqlJobs .= " AND (jp.title LIKE ? OR c.name LIKE ?)";
    $paramsJobs[] = "%$searchQuery%";
    $paramsJobs[] = "%$searchQuery%";
}
$stmtJobs = $db->prepare($sqlJobs);
$stmtJobs->execute($paramsJobs);
$jobsList = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

// Fetch Internships
$sqlInt = "SELECT i.id, i.internship_title as title, 'Internship' as job_type, i.location, i.application_deadline as deadline, i.status, i.company_name, 'internship' as source_type, i.created_at
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
    $sqlInt .= " AND (i.internship_title LIKE ? OR i.company_name LIKE ?)";
    $paramsInt[] = "%$searchQuery%";
    $paramsInt[] = "%$searchQuery%";
}
$stmtInt = $db->prepare($sqlInt);
$stmtInt->execute($paramsInt);
$internshipsList = $stmtInt->fetchAll(PDO::FETCH_ASSOC);

// Merge and sort
$allItems = array_merge($jobsList, $internshipsList);
usort($allItems, function($a, $b) {
    return strtotime($b['created_at'] ?? 'now') - strtotime($a['created_at'] ?? 'now');
});

foreach ($allItems as &$item) {
    if ($item['status'] === 'Active' && !empty($item['deadline'])) {
        if (strtotime($item['deadline']) <= time()) {
            $item['status'] = 'Ended';
        }
    }
}
unset($item);

// Calculate stats
if (!empty($usns)) {
    $usnList = "'" . implode("','", array_map('addslashes', $usns)) . "'";
    foreach ($allItems as &$item) {
        if ($item['source_type'] === 'job') {
            $stmtApps = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM job_applications WHERE job_id = ? AND student_id IN ($usnList)");
        } else {
            $stmtApps = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM internship_applications WHERE internship_id = ? AND student_id IN ($usnList)");
        }
        $stmtApps->execute([$item['id']]);
        $applied = (int)$stmtApps->fetchColumn();
        $item['applied_count'] = $applied;
        $item['total_dept_students'] = count($usns);
        $item['not_applied_count'] = max(0, count($usns) - $applied);
    }
} else {
    foreach ($allItems as &$item) {
        $item['applied_count'] = 0;
        $item['total_dept_students'] = 0;
        $item['not_applied_count'] = 0;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs & Internships - <?php echo APP_NAME; ?></title>
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
        
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h2 { font-size: 32px; color: var(--primary-maroon); font-weight: 800; }
        .page-header p { color: #64748b; font-size: 15px; margin-top: 4px; }
        
        .back-btn {
            background-color: white; color: #1e293b; border: 1px solid #e2e8f0;
            padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 14px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .back-btn:hover { background-color: #f1f5f9; }

        .filter-card {
            background: white; border-radius: 16px; padding: 20px; box-shadow: var(--shadow);
            margin-bottom: 30px; display: flex; gap: 15px; align-items: center;
        }
        
        .search-input {
            flex: 1; padding: 12px 15px; border-radius: 10px; border: 1px solid #e2e8f0;
            font-size: 14px; font-family: 'Outfit'; outline: none;
        }
        .search-input:focus { border-color: var(--primary-maroon); }
        
        .status-select {
            padding: 12px 15px; border-radius: 10px; border: 1px solid #e2e8f0;
            font-size: 14px; font-family: 'Outfit'; outline: none; min-width: 150px;
        }
        
        .btn-filter {
            padding: 12px 24px; background: var(--primary-maroon); color: white;
            border: none; border-radius: 10px; font-weight: 600; cursor: pointer;
        }

        .table-card {
            background: white; border-radius: 20px; box-shadow: var(--shadow); overflow: hidden;
        }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 18px 24px; background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        tr:hover td { background: rgba(128, 0, 0, 0.01); }
        
        .job-title { font-weight: 700; color: #1e293b; margin-bottom: 4px; font-size: 15px; }
        .comp-name { font-size: 13px; color: #64748b; font-weight: 500; display: flex; align-items: center; gap: 6px; }
        
        .badge { padding: 6px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-active { background: #ecfdf5; color: #059669; }
        .bg-closed, .bg-ended { background: #fef2f2; color: #dc2626; }
        .bg-draft { background: #f1f5f9; color: #475569; }
        
        .stat-pill {
            padding: 6px 12px; border-radius: 8px; font-size: 14px; font-weight: 800;
            display: inline-block; min-width: 50px; text-align: center;
        }
        .stat-applied { background: #e0f2fe; color: #0369a1; }
        .stat-not { background: #fff1f2; color: #be123c; }

        .btn-view {
            background: white; border: 1px solid #e2e8f0; color: var(--primary-maroon);
            padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 12px;
            text-decoration: none; transition: 0.2s;
            display: inline-block; white-space: nowrap;
        }
        .btn-view:hover {
            background: var(--primary-maroon); color: white; border-color: var(--primary-maroon);
        }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        
        <div class="page-header">
            <div>
                <h2>Jobs & Internships</h2>
                <p><?php echo htmlspecialchars($deptLabel); ?> • Track department applications</p>
            </div>
        </div>

        <form method="GET" class="filter-card">
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by title or company..." class="search-input">
            <select name="status" class="status-select">
                <option value="">All Statuses</option>
                <option value="Active" <?php echo $statusFilter == 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Closed" <?php echo $statusFilter == 'Closed' ? 'selected' : ''; ?>>Closed / Ended</option>
            </select>
            <button type="submit" class="btn-filter">Search</button>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Opportunity</th>
                        <th>Details</th>
                        <th>Deadline</th>
                        <th style="text-align: center;">Students Applied</th>
                        <th style="text-align: center;">Not Applied</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($allItems)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">No jobs or internships found.</td>
                    </tr>
                    <?php else: foreach ($allItems as $item): ?>
                    <tr>
                        <td>
                            <div class="job-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="comp-name"><i class="fas fa-building"></i> <?php echo htmlspecialchars($item['company_name'] ?: 'Company'); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 600; color: #1e293b;"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($item['job_type']); ?></div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 4px;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['location']); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 13px; color: #dc2626; font-weight: 700;">
                                <?php echo date('M d, Y - h:i A', strtotime($item['deadline'] ?? 'now')); ?>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="stat-pill stat-applied"><?php echo $item['applied_count']; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <span class="stat-pill stat-not"><?php echo $item['not_applied_count']; ?></span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo strtolower($item['status'] ?: 'unknown'); ?>">
                                <?php echo htmlspecialchars($item['status'] ?: 'Unknown'); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="job_students.php?id=<?php echo $item['id']; ?>&type=<?php echo $item['source_type']; ?>" class="btn-view">
                                View Students
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
