<?php
/**
 * Assign Task Page - Coordinator Dashboard
 * Features unified student view (GMU/GMIT) with search, filtering, and pagination.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$coordinatorId = getUserId();
$db = getDB();
$remoteDB = getDB('gmu');
$localDB = getDB(); // For local SGPA

// Get coordinator's department and institution
$stmt = $db->prepare("SELECT department, institution FROM dept_coordinators WHERE id = ?");
$stmt->execute([$coordinatorId]);
$coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
$department = $coordinator['department'];

// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    $studentIdsRaw = $_POST['student_id'];
    $studentIds = array_filter(explode(',', $studentIdsRaw));
    $taskType = $_POST['task_type'];
    $companyName = $_POST['company_name'] ?? '';
    $concept = $_POST['concept'] ?? '';
    $difficulty = $_POST['difficulty'] ?? 'Medium';
    $questionSource = $_POST['question_source'] ?? 'ai';
    $deadlineDate = $_POST['deadline_date'] ?? '';
    $hour = (int)($_POST['deadline_hour'] ?? 0);
    $minute = (int)($_POST['deadline_minute'] ?? 0);
    $ampm = $_POST['deadline_ampm'] ?? 'AM';

    // Ensure the difficulty column exists
    try {
        $db->exec("ALTER TABLE coordinator_tasks ADD COLUMN IF NOT EXISTS difficulty VARCHAR(20) DEFAULT 'Medium'");
    } catch (Exception $e) {}

    if ($ampm === 'PM' && $hour < 12) $hour += 12;
    if ($ampm === 'AM' && $hour === 12) $hour = 0;
    $deadline = $deadlineDate . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minute, 2, '0', STR_PAD_LEFT) . ':00';
    
    $assignedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;

    // Hoist prepared statements outside the loop for performance
    $stmtBranch = $remoteDB->prepare("SELECT discipline FROM gmu.ad_student_approved WHERE usn = ? 
                                     UNION ALL 
                                     SELECT discipline FROM gmit_new.ad_student_details WHERE usn = ? OR student_id = ?");
    
    $compSearch = !empty($companyName) ? $companyName : null;
    $stmtCheck = $db->prepare("SELECT id FROM coordinator_tasks 
                               WHERE IS_ACTIVE = 1 
                               AND task_type = ? 
                               AND (company_name = ? OR (company_name IS NULL AND ? IS NULL))
                               AND JSON_CONTAINS(target_students, ?)
                               AND deadline > NOW()");

    $stmtInsert = $db->prepare("INSERT INTO coordinator_tasks 
                               (coordinator_id, task_type, title, company_name, concept, question_source, difficulty,
                                target_type, target_students, target_branches, deadline) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'individual', ?, ?, ?)");

    // Start transaction for atomicity and speed
    $db->beginTransaction();
    
    try {
        $validStudents = [];
        $branches = [];

        foreach ($studentIds as $studentId) {
            $studentId = trim($studentId);
            
            // Prevent duplicate active tasks
            $checkJson = '"' . $studentId . '"'; 
            $stmtCheck->execute([$taskType, $compSearch, $compSearch, $checkJson]);
            
            if ($stmtCheck->fetch()) {
                $skippedCount++;
                continue;
            }

            $validStudents[] = $studentId;
            
            // Get correct student branch
            $stmtBranch->execute([$studentId, $studentId, $studentId]);
            $resS = $stmtBranch->fetch(PDO::FETCH_ASSOC);
            $branches[] = $resS ? $resS['discipline'] : $department;
        }

        if (!empty($validStudents)) {
            // Create bulk task
            $title = ucfirst($taskType) . " Assessment" . ($companyName ? " - $companyName" : "");
            $stmtInsert->execute([
                $coordinatorId, $taskType, $title, $compSearch, $concept, $questionSource, $difficulty,
                json_encode(array_values(array_unique($validStudents))), json_encode(array_values(array_unique($branches))), $deadline
            ]);
            $assignedCount = count($validStudents);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("BULK ASSIGN FATAL ERROR: " . $e->getMessage());
        $_SESSION['error_message'] = "A critical error occurred. All assignments rolled back. Error: " . $e->getMessage();
        header('Location: assign_task.php?' . $_SERVER['QUERY_STRING']);
        exit;
    }

    if ($assignedCount > 0) {
        $_SESSION['success_message'] = "Successfully assigned tasks to $assignedCount students." . ($skippedCount > 0 ? " ($skippedCount skipped as they already have active tasks)" : "");
    } else if ($skippedCount > 0) {
        $_SESSION['error_message'] = "All selected students ($skippedCount) already have active tasks of this type.";
    }

    header('Location: assign_task.php?' . $_SERVER['QUERY_STRING']);
    exit;
}

// Handle Task History Fetch (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_task_history'])) {
    header('Content-Type: application/json');
    $usn = $_POST['usn'] ?? '';
    
    if (empty($usn)) {
        echo json_encode(['success' => false, 'message' => 'Student ID missing']);
        exit;
    }

    $aadhar = '';
    try {
        $gmuPrefix = DB_GMU_PREFIX;
        $gmitPrefix = DB_GMIT_PREFIX;
        $stmt = $remoteDB->prepare("SELECT aadhar FROM (
            SELECT usn, aadhar, usn as student_id_map FROM {$gmuPrefix}ad_student_approved
            UNION ALL
            SELECT IFNULL(NULLIF(usn, ''), student_id) as usn, aadhar, student_id as student_id_map FROM {$gmitPrefix}ad_student_details
        ) asa WHERE asa.usn = ? OR asa.student_id_map = ? LIMIT 1");
        $stmt->execute([$usn, $usn]);
        $aadhar = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {}

    $stmt = $localDB->prepare("SELECT ct.task_type, ct.company_name, ct.created_at as assigned_at, ct.deadline, 
                                     tc.score, tc.completed_at
                               FROM coordinator_tasks ct
                               LEFT JOIN task_completions tc ON ct.id = tc.task_id AND (tc.student_id = ? OR tc.student_id = ?)
                               WHERE ct.coordinator_id = ?
                                 AND JSON_CONTAINS(ct.target_students, ?)
                               ORDER BY ct.created_at DESC");
    $stmt->execute([$usn, $aadhar ?: $usn, $coordinatorId, "\"" . $usn . "\""]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

$pageId = 'coordinator_assign_task';

// Handle POST filters
if (isPost()) {
    if (isset($_POST['reset_filters'])) {
        SessionFilterHelper::clearFilters($pageId);
    } else {
        // MERGE into the session (do not replace) so partial posts keep the other
        // filters intact. Any filter change resets pagination back to page 1; only
        // a pure pagination post carries its own "page" value.
        $updates = $_POST;
        if (!isset($updates['page'])) {
            $updates['page'] = 1;
        }
        SessionFilterHelper::updateFilters($pageId, $updates);
    }
    header("Location: assign_task.php");
    exit;
}

// Handle GET tab switching (deprecated, but handled via Session fallback if needed)
if (isset($_GET['inst'])) {
    SessionFilterHelper::updateFilters($pageId, ['inst' => $_GET['inst'], 'page' => 1]);
    header("Location: assign_task.php");
    exit;
}

$filters = SessionFilterHelper::getFilters($pageId);

$limit = 100;
$page = isset($filters['page']) ? (int)$filters['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$instFilter = $filters['inst'] ?? 'all';
$search = clean($filters['search'] ?? '');
$min_sgpa = isset($filters['min_sgpa']) ? (float)$filters['min_sgpa'] : 0;
$branch_filter_val = clean($filters['branch'] ?? '');
$sem_filter_val = isset($filters['sem']) ? (int)$filters['sem'] : 0;

$available_branches = array_values(array_unique(getCoordinatorDisciplineFilters($department)));
$discipline_filter = (!empty($branch_filter_val) && in_array($branch_filter_val, $available_branches)) ? [$branch_filter_val] : $available_branches;

function buildInClauseCoord($column, $values, &$params) {
    if (empty($values)) return "";
    $values = array_filter($values, function($v) { return $v !== ''; });
    if (empty($values)) return "";
    $placeholders = [];
    foreach ($values as $val) {
        $placeholders[] = "?";
        $params[] = $val;
    }
    return "$column IN (" . implode(",", $placeholders) . ")";
}

$gmuPrefix = DB_GMU_PREFIX;
$gmitPrefix = DB_GMIT_PREFIX;

// Unified query to fetch students from both institutions
$combinedApproved = "
    (SELECT usn, name, aadhar, faculty, school, programme, course, discipline, year, sem, sgpa, registered, usn as student_id_map, '" . INSTITUTION_GMU . "' as institution FROM {$gmuPrefix}ad_student_approved
     UNION ALL
     SELECT IFNULL(NULLIF(usn, ''), student_id) as usn, name, aadhar, college as faculty, college as school, programme, course, discipline, 0 as year, 0 as sem, 0.0 as sgpa, 1 as registered, student_id as student_id_map, '" . INSTITUTION_GMIT . "' as institution FROM {$gmitPrefix}ad_student_details)
";

$where_clauses = ["1=1"]; // Removed asa.registered = 1 to show all eligible students
$params = [];

// Filter by Institution
if ($instFilter !== 'all') {
    $where_clauses[] = "asa.institution = ?";
    $params[] = ($instFilter === 'gmu') ? INSTITUTION_GMU : INSTITUTION_GMIT;
}

// Filter by Search
if ($search) {
    $where_clauses[] = "(asa.usn LIKE ? OR asa.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter by Discipline
if ($discipline_filter_sql = buildInClauseCoord("asa.discipline", $discipline_filter, $params)) {
    $where_clauses[] = $discipline_filter_sql;
}

// Semester Filtering (Dynamic based on Department) & GMIT Local Check
// "Semester = N" must mean the student's CURRENT semester is N, not "has ever had a
// record for semester N" — both institutions keep one row per semester of history,
// so matching any row would pull in senior students (a 7th-sem student has rows for
// sems 1..7 and would match a sem-5 filter). Current semester = MAX(sem/semester).
// Min SGPA is evaluated against that current-semester row too (the GMIT rows in the
// remote union carry a dummy sgpa of 0.0, so it can never be filtered in the main SQL).
$semester_filter = getCoordinatorSemesterFilters($department);
if ($sem_filter_val > 0 && in_array($sem_filter_val, $semester_filter)) {
    $semester_filter = [$sem_filter_val];
}
$sem_placeholders = implode(',', array_fill(0, count($semester_filter), '?'));

// GMU: match students whose HIGHEST sem row is in the filter (and passes Min SGPA).
$gmuSgpaSql = ($min_sgpa > 0) ? " AND cur_rows.sgpa >= ?" : "";
$gmuCurrentSql = "asa.usn IN (
    SELECT cur_rows.usn
    FROM {$gmuPrefix}ad_student_approved cur_rows
    JOIN (
        SELECT usn, MAX(sem) AS current_sem
        FROM {$gmuPrefix}ad_student_approved
        GROUP BY usn
    ) cur ON cur.usn = cur_rows.usn AND cur_rows.sem = cur.current_sem
    WHERE cur_rows.sem IN ($sem_placeholders)$gmuSgpaSql
)";
$gmuCurrentParams = $semester_filter;
if ($min_sgpa > 0) $gmuCurrentParams[] = $min_sgpa;

if ($instFilter === 'all' || $instFilter === 'gmit') {
    // GMIT: eligible ids from the local SGPA table — filter (and apply Min SGPA) on
    // each student's highest-semester row.
    $gmitSgpaSql = ($min_sgpa > 0) ? " AND s.sgpa >= ?" : "";
    $stmtLocal = $localDB->prepare("
        SELECT DISTINCT s.student_id
        FROM student_sem_sgpa s
        JOIN (
            SELECT student_id, MAX(semester) AS current_sem
            FROM student_sem_sgpa
            WHERE institution = ?
            GROUP BY student_id
        ) cur ON cur.student_id = s.student_id AND s.semester = cur.current_sem
        WHERE s.institution = ? AND s.semester IN ($sem_placeholders)$gmitSgpaSql
    ");
    $gmitLocalParams = array_merge([INSTITUTION_GMIT, INSTITUTION_GMIT], $semester_filter);
    if ($min_sgpa > 0) $gmitLocalParams[] = $min_sgpa;
    $stmtLocal->execute($gmitLocalParams);
    $gmitUsnsRaw = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);

    // Expand GMIT USNs/IDs
    $gmitUsns = $gmitUsnsRaw;
    if (!empty($gmitUsnsRaw)) {
        $db_gmit = getDB('gmit');
        if ($db_gmit) {
            $in_placeholders = implode(',', array_fill(0, count($gmitUsnsRaw), '?'));
            $stmtRef = $db_gmit->prepare("SELECT DISTINCT usn, student_id FROM ad_student_details WHERE student_id IN ($in_placeholders) OR usn IN ($in_placeholders) OR aadhar IN ($in_placeholders) OR aadhar_no IN ($in_placeholders)");
            $stmtRef->execute(array_merge($gmitUsnsRaw, $gmitUsnsRaw, $gmitUsnsRaw, $gmitUsnsRaw));
            $mapped = $stmtRef->fetchAll(PDO::FETCH_ASSOC);
            foreach ($mapped as $m) {
                if ($m['usn']) $gmitUsns[] = $m['usn'];
                if ($m['student_id']) $gmitUsns[] = $m['student_id'];
            }
            $gmitUsns = array_values(array_unique($gmitUsns));
        }
    }

    if ($instFilter === 'gmit') {
        // GMIT only (institution clause already applied above)
        if (!empty($gmitUsns)) {
            $placeholders = implode(',', array_fill(0, count($gmitUsns), '?'));
            $where_clauses[] = "asa.usn IN ($placeholders)";
            $params = array_merge($params, $gmitUsns);
        } else {
            $where_clauses[] = "1=0";
        }
    } elseif (!empty($gmitUsns)) {
        $placeholders = implode(',', array_fill(0, count($gmitUsns), '?'));
        // GMU current-sem check OR GMIT valid ID check
        $where_clauses[] = "((asa.institution = '" . INSTITUTION_GMU . "' AND $gmuCurrentSql) OR (asa.institution = '" . INSTITUTION_GMIT . "' AND asa.usn IN ($placeholders)))";
        $params = array_merge($params, $gmuCurrentParams, $gmitUsns);
    } else {
        // No GMIT students found with valid sem, show only GMU
        $where_clauses[] = "(asa.institution = '" . INSTITUTION_GMU . "' AND $gmuCurrentSql)";
        $params = array_merge($params, $gmuCurrentParams);
    }
} else {
    // Only GMU
    $where_clauses[] = $gmuCurrentSql;
    $params = array_merge($params, $gmuCurrentParams);
}

$where_sql = implode(" AND ", $where_clauses);

// Count Total
$count_query = "SELECT COUNT(DISTINCT asa.usn) FROM {$combinedApproved} asa WHERE $where_sql";
$stmt = $remoteDB->prepare($count_query);
$stmt->execute($params);
$total_records = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $limit));
// Clamp a stale page number (e.g. filters narrowed the result set) back into range
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch Data
$query = "SELECT asa.usn, MAX(asa.name) as name, MAX(asa.aadhar) as aadhar, MAX(asa.discipline) as discipline, MAX(asa.sem) as sem, MAX(asa.sgpa) as sgpa, MAX(asa.registered) as registered, asa.institution 
          FROM {$combinedApproved} asa 
          WHERE $where_sql 
          GROUP BY asa.usn, asa.institution
          ORDER BY name ASC 
          LIMIT $limit OFFSET $offset";
$stmt = $remoteDB->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch ALL matching USNs for bulk assignment across pages
$allQuery = "SELECT asa.usn FROM {$combinedApproved} asa WHERE $where_sql GROUP BY asa.usn";
$stmtAll = $remoteDB->prepare($allQuery);
$stmtAll->execute($params);
$allMatchedUsns = $stmtAll->fetchAll(PDO::FETCH_COLUMN);

// Enrich with Local Data (GMIT Sem/SGPA + Task Status)
foreach ($students as &$student) {
    // 1. GMIT Data Enrichment
    if ($student['institution'] === INSTITUTION_GMIT) {
        $enrichParams = [$student['usn']];
        $enrichSql = "SELECT sgpa, semester FROM student_sem_sgpa WHERE (student_id = ?";
        if (!empty($student['aadhar'])) {
            $enrichSql .= " OR student_id = ?";
            $enrichParams[] = $student['aadhar'];
        }
        $enrichSql .= ") AND institution = ? ORDER BY semester DESC LIMIT 1";
        $enrichParams[] = INSTITUTION_GMIT;

        $stmt = $localDB->prepare($enrichSql);
        $stmt->execute($enrichParams);
        $localData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($localData) {
            $student['sgpa'] = $localData['sgpa'];
            $student['sem'] = $localData['semester'];
        }
    }

    // 2. Latest Task & History Count
    $stmt = $localDB->prepare("SELECT COUNT(*) FROM coordinator_tasks ct 
                               WHERE ct.coordinator_id = ? AND JSON_CONTAINS(ct.target_students, ?)");
    $stmt->execute([$coordinatorId, "\"" . $student['usn'] . "\""]);
    $student['total_tasks'] = (int)$stmt->fetchColumn();

    $stmt = $localDB->prepare("SELECT ct.id, ct.task_type, ct.created_at, ct.company_name, ct.deadline,
                                     tc.score, tc.completed_at
                               FROM coordinator_tasks ct
                               LEFT JOIN task_completions tc ON ct.id = tc.task_id AND (tc.student_id = ? OR tc.student_id = ?)
                               WHERE ct.coordinator_id = ?
                                 AND JSON_CONTAINS(ct.target_students, ?)
                               ORDER BY ct.created_at DESC LIMIT 1");
    $stmt->execute([$student['usn'], !empty($student['aadhar']) ? $student['aadhar'] : $student['usn'], $coordinatorId, "\"" . $student['usn'] . "\""]);
    $student['latest_task'] = $stmt->fetch(PDO::FETCH_ASSOC);
}
unset($student);

function buildUrl($key, $val) {
    // This is now handled via JS and hidden form for Clean URLs
    return "javascript:updateFilter('$key', '$val')";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Tasks & Assessments - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-maroon-dark: #5c0000;
            --primary-maroon-light: #990000;
            --primary-gold: #D4AF37;
            --primary-gold-dark: #b89528;
            --primary-gold-light: #f4e8b8;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border-color: #e2e8f0;
            --border-hover: #cbd5e1;
            --gmu-color: #991b1b;
            --gmu-bg: #fef2f2;
            --gmu-border: #fecaca;
            --gmit-color: #1e40af;
            --gmit-bg: #eff6ff;
            --gmit-border: #bfdbfe;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.02);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 20px 30px -10px rgba(128, 0, 0, 0.15);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: #f1f5f9; 
            color: var(--text-main);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        

        .container { 
            width: 92%; 
            max-width: 1440px; 
            margin: 24px auto 80px auto; 
        }

        /* Hero Header Bar */
        .page-header-card {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-maroon-dark) 100%);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            color: white;
            box-shadow: var(--shadow-xl);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .page-header-card::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.25) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header-card::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: 30%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }
        .header-title-group {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 8px;
        }
        .header-icon-badge {
            width: 46px;
            height: 46px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary-gold);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .header-title-group h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .header-subtitle {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 400;
            max-width: 600px;
            line-height: 1.5;
        }
        .header-meta {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .dept-pill {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* KPI Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.25s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--border-hover);
        }
        .stat-info { display: flex; flex-direction: column; gap: 4px; }
        .stat-label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 26px; font-weight: 800; color: var(--text-main); line-height: 1.1; }
        .stat-sub { font-size: 11px; color: var(--text-light); font-weight: 500; }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .stat-icon.students { background: #eff6ff; color: #2563eb; }
        .stat-icon.selected { background: #fef3c7; color: #d97706; }
        .stat-icon.gmu { background: var(--gmu-bg); color: var(--gmu-color); }
        .stat-icon.gmit { background: var(--gmit-bg); color: var(--gmit-color); }

        /* Institution Segmented Controls & Actions Toolbar */
        .controls-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .segmented-tabs {
            display: inline-flex;
            background: #e2e8f0;
            padding: 4px;
            border-radius: 30px;
            gap: 4px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
        .tab-btn {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover:not(.active) {
            color: var(--primary-maroon);
            background: rgba(255, 255, 255, 0.5);
        }
        .tab-btn.active {
            background: var(--primary-maroon);
            color: white;
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.3);
        }
        .tab-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        .tab-btn:not(.active) .tab-count {
            background: #cbd5e1;
            color: var(--text-main);
        }

        /* Filter Section Card */
        .filter-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }
        @media (max-width: 1100px) {
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-actions-item { grid-column: 1 / -1; }
        }
        @media (max-width: 600px) {
            .filter-grid { grid-template-columns: 1fr; }
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .filter-label i { color: var(--primary-maroon); font-size: 12px; }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrapper i.input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-light);
            font-size: 14px;
            pointer-events: none;
        }
        .input-wrapper select + i.select-chevron {
            position: absolute;
            right: 14px;
            color: var(--text-light);
            font-size: 12px;
            pointer-events: none;
        }

        .form-input-custom, .form-select-custom {
            height: 44px;
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            background: #fff;
            transition: all 0.2s ease;
            appearance: none;
        }
        .form-select-custom {
            padding-right: 36px;
        }
        .form-input-custom:focus, .form-select-custom:focus {
            outline: none;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.08);
        }

        .btn-apply-filter {
            height: 44px;
            background: var(--primary-maroon);
            color: white;
            border: none;
            padding: 0 22px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(128, 0, 0, 0.2);
        }
        .btn-apply-filter:hover {
            background: var(--primary-maroon-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }

        .btn-reset-filter {
            height: 44px;
            background: #f1f5f9;
            color: var(--text-muted);
            border: 1.5px solid var(--border-color);
            padding: 0 16px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-reset-filter:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        /* Active Filter Chips Bar */
        .active-chips-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px dashed var(--border-color);
            flex-wrap: wrap;
        }
        .chips-label { font-size: 11px; font-weight: 700; color: var(--text-light); text-transform: uppercase; }
        .chip {
            background: #f1f5f9;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .chip i.clear-chip {
            color: var(--text-light);
            cursor: pointer;
            font-size: 11px;
            transition: color 0.15s;
        }
        .chip i.clear-chip:hover { color: #ef4444; }

        /* Data Table Wrapper */
        .table-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            text-align: left;
        }
        .modern-table th {
            background: #f8fafc;
            padding: 16px 18px;
            font-weight: 700;
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        .modern-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: var(--text-main);
        }
        .modern-table tbody tr {
            transition: background 0.15s ease;
        }
        .modern-table tbody tr:hover {
            background: #fffcf8;
        }
        .modern-table tbody tr.selected-row {
            background: #fefce8;
        }

        /* Checkbox customization */
        .custom-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-maroon);
            cursor: pointer;
            border-radius: 4px;
        }

        /* Student Info Cell */
        .student-flex {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #4a0000 100%);
            color: var(--primary-gold);
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        .student-details { display: flex; flex-direction: column; gap: 2px; }
        .student-name { font-weight: 700; color: var(--text-main); font-size: 14px; display: flex; align-items: center; gap: 6px; }
        .student-usn { font-size: 12px; color: var(--text-muted); font-weight: 500; font-family: monospace; letter-spacing: 0.3px; }

        /* Institution Badges */
        .inst-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .inst-badge.gmu { background: var(--gmu-bg); color: var(--gmu-color); border: 1px solid var(--gmu-border); }
        .inst-badge.gmit { background: var(--gmit-bg); color: var(--gmit-color); border: 1px solid var(--gmit-border); }

        /* Status & Task Pills */
        .task-chip {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .task-chip.aptitude { background: #e0f2fe; color: #0369a1; }
        .task-chip.technical { background: #fee2e2; color: #b91c1c; }
        .task-chip.hr { background: #dcfce7; color: #15803d; }

        .score-pill {
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 12px;
            display: inline-block;
        }
        .score-high { background: #dcfce7; color: #166534; }
        .score-medium { background: #fef3c7; color: #92400e; }
        .score-low { background: #fee2e2; color: #991b1b; }

        /* Action Buttons */
        .btn-assign-single {
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--primary-gold-dark) 100%);
            color: #1e293b;
            padding: 8px 16px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 700;
            font-size: 12px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 5px rgba(212, 175, 55, 0.3);
        }
        .btn-assign-single:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(212, 175, 55, 0.5);
        }

        .btn-history-icon {
            background: #f1f5f9;
            color: var(--primary-maroon);
            border: 1px solid var(--border-color);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-history-icon:hover {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
        }

        /* Empty State */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }
        .empty-icon {
            font-size: 48px;
            color: var(--text-light);
            margin-bottom: 16px;
        }
        .empty-title { font-size: 18px; font-weight: 700; color: var(--text-main); margin-bottom: 6px; }
        .empty-desc { font-size: 14px; color: var(--text-muted); max-width: 400px; margin: 0 auto 20px auto; }

        /* Pagination Controls */
        .pagination-container {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .pagination-info { font-size: 13px; font-weight: 600; color: var(--text-muted); }
        .pagination-pills { display: flex; gap: 6px; }
        .page-pill {
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .page-pill:hover:not(.active) { background: #e2e8f0; }
        .page-pill.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }

        /* Floating Bulk Action Dock */
        .bulk-dock {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(120px);
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(212, 175, 55, 0.3);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 0 20px rgba(212, 175, 55, 0.15);
            padding: 12px 28px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 24px;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            pointer-events: none;
        }
        .bulk-dock.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
            pointer-events: auto;
        }
        .bulk-dock-text {
            color: white;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bulk-count-badge {
            background: var(--primary-gold);
            color: #0f172a;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 13px;
        }
        .btn-bulk-submit {
            background: linear-gradient(135deg, var(--primary-gold) 0%, #b89528 100%);
            color: #0f172a;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 800;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }
        .btn-bulk-submit:hover {
            transform: scale(1.04);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.6);
        }

        /* Modern Modals */
        .modal-overlay { 
            display: none; 
            position: fixed; 
            z-index: 3000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(15, 23, 42, 0.65); 
            backdrop-filter: blur(8px); 
            -webkit-backdrop-filter: blur(8px);
            align-items: center; 
            justify-content: center; 
            padding: 20px;
        }
        .modal-box { 
            background: white; 
            padding: 32px; 
            border-radius: var(--radius-xl); 
            width: 95%; 
            max-width: 760px; 
            max-height: 90vh; 
            overflow-y: auto; 
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); 
            animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid var(--border-color);
        }
        @keyframes modalSlideUp { 
            from { transform: translateY(30px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }

        .modal-header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-maroon);
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        .close-btn {
            position: absolute;
            top: 24px;
            right: 24px;
            background: #f1f5f9;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .close-btn:hover { background: #fee2e2; color: #b91c1c; }

        .modal-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 640px) {
            .modal-grid-2 { grid-template-columns: 1fr; }
        }
        .span-2 { grid-column: 1 / -1; }

        .modal-actions-bar {
            display: flex;
            gap: 14px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }
        .btn-modal-cancel {
            flex: 1;
            background: #f1f5f9;
            color: var(--text-muted);
            padding: 12px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border-color);
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-modal-cancel:hover { background: #e2e8f0; color: var(--text-main); }
        .btn-modal-submit {
            flex: 2;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-maroon-dark) 100%);
            color: white;
            padding: 12px;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }
        .btn-modal-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(128, 0, 0, 0.4); }

        /* Notification Toast */
        .toast-msg {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: var(--shadow-sm);
        }
        .toast-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .toast-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container">

        <!-- Hero Header Card -->
        <div class="page-header-card">
            <div class="header-content">
                <div class="header-title-group">
                    <div class="header-icon-badge">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h1>Assign Student Assessments</h1>
                </div>
                <p class="header-subtitle">
                    Select students across GMU and GMIT institutions to dispatch targeted Aptitude, Technical, or HR mock assessments with automated AI grading.
                </p>
            </div>
            <div class="header-meta">
                <div class="dept-pill">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($department); ?> Department
                </div>
            </div>
        </div>

        <!-- System Toast Notifications -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="toast-msg toast-success">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="toast-msg toast-error">
                <i class="fas fa-exclamation-triangle" style="font-size: 18px;"></i>
                <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
            </div>
        <?php endif; ?>

        <!-- KPI Metrics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <span class="stat-label">Total Filtered</span>
                    <span class="stat-value"><?php echo number_format($total_records); ?></span>
                    <span class="stat-sub">Eligible students</span>
                </div>
                <div class="stat-icon students">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <span class="stat-label">Selection</span>
                    <span class="stat-value" id="kpiSelectedCount">0</span>
                    <span class="stat-sub">Students selected</span>
                </div>
                <div class="stat-icon selected">
                    <i class="fas fa-check-square"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <span class="stat-label">Current View</span>
                    <span class="stat-value" style="font-size: 20px; text-transform: uppercase;">
                        <?php echo htmlspecialchars($instFilter); ?>
                    </span>
                    <span class="stat-sub">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                </div>
                <div class="stat-icon gmu">
                    <i class="fas fa-university"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <span class="stat-label">Department</span>
                    <span class="stat-value" style="font-size: 18px; line-height: 1.3;">
                        <?php echo htmlspecialchars($department); ?>
                    </span>
                    <span class="stat-sub"><?php echo count($available_branches); ?> specialization(s)</span>
                </div>
                <div class="stat-icon gmit">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
        </div>

        <!-- Controls Toolbar: Segmented Controls -->
        <div class="controls-toolbar">
            <div class="segmented-tabs">
                <a href="<?php echo buildUrl('inst', 'all'); ?>" class="tab-btn <?php echo $instFilter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-globe"></i> All Students
                </a>
                <a href="<?php echo buildUrl('inst', 'gmu'); ?>" class="tab-btn <?php echo $instFilter === 'gmu' ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap"></i> GMU
                </a>
                <a href="<?php echo buildUrl('inst', 'gmit'); ?>" class="tab-btn <?php echo $instFilter === 'gmit' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> GMIT
                </a>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <form method="POST" class="filter-grid" id="mainFilterForm">
                <input type="hidden" name="inst" value="<?php echo htmlspecialchars($instFilter); ?>">
                
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-search"></i> Student Search</label>
                    <div class="input-wrapper">
                        <i class="fas fa-search input-icon"></i>
                        <input type="text" name="search" class="form-input-custom" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by USN or Name...">
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-award"></i> Min SGPA</label>
                    <div class="input-wrapper">
                        <i class="fas fa-star input-icon"></i>
                        <input type="number" name="min_sgpa" class="form-input-custom" value="<?php echo $min_sgpa > 0 ? $min_sgpa : ''; ?>" step="0.01" min="0" max="10" placeholder="e.g. 7.5">
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-calendar-alt"></i> Semester</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar-week input-icon"></i>
                        <select name="sem" class="form-select-custom">
                            <option value="">All Semesters</option>
                            <?php foreach (getCoordinatorSemesterFilters($department) as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $sem_filter_val === (int)$s ? 'selected' : ''; ?>>
                                    Semester <?php echo $s; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-chevron"></i>
                    </div>
                </div>

                <?php if (count($available_branches) > 1): ?>
                <div class="filter-group">
                    <label class="filter-label"><i class="fas fa-code-branch"></i> Specialization</label>
                    <div class="input-wrapper">
                        <i class="fas fa-laptop-code input-icon"></i>
                        <select name="branch" class="form-select-custom">
                            <option value="">All Specializations</option>
                            <?php foreach ($available_branches as $ab): ?>
                                <option value="<?php echo htmlspecialchars($ab); ?>" <?php echo $branch_filter_val === $ab ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ab); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-chevron"></i>
                    </div>
                </div>
                <?php endif; ?>

                <div class="filter-group filter-actions-item" style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-apply-filter">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <button type="button" class="btn-reset-filter" onclick="resetFilters()" title="Reset All Filters">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </form>

            <!-- Active Chips Bar -->
            <?php if ($search || $min_sgpa > 0 || $sem_filter_val > 0 || !empty($branch_filter_val)): ?>
                <div class="active-chips-bar">
                    <span class="chips-label"><i class="fas fa-sliders-h"></i> Active Filters:</span>
                    <?php if ($search): ?>
                        <span class="chip">Search: "<?php echo htmlspecialchars($search); ?>" <i class="fas fa-times clear-chip" onclick="updateFilter('search', '')"></i></span>
                    <?php endif; ?>
                    <?php if ($min_sgpa > 0): ?>
                        <span class="chip">SGPA &ge; <?php echo $min_sgpa; ?> <i class="fas fa-times clear-chip" onclick="updateFilter('min_sgpa', '')"></i></span>
                    <?php endif; ?>
                    <?php if ($sem_filter_val > 0): ?>
                        <span class="chip">Semester <?php echo $sem_filter_val; ?> <i class="fas fa-times clear-chip" onclick="updateFilter('sem', '')"></i></span>
                    <?php endif; ?>
                    <?php if (!empty($branch_filter_val)): ?>
                        <span class="chip">Branch: <?php echo htmlspecialchars($branch_filter_val); ?> <i class="fas fa-times clear-chip" onclick="updateFilter('branch', '')"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Table Card Container -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="width: 44px; text-align: center;">
                                <input type="checkbox" id="selectAll" class="custom-checkbox" onclick="toggleSelectAll(this)">
                            </th>
                            <th style="width: 50px;">#</th>
                            <th>Student & USN</th>
                            <th>Institution</th>
                            <th>Branch</th>
                            <th>Sem</th>
                            <th>Latest Task</th>
                            <th>Score</th>
                            <th>Completed</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                                        <h3 class="empty-title">No Students Found</h3>
                                        <p class="empty-desc">No eligible students match your filter parameters. Try clearing your search or adjusting SGPA/Semester filters.</p>
                                        <button type="button" class="btn-reset-filter" onclick="resetFilters()">
                                            <i class="fas fa-sync-alt"></i> Reset All Filters
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $slNo = $offset + 1;
                            foreach ($students as $student): 
                                $usn = htmlspecialchars($student['usn']);
                                $name = htmlspecialchars($student['name'] ?? 'N/A');
                                $initial = strtoupper(substr($name, 0, 1));
                                $inst = strtolower($student['institution']);
                            ?>
                            <tr id="row_<?php echo $usn; ?>">
                                <td style="text-align: center;">
                                    <input type="checkbox" class="student-checkbox custom-checkbox" value="<?php echo $usn; ?>">
                                </td>
                                <td style="color: var(--text-light); font-weight: 600; font-size: 13px;"><?php echo $slNo++; ?></td>
                                <td>
                                    <div class="student-flex">
                                        <div class="avatar-circle"><?php echo $initial; ?></div>
                                        <div class="student-details">
                                            <div class="student-name">
                                                <span><?php echo $name; ?></span>
                                                <?php if ($student['total_tasks'] > 0): ?>
                                                    <button type="button" class="btn-history-icon" 
                                                            onclick="viewHistory('<?php echo $usn; ?>', '<?php echo addslashes($name); ?>')"
                                                            title="View Task History (<?php echo $student['total_tasks']; ?> tasks)">
                                                        <i class="fas fa-history"></i> <?php echo $student['total_tasks']; ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <span class="student-usn"><?php echo $usn; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="inst-badge <?php echo $inst; ?>">
                                        <i class="fas <?php echo $inst === 'gmu' ? 'fa-university' : 'fa-building'; ?>"></i>
                                        <?php echo strtoupper($inst); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: #334155; font-size: 13px;"><?php echo htmlspecialchars($student['discipline'] ?? 'N/A'); ?></td>
                                <td style="font-weight: 700; color: var(--primary-maroon); font-size: 13px;">Sem <?php echo htmlspecialchars($student['sem'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($student['latest_task']): $task = $student['latest_task']; ?>
                                        <div style="display: flex; flex-direction: column; gap: 3px;">
                                            <span class="task-chip <?php echo htmlspecialchars($task['task_type']); ?>">
                                                <i class="fas fa-check-circle"></i> <?php echo strtoupper($task['task_type']); ?>
                                            </span>
                                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                                                <?php echo $task['company_name'] ? htmlspecialchars($task['company_name']) : 'General Assessment'; ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 12px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($student['latest_task'] && isset($student['latest_task']['score'])): 
                                        $task = $student['latest_task'];
                                        $sClass = ($task['score'] >= 75) ? 'score-high' : (($task['score'] >= 50) ? 'score-medium' : 'score-low');
                                    ?>
                                        <span class="score-pill <?php echo $sClass; ?>">
                                            <?php echo number_format($task['score'], 0); ?>%
                                        </span>
                                    <?php elseif ($student['latest_task'] && empty($student['latest_task']['completed_at']) && !empty($student['latest_task']['deadline']) && strtotime($student['latest_task']['deadline']) < time()): ?>
                                        <span style="color:#b91c1c; font-size:11px; font-weight:700; background:#fee2e2; padding:3px 8px; border-radius:10px;">
                                            <i class="fas fa-times-circle"></i> Missed
                                        </span>
                                    <?php elseif ($student['latest_task'] && empty($student['latest_task']['completed_at'])): ?>
                                        <span style="color:#b45309; font-size:11px; font-weight:700; background:#fef3c7; padding:3px 8px; border-radius:10px;">
                                            <i class="fas fa-clock"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($student['latest_task'] && isset($student['latest_task']['completed_at'])): ?>
                                        <span style="font-size: 12px; font-weight: 600; color: #166534;">
                                            <i class="fas fa-check"></i> <?php echo date('d M, Y', strtotime($student['latest_task']['completed_at'])); ?>
                                        </span>
                                    <?php elseif ($student['latest_task'] && !empty($student['latest_task']['deadline']) && strtotime($student['latest_task']['deadline']) < time()): ?>
                                        <span style="font-size: 11px; font-weight: 600; color: #b91c1c;" title="Deadline: <?php echo date('d M Y, h:i A', strtotime($student['latest_task']['deadline'])); ?>">
                                            <i class="fas fa-exclamation-circle"></i> Expired (<?php echo date('d M', strtotime($student['latest_task']['deadline'])); ?>)
                                        </span>
                                    <?php elseif ($student['latest_task'] && !empty($student['latest_task']['deadline'])): ?>
                                        <span style="font-size: 11px; font-weight: 600; color: #b45309;">
                                            <i class="fas fa-hourglass-half"></i> Due <?php echo date('d M', strtotime($student['latest_task']['deadline'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn-assign-single" onclick="openAssignModal('<?php echo $usn; ?>', '<?php echo addslashes($name); ?>')">
                                        <i class="fas fa-paper-plane"></i> Assign
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination Bar -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo min($total_records, $offset + 1); ?> - <?php echo min($total_records, $offset + count($students)); ?> of <?php echo number_format($total_records); ?> students
                    </div>
                    <div class="pagination-pills">
                        <?php if ($page > 1): ?>
                            <a href="javascript:void(0)" onclick="updateFilter('page', <?php echo $page - 1; ?>)" class="page-pill">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<a href="javascript:void(0)" onclick="updateFilter(\'page\', 1)" class="page-pill ' . (1 === $page ? 'active' : '') . '">1</a>';
                            if ($start > 2) echo '<span class="page-pill" style="border:none; background:transparent;">...</span>';
                        }
                        for ($i = $start; $i <= $end; $i++): ?>
                            <a href="javascript:void(0)" onclick="updateFilter('page', <?php echo $i; ?>)" class="page-pill <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; 
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) echo '<span class="page-pill" style="border:none; background:transparent;">...</span>';
                            echo '<a href="javascript:void(0)" onclick="updateFilter(\'page\', ' . $total_pages . ')" class="page-pill ' . ($total_pages === $page ? 'active' : '') . '">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="javascript:void(0)" onclick="updateFilter('page', <?php echo $page + 1; ?>)" class="page-pill">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Global Hidden Form for Page/Filter Updates -->
        <form id="filterForm" method="POST" style="display:none;">
            <input type="hidden" name="inst" id="filterInst" value="<?php echo htmlspecialchars($instFilter); ?>">
            <input type="hidden" name="search" id="filterSearch" value="<?php echo htmlspecialchars($search); ?>">
            <input type="hidden" name="min_sgpa" id="filterSgpa" value="<?php echo htmlspecialchars($min_sgpa); ?>">
            <input type="hidden" name="branch" id="filterBranch" value="<?php echo htmlspecialchars($branch_filter_val); ?>">
            <input type="hidden" name="sem" id="filterSem" value="<?php echo htmlspecialchars($sem_filter_val); ?>">
            <input type="hidden" name="page" id="filterPage" value="<?php echo htmlspecialchars($page); ?>">
        </form>

    </div><!-- end .container -->

    <!-- Floating Glassmorphism Bulk Actions Dock -->
    <div id="bulkActionsBar" class="bulk-dock">
        <div class="bulk-dock-text">
            <i class="fas fa-user-check" style="color: var(--primary-gold); font-size: 16px;"></i>
            <span class="bulk-count-badge" id="selectedCountText">0</span> Students Selected
        </div>
        <button type="button" class="btn-bulk-submit" onclick="openBulkAssignModal()">
            <i class="fas fa-layer-group"></i> Bulk Assign Task
        </button>
    </div>

    <!-- Assignment Modal -->
    <div id="assignModal" class="modal-overlay">
        <div class="modal-box">
            <button type="button" class="close-btn" onclick="closeAssignModal()">&times;</button>
            <div class="modal-header-title">
                <i class="fas fa-clipboard-check"></i> Configure & Assign Task
            </div>
            
            <form method="POST">
                <input type="hidden" name="student_id" id="modal_student_id">

                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 6px; font-weight: 700; font-size: 13px; color: var(--text-main);">Target Student(s)</label>
                    <input type="text" id="modal_student_name" class="form-input-custom" style="padding-left: 16px; background: #f8fafc; font-weight: 700; color: var(--primary-maroon);" readonly>
                </div>

                <div id="bulkAssignAllContainer" style="display: none; margin-bottom: 20px; padding: 14px; background: #fefce8; border-radius: var(--radius-md); border: 1px solid #fef08a;">
                    <label style="display:flex; align-items:center; gap:10px; font-size:13px; font-weight:700; cursor:pointer; color: #854d0e; margin: 0;">
                        <input type="checkbox" id="assign_all_matching" onchange="toggleAssignAllMatching()" class="custom-checkbox">
                        Select all <?php echo count($allMatchedUsns); ?> students matching current filters (across all pages)
                    </label>
                </div>

                <!-- 2-column grid for task fields -->
                <div class="modal-grid-2">

                    <!-- Task Type -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-list-check"></i> Task Type *</label>
                        <div class="input-wrapper">
                            <i class="fas fa-tasks input-icon"></i>
                            <select name="task_type" class="form-select-custom" required>
                                <option value="">Select Task Category</option>
                                <option value="aptitude">Aptitude Round</option>
                                <option value="technical">Technical Round</option>
                                <option value="hr">HR Round</option>
                            </select>
                            <i class="fas fa-chevron-down select-chevron"></i>
                        </div>
                    </div>

                    <!-- Company Name -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-building"></i> Company Tag (Optional)</label>
                        <div class="input-wrapper">
                            <i class="fas fa-briefcase input-icon"></i>
                            <input type="text" name="company_name" class="form-input-custom" placeholder="e.g., TCS, Infosys, Accenture">
                        </div>
                    </div>

                    <!-- Concepts / Topics -->
                    <div class="filter-group span-2">
                        <label class="filter-label"><i class="fas fa-lightbulb"></i> Concepts / Topics Focus</label>
                        <div class="input-wrapper">
                            <i class="fas fa-code input-icon"></i>
                            <input type="text" name="concept" class="form-input-custom" placeholder="e.g., Data Structures, SQL Joins, React Hooks, Quantitative Aptitude">
                        </div>
                        <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px;">AI question generator will strictly focus evaluation on these topic keywords.</small>
                    </div>

                    <!-- Difficulty Level -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-gauge-high"></i> Difficulty Level *</label>
                        <div class="input-wrapper">
                            <i class="fas fa-signal input-icon"></i>
                            <select name="difficulty" class="form-select-custom" required>
                                <option value="Low">🟢 Low — Foundational / Beginner</option>
                                <option value="Medium" selected>🟡 Medium — Standard / Intermediate</option>
                                <option value="High">🔴 High — Advanced / System Design</option>
                            </select>
                            <i class="fas fa-chevron-down select-chevron"></i>
                        </div>
                    </div>

                    <!-- Question Source -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-robot"></i> Question Engine *</label>
                        <div class="input-wrapper">
                            <i class="fas fa-microchip input-icon"></i>
                            <select name="question_source" class="form-select-custom" required>
                                <option value="ai">Dynamic AI Generation</option>
                            </select>
                            <i class="fas fa-chevron-down select-chevron"></i>
                        </div>
                    </div>

                    <!-- Deadline Date & Time -->
                    <div class="filter-group span-2">
                        <label class="filter-label"><i class="fas fa-clock"></i> Task Deadline *</label>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                            <input type="date" name="deadline_date" class="form-input-custom" style="flex: 2; min-width: 140px; padding-left: 14px;" required>
                            <select name="deadline_hour" class="form-select-custom" style="width: 70px; text-align: center; padding-left: 10px; padding-right: 10px;" required>
                                <?php for($i=1; $i<=12; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                <?php endfor; ?>
                            </select>
                            <span style="font-weight: 800;">:</span>
                            <select name="deadline_minute" class="form-select-custom" style="width: 70px; text-align: center; padding-left: 10px; padding-right: 10px;" required>
                                <?php for($i=0; $i<60; $i+=5): ?>
                                    <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="deadline_ampm" class="form-select-custom" style="width: 75px; text-align: center; padding-left: 10px; padding-right: 10px;">
                                <option value="AM">AM</option>
                                <option value="PM" selected>PM</option>
                            </select>
                        </div>
                    </div>

                </div><!-- end .modal-grid-2 -->

                <div class="modal-actions-bar">
                    <button type="button" class="btn-modal-cancel" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" name="assign_task" class="btn-modal-submit">
                        <i class="fas fa-paper-plane"></i> Confirm & Assign Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Task History Modal -->
    <div id="historyModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 550px;">
            <button type="button" class="close-btn" onclick="closeHistoryModal()">&times;</button>
            <div class="modal-header-title">
                <i class="fas fa-history"></i> Assessment History
            </div>
            <p id="historyStudentName" style="font-size: 13px; color: var(--text-muted); margin-top: -12px; margin-bottom: 20px; font-weight: 600;"></p>
            
            <div id="historyList" style="display: flex; flex-direction: column; gap: 12px; max-height: 400px; overflow-y: auto;">
                <!-- History items injected via JS -->
            </div>
            <div id="historyEmpty" style="display: none; text-align: center; padding: 40px; color: var(--text-light);">
                <i class="fas fa-folder-open" style="font-size: 36px; margin-bottom: 10px; display: block;"></i>
                No previous tasks recorded for this student.
            </div>
        </div>
    </div>

    <script>
        const allMatchedUsns = <?php echo json_encode($allMatchedUsns); ?>;
        let isAllPagesSelected = false;
        
        function updateFilter(key, val) {
            if (key === 'inst') document.getElementById('filterInst').value = val;
            if (key === 'search') document.getElementById('filterSearch').value = val;
            if (key === 'min_sgpa') document.getElementById('filterSgpa').value = val;
            if (key === 'branch') document.getElementById('filterBranch').value = val;
            if (key === 'sem') document.getElementById('filterSem').value = val;
            if (key === 'page') document.getElementById('filterPage').value = val;
            
            if (key !== 'page') document.getElementById('filterPage').value = 1;
            document.getElementById('filterForm').submit();
        }

        function openAssignModal(studentId, studentName) {
            const bulkContainer = document.getElementById('bulkAssignAllContainer');
            if (bulkContainer) bulkContainer.style.display = 'none';
            document.getElementById('modal_student_id').value = studentId;
            document.getElementById('modal_student_name').value = studentName;
            
            // Set default date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dateStr = tomorrow.toISOString().split('T')[0];
            const dateInput = document.querySelector('input[name="deadline_date"]');
            if (dateInput && !dateInput.value) dateInput.value = dateStr;

            document.getElementById('assignModal').style.display = 'flex';
        }

        function openBulkAssignModal() {
            if (isAllPagesSelected) {
                document.getElementById('modal_student_id').value = allMatchedUsns.join(',');
                document.getElementById('modal_student_name').value = `${allMatchedUsns.length} Students Selected (All Pages)`;
            } else {
                const selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
                if (selected.length === 0) {
                    alert('Please select at least one student.');
                    return;
                }
                document.getElementById('modal_student_id').value = selected.join(',');
                document.getElementById('modal_student_name').value = `${selected.length} Students Selected (Current Page)`;
            }
            
            const bulkContainer = document.getElementById('bulkAssignAllContainer');
            if (bulkContainer) bulkContainer.style.display = 'none';
            
            // Set default date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dateStr = tomorrow.toISOString().split('T')[0];
            const dateInput = document.querySelector('input[name="deadline_date"]');
            if (dateInput && !dateInput.value) dateInput.value = dateStr;

            document.getElementById('assignModal').style.display = 'flex';
        }

        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }

        function viewHistory(usn, name) {
            document.getElementById('historyStudentName').innerText = name + ' (' + usn + ')';
            const list = document.getElementById('historyList');
            const empty = document.getElementById('historyEmpty');
            list.innerHTML = '<div style="text-align:center; padding:30px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Fetching assessment history...</div>';
            empty.style.display = 'none';
            document.getElementById('historyModal').style.display = 'flex';

            const formData = new FormData();
            formData.append('fetch_task_history', '1');
            formData.append('usn', usn);

            fetch('assign_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.history.length > 0) {
                        list.innerHTML = data.history.map(task => {
                            const dateStr = new Date(task.assigned_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                            const isCompleted = task.completed_at !== null && task.completed_at !== undefined;
                            const deadlinePassed = !isCompleted && task.deadline && (new Date(task.deadline).getTime() < Date.now());
                            const compDate = isCompleted ? new Date(task.completed_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : null;
                            const deadlineDate = task.deadline ? new Date(task.deadline).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '';
                            
                            let scoreHtml = '';
                            let statusSubText = '';

                            if (isCompleted) {
                                const sClass = (task.score >= 75) ? 'score-high' : ((task.score >= 50) ? 'score-medium' : 'score-low');
                                scoreHtml = `<span class="score-pill ${sClass}">${Math.round(task.score)}%</span>`;
                                statusSubText = `<span style="font-size:11px; color:#166534; font-weight:600;"><i class="fas fa-check"></i> Completed on ${compDate}</span>`;
                            } else if (deadlinePassed) {
                                scoreHtml = `<span style="color:#b91c1c; background:#fee2e2; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-times-circle"></i> Missed</span>`;
                                statusSubText = `<span style="font-size:11px; color:#b91c1c; font-weight:600;"><i class="fas fa-exclamation-circle"></i> Deadline Expired (${deadlineDate})</span>`;
                            } else {
                                scoreHtml = `<span style="color:#b45309; background:#fef3c7; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-hourglass-half"></i> Active</span>`;
                                statusSubText = `<span style="font-size:11px; color:#b45309; font-weight:600;"><i class="fas fa-clock"></i> Due by ${deadlineDate}</span>`;
                            }

                            return `
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <span class="task-chip ${task.task_type}" style="width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px;">
                                            ${task.task_type.substring(0,1).toUpperCase()}
                                        </span>
                                        <div>
                                            <div style="font-size:13px; font-weight:700; color:#0f172a;">${task.company_name || 'General Assessment'}</div>
                                            <div style="font-size:11px; color:#64748b;">Assigned: ${dateStr}</div>
                                        </div>
                                    </div>
                                    <div style="text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                                        ${scoreHtml}
                                        ${statusSubText}
                                    </div>
                                </div>
                            `;
                        }).join('');
                    } else {
                        list.innerHTML = '';
                        empty.style.display = 'block';
                    }
                } else {
                    list.innerHTML = '<div style="color:#ef4444; text-align:center; padding:20px;">Error loading history.</div>';
                }
            })
            .catch(err => {
                list.innerHTML = '<div style="color:#ef4444; text-align:center; padding:20px;">Connection failed.</div>';
            });
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        function toggleSelectAll(masterCb) {
            isAllPagesSelected = masterCb.checked;
            document.querySelectorAll('.student-checkbox').forEach(cb => {
                cb.checked = masterCb.checked;
                const row = document.getElementById('row_' + cb.value);
                if (row) {
                    if (cb.checked) row.classList.add('selected-row');
                    else row.classList.remove('selected-row');
                }
            });
            updateBulkActionBarStatus();
        }

        function resetFilters() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'assign_task.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reset_filters';
            input.value = '1';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        function updateBulkActionBarStatus() {
            const bar = document.getElementById('bulkActionsBar');
            const countText = document.getElementById('selectedCountText');
            const kpiCount = document.getElementById('kpiSelectedCount');
            
            if (isAllPagesSelected) {
                const total = allMatchedUsns.length;
                if (countText) countText.innerText = total;
                if (kpiCount) kpiCount.innerText = total;
                bar.classList.add('show');
            } else {
                const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
                if (countText) countText.innerText = selectedCount;
                if (kpiCount) kpiCount.innerText = selectedCount;

                if (selectedCount > 0) {
                    bar.classList.add('show');
                } else {
                    bar.classList.remove('show');
                }
            }
        }

        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('student-checkbox') && e.target.id !== 'selectAll') {
                const row = document.getElementById('row_' + e.target.value);
                if (row) {
                    if (e.target.checked) row.classList.add('selected-row');
                    else row.classList.remove('selected-row');
                }

                if (!e.target.checked && isAllPagesSelected) {
                    isAllPagesSelected = false;
                    const masterCb = document.getElementById('selectAll');
                    if (masterCb) masterCb.checked = false;
                }
                updateBulkActionBarStatus();
            }
        });

        window.onclick = function(event) {
            const assignModal = document.getElementById('assignModal');
            const historyModal = document.getElementById('historyModal');
            if (event.target === assignModal) closeAssignModal();
            if (event.target === historyModal) closeHistoryModal();
        }
    </script>
</body>
</html>


