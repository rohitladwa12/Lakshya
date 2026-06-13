<?php
/**
 * Head of Department (HOD) Dashboard
 * Monitored overview of coordinators and student metrics
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

// Handle AJAX request for viewing coordinator tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_coordinator_tasks') {
    $db = getDB();
    $department = getDepartment() ?: 'CSE';
    $coordId = (int)($_POST['coordinator_id'] ?? 0);
    
    // Verify department matches
    $stmt = $db->prepare("SELECT department FROM dept_coordinators WHERE id = ?");
    $stmt->execute([$coordId]);
    $coordDept = $stmt->fetchColumn();
    
    if (!$coordDept || $coordDept !== $department) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access Denied.']);
        exit;
    }
    
    $stmtTasks = $db->prepare("
        SELECT 
            MIN(id) as id,
            title, 
            task_type, 
            company_name, 
            concept, 
            question_source, 
            deadline, 
            created_at,
            COUNT(*) as targeted_count
        FROM coordinator_tasks 
        WHERE coordinator_id = ?
        GROUP BY title, task_type, deadline, company_name, concept, question_source
        ORDER BY created_at DESC
    ");
    $stmtTasks->execute([$coordId]);
    $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    foreach ($tasks as &$task) {
        $stmtComp = $db->prepare("
            SELECT COUNT(*) 
            FROM task_completions tc
            JOIN coordinator_tasks ct ON tc.task_id = ct.id
            WHERE ct.coordinator_id = ? 
              AND ct.title = ? 
              AND ct.task_type = ? 
              AND ct.deadline = ?
        ");
        $stmtComp->execute([$coordId, $task['title'], $task['task_type'], $task['deadline']]);
        $task['completed_count'] = (int)$stmtComp->fetchColumn();
        
        $task['deadline_formatted'] = date('d M Y, h:i A', strtotime($task['deadline']));
        $task['created_formatted'] = date('d M Y, h:i A', strtotime($task['created_at']));
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'tasks' => $tasks]);
    exit;
}

$fullName = getFullName();
$department = getDepartment() ?: 'CSE';
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptGmu = $discipline_filters[0] ?? $department;
$deptGmit = $discipline_filters[1] ?? $department;
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;

require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();

// Fetch overall strength for Sem 5, 6, 7, 8
$overallFilters = [
    'discipline' => $discipline_filters,
    'semesters' => [5, 6, 7, 8]
];
$totalStudents5to8 = $studentModel->getTotalAcademicStrength($overallFilters);

// Breakdown for each semester
$semBreakdown = [];
foreach ([5, 6, 7, 8] as $sem) {
    $semFilters = [
        'discipline' => $discipline_filters,
        'semesters' => [$sem]
    ];
    $semBreakdown[$sem] = $studentModel->getTotalAcademicStrength($semFilters);
}

// Fetch Coordinators and their activities
$db = getDB();
$stmt = $db->prepare("SELECT id, email, full_name, department, institution, is_active FROM dept_coordinators WHERE department = ?");
$stmt->execute([$department]);
$coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$coordinatorStats = [];
$totalTasksAssigned = 0;
$totalCompletions = 0;

foreach ($coordinators as $coord) {
    $coordId = $coord['id'];
    
    // Number of times tasks assigned (coordinator_tasks entries grouped as campaigns)
    $stmtTasks = $db->prepare("SELECT COUNT(DISTINCT title, task_type, deadline) FROM coordinator_tasks WHERE coordinator_id = ?");
    $stmtTasks->execute([$coordId]);
    $tasksAssigned = (int)$stmtTasks->fetchColumn();
    $totalTasksAssigned += $tasksAssigned;
    
    // Number of students who completed coordinator tasks
    $stmtCompletions = $db->prepare("
        SELECT COUNT(*) 
        FROM task_completions tc 
        JOIN coordinator_tasks ct ON tc.task_id = ct.id 
        WHERE ct.coordinator_id = ?
    ");
    $stmtCompletions->execute([$coordId]);
    $completionsCount = (int)$stmtCompletions->fetchColumn();
    $totalCompletions += $completionsCount;
    
    $coordinatorStats[] = [
        'id' => $coord['id'],
        'name' => $coord['full_name'],
        'email' => $coord['email'],
        'institution' => $coord['institution'] ?: 'GMU',
        'tasks_assigned' => $tasksAssigned,
        'completions' => $completionsCount,
        'is_active' => $coord['is_active']
    ];
}

// Fetch student USNs for the department to compute PRI
$students = $studentModel->getAllWithUsers($overallFilters);
$usns = array_column($students, 'usn');

$priIndex = 0;
$resumeRate = 0;
$mockRate = 0;
$taskRate = 0;

if (!empty($usns)) {
    $usnList = "'" . implode("','", array_map('addslashes', $usns)) . "'";
    
    // 1. Resume Upload Rate
    $stmt = $db->query("SELECT COUNT(DISTINCT student_id) FROM student_resumes WHERE student_id IN ($usnList)");
    $resumesCount = (int)$stmt->fetchColumn();
    $resumeRate = ($resumesCount / count($usns)) * 100;
    
    // 2. AI Mock Interview Completion Rate
    $stmt = $db->query("SELECT COUNT(DISTINCT student_id) FROM mock_ai_interview_sessions WHERE student_id IN ($usnList) AND status = 'completed'");
    $mockCount = (int)$stmt->fetchColumn();
    $mockRate = ($mockCount / count($usns)) * 100;
    
    // 3. Coordinator Task Completion Rate
    $stmt = $db->query("SELECT COUNT(DISTINCT student_id) FROM task_completions tc JOIN coordinator_tasks ct ON tc.task_id = ct.id WHERE tc.student_id IN ($usnList)");
    $taskCompletedCount = (int)$stmt->fetchColumn();
    $taskRate = ($taskCompletedCount / count($usns)) * 100;
    
    // Composite Placement Readiness Index
    $priIndex = round((0.3 * $resumeRate) + (0.4 * $mockRate) + (0.3 * $taskRate), 1);
}

// Fetch top 5 performers for the Wall of Fame
$topPerformers = [];
try {
    $rankings = \App\Services\LeaderboardService::getRankings($overallFilters);
    $topPerformers = array_slice($rankings, 0, 5);
} catch (Exception $e) {
    error_log("Failed to fetch HOD leaderboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 20px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        .navbar-spacer { height: 80px; }
        
        .main-content {
            padding: 40px 50px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h2 {
            font-size: 32px;
            color: var(--primary-maroon);
            font-weight: 800;
            margin-bottom: 4px;
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 15px;
        }
        
        /* Stats Dashboard Layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .summary-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary-maroon);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .summary-info h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .summary-info .main-val {
            font-size: 48px;
            font-weight: 800;
            color: var(--primary-maroon);
            line-height: 1;
        }
        
        .summary-info p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 10px;
        }
        
        .summary-icon {
            width: 70px;
            height: 70px;
            background: rgba(128, 0, 0, 0.08);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-maroon);
            font-size: 32px;
        }
        
        .sem-breakdown-card {
            background: white;
            padding: 24px;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }
        
        .sem-breakdown-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-dark);
            border-bottom: 1.5px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .sem-list {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .sem-pill {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            transition: var(--transition);
        }
        
        .sem-pill:hover {
            border-color: var(--primary-gold);
            transform: translateY(-2px);
            background: #fffdf5;
        }
        
        .sem-pill .sem-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            display: block;
            margin-bottom: 4px;
        }
        
        .sem-pill .sem-count {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-maroon);
        }
        
        /* Coordinator activity table */
        .section-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .section-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-card h3 i {
            color: var(--primary-maroon);
        }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        
        th {
            padding: 16px 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 18px 20px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .coord-name-wrap {
            display: flex;
            flex-direction: column;
        }
        
        .coord-name {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .coord-email {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .badge-success {
            background-color: #f0fdf4;
            color: #166534;
        }
        
        .badge-warning {
            background-color: #fffbeb;
            color: #92400e;
        }
        
        .stat-count-pill {
            background-color: #f1f5f9;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            color: var(--primary-maroon);
            display: inline-block;
        }

        .quick-monitor-btn {
            background-color: var(--primary-maroon);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(128,0,0,0.2);
            transition: var(--transition);
        }

        .quick-monitor-btn:hover {
            background-color: #600000;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(128,0,0,0.3);
        }

        .pri-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary-gold);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .pri-circle-wrap {
            position: relative;
            width: 70px;
            height: 70px;
        }
        
        .pri-circle-bg {
            fill: none;
            stroke: #f1f5f9;
            stroke-width: 6;
        }
        
        .pri-circle-progress {
            fill: none;
            stroke: var(--primary-gold);
            stroke-width: 6;
            stroke-linecap: round;
            stroke-dasharray: 220;
            stroke-dashoffset: calc(220 - (220 * var(--pri-percent)) / 100);
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 1s ease-in-out;
        }
        
        .pri-val-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .dashboard-main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
            align-items: start;
        }

        .top-performers-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .top-performers-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            border-bottom: 1.5px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-performers-card h3 i {
            color: var(--primary-gold);
        }
        
        .performer-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .performer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-radius: 12px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .performer-row:hover {
            transform: translateX(4px);
            border-color: var(--primary-gold);
            background: #fffdf9;
        }
        
        .performer-info-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .performer-rank {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .rank-1 {
            background: rgba(212, 175, 55, 0.15);
            color: #b58d16;
            border: 1px solid #d4af37;
        }
        
        .rank-2 {
            background: rgba(192, 192, 192, 0.15);
            color: #7f7f7f;
            border: 1px solid #c0c0c0;
        }
        
        .rank-3 {
            background: rgba(205, 127, 50, 0.15);
            color: #9c5c24;
            border: 1px solid #cd7f32;
        }
        
        .rank-other {
            background: #f1f5f9;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }
        
        .performer-details {
            display: flex;
            flex-direction: column;
        }
        
        .performer-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-dark);
        }
        
        .performer-meta {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .performer-score {
            font-weight: 700;
            font-size: 14px;
            color: var(--primary-maroon);
            background: rgba(128, 0, 0, 0.05);
            padding: 4px 8px;
            border-radius: 6px;
        }

        @media (max-width: 992px) {
            .stats-grid, .dashboard-main-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        
        /* Modal Styles */
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
        }
        
        .modal.show .modal-content {
            transform: translateY(0);
        }
        
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
            border-bottom: 2px solid var(--border-color) !important;
            background-color: #f8fafc !important;
            letter-spacing: 0.5px !important;
        }
        
        .modal-content td {
            padding: 12px 14px !important;
            font-size: 13px !important;
            border-bottom: 1px solid var(--border-color) !important;
            color: var(--text-dark) !important;
            vertical-align: middle !important;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: var(--primary-maroon);
            font-weight: 700;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text-muted);
            cursor: pointer;
            line-height: 1;
            transition: var(--transition);
        }
        
        .close-modal:hover {
            color: var(--primary-maroon);
            transform: scale(1.1);
        }

        /* Hover states for Table Row / clickable link */
        .clickable-row {
            cursor: pointer;
            transition: var(--transition);
        }
        .clickable-row:hover {
            background-color: rgba(128, 0, 0, 0.03) !important;
        }
        
        .stat-count-pill.interactive {
            cursor: pointer;
            transition: var(--transition);
        }
        .stat-count-pill.interactive:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <a href="https://erp.gmit.info/gmu_ac/output/hOD/index.php" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: white; background: var(--primary-maroon); padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15); transition: var(--transition);" onmouseover="this.style.background='#600000'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='var(--primary-maroon)'; this.style.transform='translateY(0)'">
                <i class="fas fa-home"></i> Back to ERP Home
            </a>
        </div>
        <div class="page-header">
            <div>
                <h2>HOD Dashboard</h2>
                <p><?php echo htmlspecialchars($deptLabel); ?> • Academic Tracking</p>
            </div>
            <div style="display: flex; gap: 15px;">
                <a href="campus_drives.php" class="quick-monitor-btn" style="background-color: #d97706; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.2);">
                    <i class="fas fa-briefcase"></i> View Campus Drives
                </a>
                <a href="ai_monitor.php" class="quick-monitor-btn">
                    <i class="fas fa-robot"></i> Monitor Student Engagement
                </a>
            </div>
        </div>

        <!-- Metric summaries -->
        <div class="stats-grid">
            <!-- Card 1: Total strength -->
            <div class="summary-card">
                <div class="summary-info">
                    <h3>Total Strength</h3>
                    <div class="main-val"><?php echo (int) $totalStudents5to8; ?></div>
                    <p>Registered students (Semesters 5, 6, 7, 8)</p>
                </div>
                <div class="summary-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>

            <!-- Card 2: Placement Readiness Index -->
            <div class="pri-card">
                <div class="summary-info">
                    <h3>Placement Readiness</h3>
                    <div class="main-val" style="color: var(--primary-gold); margin-bottom: 8px;"><?php echo $priIndex; ?>%</div>
                    <div class="pri-breakdown" style="font-size: 11px; color: var(--text-muted); display: flex; flex-direction: column; gap: 4px; font-weight: 500;">
                        <div><i class="fas fa-file-alt" style="color: var(--primary-maroon); width: 14px;"></i> Resumes Uploaded: <strong><?php echo round($resumeRate); ?>%</strong></div>
                        <div><i class="fas fa-robot" style="color: var(--primary-maroon); width: 14px;"></i> Mock AI Completed: <strong><?php echo round($mockRate); ?>%</strong></div>
                        <div><i class="fas fa-tasks" style="color: var(--primary-maroon); width: 14px;"></i> Tasks Completed: <strong><?php echo round($taskRate); ?>%</strong></div>
                    </div>
                </div>
                <div class="pri-circle-wrap" style="--pri-percent: <?php echo $priIndex; ?>;">
                    <svg width="70" height="70" viewBox="0 0 80 80">
                        <circle class="pri-circle-bg" cx="40" cy="40" r="35"></circle>
                        <circle class="pri-circle-progress" cx="40" cy="40" r="35"></circle>
                    </svg>
                    <div class="pri-val-text"><?php echo round($priIndex); ?>%</div>
                </div>
            </div>

            <!-- Card 3: Coordinator Stats Summary -->
            <div class="sem-breakdown-card" style="padding: 20px 24px;">
                <h3 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">Coordinator Activity</h3>
                <div class="sem-list" style="grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div class="sem-pill" style="padding: 8px 10px;">
                        <span class="sem-label" style="font-size: 11px;">Tasks</span>
                        <span class="sem-count" style="font-size: 18px;"><?php echo $totalTasksAssigned; ?></span>
                    </div>
                    <div class="sem-pill" style="padding: 8px 10px;">
                        <span class="sem-label" style="font-size: 11px;">Completions</span>
                        <span class="sem-count" style="color: #166534; font-size: 18px;"><?php echo $totalCompletions; ?></span>
                    </div>
                </div>
                <div style="margin-top: 8px; text-align: center;">
                    <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                        Avg: <strong><?php echo ($totalTasksAssigned > 0) ? round($totalCompletions / $totalTasksAssigned, 1) : 0; ?></strong> completions/task
                    </span>
                </div>
            </div>
        </div>

        <!-- Two Column Content Grid -->
        <div class="dashboard-main-grid">
            <!-- Left Column: Coordinator tracking table -->
            <div class="section-card" style="margin-bottom: 0;">
                <h3><i class="fas fa-user-shield"></i> Department Coordinators Activity</h3>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Coordinator</th>
                                <th style="text-align: center;">Tasks Assigned</th>
                                <th style="text-align: center;">Completions</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($coordinatorStats)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        No coordinators registered for the <?php echo htmlspecialchars($department); ?> department.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($coordinatorStats as $stat): ?>
                                    <tr class="clickable-row" onclick="viewCoordinatorTasks(<?php echo $stat['id']; ?>, '<?php echo htmlspecialchars($stat['name'], ENT_QUOTES); ?>')">
                                        <td>
                                            <div class="coord-name-wrap">
                                                <span class="coord-name"><?php echo htmlspecialchars($stat['name']); ?></span>
                                                <span class="coord-email"><?php echo htmlspecialchars($stat['email']); ?></span>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="stat-count-pill interactive"><?php echo $stat['tasks_assigned']; ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="stat-count-pill" style="background-color: #e0f2fe; color: #0369a1;"><?php echo $stat['completions']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($stat['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Top Performers (Wall of Fame) -->
            <div class="top-performers-card">
                <h3><i class="fas fa-trophy"></i> Top Performers</h3>
                <div class="performer-list">
                    <?php if (empty($topPerformers)): ?>
                        <div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 13px;">
                            No performance data recorded yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($topPerformers as $index => $perf): 
                            $rank = $index + 1;
                            $rankClass = ($rank == 1) ? 'rank-1' : (($rank == 2) ? 'rank-2' : (($rank == 3) ? 'rank-3' : 'rank-other'));
                            $rankIcon = ($rank == 1) ? '<i class="fas fa-crown"></i>' : (($rank == 2) ? '<i class="fas fa-award"></i>' : $rank);
                        ?>
                            <div class="performer-row">
                                <div class="performer-info-left">
                                    <div class="performer-rank <?php echo $rankClass; ?>">
                                        <?php echo $rankIcon; ?>
                                    </div>
                                    <div class="performer-details">
                                        <span class="performer-name"><?php echo htmlspecialchars($perf['name']); ?></span>
                                        <span class="performer-meta"><?php echo htmlspecialchars($perf['usn']); ?> • <?php echo htmlspecialchars($perf['institution']); ?></span>
                                    </div>
                                </div>
                                <div class="performer-score">
                                    <?php echo number_format($perf['total'], 1); ?> pts
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tasks List Modal -->
    <div id="tasksModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Coordinator Tasks</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div style="overflow-x: auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #cbd5e1; background-color:#f8fafc;">
                            <th style="padding:12px 10px; text-align:left; font-weight:700;">Task Title</th>
                            <th style="padding:12px 10px; text-align:left; font-weight:700;">Type</th>
                            <th style="padding:12px 10px; text-align:left; font-weight:700;">Target/Role Info</th>
                            <th style="padding:12px 10px; text-align:center; font-weight:700;">Targeted Students</th>
                            <th style="padding:12px 10px; text-align:center; font-weight:700;">Completions</th>
                            <th style="padding:12px 10px; text-align:left; font-weight:700;">Deadline</th>
                        </tr>
                    </thead>
                    <tbody id="modalTasksBody">
                        <!-- Tasks list will be loaded here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function closeModal() {
            const modal = document.getElementById('tasksModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Close modal when clicking outside of modal-content
        window.onclick = function(event) {
            const modal = document.getElementById('tasksModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        function viewCoordinatorTasks(coordId, coordName) {
            const modal = document.getElementById('tasksModal');
            const titleEl = document.getElementById('modalTitle');
            const tbody = document.getElementById('modalTasksBody');
            
            titleEl.innerText = "Tasks Assigned by " + coordName;
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size: 28px; color: var(--primary-maroon);"></i><br><span style="display:inline-block; margin-top:12px; color:var(--text-muted); font-weight:500;">Retrieving coordinator tasks...</span></td></tr>';
            
            modal.style.display = 'flex';
            // Trigger reflow for transition
            modal.offsetHeight;
            modal.classList.add('show');
            
            const formData = new FormData();
            formData.append('action', 'fetch_coordinator_tasks');
            formData.append('coordinator_id', coordId);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.tasks.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted); font-weight:500;">No tasks assigned by this coordinator yet.</td></tr>';
                        return;
                    }
                    
                    let html = '';
                    data.tasks.forEach(task => {
                        let typeBadge = '';
                        if (task.task_type === 'aptitude') typeBadge = '<span class="badge-type badge-aptitude">Aptitude</span>';
                        else if (task.task_type === 'technical') typeBadge = '<span class="badge-type badge-technical">Technical</span>';
                        else if (task.task_type === 'hr') typeBadge = '<span class="badge-type badge-hr">HR</span>';
                        
                        let targetInfo = '';
                        if (task.company_name) {
                            targetInfo += '<strong>Company:</strong> ' + escapeHtml(task.company_name);
                        }
                        if (task.concept) {
                            if (targetInfo) targetInfo += '<br>';
                            targetInfo += '<strong>Role/Concept:</strong> ' + escapeHtml(task.concept);
                        }
                        if (!targetInfo) {
                            targetInfo = '<span style="color:var(--text-muted); font-style:italic;">General Practice</span>';
                        }
                        
                        html += '<tr style="border-bottom: 1px solid var(--border-color);">';
                        html += '<td style="padding:14px 10px; font-weight:600; color:var(--text-dark);">' + escapeHtml(task.title) + '</td>';
                        html += '<td style="padding:14px 10px;">' + typeBadge + '</td>';
                        html += '<td style="padding:14px 10px; font-size:12px; line-height:1.4;">' + targetInfo + '</td>';
                        html += '<td style="padding:14px 10px; text-align:center;"><span class="stat-count-pill" style="font-size:12px; font-weight:700;">' + task.targeted_count + '</span></td>';
                        html += '<td style="padding:14px 10px; text-align:center;"><span class="stat-count-pill" style="background-color:#e0f2fe; color:#0369a1; font-size:12px; font-weight:700;">' + task.completed_count + '</span></td>';
                        html += '<td style="padding:14px 10px; font-weight:500; font-size:12px; color:var(--text-muted);">' + task.deadline_formatted + '</td>';
                        html += '</tr>';
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#ef4444; font-weight:600;">Error: ' + escapeHtml(data.message) + '</td></tr>';
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#ef4444; font-weight:600;">Failed to load tasks. Please try again.</td></tr>';
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>
