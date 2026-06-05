<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$coordinatorId = getUserId();
$db = getDB();
$remoteDB = getDB('gmu');

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

$pageId = 'coordinator_ai_monitor';

// Handle POST to save filters to session and redirect to clean URL
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_filters'])) {
        SessionFilterHelper::clearFilters($pageId);
    } else {
        $currentFilters = SessionFilterHelper::getFilters($pageId);
        
        // 1. Handle Search Form submission
        if (isset($_POST['search'])) {
            $currentFilters['search'] = trim($_POST['search']);
            $currentFilters['page'] = 1; // Reset page on new search
        }
        
        // 2. Handle Date Form submission
        if (isset($_POST['date_preset']) || isset($_POST['start_date']) || isset($_POST['end_date'])) {
            $datePreset = $_POST['date_preset'] ?? '';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            
            if ($startDate !== '' || $endDate !== '') {
                $currentFilters['date_preset'] = '';
                $currentFilters['start_date'] = $startDate;
                $currentFilters['end_date'] = $endDate;
            } else {
                $currentFilters['date_preset'] = $datePreset;
                $currentFilters['start_date'] = '';
                $currentFilters['end_date'] = '';
            }
            $currentFilters['page'] = 1; // Reset page on date filter change
        }
        
        SessionFilterHelper::setFilters($pageId, $currentFilters);
    }
    header("Location: ai_monitor.php");
    exit;
}

// Handle GET for pagination redirection to preserve clean URL
if (isset($_GET['page'])) {
    SessionFilterHelper::updateFilters($pageId, ['page' => (int)$_GET['page']]);
    header("Location: ai_monitor.php");
    exit;
}

// 1. Get coordinator's department and institution details
$stmt = $db->prepare("SELECT department, institution FROM dept_coordinators WHERE id = ?");
$stmt->execute([$coordinatorId]);
$coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
$department = $coordinator['department'] ?? 'General';

// Get active filters from session
$filters = SessionFilterHelper::getFilters($pageId);
$dateRangePreset = $filters['date_preset'] ?? 'last_30_days';
$customStartDate = $filters['start_date'] ?? '';
$customEndDate = $filters['end_date'] ?? '';
$search = $filters['search'] ?? '';
$page = (int)($filters['page'] ?? 1);

$startDate = $customStartDate;
$endDate = $customEndDate;

