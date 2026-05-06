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
    $questionSource = $_POST['question_source'] ?? 'ai';
    $deadlineDate = $_POST['deadline_date'] ?? '';
    $hour = (int)($_POST['deadline_hour'] ?? 0);
    $minute = (int)($_POST['deadline_minute'] ?? 0);
    $ampm = $_POST['deadline_ampm'] ?? 'AM';

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
                               (coordinator_id, task_type, title, company_name, concept, question_source, 
                                target_type, target_students, target_branches, deadline) 
                               VALUES (?, ?, ?, ?, ?, ?, 'individual', ?, ?, ?)");

    // Start transaction for atomicity and speed
    $db->beginTransaction();
    
    try {
        foreach ($studentIds as $studentId) {
            $studentId = trim($studentId);
            
            // Get correct student branch
            $stmtBranch->execute([$studentId, $studentId, $studentId]);
            $resS = $stmtBranch->fetch(PDO::FETCH_ASSOC);
            $targetBranch = $resS ? $resS['discipline'] : $department;
            
            // Prevent duplicate active tasks
            $checkJson = json_encode($studentId); 
            $stmtCheck->execute([$taskType, $compSearch, $compSearch, $checkJson]);
            
            if ($stmtCheck->fetch()) {
                $skippedCount++;
                continue;
            }

            // Create task
            $title = ucfirst($taskType) . " Assessment" . ($companyName ? " - $companyName" : "");
            $stmtInsert->execute([
                $coordinatorId, $taskType, $title, $compSearch, $concept, $questionSource, 
                json_encode([$studentId]), json_encode([$targetBranch]), $deadline
            ]);
            $assignedCount++;
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

    $stmt = $localDB->prepare("SELECT ct.task_type, ct.company_name, ct.created_at as assigned_at, 
                                     tc.score, tc.completed_at
                               FROM coordinator_tasks ct
                               LEFT JOIN task_completions tc ON ct.id = tc.task_id AND tc.student_id = ?
                               WHERE ct.coordinator_id = ?
                                 AND JSON_CONTAINS(ct.target_students, ?)
                               ORDER BY ct.created_at DESC");
    $stmt->execute([$usn, $coordinatorId, "\"" . $usn . "\""]);
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
        SessionFilterHelper::handlePostToSession($pageId, $_POST);
    }
    header("Location: assign_task.php");
    exit;
}

// Handle GET tab switching (deprecated, but handled via Session fallback if needed)
if (isset($_GET['inst'])) {
    SessionFilterHelper::updateFilters($pageId, ['inst' => $_GET['inst']]);
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
     SELECT student_id as usn, name, aadhar, college as faculty, college as school, programme, course, discipline, 0 as year, 0 as sem, 0.0 as sgpa, 1 as registered, student_id as student_id_map, '" . INSTITUTION_GMIT . "' as institution FROM {$gmitPrefix}ad_student_details)
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

// Filter by Min SGPA (Needs careful handling for GMIT as SGPA is 0 in remote)
if ($min_sgpa > 0) {
    // For GMU, check sgpa column directly. For GMIT, we rely on the local fetch below.
    // However, to do this efficiently in SQL for pagination, we join with local table.
    // Since complex join across servers is tricky, we filter post-fetch or use simple logic.
    // For now, to match report logic exactly, apply only to GMU or local GMIT IDs.
    // Report logic filters GMU by SGPA directly in WHERE. For GMIT it relies on local IDs.
    
    // We'll mimic report: if searching for high SGPA, we must know which GMIT students possess it.
    // But since report handles it via ID filtering, let's skip complex SGPA filtering in main SQL for GMIT
    // and rely on the fact that most filtering is by semester.
    
    // Actually, report adds: asa.sgpa >= ? which affects GMU. GMIT requires local ID filter for this.
    // Simplified for now: Apply only to GMU in main query if needed, or skip SQL filter for mixed.
    // Let's stick to report logic:
    if ($instFilter === 'gmu' || $instFilter === 'all') {
         // This might filter out all GMIT if their dummy SGPA is 0.0 < min_sgpa.
         // Report handles this by checking local DB first for GMIT high scorers.
         // Let's just create a list of eligible GMIT IDs locally first.
         $stmtLocal = $localDB->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND sgpa >= ?");
         $stmtLocal->execute([INSTITUTION_GMIT, $min_sgpa]);
         $highScorerIds = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
         
         if (!empty($highScorerIds)) {
             $placeholders = implode(',', array_fill(0, count($highScorerIds), '?'));
             $where_clauses[] = "((asa.institution = '" . INSTITUTION_GMU . "' AND asa.sgpa >= ?) OR (asa.institution = '" . INSTITUTION_GMIT . "' AND asa.usn IN ($placeholders)))";
             $params[] = $min_sgpa;
             $params = array_merge($params, $highScorerIds);
         } else {
             $where_clauses[] = "(asa.institution = '" . INSTITUTION_GMU . "' AND asa.sgpa >= ?)";
             $params[] = $min_sgpa;
         }
    }
}

// Semester Filtering (Dynamic based on Department) & GMIT Local Check
$semester_filter = getCoordinatorSemesterFilters($department);
$sem_placeholders = implode(',', array_fill(0, count($semester_filter), '?'));

if ($instFilter === 'all' || $instFilter === 'gmit') {
    $stmtLocal = $localDB->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN ($sem_placeholders)");
    $stmtLocal->execute(array_merge([INSTITUTION_GMIT], $semester_filter));
    $gmitUsns = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
    
    $gmu_sem_sql = "asa.sem IN ($sem_placeholders)";
    $gmu_sem_params = $semester_filter;

    if (!empty($gmitUsns)) {
         $placeholders = implode(',', array_fill(0, count($gmitUsns), '?'));
         // GMU sem check OR GMIT valid ID check
         $where_clauses[] = "((asa.institution = '" . INSTITUTION_GMU . "' AND $gmu_sem_sql) OR (asa.institution = '" . INSTITUTION_GMIT . "' AND asa.usn IN ($placeholders)))";
         $params = array_merge($params, $gmu_sem_params, $gmitUsns);
    } else {
         // No GMIT students found with valid sem, show only GMU
         $where_clauses[] = "(asa.institution = '" . INSTITUTION_GMU . "' AND $gmu_sem_sql)";
         $params = array_merge($params, $gmu_sem_params);
    }
} else {
    // Only GMU
     $where_clauses[] = "asa.sem IN ($sem_placeholders)";
     $params = array_merge($params, $semester_filter);
}

$where_sql = implode(" AND ", $where_clauses);

// Count Total
$count_query = "SELECT COUNT(DISTINCT asa.usn) FROM {$combinedApproved} asa WHERE $where_sql";
$stmt = $remoteDB->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Data
$query = "SELECT asa.usn, MAX(asa.name) as name, MAX(asa.discipline) as discipline, MAX(asa.sem) as sem, MAX(asa.sgpa) as sgpa, MAX(asa.registered) as registered, asa.institution 
          FROM {$combinedApproved} asa 
          WHERE $where_sql 
          GROUP BY asa.usn, asa.institution
          ORDER BY name ASC 
          LIMIT $limit OFFSET $offset";
$stmt = $remoteDB->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enrich with Local Data (GMIT Sem/SGPA + Task Status)
foreach ($students as &$student) {
    // 1. GMIT Data Enrichment
    if ($student['institution'] === INSTITUTION_GMIT) {
        $stmt = $localDB->prepare("SELECT sgpa, semester FROM student_sem_sgpa 
                                   WHERE student_id = ? AND institution = ? 
                                   ORDER BY semester DESC LIMIT 1");
        $stmt->execute([$student['usn'], INSTITUTION_GMIT]);
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

    $stmt = $localDB->prepare("SELECT ct.id, ct.task_type, ct.created_at, ct.company_name, 
                                     tc.score, tc.completed_at
                               FROM coordinator_tasks ct
                               LEFT JOIN task_completions tc ON ct.id = tc.task_id AND tc.student_id = ?
                               WHERE ct.coordinator_id = ?
                                 AND JSON_CONTAINS(ct.target_students, ?)
                               ORDER BY ct.created_at DESC LIMIT 1");
    $stmt->execute([$student['usn'], $coordinatorId, "\"" . $student['usn'] . "\""]);
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Tasks - <?php echo APP_NAME; ?></title>
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-light); color: var(--text-main); }
        .navbar-spacer { height: 70px; }
        .container { 
            width: 90%; 
            max-width: 1400px; 
            margin: 30px auto; 
            background: transparent;
        }
        
        .page-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; }
        .page-header h2 { font-size: 28px; color: var(--primary-maroon); font-weight: 700; margin-bottom: 5px; }
        
        /* Tabs & Filters */
        .tabs-inst { display: flex;gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab-inst { padding: 8px 16px; border-radius: 6px; font-weight: 600; text-decoration: none; color: #64748b; transition: all 0.2s; }
        .tab-inst.active { background: var(--primary-maroon); color: white; }
        .tab-inst:hover:not(.active) { background: #e2e8f0; color: var(--primary-maroon); }

        .filter-section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .filter-grid { display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 200px; }
        .filter-item label { font-size: 13px; font-weight: 600; color: #64748b; }
        
        .form-input, .form-select { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; background: white; transition: all 0.2s; cursor: pointer; }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--primary-maroon); box-shadow: 0 0 0 3px rgba(128,0,0,0.1); }
        .filter-item select { appearance: none; padding-right: 35px; }
        .filter-item-wrapper { position: relative; }
        .filter-item-wrapper i.filter-icon { position: absolute; right: 12px; top: 38px; color: var(--text-muted); pointer-events: none; }
        
        .btn-filter { background: var(--primary-maroon); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .btn-filter:hover { background: #600000; }

        /* Table */
        .table-container { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f8f9fa; padding: 14px 12px; text-align: left; font-weight: 700; color: var(--text-main); border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:hover { background: #fdfafa; }

        .btn-assign { background: var(--primary-gold); color: #1e293b; padding: 6px 14px; border-radius: 20px; border: none; cursor: pointer; font-weight: 600; font-size: 12px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-assign:hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); background: #c4a137; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-gmu { background: #ffebee; color: #b71c1c; }
        .badge-gmit { background: #e3f2fd; color: #0d47a1; }

        .task-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .task-badge.aptitude { background: #e3f2fd; color: #1976d2; }
        .task-badge.technical { background: #ffebee; color: #c62828; }
        .task-badge.hr { background: #e8f5e9; color: #2e7d32; }

        .score-badge { padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 12px; }
        .score-high { background: #d1e7dd; color: #0f5132; }
        .score-low { background: #f8d7da; color: #842029; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { font-size: 22px; font-weight: 700; color: var(--primary-maroon); margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 12px; margin-top: 25px; }
        .btn-cancel { flex: 1; background: #e2e8f0; padding: 12px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }
        .btn-submit { flex: 1; background: var(--primary-maroon); color: white; padding: 12px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }

        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap; }
        .page-link { padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #64748b; font-weight: 600; transition: all 0.2s; }
        .page-link.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }
        .page-link:hover:not(.active) { background: #f1f5f9; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="navbar-spacer"></div>
    
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-clipboard-list"></i> Assign Tasks</h2>
            <p>Select students to assign aptitude, technical, or HR assessments.</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div style="background: #f8d7da; color: #842029; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs-inst">
            <a href="<?php echo buildUrl('inst', 'all'); ?>" class="tab-inst <?php echo $instFilter === 'all' ? 'active' : ''; ?>">All Students</a>
            <a href="<?php echo buildUrl('inst', 'gmu'); ?>" class="tab-inst <?php echo $instFilter === 'gmu' ? 'active' : ''; ?>">GMU</a>
            <a href="<?php echo buildUrl('inst', 'gmit'); ?>" class="tab-inst <?php echo $instFilter === 'gmit' ? 'active' : ''; ?>">GMIT</a>
        </div>

        <div class="filter-section">
            <form method="POST" class="filter-grid">
                <input type="hidden" name="inst" value="<?php echo htmlspecialchars($instFilter); ?>">
                
                <div class="filter-item">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="USN or Name...">
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-graduation-cap"></i> Min SGPA</label>
                    <input type="number" name="min_sgpa" class="form-input" value="<?php echo $min_sgpa > 0 ? $min_sgpa : ''; ?>" step="0.01" min="0" max="10" placeholder="e.g. 7.5">
                </div>

                <?php if (count($available_branches) > 1): ?>
                <div class="filter-item filter-item-wrapper">
                    <label><i class="fas fa-code-branch"></i> Branch Filter</label>
                    <select name="branch" class="form-input">
                        <option value="">All Specializations</option>
                        <?php foreach ($available_branches as $ab): ?>
                            <option value="<?php echo htmlspecialchars($ab); ?>" <?php echo $branch_filter_val === $ab ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ab); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-chevron-down filter-icon"></i>
                </div>
                <?php endif; ?>

                <div class="filter-item" style="flex: 0; display: flex; gap: 10px; align-items: flex-end;">
                    <button type="submit" class="btn-filter">Apply Filters</button>
                    <form method="POST" action="assign_task.php" style="display: contents;">
                        <input type="hidden" name="reset_filters" value="1">
                        <button type="submit" class="btn-filter" style="background: #64748b;">Reset</button>
                    </form>
                    <button type="button" class="btn-filter" style="background: #1e293b;" onclick="openBulkAssignModal()">
                        <i class="fas fa-tasks"></i> Bulk Assign
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                        </th>
                        <th style="width: 40px;">#</th>
                        <th>Student Name & USN</th>
                        <th>Inst</th>
                        <th>Branch</th>
                        <th>Sem</th>
                        <th>Task Assigned</th>
                        <th>Score</th>
                        <th>Completed</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="10" style="text-align: center; padding: 40px; color: #94a3b8;">No eligible students found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php 
                        $slNo = $offset + 1;
                        foreach ($students as $student): 
                            $isRegistered = (int)$student['registered'] === 1;
                            $scoreClass = '';
                            if (isset($student['score'])) {
                                if ($student['score'] >= 75) $scoreClass = 'score-high';
                                elseif ($student['score'] >= 50) $scoreClass = 'score-medium';
                                else $scoreClass = 'score-low';
                            }
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="student-checkbox" value="<?php echo htmlspecialchars($student['usn']); ?>">
                            </td>
                            <td style="color: #94a3b8;"><?php echo $slNo++; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($student['name'] ?? 'N/A'); ?></div>
                                    <?php if ($student['total_tasks'] > 0): ?>
                                        <i class="fas fa-history" style="color: var(--primary-maroon); cursor: pointer; font-size: 11px; opacity: 0.7;" 
                                           onclick="viewHistory('<?php echo htmlspecialchars($student['usn']); ?>', '<?php echo htmlspecialchars($student['name']); ?>')" 
                                           title="View Task History (<?php echo $student['total_tasks']; ?> tasks)"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($student['usn']); ?></div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($student['institution']); ?>">
                                    <?php echo $student['institution']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($student['discipline'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($student['sem'] ?? '-'); ?></td>
                            <td>
                                <?php if ($student['latest_task']): $task = $student['latest_task']; ?>
                                    <div style="display: flex; align-items: center; gap: 4px; font-size: 11px;">
                                        <span class="task-badge <?php echo $task['task_type']; ?>" style="padding: 2px 6px; font-size: 9px;">
                                            <?php echo strtoupper(substr($task['task_type'], 0, 1)); ?>
                                        </span>
                                        <span style="color: #64748b; font-size: 10px; white-space: nowrap;">
                                            <?php echo $task['company_name'] ? htmlspecialchars($task['company_name']) : 'General'; ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 9px; color: #94a3b8; margin-top: 2px;">Assigned: <?php echo date('d M', strtotime($task['created_at'])); ?></div>
                                <?php else: ?>
                                    <span style="color: #cbd5e1;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['latest_task'] && isset($student['latest_task']['score'])): 
                                    $task = $student['latest_task'];
                                    $sClass = ($task['score'] >= 75) ? 'score-high' : (($task['score'] >= 50) ? 'score-medium' : 'score-low');
                                ?>
                                    <span class="score-badge <?php echo $sClass; ?>" style="padding: 2px 6px; font-size: 10px;">
                                        <?php echo number_format($task['score'], 0); ?>%
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($student['latest_task'] && isset($student['latest_task']['completed_at'])): ?>
                                    <div style="font-size: 11px; color: #64748b;">
                                        <?php echo date('d M', strtotime($student['latest_task']['completed_at'])); ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <button class="btn-assign" onclick="openAssignModal('<?php echo htmlspecialchars($student['usn']); ?>', '<?php echo htmlspecialchars($student['name']); ?>')">
                                    <i class="fas fa-plus"></i> Assign
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<a href="javascript:void(0)" onclick="updateFilter(\'page\', 1)" class="page-link ' . (1 === $page ? 'active' : '') . '">1</a>';
                        if ($start > 2) echo '<span class="page-link" style="border:none;">...</span>';
                    }
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="javascript:void(0)" onclick="updateFilter('page', <?php echo $i; ?>)" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<span class="page-link" style="border:none;">...</span>';
                        echo '<a href="javascript:void(0)" onclick="updateFilter(\'page\', ' . $total_pages . ')" class="page-link ' . ($total_pages === $page ? 'active' : '') . '">' . $total_pages . '</a>';
                    }
                    ?>
                </div>
                
            <?php endif; ?>

            <!-- Global Hidden Form for Page/Filter Updates -->
            <form id="filterForm" method="POST" style="display:none;">
                <input type="hidden" name="inst" id="filterInst" value="<?php echo htmlspecialchars($instFilter); ?>">
                <input type="hidden" name="search" id="filterSearch" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="min_sgpa" id="filterSgpa" value="<?php echo htmlspecialchars($min_sgpa); ?>">
                <input type="hidden" name="branch" id="filterBranch" value="<?php echo htmlspecialchars($branch_filter_val); ?>">
                <input type="hidden" name="page" id="filterPage" value="<?php echo htmlspecialchars($page); ?>">
            </form>

            <script>
                function updateFilter(key, val) {
                    if (key === 'inst') document.getElementById('filterInst').value = val;
                    if (key === 'search') document.getElementById('filterSearch').value = val;
                    if (key === 'min_sgpa') document.getElementById('filterSgpa').value = val;
                    if (key === 'branch') document.getElementById('filterBranch').value = val;
                    if (key === 'page') document.getElementById('filterPage').value = val;
                    
                    // Reset page if filtering by other criteria
                    if (key !== 'page') document.getElementById('filterPage').value = 1;
                    
                    document.getElementById('filterForm').submit();
                }
            </script>
        </div>
    </div>

    <!-- Assignment Modal (Same as before) -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <!-- Task History Modal -->
            <div id="historyModal" class="modal">
                <div class="modal-content" style="max-width: 500px;">
                    <button class="close-modal" onclick="closeHistoryModal()">&times;</button>
                    <div class="modal-header">
                        <h2 style="font-size: 18px; color: var(--primary-maroon);">Task History</h2>
                        <p id="historyStudentName" style="font-size: 13px; color: var(--text-muted);"></p>
                    </div>
                    <div class="modal-body">
                        <div id="historyList" style="display: flex; flex-direction: column; gap: 12px; padding: 10px 0;">
                            <!-- History items will be injected here -->
                        </div>
                        <div id="historyEmpty" style="display: none; text-align: center; padding: 30px; color: #94a3b8;">
                            No task history found for this student.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-header">Assign Task</div>
            <form method="POST">
                <input type="hidden" name="student_id" id="modal_student_id">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 8px; font-weight: 600;">Student</label>
                    <input type="text" id="modal_student_name" class="form-input" style="width: 100%; padding: 12px; background: #f8fafc;" readonly>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 8px; font-weight: 600;">Task Type *</label>
                    <select name="task_type" class="form-select" style="width: 100%;" required>
                        <option value="">Select Type</option>
                        <option value="aptitude">Aptitude Round</option>
                        <option value="technical">Technical Round</option>
                        <option value="hr">HR Round</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 8px; font-weight: 600;">Company Name (Optional)</label>
                    <input type="text" name="company_name" class="form-input" style="width: 100%;" placeholder="e.g., TCS, Infosys">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 8px; font-weight: 600;">Technical Concept / Job Role (Recommended)</label>
                    <input type="text" name="concept" class="form-input" style="width: 100%;" placeholder="e.g., Site Engineering, Taxation, HVAC Design">
                    <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 4px;">This helps the AI tailor Technical and HR questions for non-technical branches.</small>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 8px; font-weight: 600;">Question Source *</label>
                    <select name="question_source" class="form-select" style="width: 100%;" required>
                        <option value="ai">AI-Generated Questions</option>
                        <option value="manual">Manual Questions (Coming Soon)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom: 8px; font-weight: 600;">Deadline *</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="date" name="deadline_date" class="form-input" style="flex: 2;" required>
                        <div style="display: flex; gap: 5px; flex: 3; align-items: center;">
                            <select name="deadline_hour" class="form-select" style="width: 70px; text-align: center;" required>
                                <?php for($i=1; $i<=12; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                <?php endfor; ?>
                            </select>
                            <span>:</span>
                            <select name="deadline_minute" class="form-select" style="width: 70px; text-align: center;" required>
                                <?php for($i=0; $i<60; $i+=5): ?>
                                    <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="deadline_ampm" class="form-select" style="width: 80px;">
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" name="assign_task" class="btn-submit">Assign Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAssignModal(studentId, studentName) {
            document.getElementById('modal_student_id').value = studentId;
            document.getElementById('modal_student_name').value = studentName;
            document.getElementById('assignModal').style.display = 'flex';
        }

        function openBulkAssignModal() {
            const selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) {
                alert('Please select at least one student.');
                return;
            }
            document.getElementById('modal_student_id').value = selected.join(',');
            document.getElementById('modal_student_name').value = `${selected.length} students selected`;
            document.getElementById('assignModal').style.display = 'flex';
        }

        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }

        function selectAll(cb) {
            document.querySelectorAll('.student-checkbox').forEach(c => c.checked = cb.checked);
        }

        // --- History Modal Functions ---
        function viewHistory(usn, name) {
            document.getElementById('historyStudentName').innerText = name + ' (' + usn + ')';
            const list = document.getElementById('historyList');
            const empty = document.getElementById('historyEmpty');
            list.innerHTML = '<div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
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
                            const dateStr = new Date(task.assigned_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
                            const compDate = task.completed_at ? new Date(task.completed_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : 'Pending';
                            const scoreHtml = task.score !== null 
                                ? `<span class="score-badge ${task.score >= 75 ? 'score-high' : (task.score >= 50 ? 'score-medium' : 'score-low')}" style="font-size:10px;">${Math.round(task.score)}%</span>` 
                                : '<span style="color:#cbd5e1; font-size:10px;">Pending</span>';
                            
                            return `
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span class="task-badge ${task.task_type}" style="width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:4px; font-weight:700; font-size:10px;">
                                            ${task.task_type.substring(0,1).toUpperCase()}
                                        </span>
                                        <div>
                                            <div style="font-size:12px; font-weight:600; color:#1e293b;">${task.company_name || 'General Assessment'}</div>
                                            <div style="font-size:10px; color:#64748b;">Assigned: ${dateStr}</div>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        ${scoreHtml}
                                        <div style="font-size:9px; color:#94a3b8; margin-top:2px;">${compDate}</div>
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
            document.querySelectorAll('.student-checkbox').forEach(cb => {
                cb.checked = masterCb.checked;
            });
        }

        window.onclick = function(event) {
            const modal = document.getElementById('assignModal');
            if (event.target === modal) closeAssignModal();
        }
    </script>
</body>
</html>
