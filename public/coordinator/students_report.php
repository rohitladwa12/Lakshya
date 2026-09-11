<?php
/**
 * Department Coordinator - Consolidated Students & Reports
 * One page: Student Details | AI Reports — with tabs for All | GMU | GMIT
 */

$pageStartTime = microtime(true);

require_once __DIR__ . '/../../config/bootstrap.php';

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_DEPT_COORDINATOR);

$pageId = 'coordinator_students_report';

// Handle POST (Filters, Reset, Pagination)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    if (isset($_POST['reset_filters'])) {
        SessionFilterHelper::clearFilters($pageId);
    } else {
        // MERGE into the session (do not replace) so partial posts — tab switches
        // (only "inst"/"section") and pagination (only "page") — keep the other filters intact.
        $updates = $_POST;
        // Any filter/tab change resets pagination back to page 1; only a pure
        // pagination post carries its own "page" value.
        if (!isset($updates['page'])) {
            $updates['page'] = 1;
        }
        SessionFilterHelper::updateFilters($pageId, $updates);
    }
    header("Location: students_report.php");
    exit;
}

// Handle GET tab switching (manual redirect to session via POST is preferred, but we support GET for direct links if needed, immediately redirecting)
if (isset($_GET['section']) || isset($_GET['inst'])) {
    $updates = ['page' => 1];
    if (isset($_GET['section'])) $updates['section'] = $_GET['section'];
    if (isset($_GET['inst'])) $updates['inst'] = $_GET['inst'];
    SessionFilterHelper::updateFilters($pageId, $updates);
    header("Location: students_report.php");
    exit;
}

$filters = SessionFilterHelper::getFilters($pageId);

$department = getDepartment();
list($deptGmu, $deptGmit) = getCoordinatorDisciplineFilters($department);
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;

$section = $filters['section'] ?? 'details';
if (!in_array($section, ['details', 'reports'], true)) $section = 'details';

$inst = $filters['inst'] ?? 'all';
if (!in_array($inst, ['all', 'gmu', 'gmit'], true)) $inst = 'all';

$instFilter = ($inst === 'gmu') ? INSTITUTION_GMU : (($inst === 'gmit') ? INSTITUTION_GMIT : null);

