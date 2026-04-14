<?php
/**
 * Manage Tasks Page
 * View assigned tasks and track student completion with full academic details
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$coordinatorId = getUserId();
$db = getDB();
$remoteDB = getDB('gmu');

// Get coordinator's department and institution
$stmt = $db->prepare("SELECT department, institution FROM dept_coordinators WHERE id = ?");
$stmt->execute([$coordinatorId]);
$coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
$department = $coordinator['department'];
$institution = $coordinator['institution'];

// Get consolidated branch and semester filters
$discipline_filters = getCoordinatorDisciplineFilters($department);
$semester_filter = getCoordinatorSemesterFilters($department);

$discipline_placeholders = implode(',', array_fill(0, count($discipline_filters), '?'));
$sem_placeholders = implode(',', array_fill(0, count($semester_filter), '?'));

// Fetch all tasks created by this coordinator
$stmt = $db->prepare("SELECT * FROM coordinator_tasks WHERE coordinator_id = ? ORDER BY created_at DESC");
$stmt->execute([$coordinatorId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get task details if viewing specific task
$viewingTask = null;
$taskStudents = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $taskId = (int) $_GET['view'];

    // Get task details
    $stmt = $db->prepare("SELECT * FROM coordinator_tasks WHERE id = ? AND coordinator_id = ?");
    $stmt->execute([$taskId, $coordinatorId]);
    $viewingTask = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($viewingTask) {
        // Fetch GMU students
        $gmuStudents = [];
        $gmuQuery = "SELECT ad.usn as student_id, ad.name, asd.email_id as email,
                            ad.discipline as branch, ad.sem as current_sem,
                            ad.sgpa, 'GMU' as institution
                     FROM gmu.ad_student_approved ad
                     LEFT JOIN gmu.ad_student_details asd ON ad.usn = asd.usn
                     WHERE ad.discipline IN ($discipline_placeholders)
                       AND ad.sem IN ($sem_placeholders)
                       AND (ad.usn, ad.year, ad.sem) IN (
                           SELECT usn, MAX(year), MAX(sem)
                           FROM gmu.ad_student_approved
                           WHERE discipline IN ($discipline_placeholders)
                           GROUP BY usn
                       )
                     ORDER BY ad.name";
        $stmt = $remoteDB->prepare($gmuQuery);
        $stmt->execute(array_merge($discipline_filters, $semester_filter, $discipline_filters));
        $gmuStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch GMIT students
        $gmitStudents = [];
        $gmitQuery = "SELECT ad.student_id, ad.name, ad.email_id as email,
                             ad.discipline as branch,
                             'GMIT' as institution
                      FROM gmit_new.ad_student_details ad
                      WHERE ad.discipline IN ($discipline_placeholders)
                      ORDER BY ad.name";
        $stmt = $remoteDB->prepare($gmitQuery);
        $stmt->execute($discipline_filters);
        $gmitStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch SGPA from student_sem_sgpa table (max semester)
        $gmitStudentsWithSgpa = [];
        foreach ($gmitStudents as $student) {
            $stmt = $localDB->prepare("SELECT sgpa, semester FROM student_sem_sgpa 
                                       WHERE student_id = ? AND institution = ? AND semester IN ($sem_placeholders)
                                       ORDER BY semester DESC LIMIT 1");
            $stmt->execute(array_merge([$student['student_id'], 'GMIT'], $semester_filter));
            $sgpaData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sgpaData) {
                $student['sgpa'] = $sgpaData['sgpa'];
                $student['current_sem'] = $sgpaData['semester'];
                $gmitStudentsWithSgpa[] = $student;
            }
        }
    }
    $gmitStudents = $gmitStudentsWithSgpa;

    // Merge all eligible students (filtering was already done in the loops above)
    $allStudents = array_merge($gmuStudents, $gmitStudents);

    // Fetch completion data
    $stmt = $db->prepare("SELECT student_id, score, time_taken, completed_at 
                              FROM task_completions WHERE task_id = ?");
    $stmt->execute([$taskId]);
    $completions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $completions[$row['student_id']] = $row;
    }

    // Merge completion data with student data
    foreach ($allStudents as &$student) {
        $completion = $completions[$student['student_id']] ?? null;
        $student['status'] = $completion ? 'completed' : 'pending';
        $student['score'] = $completion['score'] ?? null;
        $student['completed_at'] = $completion['completed_at'] ?? null;
        $student['time_taken'] = $completion['time_taken'] ?? null;
    }

    $taskStudents = $allStudents;
}

// Calculate stats for each task
foreach ($tasks as &$task) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM task_completions WHERE task_id = ?");
    $stmt->execute([$task['id']]);
    $task['completed_count'] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tasks - <?php echo APP_NAME; ?></title>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
        }

        .navbar-spacer {
            height: 70px;
        }

        .container {
            width: 100%;
            margin: 40px 0;
            padding: 0 40px;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .page-header h2 {
            font-size: 32px;
            color: var(--primary-maroon);
            font-weight: 700;
        }

        .btn-primary {
            background: var(--primary-maroon);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tasks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .task-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .task-card.aptitude {
            border-left-color: #3498db;
        }

        .task-card.technical {
            border-left-color: #e74c3c;
        }

        .task-card.hr {
            border-left-color: #2ecc71;
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .task-type-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .task-type-badge.aptitude {
            background: #e3f2fd;
            color: #1976d2;
        }

        .task-type-badge.technical {
            background: #ffebee;
            color: #c62828;
        }

        .task-type-badge.hr {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .task-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .task-meta {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .completion-bar {
            background: #e2e8f0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .completion-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-maroon), var(--primary-gold));
            transition: width 0.3s;
        }

        .completion-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .task-actions {
            margin-top: 16px;
            display: flex;
            gap: 8px;
        }

        .btn-view {
            flex: 1;
            background: var(--primary-maroon);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
        }

        .table-container {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-title {
            font-size: 20px;
            font-weight: 700;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
        }

        .filter-tab {
            padding: 6px 16px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            background: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }

        .filter-tab.active {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 700;
            color: var(--text-main);
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .score-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 700;
        }

        .score-high {
            background: #d4edda;
            color: #155724;
        }

        .score-medium {
            background: #fff3cd;
            color: #856404;
        }

        .score-low {
            background: #f8d7da;
            color: #721c24;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="navbar-spacer"></div>

    <div class="container">
        <?php if ($viewingTask): ?>
            <a href="manage_tasks.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to All Tasks
            </a>

            <div class="page-header">
                <div>
                    <h2><?php echo htmlspecialchars($viewingTask['title']); ?></h2>
                    <p style="color: var(--text-muted); margin-top: 8px;">
                        <span class="task-type-badge <?php echo $viewingTask['task_type']; ?>">
                            <?php echo strtoupper($viewingTask['task_type']); ?>
                        </span>
                        <?php if ($viewingTask['company_name']): ?>
                            | <?php echo htmlspecialchars($viewingTask['company_name']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Student Completion Tracking</div>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterTable('all')">All</button>
                        <button class="filter-tab" onclick="filterTable('completed')">Completed</button>
                        <button class="filter-tab" onclick="filterTable('pending')">Pending</button>
                    </div>
                </div>

                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Institution</th>
                            <th>Branch</th>
                            <th>Sem</th>
                            <th>CGPA</th>
                            <th>Status</th>
                            <th>Score</th>
                            <th>Completed On</th>
                            <th>Time Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $slNo = 1;
                        foreach ($taskStudents as $student):
                            $scoreClass = '';
                            if ($student['score'] !== null) {
                                if ($student['score'] >= 75)
                                    $scoreClass = 'score-high';
                                elseif ($student['score'] >= 50)
                                    $scoreClass = 'score-medium';
                                else
                                    $scoreClass = 'score-low';
                            }
                            ?>
                            <tr data-status="<?php echo $student['status']; ?>">
                                <td><?php echo $slNo++; ?></td>
                                <td><?php echo htmlspecialchars($student['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['institution']); ?></td>
                                <td><?php echo htmlspecialchars($student['branch'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['current_sem'] ?? 'N/A'); ?></td>
                                <td><?php echo $student['cgpa'] ? number_format($student['cgpa'], 2) : 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $student['status']; ?>">
                                        <?php echo $student['status'] === 'completed' ? '✅ Completed' : '⏳ Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($student['score'] !== null): ?>
                                        <span class="score-badge <?php echo $scoreClass; ?>">
                                            <?php echo number_format($student['score'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $student['completed_at'] ? date('d M Y, h:i A', strtotime($student['completed_at'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $student['time_taken'] ? round($student['time_taken'] / 60, 1) . ' min' : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="page-header">
                <h2><i class="fas fa-tasks"></i> Manage Tasks</h2>
                <a href="assign_task.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Assign New Task
                </a>
            </div>

            <?php if (empty($tasks)): ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 16px;">
                    <i class="fas fa-clipboard-list" style="font-size: 64px; color: #cbd5e0; margin-bottom: 20px;"></i>
                    <h3 style="color: var(--text-muted); margin-bottom: 12px;">No Tasks Assigned Yet</h3>
                    <p style="color: var(--text-muted); margin-bottom: 24px;">Create your first task to get started</p>
                    <a href="assign_task.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Assign New Task
                    </a>
                </div>
            <?php else: ?>
                <div class="tasks-grid">
                    <?php foreach ($tasks as $task):
                        $completionPercent = 0; // Will calculate based on student count later
                        ?>
                        <div class="task-card <?php echo $task['task_type']; ?>">
                            <div class="task-header">
                                <span class="task-type-badge <?php echo $task['task_type']; ?>">
                                    <?php echo strtoupper($task['task_type']); ?>
                                </span>
                                <?php if ($task['question_source'] === 'manual'): ?>
                                    <span style="font-size: 12px; color: var(--primary-gold);">
                                        <i class="fas fa-edit"></i> Manual
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>

                            <div class="task-meta">
                                <?php if ($task['company_name']): ?>
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($task['company_name']); ?><br>
                                <?php endif; ?>
                                <i class="fas fa-calendar"></i> Due: <?php echo date('d M Y', strtotime($task['deadline'])); ?>
                            </div>

                            <div class="completion-bar">
                                <div class="completion-fill" style="width: <?php echo $completionPercent; ?>%"></div>
                            </div>
                            <div class="completion-text">
                                <?php echo $task['completed_count']; ?> students completed
                            </div>

                            <div class="task-actions">
                                <a href="?view=<?php echo $task['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function filterTable(status) {
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            const tabs = document.querySelectorAll('.filter-tab');

            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');

            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>