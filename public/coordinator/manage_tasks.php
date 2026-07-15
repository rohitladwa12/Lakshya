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

// Handle deadline update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_deadline'])) {
    $taskId = (int) $_POST['task_id'];
    $deadlineDate = $_POST['deadline_date'] ?? '';
    $hour = (int) ($_POST['deadline_hour'] ?? 0);
    $minute = (int) ($_POST['deadline_minute'] ?? 0);
    $ampm = $_POST['deadline_ampm'] ?? 'AM';

    if ($ampm === 'PM' && $hour < 12)
        $hour += 12;
    if ($ampm === 'AM' && $hour === 12)
        $hour = 0;
    $newDeadline = $deadlineDate . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minute, 2, '0', STR_PAD_LEFT) . ':00';

    $stmtUpdate = $db->prepare("UPDATE coordinator_tasks SET deadline = ? WHERE id = ? AND coordinator_id = ?");
    $stmtUpdate->execute([$newDeadline, $taskId, $coordinatorId]);

    $_SESSION['success_message'] = "Deadline updated successfully!";
    header("Location: manage_tasks.php?view=" . $taskId);
    exit;
}

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
                            ad.sgpa, ad.aadhar, 'GMU' as institution
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
                             ad.discipline as branch, ad.aadhar,
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
            $stmt = $db->prepare("SELECT sgpa, semester FROM student_sem_sgpa 
                                       WHERE (student_id = ? OR student_id = ?) AND institution = ? AND semester IN ($sem_placeholders)
                                       ORDER BY semester DESC LIMIT 1");
            $stmt->execute(array_merge([$student['student_id'], $student['aadhar'], 'GMIT'], $semester_filter));
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
    $rawStudents = array_merge($gmuStudents, $gmitStudents);
    
    // De-duplicate by student_id (case-insensitive and stripping all non-alphanumeric chars)
    $uniqueStudents = [];
    foreach ($rawStudents as $student) {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $student['student_id'] ?? ''));
        if (!empty($key)) {
            $uniqueStudents[$key] = $student;
        }
    }
    $allStudents = array_values($uniqueStudents);

    // Filter to ONLY show students assigned to this task
    $targetStudents = json_decode($viewingTask['target_students'] ?? '[]', true) ?: [];
    if ($viewingTask['target_type'] === 'individual' && !empty($targetStudents)) {
        $filteredStudents = [];
        foreach ($allStudents as $student) {
            if (in_array($student['student_id'], $targetStudents)) {
                $filteredStudents[] = $student;
            }
        }
        $allStudents = $filteredStudents;
    }

    // Fetch completion data (time_taken, completed_at from task_completions)
    $stmt = $db->prepare("SELECT student_id, score, time_taken, completed_at 
                              FROM task_completions WHERE task_id = ?");
    $stmt->execute([$taskId]);
    $completions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $completions[$row['student_id']] = $row;
    }

    // Fetch SCORES from unified_ai_assessments (authoritative AI-evaluated score)
    // We match on the task_id stored inside the details JSON field.
    $uaiScores = [];
    try {
        $stmtUai = $db->prepare(
            "SELECT usn AS student_id, score 
             FROM unified_ai_assessments 
             WHERE status = 'completed' 
               AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.task_id')) = ?"
        );
        $stmtUai->execute([(string) $taskId]);
        while ($row = $stmtUai->fetch(PDO::FETCH_ASSOC)) {
            if ($row['score'] !== null) {
                $uaiScores[$row['student_id']] = (int) $row['score'];
            }
        }
    } catch (Throwable $e) {
        error_log("manage_tasks: failed to fetch UAI scores: " . $e->getMessage());
    }

    // Check if task is expired
    $isExpired = strtotime($viewingTask['deadline']) < time();

    // Merge completion data with student data
    foreach ($allStudents as &$student) {
        $sid = $student['student_id'];
        $aadhar = $student['aadhar'] ?? '';

        $completion = $completions[$sid] ?? (!empty($aadhar) ? ($completions[$aadhar] ?? null) : null);
        if ($completion) {
            $student['status'] = 'completed';
        } else {
            $student['status'] = $isExpired ? 'missed' : 'pending';
        }

        // Score: prefer unified_ai_assessments, fall back to task_completions
        $student['score'] = $uaiScores[$sid] ?? (!empty($aadhar) ? ($uaiScores[$aadhar] ?? null) : null)
                            ?? $completion['score'] ?? null;

        $student['completed_at'] = $completion['completed_at'] ?? null;
        $student['time_taken']   = $completion['time_taken']   ?? null;
    }
    unset($student); // Crucial fix: break the reference before the next loop

    $taskStudents = $allStudents;
}