// --- AJAX Update Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $usn = $_POST['usn'] ?? '';
    $inst = $_POST['inst'] ?? '';
    $val = $_POST['value'] ?? '';
    $field = $_POST['field'] ?? '';

    $freezeActions = ['freeze_sgpa', 'unfreeze_sgpa', 'freeze_all_sgpa', 'unfreeze_all_sgpa'];
    if (!in_array($_POST['ajax_action'], $freezeActions) && (empty($usn) || empty($inst) || empty($field))) {
        echo json_encode(['status' => 'error', 'message' => 'Missing USN, Institution or Field']);
        exit;
    }

    $db_update = getDB(); // Local DB

    // --- Permission Check ---
    // Ensure the coordinator can only edit students within their department's disciplines
    $department = getDepartment();
    $allowed_disciplines = getCoordinatorDisciplineFilters($department);
    
    // Fetch student's discipline for verification
    try {
        $student_discipline = null;
        if ($inst === INSTITUTION_GMU) {
            $stmtD = $db_update->prepare("SELECT discipline FROM " . DB_GMU_PREFIX . "ad_student_approved WHERE usn = ?");
            $stmtD->execute([$usn]);
            $student_discipline = $stmtD->fetchColumn();
        } else {
            $db_inst = getDB('gmit');
            if ($db_inst) {
                $stmtD = $db_inst->prepare("SELECT discipline FROM ad_student_details WHERE student_id = ? OR usn = ?");
                $stmtD->execute([$usn, $usn]);
                $student_discipline = $stmtD->fetchColumn();
            }
        }
        
        if ($student_discipline && !in_array(strtoupper(trim($student_discipline)), array_map('strtoupper', $allowed_disciplines))) {
            echo json_encode(['status' => 'error', 'message' => 'Access Denied: Student is not in your department.']);
            exit;
        }
    } catch (Exception $e) {
        // Log error but proceed if verification fails to avoid blocking legitimate edits due to DB connection issues
        error_log("AJAX Permission Check Error: " . $e->getMessage());
    }
    // --- End Permission Check ---
    if (strpos($field, 'sem_') === 0) {
        // Handle SGPA Update in local DB (Coordinator)
        $sem = (int)str_replace('sem_', '', $field);
        // Treat empty string or dash as NULL (clearing the SGPA)
        $sgpaVal = ($val === '' || $val === '-') ? null : (float)$val;
        try {
            if ($sgpaVal === null) {
                // Delete the row if value is cleared
                $stmt = $db_update->prepare("DELETE FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND semester = ?");
                $stmt->execute([$usn, $inst, $sem]);
            } else {
                // Upsert the SGPA and mark it as frozen (coordinator-set)
                // For GMIT, if we are saving by USN but a record exists for Aadhaar, we should update both?
                // Actually, let's just save to the identifier provided by the report (which is now the USN preferring IFNULL(NULLIF(usn, ''), student_id))
                $stmt = $db_update->prepare(
                    "INSERT INTO student_sem_sgpa (student_id, institution, semester, sgpa, freezed) 
                     VALUES (?, ?, ?, ?, 1) 
                     ON DUPLICATE KEY UPDATE sgpa = VALUES(sgpa), freezed = 1"
                );
                $stmt->execute([$usn, $inst, $sem, $sgpaVal]);

                // Special handling for GMIT: if a record exists for a different ID (e.g. Aadhar vs USN), 
                // we should probably ensure consistency. But the flexible fetch should handle viewing.

                // Ensure is_current is set on the highest semester for this student
                // so the student dashboard recognises the history as complete.
                $db_update->prepare("UPDATE student_sem_sgpa SET is_current = 0 WHERE student_id = ? AND institution = ?")
                          ->execute([$usn, $inst]);
                $stmtMax = $db_update->prepare(
                    "SELECT MAX(semester) FROM student_sem_sgpa WHERE student_id = ? AND institution = ?"
                );
                $stmtMax->execute([$usn, $inst]);
                $maxSem = $stmtMax->fetchColumn();
                if ($maxSem) {
                    $db_update->prepare(
                        "UPDATE student_sem_sgpa SET is_current = 1 WHERE student_id = ? AND institution = ? AND semester = ?"
                    )->execute([$usn, $inst, $maxSem]);
                }
            }
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    } elseif ($field === 'verify_portfolio') {
        // Handle Portfolio Verification
        $itemId = (int)$_POST['item_id'];
        try {
            $stmt = $db_update->prepare("UPDATE student_portfolio SET is_verified = 1 WHERE id = ? AND student_id = ?");
            $stmt->execute([$itemId, $usn]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['ajax_action'] === 'freeze_sgpa' || $_POST['ajax_action'] === 'unfreeze_sgpa') {
        $isFrozen = ($_POST['ajax_action'] === 'freeze_sgpa') ? 1 : 0;
        try {
            $stmt = $db_update->prepare("UPDATE student_sem_sgpa SET freezed = ? WHERE student_id = ? AND institution = ?");
            $stmt->execute([$isFrozen, $usn, $inst]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['ajax_action'] === 'freeze_all_sgpa') {
        $students = json_decode($_POST['students'] ?? '[]', true);
        if (empty($students)) {
            echo json_encode(['status' => 'error', 'message' => 'No students provided']);
            exit;
        }
        try {
            $db_update->beginTransaction();
            $stmt = $db_update->prepare("UPDATE student_sem_sgpa SET freezed = 1 WHERE student_id = ? AND institution = ?");
            foreach ($students as $s) {
                if (!empty($s['usn']) && !empty($s['inst'])) {
                    $stmt->execute([$s['usn'], $s['inst']]);
                }
            }
            $db_update->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $db_update->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['ajax_action'] === 'unfreeze_all_sgpa') {
        $students = json_decode($_POST['students'] ?? '[]', true);
        if (empty($students)) {
            echo json_encode(['status' => 'error', 'message' => 'No students provided']);
            exit;
        }
        try {
            $db_update->beginTransaction();
            $stmt = $db_update->prepare("UPDATE student_sem_sgpa SET freezed = 0 WHERE student_id = ? AND institution = ?");
            foreach ($students as $s) {
                if (!empty($s['usn']) && !empty($s['inst'])) {
                    $stmt->execute([$s['usn'], $s['inst']]);
                }
            }
            $db_update->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $db_update->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    } else {
        // Handle Student Details Update (Fathers Name, Mobiles, Scores, etc)
        $targetTable = ($inst === INSTITUTION_GMU) ? DB_GMU_PREFIX . 'ad_student_details' : DB_GMIT_PREFIX . 'ad_student_details';
        $idCol = ($inst === INSTITUTION_GMU) ? 'usn' : 'student_id';
        $allowedFields = ['father_name', 'parent_mobile', 'student_mobile', 'puc_percentage', 'sslc_percentage', 'gender', 'name'];
        
        if (!in_array($field, $allowedFields)) {
            echo json_encode(['status' => 'error', 'message' => 'Field not allowed']);
            exit;
        }

        try {
            $db_inst = getDB('gmu'); 
            $stmt = $db_inst->prepare("UPDATE $targetTable SET $field = ? WHERE $idCol = ?");
            $stmt->execute([$val, $usn]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// --- Filter Variable Initialization ---
$search = clean($filters['search'] ?? '');
$min_sgpa = (isset($filters['min_sgpa']) && $filters['min_sgpa'] !== '') ? (float)$filters['min_sgpa'] : 0;
$max_sgpa = (isset($filters['max_sgpa']) && $filters['max_sgpa'] !== '') ? (float)$filters['max_sgpa'] : 0;
$sgpa_mode = clean($filters['sgpa_mode'] ?? 'latest'); // 'latest', 'avg', 'sem_1'..'sem_8', 'all_sems'
$min_puc = (isset($filters['min_puc']) && $filters['min_puc'] !== '') ? (float)$filters['min_puc'] : 0;
$min_sslc = (isset($filters['min_sslc']) && $filters['min_sslc'] !== '') ? (float)$filters['min_sslc'] : 0;
$gender_filter = clean($filters['gender'] ?? '');
$portfolio_filter = clean($filters['portfolio_status'] ?? '');
$freeze_filter = clean($filters['freeze_status'] ?? '');
$sort_by = clean($filters['sort_by'] ?? 'name_asc');
$branch_filter_val = clean($filters['branch'] ?? '');

// Default semester: query actual highest sem with students in this dept
$semester_filter_range = getCoordinatorSemesterFilters($department);
if (!array_key_exists('sem', $filters)) {
    $disc_temp = array_values(array_unique(getCoordinatorDisciplineFilters($department)));
    $ph_d = implode(',', array_fill(0, count($disc_temp), '?'));
    $ph_s = implode(',', array_fill(0, count($semester_filter_range), '?'));
    try {
        $dbTmp = getDB('gmu');
        $stmtMs = $dbTmp->prepare("SELECT MAX(sem) FROM " . DB_GMU_PREFIX . "ad_student_approved WHERE discipline IN ($ph_d) AND sem IN ($ph_s)");
        $stmtMs->execute(array_merge($disc_temp, $semester_filter_range));
        $sem_filter_val = (int)($stmtMs->fetchColumn() ?: 0);
    } catch (Exception $e) {
        $sem_filter_val = 0;
    }
} else {
    $sem_filter_val = (int)($filters['sem'] ?? 0);
}

// --- AI Reports data ---
$officerModel = new PlacementOfficer();
$semester_filter = getCoordinatorSemesterFilters($department);
if ($sem_filter_val > 0 && in_array($sem_filter_val, $semester_filter)) {
    $semester_filter = [$sem_filter_val];
}
$reportFilters = ['department' => $department, 'semesters' => $semester_filter];
if ($instFilter) $reportFilters['institution'] = $instFilter;
// AI reports are loaded dynamically via AJAX in the student modal for this section.

function findStudentReportsCoord($usn) {
    $reports = [];
    $hrDir = __DIR__ . '/../uploads/reports/hr/';
    $techDir = __DIR__ . '/../uploads/reports/technical/';
    if (is_dir($hrDir)) {
        foreach (glob($hrDir . $usn . '_*.pdf') ?: [] as $file) {
            $reports[] = ['type' => 'HR', 'path' => 'uploads/reports/hr/' . basename($file), 'filename' => basename($file)];
        }
    }
    if (is_dir($techDir)) {
        foreach (glob($techDir . $usn . '_*.pdf') ?: [] as $file) {
            $reports[] = ['type' => 'Technical', 'path' => 'uploads/reports/technical/' . basename($file), 'filename' => basename($file)];
        }
    }
    $mockDir = __DIR__ . '/../uploads/reports/mock_ai/';
    if (is_dir($mockDir)) {
        foreach (glob($mockDir . $usn . '_*.pdf') ?: [] as $file) {
            $reports[] = ['type' => 'Mock AI', 'path' => 'uploads/reports/mock_ai/' . basename($file), 'filename' => basename($file)];
        }
    }
    return $reports;
}

// --- Student Details data (only when section=details) ---
$db = getDB('gmu');
$localDB = getDB();
$limit = 100;
$page = max(1, (int)($filters['page'] ?? 1));
$offset = ($page - 1) * $limit;

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

$combinedApproved = "
    (SELECT usn, name, aadhar, faculty, school, programme, course, discipline, year, sem, sgpa, registered, usn as student_id_map, '" . INSTITUTION_GMU . "' as institution FROM {$gmuPrefix}ad_student_approved
     UNION ALL
     SELECT IFNULL(NULLIF(usn, ''), student_id) as usn, name, aadhar, college as faculty, college as school, programme, course, discipline, 0 as year, 0 as sem, 0.0 as sgpa, 1 as registered, student_id as student_id_map, '" . INSTITUTION_GMIT . "' as institution FROM {$gmitPrefix}ad_student_details)
";

$combinedDetails = "
    (SELECT usn, student_id, gender, dob, student_mobile, parent_mobile, father_name, mother_name, email_id, puc_percentage, sslc_percentage, category, district, taluk, state, regular_lateral
     FROM {$gmuPrefix}ad_student_details
     UNION ALL
     SELECT usn, student_id, gender, dob, student_mobile, parent_mobile, father_name, mother_name, email_id, puc_percentage, sslc_percentage, category, district, taluk, state, regular_lateral
     FROM {$gmitPrefix}ad_student_details)
";

$where_clauses = []; // No registered filter — students in latest sem are always valid
$params = [];

if ($instFilter) {
    $where_clauses[] = "asa.institution = ?";
    $params[] = $instFilter;
}

if ($search) {
    $where_clauses[] = "(asa.usn LIKE ? OR asa.name LIKE ? OR asa.aadhar LIKE ? OR asd.student_mobile LIKE ? OR asd.parent_mobile LIKE ? OR asd.father_name LIKE ? OR asd.email_id LIKE ?)";
    for ($k = 0; $k < 7; $k++) {
        $params[] = "%$search%";
    }
}

if ($discipline_filter_sql = buildInClauseCoord("asa.discipline", $discipline_filter, $params)) {
    $where_clauses[] = $discipline_filter_sql;
}

// 10th and 12th Percentage Filters
if ($min_puc > 0) {
    $where_clauses[] = "CAST(NULLIF(asd.puc_percentage, '') AS DECIMAL(5,2)) >= ?";
    $params[] = $min_puc;
}
if ($min_sslc > 0) {
    $where_clauses[] = "CAST(NULLIF(asd.sslc_percentage, '') AS DECIMAL(5,2)) >= ?";
    $params[] = $min_sslc;
}

// Gender Filter
if ($gender_filter === 'M' || $gender_filter === 'F') {
    $where_clauses[] = "asd.gender LIKE ?";
    $params[] = $gender_filter . '%';
}

// Portfolio Verification Status Filter
if ($portfolio_filter === 'verified') {
    $stmtVer = $localDB->query("SELECT DISTINCT student_id FROM student_portfolio WHERE is_verified = 1");
    $verIds = $stmtVer ? $stmtVer->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!empty($verIds)) {
        $phVer = implode(',', array_fill(0, count($verIds), '?'));
        $where_clauses[] = "asa.usn IN ($phVer)";
        $params = array_merge($params, $verIds);
    } else {
        $where_clauses[] = "1=0";
    }
} elseif ($portfolio_filter === 'unverified') {
    $stmtVer = $localDB->query("SELECT DISTINCT student_id FROM student_portfolio WHERE is_verified = 1");
    $verIds = $stmtVer ? $stmtVer->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!empty($verIds)) {
        $phVer = implode(',', array_fill(0, count($verIds), '?'));
        $where_clauses[] = "asa.usn NOT IN ($phVer)";
        $params = array_merge($params, $verIds);
    }
}

// Freeze Status Filter
if ($freeze_filter === 'frozen') {
    $stmtFrz = $localDB->query("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE freezed = 1");
    $frzIds = $stmtFrz ? $stmtFrz->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!empty($frzIds)) {
        $phFrz = implode(',', array_fill(0, count($frzIds), '?'));
        $where_clauses[] = "asa.usn IN ($phFrz)";
        $params = array_merge($params, $frzIds);
    } else {
        $where_clauses[] = "1=0";
    }
} elseif ($freeze_filter === 'active') {
    $stmtFrz = $localDB->query("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE freezed = 1");
    $frzIds = $stmtFrz ? $stmtFrz->fetchAll(PDO::FETCH_COLUMN) : [];
    if (!empty($frzIds)) {
        $phFrz = implode(',', array_fill(0, count($frzIds), '?'));
        $where_clauses[] = "asa.usn NOT IN ($phFrz)";
        $params = array_merge($params, $frzIds);
    }
}

// Semester eligibility
$sem_placeholders = implode(',', array_fill(0, count($semester_filter), '?'));

// 1. GMIT active USNs in selected semesters
$stmtLocal = $localDB->prepare("
    SELECT DISTINCT s.student_id
    FROM student_sem_sgpa s
    JOIN (
        SELECT student_id, MAX(semester) AS current_sem
        FROM student_sem_sgpa
        WHERE institution = ?
        GROUP BY student_id
    ) cur ON cur.student_id = s.student_id AND s.semester = cur.current_sem
    WHERE s.institution = ? AND s.semester IN ($sem_placeholders)
");
$stmtLocal->execute(array_merge([INSTITUTION_GMIT, INSTITUTION_GMIT], $semester_filter));
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

// 2. GMU semester matching rule
$gmuCurrentSql = "asa.usn IN (
    SELECT cur_rows.usn
    FROM {$gmuPrefix}ad_student_approved cur_rows
    JOIN (
        SELECT usn, MAX(sem) AS current_sem
        FROM {$gmuPrefix}ad_student_approved
        GROUP BY usn
    ) cur ON cur.usn = cur_rows.usn AND cur_rows.sem = cur.current_sem
    WHERE cur_rows.sem IN ($sem_placeholders)
)";

if (!$instFilter) {
    foreach ($semester_filter as $s_val) $params[] = $s_val;
    if (!empty($gmitUsns)) {
        $placeholders = implode(',', array_fill(0, count($gmitUsns), '?'));
        $where_clauses[] = "((asa.institution = '" . INSTITUTION_GMU . "' AND $gmuCurrentSql) OR (asa.institution = '" . INSTITUTION_GMIT . "' AND asa.usn IN ($placeholders)))";
        $params = array_merge($params, $gmitUsns);
    } else {
        $where_clauses[] = "(asa.institution = '" . INSTITUTION_GMU . "' AND $gmuCurrentSql)";
    }
} else {
    if ($instFilter === INSTITUTION_GMU) {
        $where_clauses[] = $gmuCurrentSql;
        foreach ($semester_filter as $s_val) $params[] = $s_val;
    } else {
        if (!empty($gmitUsns)) {
            $placeholders = implode(',', array_fill(0, count($gmitUsns), '?'));
            $where_clauses[] = "asa.usn IN ($placeholders)";
            $params = array_merge($params, $gmitUsns);
        } else {
            $where_clauses[] = "1=0";
        }
    }
}

// 3. Fully Advanced & Universal SGPA Filter (Checks local student_sem_sgpa + remote DB)
if ($min_sgpa > 0 || $max_sgpa > 0) {
    $qualifyingSgpaIds = [];
    
    if ($sgpa_mode === 'avg') {
        // Cumulative CGPA / Average SGPA
        $havingLocal = [];
        $havingParamsLocal = [];
        if ($min_sgpa > 0) { $havingLocal[] = "AVG(sgpa) >= ?"; $havingParamsLocal[] = $min_sgpa; }
        if ($max_sgpa > 0) { $havingLocal[] = "AVG(sgpa) <= ?"; $havingParamsLocal[] = $max_sgpa; }
        $hLocalSql = implode(" AND ", $havingLocal);
        
        $stmtLSgpa = $localDB->prepare("SELECT student_id FROM student_sem_sgpa WHERE sgpa > 0 GROUP BY student_id HAVING $hLocalSql");
        $stmtLSgpa->execute($havingParamsLocal);
        $localIds = $stmtLSgpa->fetchAll(PDO::FETCH_COLUMN);

        $stmtGSgpa = $db->prepare("SELECT usn FROM {$gmuPrefix}ad_student_approved WHERE sgpa > 0 GROUP BY usn HAVING $hLocalSql");
        $stmtGSgpa->execute($havingParamsLocal);
        $gmuIds = $stmtGSgpa->fetchAll(PDO::FETCH_COLUMN);
        
        $qualifyingSgpaIds = array_values(array_unique(array_merge($localIds, $gmuIds)));
        
    } elseif (strpos($sgpa_mode, 'sem_') === 0) {
        // Specific Semester (Sem 1 to 8)
        $semTarget = (int)str_replace('sem_', '', $sgpa_mode);
        $condsL = ["semester = ?", "sgpa > 0"];
        $pL = [$semTarget];
        if ($min_sgpa > 0) { $condsL[] = "sgpa >= ?"; $pL[] = $min_sgpa; }
        if ($max_sgpa > 0) { $condsL[] = "sgpa <= ?"; $pL[] = $max_sgpa; }
        $cSqlL = implode(" AND ", $condsL);

        $stmtLSgpa = $localDB->prepare("SELECT student_id FROM student_sem_sgpa WHERE $cSqlL");
        $stmtLSgpa->execute($pL);
        $localIds = $stmtLSgpa->fetchAll(PDO::FETCH_COLUMN);

        $condsG = ["sem = ?", "sgpa > 0"];
        $pG = [$semTarget];
        if ($min_sgpa > 0) { $condsG[] = "sgpa >= ?"; $pG[] = $min_sgpa; }
        if ($max_sgpa > 0) { $condsG[] = "sgpa <= ?"; $pG[] = $max_sgpa; }
        $cSqlG = implode(" AND ", $condsG);

        $stmtGSgpa = $db->prepare("SELECT usn FROM {$gmuPrefix}ad_student_approved WHERE $cSqlG");
        $stmtGSgpa->execute($pG);
        $gmuIds = $stmtGSgpa->fetchAll(PDO::FETCH_COLUMN);

        $qualifyingSgpaIds = array_values(array_unique(array_merge($localIds, $gmuIds)));

        // DIPLOMA / LATERAL ENTRY CONSIDERATION:
        // Diploma students start directly in 2nd year (Sem 3) and have NO Sem 1 or Sem 2 SGPA.
        // When filtering by Sem 1 or Sem 2, evaluate them on their starting degree semester (Sem 3 SGPA) or Diploma aggregate percentage!
        if ($semTarget === 1 || $semTarget === 2) {
            // 1. GMU Diploma students whose Sem 3 SGPA meets criteria
            $condsDipG = ["cur.sem = 3", "cur.sgpa > 0"];
            $pDipG = [];
            if ($min_sgpa > 0) { $condsDipG[] = "cur.sgpa >= ?"; $pDipG[] = $min_sgpa; }
            if ($max_sgpa > 0) { $condsDipG[] = "cur.sgpa <= ?"; $pDipG[] = $max_sgpa; }
            $cSqlDipG = implode(" AND ", $condsDipG);

            $stmtDipG = $db->prepare("
                SELECT DISTINCT cur.usn 
                FROM {$gmuPrefix}ad_student_approved cur
                LEFT JOIN {$gmuPrefix}ad_student_details det ON cur.usn = det.usn
                WHERE (det.regular_lateral = 'LATERAL' OR cur.usn NOT IN (
                    SELECT DISTINCT usn FROM {$gmuPrefix}ad_student_approved WHERE sem IN (1, 2) AND sgpa > 0
                ))
                AND $cSqlDipG
            ");
            $stmtDipG->execute($pDipG);
            $dipGmuIds = $stmtDipG->fetchAll(PDO::FETCH_COLUMN);

            // 2. GMIT / Local Diploma students whose Sem 3 SGPA in student_sem_sgpa meets criteria
            $condsDipL = ["s.semester = 3", "s.sgpa > 0"];
            $pDipL = [];
            if ($min_sgpa > 0) { $condsDipL[] = "s.sgpa >= ?"; $pDipL[] = $min_sgpa; }
            if ($max_sgpa > 0) { $condsDipL[] = "s.sgpa <= ?"; $pDipL[] = $max_sgpa; }
            $cSqlDipL = implode(" AND ", $condsDipL);

            $stmtDipL = $localDB->prepare("
                SELECT DISTINCT s.student_id 
                FROM student_sem_sgpa s
                WHERE s.student_id NOT IN (
                    SELECT DISTINCT student_id FROM student_sem_sgpa WHERE semester IN (1, 2) AND sgpa > 0
                )
                AND $cSqlDipL
            ");
            $stmtDipL->execute($pDipL);
            $dipLocIds = $stmtDipL->fetchAll(PDO::FETCH_COLUMN);

            // 3. Also check Diploma percentage in puc_percentage for lateral students (e.g. 7.5 SGPA ~ 75%)
            if ($min_sgpa > 0) {
                $minPct = $min_sgpa * 10;
                $maxPct = ($max_sgpa > 0) ? $max_sgpa * 10 : 100;
                $stmtDipPctGmu = $db->prepare("
                    SELECT usn FROM {$gmuPrefix}ad_student_details 
                    WHERE regular_lateral = 'LATERAL' AND puc_percentage >= ? AND puc_percentage <= ?
                ");
                $stmtDipPctGmu->execute([$minPct, $maxPct]);
                $dipPctGmuIds = $stmtDipPctGmu->fetchAll(PDO::FETCH_COLUMN);

                $db_gmit = getDB('gmit');
                $dipPctGmitIds = [];
                if ($db_gmit) {
                    $stmtDipPctGmit = $db_gmit->prepare("
                        SELECT IFNULL(NULLIF(usn, ''), student_id) FROM ad_student_details 
                        WHERE regular_lateral LIKE '%LATERAL%' AND puc_percentage >= ? AND puc_percentage <= ?
                    ");
                    $stmtDipPctGmit->execute([$minPct, $maxPct]);
                    $dipPctGmitIds = $stmtDipPctGmit->fetchAll(PDO::FETCH_COLUMN);
                }
                $qualifyingSgpaIds = array_values(array_unique(array_merge($qualifyingSgpaIds, $dipPctGmuIds, $dipPctGmitIds)));
            }

            $qualifyingSgpaIds = array_values(array_unique(array_merge($qualifyingSgpaIds, $dipGmuIds, $dipLocIds)));
        }

    } elseif ($sgpa_mode === 'all_sems') {
        // Cleared all available semesters with >= min_sgpa (Diploma students evaluated on their available Sem 3+ semesters)
        $stmtLSgpa = $localDB->prepare("SELECT student_id FROM student_sem_sgpa WHERE sgpa > 0 GROUP BY student_id HAVING MIN(sgpa) >= ?");
        $stmtLSgpa->execute([$min_sgpa]);
        $localIds = $stmtLSgpa->fetchAll(PDO::FETCH_COLUMN);

        $stmtGSgpa = $db->prepare("SELECT usn FROM {$gmuPrefix}ad_student_approved WHERE sgpa > 0 GROUP BY usn HAVING MIN(sgpa) >= ?");
        $stmtGSgpa->execute([$min_sgpa]);
        $gmuIds = $stmtGSgpa->fetchAll(PDO::FETCH_COLUMN);

        $qualifyingSgpaIds = array_values(array_unique(array_merge($localIds, $gmuIds)));

    } else {
        // 'latest' / Current Semester SGPA (Default)
        $condsL = ["s.sgpa > 0"];
        $pL = [];
        if ($min_sgpa > 0) { $condsL[] = "s.sgpa >= ?"; $pL[] = $min_sgpa; }
        if ($max_sgpa > 0) { $condsL[] = "s.sgpa <= ?"; $pL[] = $max_sgpa; }
        $cSqlL = implode(" AND ", $condsL);

        $stmtLSgpa = $localDB->prepare("
            SELECT DISTINCT s.student_id 
            FROM student_sem_sgpa s 
            JOIN (
                SELECT student_id, MAX(semester) as max_sem 
                FROM student_sem_sgpa 
                WHERE sgpa > 0 
                GROUP BY student_id
            ) m ON s.student_id = m.student_id AND s.semester = m.max_sem 
            WHERE $cSqlL
        ");
        $stmtLSgpa->execute($pL);
        $localIds = $stmtLSgpa->fetchAll(PDO::FETCH_COLUMN);

        $condsG = ["cur_rows.sgpa > 0"];
        $pG = [];
        if ($min_sgpa > 0) { $condsG[] = "cur_rows.sgpa >= ?"; $pG[] = $min_sgpa; }
        if ($max_sgpa > 0) { $condsG[] = "cur_rows.sgpa <= ?"; $pG[] = $max_sgpa; }
        $cSqlG = implode(" AND ", $condsG);

        $stmtGSgpa = $db->prepare("
            SELECT DISTINCT cur_rows.usn 
            FROM {$gmuPrefix}ad_student_approved cur_rows 
            JOIN (
                SELECT usn, MAX(sem) as max_sem 
                FROM {$gmuPrefix}ad_student_approved 
                WHERE sgpa > 0 
                GROUP BY usn
            ) cur ON cur_rows.usn = cur.usn AND cur_rows.sem = cur.max_sem 
            WHERE $cSqlG
        ");
        $stmtGSgpa->execute($pG);
        $gmuIds = $stmtGSgpa->fetchAll(PDO::FETCH_COLUMN);

        $qualifyingSgpaIds = array_values(array_unique(array_merge($localIds, $gmuIds)));
    }

    if (!empty($qualifyingSgpaIds)) {
        $expandedSgpaIds = $qualifyingSgpaIds;
        $db_gmit = getDB('gmit');
        if ($db_gmit) {
            $phSg = implode(',', array_fill(0, count($qualifyingSgpaIds), '?'));
            $stmtExp = $db_gmit->prepare("SELECT DISTINCT usn, student_id FROM ad_student_details WHERE student_id IN ($phSg) OR usn IN ($phSg)");
            $stmtExp->execute(array_merge($qualifyingSgpaIds, $qualifyingSgpaIds));
            while ($m = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($m['usn'])) $expandedSgpaIds[] = $m['usn'];
                if (!empty($m['student_id'])) $expandedSgpaIds[] = $m['student_id'];
            }
            $expandedSgpaIds = array_values(array_unique($expandedSgpaIds));
        }
        $phQual = implode(',', array_fill(0, count($expandedSgpaIds), '?'));
        $where_clauses[] = "(asa.usn IN ($phQual) OR asa.student_id_map IN ($phQual))";
        $params = array_merge($params, $expandedSgpaIds, $expandedSgpaIds);
    } else {
        $where_clauses[] = "1=0";
    }
}

// Sorting Order
$orderBySql = "name ASC";
switch ($sort_by) {
    case 'name_desc': $orderBySql = "name DESC"; break;
    case 'usn_asc': $orderBySql = "asa.usn ASC"; break;
    case 'usn_desc': $orderBySql = "asa.usn DESC"; break;
    case 'puc_desc': $orderBySql = "CAST(NULLIF(MAX(asd.puc_percentage), '') AS DECIMAL(5,2)) DESC"; break;
    case 'sslc_desc': $orderBySql = "CAST(NULLIF(MAX(asd.sslc_percentage), '') AS DECIMAL(5,2)) DESC"; break;
    default: $orderBySql = "name ASC"; break;
}

$where_sql = implode(" AND ", $where_clauses);

$count_query = "
    SELECT COUNT(DISTINCT asa.usn) 
    FROM {$combinedApproved} asa
    LEFT JOIN {$combinedDetails} asd ON ( (asa.usn = asd.student_id AND asa.institution = '" . INSTITUTION_GMIT . "') OR (asa.usn = asd.usn AND asa.institution = '" . INSTITUTION_GMU . "') )
    WHERE $where_sql
";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

if (isset($filters['export']) && $section === 'details') {
    SessionFilterHelper::updateFilters($pageId, ['export' => null]);
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="student_details_report_'.date('Y-m-d').'.xls"');
    $query = "
        SELECT asa.usn, MAX(asa.name) as name, asa.aadhar, MAX(asa.faculty) as faculty, MAX(asa.discipline) as discipline,
        MAX(asa.programme) as programme, MAX(asd.puc_percentage) as puc_percentage, MAX(asd.sslc_percentage) as sslc_percentage,
        MAX(asd.student_mobile) as student_mobile, MAX(asd.parent_mobile) as parent_mobile, MAX(asd.father_name) as father_name,
        MAX(asd.mother_name) as mother_name, MAX(asd.email_id) as email_id, MAX(asa.institution) as institution,
        MAX(asa.sem) as sem
        FROM {$combinedApproved} asa
        LEFT JOIN {$combinedDetails} asd ON ( (asa.usn = asd.student_id AND asa.institution = '" . INSTITUTION_GMIT . "') OR (asa.usn = asd.usn AND asa.institution = '" . INSTITUTION_GMU . "') )
        WHERE $where_sql
        GROUP BY asa.usn, asa.aadhar, asa.institution
        ORDER BY $orderBySql
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $all_students = $stmt->fetchAll();
    echo '<table border="1">';
    echo '<tr><th>Institution</th><th>USN</th><th>Name</th><th>Sem</th><th>Aadhar</th><th>Faculty</th><th>Discipline</th><th>Programme</th><th>Father</th><th>Mother</th><th>Parent Mobile</th>';
    echo '<th>Applied Jobs</th>';
    for($i=1; $i<=8; $i++) echo "<th>Sem $i SGPA</th>";
    echo '<th>PUC %</th><th>SSLC %</th><th>Mobile</th><th>Email</th><th>Resume Link</th></tr>';
    foreach($all_students as $s) {
        $sgpaData = getSemesterSGPACoord($db, $localDB, $s['usn'], $s['aadhar'], $s['institution']);
        $appCount = $localDB->prepare("SELECT COUNT(*) FROM job_applications WHERE student_id = ?");
        $appCount->execute([$s['usn']]);
        $count = $appCount->fetchColumn();

        $resumeFile = strtoupper($s['usn']) . '_Resume.pdf';
        $resumePath = UPLOADS_PATH . '/resumes/Student_Resumes/' . $resumeFile;
        $resumeLink = file_exists($resumePath) ? (APP_URL . '/student/view_resume.php?usn=' . urlencode($s['usn'])) : 'No Resume';

        echo "<tr>";
        $currentSem = $s['sem'];
        if ($s['institution'] === INSTITUTION_GMIT) {
            $stmtC = $localDB->prepare("SELECT MAX(semester) FROM student_sem_sgpa WHERE student_id = ? AND institution = ?");
            $stmtC->execute([$s['usn'], INSTITUTION_GMIT]);
            $resC = $stmtC->fetchColumn();
            if ($resC) $currentSem = $resC;
        }
        echo "<td>{$s['institution']}</td><td style=\"mso-number-format:'\@';\">{$s['usn']}</td><td>{$s['name']}</td><td>{$currentSem}</td><td style=\"mso-number-format:'\@';\">{$s['aadhar']}</td><td>{$s['faculty']}</td><td>{$s['discipline']}</td><td>{$s['programme']}</td>";
        echo "<td>{$s['father_name']}</td><td>{$s['mother_name']}</td><td style=\"mso-number-format:'\@';\">{$s['parent_mobile']}</td>";
        echo "<td>{$count}</td>";
        foreach ($sgpaData as $val) echo "<td>" . ($val !== null ? number_format($val, 2) : '-') . "</td>";
        echo "<td>" . ($s['puc_percentage'] ?? '-') . "</td><td>" . ($s['sslc_percentage'] ?? '-') . "</td><td style=\"mso-number-format:'\@';\">{$s['student_mobile']}</td><td>{$s['email_id']}</td><td>" . ($resumeLink !== 'No Resume' ? "<a href=\"" . htmlspecialchars($resumeLink) . "\">" . htmlspecialchars($resumeLink) . "</a>" : "No Resume") . "</td>";
        echo "</tr>";
    }

    echo '</table>';
    exit;
}

$details_query = "
    SELECT asa.usn, MAX(asa.name) as name, asa.aadhar, MAX(asa.faculty) as faculty, MAX(asa.school) as school,
    MAX(asa.programme) as programme, MAX(asa.course) as course, MAX(asa.discipline) as discipline,
    MAX(asd.gender) as gender, MAX(asd.dob) as dob, MAX(asd.student_mobile) as student_mobile, MAX(asd.parent_mobile) as parent_mobile,
    MAX(asd.father_name) as father_name, MAX(asd.mother_name) as mother_name,
    MAX(asd.email_id) as email_id, MAX(asd.puc_percentage) as puc_percentage, MAX(asd.sslc_percentage) as sslc_percentage,
    MAX(asd.category) as category, MAX(asd.district) as district, MAX(asd.taluk) as taluk, MAX(asd.state) as state,
    MAX(asd.regular_lateral) as regular_lateral, asa.institution,
    MAX(asa.sem) as sem
    FROM {$combinedApproved} asa
    LEFT JOIN {$combinedDetails} asd ON ( (asa.usn = asd.student_id AND asa.institution = '" . INSTITUTION_GMIT . "') OR (asa.usn = asd.usn AND asa.institution = '" . INSTITUTION_GMU . "') )
    WHERE $where_sql
    GROUP BY asa.usn, asa.aadhar, asa.institution
    ORDER BY $orderBySql
    LIMIT $limit OFFSET $offset
";

try {
    $stmt = $db->prepare($details_query);
    $stmt->execute($params);
    $detailsStudents = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Query Error: " . $e->getMessage());
    $detailsStudents = [];
}

// Portfolio summary (skills/projects + verified counts) for the students in the current page
$portfolioSummary = []; // key: institution|usn => ['Skill'=>['total'=>0,'verified'=>0], 'Project'=>...]
// Portfolio summary
try {
    if (!empty($detailsStudents)) {
        $stmtCol = $localDB->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_portfolio' AND COLUMN_NAME = 'is_verified'");
        $stmtCol->execute();
        $hasIsVerified = ((int) $stmtCol->fetchColumn() > 0);

        $usns = [];
        $insts = [];
        foreach ($detailsStudents as $s) {
            $u = trim((string) ($s['usn'] ?? ''));
            $i = trim((string) ($s['institution'] ?? ''));
            if ($u !== '' && $i !== '') {
                $usns[$u] = true; $insts[$i] = true;
                $portfolioSummary[$i . '|' . $u] = [
                    'Skill' => ['total' => 0, 'verified' => 0], 
                    'Project' => ['total' => 0, 'verified' => 0],
                    'Certification' => ['total' => 0, 'verified' => 0]
                ];
            }
        }

        if (!empty($usns)) {
            $usnList = array_keys($usns); $instList = array_keys($insts);
            $phUsn = implode(',', array_fill(0, count($usnList), '?'));
            $phInst = implode(',', array_fill(0, count($instList), '?'));
            $verifiedExpr = $hasIsVerified ? "SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END)" : "0";
            $sql = "SELECT student_id, institution, category, COUNT(*) as total_count, {$verifiedExpr} as verified_count 
                    FROM student_portfolio 
                    WHERE institution IN ($phInst) AND student_id IN ($phUsn) 
                      AND category IN ('Skill','Project','Certification') 
                    GROUP BY student_id, institution, category";
            $stmtP = $localDB->prepare($sql);
            $stmtP->execute(array_merge($instList, $usnList));
            while ($r = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                $cat = $r['category'];
                $portfolioSummary[$r['institution'] . '|' . $r['student_id']][$cat] = ['total' => (int)$r['total_count'], 'verified' => (int)$r['verified_count']];
            }
        }
    }
} catch (Exception $e) {}

// Freeze Statuses — from student_sem_sgpa.freezed column
$freezeStatuses = [];
try {
    if (!empty($detailsStudents)) {
        $usnList = array_map(function($s) { return $s['usn']; }, $detailsStudents);
        $phUsn = implode(',', array_fill(0, count($usnList), '?'));
        $sqlFreeze = "SELECT student_id, institution, MAX(freezed) as is_frozen FROM student_sem_sgpa WHERE student_id IN ($phUsn) GROUP BY student_id, institution";
        $stmtFreeze = $localDB->prepare($sqlFreeze);
        $stmtFreeze->execute($usnList);
        while ($r = $stmtFreeze->fetch(PDO::FETCH_ASSOC)) {
            $freezeStatuses[$r['institution'] . '|' . $r['student_id']] = (int)$r['is_frozen'];
        }
    }
} catch (Exception $e) {}

// Job Application Details
$applicationInfo = []; // key: USN => [['company' => '...', 'title' => '...'], ...]
$applicationCounts = [];
try {
    if (!empty($detailsStudents)) {
        $usnList = array_map(function($s) { return $s['usn']; }, $detailsStudents);
        $placeholders = implode(',', array_fill(0, count($usnList), '?'));
        $sqlApps = "SELECT ja.student_id, jp.title as job_title, c.name as company_name, ja.status, ja.applied_at 
                    FROM job_applications ja 
                    JOIN job_postings jp ON ja.job_id = jp.id 
                    JOIN companies c ON jp.company_id = c.id 
                    WHERE ja.student_id IN ($placeholders)";
        $stmtApps = $localDB->prepare($sqlApps);
        $stmtApps->execute($usnList);
        while ($r = $stmtApps->fetch(PDO::FETCH_ASSOC)) {
            $sid = $r['student_id'];
            if (!isset($applicationInfo[$sid])) {
                $applicationInfo[$sid] = [];
                $applicationCounts[$sid] = 0;
            }
            $applicationInfo[$sid][] = [
                'company' => $r['company_name'],
                'title' => $r['job_title'],
                'status' => $r['status'],
                'date' => date('d M Y', strtotime($r['applied_at']))
            ];
            $applicationCounts[$sid]++;
        }
    }
} catch (Exception $e) {}
// Bulk fetch current semesters for GMIT
$gmitCurrentSemsMap = [];
$bulkSgpaMap = [];
if (!empty($detailsStudents)) {
    $searchIds = [];
    $usnListAll = [];
    foreach ($detailsStudents as $s) {
        if (!empty($s['usn'])) {
            $usnListAll[] = $s['usn'];
            $searchIds[] = $s['usn'];
        }
        if (!empty($s['aadhar'])) {
            $searchIds[] = $s['aadhar'];
        }
    }
    if (!empty($searchIds)) {
        $searchIds = array_values(array_unique($searchIds));
        $ph = implode(',', array_fill(0, count($searchIds), '?'));
        
        // 1. Current semester for GMIT
        $stmtC = $localDB->prepare("SELECT student_id, MAX(semester) FROM student_sem_sgpa WHERE institution = ? AND student_id IN ($ph) GROUP BY student_id");
        $stmtC->execute(array_merge([INSTITUTION_GMIT], $searchIds));
        $gmitCurrentSemsMap = $stmtC->fetchAll(PDO::FETCH_KEY_PAIR);

        // 2. High-Performance Bulk Pre-fetch all 8 Semesters of SGPA from Local DB
        try {
            $stmtBulkLocal = $localDB->prepare("
                SELECT student_id, semester, sgpa 
                FROM student_sem_sgpa 
                WHERE student_id IN ($ph) AND sgpa > 0
            ");
            $stmtBulkLocal->execute($searchIds);
            while ($r = $stmtBulkLocal->fetch(PDO::FETCH_ASSOC)) {
                $sid = $r['student_id'];
                $sem = (int)$r['semester'];
                if ($sem >= 1 && $sem <= 8) {
                    if (!isset($bulkSgpaMap[$sid])) $bulkSgpaMap[$sid] = array_fill(1, 8, null);
                    $bulkSgpaMap[$sid][$sem] = (float)$r['sgpa'];
                }
            }
        } catch (Exception $e) {}

        // 3. High-Performance Bulk Pre-fetch from GMU remote DB for any missing values
        if ($inst !== 'gmit') {
            try {
                $stmtBulkGmu = $db->prepare("
                    SELECT usn, sem, sgpa 
                    FROM " . DB_GMU_PREFIX . "ad_student_approved 
                    WHERE usn IN ($ph) AND sem BETWEEN 1 AND 8 AND sgpa > 0
                ");
                $stmtBulkGmu->execute($searchIds);
                while ($r = $stmtBulkGmu->fetch(PDO::FETCH_ASSOC)) {
                    $u = $r['usn'];
                    $sem = (int)$r['sem'];
                    if (!isset($bulkSgpaMap[$u])) $bulkSgpaMap[$u] = array_fill(1, 8, null);
                    if ($bulkSgpaMap[$u][$sem] === null) {
                        $bulkSgpaMap[$u][$sem] = (float)$r['sgpa'];
                    }
                }
            } catch (Exception $e) {}
        }
    }
}

function getSemesterSGPACoord($remoteDB, $localDB, $usn, $aadhar, $institution) {
    $sgpaData = array_fill(1, 8, null);
    try {
        // 1. Check local student_sem_sgpa (for both GMIT and GMU coordinator updates)
        $stmt = $localDB->prepare("SELECT semester, sgpa FROM student_sem_sgpa WHERE (student_id = ? OR student_id = ?) AND (institution = ? OR institution IS NULL) ORDER BY semester");
        $stmt->execute([$usn, $aadhar, $institution]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $s = (int)$row['semester'];
            if ($s >= 1 && $s <= 8 && $row['sgpa'] !== null && (float)$row['sgpa'] > 0) {
                $sgpaData[$s] = (float)$row['sgpa'];
            }
        }
    } catch (Exception $e) { }

    // 2. If GMU, fill in any missing semesters from remote ad_student_approved
    if ($institution === INSTITUTION_GMU) {
        $prefix = DB_GMU_PREFIX;
        try {
            $stmt = $remoteDB->prepare("SELECT sem, sgpa FROM {$prefix}ad_student_approved WHERE usn = ? ORDER BY sem");
            $stmt->execute([$usn]);
            while ($row = $stmt->fetch()) {
                $s = (int)$row['sem'];
                if ($s >= 1 && $s <= 8 && $sgpaData[$s] === null && $row['sgpa'] !== null && (float)$row['sgpa'] > 0) {
                    $sgpaData[$s] = (float)$row['sgpa'];
                }
            }
        } catch (PDOException $e) {}
    }
    return $sgpaData;
}

function buildSectionUrl($s, $i) {
    return "students_report.php?section=$s&inst=$i";
}

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students & Reports — <?php echo htmlspecialchars($deptLabel); ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --bg-body: #f8fafc;
            --maroon-tint: #faf4f4;
            --maroon-tint-border: #eddcdc;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        .main-content {
            /* Layout handled by navbar.php */
        }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px; margin-bottom: 22px; }
        .page-title { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; color: var(--primary-maroon); margin-bottom: 4px; }
        .page-subtitle { font-size: 13px; color: var(--text-muted); }

        /* Institution segmented control */
        .tabs-inst { display: inline-flex; gap: 2px; background: #fff; border: 1px solid var(--border-light); border-radius: 10px; padding: 4px; box-shadow: 0 1px 2px rgba(15,23,42,0.05); }
        .tab-inst { padding: 7px 20px; border: none; background: transparent; border-radius: 7px; font-family: inherit; font-size: 12.5px; font-weight: 700; color: var(--text-muted); cursor: pointer; transition: all 0.15s ease; }
        .tab-inst:hover { color: var(--primary-maroon); background: var(--maroon-tint); }
        .tab-inst.active { background: var(--primary-maroon); color: #fff; box-shadow: 0 2px 8px rgba(128,0,0,0.28); }
        .tab-inst:focus-visible { outline: 2px solid var(--primary-maroon); outline-offset: 2px; }

        /* Filter toolbar */
        .filter-section { background: white; border: 1px solid var(--border-light); border-radius: 14px; padding: 18px 20px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(15,23,42,0.04); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; align-items: end; }
        .filter-item { display: flex; flex-direction: column; gap: 5px; position: relative; min-width: 0; }
        .filter-item.col-span-2 { grid-column: span 2; }
        .filter-item.col-span-3 { grid-column: span 3; }
        .filter-item label { font-size: 10.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }
        .filter-item input, .filter-item select { height: 36px; padding: 0 10px; border: 1px solid var(--border-light); border-radius: 8px; font-size: 12.5px; font-family: inherit; color: var(--text-dark); background-color: #fff; width: 100%; min-width: 0; transition: border-color 0.15s, box-shadow 0.15s; }
        .filter-item select { cursor: pointer; appearance: none; -webkit-appearance: none; padding-right: 28px; }
        .filter-item input:focus, .filter-item select:focus { border-color: var(--primary-maroon); outline: none; box-shadow: 0 0 0 3px rgba(128,0,0,0.08); }
        .filter-item i.filter-icon { position: absolute; right: 10px; bottom: 12px; color: var(--text-muted); pointer-events: none; font-size: 10px; }
        .filter-actions-bar { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding-top: 12px; margin-top: 4px; border-top: 1px solid #f1f5f9; }
        .filter-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        /* Active filter chips */
        .filter-chip { display: inline-flex; align-items: center; gap: 5px; background: var(--maroon-tint); color: var(--primary-maroon); border: 1px solid var(--maroon-tint-border); padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }

        /* Table card */
        .table-card { background: white; border: 1px solid var(--border-light); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(15,23,42,0.04); }
        .results-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-bottom: 1px solid var(--border-light); font-size: 12.5px; color: var(--text-muted); }
        .results-bar b { color: var(--text-dark); font-weight: 700; }
        .table-wrap { overflow: auto; max-height: 68vh; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 11.5px; }
        thead th { position: sticky; top: 0; z-index: 5; background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.04em; padding: 10px 8px; border-bottom: 1px solid var(--border-light); text-align: left; white-space: nowrap; }
        td { padding: 9px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tbody tr:nth-child(even) td { background: #fcfcfd; }
        tbody tr:hover td { background: var(--maroon-tint); }

        /* Compact Data Styles */
        .student-name { font-weight: 700; color: #111; display: block; white-space: nowrap; }
        .usn-text { color: var(--text-muted); font-family: Consolas, 'SFMono-Regular', monospace; font-size: 10.5px; letter-spacing: 0.02em; }
        .sgpa-col { text-align: center; font-weight: 700; color: var(--primary-maroon); width: 40px; }
        .score-box { font-weight: 700; color: #059669; }
        .badge-simple { display: inline-block; font-size: 9.5px; font-weight: 700; padding: 2px 7px; border-radius: 20px; background: #f1f5f9; color: #475569; letter-spacing: 0.03em; }
        .badge-inst-gmu { background: var(--maroon-tint); color: var(--primary-maroon); border: 1px solid var(--maroon-tint-border); }
        .badge-inst-gmit { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }

        /* Buttons */
        .btn-simple { height: 36px; padding: 0 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; font-family: inherit; cursor: pointer; border: 1px solid var(--border-light); background: white; color: var(--text-dark); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease; white-space: nowrap; }
        .btn-simple:hover { border-color: #cbd5e1; background: #f8fafc; }
        .btn-maroon { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }
        .btn-maroon:hover { background: #690000; border-color: #690000; }

        .btn-view { padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border-light); background: white; cursor: pointer; color: var(--text-muted); font-size: 11px; font-weight: 600; font-family: inherit; transition: all 0.2s; }
        .btn-view:hover { border-color: var(--primary-maroon); color: var(--primary-maroon); background: var(--maroon-tint); }
        .btn-view.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }

        /* Pagination & Meta */
        .page-meta { padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 12.5px; color: var(--text-muted); border-top: 1px solid var(--border-light); background: #fff; }
        .pagination { display: flex; gap: 4px; }
        .page-link { min-width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; padding: 0 9px; border: 1px solid var(--border-light); border-radius: 7px; text-decoration: none; color: var(--text-dark); background: white; font-size: 12px; font-weight: 600; transition: all 0.15s; }
        .page-link:hover { border-color: var(--primary-maroon); color: var(--primary-maroon); }
        .page-link.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }

        /* Toast notifications */
        .toast-stack { position: fixed; bottom: 22px; right: 22px; display: flex; flex-direction: column; gap: 8px; z-index: 3000; }
        .toast { background: #1e293b; color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 12.5px; font-weight: 600; box-shadow: 0 10px 25px rgba(15,23,42,0.25); display: flex; align-items: center; gap: 8px; animation: toastIn 0.25s ease; }
        .toast.success { background: #065f46; }
        .toast.error { background: #991b1b; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            .toast { animation: none; }
            .tab-inst, .btn-simple, .btn-view, .page-link { transition: none; }
        }

        @media (max-width: 900px) {
            .filter-item.col-span-2, .filter-item.col-span-3 { grid-column: span 1; }
        }
        
        /* Modal Professional */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 2000; animation: fadeIn 0.3s; }
        .modal-content { background: white; width: 95%; max-width: 700px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; display: flex; flex-direction: column; max-height: 90vh; border: 1px solid var(--border-light); }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-light); }
        .modal-body { padding: 0 24px 24px; overflow-y: auto; flex: 1; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
        .modal-body::-webkit-scrollbar { width: 6px; }
        .modal-body::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
        .close-modal { position: absolute; top: 20px; right: 24px; cursor: pointer; font-size: 24px; color: var(--text-muted); border: none; background: none; transition: 0.2s; z-index: 10; }
        .close-modal:hover { color: var(--primary-maroon); transform: scale(1.1); }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Inline Edit Styles */
        .editable { cursor: pointer; border-radius: 4px; transition: all 0.2s; position: relative; }
        .editable:hover { background: #fff7ed; outline: 1px dashed var(--primary-maroon); }
        .editable:focus { background: white; outline: 2px solid var(--primary-maroon); padding: 2px 4px; box-shadow: 0 0 8px rgba(128,0,0,0.1); }
        .editable-saving { opacity: 0.5; pointer-events: none; }
        .editable-success { background: #dcfce7 !important; outline: none !important; }
        .editable-error { background: #fee2e2 !important; outline: 2px solid #ef4444 !important; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Students &amp; Reports</h1>
                <p class="page-subtitle">Managing data for <strong><?php echo htmlspecialchars($deptLabel); ?></strong></p>
            </div>
            <!-- Institution tabs: All | GMU | GMIT -->
            <div class="tabs-inst" role="tablist" aria-label="Institution">
                <form method="POST" style="display: contents;">
                    <input type="hidden" name="inst" value="all">
                    <button type="submit" class="tab-inst <?php echo $inst === 'all' ? 'active' : ''; ?>">All Institutions</button>
                </form>
                <form method="POST" style="display: contents;">
                    <input type="hidden" name="inst" value="gmu">
                    <button type="submit" class="tab-inst <?php echo $inst === 'gmu' ? 'active' : ''; ?>">GMU</button>
                </form>
                <form method="POST" style="display: contents;">
                    <input type="hidden" name="inst" value="gmit">
                    <button type="submit" class="tab-inst <?php echo $inst === 'gmit' ? 'active' : ''; ?>">GMIT</button>
                </form>
            </div>
        </div>

        <div id="panel-details">
            <div class="filter-section">
                <form method="POST" class="filter-grid" id="filterForm">
                    <input type="hidden" name="section" value="details">
                    <input type="hidden" name="inst" value="<?php echo htmlspecialchars($inst); ?>">
                    
                    <!-- Search Field -->
                    <div class="filter-item col-span-2">
                        <label for="f-search"><i class="fas fa-search"></i> Search Student</label>
                        <input type="text" id="f-search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="USN, Name, Aadhar, Mobile, Email...">
                    </div>
                    
                    <!-- SGPA Filter Mode -->
                    <div class="filter-item">
                        <label for="f-sgpa-mode"><i class="fas fa-calculator"></i> SGPA Mode</label>
                        <select id="f-sgpa-mode" name="sgpa_mode" onchange="this.form.submit()">
                            <option value="latest" <?php echo $sgpa_mode === 'latest' ? 'selected' : ''; ?>>Current Sem</option>
                            <option value="avg" <?php echo $sgpa_mode === 'avg' ? 'selected' : ''; ?>>Average CGPA</option>
                            <option value="all_sems" <?php echo $sgpa_mode === 'all_sems' ? 'selected' : ''; ?>>All Sems &ge; Min</option>
                            <optgroup label="Specific Semester">
                                <?php for($s=1; $s<=8; $s++): ?>
                                    <option value="sem_<?php echo $s; ?>" <?php echo $sgpa_mode === 'sem_' . $s ? 'selected' : ''; ?>>
                                        Sem <?php echo $s; ?> SGPA<?php echo ($s <= 2) ? ' (Sem 3 for Diploma)' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </optgroup>
                        </select>
                        <i class="fas fa-chevron-down filter-icon"></i>
                    </div>

                    <!-- Min SGPA -->
                    <div class="filter-item">
                        <label for="f-sgpa"><i class="fas fa-arrow-up-wide-short"></i> Min SGPA</label>
                        <input type="number" id="f-sgpa" name="min_sgpa" value="<?php echo $min_sgpa > 0 ? htmlspecialchars($min_sgpa) : ''; ?>" step="0.01" min="0" max="10" placeholder="e.g. 7.5">
                    </div>

                    <!-- Max SGPA -->
                    <div class="filter-item">
                        <label for="f-max-sgpa"><i class="fas fa-arrow-down-wide-short"></i> Max SGPA</label>
                        <input type="number" id="f-max-sgpa" name="max_sgpa" value="<?php echo $max_sgpa > 0 ? htmlspecialchars($max_sgpa) : ''; ?>" step="0.01" min="0" max="10" placeholder="e.g. 10.0">
                    </div>

                    <!-- Semester -->
                    <div class="filter-item">
                        <label for="f-sem"><i class="fas fa-graduation-cap"></i> Semester</label>
                        <select id="f-sem" name="sem" onchange="this.form.submit()">
                            <option value="">All Semesters</option>
                            <?php foreach (getCoordinatorSemesterFilters($department) as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $sem_filter_val === (int)$s ? 'selected' : ''; ?>>
                                    Semester <?php echo $s; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down filter-icon"></i>
                    </div>

                    <!-- Branch -->
                    <?php if (count($available_branches) > 1): ?>
                    <div class="filter-item">
                        <label for="f-branch"><i class="fas fa-code-branch"></i> Branch</label>
                        <select id="f-branch" name="branch" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            <?php foreach ($available_branches as $ab): ?>
                                <option value="<?php echo htmlspecialchars($ab); ?>" <?php echo $branch_filter_val === $ab ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ab); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down filter-icon"></i>
                    </div>
                    <?php endif; ?>

                    <!-- Min PUC / 12th % -->
                    <div class="filter-item">
                        <label for="f-puc"><i class="fas fa-percent"></i> Min 12th%</label>
                        <input type="number" id="f-puc" name="min_puc" value="<?php echo $min_puc > 0 ? htmlspecialchars($min_puc) : ''; ?>" step="0.1" min="0" max="100" placeholder="e.g. 60">
                    </div>

                    <!-- Min SSLC / 10th % -->
                    <div class="filter-item">
                        <label for="f-sslc"><i class="fas fa-percent"></i> Min 10th%</label>
                        <input type="number" id="f-sslc" name="min_sslc" value="<?php echo $min_sslc > 0 ? htmlspecialchars($min_sslc) : ''; ?>" step="0.1" min="0" max="100" placeholder="e.g. 60">
                    </div>

                    <!-- Gender -->
                    <div class="filter-item">
                        <label for="f-gender"><i class="fas fa-venus-mars"></i> Gender</label>
                        <select id="f-gender" name="gender" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="M" <?php echo $gender_filter === 'M' ? 'selected' : ''; ?>>Male</option>
                            <option value="F" <?php echo $gender_filter === 'F' ? 'selected' : ''; ?>>Female</option>
                        </select>
                        <i class="fas fa-chevron-down filter-icon"></i>
                    </div>

                    <!-- Portfolio Verification -->
                    <div class="filter-item">
                        <label for="f-portfolio"><i class="fas fa-certificate"></i> Portfolio</label>
                        <select id="f-portfolio" name="portfolio_status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="verified" <?php echo $portfolio_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="unverified" <?php echo $portfolio_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        </select>
                        <i class="fas fa-chevron-down filter-icon"></i>
                    </div>

                    <!-- Freeze Status -->
                    <div class="filter-item">
                        <label for="f-freeze"><i class="fas fa-lock"></i> Freeze Status</label>
                        <select id="f-freeze" name="freeze_status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="frozen" <?php echo $freeze_filter === 'frozen' ? 'selected' : ''; ?>>Frozen</option>
                            <option value="active" <?php echo $freeze_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        </select>
                        <i class="fas fa-chevron-down filter-icon"></i>
                    </div>

                    <!-- Sort By -->
                    <div class="filter-item">
                        <label for="f-sort"><i class="fas fa-sort"></i> Sort Order</label>
                        <select id="f-sort" name="sort_by" onchange="this.form.submit()">
                            <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Name (A &rarr; Z)</option>
                            <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Name (Z &rarr; A)</option>
                            <option value="usn_asc" <?php echo $sort_by === 'usn_asc' ? 'selected' : ''; ?>>USN (Asc)</option>
                            <option value="usn_desc" <?php echo $sort_by === 'usn_desc' ? 'selected' : ''; ?>>USN (Desc)</option>
                            <option value="puc_desc" <?php echo $sort_by === 'puc_desc' ? 'selected' : ''; ?>>12th % (High)</option>
                            <option value="sslc_desc" <?php echo $sort_by === 'sslc_desc' ? 'selected' : ''; ?>>10th % (High)</option>
                        </select>
                        <i class="fas fa-chevron-down filter-icon"></i>
                    </div>

                    <!-- Bottom Actions Bar -->
                    <div class="filter-actions-bar">
                        <div class="filter-actions">
                            <button type="submit" class="btn-simple btn-maroon"><i class="fas fa-filter"></i> Apply Filters</button>
                            <button type="submit" name="reset_filters" value="1" class="btn-simple" title="Clear all filters">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                        <div class="filter-actions">
                            <?php if ($inst !== 'gmu'): ?>
                            <button type="button" class="btn-simple" onclick="freezeAllStudents()" style="background:#fff1f2; color:#be123c; border-color:#fda4af;">
                                <i class="fas fa-lock"></i> Freeze All
                            </button>
                            <button type="button" class="btn-simple" onclick="unfreezeAllStudents()" style="background:#ecfdf5; color:#059669; border-color:#6ee7b7;">
                                <i class="fas fa-lock-open"></i> Unfreeze All
                            </button>
                            <?php endif; ?>
                            <button type="submit" name="export" value="1" class="btn-simple">
                                <i class="fas fa-file-excel" style="color:#059669;"></i> Export Excel
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php
                $showFrom = $total_records > 0 ? $offset + 1 : 0;
                $showTo = min($offset + $limit, $total_records);
                $loadTimeMs = isset($pageStartTime) ? round((microtime(true) - $pageStartTime) * 1000, 1) : 0;
                $activeChips = [];
                if ($search !== '') $activeChips[] = ['icon' => 'fa-magnifying-glass', 'label' => 'Search: "' . $search . '"'];
                if ($min_sgpa > 0 || $max_sgpa > 0) {
                    $modeLabel = ($sgpa_mode === 'avg') ? 'CGPA' : (($sgpa_mode === 'all_sems') ? 'All Sems' : (strpos($sgpa_mode, 'sem_') === 0 ? 'Sem ' . str_replace('sem_', '', $sgpa_mode) : 'SGPA'));
                    if ($min_sgpa > 0 && $max_sgpa > 0) {
                        $activeChips[] = ['icon' => 'fa-calculator', 'label' => $modeLabel . ': ' . $min_sgpa . ' - ' . $max_sgpa];
                    } elseif ($min_sgpa > 0) {
                        $activeChips[] = ['icon' => 'fa-arrow-up-short-wide', 'label' => $modeLabel . ' >= ' . $min_sgpa];
                    } else {
                        $activeChips[] = ['icon' => 'fa-arrow-down-short-wide', 'label' => $modeLabel . ' <= ' . $max_sgpa];
                    }
                }
                if ($sem_filter_val > 0) $activeChips[] = ['icon' => 'fa-graduation-cap', 'label' => 'Semester ' . $sem_filter_val];
                if ($branch_filter_val !== '' && in_array($branch_filter_val, $available_branches)) $activeChips[] = ['icon' => 'fa-code-branch', 'label' => $branch_filter_val];
                if ($min_puc > 0) $activeChips[] = ['icon' => 'fa-percent', 'label' => '12th >= ' . $min_puc . '%'];
                if ($min_sslc > 0) $activeChips[] = ['icon' => 'fa-percent', 'label' => '10th >= ' . $min_sslc . '%'];
                if ($gender_filter === 'M') $activeChips[] = ['icon' => 'fa-mars', 'label' => 'Male'];
                if ($gender_filter === 'F') $activeChips[] = ['icon' => 'fa-venus', 'label' => 'Female'];
                if ($portfolio_filter === 'verified') $activeChips[] = ['icon' => 'fa-certificate', 'label' => 'Verified Portfolio'];
                if ($portfolio_filter === 'unverified') $activeChips[] = ['icon' => 'fa-clock', 'label' => 'Unverified Portfolio'];
                if ($freeze_filter === 'frozen') $activeChips[] = ['icon' => 'fa-lock', 'label' => 'Frozen Records'];
                if ($freeze_filter === 'active') $activeChips[] = ['icon' => 'fa-lock-open', 'label' => 'Active Records'];
                if ($sort_by !== 'name_asc') $activeChips[] = ['icon' => 'fa-sort', 'label' => 'Sorted: ' . $sort_by];
            ?>
            <div class="table-card">
                <div class="results-bar">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span>Showing <b><?php echo $showFrom; ?>&ndash;<?php echo $showTo; ?></b> of <b><?php echo $total_records; ?></b> students</span>
                        <span class="badge-simple" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569; font-size:11px; font-weight:600;" title="Execution load time">
                            <i class="fas fa-bolt" style="color:#eab308; margin-right:3px;"></i> <?php echo $loadTimeMs; ?> ms
                        </span>
                        <?php foreach ($activeChips as $chip): ?>
                            <span class="filter-chip"><i class="fas <?php echo $chip['icon']; ?>"></i> <?php echo htmlspecialchars($chip['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <span class="badge-simple <?php echo $inst === 'gmit' ? 'badge-inst-gmit' : ($inst === 'gmu' ? 'badge-inst-gmu' : ''); ?>" style="font-size:10.5px; padding:4px 10px;">
                        <?php echo $inst === 'all' ? 'GMU + GMIT' : strtoupper($inst); ?>
                    </span>
                </div>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th>Student USN & Full Name</th>
                            <th>Branch</th>
                            <th>Sem</th>
                            <th>Gender</th>
                            <th>Institution</th>
                            <th>SGPA Freeze</th>
                            <?php for($i=1; $i<=8; $i++) echo "<th class='sgpa-col'>Sem $i</th>"; ?>
                            <th>12<sup>th</sup></th>
                            <th>10<sup>th</sup></th>
                            <th>Father Name</th>
                            <th>Parent Mobile</th>
                            <th>Student Mobile</th>
                            <th>Applied Jobs</th>
                            <th>Skills & Projects</th>
                        </tr>
                    </thead>
                    <tbody>

                            <?php foreach ($detailsStudents as $index => $student):
                                $uKey = $student['usn'] ?? '';
                                $aKey = $student['aadhar'] ?? '';
                                $sgpaData = $bulkSgpaMap[$uKey] ?? ($aKey && isset($bulkSgpaMap[$aKey]) ? $bulkSgpaMap[$aKey] : array_fill(1, 8, null));
                                $rowNum = $offset + $index + 1;
                                $pKey = ($student['institution'] ?? '') . '|' . ($student['usn'] ?? '');
                                $pSkill = $portfolioSummary[$pKey]['Skill'] ?? ['total' => 0, 'verified' => 0];
                                $pProj = $portfolioSummary[$pKey]['Project'] ?? ['total' => 0, 'verified' => 0];
                                $appCount = $applicationCounts[$student['usn']] ?? 0;
                                $isDiploma = (stripos($student['regular_lateral'] ?? '', 'LATERAL') !== false) 
                                             || ($sgpaData[1] === null && $sgpaData[2] === null && ($sgpaData[3] !== null || $sgpaData[4] !== null || $sgpaData[5] !== null));
                            ?>
                            <tr data-usn="<?php echo htmlspecialchars($student['usn']); ?>" data-inst="<?php echo htmlspecialchars($student['institution']); ?>">
                                <td style="text-align: center; color: #999;"><?php echo $rowNum; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="student-name editable" data-field="name" contenteditable="true"><?php echo htmlspecialchars($student['name']); ?></span>
                                        <i class="fas fa-address-card" style="color: var(--primary-maroon); cursor: pointer; font-size: 13px; opacity: 0.8;"
                                           onclick="openPortfolio('<?php echo htmlspecialchars($student['usn']); ?>','<?php echo htmlspecialchars($student['institution']); ?>','<?php echo htmlspecialchars($student['name']); ?>', 'AI')"
                                           title="View Portfolio & AI Reports"></i>
                                    </div>
                                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 2px;">
                                        <span class="usn-text"><?php echo htmlspecialchars($student['usn']); ?></span>
                                        <?php if ($isDiploma): ?>
                                            <span class="badge-simple" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:9.5px; padding:1px 5px; font-weight:700; border-radius:3px;" title="Diploma / Lateral Entry (Admitted directly to Sem 3)">Diploma</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-size: 11px; font-weight: 600; color: #475569;">
                                    <?php echo htmlspecialchars($student['discipline'] ?? '-'); ?>
                                </td>
                                <td>
                                    <?php 
                                        $displaySem = $student['sem'];
                                        if ($student['institution'] === INSTITUTION_GMIT) {
                                            $u = $student['usn'];
                                            $a = $student['aadhar'];
                                            if (isset($gmitCurrentSemsMap[$u])) {
                                                $displaySem = $gmitCurrentSemsMap[$u];
                                            } elseif (isset($gmitCurrentSemsMap[$a])) {
                                                $displaySem = $gmitCurrentSemsMap[$a];
                                            }
                                        }
                                        echo "" . ($displaySem ?: '-');
                                    ?>
                                </td>
                                <td><span class="editable" data-field="gender" contenteditable="true"><?php echo substr($student['gender'] ?? '-', 0, 1); ?></span></td>
                                <td><span class="badge-simple badge-inst-<?php echo strtolower($student['institution']); ?>"><?php echo $student['institution']; ?></span></td>
                                
                                <td style="text-align: center;">
                                    <?php $isFrozen = $freezeStatuses[$pKey] ?? 0; ?>
                                    <?php if ($student['institution'] === INSTITUTION_GMIT): ?>
                                        <?php if ($isFrozen): ?>
                                            <button class="btn-view" style="color: #059669; border-color: #059669; background: #ecfdf5; padding: 2px 6px; font-size:10px;" onclick="toggleFreeze('<?php echo htmlspecialchars($student['usn']); ?>', '<?php echo htmlspecialchars($student['institution']); ?>', 0, this)">
                                                <i class="fas fa-lock"></i> Frozen
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-view" style="color: #94a3b8; padding: 2px 6px; font-size:10px;" onclick="toggleFreeze('<?php echo htmlspecialchars($student['usn']); ?>', '<?php echo htmlspecialchars($student['institution']); ?>', 1, this)">
                                                <i class="fas fa-lock-open"></i> Active
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1; font-size:10px;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php for($i=1; $i<=8; $i++): ?>
                                    <td class="sgpa-col">
                                        <?php if ($isDiploma && $i <= 2 && $sgpaData[$i] === null): ?>
                                            <span class="editable" data-field="sem_<?php echo $i; ?>" contenteditable="true" data-old-value="-" title="Diploma / Lateral Entry (Direct entry to Sem 3, no Sem <?php echo $i; ?>)" style="color:#94a3b8; font-style:italic;">
                                                -
                                            </span>
                                        <?php else: ?>
                                            <span class="editable" data-field="sem_<?php echo $i; ?>" contenteditable="true" data-old-value="<?php echo $sgpaData[$i] !== null ? number_format($sgpaData[$i], 1) : '-'; ?>">
                                                <?php echo $sgpaData[$i] !== null ? number_format($sgpaData[$i], 1) : '-'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>

                                <td><span class="score-box editable" data-field="puc_percentage" contenteditable="true" data-old-value="<?php echo $student['puc_percentage'] ? round($student['puc_percentage']).'%' : '-'; ?>"><?php echo $student['puc_percentage'] ? round($student['puc_percentage']).'%' : '-'; ?></span></td>
                                <td><span class="score-box editable" data-field="sslc_percentage" contenteditable="true" data-old-value="<?php echo $student['sslc_percentage'] ? round($student['sslc_percentage']).'%' : '-'; ?>"><?php echo $student['sslc_percentage'] ? round($student['sslc_percentage']).'%' : '-'; ?></span></td>
                                
                                <td style="font-size: 10px;"><span class="editable" data-field="father_name" contenteditable="true" data-old-value="<?php echo htmlspecialchars($student['father_name'] ?? '-'); ?>"><?php echo htmlspecialchars($student['father_name'] ?? '-'); ?></span></td>
                                <td style="font-size: 10px; color: #666;"><span class="editable" data-field="parent_mobile" contenteditable="true" data-old-value="<?php echo htmlspecialchars($student['parent_mobile'] ?? '-'); ?>"><?php echo htmlspecialchars($student['parent_mobile'] ?? '-'); ?></span></td>
                                <td style="font-size: 10px;"><span class="editable" data-field="student_mobile" contenteditable="true" data-old-value="<?php echo $student['student_mobile'] ?? '-'; ?>"><?php echo $student['student_mobile'] ?? '-'; ?></span></td>
                                
                                <td style="text-align: center;">
                                    <?php if ($appCount > 0): 
                                        $jobsData = json_encode($applicationInfo[$student['usn']] ?? []);
                                    ?>
                                        <span class="badge-simple" style="background:#e6f4ff; color:#0958d9; cursor: pointer; border: 1px solid #bae0ff;" 
                                              onclick='openJobsModal(<?php echo json_encode($student['name']); ?>, <?php echo $jobsData; ?>)'>
                                            <?php echo $appCount; ?> Jobs
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-simple" style="cursor: pointer;" onclick="openPortfolio('<?php echo htmlspecialchars($student['usn']); ?>','<?php echo htmlspecialchars($student['institution']); ?>','<?php echo htmlspecialchars($student['name']); ?>', 'Skill')">S <?php echo (int)$pSkill['verified']; ?>/<?php echo (int)$pSkill['total']; ?></span>
                                    <span class="badge-simple" style="cursor: pointer;" onclick="openPortfolio('<?php echo htmlspecialchars($student['usn']); ?>','<?php echo htmlspecialchars($student['institution']); ?>','<?php echo htmlspecialchars($student['name']); ?>', 'Project')">P <?php echo (int)$pProj['verified']; ?>/<?php echo (int)$pProj['total']; ?></span>
                                    <span class="badge-simple" style="cursor: pointer;" onclick="openPortfolio('<?php echo htmlspecialchars($student['usn']); ?>','<?php echo htmlspecialchars($student['institution']); ?>','<?php echo htmlspecialchars($student['name']); ?>', 'Certification')">C <?php echo (int)($portfolioSummary[$pKey]['Certification']['verified'] ?? 0); ?>/<?php echo (int)($portfolioSummary[$pKey]['Certification']['total'] ?? 0); ?></span>
                                </td>
                            </tr>

                            <?php endforeach; ?>
                            <?php if (empty($detailsStudents)): ?>
                            <tr><td colspan="22" style="text-align:center; padding:48px 20px; color:#94a3b8;">
                                <i class="fas fa-user-slash" style="font-size:26px; display:block; margin-bottom:12px; opacity:0.4;"></i>
                                <div style="font-weight:600; color:#64748b; margin-bottom:4px;">No students match the current filters</div>
                                <div style="font-size:11.5px;">Adjust the filters above, or press Reset to clear them all.</div>
                            </td></tr>
                            <?php endif; ?>
                    </tbody>
                </table>
                </div><!-- /table-wrap -->

                <div class="page-meta">
                    <div>Page <b><?php echo $page; ?></b> of <b><?php echo $total_pages; ?></b></div>
                    <?php if ($total_pages > 1): ?>
                    <form method="POST" id="paginationForm" style="display:none;"><input type="hidden" name="page" id="pageNum"></form>
                    <div class="pagination">
                        <?php
                        if ($page > 1) {
                            echo '<a href="javascript:void(0)" onclick="goToPage(1)" class="page-link" title="First page">&laquo;</a>';
                            echo '<a href="javascript:void(0)" onclick="goToPage('.($page-1).')" class="page-link" title="Previous page">&lsaquo;</a>';
                        }
                        for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++) {
                            echo '<a href="javascript:void(0)" onclick="goToPage('.$i.')" class="page-link '.($i==$page?'active':'').'">'.$i.'</a>';
                        }
                        if ($page < $total_pages) {
                            echo '<a href="javascript:void(0)" onclick="goToPage('.($page+1).')" class="page-link" title="Next page">&rsaquo;</a>';
                            echo '<a href="javascript:void(0)" onclick="goToPage('.$total_pages.')" class="page-link" title="Last page">&raquo;</a>';
                        }
                        ?>
                    </div>
                    <script>
                        function goToPage(n) {
                            document.getElementById('pageNum').value = n;
                            document.getElementById('paginationForm').submit();
                        }
                    </script>
                    <?php endif; ?>
                </div>
            </div><!-- /table-card -->
        </div><!-- /panel-details -->
    </div><!-- /main-content -->

    <!-- Portfolio Modal -->
    <div id="portfolioModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closePortfolioModal()">&times;</button>
            <div class="modal-header">
                <div style="display:flex; justify-content: space-between; align-items: flex-end; gap: 12px;">
                    <div>
                        <h2 style="color: var(--primary-maroon); margin: 0; font-size: 20px;">Student Portfolio</h2>
                        <div id="portfolioMeta" style="color:var(--text-muted); font-size: 13px; font-weight: 500; margin-top: 4px;"></div>
                    </div>
                </div>
                <div style="margin-top: 20px; display:flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn-view" onclick="showPortfolioTab('Skill')" id="tabSkillBtn">Skills</button>
                    <button type="button" class="btn-view" onclick="showPortfolioTab('Project')" id="tabProjectBtn">Projects</button>
                    <button type="button" class="btn-view" onclick="showPortfolioTab('Certification')" id="tabCertBtn">Certifications</button>
                    <button type="button" class="btn-view" onclick="showPortfolioTab('AI')" id="tabAIBtn">AI Reports</button>
                    <button type="button" class="btn-view" onclick="showPortfolioTab('Resume')" id="tabResumeBtn">Resume</button>
                </div>
            </div>
            <div class="modal-body">
                <div id="portfolioLoading" style="padding: 40px; text-align: center; color:var(--text-muted); display:none;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; margin-bottom: 12px; display: block;"></i> Loading portfolio data...
                </div>
                <div id="portfolioError" style="padding: 20px; background: #fff1f2; border: 1px solid #fecaca; border-radius: 8px; color:#b91c1c; margin-top: 16px; display:none;"></div>
                <div id="portfolioSkill"></div>
                <div id="portfolioProject" style="display:none;"></div>
                <div id="portfolioCertification" style="display:none;"></div>
                <div id="portfolioAI" style="display:none;"></div>
                <div id="portfolioResume" style="display:none;"></div>
            </div>
        </div>
    </div>

    <script>
        let portfolioActiveTab = 'Skill';

        function showPortfolioTab(tab) {
            portfolioActiveTab = tab;
            
            // Toggle Display
            document.getElementById('portfolioSkill').style.display = (tab === 'Skill') ? 'block' : 'none';
            document.getElementById('portfolioProject').style.display = (tab === 'Project') ? 'block' : 'none';
            document.getElementById('portfolioCertification').style.display = (tab === 'Certification') ? 'block' : 'none';
            document.getElementById('portfolioAI').style.display = (tab === 'AI') ? 'block' : 'none';
            document.getElementById('portfolioResume').style.display = (tab === 'Resume') ? 'block' : 'none';
            
            // Toggle Button Active State
            document.getElementById('tabSkillBtn').classList.toggle('active', tab === 'Skill');
            document.getElementById('tabProjectBtn').classList.toggle('active', tab === 'Project');
            document.getElementById('tabCertBtn').classList.toggle('active', tab === 'Certification');
            document.getElementById('tabAIBtn').classList.toggle('active', tab === 'AI');
            document.getElementById('tabResumeBtn').classList.toggle('active', tab === 'Resume');
        }

        function closePortfolioModal() {
            document.getElementById('portfolioModal').style.display = 'none';
        }

        function renderPortfolioList(items) {
            if (!items || items.length === 0) {
                return '<div style="padding:40px; text-align:center; color:var(--text-muted); font-size:13px;"><i class="fas fa-folder-open" style="font-size:24px; display:block; margin-bottom:10px; opacity:0.3;"></i>No items shared yet.</div>';
            }
            return '<div style="margin-top: 16px; display: flex; flex-direction: column; gap: 12px;">' + items.map(function(it) {
                const verified = (parseInt(it.is_verified || 0, 10) === 1);
                let badge = verified
                    ? '<span style="padding:4px 10px; border-radius:20px; font-size:10px; font-weight:700; background:#ecfdf5; color:#059669; border:1px solid #05966922;"><i class="fas fa-check-circle" style="margin-right:4px;"></i>VERIFIED</span>'
                    : '<button type="button" class="btn-view" style="padding:4px 12px; font-size:10px; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; cursor:pointer;" onclick="verifyItem('+it.id+', this)"><i class="fas fa-shield-halved" style="margin-right:4px;"></i>VERIFY</button>';
                
                const link = it.link ? ('<div style="margin-top:10px; padding-top:10px; border-top:1px solid #f1f5f9;"><a href="' + encodeURI(it.link) + '" target="_blank" style="color:#2563eb; text-decoration:none; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px;"><i class="fas fa-external-link-alt"></i> ' + (it.link_label || 'Project/Skill Link') + '</a></div>') : '';
                const subtitle = it.sub_title ? ('<div style="color:var(--text-muted); font-size:12px; margin-top:2px; font-weight:500;">' + escapeHtml(it.sub_title) + '</div>') : '';
                const desc = it.description ? ('<div style="color:#475569; font-size:13px; margin-top:10px; line-height:1.6; background:#f8fafc; padding:10px; border-radius:8px;">' + escapeHtml(it.description) + '</div>') : '';
                
                return (
                    '<div style="padding:16px; border:1px solid #e2e8f0; border-radius:12px; background:white; transition: 0.2s;">' +
                        '<div style="display:flex; align-items:flex-start; justify-content:space-between; gap:15px;">' +
                            '<div>' +
                                '<div style="font-weight:700; color:#0f172a; font-size:14px;">' + escapeHtml(it.title || 'Untitled Item') + '</div>' +
                                subtitle +
                            '</div>' +
                            '<div>' + badge + '</div>' +
                        '</div>' +
                        desc +
                        link +
                    '</div>'
                );
            }).join('') + '</div>';
        }

        function escapeHtml(str) {
            return String(str)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        async function openPortfolio(usn, institution, name, initialTab = 'Skill') {
            document.getElementById('portfolioMeta').innerText = (name || '') + ' — ' + (usn || '') + ' (' + (institution || '') + ')';
            document.getElementById('portfolioLoading').style.display = 'block';
            document.getElementById('portfolioError').style.display = 'none';
            document.getElementById('portfolioSkill').innerHTML = '';
            document.getElementById('portfolioProject').innerHTML = '';
            document.getElementById('portfolioCertification').innerHTML = '';
            document.getElementById('portfolioAI').innerHTML = '';
            document.getElementById('portfolioResume').innerHTML = '';
            document.getElementById('portfolioModal').style.display = 'flex';
            
            // Initialize with requested tab active
            showPortfolioTab(initialTab);

            portfolioCurrentUsn = usn;
            portfolioCurrentInst = institution;

            try {
                const formData = new FormData();
                formData.append('usn', usn);
                formData.append('institution', institution);
                const res = await fetch('portfolio_details.php', { 
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' } 
                });
                const data = await res.json();
                document.getElementById('portfolioLoading').style.display = 'none';
                if (!data.success) {
                    document.getElementById('portfolioError').style.display = 'block';
                    document.getElementById('portfolioError').innerText = data.message || 'Failed to load portfolio.';
                    return;
                }

                document.getElementById('portfolioSkill').innerHTML = renderPortfolioList(data.skills || []);
                document.getElementById('portfolioProject').innerHTML = renderPortfolioList(data.projects || []);
                document.getElementById('portfolioCertification').innerHTML = renderPortfolioList(data.certifications || []);
                
                // Filter and Render Resume specifically
                const resumes = (data.ai_reports || []).filter(r => (r.assessment_type === 'Resume' || (r.pdf_reports && r.pdf_reports.some(p => p.type === 'Resume'))));
                const otherReports = (data.ai_reports || []).filter(r => !(r.assessment_type === 'Resume' || (r.pdf_reports && r.pdf_reports.some(p => p.type === 'Resume'))));

                if (resumes.length > 0) {
                    document.getElementById('portfolioResume').innerHTML = '<div style="margin-top: 20px; text-align: center; padding: 30px; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px;">' + 
                        resumes.flatMap(r => r.pdf_reports || []).filter(p => p.type === 'Resume').map(pdf => `
                            <div style="margin-bottom: 15px;">
                                <i class="fas fa-file-pdf" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                                <div style="font-weight: 700; color: #1e293b; font-size: 16px; margin-bottom: 5px;">Student Professional Resume</div>
                                <div style="color: #64748b; font-size: 13px; margin-bottom: 20px;">Built via Lakshya Resume Builder</div>
                                <a href="../student/view_resume.php?usn=${encodeURIComponent(portfolioCurrentUsn)}" target="_blank" class="btn-simple btn-maroon" style="padding: 12px 24px; font-size: 14px; border-radius: 8px;">
                                    <i class="fas fa-download"></i> View & Download Resume
                                </a>
                            </div>
                        `).join('') + '</div>';
                } else {
                    document.getElementById('portfolioResume').innerHTML = '<div style="padding:40px; text-align:center; color:var(--text-muted); font-size:13px;"><i class="fas fa-file-excel" style="font-size:24px; display:block; margin-bottom:10px; opacity:0.3;"></i>No Resume found for this student.</div>';
                }

                // Render Other AI Reports
                if (otherReports.length > 0) {
                    document.getElementById('portfolioAI').innerHTML = '<div style="margin-top: 16px; display: flex; flex-direction: column; gap: 12px;">' + otherReports.map(rep => {
                        const isNumeric = !isNaN(parseFloat(rep.score)) && isFinite(rep.score);
                        const score = isNumeric ? parseInt(rep.score) : 0;
                        const displayScore = isNumeric ? score : '--';
                        
                        const scoreColor = score >= 80 ? '#059669' : (score >= 60 ? '#d97706' : '#dc2626');
                        const scoreBg = score >= 80 ? '#ecfdf5' : (score >= 60 ? '#fffbeb' : '#fef2f2');
                        
                        const pdfLinks = (rep.pdf_reports || []).map(pdf => 
                            `<a href="../student/view_resume.php?usn=${encodeURIComponent(portfolioCurrentUsn)}&type=${encodeURIComponent(pdf.type)}&path=${encodeURIComponent(pdf.path)}" target="_blank" class="btn-simple" style="padding:6px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:11px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fas fa-file-pdf" style="color:#ef4444;"></i> Download ${pdf.type} Report
                            </a>`
                        ).join(' ');
                        
                        return `
                            <div style="padding:16px; border:1px solid #e2e8f0; border-radius:12px; background:white; position:relative;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div style="flex:1;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span style="font-weight:700; color:#0f172a; font-size:14px;">${escapeHtml(rep.company_name)}</span>
                                            ${(rep.assessment_type || '').toLowerCase().includes('round') || (rep.assessment_type || '').toLowerCase().includes('ai') ? '<span style="font-size:10px; font-weight:700; color:var(--text-muted); background:#f1f5f9; padding:2px 8px; border-radius:10px;">PROCTORING: ON</span>' : ''}
                                        </div>
                                        <div style="display:flex; gap:15px; margin-top:8px;">
                                            <div style="font-size:12px; color:var(--text-muted);"><i class="far fa-calendar-alt" style="margin-right:6px;"></i> ${new Date(rep.started_at).toLocaleDateString()}</div>
                                            <div style="font-size:12px; color:var(--text-muted);"><i class="fas fa-layer-group" style="margin-right:6px;"></i> ${escapeHtml(rep.assessment_type)} Round</div>
                                        </div>
                                    </div>
                                    <div style="background:${scoreBg}; padding:10px 15px; border-radius:12px; text-align:center; border:1px solid ${scoreColor}22; min-width:80px;">
                                        <div style="font-size:22px; font-weight:900; color:${scoreColor}; line-height:1;">${displayScore}${isNumeric ? '<span style="font-size:11px;">%</span>' : ''}</div>
                                        <div style="font-size:9px; font-weight:700; color:${scoreColor}; margin-top:4px; text-transform:uppercase;">Overall Score</div>
                                    </div>
                                </div>
                                ${pdfLinks ? `<div style="margin-top:15px; display:flex; gap:10px; border-top:1px solid #f1f5f9; padding-top:12px;">${pdfLinks}</div>` : ''}
                            </div>
                        `;
                    }).join('') + '</div>';
                }
          else {
                    document.getElementById('portfolioAI').innerHTML = '<div style="padding:40px; text-align:center; color:var(--text-muted); font-size:13px;"><i class="fas fa-robot" style="font-size:24px; display:block; margin-bottom:10px; opacity:0.3;"></i>No AI Assessments record found.</div>';
                }
            } catch (e) {
                document.getElementById('portfolioLoading').style.display = 'none';
                document.getElementById('portfolioError').style.display = 'block';
                document.getElementById('portfolioError').innerText = 'Connection error while loading portfolio.';
            }
        }

        // (Outside-click closing for both modals is handled by the single
        // window.onclick handler further below — keeping one handler avoids
        // the second assignment silently overwriting the first.)

        // --- Toast notifications (replaces blocking alert() for inline saves) ---
        function showToast(message, type = 'success') {
            let stack = document.getElementById('toastStack');
            if (!stack) {
                stack = document.createElement('div');
                stack.id = 'toastStack';
                stack.className = 'toast-stack';
                document.body.appendChild(stack);
            }
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation';
            toast.innerHTML = '<i class="fas ' + icon + '"></i> ' + escapeHtml(message);
            stack.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        }

        async function verifyItem(itemId, btn) {
            if (!confirm('Mark this item as verified?')) return;
            
            const formData = new FormData();
            formData.append('ajax_action', 'update_student');
            formData.append('field', 'verify_portfolio');
            formData.append('usn', portfolioCurrentUsn);
            formData.append('inst', portfolioCurrentInst);
            formData.append('item_id', itemId);

            btn.disabled = true;
            btn.innerText = 'SAVING...';

            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') {
                    btn.outerHTML = '<span style="margin-left:8px; padding:2px 8px; border-radius:499px; font-size:10px; font-weight:700; background:#e3fcef; color:#00875a; border:1px solid #00875a33;">VERIFIED</span>';
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = 'VERIFY NOW';
                }
            } catch (err) {
                alert('Connection error.');
                btn.disabled = false;
                btn.innerText = 'VERIFY NOW';
            }
        }

        let portfolioCurrentUsn = '';
        let portfolioCurrentInst = '';

        // --- Inline Editing Logic ---
        document.querySelectorAll('.editable').forEach(el => {
            el.dataset.oldValue = el.innerText.trim(); // Store initial value
            el.addEventListener('blur', function() {
                saveInlineEdit(this);
            });
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });

        async function saveInlineEdit(el) {
            const tr = el.closest('tr');
            const usn = tr.dataset.usn;
            const inst = tr.dataset.inst;
            const field = el.dataset.field;
            let val = el.innerText.trim();

            // Remove '%' for percentage fields
            if (field === 'puc_percentage' || field === 'sslc_percentage') {
                val = val.replace(/%/g, '');
            }
            // Replace '-' with empty string for null values
            if (val === '-') {
                val = '';
            }

            if (el.dataset.oldValue === val) return;

            el.classList.add('editable-saving');
            
            const formData = new FormData();
            formData.append('ajax_action', 'update_student');
            formData.append('usn', usn);
            formData.append('inst', inst);
            formData.append('field', field);
            formData.append('value', val);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                el.classList.remove('editable-saving');
                if (result.status === 'success') {
                    el.classList.add('editable-success');
                    el.dataset.oldValue = val; // Update old value on success
                    showToast('Saved.');
                    setTimeout(() => el.classList.remove('editable-success'), 1500);
                } else {
                    el.classList.add('editable-error');
                    showToast('Update failed: ' + (result.message || 'Unknown error'), 'error');
                    // Revert to old value on error
                    el.innerText = el.dataset.oldValue;
                    setTimeout(() => el.classList.remove('editable-error'), 2000);
                }
            } catch (err) {
                el.classList.remove('editable-saving');
                el.classList.add('editable-error');
                console.error(err);
                showToast('Connection error — change was not saved.', 'error');
                // Revert to old value on error
                el.innerText = el.dataset.oldValue;
                setTimeout(() => el.classList.remove('editable-error'), 2000);
            }
        }
    </script>
    <!-- Jobs Details Modal -->
    <div id="jobsModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 style="font-size: 18px; color: var(--text-dark);">Applied Jobs: <span id="jobsModalStudentName" style="color: var(--primary-maroon);"></span></h3>
                <button class="close-modal" onclick="closeJobsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-wrap" style="border: none; margin-top: 10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Job Role</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="jobsModalBody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openJobsModal(studentName, jobsData) {
            document.getElementById('jobsModalStudentName').textContent = studentName;
            const tbody = document.getElementById('jobsModalBody');
            tbody.innerHTML = '';
            
            if (jobsData && jobsData.length > 0) {
                jobsData.forEach(job => {
                    let statusColor = '#64748b';
                    let bg = '#f1f5f9';
                    
                    if(job.status === 'selected') { statusColor = '#166534'; bg = '#dcfce7'; }
                    else if(job.status === 'rejected') { statusColor = '#991b1b'; bg = '#fee2e2'; }
                    else if(job.status === 'shortlisted') { statusColor = '#854d0e'; bg = '#fef9c3'; }
                    
                    const row = `
                        <tr>
                            <td style="font-weight: 600;">${job.company}</td>
                            <td>${job.title}</td>
                            <td style="color: #64748b;">${job.date}</td>
                            <td><span class="badge-simple" style="background: ${bg}; color: ${statusColor};">${job.status.charAt(0).toUpperCase() + job.status.slice(1)}</span></td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">No applications found.</td></tr>';
            }
            
            document.getElementById('jobsModal').style.display = 'flex';
        }

        function closeJobsModal() {
            document.getElementById('jobsModal').style.display = 'none';
        }

        // Close modals on outside click (single handler for both modals)
        window.onclick = function(event) {
            const jobsModal = document.getElementById('jobsModal');
            if (event.target == jobsModal) {
                jobsModal.style.display = "none";
            }
            const modal = document.getElementById('portfolioModal');
            if (event.target == modal) {
                closePortfolioModal();
            }
        }

        // Close modals on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeJobsModal();
                closePortfolioModal();
            }
        });

        function toggleFreeze(usn, inst, action, btn) {
            if (!confirm(action === 1 ? "Freeze SGPA for this student?" : "Unfreeze SGPA for this student?")) return;
            
            let originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax_action: action === 1 ? 'freeze_sgpa' : 'unfreeze_sgpa',
                    usn: usn,
                    inst: inst
                })
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    if (action === 1) {
                        btn.outerHTML = `<button class="btn-view" style="color: #059669; border-color: #059669; background: #ecfdf5; padding: 2px 6px; font-size:10px;" onclick="toggleFreeze('${usn}', '${inst}', 0, this)"><i class="fas fa-lock"></i> Frozen</button>`;
                    } else {
                        btn.outerHTML = `<button class="btn-view" style="color: #94a3b8; padding: 2px 6px; font-size:10px;" onclick="toggleFreeze('${usn}', '${inst}', 1, this)"><i class="fas fa-lock-open"></i> Active</button>`;
                    }
                } else {
                    alert(res.message);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            }).catch(e => {
                alert("An error occurred");
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }

        function freezeAllStudents() {
            if (!confirm("Freeze SGPAs for all students visible on this page?")) return;
            batchFreezeAction('freeze_all_sgpa', 'frozen');
        }

        function unfreezeAllStudents() {
            if (!confirm("Unfreeze SGPAs for all students visible on this page?")) return;
            batchFreezeAction('unfreeze_all_sgpa', 'unfrozen');
        }

        function batchFreezeAction(action, label) {
            let students = [];
            document.querySelectorAll('tr[data-usn]').forEach(tr => {
                students.push({
                    usn: tr.getAttribute('data-usn'),
                    inst: tr.getAttribute('data-inst')
                });
            });

            if (students.length === 0) {
                alert("No students found.");
                return;
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax_action: action,
                    students: JSON.stringify(students)
                })
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    alert(`All visible students have been ${label}.`);
                    window.location.reload();
                } else {
                    alert(res.message || `Failed to ${label}.`);
                }
            }).catch(e => {
                alert(`An error occurred during batch ${label}.`);
            });
        }
    </script>
</body>
</html>

