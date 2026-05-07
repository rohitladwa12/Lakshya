<?php
/**
 * Department Analytics Page
 * Visual overview of student progress in the department
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_DEPT_COORDINATOR);

$pageId = 'coordinator_analytics';

// Handle POST (Filters, Reset, Export Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SessionFilterHelper::handlePostToSession($pageId, $_POST);
    header("Location: analytics.php");
    exit;
}

// Handle GET Reset
if (isset($_GET['reset'])) {
    SessionFilterHelper::clearFilters($pageId);
    header("Location: analytics.php");
    exit;
}

$filters = SessionFilterHelper::getFilters($pageId);

$fullName = getFullName();
$coordinatorId = getUserId();
$department = getDepartment();
$semester_filter = getCoordinatorSemesterFilters($department);
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptLabel = (is_array($discipline_filters) && count($discipline_filters) > 1 && $discipline_filters[0] !== $discipline_filters[1])
    ? $discipline_filters[0] . ' & ' . $discipline_filters[1]
    : $department;

// Institution Filter
$instFilter = $filters['inst'] ?? 'all';
$validInstitutions = ['GMU', 'GMIT'];
if (!in_array($instFilter, $validInstitutions)) {
    $instFilter = 'all';
}

$studentModel = new StudentProfile();
$coordFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semester_filter
];
if ($instFilter !== 'all') {
    $coordFilters['institution'] = $instFilter;
}
$students = $studentModel->getAllWithUsers($coordFilters);
$studentCount = count($students);

// Extract USNs/IDs
$studentIds = array_column($students, 'usn');

$metrics = [
    'skills' => 0,
    'certifications' => 0,
    'projects' => 0,
    'resumes' => 0,
    'mock_interviews' => 0,
    'assessments' => 0
];

if (!empty($studentIds)) {
    $db = getDB();
    // PDO doesn't support binding arrays directly in IN clause easily without building it
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

    // --- Pre-calculate Metrics for both UI and Reports ---
    $getPercentage = function ($count, $total) {
        if ($total <= 0) return 0;
        return round(($count / $total) * 100);
    };

    // 1. Portfolio Metrics (Skills, Certifications, Projects)
    $portfolioSql = "SELECT category, COUNT(DISTINCT student_id) as count FROM student_portfolio WHERE student_id IN ($placeholders) GROUP BY category";
    $stmt = $db->prepare($portfolioSql);
    $stmt->execute($studentIds);
    $portData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Resumes Built
    $resumeSql = "SELECT COUNT(DISTINCT student_id) FROM student_resumes WHERE student_id IN ($placeholders)";
    $stmt = $db->prepare($resumeSql);
    $stmt->execute($studentIds);
    $resCount = (int)$stmt->fetchColumn();

    // 3. Mock AI Interview Sessions
    $mockSql = "SELECT COUNT(DISTINCT student_id) FROM mock_ai_interview_sessions WHERE student_id IN ($placeholders)";
    $stmt = $db->prepare($mockSql);
    $stmt->execute($studentIds);
    $mkCount = (int)$stmt->fetchColumn();

    // 4. Unified AI Assessments
    $assessmentSql = "SELECT COUNT(DISTINCT student_id) FROM unified_ai_assessments WHERE student_id IN ($placeholders)";
    $stmt = $db->prepare($assessmentSql);
    $stmt->execute($studentIds);
    $asCount = (int)$stmt->fetchColumn();

    $metrics = [
        'skills' => $portData['Skill'] ?? 0,
        'certifications' => $portData['Certification'] ?? 0,
        'projects' => $portData['Project'] ?? 0,
        'resumes' => $resCount,
        'mock_interviews' => $mkCount,
        'assessments' => $asCount
    ];

    // --- 5. Assigned Task Performance (Grouped by Category) ---
    $tasksDataMap = [];
    $stmtT = $db->prepare("SELECT * FROM coordinator_tasks WHERE coordinator_id = ? ORDER BY created_at DESC");
    $stmtT->execute([$coordinatorId]);
    $repoTasks = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    foreach ($repoTasks as $task) {
        $taskId = $task['id'];
        $type = $task['task_type'];
        if (!isset($tasksDataMap[$type])) {
            $tasksDataMap[$type] = [
                'type' => $type,
                'title' => ucfirst($type) . " Assessment Overview",
                'completed_list' => [],
                'pending_list' => [],
                'missed_list' => [],
                'unique_assigned' => [],
                'unique_completed' => [],
                'unique_missed' => []
            ];
        }

        $targetUsns = json_decode($task['target_students'], true) ?: [];
        
        // Filter target USNs by the current institution filter
        $relevantUsns = [];
        foreach ($students as $s) {
            if (in_array($s['usn'], $targetUsns)) {
                $relevantUsns[] = $s['usn'];
                $tasksDataMap[$type]['unique_assigned'][$s['usn']] = true;
            }
        }

        if (empty($relevantUsns)) continue;

        // Get completions for these relevant USNs
        $phTask = implode(',', array_fill(0, count($relevantUsns), '?'));
        $stmtC = $db->prepare("SELECT student_id, score FROM task_completions WHERE task_id = ? AND student_id IN ($phTask)");
        $stmtC->execute(array_merge([$taskId], $relevantUsns));
        $completions = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        $completeMap = [];
        foreach ($completions as $c) $completeMap[$c['student_id']] = $c;

        foreach ($relevantUsns as $ru) {
            // Find student details
            $sDet = null;
            foreach ($students as $s) { if ($s['usn'] == $ru) { $sDet = $s; break; } }
            if (!$sDet) continue;
            
            $sDet['task_title'] = $task['title'];
            $sDet['assigned_at'] = $task['created_at'];

            $isExpired = strtotime($task['deadline']) < time();
            if (isset($completeMap[$ru])) {
                $sDet['score'] = $completeMap[$ru]['score'];
                $tasksDataMap[$type]['completed_list'][$ru . '_' . $taskId] = $sDet;
                $tasksDataMap[$type]['unique_completed'][$ru] = true;
            } else if ($isExpired) {
                $tasksDataMap[$type]['missed_list'][$ru . '_' . $taskId] = $sDet;
                $tasksDataMap[$type]['unique_missed'][$ru] = true;
            } else {
                $tasksDataMap[$type]['pending_list'][$ru . '_' . $taskId] = $sDet;
            }
        }
    }

    // Finalize tasksData array from the map
    $tasksData = [];
    foreach ($tasksDataMap as $type => $data) {
        $totalAssigned = count($data['unique_assigned']);
        $totalCompleted = count($data['unique_completed']);
        $totalMissed = count($data['unique_missed']);
        $tasksData[] = [
            'type' => $type,
            'title' => $data['title'],
            'total' => $totalAssigned,
            'completed' => $totalCompleted,
            'missed' => $totalMissed,
            'completed_list' => array_values($data['completed_list']),
            'pending_list' => array_values($data['pending_list']),
            'missed_list' => array_values($data['missed_list']),
            'percentage' => $getPercentage($totalCompleted, $totalAssigned)
        ];
    }

    // --- Export Logic (Excel & PDF) ---
    if (isset($filters['export']) || isset($filters['pdf']) || isset($filters['pending_pdf'])) {
        $isPdf = isset($filters['pdf']) || isset($filters['pending_pdf']);
        $isMissedOnly = isset($filters['missed_pdf']);
        $pendingType = $filters['type'] ?? null;
        
        // Consume export triggers
        SessionFilterHelper::setFilters($pageId, array_diff_key($filters, ['export'=>1, 'pdf'=>1, 'missed_pdf'=>1, 'type'=>1]));
        
        if (!$isPdf) {
            ob_clean();
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="department_detailed_analytics_'.date('Y-m-d').'.xls"');
            echo '<html><head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
        }
        
        // Fetch detailed data for mapping
        $portfolioDetails = [];
        $stmtP = $db->prepare("SELECT student_id, category, COUNT(*) as count FROM student_portfolio WHERE student_id IN ($placeholders) GROUP BY student_id, category");
        $stmtP->execute($studentIds);
        while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
            $portfolioDetails[$row['student_id']][$row['category']] = $row['count'];
        }

        $resumes = $db->prepare("SELECT DISTINCT student_id FROM student_resumes WHERE student_id IN ($placeholders)");
        $resumes->execute($studentIds);
        $resumes = $resumes->fetchAll(PDO::FETCH_COLUMN);

        $mocks = $db->prepare("SELECT DISTINCT student_id FROM mock_ai_interview_sessions WHERE student_id IN ($placeholders)");
        $mocks->execute($studentIds);
        $mocks = $mocks->fetchAll(PDO::FETCH_COLUMN);

        $assessments = $db->prepare("SELECT DISTINCT student_id FROM unified_ai_assessments WHERE student_id IN ($placeholders)");
        $assessments->execute($studentIds);
        $assessments = $assessments->fetchAll(PDO::FETCH_COLUMN);

        // Fetch Current Semesters for GMIT
        $gmitUsns = [];
        foreach ($students as $s) if ($s['institution'] === INSTITUTION_GMIT) $gmitUsns[] = $s['usn'];
        $gmitSems = [];
        if (!empty($gmitUsns)) {
            $phGmit = implode(',', array_fill(0, count($gmitUsns), '?'));
            $stmtC = $db->prepare("SELECT student_id, MAX(semester) FROM student_sem_sgpa WHERE institution = ? AND student_id IN ($phGmit) GROUP BY student_id");
            $stmtC->execute(array_merge([INSTITUTION_GMIT], $gmitUsns));
            $gmitSems = $stmtC->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        if ($isPdf): ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
                <meta charset="UTF-8">
                <title>Department Analytics Report - <?php echo date('Y-m-d'); ?></title>
                <style>
                    body { font-family: 'Outfit', sans-serif; padding: 20px; color: #333; }
                    .report-header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #800000; padding-bottom: 15px; }
                    .report-header h1 { margin: 0; color: #800000; }
                    .report-header p { margin: 5px 0; color: #666; }
                    
                    /* Summary Cards for PDF */
                    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 40px; }
                    .card { border: 1px solid #eee; border-radius: 12px; padding: 15px; text-align: center; background: #fafafa; }
                    .card h3 { font-size: 11px; margin: 0; color: #666; text-transform: uppercase; }
                    .card .value { font-size: 24px; font-weight: 700; color: #800000; margin: 5px 0; }
                    .card .percent { font-size: 12px; color: #16a34a; font-weight: 600; }
                    
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
                    th, td { border: 1px solid #ddd; padding: 8px 5px; text-align: left; }
                    th { background: #800000; color: white; text-transform: uppercase; }
                    tr:nth-child(even) { background: #f9f9f9; }
                    .print-btn { display: block; width: fit-content; margin: 0 auto 20px; padding: 10px 20px; background: #800000; color: white; border: none; border-radius: 5px; cursor: pointer; }
                    @media print { .print-btn { display: none; } }
                </style>
            </head>
            <body>
                <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
                <div class="report-header">
                    <h1><?php echo $isMissedOnly ? "Missed Students Report" : "Department Analytics Report"; ?></h1>
                    <p>Generated on <?php echo date('d M Y'); ?> for <?php echo htmlspecialchars($deptLabel); ?></p>
                    <?php if ($isMissedOnly && $pendingType): ?>
                        <p style="font-weight: 700; color: #dc2626;">Filter: Only Missed <?php echo ucfirst($pendingType); ?> Tasks</p>
                    <?php endif; ?>
                    <p>Total Students: <?php echo $studentCount; ?> | Date: <?php echo date('d M Y'); ?></p>
                </div>

                <div class="summary-grid">
                    <div class="card">
                        <h3>Portfolio Skills</h3>
                        <div class="value"><?php echo $metrics['skills']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['skills'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Certifications</h3>
                        <div class="value"><?php echo $metrics['certifications']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['certifications'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Projects Added</h3>
                        <div class="value"><?php echo $metrics['projects']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['projects'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Resumes Built</h3>
                        <div class="value"><?php echo $metrics['resumes']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['resumes'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>Mock Interviews</h3>
                        <div class="value"><?php echo $metrics['mock_interviews']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['mock_interviews'], $studentCount); ?>% Students</div>
                    </div>
                    <div class="card">
                        <h3>AI Assessments</h3>
                        <div class="value"><?php echo $metrics['assessments']; ?></div>
                        <div class="percent"><?php echo $getPercentage($metrics['assessments'], $studentCount); ?>% Students</div>
                    </div>
                </div>

                <?php if (!empty($tasksData)): ?>
                <div style="margin-top: 20px;">
                    <h2 style="color: #800000; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Assigned Task Performance</h2>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr style="background: #f1f5f9; color: #333;">
                                <th>Task Title</th>
                                <th>Type</th>
                                <th>Assigned</th>
                                <th>Completed</th>
                                <th>Missed</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasksData as $td): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($td['title']); ?></td>
                                <td><?php echo ucfirst($td['type']); ?></td>
                                <td><?php echo $td['total']; ?></td>
                                <td style="color: #16a34a; font-weight: 700;"><?php echo $td['completed']; ?></td>
                                <td style="color: #dc2626; font-weight: 700;"><?php echo count($td['pending_list']) + $td['missed']; ?></td>
                                <td><?php echo $td['percentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
        <?php endif; ?>

        <table border="1" cellspacing="0" cellpadding="5">
            <thead>
                <tr style="background:#800000; color:white;">
                    <th>Institution</th>
                    <th>USN</th>
                    <th>Name</th>
                    <th>Branch</th>
                    <th>Sem</th>
                    <th>Skills</th>
                    <th>Certs</th>
                    <th>Projects</th>
                    <th>Resume</th>
                    <th>Mock AI</th>
                    <th>AI Assess</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): 
                    $usn = $s['usn'];
                    
                    // If Missed Only mode, skip students who have completed the task
                    if ($isMissedOnly && $pendingType) {
                        $isMissed = false;
                        // Check both pending and missed lists as both are "not completed"
                        $combinedMissing = array_merge($tasksDataMap[$pendingType]['pending_list'] ?? [], $tasksDataMap[$pendingType]['missed_list'] ?? []);
                        foreach ($combinedMissing as $pl) {
                            if ($pl['usn'] === $usn) {
                                $isMissed = true;
                                break;
                            }
                        }
                        if (!$isMissed) continue;
                    }

                    $displaySem = ($s['semester'] ?? null) ?: ($gmitSems[$usn] ?? '-');
                    $deptName = $s['department'] ?? '-';
                    
                    $sCount = $portfolioDetails[$usn]['Skill'] ?? 0;
                    $cCount = $portfolioDetails[$usn]['Certification'] ?? 0;
                    $pCount = $portfolioDetails[$usn]['Project'] ?? 0;
                    $hasR = in_array($usn, $resumes) ? 'Yes' : 'No';
                    $hasM = in_array($usn, $mocks) ? 'Yes' : 'No';
                    $hasA = in_array($usn, $assessments) ? 'Yes' : 'No';
                ?>
                    <tr>
                        <td><?php echo $s['institution']; ?></td>
                        <td><?php echo $usn; ?></td>
                        <td><?php echo $s['name']; ?></td>
                        <td><?php echo $deptName; ?></td>
                        <td><?php echo $displaySem; ?></td>
                        <td><?php echo $sCount; ?></td>
                        <td><?php echo $cCount; ?></td>
                        <td><?php echo $pCount; ?></td>
                        <td><?php echo $hasR; ?></td>
                        <td><?php echo $hasM; ?></td>
                        <td><?php echo $hasA; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($isPdf): ?>
            </body>
            </html>
        <?php else: ?>
            </body>
            </html>
        <?php endif; ?>
        <?php exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Analytics - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --shadow: 0 4px 12px rgba(0,0,0,0.05);
            --radius: 16px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
            line-height: 1.6;
        }
        
        .navbar-spacer { height: 70px; }
        
        .main-content { 
            /* Layout handled by navbar.php */
        }
        
        .page-header { 
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .header-info h2 { 
            font-size: 32px; 
            color: var(--primary-maroon); 
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .header-info p { 
            color: var(--text-muted); 
            font-size: 15px; 
        }

        .back-btn {
            text-decoration: none;
            color: var(--primary-maroon);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            transition: transform 0.2s;
        }

        .back-btn:hover {
            transform: translateX(-4px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-5px);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .metric-info h3 {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-main);
            margin: 10px 0;
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .metric-total {
            font-size: 16px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .progress-container {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            margin-top: 15px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }

        .percent-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        /* Metric Specific Colors */
        .skills-theme { background: #eff6ff; color: #2563eb; }
        .skills-bar { background: #2563eb; }
        .skills-bg { background: #dbeafe; color: #1e40af; }

        .certs-theme { background: #fef2f2; color: #dc2626; }
        .certs-bar { background: #dc2626; }
        .certs-bg { background: #fee2e2; color: #991b1b; }

        .projects-theme { background: #f0fdf4; color: #16a34a; }
        .projects-bar { background: #16a34a; }
        .projects-bg { background: #dcfce7; color: #166534; }

        .resumes-theme { background: #faf5ff; color: #9333ea; }
        .resumes-bar { background: #9333ea; }
        .resumes-bg { background: #f3e8ff; color: #6b21a8; }

        .training-theme { background: #fffbeb; color: #d97706; }
        .training-bar { background: #d97706; }
        .training-bg { background: #fef3c7; color: #92400e; }

        .empty-state {
            background: white;
            padding: 60px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: var(--text-main);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-muted);
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn-download {
            background: var(--primary-maroon);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }

        .btn-download:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(128, 0, 0, 0.3);
            color: white;
        }

        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="navbar-spacer"></div>

    <div class="main-content">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="page-header">
            <div class="header-info">
                <h2>Department Analytics</h2>
                <p><?php echo htmlspecialchars($deptLabel); ?> • <?php echo $studentCount; ?> Students Enrolled</p>
            </div>
            <?php if ($studentCount > 0): ?>
            <div class="header-actions">
                <form id="instFilterForm" method="POST" style="display: flex; gap: 10px; align-items: center; background: white; padding: 5px 15px; border-radius: 12px; box-shadow: var(--shadow); border: 1px solid #e2e8f0;">
                    <i class="fas fa-filter" style="color: var(--text-muted); font-size: 14px;"></i>
                    <select name="inst" onchange="this.form.submit()" style="border: none; background: transparent; font-family: 'Outfit', sans-serif; font-weight: 600; color: var(--text-main); font-size: 14px; cursor: pointer; outline: none;">
                        <option value="all" <?php echo $instFilter === 'all' ? 'selected' : ''; ?>>All Institutions</option>
                        <option value="GMU" <?php echo $instFilter === 'GMU' ? 'selected' : ''; ?>>GMU Only</option>
                        <option value="GMIT" <?php echo $instFilter === 'GMIT' ? 'selected' : ''; ?>>GMIT Only</option>
                    </select>
                </form>
                <form method="POST" target="_blank">
                    <input type="hidden" name="pdf" value="1">
                    <button type="submit" class="btn-download" style="background: #2c3e50; border:none; cursor:pointer;">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </button>
                </form>
            </div>
            <?php
endif; ?>
        </div>

        <?php if ($studentCount > 0): ?>
            <!-- 1. Task Completion Overview (Primary Focus) -->
            <?php if (!empty($tasksData)): ?>
            <div style="margin-top: 10px; margin-bottom: 32px;">
                <h3 style="font-size: 22px; color: var(--primary-maroon); font-weight: 700; display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
                    <i class="fas fa-chart-line"></i> Task Completion Overview
                </h3>
                <p style="color: var(--text-muted); font-size: 14px;">Real-time progress of assessments assigned by you.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 48px;">
                <?php foreach ($tasksData as $task): ?>
                <div class="metric-card" style="padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); min-height: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div>
                            <span style="font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">
                                <?php echo ucfirst($task['type']); ?> TRACKING
                            </span>
                            <h3 style="font-size: 18px; color: var(--text-main); margin-top: 2px; font-weight: 700;"><?php echo htmlspecialchars($task['title']); ?></h3>
                        </div>
                        <div style="background: <?php echo $task['percentage'] > 70 ? '#dcfce7' : ($task['percentage'] > 30 ? '#fef3c7' : '#fee2e2'); ?>; color: <?php echo $task['percentage'] > 70 ? '#166534' : ($task['percentage'] > 30 ? '#92400e' : '#991b1b'); ?>; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 800;">
                            <?php echo $task['percentage']; ?>%
                        </div>
                    </div>

                    <div style="display: flex; gap: 24px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 20px; font-weight: 800; color: #16a34a;"><?php echo $task['completed']; ?></div>
                            <div style="font-size: 10px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">DONE</div>
                        </div>
                        <div style="padding-left: 24px; border-left: 1px solid #f1f5f9;">
                            <div style="font-size: 20px; font-weight: 800; color: #ef4444;"><?php echo count($task['pending_list']) + $task['missed']; ?></div>
                            <div style="font-size: 10px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">MISSED</div>
                        </div>
                        <div style="padding-left: 24px; border-left: 1px solid #f1f5f9;">
                            <div style="font-size: 20px; font-weight: 800; color: var(--text-main);"><?php echo $task['total']; ?></div>
                            <div style="font-size: 10px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">TOTAL</div>
                        </div>
                    </div>

                    <div class="progress-container" style="height: 8px; margin-bottom: 20px; background: #f1f5f9; border-radius: 4px;">
                        <div class="progress-bar" style="width: <?php echo $task['percentage']; ?>%; background: linear-gradient(90deg, #16a34a, #22c55e); border-radius: 4px;"></div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button onclick="showTaskDetails('<?php echo $task['type']; ?>')" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; color: var(--text-main); font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-list-check"></i> View Detailed Breakdown
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- 2. Portfolio Highlights (Secondary Context) -->
            <div style="margin-top: 32px; margin-bottom: 24px;">
                <h3 style="font-size: 20px; color: var(--primary-maroon); font-weight: 700; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-layer-group"></i> General Portfolio Highlights
                </h3>
            </div>

            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <?php 
                $highlightMetrics = [
                    ['label' => 'Skills Added', 'key' => 'skills', 'icon' => 'fa-bolt', 'color' => '#fbbf24'],
                    ['label' => 'Certifications', 'key' => 'certifications', 'icon' => 'fa-certificate', 'color' => '#3b82f6'],
                    ['label' => 'Projects', 'key' => 'projects', 'icon' => 'fa-project-diagram', 'color' => '#8b5cf6'],
                    ['label' => 'Resumes', 'key' => 'resumes', 'icon' => 'fa-file-invoice', 'color' => '#ec4899'],
                    ['label' => 'Mock Starts', 'key' => 'mock_interviews', 'icon' => 'fa-headset', 'color' => '#10b981'],
                    ['label' => 'AI Assess', 'key' => 'assessments', 'icon' => 'fa-robot', 'color' => '#6366f1']
                ];
                foreach ($highlightMetrics as $hm): 
                    $p = $getPercentage($metrics[$hm['key']], $studentCount);
                ?>
                <div class="metric-card" style="padding: 16px; min-height: auto;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: <?php echo $hm['color']; ?>15; color: <?php echo $hm['color']; ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                            <i class="fas <?php echo $hm['icon']; ?>"></i>
                        </div>
                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;"><?php echo $hm['label']; ?></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                        <div style="font-size: 20px; font-weight: 800; color: var(--text-main);"><?php echo $metrics[$hm['key']]; ?></div>
                        <div style="font-size: 12px; font-weight: 700; color: #16a34a;"><?php echo $p; ?>%</div>
                    </div>
                    <div style="height: 4px; background: #f1f5f9; border-radius: 2px; margin-top: 8px;">
                        <div style="width: <?php echo $p; ?>%; height: 100%; background: <?php echo $hm['color']; ?>; border-radius: 2px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 32px; border-top: 1px solid #f1f5f9; padding-top: 24px; display: flex; justify-content: flex-end; gap: 15px;">
                <form method="POST">
                    <input type="hidden" name="export" value="1">
                    <button type="submit" style="padding: 10px 20px; border-radius: 10px; border: 1px solid #e2e8f0; background: white; color: var(--text-main); cursor:pointer; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                </form>
                <form method="POST" target="_blank">
                    <input type="hidden" name="pdf" value="1">
                    <button type="submit" style="padding: 10px 20px; border-radius: 10px; background: var(--primary-maroon); color: white; cursor:pointer; border:none; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-file-pdf"></i> Download Full Report
                    </button>
                </form>
            </div>

            <!-- Detail Modals -->
            <?php foreach ($tasksData as $task): ?>
                <div id="modal_<?php echo $task['type']; ?>" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999; padding: 20px; align-items: center; justify-content: center;">
                    <div style="background: white; width: 100%; max-width: 900px; max-height: 85vh; border-radius: 24px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                        <div style="padding: 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="font-size: 20px; font-weight: 700; color: var(--primary-maroon);"><?php echo htmlspecialchars($task['title']); ?> - Detailed View</h3>
                                <p style="font-size: 14px; color: var(--text-muted);"><?php echo $task['total']; ?> Total Assignments</p>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <a href="?missed_pdf=1&type=<?php echo $task['type']; ?>&inst=<?php echo $instFilter; ?>" target="_blank" style="padding: 8px 16px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-file-pdf"></i> Download Missed List
                                </a>
                                <button onclick="hideTaskDetails('<?php echo $task['type']; ?>')" style="background: #f1f5f9; border: none; width: 40px; height: 40px; border-radius: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div style="overflow-y: auto; padding: 24px;">
                            <!-- Summary Row -->
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
                                <div style="background: #f0fdf4; padding: 15px; border-radius: 16px; border: 1px solid #dcfce7; text-align: center;">
                                    <h4 style="font-size: 11px; color: #166534; text-transform: uppercase;">Completed</h4>
                                    <div style="font-size: 24px; font-weight: 800; color: #166534;"><?php echo $task['completed']; ?></div>
                                </div>
                                <div style="background: #fff5f5; padding: 15px; border-radius: 16px; border: 1px solid #fee2e2; text-align: center;">
                                    <h4 style="font-size: 11px; color: #991b1b; text-transform: uppercase;">Missed</h4>
                                    <div style="font-size: 24px; font-weight: 800; color: #991b1b;"><?php echo count($task['pending_list']) + $task['missed']; ?></div>
                                </div>
                                <div style="background: #f8fafc; padding: 15px; border-radius: 16px; border: 1px solid #e2e8f0; text-align: center;">
                                    <h4 style="font-size: 11px; color: #475569; text-transform: uppercase;">Rate</h4>
                                    <div style="font-size: 24px; font-weight: 800; color: #475569;"><?php echo $task['percentage']; ?>%</div>
                                </div>
                            </div>

                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="text-align: left; background: #f8fafc; border-radius: 10px;">
                                        <th style="padding: 12px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Student Info</th>
                                        <th style="padding: 12px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Assigned Task</th>
                                        <th style="padding: 12px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Status</th>
                                        <th style="padding: 12px; font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($task['completed_list'] as $s): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo $s['usn']; ?></div>
                                        </td>
                                        <td style="padding: 12px; font-size: 13px;">
                                            <div><?php echo htmlspecialchars($s['task_title']); ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($s['assigned_at'])); ?></div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">COMPLETED</span>
                                        </td>
                                        <td style="padding: 12px; font-weight: 700; color: #166534;">Score: <?php echo $s['score'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($task['missed_list'] as $s): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo $s['usn']; ?></div>
                                        </td>
                                        <td style="padding: 12px; font-size: 13px;">
                                            <div><?php echo htmlspecialchars($s['task_title']); ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($s['assigned_at'])); ?></div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: #ffebee; color: #c62828; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">MISSED</span>
                                        </td>
                                        <td style="padding: 12px;">-</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($task['pending_list'] as $s): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($s['name']); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo $s['usn']; ?></div>
                                        </td>
                                        <td style="padding: 12px; font-size: 13px;">
                                            <div><?php echo htmlspecialchars($s['task_title']); ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($s['assigned_at'])); ?></div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">MISSED</span>
                                        </td>
                                        <td style="padding: 12px;">-</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <script>
                function showTaskDetails(type) {
                    document.getElementById('modal_' + type).style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
                function hideTaskDetails(type) {
                    document.getElementById('modal_' + type).style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
                // Close on background click
                window.onclick = function(event) {
                    if (event.target.id.startsWith('modal_')) {
                        event.target.style.display = "none";
                        document.body.style.overflow = 'auto';
                    }
                }
            </script>
        <?php
else: ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Students Found</h3>
                <p>There are no students currently enrolled in your department for the selected filters.</p>
            </div>
        <?php
endif; ?>
    </div>

</body>
</html>