// Calculate stats for each task
foreach ($tasks as &$task) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM task_completions WHERE task_id = ?");
    $stmt->execute([$task['id']]);
    $task['completed_count'] = $stmt->fetchColumn();
}
unset($task); // Break the reference to avoid overwriting elements in the next loop
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tasks - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-maroon-hover: #600000;
            --primary-gold: #D4AF37;
            --primary-gold-hover: #c4a137;
            --dark-blue: #0f172a;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            
            --success: #10b981;
            --success-bg: #ecfdf5;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --danger: #ef4444;
            --danger-bg: #fef2f2;
            --info: #3b82f6;
            --info-bg: #eff6ff;
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
            line-height: 1.5;
        }

        .container {
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            padding: 40px;
        }

        .page-header {
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h2 {
            font-size: 32px;
            color: var(--primary-maroon);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            color: var(--text-muted);
            margin-top: 6px;
            font-size: 15px;
        }

        .btn-primary {
            background: var(--primary-maroon);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
        }

        .btn-primary:hover {
            background: var(--primary-maroon-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.25);
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.total {
            background: var(--info-bg);
            color: var(--info);
        }

        .stat-icon.completed {
            background: var(--success-bg);
            color: var(--success);
        }

        .stat-icon.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .stat-icon.missed {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .stat-icon.score {
            background: #faf5ff;
            color: #a855f7;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.2;
        }

        .stat-info p {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ===== TASKS GRID & CARD ===== */
        .tasks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .task-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            border-left: 5px solid;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            border-color: #e2e8f0;
        }

        .task-card.aptitude {
            border-left-color: var(--info);
        }

        .task-card.technical {
            border-left-color: var(--danger);
        }

        .task-card.hr {
            border-left-color: var(--success);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .task-badges-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .task-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .task-type-badge.aptitude {
            background: var(--info-bg);
            color: var(--info);
        }

        .task-type-badge.technical {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .task-type-badge.hr {
            background: var(--success-bg);
            color: var(--success);
        }

        .difficulty-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .difficulty-badge.low {
            background: #e6fcf5;
            color: #0ca678;
        }

        .difficulty-badge.medium {
            background: #fff9db;
            color: #f59f00;
        }

        .difficulty-badge.high {
            background: #fff5f5;
            color: #f03e3e;
        }

        .task-title {
            font-size: 19px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-main);
            line-height: 1.3;
        }

        /* Concepts Section inside cards */
        .task-concepts {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
        }

        .concept-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .concept-tag:hover {
            background: #e2e8f0;
            color: var(--primary-maroon);
        }

        .task-concepts-empty {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 16px;
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .task-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .task-meta span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .task-meta span i {
            width: 16px;
            color: var(--primary-maroon);
        }

        .completion-container {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1.5px solid #f1f5f9;
        }

        .completion-bar {
            background: #f1f5f9;
            height: 8px;
            border-radius: 9999px;
            overflow: hidden;
            margin-bottom: 8px;
            position: relative;
        }

        .completion-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-maroon), var(--primary-gold));
            border-radius: 9999px;
            transition: width 0.5s ease-out;
        }

        .completion-text {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .completion-text span strong {
            color: var(--text-main);
        }

        .task-actions {
            margin-top: 16px;
        }

        .btn-view {
            display: block;
            width: 100%;
            background: #f8fafc;
            color: var(--primary-maroon);
            border: 1.5px solid var(--primary-maroon);
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .btn-view:hover {
            background: var(--primary-maroon);
            color: white;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
        }

        /* ===== DETAILS SCREEN METADATA & BUTTONS ===== */
        .task-details-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-top: 12px;
        }

        .meta-item {
            font-size: 13px;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            padding: 4px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .btn-edit-deadline {
            background: transparent;
            border: 1.5px solid var(--primary-maroon);
            border-radius: 8px;
            padding: 4px 12px;
            color: var(--primary-maroon);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-edit-deadline:hover {
            background: var(--primary-maroon);
            color: white;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
        }

        .task-details-concepts {
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fdfafa;
            border-left: 3px solid var(--primary-maroon);
            padding: 10px 16px;
            border-radius: 0 12px 12px 0;
            flex-wrap: wrap;
        }

        .concepts-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
        }

        .concepts-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .btn-print {
            background: var(--dark-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.25);
            background: #1e293b;
        }

        /* ===== TABLE STYLING ===== */
        .table-container {
            background: var(--white);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            overflow-x: auto;
            margin-top: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }

        .filter-tab:hover {
            color: var(--text-main);
        }

        .filter-tab.active {
            background: var(--white);
            color: var(--primary-maroon);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 1.5px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        th:first-child {
            border-top-left-radius: 8px;
        }
        th:last-child {
            border-top-right-radius: 8px;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #fafafc;
        }

        .student-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-maroon), var(--primary-gold));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
        }

        .student-details-cell {
            display: flex;
            flex-direction: column;
        }

        .student-name {
            font-weight: 600;
            color: var(--text-main);
        }

        .student-usn {
            font-size: 12px;
            color: var(--text-muted);
        }

        .badge-inst {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-inst.gmu {
            background: #ffebee;
            color: #b71c1c;
        }
        .badge-inst.gmit {
            background: #e3f2fd;
            color: #0d47a1;
        }

        /* Status Badge Styling */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.status-completed {
            background: var(--success-bg);
            color: var(--success);
        }

        .status-badge.status-pending {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-badge.status-missed {
            background: var(--danger-bg);
            color: var(--danger);
        }

        /* Score Badge Styling */
        .score-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
        }

        .score-badge.score-high {
            background: var(--success-bg);
            color: var(--success);
        }

        .score-badge.score-medium {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .score-badge.score-low {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 700;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            transform: translateX(-4px);
            color: var(--primary-maroon-hover);
        }

        /* ===== MODAL GENERAL STYLES ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalFadeIn {
            from {
                transform: scale(0.95) translateY(10px);
                opacity: 0;
            }
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .close-modal:hover {
            color: var(--primary-maroon);
        }

        .modal-content h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-maroon);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-main);
            font-size: 14px;
        }

        .deadline-inputs {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .deadline-inputs input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text-main);
        }

        .deadline-inputs input[type="date"]:focus,
        .time-selects select:focus {
            outline: none;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.1);
        }

        .time-selects {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .time-selects select {
            flex: 1;
            padding: 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            background: white;
            color: var(--text-main);
        }

        .time-separator {
            font-weight: 700;
            color: var(--text-muted);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn-cancel {
            flex: 1;
            background: #f1f5f9;
            color: #475569;
            padding: 12px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        .btn-submit {
            flex: 1;
            background: var(--primary-maroon);
            color: white;
            padding: 12px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            background: var(--primary-maroon-hover);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            /* Hide everything UI-related */
            nav, .navbar-spacer, .back-link, .filter-tabs, .no-print,
            button, .btn-primary, .task-card, .tasks-grid,
            .page-header, .stats-grid, .btn-edit-deadline, .task-details-concepts {
                display: none !important;
            }

            body {
                background: #fff;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12pt;
                color: #000;
            }

            .container { margin: 0; padding: 10px 20px; }

            /* Print header block */
            .print-report-header {
                display: block !important;
                border-bottom: 2px solid #000;
                padding-bottom: 12px;
                margin-bottom: 16px;
            }
            .print-report-header h1 {
                font-size: 18pt;
                font-weight: bold;
                margin: 0 0 4px 0;
            }
            .print-report-header p {
                font-size: 10pt;
                margin: 2px 0;
                color: #333;
            }
            .print-stats-row {
                display: flex !important;
                gap: 40px;
                margin-bottom: 12px;
                font-size: 10pt;
            }
            .print-stats-row span strong {
                font-size: 11pt;
            }

            /* Make page-header plain */
            .page-header {
                border-bottom: 1px solid #000;
                padding-bottom: 8px;
                margin-bottom: 12px;
                display: block !important;
            }
            .page-header h2 {
                font-size: 16pt;
                color: #000;
            }

            /* Table */
            .table-container {
                box-shadow: none;
                border-radius: 0;
                border: none;
                margin-top: 15px;
                padding: 0;
            }
            .table-header { display: none !important; }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10pt;
                page-break-inside: auto;
            }
            thead tr {
                background: #000 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            th {
                background: #000 !important;
                color: #fff !important;
                border: 1px solid #000;
                padding: 6px 8px;
                text-align: left;
                font-size: 9pt;
                font-weight: bold;
            }
            td {
                border: 1px solid #bbb;
                padding: 5px 8px;
                vertical-align: middle;
                font-size: 9pt;
            }
            tr:nth-child(even) td { background: #f5f5f5; }
            tr { page-break-inside: avoid; }

            /* Replace colored badges with plain text */
            .status-badge, .score-badge, .difficulty-badge {
                background: none !important;
                color: #000 !important;
                padding: 0;
                font-weight: bold;
                border-radius: 0;
                font-size: 9pt;
            }
            .task-type-badge {
                background: none !important;
                color: #000 !important;
                border: 1px solid #000;
                font-size: 9pt;
                padding: 2px 6px;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="navbar-spacer"></div>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($viewingTask): ?>
            <a href="manage_tasks.php" class="back-link no-print">
                <i class="fas fa-arrow-left"></i> Back to All Tasks
            </a>

            <?php
                $totalStudents = count($taskStudents);
                $completedCount = count(array_filter($taskStudents, fn($s) => $s['status'] === 'completed'));
                $pendingCount   = count(array_filter($taskStudents, fn($s) => $s['status'] === 'pending'));
                $missedCount    = count(array_filter($taskStudents, fn($s) => $s['status'] === 'missed'));
                $avgScore = $completedCount > 0
                    ? round(array_sum(array_filter(array_column($taskStudents, 'score'), fn($v) => $v !== null)) / $completedCount, 1)
                    : 0;
            ?>

            <!-- Print-only report header (hidden on screen) -->
            <div class="print-report-header" style="display:none;">
                <h1><?php echo htmlspecialchars($viewingTask['title']); ?></h1>
                <p>
                    Type: <strong><?php echo strtoupper($viewingTask['task_type']); ?></strong>
                    &nbsp;|&nbsp; Difficulty: <strong><?php echo htmlspecialchars($viewingTask['difficulty'] ?? 'Medium'); ?></strong>
                    <?php if (!empty($viewingTask['concept'])): ?>
                    &nbsp;|&nbsp; Concepts: <strong><?php echo htmlspecialchars($viewingTask['concept']); ?></strong>
                    <?php endif; ?>
                    <?php if ($viewingTask['company_name']): ?>
                    &nbsp;|&nbsp; Company: <strong><?php echo htmlspecialchars($viewingTask['company_name']); ?></strong>
                    <?php endif; ?>
                    &nbsp;|&nbsp; Deadline: <strong><?php echo date('d M Y, h:i A', strtotime($viewingTask['deadline'])); ?></strong>
                </p>
                <p>Generated on: <?php echo date('d M Y, h:i A'); ?> &nbsp;|&nbsp; Coordinator: <strong><?php echo htmlspecialchars($fullName); ?></strong></p>
                <div class="print-stats-row">
                    <span>Total Assigned: <strong><?php echo $totalStudents; ?></strong></span>
                    <span>Completed: <strong><?php echo $completedCount; ?></strong></span>
                    <span>Pending: <strong><?php echo $pendingCount; ?></strong></span>
                    <span>Missed: <strong><?php echo $missedCount; ?></strong></span>
                    <?php if ($completedCount > 0): ?>
                    <span>Avg Score: <strong><?php echo $avgScore; ?>%</strong></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="page-header">
                <div>
                    <h2><?php echo htmlspecialchars($viewingTask['title']); ?></h2>
                    
                    <div class="task-details-meta">
                        <span class="task-type-badge <?php echo $viewingTask['task_type']; ?>">
                            <i class="fas <?php 
                                echo $viewingTask['task_type'] === 'aptitude' ? 'fa-calculator' : 
                                    ($viewingTask['task_type'] === 'technical' ? 'fa-laptop-code' : 'fa-users'); 
                            ?>"></i>
                            <?php echo strtoupper($viewingTask['task_type']); ?>
                        </span>
                        
                        <span class="difficulty-badge <?php echo strtolower($viewingTask['difficulty'] ?? 'Medium'); ?>">
                            <i class="fas fa-circle" style="font-size: 6px;"></i>
                            Difficulty: <?php echo htmlspecialchars($viewingTask['difficulty'] ?? 'Medium'); ?>
                        </span>
                        
                        <?php if ($viewingTask['company_name']): ?>
                            <span class="meta-item">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($viewingTask['company_name']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <span class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            Deadline: <?php echo date('d M Y, h:i A', strtotime($viewingTask['deadline'])); ?>
                        </span>
                        
                        <button onclick="openEditDeadlineModal()" class="btn-edit-deadline no-print">
                            <i class="fas fa-edit"></i> Edit Deadline
                        </button>
                    </div>

                    <?php if (!empty($viewingTask['concept'])): ?>
                        <div class="task-details-concepts no-print">
                            <span class="concepts-label"><i class="fas fa-tags"></i> Target Concepts:</span>
                            <div class="concepts-tags-list">
                                <?php 
                                $concepts = array_map('trim', explode(',', $viewingTask['concept']));
                                foreach ($concepts as $c): 
                                    if (empty($c)) continue;
                                ?>
                                    <span class="concept-tag">#<?php echo htmlspecialchars($c); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button onclick="printTaskReport()" class="btn-print no-print">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>

            <!-- Stats Overview Cards Grid -->
            <div class="stats-grid no-print">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalStudents; ?></h3>
                        <p>Total Assigned</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $completedCount; ?></h3>
                        <p>Completed (<?php echo $totalStudents > 0 ? round(($completedCount/$totalStudents)*100) : 0; ?>%)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pendingCount; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon missed">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $missedCount; ?></h3>
                        <p>Missed</p>
                    </div>
                </div>
                <?php if ($completedCount > 0): ?>
                    <div class="stat-card">
                        <div class="stat-icon score">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $avgScore; ?>%</h3>
                            <p>Average Score</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Student Completion Tracking <span style="font-size: 14px; color: var(--text-muted); font-weight: 500;">(<?php echo $totalStudents; ?> Assigned)</span></div>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterTable('all', this)">All</button>
                        <button class="filter-tab" onclick="filterTable('completed', this)">Completed</button>
                        <button class="filter-tab" onclick="filterTable('pending', this)">Pending</button>
                        <button class="filter-tab" onclick="filterTable('missed', this)" style="color: #c62828;">Missed</button>
                    </div>
                </div>

                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Sl No</th>
                            <th>Student</th>
                            <th>Institution</th>
                            <th>Branch</th>
                            <th>Sem</th>
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
                                <td>
                                    <div class="student-profile">
                                        <div class="student-avatar"><?php echo strtoupper(substr($student['name'] ?? 'N', 0, 1)); ?></div>
                                        <div class="student-details-cell">
                                            <span class="student-name"><?php echo htmlspecialchars($student['name'] ?? 'N/A'); ?></span>
                                            <span class="student-usn"><?php echo htmlspecialchars($student['student_id']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-inst <?php echo strtolower($student['institution']); ?>">
                                        <?php echo htmlspecialchars($student['institution']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($student['branch'] ?? 'N/A'); ?></td>
                                <td>Sem <?php echo htmlspecialchars($student['current_sem'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $student['status']; ?>">
                                        <?php
                                        if ($student['status'] === 'completed')
                                            echo '<i class="fas fa-check-circle no-print"></i> Completed';
                                        elseif ($student['status'] === 'missed')
                                            echo '<i class="fas fa-times-circle no-print"></i> Missed';
                                        else
                                            echo '<i class="fas fa-hourglass-half no-print"></i> Pending';
                                        ?>
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
                <div>
                    <h2><i class="fas fa-tasks" style="color: var(--primary-maroon); margin-right: 8px;"></i> Manage Tasks</h2>
                    <p>Track academic tasks, student completion rates, and performance.</p>
                </div>
                <a href="assign_task.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Assign New Task
                </a>
            </div>

            <?php if (empty($tasks)): ?>
                <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;">
                    <i class="fas fa-clipboard-list" style="font-size: 64px; color: #cbd5e0; margin-bottom: 24px;"></i>
                    <h3 style="color: var(--text-main); font-weight: 700; margin-bottom: 12px; font-size: 20px;">No Tasks Assigned Yet</h3>
                    <p style="color: var(--text-muted); margin-bottom: 28px; max-width: 400px; margin-left: auto; margin-right: auto;">Get started by assigning your department students their first mock technical, hr, or aptitude test.</p>
                    <a href="assign_task.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Assign New Task
                    </a>
                </div>
            <?php else: ?>
                <div class="tasks-grid">
                    <?php foreach ($tasks as $task):
                        $assignedCount = count(json_decode($task['target_students'] ?? '[]', true) ?: []);
                        $completionPercent = $assignedCount > 0 ? round(($task['completed_count'] / $assignedCount) * 100) : 0;
                        ?>
                        <div class="task-card <?php echo $task['task_type']; ?>">
                            <div class="task-header">
                                <div class="task-badges-row">
                                    <span class="task-type-badge <?php echo $task['task_type']; ?>">
                                        <i class="fas <?php 
                                            echo $task['task_type'] === 'aptitude' ? 'fa-calculator' : 
                                                ($task['task_type'] === 'technical' ? 'fa-laptop-code' : 'fa-users'); 
                                        ?>"></i>
                                        <?php echo strtoupper($task['task_type']); ?>
                                    </span>
                                    
                                    <span class="difficulty-badge <?php echo strtolower($task['difficulty'] ?? 'Medium'); ?>">
                                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                                        <?php echo htmlspecialchars($task['difficulty'] ?? 'Medium'); ?>
                                    </span>
                                </div>
                                
                                <?php if ($task['question_source'] === 'manual'): ?>
                                    <span style="font-size: 12px; color: var(--primary-gold); font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-edit"></i> Manual
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>

                            <!-- Concepts inside cards -->
                            <?php if (!empty($task['concept'])): ?>
                                <div class="task-concepts">
                                    <?php 
                                    $concepts = array_map('trim', explode(',', $task['concept']));
                                    $countConcepts = 0;
                                    foreach ($concepts as $c): 
                                        if (empty($c)) continue;
                                        if ($countConcepts++ >= 3) {
                                            echo '<span class="concept-tag" style="background:#e2e8f0;">+ ' . (count($concepts) - 3) . ' more</span>';
                                            break;
                                        }
                                    ?>
                                        <span class="concept-tag">#<?php echo htmlspecialchars($c); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="task-concepts-empty">
                                    <i class="fas fa-info-circle"></i> General Assessment
                                </div>
                            <?php endif; ?>

                            <div class="task-meta">
                                <?php if ($task['company_name']): ?>
                                    <span><i class="fas fa-building"></i> <strong>Company:</strong> <?php echo htmlspecialchars($task['company_name']); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-calendar-alt"></i> <strong>Due:</strong> <?php echo date('d M Y, h:i A', strtotime($task['deadline'])); ?></span>
                                <span><i class="fas fa-users"></i> <strong>Assigned:</strong> <?php echo $assignedCount; ?> Students</span>
                            </div>

                            <div class="completion-container">
                                <div class="completion-bar">
                                    <div class="completion-fill" style="width: <?php echo $completionPercent; ?>%"></div>
                                </div>
                                <div class="completion-text">
                                    <span>Progress:</span>
                                    <span><strong><?php echo $task['completed_count']; ?></strong> / <?php echo $assignedCount; ?> completed (<?php echo $completionPercent; ?>%)</span>
                                </div>
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
        function filterTable(status, btn) {
            const rows = document.querySelectorAll('#studentsTable tbody tr');
            const tabs = document.querySelectorAll('.filter-tab');

            tabs.forEach(tab => tab.classList.remove('active'));
            if (btn) {
                btn.classList.add('active');
            } else if (window.event && window.event.target) {
                window.event.target.classList.add('active');
            }

            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function printTaskReport() {
            const activeTab = document.querySelector('.filter-tab.active');
            const activeFilter = activeTab ? activeTab.textContent.trim() : 'All';

            const rows = document.querySelectorAll('#studentsTable tbody tr');
            let visibleCount = 0;
            rows.forEach(row => { if (row.style.display !== 'none') visibleCount++; });

            const printHeader = document.querySelector('.print-report-header');
            if (printHeader) {
                let filterLine = printHeader.querySelector('.print-filter-line');
                if (!filterLine) {
                    filterLine = document.createElement('p');
                    filterLine.className = 'print-filter-line';
                    filterLine.style.fontWeight = 'bold';
                    filterLine.style.marginTop = '4px';
                    printHeader.appendChild(filterLine);
                }
                if (activeFilter === 'All') {
                    filterLine.textContent = 'Showing: All Students (' + visibleCount + ')';
                } else {
                    filterLine.textContent = 'Showing: ' + activeFilter + ' Students (' + visibleCount + ')';
                }
                printHeader.style.display = 'block';
            }

            window.print();

            if (printHeader) printHeader.style.display = 'none';
        }
    </script>

    <?php if ($viewingTask): ?>
        <div id="editDeadlineModal" class="modal">
            <div class="modal-content">
                <button type="button" class="close-modal" onclick="closeEditDeadlineModal()">&times;</button>
                <h2>Edit Task Deadline</h2>
                <form method="POST">
                    <input type="hidden" name="task_id" value="<?php echo $viewingTask['id']; ?>">
                    <input type="hidden" name="update_deadline" value="1">

                    <div class="form-group">
                        <label>New Deadline Date & Time</label>
                        <div class="deadline-inputs">
                            <?php
                            $dt = new DateTime($viewingTask['deadline']);
                            $curDate = $dt->format('Y-m-d');
                            $curHour = $dt->format('h');
                            $curMin = $dt->format('i');
                            $curAmPm = $dt->format('A');
                            ?>
                            <input type="date" name="deadline_date" value="<?php echo $curDate; ?>" required>
                            
                            <div class="time-selects">
                                <select name="deadline_hour" required>
                                    <?php for ($i = 1; $i <= 12; $i++):
                                        $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($curHour == $val) ? 'selected' : ''; ?>>
                                            <?php echo $val; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <span class="time-separator">:</span>
                                <select name="deadline_minute" required>
                                    <?php for ($i = 0; $i < 60; $i += 5):
                                        $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($curMin == $val) ? 'selected' : ''; ?>>
                                            <?php echo $val; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="deadline_ampm">
                                    <option value="AM" <?php echo ($curAmPm == 'AM') ? 'selected' : ''; ?>>AM</option>
                                    <option value="PM" <?php echo ($curAmPm == 'PM') ? 'selected' : ''; ?>>PM</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeEditDeadlineModal()">Cancel</button>
                        <button type="submit" class="btn-submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            function openEditDeadlineModal() { document.getElementById('editDeadlineModal').style.display = 'flex'; }
            function closeEditDeadlineModal() { document.getElementById('editDeadlineModal').style.display = 'none'; }
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('editDeadlineModal');
                if (event.target === modal) {
                    closeEditDeadlineModal();
                }
            }
        </script>
    <?php endif; ?>
</body>

</html>