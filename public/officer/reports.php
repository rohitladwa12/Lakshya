<?php
/**
 * Placement Officer - Consolidated Students & Reports
 * One page: Student Details | AI Reports — with tabs for All | GMU | GMIT
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_PLACEMENT_OFFICER);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_PLACEMENT_OFFICER);

$pageId = 'officer_reports';

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_tab'])) {
        SessionFilterHelper::updateFilters($pageId, [
            'section' => $_POST['section'] ?? 'details',
            'inst' => $_POST['inst'] ?? 'all'
        ]);
    } else {
        SessionFilterHelper::handlePostToSession($pageId, $_POST);
    }
    header("Location: reports.php");
    exit;
}

// Retrieve from Session
$filters = SessionFilterHelper::getFilters($pageId);

$section = $filters['section'] ?? 'details';
if (!in_array($section, ['details', 'reports'], true)) $section = 'details';

$inst = $filters['inst'] ?? 'all';
if (!in_array($inst, ['all', 'gmu', 'gmit'], true)) $inst = 'all';

$instFilter = ($inst === 'gmu') ? INSTITUTION_GMU : (($inst === 'gmit') ? INSTITUTION_GMIT : null);

// --- Global Filters ---
$search = clean($filters['search'] ?? '');
$min_sgpa = isset($filters['min_sgpa']) ? (float)$filters['min_sgpa'] : 0;
$faculty_filter = isset($filters['faculty']) ? (is_array($filters['faculty']) ? clean($filters['faculty']) : [clean($filters['faculty'])]) : [];
$discipline_filter = isset($filters['discipline']) ? (is_array($filters['discipline']) ? clean($filters['discipline']) : [clean($filters['discipline'])]) : [];
$sem_filter = isset($filters['sem']) ? (int)$filters['sem'] : 0;
if ($sem_filter < 5 || $sem_filter > 8) $sem_filter = 0;

// --- AI Reports data ---
$officerModel = new PlacementOfficer();
$reportFilters = [];
if ($instFilter) $reportFilters['institution'] = $instFilter;
if ($sem_filter) $reportFilters['semesters'] = [$sem_filter];
$aiReports = $officerModel->getUnifiedAIReports($reportFilters);

// Filter AI reports for semesters 5, 6, 7, 8 (or specific sem if filtered)
$aiReports = array_filter($aiReports, function($r) use ($sem_filter) {
    $s = (int)($r['current_sem'] ?? 0);
    if ($sem_filter) return $s === $sem_filter;
    return in_array($s, [5, 6, 7, 8]);
});

function findStudentReportsOfficer($usn) {
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
$limit = 10;
$page = $filters['page'] ?? 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

function buildInClauseOfficer($column, $values, &$params) {
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
     SELECT student_id as usn, name, aadhar, college as faculty, college as school, programme, course, discipline, 0 as year, 0 as sem, 0.0 as sgpa, 1 as registered, student_id as student_id_map, '" . INSTITUTION_GMIT . "' as institution FROM {$gmitPrefix}ad_student_details)
";

$combinedDetails = "
    (SELECT usn, student_id, gender, dob, student_mobile, parent_mobile, father_name, mother_name, email_id, puc_percentage, sslc_percentage, category, district, taluk, state
     FROM {$gmuPrefix}ad_student_details
     UNION ALL
     SELECT usn, student_id, gender, dob, student_mobile, parent_mobile, father_name, mother_name, email_id, puc_percentage, sslc_percentage, category, district, taluk, state
     FROM {$gmitPrefix}ad_student_details)
";
$where_clauses = ["asa.registered = 1"];
$params = [];

$targetSems = $sem_filter ? [$sem_filter] : [5, 6, 7, 8];
$targetSemsPh = implode(',', $targetSems);

if (!$instFilter) {
    $stmtLocal = $localDB->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN ($targetSemsPh) AND is_current = 1");
    $stmtLocal->execute([INSTITUTION_GMIT]);
    $gmitUsns = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($gmitUsns)) {
        $ph = implode(',', array_fill(0, count($gmitUsns), '?'));
        $where_clauses[] = "((asa.institution = '" . INSTITUTION_GMU . "' AND asa.sem IN ($targetSemsPh)) OR (asa.institution = '" . INSTITUTION_GMIT . "' AND asa.usn IN ($ph)))";
        $params = array_merge($params, $gmitUsns);
    } else {
        $where_clauses[] = "((asa.institution = '" . INSTITUTION_GMU . "' AND asa.sem IN ($targetSemsPh)) OR (asa.institution = '" . INSTITUTION_GMIT . "' AND 1=0))";
    }
} else {
    if ($instFilter === INSTITUTION_GMU) {
        $where_clauses[] = "asa.sem IN ($targetSemsPh)";
    } else {
        $stmtLocal = $localDB->prepare("SELECT DISTINCT student_id FROM student_sem_sgpa WHERE institution = ? AND semester IN ($targetSemsPh) AND is_current = 1");
        $stmtLocal->execute([INSTITUTION_GMIT]);
        $gmitUsns = $stmtLocal->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($gmitUsns)) {
            $ph = implode(',', array_fill(0, count($gmitUsns), '?'));
            $where_clauses[] = "asa.usn IN ($ph)";
            $params = array_merge($params, $gmitUsns);
        } else {
            $where_clauses[] = "1=0";
        }
    }
}

if ($search) {
    $where_clauses[] = "(asa.usn LIKE ? OR asa.name LIKE ? OR asa.aadhar LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($faculty_filter_sql = buildInClauseOfficer("asa.faculty", $faculty_filter, $params)) $where_clauses[] = $faculty_filter_sql;
if ($discipline_filter_sql = buildInClauseOfficer("asa.discipline", $discipline_filter, $params)) $where_clauses[] = $discipline_filter_sql;
if ($min_sgpa > 0) {
    $where_clauses[] = "asa.sgpa >= ?";
    $params[] = $min_sgpa;
}

$where_sql = implode(" AND ", $where_clauses);

$count_query = "SELECT COUNT(DISTINCT asa.usn) FROM {$combinedApproved} asa WHERE $where_sql";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

if (isset($filters['export']) && $section === 'details') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="student_details_report_'.date('Y-m-d').'.xls"');
    $query = "
        SELECT asa.usn, MAX(asa.name) as name, asa.aadhar, MAX(asa.faculty) as faculty, MAX(asa.discipline) as discipline,
        MAX(asa.programme) as programme, MAX(asa.sem) as max_sem, MAX(asd.puc_percentage) as puc_percentage, MAX(asd.sslc_percentage) as sslc_percentage,
        MAX(asd.student_mobile) as student_mobile, MAX(asd.parent_mobile) as parent_mobile, MAX(asd.father_name) as father_name,
        MAX(asd.mother_name) as mother_name, MAX(asd.email_id) as email_id, MAX(asa.institution) as institution, MAX(asd.gender) as gender
        FROM {$combinedApproved} asa
        LEFT JOIN {$combinedDetails} asd ON ( (asa.usn = asd.student_id AND asa.institution = '" . INSTITUTION_GMIT . "') OR (asa.usn = asd.usn AND asa.institution = '" . INSTITUTION_GMU . "') )
        WHERE $where_sql
        GROUP BY asa.usn, asa.aadhar, asa.institution
        ORDER BY name ASC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $all_students = $stmt->fetchAll();
    echo '<table border="1">';
    echo '<tr><th>Institution</th><th>USN</th><th>Name</th><th>Gender</th><th>Aadhar</th><th>Faculty</th><th>Discipline</th><th>Programme</th><th>Current Sem</th>';
    echo '<th>Father</th><th>Mother</th><th>Parent Mobile</th>';
    echo '<th>PUC %</th><th>SSLC %</th><th>Mobile</th><th>Email</th>';
    echo '<th>Sem 1 SGPA</th><th>Sem 2 SGPA</th><th>Sem 3 SGPA</th><th>Sem 4 SGPA</th><th>Sem 5 SGPA</th><th>Sem 6 SGPA</th><th>Sem 7 SGPA</th><th>Sem 8 SGPA</th></tr>';
    foreach($all_students as $s) {
        $sgpaData = getSemesterSGPAPlaceOfficer($db, $localDB, $s['usn'], $s['institution']);
        
        // Fetch current sem for this student
        $currStmt = $localDB->prepare("SELECT semester FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
        $currStmt->execute([$s['usn'], $s['institution']]);
        $cs = $currStmt->fetchColumn();
        if (!$cs && $s['institution'] === INSTITUTION_GMU) {
             $cs = $s['max_sem'] ?? null;
             if (!$cs) {
                 $remoteStmt = $db->prepare("SELECT MAX(sem) FROM {$gmuPrefix}ad_student_approved WHERE usn = ?");
                 $remoteStmt->execute([$s['usn']]);
                 $cs = $remoteStmt->fetchColumn();
             }
        }

        echo "<tr>";
        echo "<td>{$s['institution']}</td><td>{$s['usn']}</td><td>{$s['name']}</td><td>{$s['gender']}</td><td>{$s['aadhar']}</td><td>{$s['faculty']}</td><td>{$s['discipline']}</td><td>{$s['programme']}</td><td>Sem {$cs}</td>";
        echo "<td>{$s['father_name']}</td><td>{$s['mother_name']}</td><td>{$s['parent_mobile']}</td>";
        echo "<td>" . ($s['puc_percentage'] ?? '-') . "</td><td>" . ($s['sslc_percentage'] ?? '-') . "</td><td>{$s['student_mobile']}</td><td>{$s['email_id']}</td>";
        for ($sem = 1; $sem <= 8; $sem++) {
            $sgpaVal = $sgpaData[$sem] ?? null;
            echo "<td>" . ($sgpaVal !== null && $sgpaVal !== '-' ? number_format((float)$sgpaVal, 2) : '-') . "</td>";
        }
        echo "</tr>";
    }
    echo '</table>';
    exit;
}

$details_query = "
    SELECT asa.usn, MAX(asa.name) as name, asa.aadhar, MAX(asa.faculty) as faculty, MAX(asa.school) as school,
    MAX(asa.programme) as programme, MAX(asa.course) as course, MAX(asa.discipline) as discipline,
    MAX(asa.sem) as max_sem,
    MAX(asd.gender) as gender, MAX(asd.dob) as dob, MAX(asd.student_mobile) as student_mobile, MAX(asd.parent_mobile) as parent_mobile,
    MAX(asd.father_name) as father_name, MAX(asd.mother_name) as mother_name,
    MAX(asd.email_id) as email_id, MAX(asd.puc_percentage) as puc_percentage, MAX(asd.sslc_percentage) as sslc_percentage,
    MAX(asd.category) as category, MAX(asd.district) as district, MAX(asd.taluk) as taluk, MAX(asd.state) as state, asa.institution
    FROM {$combinedApproved} asa
    LEFT JOIN {$combinedDetails} asd ON ( (asa.usn = asd.student_id AND asa.institution = '" . INSTITUTION_GMIT . "') OR (asa.usn = asd.usn AND asa.institution = '" . INSTITUTION_GMU . "') )
    WHERE $where_sql
    GROUP BY asa.usn, asa.aadhar, asa.institution
    ORDER BY name ASC
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

// Portfolio summary
$portfolioSummary = [];
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
                $usns[$u] = true;
                $insts[$i] = true;
                $key = $i . '|' . $u;
                $portfolioSummary[$key] = [
                    'Skill' => ['total' => 0, 'verified' => 0],
                    'Project' => ['total' => 0, 'verified' => 0],
                ];
            }
        }

        if (!empty($usns) && !empty($insts)) {
            $usnList = array_keys($usns);
            $instList = array_keys($insts);
            $phUsn = implode(',', array_fill(0, count($usnList), '?'));
            $phInst = implode(',', array_fill(0, count($instList), '?'));
            $verifiedExpr = $hasIsVerified ? "SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END)" : "0";
            $sql = "SELECT student_id, institution, category,
                           COUNT(*) as total_count,
                           {$verifiedExpr} as verified_count
                    FROM student_portfolio
                    WHERE institution IN ($phInst)
                      AND student_id IN ($phUsn)
                      AND category IN ('Skill','Project')
                    GROUP BY student_id, institution, category";
            $stmtP = $localDB->prepare($sql);
            $stmtP->execute(array_merge($instList, $usnList));
            while ($r = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                $key = ($r['institution'] ?? '') . '|' . ($r['student_id'] ?? '');
                $cat = $r['category'] ?? '';
                if (isset($portfolioSummary[$key]) && ($cat === 'Skill' || $cat === 'Project')) {
                    $portfolioSummary[$key][$cat]['total'] = (int) ($r['total_count'] ?? 0);
                    $portfolioSummary[$key][$cat]['verified'] = (int) ($r['verified_count'] ?? 0);
                }
            }
        }
    }
} catch (Exception $e) {}

function getSemesterSGPAPlaceOfficer($remoteDB, $localDB, $usn, $institution) {
    if ($institution === INSTITUTION_GMIT) {
        $sgpaData = array_fill(1, 8, null);
        try {
            $stmt = $localDB->prepare("SELECT semester, sgpa FROM student_sem_sgpa WHERE student_id = ? AND institution = ? ORDER BY semester");
            $stmt->execute([$usn, INSTITUTION_GMIT]);
            while ($row = $stmt->fetch()) {
                if ($row['semester'] >= 1 && $row['semester'] <= 8) $sgpaData[$row['semester']] = $row['sgpa'];
            }
        } catch (PDOException $e) {}
        return $sgpaData;
    }
    $prefix = DB_GMU_PREFIX;
    $sgpaData = array_fill(1, 8, null);
    try {
        $stmt = $remoteDB->prepare("SELECT sem, sgpa FROM {$prefix}ad_student_approved WHERE usn = ? ORDER BY sem");
        $stmt->execute([$usn]);
        while ($row = $stmt->fetch()) {
            if ($row['sem'] >= 1 && $row['sem'] <= 8) $sgpaData[$row['sem']] = $row['sgpa'];
        }
    } catch (PDOException $e) {}
    return $sgpaData;
}

function buildSectionUrlOfficer($s, $i) {
    echo '<form method="POST" id="switchTabForm_'.$s.'_'.$i.'" style="display:none;">';
    echo '<input type="hidden" name="update_tab" value="1">';
    echo '<input type="hidden" name="section" value="'.$s.'">';
    echo '<input type="hidden" name="inst" value="'.$i.'">';
    echo '</form>';
    return "javascript:document.getElementById('switchTabForm_{$s}_{$i}').submit()";
}

// Fetch faculties and disciplines for dropdowns
function getDistinctValuesRep($db, $column) {
    // Whitelist allowed columns to prevent SQL injection in identifier parts
    $allowedCols = ['faculty', 'discipline'];
    if (!in_array($column, $allowedCols)) return [];
    
    $gmuPrefix = DB_GMU_PREFIX;
    $gmitPrefix = DB_GMIT_PREFIX;
    $gmitCol = ($column === 'faculty') ? 'college' : $column;
    
    $sql = "SELECT DISTINCT $column FROM (
                SELECT $column FROM {$gmuPrefix}ad_student_approved WHERE registered = 1 AND $column IS NOT NULL AND $column != ''
                UNION
                SELECT $gmitCol as $column FROM {$gmitPrefix}ad_student_details WHERE $gmitCol IS NOT NULL AND $gmitCol != ''
            ) combined ORDER BY $column";
            
    try { 
        // Identifiers ($column, $gmitCol) are whitelisted
        return $db->query($sql)->fetchAll(PDO::FETCH_COLUMN); 
    } catch (Exception $e) { 
        return []; 
    }
}
$faculties = getDistinctValuesRep($db, 'faculty');
$disciplines = getDistinctValuesRep($db, 'discipline');

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Insights — <?php echo APP_NAME; ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-maroon: #800000; --primary-dark: #5b1f1f; --primary-gold: #e9c66f; --white: #ffffff; --shadow: 0 4px 20px rgba(0,0,0,0.08); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; min-height: 100vh; }
        .main-content { 
            /* Layout handled by navbar.php */
        }
        .header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 24px; color: var(--primary-maroon); font-weight: 700; }
        .tabs-main { display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 2px solid #e2e8f0; flex-wrap: wrap; }
        .tab-main { padding: 12px 20px; text-decoration: none; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-main:hover { color: var(--primary-maroon); }
        .tab-main.active { color: var(--primary-maroon); border-bottom-color: var(--primary-maroon); }
        .tabs-inst { display: flex; gap: 4px; margin-bottom: 20px; }
        .tab-inst { padding: 8px 16px; text-decoration: none; background: #f1f5f9; color: #475569; font-weight: 600; border-radius: 8px; font-size: 14px; }
        .tab-inst:hover { background: #e2e8f0; color: var(--primary-maroon); }
        .tab-inst.active { background: var(--primary-maroon); color: white; }
        .panel { display: none; }
        .panel.active { display: block; }
        .table-container { background: var(--white); border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 24px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: auto; }
        th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #f1f5f9; vertical-align: middle; word-wrap: break-word; }
        th { background: #f8fafc; color: #64748b; font-weight: 800; text-transform: uppercase; font-size: 9px; letter-spacing: 0.02em; border-bottom: 2px solid #eaeef2; white-space: nowrap; }
        
        .student-name { font-weight: 700; color: var(--primary-maroon); display: block; min-width: 100px; }
        .usn-font { font-family: monospace; font-weight: 600; color: #475569; }
        .score-val { font-weight: 700; color: #059669; }
        
        .btn-view { color: var(--primary-maroon); background: white; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--primary-maroon); font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; white-space: nowrap; }
        .btn-view:hover { background: var(--primary-maroon); color: white; }

        .pagination { display: flex; justify-content: center; align-items: center; padding: 15px; gap: 4px; }
        .page-link { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 4px; text-decoration: none; color: #475569; font-size: 12px; background: white; }
        .page-link.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }

        /* Modal Styling */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1100; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; position: relative; }
        .close-modal { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; color: #666; }
        
        .round-tag { font-size: 10px; padding: 2px 8px; border-radius: 4px; background: #e2e8f0; color: #475569; font-weight: bold; }
        .round-hr { background: #f6ffed; color: #389e0d; }
        .round-technical { background: #e6f7ff; color: #0050b3; }

        /* Filter Section Styling */
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .filter-item { display: flex; flex-direction: column; gap: 5px; }
        .filter-item label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.02em; }
        .filter-item input, .filter-item select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #334155; transition: all 0.2s; background: #fff; width: 100%; }
        .filter-item input:focus, .filter-item select:focus { border-color: var(--primary-maroon); outline: none; box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1); }

        .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: 1px solid transparent; text-decoration: none; }
        .btn-primary { background: var(--primary-maroon); color: white; border: none; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15); }
        .btn-excel { background: #15803d; color: white; border: none; }
        .btn-excel:hover { background: #166534; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22, 101, 52, 0.15); }
        .btn-clear { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-clear:hover { background: #e2e8f0; color: #1e293b; }
        
        .filtered-count { font-size: 13px; color: #64748b; background: #f8fafc; padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; }
        .filtered-count strong { color: var(--primary-maroon); }
    </style>
</head>
<body>
    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Placement Reports & Insights</h1>
        </div>

        <!-- Main tabs: Student Details | AI Reports -->
        <nav class="tabs-main">
            <a href="<?php echo buildSectionUrlOfficer('details', $inst); ?>" class="tab-main <?php echo $section === 'details' ? 'active' : ''; ?>"><i class="fas fa-user-graduate"></i> Student Details</a>
            <a href="<?php echo buildSectionUrlOfficer('reports', $inst); ?>" class="tab-main <?php echo $section === 'reports' ? 'active' : ''; ?>"><i class="fas fa-robot"></i> AI Assessment Reports</a>
        </nav>

        <!-- Sub-tabs: All | GMU | GMIT -->
        <div class="tabs-inst">
            <a href="<?php echo buildSectionUrlOfficer($section, 'all'); ?>" class="tab-inst <?php echo $inst === 'all' ? 'active' : ''; ?>">All Institutions</a>
            <a href="<?php echo buildSectionUrlOfficer($section, 'gmu'); ?>" class="tab-inst <?php echo $inst === 'gmu' ? 'active' : ''; ?>">GMU</a>
            <a href="<?php echo buildSectionUrlOfficer($section, 'gmit'); ?>" class="tab-inst <?php echo $inst === 'gmit' ? 'active' : ''; ?>">GMIT</a>
        </div>

        <!-- Common Filters -->
        <form method="POST" style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: var(--shadow);">
            <div class="filter-grid">
                <div class="filter-item">
                    <label>Search USN/Name</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student...">
                </div>
                <!-- ... -->
                <div class="filter-item">
                    <label>Faculty</label>
                    <select name="faculty[]">
                        <option value="">All Faculties</option>
                        <?php foreach ($faculties as $f): ?>
                        <option value="<?php echo htmlspecialchars($f); ?>" <?php echo in_array($f, $faculty_filter) ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Discipline</label>
                    <select name="discipline[]">
                        <option value="">All Disciplines</option>
                        <?php foreach ($disciplines as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo in_array($d, $discipline_filter) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Min SGPA</label>
                    <input type="number" name="min_sgpa" value="<?php echo $min_sgpa > 0 ? htmlspecialchars($min_sgpa) : ''; ?>" step="0.01" placeholder="e.g. 8.0">
                </div>
                <div class="filter-item">
                    <label>Semester</label>
                    <select name="sem" onchange="this.form.submit()">
                        <option value="">All Semesters (5-8)</option>
                        <?php foreach ([5, 6, 7, 8] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $sem_filter === $s ? 'selected' : ''; ?>>Semester <?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; border-top: 1px solid #f1f5f9; pt: 15px; padding-top: 15px;">
                <span class="filtered-count">Filtered Count: <strong><?php echo ($section === 'details') ? $total_records : count($aiReports); ?></strong></span>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" name="reset_filters" value="1" class="btn btn-clear">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <?php if ($section === 'details'): ?>
                    <button type="submit" name="export" value="1" class="btn btn-excel">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Panel: Student Details -->
        <div id="panel-details" class="panel <?php echo $section === 'details' ? 'active' : ''; ?>">

            <div class="table-container">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>USN</th>
                                <th>Name</th>
                                <th>Gnd</th>
                                <th>Inst</th>
                                <th>Sem</th>
                                <th>PUC %</th>
                                <th>10th %</th>
                                <th>Discipline</th> 
                                <th>Father's Name</th>
                                <th>Mother's Name</th>
                                <th>Parent Mob.</th>
                                <th>Student Mob.</th>
                                <th style="min-width: 150px;">Email ID</th>
                                <th>SGPA</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailsStudents as $index => $student):
                                $rowNum = $offset + $index + 1;
                                $currStmt = $localDB->prepare("SELECT semester FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                                $currStmt->execute([$student['usn'], $student['institution']]);
                                $cs = $currStmt->fetchColumn();
                                if (!$cs && $student['institution'] === INSTITUTION_GMU) {
                                     $cs = $student['max_sem'] ?? null;
                                     if (!$cs) {
                                         $remoteStmt = $db->prepare("SELECT MAX(sem) FROM {$gmuPrefix}ad_student_approved WHERE usn = ?");
                                         $remoteStmt->execute([$student['usn']]);
                                         $cs = $remoteStmt->fetchColumn();
                                     }
                                }
                            ?>
                            <tr>
                                <td style="color:#94a3b8;"><?php echo $rowNum; ?></td>
                                <td class="usn-font"><?php echo htmlspecialchars($student['usn']); ?></td>
                                <td><span class="student-name"><?php echo htmlspecialchars($student['name']); ?></span></td>
                                <td><?php echo htmlspecialchars($student['gender'] ?? '-'); ?></td>
                                <td><?php echo $student['institution']; ?></td>
                                <td style="font-weight: 700; color: var(--primary-maroon);"><?php echo $cs ?? '-'; ?></td>
                                <td class="score-val"><?php echo $student['puc_percentage'] ? round($student['puc_percentage']).'%' : '-'; ?></td>
                                <td class="score-val"><?php echo $student['sslc_percentage'] ? round($student['sslc_percentage']).'%' : '-'; ?></td>
                                <td style="font-size: 10px;"><?php echo htmlspecialchars($student['discipline'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['father_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['mother_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['parent_mobile'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['student_mobile'] ?? '-'); ?></td>
                                <td style="font-size: 10px; color: #64748b;"><?php echo htmlspecialchars($student['email_id'] ?? '-'); ?></td>
                                <td>
                                    <button class="btn-view" type="button" onclick="openSGPAModal('<?php echo htmlspecialchars($student['usn']); ?>','<?php echo htmlspecialchars($student['institution']); ?>','<?php echo htmlspecialchars($student['name']); ?>')">
                                        <i class="fas fa-chart-line"></i> SGPA
                                    </button>
                                </td>
                                <td>
                                    <button class="btn-view" type="button"
                                            onclick="openPortfolio('<?php echo htmlspecialchars($student['usn']); ?>','<?php echo htmlspecialchars($student['institution']); ?>','<?php echo htmlspecialchars($student['name']); ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($detailsStudents)): ?>
                            <tr><td colspan="15" style="text-align:center; padding:40px; color:#94a3b8;">No records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
            <form method="POST" id="paginationForm" style="display:none;"><input type="hidden" name="page" id="pageNum"></form>
            <div class="pagination">
                <?php
                if ($page > 1) {
                    echo '<a href="javascript:void(0)" onclick="goToPage('.($page-1).')" class="page-link">&laquo;</a>';
                }

                $range = 2; 
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);

                if ($start > 1) {
                    echo '<a href="javascript:void(0)" onclick="goToPage(1)" class="page-link">1</a>';
                    if ($start > 2) echo '<span style="color:#94a3b8; padding: 0 10px;">...</span>';
                }

                for ($i = $start; $i <= $end; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<a href="javascript:void(0)" onclick="goToPage('.$i.')" class="page-link ' . $active . '">' . $i . '</a>';
                }

                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<span style="color:#94a3b8; padding: 0 10px;">...</span>';
                    echo '<a href="javascript:void(0)" onclick="goToPage('.$total_pages.')" class="page-link">' . $total_pages . '</a>';
                }

                if ($page < $total_pages) {
                    echo '<a href="javascript:void(0)" onclick="goToPage('.($page+1).')" class="page-link">&raquo;</a>';
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
        </div>

        <!-- Panel: AI Reports -->
        <div id="panel-reports" class="panel <?php echo $section === 'reports' ? 'active' : ''; ?>">
            <div class="table-container">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th><th>Institution</th><th>Academic</th><th>Round</th><th>Score</th><th>Date</th><th>AI Round PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aiReports as $report):
                                $overall = (int)($report['score'] ?? 0);
                                $scoreClass = ($overall >= 80) ? 'score-high' : (($overall >= 60) ? 'score-mid' : 'score-low');
                                $type = strtolower($report['assessment_type'] ?? '');
                                $typeClass = 'round-aptitude';
                                if (strpos($type, 'technical') !== false || strpos($type, 'mock ai') !== false) $typeClass = 'round-technical';
                                elseif (strpos($type, 'hr') !== false) $typeClass = 'round-hr';
                                elseif (strpos($type, 'skill verify') !== false) $typeClass = 'round-skill-verify';
                                elseif (strpos($type, 'project verify') !== false) $typeClass = 'round-project-verify';
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($report['student_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size: 12px; color: #999;">USN: <?php echo htmlspecialchars($report['usn'] ?? 'N/A'); ?></div>
                                </td>
                                <td><span class="round-tag"><?php echo htmlspecialchars($report['institution'] ?? 'GMU'); ?></span></td>
                                <td>
                                    <div style="font-size: 13px; font-weight: 600; color: var(--primary-maroon);"><?php echo htmlspecialchars($report['company_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($report['branch'] ?? 'N/A'); ?> (Sem <?php echo htmlspecialchars($report['current_sem'] ?? '-'); ?>)</div>
                                </td>
                                <td><span class="round-tag <?php echo $typeClass; ?>"><?php echo htmlspecialchars($report['assessment_type'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <span class="score-badge <?php echo $scoreClass; ?>"><?php echo $overall; ?>%</span>
                                    <?php if (($report['status'] ?? '') === 'active'): ?><br><span style="font-size:10px; color:#f39c12;">(Ongoing)</span><?php endif; ?>
                                </td>
                                <td style="font-size: 13px; color: #666;"><?php echo date('d M Y', strtotime($report['started_at'] ?? 'now')); ?></td>
                                <td>
                                    <?php
                                    $studentReports = findStudentReportsOfficer($report['usn'] ?? '');
                                    if (!empty($studentReports)):
                                        foreach ($studentReports as $pdf):
                                    ?>
                                    <div style="margin-bottom: 6px;">
                                        <a href="../<?php echo htmlspecialchars($pdf['path']); ?>" target="_blank" class="round-tag <?php echo $pdf['type'] === 'HR' ? 'round-hr' : 'round-technical'; ?>" style="text-decoration: none; padding: 5px 10px;">
                                            <i class="far fa-file-pdf"></i> <?php echo htmlspecialchars($pdf['type']); ?> Report
                                        </a>
                                    </div>
                                    <?php endforeach; else: ?>
                                    <span style="color: #999; font-size: 12px;">No reports yet</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- SGPA Modal -->
    <div id="sgpaModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-modal" onclick="closeSGPAModal()">&times;</span>
            <div style="border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="color: var(--primary-maroon); margin: 0; font-size: 20px;">Semester-wise SGPA</h2>
                <div id="sgpaMeta" style="color:#64748b; font-weight: 600; font-size: 14px; margin-top: 5px;"></div>
            </div>
            <div id="sgpaLoading" style="text-align: center; padding: 20px; color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            <div id="sgpaError" style="color:#b91c1c; display:none; padding: 20px; text-align: center;"></div>
            <div id="sgpaContent" style="display:none;">
                <table style="font-size: 14px; width: 100%;">
                    <thead>
                        <tr>
                            <th style="font-size: 12px; padding: 12px;">Semester</th>
                            <th style="font-size: 12px; padding: 12px; text-align: center;">SGPA</th>
                        </tr>
                    </thead>
                    <tbody id="sgpaTableBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Portfolio Modal -->
    <div id="portfolioModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closePortfolioModal()">&times;</span>
            <div style="display:flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="color: var(--primary-maroon); margin: 0; font-size: 20px;">Student Portfolio</h2>
                <div id="portfolioMeta" style="color:#64748b; font-weight: 600; font-size: 14px;"></div>
            </div>
            <div style="margin-bottom: 20px; display:flex; gap: 10px;">
                <button type="button" class="btn tab-btn active" onclick="showPortfolioTab('Skill')" id="tabSkillBtn" style="flex: 1; border: 1px solid var(--primary-maroon);">Skills</button>
                <button type="button" class="btn tab-btn" onclick="showPortfolioTab('Project')" id="tabProjectBtn" style="flex: 1; border: 1px solid #ddd; background: #fff; color: #666;">Projects</button>
            </div>
            <div id="portfolioLoading" style="text-align: center; padding: 20px; color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            <div id="portfolioError" style="color:#b91c1c; display:none; padding: 20px; text-align: center;"></div>
            <div id="portfolioSkill"></div>
            <div id="portfolioProject" style="display:none;"></div>
        </div>
    </div>

    <script>
        function showPortfolioTab(tab) {
            document.getElementById('portfolioSkill').style.display = (tab === 'Skill') ? 'block' : 'none';
            document.getElementById('portfolioProject').style.display = (tab === 'Project') ? 'block' : 'none';
            
            const skillBtn = document.getElementById('tabSkillBtn');
            const projectBtn = document.getElementById('tabProjectBtn');
            
            if (tab === 'Skill') {
                skillBtn.style.background = 'var(--primary-maroon)'; skillBtn.style.color = 'white'; skillBtn.style.borderColor = 'var(--primary-maroon)';
                projectBtn.style.background = '#fff'; projectBtn.style.color = '#666'; projectBtn.style.borderColor = '#ddd';
            } else {
                projectBtn.style.background = 'var(--primary-maroon)'; projectBtn.style.color = 'white'; projectBtn.style.borderColor = 'var(--primary-maroon)';
                skillBtn.style.background = '#fff'; skillBtn.style.color = '#666'; skillBtn.style.borderColor = '#ddd';
            }
        }

        function closePortfolioModal() { document.getElementById('portfolioModal').style.display = 'none'; }

        async function openPortfolio(usn, institution, name) {
            document.getElementById('portfolioMeta').innerText = name + ' (' + usn + ')';
            document.getElementById('portfolioLoading').style.display = 'block';
            document.getElementById('portfolioError').style.display = 'none';
            document.getElementById('portfolioSkill').innerHTML = '';
            document.getElementById('portfolioProject').innerHTML = '';
            document.getElementById('portfolioModal').style.display = 'flex';
            showPortfolioTab('Skill');

            try {
                const formData = new FormData();
                formData.append('usn', usn);
                formData.append('institution', institution);

                const res = await fetch(`portfolio_details.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                document.getElementById('portfolioLoading').style.display = 'none';
                if (!data.success) {
                    document.getElementById('portfolioError').innerText = data.message;
                    document.getElementById('portfolioError').style.display = 'block';
                    return;
                }
                document.getElementById('portfolioSkill').innerHTML = renderPortfolioItems(data.skills);
                document.getElementById('portfolioProject').innerHTML = renderPortfolioItems(data.projects);
            } catch (e) {
                document.getElementById('portfolioLoading').style.display = 'none';
                document.getElementById('portfolioError').innerText = 'Failed to load details.';
                document.getElementById('portfolioError').style.display = 'block';
            }
        }

        function renderPortfolioItems(items) {
            if (!items || items.length === 0) return '<div style="text-align: center; color: #999; padding: 20px;">No items found.</div>';
            return items.map(it => `
                <div style="border: 1px solid #eee; border-radius: 10px; padding: 15px; margin-bottom: 12px; background: #fafafa;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 700; color: #333;">${it.title}</span>
                        <span class="round-tag ${it.is_verified ? 'round-hr' : ''}">${it.is_verified ? 'Verified' : 'Pending Verification'}</span>
                    </div>
                    ${it.sub_title ? `<div style="font-size: 13px; color: #666; margin-bottom: 5px;">${it.sub_title}</div>` : ''}
                    <div style="font-size: 13px; color: #444; line-height: 1.5;">${it.description}</div>
                    ${it.link ? `<a href="${it.link}" target="_blank" style="display: inline-block; margin-top: 10px; color: var(--primary-maroon); font-size: 12px; font-weight: 600;">View Resource <i class="fas fa-external-link-alt"></i></a>` : ''}
                </div>
            `).join('');
        }

        window.onclick = e => { 
            if (e.target.id === 'portfolioModal') closePortfolioModal(); 
            if (e.target.id === 'sgpaModal') closeSGPAModal();
        };

        function closeSGPAModal() { document.getElementById('sgpaModal').style.display = 'none'; }

        async function openSGPAModal(usn, institution, name) {
            document.getElementById('sgpaMeta').innerText = name + ' (' + usn + ')';
            document.getElementById('sgpaLoading').style.display = 'block';
            document.getElementById('sgpaError').style.display = 'none';
            document.getElementById('sgpaContent').style.display = 'none';
            document.getElementById('sgpaModal').style.display = 'flex';

            try {
                const formData = new FormData();
                formData.append('usn', usn);
                formData.append('institution', institution);

                const res = await fetch(`get_sgpa_details.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                document.getElementById('sgpaLoading').style.display = 'none';
                
                if (!data.success) {
                    document.getElementById('sgpaError').innerText = data.message;
                    document.getElementById('sgpaError').style.display = 'block';
                    return;
                }

                let html = '';
                for (let sem = 1; sem <= 8; sem++) {
                    const val = data.sgpa[sem] || '-';
                    html += `<tr>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-weight: 600;">Semester ${sem}</td>
                        <td style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: center; font-weight: 700; color: var(--primary-maroon);">${val}</td>
                    </tr>`;
                }
                document.getElementById('sgpaTableBody').innerHTML = html;
                document.getElementById('sgpaContent').style.display = 'block';
            } catch (e) {
                document.getElementById('sgpaLoading').style.display = 'none';
                document.getElementById('sgpaError').innerText = 'Failed to load SGPA details.';
                document.getElementById('sgpaError').style.display = 'block';
            }
        }
    </script>
</body>
</html>