$todayStr = date('Y-m-d');
if (empty($startDate) || empty($endDate)) {
    switch ($dateRangePreset) {
        case 'today':
            $startDate = $todayStr;
            $endDate = $todayStr;
            break;
        case 'yesterday':
            $startDate = date('Y-m-d', strtotime('-1 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'last_7_days':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate = $todayStr;
            break;
        case 'this_month':
            $startDate = date('Y-m-01');
            $endDate = $todayStr; // Default to today instead of end of month for better graph
            break;
        case 'last_30_days':
        default:
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = $todayStr;
            break;
    }
}

$startDateTimeStr = $startDate . ' 00:00:00';
$endDateTimeStr = $endDate . ' 23:59:59';

// 2. Fetch all students matching coordinator filters (department, discipline & semesters)
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();
$semester_filter = getCoordinatorSemesterFilters($department) ?: [1, 8];
$discipline_filters = getCoordinatorDisciplineFilters($department);

$coordFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semester_filter
];

$students = $studentModel->getAllWithUsers($coordFilters);

// Build unified student roster of this department
$roster = [];
$allStudentIds = [];

foreach ($students as $s) {
    $usn = trim((string)($s['usn'] ?? ''));
    if (!empty($usn)) {
        $roster[$usn] = [
            'name' => trim((string)($s['name'] ?? '')),
            'usn' => $usn,
            'institution' => $s['institution'] ?? 'N/A',
            'logins' => 0
        ];
        $allStudentIds[] = $usn;
    }
}

$allStudentIds = array_values(array_unique(array_filter($allStudentIds)));
if (empty($allStudentIds)) {
    $allStudentIds = ['__NONE__'];
}

// 3. Query total login counts per student within the date range
$placeholders = implode(',', array_fill(0, count($allStudentIds), '?'));
$loginCountsQuery = "SELECT user_id, COUNT(*) as count 
                     FROM activity_logs 
                     WHERE action = 'login' 
                       AND user_id IN ($placeholders) 
                       AND created_at >= ? 
                       AND created_at <= ? 
                     GROUP BY user_id";
$stmtLogs = $db->prepare($loginCountsQuery);
$stmtLogs->execute(array_merge($allStudentIds, [$startDateTimeStr, $endDateTimeStr]));
while ($row = $stmtLogs->fetch(PDO::FETCH_ASSOC)) {
    $uid = $row['user_id'];
    $cnt = (int)$row['count'];
    if (isset($roster[$uid])) {
        $roster[$uid]['logins'] = $cnt;
    } else {
        // Double-check case-insensitively or via alternative ID matching
        foreach ($roster as $usnKey => $student) {
            if (strcasecmp($usnKey, $uid) === 0) {
                $roster[$usnKey]['logins'] = $cnt;
            }
        }
    }
}

// 4. Query daily active login trends within the date range
$dailyTrend = [];
$dailyQuery = "SELECT DATE(created_at) as log_date, COUNT(*) as total_logins, COUNT(DISTINCT user_id) as active_users 
               FROM activity_logs 
               WHERE action = 'login' 
                 AND user_id IN ($placeholders) 
                 AND created_at >= ? 
                 AND created_at <= ? 
               GROUP BY DATE(created_at)
               ORDER BY log_date ASC";
$stmtDaily = $db->prepare($dailyQuery);
$stmtDaily->execute(array_merge($allStudentIds, [$startDateTimeStr, $endDateTimeStr]));
$dailyTrend = $stmtDaily->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Populate default fallback trend data if logs are empty (to keep the graph populated and interactive)
if (empty($dailyTrend)) {
    try {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
        foreach ($period as $date) {
            $dailyTrend[] = [
                'log_date' => $date->format('Y-m-d'),
                'total_logins' => 0,
                'active_users' => 0
            ];
        }
    } catch (Throwable $t) {
        for ($i = 29; $i >= 0; $i--) {
            $log_date = date('Y-m-d', strtotime("-$i days"));
            $dailyTrend[] = [
                'log_date' => $log_date,
                'total_logins' => 0,
                'active_users' => 0
            ];
        }
    }
}

// 5. Query recent login events for this department within the date range
$recentLogins = [];
$recentQuery = "SELECT * FROM activity_logs 
                WHERE action = 'login' 
                  AND user_id IN ($placeholders) 
                  AND created_at >= ? 
                  AND created_at <= ? 
                ORDER BY created_at DESC LIMIT 10";
$stmtRecent = $db->prepare($recentQuery);
$stmtRecent->execute(array_merge($allStudentIds, [$startDateTimeStr, $endDateTimeStr]));
$recentLogins = $stmtRecent->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Calculate stats summary
$totalStudentsCount = $studentModel->getTotalAcademicStrength($coordFilters);
$activeStudentsCount = count(array_filter($roster, function($s) { return $s['logins'] > 0; }));
$inactiveStudentsCount = max(0, $totalStudentsCount - $activeStudentsCount);
$engagementRate = $totalStudentsCount > 0 ? round(($activeStudentsCount / $totalStudentsCount) * 100, 1) : 0;

// Logins today
$loginsToday = 0;
$todayStr = date('Y-m-d');
foreach ($dailyTrend as $d) {
    if ($d['log_date'] === $todayStr) {
        $loginsToday = (int)$d['total_logins'];
        break;
    }
}

// 6. Roster filtering, sorting & pagination
$filteredRoster = $roster;

if ($search !== '') {
    $filteredRoster = array_filter($roster, function($s) use ($search) {
        return (strpos(strtolower($s['name']), strtolower($search)) !== false ||
                strpos(strtolower($s['usn']), strtolower($search)) !== false);
    });
}

// Sort roster: logins desc, then name asc
usort($filteredRoster, function($a, $b) {
    if ($b['logins'] === $a['logins']) {
        return strcmp($a['name'], $b['name']);
    }
    return $b['logins'] <=> $a['logins'];
});

$rosterLimit = 15;
$totalRosterCount = count($filteredRoster);
$totalPages = max(1, ceil($totalRosterCount / $rosterLimit));
$page = max(1, $page);
$offset = ($page - 1) * $rosterLimit;
$paginatedRoster = array_slice($filteredRoster, $offset, $rosterLimit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Logins & Engagement Monitor — <?php echo htmlspecialchars($department); ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #1a1a1a;
            --accent-blue: #0066cc;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .main-content {
            padding: 40px 50px;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            margin-top: 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 25px 35px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .header-title h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-maroon);
        }
        
        .header-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: var(--white);
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(128, 0, 0, 0.05);
        }

        .metric-card .label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.8px;
            margin-bottom: 10px;
        }

        .metric-card .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .metric-card .trend {
            font-size: 12px;
            margin-top: 6px;
            color: var(--text-muted);
        }

        /* Layout Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 25px;
            margin-bottom: 40px;
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .content-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: #fafafb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        /* Search Form */
        .search-container {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            max-width: 250px;
        }
        .search-container input {
            background: transparent;
            border: none;
            outline: none;
            font-size: 13px;
            padding: 4px 6px;
            width: 100%;
        }
        .search-container button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
        }

        .table-responsive {
            overflow-x: auto;
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 14px 20px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            padding: 20px;
            border-top: 1px solid var(--border);
            background: #fafafb;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .pagination-btn:hover:not(.disabled) {
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
            background: #fff7ed;
        }

        .pagination-btn.active {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .text-center { text-align: center; }

        .student-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .student-name {
            font-weight: 700;
            color: #111;
        }
        .student-usn {
            font-size: 11px;
            color: var(--text-muted);
            font-family: monospace;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="header-section">
            <div class="header-title">
                <h1>Student Login & Engagement Monitor</h1>
                <p>Track daily active student logins and total engagement for the department of <strong><?php echo htmlspecialchars($department); ?></strong></p>
            </div>
        </div>

        <!-- Date Range Filter Form -->
        <form method="POST" action="ai_monitor.php" style="background: white; border: 1px solid var(--border); border-radius: 20px; padding: 20px 25px; margin-bottom: 30px; box-shadow: var(--shadow); display: flex; flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="far fa-calendar-alt" style="color: var(--primary-maroon);"></i>
                    <select name="date_preset" style="padding: 10px 18px; border-radius: 10px; border: 1px solid var(--border); font-family: 'Outfit', sans-serif; font-size: 14px; background: white; cursor: pointer; min-width: 150px;" onchange="this.form.submit()">
                        <?php if (empty($dateRangePreset)): ?>
                            <option value="" selected>-- Custom Range --</option>
                        <?php endif; ?>
                        <option value="today" <?php echo $dateRangePreset === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $dateRangePreset === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="last_7_days" <?php echo $dateRangePreset === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="last_30_days" <?php echo $dateRangePreset === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="this_month" <?php echo $dateRangePreset === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">From:</span>
                    <input type="date" name="start_date" style="padding: 9px 15px; border-radius: 10px; border: 1px solid var(--border); font-family: 'Outfit', sans-serif; font-size: 13px;" value="<?php echo htmlspecialchars($customStartDate); ?>">
                    
                    <span style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">To:</span>
                    <input type="date" name="end_date" style="padding: 9px 15px; border-radius: 10px; border: 1px solid var(--border); font-family: 'Outfit', sans-serif; font-size: 13px;" value="<?php echo htmlspecialchars($customEndDate); ?>">
                </div>
                
                <button type="submit" style="background: var(--primary-maroon); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; font-family: 'Outfit', sans-serif; transition: opacity 0.2s;"><i class="fas fa-filter"></i> Apply Dates</button>
            </div>
            
            <button type="submit" name="reset_filters" value="1" style="background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: 'Outfit', sans-serif; transition: all 0.2s;"><i class="fas fa-undo"></i> Reset Filters</button>
        </form>

        <!-- Metrics Grid -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="label"><i class="fas fa-user-graduate"></i> Total Strength</div>
                <div class="value"><?php echo number_format($totalStudentsCount); ?></div>
                <div class="trend">Total registered students</div>
            </div>

            <div class="metric-card">
                <div class="label"><i class="fas fa-sign-in-alt"></i> Logins Today</div>
                <div class="value"><?php echo number_format($loginsToday); ?></div>
                <div class="trend">Login actions today</div>
            </div>

            <div class="metric-card">
                <div class="label"><i class="fas fa-user-check"></i> Engaged Students</div>
                <div class="value"><?php echo number_format($activeStudentsCount); ?></div>
                <div class="trend"><?php echo number_format($inactiveStudentsCount); ?> students inactive</div>
            </div>

            <div class="metric-card">
                <div class="label"><i class="fas fa-chart-pie"></i> Engagement Rate</div>
                <div class="value"><?php echo $engagementRate; ?>%</div>
                <div class="trend">Students with at least 1 login</div>
            </div>
        </div>

        <!-- Daily Logins Chart -->
        <div class="content-card" style="margin-bottom: 30px; padding: 25px;">
            <div style="font-weight: 700; font-size: 16px; margin-bottom: 15px; color: var(--primary-dark);">
                <i class="fas fa-chart-line" style="margin-right: 8px; color: var(--primary-maroon);"></i> Daily Active Logins Trend (Last 30 Days)
            </div>
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="dailyLoginsChart"></canvas>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            
            <!-- Student Engagement Roster -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Student Engagement Roster</h2>
                    <form method="POST" action="ai_monitor.php" class="search-container">
                        <input type="text" name="search" placeholder="Search name or USN..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>Source</th>
                                <th class="text-center">Total Logins</th>
                                <th>Engagement Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paginatedRoster)): ?>
                                <tr><td colspan="4" class="text-center" style="padding: 30px; color: var(--text-muted);">No student logs match your filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($paginatedRoster as $student): ?>
                                <tr>
                                    <td>
                                        <div class="student-meta">
                                            <span class="student-name"><?php echo htmlspecialchars($student['name']); ?></span>
                                            <span class="student-usn"><?php echo htmlspecialchars($student['usn']); ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo $student['institution']; ?></strong></td>
                                    <td class="text-center"><?php echo $student['logins']; ?></td>
                                    <td>
                                        <?php if ($student['logins'] >= 15): ?>
                                            <span class="badge badge-success">Highly Engaged</span>
                                        <?php elseif ($student['logins'] >= 5): ?>
                                            <span class="badge badge-info">Active</span>
                                        <?php elseif ($student['logins'] >= 1): ?>
                                            <span class="badge badge-warning">Low Activity</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Never Logged In</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = $i === $page ? 'active' : '';
                        echo "<a href='?page=$i' class='pagination-btn $activeClass'>$i</a>";
                    }
                    ?>
                    
                    <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Logins Stream -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Live Login Stream</h2>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Student details</th>
                                <th>Institution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLogins)): ?>
                                <tr><td colspan="3" class="text-center" style="padding: 30px; color: var(--text-muted);">No login events found for your department.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLogins as $log): ?>
                                <?php 
                                    $sUsn = $log['user_id'];
                                    $student = $roster[$sUsn] ?? null;
                                    $sName = $student ? $student['name'] : 'Student Profile';
                                    $sInst = $student ? $student['institution'] : 'N/A';
                                ?>
                                <tr>
                                    <td style="color: var(--text-muted); font-size: 12px; white-space: nowrap;">
                                        <?php echo date('d M, H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="student-meta">
                                            <span class="student-name"><?php echo htmlspecialchars($sName); ?></span>
                                            <span class="student-usn"><?php echo htmlspecialchars($sUsn); ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo $sInst; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Draw Chart.js Line Chart
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('dailyLoginsChart').getContext('2d');
            
            const rawData = <?php echo json_encode($dailyTrend); ?>;
            const labels = rawData.map(d => {
                const date = new Date(d.log_date);
                return date.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
            });
            const logins = rawData.map(d => d.total_logins);
            const users = rawData.map(d => d.active_users);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Login Operations',
                            data: logins,
                            borderColor: '#800000',
                            backgroundColor: 'rgba(128, 0, 0, 0.05)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointBackgroundColor: '#800000',
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Unique Active Students',
                            data: users,
                            borderColor: '#0066cc',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.35,
                            pointBackgroundColor: '#0066cc',
                            pointHoverRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    family: "'Outfit', sans-serif",
                                    size: 12
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    family: "'Outfit', sans-serif"
                                }
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    family: "'Outfit', sans-serif"
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });

        // Auto-submit search form on debounce with premium UI loading state
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.querySelector('.search-container input[name="search"]');
            if (searchInput) {
                // Keep focus and place cursor at the end of the search query after reload
                if (searchInput.value !== '') {
                    searchInput.focus();
                    const val = searchInput.value;
                    searchInput.value = '';
                    searchInput.value = val;
                }

                let debounceTimer;
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        searchInput.form.dispatchEvent(new Event('submit'));
                        searchInput.form.submit();
                    }, 600);
                });

                searchInput.form.addEventListener('submit', function() {
                    const btnIcon = searchInput.form.querySelector('button i');
                    if (btnIcon) {
                        btnIcon.className = 'fas fa-spinner fa-spin';
                    }
                });
            }
        });
    </script>
    <script src="<?php echo APP_URL; ?>/js/maintenance_interceptor.js"></script>
</body>
</html>
