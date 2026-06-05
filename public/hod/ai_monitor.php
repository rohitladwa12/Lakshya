<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$fullName = getFullName();
$db = getDB();
$remoteDB = getDB('gmu');

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

$pageId = 'hod_ai_monitor';

// Handle AJAX Request for Student Personalized Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_student_report') {
    header('Content-Type: application/json');
    $studentId = trim($_POST['student_id'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    
    if (empty($studentId)) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        exit;
    }
    
    try {
        // 1. Fetch Student general details using the unified StudentProfile model
        require_once __DIR__ . '/../../src/Models/StudentProfile.php';
        $studentModel = new StudentProfile();
        $student = $studentModel->getByUserId($studentId, $institution);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        // 2. Fetch Tab 1: Logins & Activity Stream
        $stmt = $db->prepare("SELECT action, description, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
        $stmt->execute([$studentId]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // 3. Fetch Tab 2: Mock AI Assessments
        $stmt = $db->prepare("SELECT id, role_name as job_title, overall_score as score, status, completed_at as created_at FROM mock_ai_interview_sessions WHERE student_id = ? ORDER BY completed_at DESC");
        $stmt->execute([$studentId]);
        $interviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // 4. Fetch Tab 3: Strengths, Weaknesses & AI Profile
        $stmt = $db->prepare("SELECT predicted_role, confidence_score, detected_interests, personality_pref, ai_summary FROM student_ai_profiles WHERE student_id = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $aiProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        // Fetch Mock Interview Performance
        $stmt = $db->prepare("
            SELECT 
                role_name as domain,
                COUNT(*) as total_attempts,
                COALESCE(ROUND(AVG(overall_score)), 0) as avg_score
            FROM mock_ai_interview_sessions
            WHERE student_id = ? AND status = 'completed' AND overall_score IS NOT NULL
            GROUP BY role_name
            ORDER BY avg_score DESC
        ");
        $stmt->execute([$studentId]);
        $mockStats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Fetch Coordinator Task Performance
        $stmt = $db->prepare("
            SELECT 
                ct.task_type,
                COUNT(ct.id) as total_assigned,
                SUM(IF(tc.id IS NOT NULL, 1, 0)) as completed_count,
                COALESCE(ROUND(AVG(tc.score)), 0) as avg_score
            FROM coordinator_tasks ct
            JOIN dept_coordinators dc ON ct.coordinator_id = dc.id
            LEFT JOIN task_completions tc ON ct.id = tc.task_id AND tc.student_id = ?
            WHERE (
                (ct.target_type = 'department' AND dc.department = ? AND dc.institution = ?)
                OR (ct.target_type = 'branch' AND JSON_CONTAINS(ct.target_branches, ?))
                OR (ct.target_type = 'individual' AND ct.target_students LIKE ?)
            )
            GROUP BY ct.task_type
        ");
        $stmt->execute([
            $studentId,
            $student['department'],
            $student['institution'],
            '"' . $student['department'] . '"',
            '%"' . $studentId . '"%'
        ]);
        $taskStats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // 5. Fetch Tab 4: Coordinator Tasks & Completion status
        $stmt = $db->prepare("
            SELECT ct.id, ct.title, ct.task_type, ct.deadline, ct.company_name,
                   tc.completed_at,
                   IF(tc.id IS NOT NULL, 'Completed', IF(ct.deadline < NOW(), 'Missed', 'Pending')) as status
            FROM coordinator_tasks ct
            JOIN dept_coordinators dc ON ct.coordinator_id = dc.id
            LEFT JOIN task_completions tc ON ct.id = tc.task_id AND tc.student_id = ?
            WHERE (
                (ct.target_type = 'department' AND dc.department = ? AND dc.institution = ?)
                OR (ct.target_type = 'branch' AND JSON_CONTAINS(ct.target_branches, ?))
                OR (ct.target_type = 'individual' AND ct.target_students LIKE ?)
            )
            ORDER BY ct.created_at DESC
        ");
        $stmt->execute([
            $studentId,
            $student['department'],
            $student['institution'],
            '"' . $student['department'] . '"',
            '%"' . $studentId . '"%'
        ]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Fetch Tab 5: Portfolio (Skills, Projects, Certifications)
        $stmt = $db->prepare("
            SELECT category, title, description, link, sub_title, date_completed 
            FROM student_portfolio 
            WHERE student_id = ? AND institution = ? 
            ORDER BY category, title ASC
        ");
        $stmt->execute([$studentId, $institution]);
        $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Format dates nicely
        foreach ($activities as &$act) {
            $act['created_formatted'] = !empty($act['created_at']) ? date('d M Y, h:i A', strtotime($act['created_at'])) : '---';
        }
        foreach ($interviews as &$int) {
            $int['created_formatted'] = !empty($int['created_at']) ? date('d M Y, h:i A', strtotime($int['created_at'])) : '---';
        }
        foreach ($tasks as &$t) {
            $t['deadline_formatted'] = !empty($t['deadline']) ? date('d M Y, h:i A', strtotime($t['deadline'])) : '---';
            $t['completed_formatted'] = !empty($t['completed_at']) ? date('d M Y, h:i A', strtotime($t['completed_at'])) : null;
        }
        
        echo json_encode([
            'success' => true,
            'student' => $student,
            'activities' => $activities,
            'interviews' => $interviews,
            'aiProfile' => $aiProfile,
            'mockStats' => $mockStats,
            'taskStats' => $taskStats,
            'tasks' => $tasks,
            'portfolio' => $portfolio
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

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

// 1. Resolve department and active filters from session
$department = getDepartment() ?: 'CSE';

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
            $endDate = $todayStr;
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

// 2. Fetch all department students for semesters 5, 6, 7, 8 matching filters
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();
$semester_filter = [5, 6, 7, 8];
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
            'course' => trim((string)($s['course'] ?? 'N/A')),
            'discipline' => trim((string)($s['department'] ?? 'N/A')),
            'academic_year' => trim((string)($s['academic_year'] ?? 'N/A')),
            'sem' => $s['semester'] ?? 0,
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
        // Case-insensitive fallback
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

// Fetch USNs of students who logged in today
$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';
$stmtTodayLogins = $db->prepare("
    SELECT DISTINCT user_id 
    FROM activity_logs 
    WHERE action = 'login' 
      AND user_id IN ($placeholders) 
      AND created_at >= ? 
      AND created_at <= ?
");
$stmtTodayLogins->execute(array_merge($allStudentIds, [$todayStart, $todayEnd]));
$todayLoginsUsns = $stmtTodayLogins->fetchAll(PDO::FETCH_COLUMN) ?: [];

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
    <title>Student Engagement Monitor - <?php echo htmlspecialchars($department); ?></title>
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

        .navbar-spacer { height: 80px; }

        .main-content {
            padding: 40px 50px;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
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
            grid-template-columns: 1fr;
            gap: 25px;
            margin-bottom: 40px;
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
        
        /* Tab Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            opacity: 1;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 950px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .modal.show .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: var(--primary-maroon);
            font-weight: 800;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text-muted);
            cursor: pointer;
            line-height: 1;
            transition: all 0.2s;
        }
        
        .close-modal:hover {
            color: var(--primary-maroon);
            transform: scale(1.1);
        }

        /* Tabs styling */
        .modal-tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .modal-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        
        .modal-tab:hover {
            color: var(--primary-maroon);
        }
        
        .modal-tab.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }

        /* Scoped table overrides for modal */
        .modal-content table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .modal-content th {
            padding: 12px 14px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            color: var(--text-muted) !important;
            border-bottom: 2px solid var(--border) !important;
            background-color: #f8fafc !important;
            letter-spacing: 0.5px !important;
        }
        
        .modal-content td {
            padding: 12px 14px !important;
            font-size: 13px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            color: var(--text-dark) !important;
            vertical-align: middle !important;
        }
        
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .clickable-row:hover {
            background-color: rgba(128, 0, 0, 0.03) !important;
        }
        
        .badge-type {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-aptitude { background: #e3f2fd; color: #1976d2; }
        .badge-technical { background: #ffebee; color: #c62828; }
        .badge-hr { background: #e8f5e9; color: #2e7d32; }

        .progress-bar-container {
            background: #e2e8f0;
            height: 10px;
            border-radius: 5px;
            width: 100%;
            overflow: hidden;
            display: block;
            margin-top: 4px;
        }
        
        .progress-bar-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.4s ease;
        }

        /* Live Login Stream Cards styling */
        .login-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding: 24px;
        }

        .login-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
            gap: 8px;
            position: relative;
        }

        .login-card:hover {
            border-color: var(--primary-maroon);
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(128, 0, 0, 0.05);
        }

        .login-card .time {
            font-size: 11px;
            color: var(--primary-maroon);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .login-card .student-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .login-card .student-usn {
            font-size: 12px;
            color: var(--text-muted);
            font-family: monospace;
        }

        .login-card .institution-badge {
            align-self: flex-start;
            background: #f1f5f9;
            color: var(--text-dark);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            margin-top: 4px;
        }
        
        .btn-print-tab {
            background: #f8fafc;
            color: var(--primary-maroon);
            border: 1px solid var(--border);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            margin-bottom: 15px;
        }
        .btn-print-tab:hover {
            background: #fff7ed;
            border-color: var(--primary-maroon);
            color: var(--primary-maroon);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="main-content">
        <div class="header-section">
            <div class="header-title">
                <h1>Student Login & Engagement Monitor</h1>
                <p>Track student logins and total engagement for HOD of <strong><?php echo htmlspecialchars($department); ?></strong> (Semesters 5-8)</p>
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
            <div class="metric-card" id="cardTotalStrength">
                <div class="label"><i class="fas fa-user-graduate"></i> Total Strength</div>
                <div class="value"><?php echo number_format($totalStudentsCount); ?></div>
                <div class="trend">Total registered students</div>
            </div>

            <div class="metric-card" id="cardLoginsToday">
                <div class="label"><i class="fas fa-sign-in-alt"></i> Logins Today</div>
                <div class="value"><?php echo number_format($loginsToday); ?></div>
                <div class="trend">Login actions today</div>
            </div>

            <div class="metric-card" id="cardEngagedStudents">
                <div class="label"><i class="fas fa-user-check"></i> Engaged Students</div>
                <div class="value"><?php echo number_format($activeStudentsCount); ?></div>
                <div class="trend"><?php echo number_format($inactiveStudentsCount); ?> students inactive</div>
            </div>

            <div class="metric-card" id="cardEngagementRate">
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
                                <th>USN</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Branch</th>
                                <th class="text-center">Semester</th>
                                <th>Source</th>
                                <th class="text-center">Total Logins</th>
                                <th>Engagement Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paginatedRoster)): ?>
                                <tr><td colspan="8" class="text-center" style="padding: 30px; color: var(--text-muted);">No student logs match your filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($paginatedRoster as $student): ?>
                                <tr class="clickable-row" data-usn="<?php echo htmlspecialchars($student['usn']); ?>" data-institution="<?php echo htmlspecialchars($student['institution']); ?>">
                                    <td style="font-family: monospace; font-weight: 600;"><?php echo htmlspecialchars($student['usn']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['course']); ?></td>
                                    <td><?php echo htmlspecialchars($student['discipline']); ?></td>
                                    <td class="text-center"><?php echo $student['sem'] > 0 ? htmlspecialchars($student['sem']) : '-'; ?></td>
                                    <td><strong><?php echo htmlspecialchars($student['institution']); ?></strong></td>
                                    <td class="text-center" style="font-weight: 700; color: var(--primary-maroon);"><?php echo $student['logins']; ?></td>
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



        </div>
    </main>

    <!-- Student Profile Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 id="modalStudentName">Student Profile</h3>
                    <div id="modalStudentUsn" style="font-size: 13px; color: var(--text-muted); margin-top: 4px; font-family: monospace;">USN: -</div>
                </div>
                <button class="close-modal" id="closeStudentModal">&times;</button>
            </div>
            
            <div class="modal-tabs">
                <button class="modal-tab active" data-tab="portal-activity"><i class="fas fa-history"></i> Portal Activity</button>
                <button class="modal-tab" data-tab="mock-interviews"><i class="fas fa-robot"></i> Mock AI Interviews</button>
                <button class="modal-tab" data-tab="skills-mastery"><i class="fas fa-award"></i> AI Profile</button>
                <button class="modal-tab" data-tab="skills-projects"><i class="fas fa-project-diagram"></i> Skills & Projects</button>
                <button class="modal-tab" data-tab="coord-tasks"><i class="fas fa-tasks"></i> Coordinator Tasks</button>
            </div>
            
            <!-- Tab 1: Portal Activity -->
            <div id="portal-activity" class="tab-pane active">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
                    <button class="btn-print-tab" onclick="printTab('portal-activity')"><i class="fas fa-print"></i> Print Report</button>
                </div>
                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 20px; display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>Institution: <strong id="modalInstitution">-</strong></div>
                    <div>Department: <strong id="modalDepartment">-</strong></div>
                    <div id="modalCgpaContainer">Current CGPA: <strong id="modalCGPA">-</strong></div>
                </div>
                <h4 style="margin-top: 15px; margin-bottom: 10px; color: var(--primary-maroon);">Recent Portal Actions</h4>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="activityTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Tab 2: Mock AI Interviews -->
            <div id="mock-interviews" class="tab-pane">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; color: var(--primary-maroon);">Mock AI Interview Sessions</h4>
                    <button class="btn-print-tab" onclick="printTab('mock-interviews')"><i class="fas fa-print"></i> Print Report</button>
                </div>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Interview Date</th>
                                <th>Job Title / Domain</th>
                                <th>Status</th>
                                <th class="text-center">Score</th>
                            </tr>
                        </thead>
                        <tbody id="interviewTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Tab 3: Skills & AI Profile -->
            <div id="skills-mastery" class="tab-pane">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
                    <button class="btn-print-tab" onclick="printTab('skills-mastery')"><i class="fas fa-print"></i> Print Report</button>
                </div>
                <div id="aiProfileSummaryCard" style="background: linear-gradient(135deg, rgba(128, 0, 0, 0.05) 0%, rgba(128, 0, 0, 0.02) 100%); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--primary-maroon); letter-spacing: 0.5px;">AI Predicted Career Role</span>
                            <h4 id="aiPredictedRole" style="font-size: 18px; font-weight: 800; color: var(--primary-dark); margin-top: 4px;">Software Engineer</h4>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Portfolio Match</span>
                            <div id="aiConfidenceScore" style="font-size: 18px; font-weight: 800; color: var(--primary-maroon); margin-top: 4px;">85%</div>
                        </div>
                    </div>
                    <p id="aiSummaryText" style="font-size: 13.5px; color: #475569; line-height: 1.6; font-style: italic;">No profile generated yet.</p>
                </div>
                
                <h4 style="margin-bottom: 10px; color: var(--primary-maroon);">Mock AI Interview Performance</h4>
                <div class="table-responsive" style="margin-bottom: 25px; border: 1px solid var(--border); border-radius: 10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Interview Domain</th>
                                <th class="text-center">Attempts</th>
                                <th style="width: 250px;">Average Score</th>
                            </tr>
                        </thead>
                        <tbody id="mockPerformanceTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>

                <h4 style="margin-bottom: 10px; color: var(--primary-maroon);">Assigned Task Performance</h4>
                <div class="table-responsive" style="border: 1px solid var(--border); border-radius: 10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Task Category</th>
                                <th class="text-center">Completion Rate</th>
                                <th style="width: 250px;">Average Score</th>
                            </tr>
                        </thead>
                        <tbody id="taskPerformanceTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Tab 5: Skills & Projects -->
            <div id="skills-projects" class="tab-pane">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
                    <button class="btn-print-tab" onclick="printTab('skills-projects')"><i class="fas fa-print"></i> Print Report</button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 15px;">
                        <h4 style="margin-bottom: 12px; color: var(--primary-maroon); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-tools"></i> Skills Added
                        </h4>
                        <div id="skillsContainer" style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <!-- Populated dynamically -->
                        </div>
                        <div id="noSkillsMessage" style="color: var(--text-muted); font-size: 13px; font-style: italic; display: none; margin-top: 5px;">No skills added yet.</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 15px;">
                        <h4 style="margin-bottom: 12px; color: var(--primary-maroon); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-certificate"></i> Certifications Added
                        </h4>
                        <div id="certificationsContainer" style="display: flex; flex-direction: column; gap: 10px;">
                            <!-- Populated dynamically -->
                        </div>
                        <div id="noCertificationsMessage" style="color: var(--text-muted); font-size: 13px; font-style: italic; display: none; margin-top: 5px;">No certifications added yet.</div>
                    </div>
                </div>

                <h4 style="margin-bottom: 10px; color: var(--primary-maroon); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-project-diagram"></i> Projects Added
                </h4>
                <div class="table-responsive" style="border: 1px solid var(--border); border-radius: 10px; max-height: 250px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Role / Context</th>
                                <th>Technologies / Description</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody id="projectsTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Tab 4: Coordinator Tasks -->
            <div id="coord-tasks" class="tab-pane">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; color: var(--primary-maroon);">Assigned Task Performance</h4>
                    <button class="btn-print-tab" onclick="printTab('coord-tasks')"><i class="fas fa-print"></i> Print Report</button>
                </div>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Type</th>
                                <th>Deadline</th>
                                <th>Completion Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="tasksTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics Detail Modal -->
    <div id="metricsModal" class="modal">
        <div class="modal-content" style="max-width: 750px;">
            <div class="modal-header">
                <div>
                    <h3 id="metricsModalTitle" style="font-size: 20px; color: var(--primary-maroon); font-weight: 800;">Metric Details</h3>
                    <div id="metricsModalSubtitle" style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Students List</div>
                </div>
                <button class="close-modal" id="closeMetricsModal">&times;</button>
            </div>
            <div class="table-responsive" style="max-height: 450px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px;">
                <table>
                    <thead>
                        <tr>
                            <th>USN</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Branch</th>
                            <th>Source</th>
                            <th class="text-center">Logins</th>
                        </tr>
                    </thead>
                    <tbody id="metricsModalTableBody">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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

        // Tab switching logic for Student Profile Modal
        const modalTabs = document.querySelectorAll('.modal-tab');
        const tabPanes = document.querySelectorAll('.tab-pane');
        modalTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                modalTabs.forEach(t => t.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                
                tab.classList.add('active');
                const targetPane = document.getElementById(tab.getAttribute('data-tab'));
                if (targetPane) targetPane.classList.add('active');
            });
        });

        // Close modal logic
        const sModal = document.getElementById('studentModal');
        const sCloseBtn = document.getElementById('closeStudentModal');
        
        function closeStudentProfileModal() {
            sModal.classList.remove('show');
            setTimeout(() => {
                sModal.style.display = 'none';
            }, 300);
        }
        
        if (sCloseBtn) {
            sCloseBtn.addEventListener('click', closeStudentProfileModal);
        }
        if (sModal) {
            sModal.addEventListener('click', (e) => {
                if (e.target === sModal) closeStudentProfileModal();
            });
        }

        // Open modal and load student report via AJAX
        document.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', () => {
                const studentId = row.getAttribute('data-usn');
                const institution = row.getAttribute('data-institution') || 'GMU';
                openStudentProfile(studentId, institution);
            });
        });

        // Global arrays of roster students and today logins for metric click filtering
        const allRosterStudents = <?php echo json_encode(array_values($roster)); ?>;
        const todayLoginsUsns = <?php echo json_encode($todayLoginsUsns); ?>;

        // Metrics Modal elements
        const mModal = document.getElementById('metricsModal');
        const mCloseBtn = document.getElementById('closeMetricsModal');
        const mTitle = document.getElementById('metricsModalTitle');
        const mSubtitle = document.getElementById('metricsModalSubtitle');
        const mTableBody = document.getElementById('metricsModalTableBody');

        function closeMetricsModal() {
            mModal.classList.remove('show');
            setTimeout(() => {
                mModal.style.display = 'none';
            }, 300);
        }

        if (mCloseBtn) {
            mCloseBtn.addEventListener('click', closeMetricsModal);
        }
        if (mModal) {
            mModal.addEventListener('click', (e) => {
                if (e.target === mModal) closeMetricsModal();
            });
        }

        function showMetricsDetail(title, subtitle, filteredList) {
            mTitle.textContent = title;
            mSubtitle.textContent = subtitle;
            
            if (filteredList.length === 0) {
                mTableBody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 30px; color: var(--text-muted);">No student records found matching this metric.</td></tr>';
            } else {
                mTableBody.innerHTML = filteredList.map(s => `
                    <tr class="clickable-row-from-metrics" data-usn="${s.usn}" data-institution="${s.institution}" style="cursor: pointer;">
                        <td style="font-family: monospace; font-weight: 600;">${s.usn}</td>
                        <td><strong>${s.name}</strong></td>
                        <td>${s.course || 'N/A'}</td>
                        <td>${s.discipline || 'N/A'}</td>
                        <td><strong>${s.institution}</strong></td>
                        <td class="text-center" style="font-weight: 700; color: var(--primary-maroon);">${s.logins}</td>
                    </tr>
                `).join('');
                
                // Add click handler to rows to load detailed student profile modal
                mTableBody.querySelectorAll('.clickable-row-from-metrics').forEach(row => {
                    row.addEventListener('click', () => {
                        const usn = row.getAttribute('data-usn');
                        const inst = row.getAttribute('data-institution');
                        closeMetricsModal();
                        openStudentProfile(usn, inst);
                    });
                });
            }
            
            mModal.style.display = 'flex';
            setTimeout(() => mModal.classList.add('show'), 10);
        }

        // Card click listeners
        const cardTotal = document.getElementById('cardTotalStrength');
        if (cardTotal) {
            cardTotal.addEventListener('click', () => {
                showMetricsDetail('Total Strength', 'All registered students in the department', allRosterStudents);
            });
        }

        const cardToday = document.getElementById('cardLoginsToday');
        if (cardToday) {
            cardToday.addEventListener('click', () => {
                const list = allRosterStudents.filter(s => todayLoginsUsns.includes(s.usn));
                showMetricsDetail('Logins Today', 'Students who logged in today', list);
            });
        }

        const cardEngaged = document.getElementById('cardEngagedStudents');
        if (cardEngaged) {
            cardEngaged.addEventListener('click', () => {
                const list = allRosterStudents.filter(s => s.logins > 0);
                showMetricsDetail('Engaged Students', 'Students with at least 1 login in selected date range', list);
            });
        }

        const cardRate = document.getElementById('cardEngagementRate');
        if (cardRate) {
            cardRate.addEventListener('click', () => {
                const list = allRosterStudents.filter(s => s.logins > 0);
                showMetricsDetail('Engagement Rate Details', 'Students with active login records', list);
            });
        }

        // Open modal and load student report via AJAX
        function openStudentProfile(studentId, institution = 'GMU') {
            if (!studentId) return;
                
                // Show modal loading state
                document.getElementById('modalStudentName').textContent = 'Loading Student Profile...';
                document.getElementById('modalStudentUsn').textContent = 'USN: ' + studentId;
                document.getElementById('modalInstitution').textContent = 'Loading...';
                document.getElementById('modalDepartment').textContent = 'Loading...';
                document.getElementById('modalCGPA').textContent = 'Loading...';
                document.getElementById('activityTableBody').innerHTML = '<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Fetching logs...</td></tr>';
                document.getElementById('interviewTableBody').innerHTML = '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i> Fetching assessments...</td></tr>';
                document.getElementById('mockPerformanceTableBody').innerHTML = '<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Fetching performance...</td></tr>';
                document.getElementById('taskPerformanceTableBody').innerHTML = '<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Fetching performance...</td></tr>';
                document.getElementById('tasksTableBody').innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Fetching assigned tasks...</td></tr>';
                
                // Initialize Skills & Projects loading state
                document.getElementById('skillsContainer').innerHTML = '<span style="color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading...</span>';
                document.getElementById('certificationsContainer').innerHTML = '<span style="color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading...</span>';
                document.getElementById('projectsTableBody').innerHTML = '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i> Fetching projects...</td></tr>';
                
                // Hide AI Profile summary card initially
                document.getElementById('aiProfileSummaryCard').style.display = 'none';
                
                // Reset active tab to Tab 1
                modalTabs.forEach(t => t.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                modalTabs[0].classList.add('active');
                tabPanes[0].classList.add('active');
                
                // Display modal container
                sModal.style.display = 'flex';
                setTimeout(() => sModal.classList.add('show'), 10);
                
                // Fetch report data
                const formData = new FormData();
                formData.append('action', 'fetch_student_report');
                formData.append('student_id', studentId);
                formData.append('institution', institution);
                
                fetch('ai_monitor.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('modalStudentName').textContent = 'Error Loading Student';
                        document.getElementById('activityTableBody').innerHTML = `<tr><td colspan="3" class="text-center text-danger">${data.message}</td></tr>`;
                        return;
                    }
                    
                    // Render Student Header Info
                    document.getElementById('modalStudentName').textContent = data.student.name || 'Student Profile';
                    document.getElementById('modalStudentUsn').textContent = 'USN: ' + data.student.usn;
                    document.getElementById('modalInstitution').textContent = data.student.institution || 'N/A';
                    document.getElementById('modalDepartment').textContent = data.student.department || 'N/A';
                    
                    const cgpa = data.student.cgpa;
                    const cgpaContainer = document.getElementById('modalCgpaContainer');
                    if (cgpa && parseFloat(cgpa) > 0) {
                        document.getElementById('modalCGPA').textContent = cgpa;
                        if (cgpaContainer) cgpaContainer.style.display = 'block';
                    } else {
                        if (cgpaContainer) cgpaContainer.style.display = 'none';
                    }
                    
                    // Render Activities (Tab 1)
                    const actBody = document.getElementById('activityTableBody');
                    if (data.activities.length === 0) {
                        actBody.innerHTML = '<tr><td colspan="3" class="text-center" style="color: var(--text-muted); padding: 20px;">No portal activity logs found.</td></tr>';
                    } else {
                        actBody.innerHTML = data.activities.map(act => `
                             <tr>
                                 <td style="white-space: nowrap; color: var(--text-muted); font-size: 12px;">${act.created_formatted}</td>
                                 <td><span class="badge badge-info" style="text-transform: uppercase;">${act.action}</span></td>
                                 <td style="font-weight: 500;">${act.description || ''}</td>
                             </tr>
                        `).join('');
                    }
                    
                    // Render Interviews (Tab 2)
                    const intBody = document.getElementById('interviewTableBody');
                    if (data.interviews.length === 0) {
                        intBody.innerHTML = '<tr><td colspan="4" class="text-center" style="color: var(--text-muted); padding: 20px;">No mock AI interviews completed yet.</td></tr>';
                    } else {
                        intBody.innerHTML = data.interviews.map(int => {
                            let statusBadge = '';
                            if (int.status === 'completed') {
                                 statusBadge = '<span class="badge badge-success">Completed</span>';
                            } else {
                                 statusBadge = `<span class="badge badge-warning">${int.status}</span>`;
                            }
                            
                            return `
                                 <tr>
                                     <td style="color: var(--text-muted); font-size: 12px;">${int.created_formatted}</td>
                                     <td><strong>${int.job_title || 'General Interview'}</strong></td>
                                     <td>${statusBadge}</td>
                                     <td class="text-center" style="font-weight: 700; color: var(--primary-maroon); font-size: 15px;">${int.score || 0}%</td>
                                 </tr>
                            `;
                        }).join('');
                    }
                    
                    // Render AI Profile & Mastery (Tab 3)
                    if (data.aiProfile) {
                        document.getElementById('aiPredictedRole').textContent = data.aiProfile.predicted_role || 'Software Engineer';
                        const matchScore = Math.min(85, Math.round((data.aiProfile.confidence_score || 0.5) * 100));
                        document.getElementById('aiConfidenceScore').textContent = matchScore + '%';
                        document.getElementById('aiSummaryText').textContent = data.aiProfile.ai_summary || '';
                        document.getElementById('aiProfileSummaryCard').style.display = 'block';
                    }
                    
                    // Render Mock Interview stats
                    const mockPerfBody = document.getElementById('mockPerformanceTableBody');
                    if (data.mockStats.length === 0) {
                        mockPerfBody.innerHTML = '<tr><td colspan="3" class="text-center" style="color: var(--text-muted); padding: 20px;">No completed mock interviews found.</td></tr>';
                    } else {
                        mockPerfBody.innerHTML = data.mockStats.map(m => {
                            const score = parseInt(m.avg_score) || 0;
                            let progressColor = 'var(--primary-maroon)';
                            if (score >= 75) progressColor = 'var(--success)';
                            else if (score >= 45) progressColor = 'var(--accent-blue)';
                            
                            return `
                                <tr>
                                    <td style="font-weight: 600;">${m.domain || 'General Mock'}</td>
                                    <td class="text-center">${m.total_attempts}</td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-weight: 700; width: 35px; text-align: right;">${score}%</span>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: ${score}%; background-color: ${progressColor};"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('');
                    }
                    
                    // Render Coordinator Task stats
                    const taskPerfBody = document.getElementById('taskPerformanceTableBody');
                    if (data.taskStats.length === 0) {
                        taskPerfBody.innerHTML = '<tr><td colspan="3" class="text-center" style="color: var(--text-muted); padding: 20px;">No coordinator tasks assigned to this student.</td></tr>';
                    } else {
                        taskPerfBody.innerHTML = data.taskStats.map(t => {
                            const total = parseInt(t.total_assigned) || 0;
                            const completed = parseInt(t.completed_count) || 0;
                            const rate = total > 0 ? Math.round((completed / total) * 100) : 0;
                            const score = parseInt(t.avg_score) || 0;
                            
                            let progressColor = 'var(--primary-maroon)';
                            if (score >= 75) progressColor = 'var(--success)';
                            else if (score >= 45) progressColor = 'var(--accent-blue)';
                            
                            let catClass = 'badge-technical';
                            if (t.task_type === 'aptitude') catClass = 'badge-aptitude';
                            if (t.task_type === 'hr') catClass = 'badge-hr';
                            
                            return `
                                <tr>
                                    <td><span class="badge-type ${catClass}" style="text-transform: uppercase;">${t.task_type}</span></td>
                                    <td class="text-center"><strong>${completed} / ${total}</strong> (${rate}%)</td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-weight: 700; width: 35px; text-align: right;">${score}%</span>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: ${score}%; background-color: ${progressColor};"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('');
                    }
                    
                    // Render Tasks (Tab 4)
                    const tasksBody = document.getElementById('tasksTableBody');
                    if (data.tasks.length === 0) {
                        tasksBody.innerHTML = '<tr><td colspan="5" class="text-center" style="color: var(--text-muted); padding: 20px;">No coordinator tasks assigned to this department.</td></tr>';
                    } else {
                        tasksBody.innerHTML = data.tasks.map(t => {
                            let statusBadge = '';
                            if (t.status === 'Completed') {
                                statusBadge = '<span class="badge badge-success"><i class="fas fa-check-circle" style="margin-right: 4px;"></i> Completed</span>';
                            } else if (t.status === 'Missed') {
                                statusBadge = '<span class="badge badge-danger"><i class="fas fa-times-circle" style="margin-right: 4px;"></i> Missed</span>';
                            } else {
                                statusBadge = '<span class="badge badge-warning"><i class="far fa-clock" style="margin-right: 4px;"></i> Pending</span>';
                            }
                            
                            return `
                                <tr>
                                    <td style="font-weight: 600;">${t.title}</td>
                                    <td><span class="badge badge-info">${t.task_type}</span></td>
                                    <td style="color: var(--text-muted); font-size: 12px;">${t.deadline_formatted}</td>
                                    <td style="font-weight: 500;">${t.completed_formatted || '---'}</td>
                                    <td>${statusBadge}</td>
                                </tr>
                            `;
                        }).join('');
                    }
                    
                    // Render Skills & Projects (Tab 5)
                    const skillsContainer = document.getElementById('skillsContainer');
                    const noSkillsMsg = document.getElementById('noSkillsMessage');
                    const certsContainer = document.getElementById('certificationsContainer');
                    const noCertsMsg = document.getElementById('noCertificationsMessage');
                    const projBody = document.getElementById('projectsTableBody');
                    
                    // Clear previous
                    skillsContainer.innerHTML = '';
                    certsContainer.innerHTML = '';
                    projBody.innerHTML = '';
                    
                    const skills = data.portfolio.filter(p => p.category === 'Skill');
                    const certs = data.portfolio.filter(p => p.category === 'Certification');
                    const projects = data.portfolio.filter(p => p.category === 'Project');
                    
                    // Render Skills
                    if (skills.length === 0) {
                        noSkillsMsg.style.display = 'block';
                    } else {
                        noSkillsMsg.style.display = 'none';
                        skillsContainer.innerHTML = skills.map(s => `
                            <span class="badge badge-info" style="font-size: 12px; padding: 6px 12px; font-weight: 600; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                                ${s.title}
                            </span>
                        `).join('');
                    }
                    
                    // Render Certifications
                    if (certs.length === 0) {
                        noCertsMsg.style.display = 'block';
                    } else {
                        noCertsMsg.style.display = 'none';
                        certsContainer.innerHTML = certs.map(c => `
                            <div style="background: white; border: 1px solid var(--border); border-radius: 8px; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                <div>
                                    <strong style="font-size: 13px; color: var(--primary-dark);">${c.title}</strong>
                                    ${c.sub_title ? `<div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Issuer: ${c.sub_title}</div>` : ''}
                                </div>
                                ${c.link ? `<a href="${c.link}" target="_blank" class="badge badge-success" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; background: #dcfce7; color: #15803d;"><i class="fas fa-external-link-alt" style="font-size: 10px;"></i> View</a>` : ''}
                            </div>
                        `).join('');
                    }
                    
                    // Render Projects
                    if (projects.length === 0) {
                        projBody.innerHTML = '<tr><td colspan="4" class="text-center" style="color: var(--text-muted); padding: 20px;">No projects added yet.</td></tr>';
                    } else {
                        projBody.innerHTML = projects.map(p => {
                            const dateStr = p.date_completed ? new Date(p.date_completed).toLocaleDateString('en-US', {month: 'short', year: 'numeric'}) : '';
                            return `
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary-dark); font-size: 13.5px;">${p.title}</strong>
                                        ${dateStr ? `<div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Completed: ${dateStr}</div>` : ''}
                                    </td>
                                    <td style="font-weight: 500;">${p.sub_title || '---'}</td>
                                    <td>
                                        <div style="font-size: 13px; line-height: 1.4; color: #475569;">${p.description || '---'}</div>
                                    </td>
                                    <td>
                                        ${p.link ? `<a href="${p.link}" target="_blank" style="color: var(--accent-blue); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;"><i class="fab fa-github"></i> Link</a>` : '---'}
                                    </td>
                                </tr>
                            `;
                        }).join('');
                    }
                })
                .catch(err => {
                    document.getElementById('modalStudentName').textContent = 'Connection Error';
                    document.getElementById('activityTableBody').innerHTML = '<tr><td colspan="3" class="text-center text-danger">Could not fetch report data.</td></tr>';
                });
        }
        
        function printTab(tabId) {
            const studentName = document.getElementById('modalStudentName').textContent;
            const studentUsn = document.getElementById('modalStudentUsn').textContent;
            const institution = document.getElementById('modalInstitution').textContent;
            const department = document.getElementById('modalDepartment').textContent;
            
            let tabTitle = "Student Report";
            if (tabId === 'portal-activity') tabTitle = "Portal Activity & Actions";
            else if (tabId === 'mock-interviews') tabTitle = "Mock AI Interview History";
            else if (tabId === 'skills-mastery') tabTitle = "AI Profile & Mastery Summary";
            else if (tabId === 'skills-projects') tabTitle = "Student Skills & Projects Portfolio";
            else if (tabId === 'coord-tasks') tabTitle = "Coordinator Tasks & Performance";
            
            const pane = document.getElementById(tabId);
            if (!pane) return;
            
            // Clone and prepare content
            const clonedPane = pane.cloneNode(true);
            clonedPane.querySelectorAll('.btn-print-tab').forEach(el => el.remove());
            
            // Clear any max-height constraints and scrollbars for printing
            const tableResps = clonedPane.querySelectorAll('.table-responsive');
            tableResps.forEach(el => {
                el.style.maxHeight = 'none';
                el.style.overflowY = 'visible';
                el.style.border = 'none';
            });
            
            const printWindow = window.open('', '_blank', 'width=900,height=800');
            if (!printWindow) {
                alert("Please allow popups to print tab reports.");
                return;
            }
            
            const htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print - ${studentName} (${studentUsn})</title>
                    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                    <style>
                        body {
                            font-family: 'Outfit', sans-serif;
                            color: #1e293b;
                            padding: 40px;
                            margin: 0;
                            background: #fff;
                        }
                        .print-header {
                            border-bottom: 2px solid #800000;
                            padding-bottom: 15px;
                            margin-bottom: 25px;
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-end;
                        }
                        .student-title h1 {
                            font-size: 22px;
                            font-weight: 800;
                            color: #800000;
                            margin: 0 0 5px 0;
                            text-transform: uppercase;
                        }
                        .student-title p {
                            font-size: 13px;
                            color: #64748b;
                            margin: 0;
                            font-family: monospace;
                            font-weight: 600;
                        }
                        .student-meta {
                            text-align: right;
                            font-size: 12px;
                            line-height: 1.5;
                        }
                        .student-meta strong {
                            color: #1a1a1a;
                        }
                        .tab-title-header {
                            font-size: 16px;
                            font-weight: 700;
                            color: #1a1a1a;
                            margin-bottom: 20px;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            border-left: 4px solid #800000;
                            padding-left: 10px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 15px;
                            margin-bottom: 25px;
                        }
                        th {
                            background: #f8fafc !important;
                            color: #64748b !important;
                            text-transform: uppercase !important;
                            font-size: 10px !important;
                            font-weight: 700 !important;
                            letter-spacing: 0.5px !important;
                            padding: 10px 12px !important;
                            border-bottom: 2px solid #e2e8f0 !important;
                            text-align: left !important;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        td {
                            padding: 10px 12px !important;
                            border-bottom: 1px solid #f1f5f9 !important;
                            font-size: 12px !important;
                            color: #1a1a1a !important;
                        }
                        .text-center { text-align: center !important; }
                        .badge {
                            display: inline-block;
                            padding: 3px 8px;
                            border-radius: 100px;
                            font-size: 10px;
                            font-weight: 700;
                            text-transform: uppercase;
                        }
                        .badge-success { background: #dcfce7 !important; color: #15803d !important; border: 1px solid #bbf7d0 !important; }
                        .badge-info { background: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #bae6fd !important; }
                        .badge-warning { background: #fef9c3 !important; color: #854d0e !important; border: 1px solid #fef08a !important; }
                        .badge-danger { background: #fee2e2 !important; color: #b91c1c !important; border: 1px solid #fecaca !important; }
                        
                        .badge-type {
                            padding: 3px 6px;
                            border-radius: 4px;
                            font-size: 10px;
                            font-weight: 700;
                            text-transform: uppercase;
                        }
                        .badge-aptitude { background: #e3f2fd !important; color: #1976d2 !important; }
                        .badge-technical { background: #ffebee !important; color: #c62828 !important; }
                        .badge-hr { background: #e8f5e9 !important; color: #2e7d32 !important; }
                        
                        .progress-bar-container {
                            width: 120px;
                            height: 8px;
                            background-color: #e2e8f0 !important;
                            border-radius: 4px;
                            overflow: hidden;
                            display: inline-block;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        .progress-bar-fill {
                            height: 100%;
                            border-radius: 4px;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        .print-footer {
                            margin-top: 40px;
                            border-top: 1px solid #e2e8f0;
                            padding-top: 12px;
                            font-size: 10px;
                            color: #94a3b8;
                            display: flex;
                            justify-content: space-between;
                        }
                        h4 {
                            margin-top: 20px;
                            margin-bottom: 10px;
                            color: #800000;
                            font-size: 14px;
                            border-bottom: 1px dashed #e2e8f0;
                            padding-bottom: 4px;
                        }
                        
                        /* Styles specific to skills and projects print layout */
                        #skillsContainer {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 6px;
                        }
                        #certificationsContainer {
                            display: flex;
                            flex-direction: column;
                            gap: 8px;
                        }
                        
                        @media print {
                            body { padding: 10px; }
                            .no-print { display: none; }
                            th, td, .badge, .badge-type, .progress-bar-container, .progress-bar-fill {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <div class="student-title">
                            <h1>${studentName}</h1>
                            <p>${studentUsn}</p>
                        </div>
                        <div class="student-meta">
                            <div>Institution: <strong>${institution}</strong></div>
                            <div>Department: <strong>${department}</strong></div>
                            <div>Generated: <strong>${new Date().toLocaleString()}</strong></div>
                        </div>
                    </div>
                    
                    <div class="tab-title-header">${tabTitle}</div>
                    
                    <div>
                        ${clonedPane.innerHTML}
                    </div>
                    
                    <div class="print-footer">
                        <div>Lakshya Academic Portal &copy; ${new Date().getFullYear()}</div>
                        <div>Confidential Student Report</div>
                    </div>
                    
                    <script>
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                                window.close();
                            }, 500);
                        };
                    <\/script>
                </body>
                </html>
            `;
            
            printWindow.document.open();
            printWindow.document.write(htmlContent);
            printWindow.document.close();
        }
    </script>
    <script src="<?php echo APP_URL; ?>/js/maintenance_interceptor.js"></script>
</body>
</html>
