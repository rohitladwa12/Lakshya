<?php
/**
 * VC - Student Analysis (Deep Dive)
 * Directly adapted from Placement Officer's reports.php as requested
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

requireRole(ROLE_VC);

require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_VC);

$pageId = 'vc_student_analysis';

// Handle POST and Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_filters'])) {
        SessionFilterHelper::clearFilters($pageId);
    } else {
        SessionFilterHelper::handlePostToSession($pageId, $_POST);
    }
    header("Location: student_analysis.php");
    exit;
}

// Handle GET tab switching (manual redirect to session via POST is preferred, but we support GET for direct links if needed, immediately redirecting)
if (isset($_GET['section']) || isset($_GET['inst'])) {
    $updates = [];
    if (isset($_GET['section'])) $updates['section'] = $_GET['section'];
    if (isset($_GET['inst'])) $updates['inst'] = $_GET['inst'];
    SessionFilterHelper::updateFilters($pageId, $updates);
    header("Location: student_analysis.php");
    exit;
}

$filters = SessionFilterHelper::getFilters($pageId);

$section = $filters['section'] ?? 'details';
if (!in_array($section, ['details', 'reports', 'portfolio'], true)) $section = 'details';

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

// Model for reports
$officerModel = new PlacementOfficer();
$reportFilters = [];
if ($instFilter) $reportFilters['institution'] = $instFilter;
if ($sem_filter) $reportFilters['semesters'] = [$sem_filter];
$aiReports = $officerModel->getUnifiedAIReports($reportFilters);

// Filter
$aiReports = array_filter($aiReports, function($r) use ($sem_filter) {
    $s = (int)($r['current_sem'] ?? 0);
    if ($sem_filter) return $s === $sem_filter;
    return in_array($s, [5, 6, 7, 8]);
});

function findStudentReportsVC($usn) {
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
    return $reports;
}

$db = getDB('gmu');
$localDB = getDB();
$limit = 50;
$page = $filters['page'] ?? 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

function buildInClauseVC($column, $values, &$params) {
    if (empty($values)) return "";
    $values = array_filter($values, function($v) { return $v !== ''; });
    if (empty($values)) return "";
    $placeholders = [];
    foreach ($values as $val) { $placeholders[] = "?"; $params[] = $val; }
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
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

if ($faculty_filter_sql = buildInClauseVC("asa.faculty", $faculty_filter, $params)) $where_clauses[] = $faculty_filter_sql;
if ($discipline_filter_sql = buildInClauseVC("asa.discipline", $discipline_filter, $params)) $where_clauses[] = $discipline_filter_sql;
if ($min_sgpa > 0) { $where_clauses[] = "asa.sgpa >= ?"; $params[] = $min_sgpa; }

$where_sql = implode(" AND ", $where_clauses);
$count_query = "SELECT COUNT(DISTINCT asa.usn) FROM {$combinedApproved} asa WHERE $where_sql";
$stmt = $db->prepare($count_query); $stmt->execute($params);
$total_records = $stmt->fetchColumn(); $total_pages = ceil($total_records / $limit);

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
$stmt = $db->prepare($details_query); $stmt->execute($params); $detailsStudents = $stmt->fetchAll();

// Fetch faculties and disciplines for dropdowns
function getDistinctVC($db, $column) {
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
        // Identifiers ($column, $gmitCol) are now whitelisted, so this is safe
        return $db->query($sql)->fetchAll(PDO::FETCH_COLUMN); 
    } catch (Exception $e) { 
        return []; 
    }
}
$faculties = getDistinctVC($db, 'faculty');
$disciplines = getDistinctVC($db, 'discipline');

$GLOBALS['fullName'] = getFullName();
renderVCHeader("Student Analysis Deep Dive");
?>

<style>
    .tabs-main { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
    .tab-main { padding: 12px 24px; text-decoration: none; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; }
    .tab-main.active { color: var(--primary-maroon); border-bottom-color: var(--primary-maroon); }
    
    .tabs-inst { display: flex; gap: 8px; margin-bottom: 25px; }
    .tab-inst { padding: 8px 16px; text-decoration: none; background: #fff; color: #64748b; font-weight: 600; border-radius: 10px; font-size: 13px; border: 1px solid #e2e8f0; }
    .tab-inst.active { background: var(--primary-maroon); color: white; border-color: var(--primary-maroon); }
    
    .score-badge { font-weight: 800; padding: 4px 10px; border-radius: 6px; font-size: 13px; }
    .score-high { background: #dcfce7; color: #166534; }
    .score-mid { background: #fef9c3; color: #854d0e; }
    .score-low { background: #fee2e2; color: #991b1b; }
    
    .round-tag { font-size: 11px; padding: 4px 10px; border-radius: 6px; font-weight: 700; text-decoration: none; display: inline-block; margin-right: 5px; } /* Simplified tags */
    .round-hr { background: #dcfce7; color: #166534; }

    /* Modal Styles */
    .modal {
        display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;
    }
    .modal-content {
        background-color: #fefefe; padding: 30px; border-radius: 20px; width: 90%; max-width: 700px;
        position: relative; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .close-modal {
        position: absolute; right: 20px; top: 20px; font-size: 28px; font-weight: bold;
        color: #94a3b8; cursor: pointer; transition: color 0.2s;
    }
    .close-modal:hover { color: var(--primary-maroon); }
    .tab-btn { flex: 1; padding: 10px; border-radius: 10px; cursor: pointer; font-weight: 700; transition: all 0.2s; }
</style>

<div class="header">
    <div class="view-title">
        <h2>Student Analysis Deep Dive</h2>
        <p>Comprehensive student performance, portfolios, and AI reports.</p>
    </div>
</div>

<nav class="tabs-main">
    <form method="POST" style="display: contents;">
        <input type="hidden" name="section" value="details">
        <button type="submit" class="tab-main <?php echo $section === 'details' ? 'active' : ''; ?>" style="background:none; border:none; padding:12px 24px; font-family:inherit; cursor:pointer;">Student Details</button>
    </form>
    <form method="POST" style="display: contents;">
        <input type="hidden" name="section" value="reports">
        <button type="submit" class="tab-main <?php echo $section === 'reports' ? 'active' : ''; ?>" style="background:none; border:none; padding:12px 24px; font-family:inherit; cursor:pointer;">AI Assessment Reports</button>
    </form>
</nav>

<div class="tabs-inst">
    <form method="POST" style="display: contents;">
        <input type="hidden" name="inst" value="all">
        <button type="submit" class="tab-inst <?php echo $inst === 'all' ? 'active' : ''; ?>" style="font-family:inherit; cursor:pointer;">All Institutions</button>
    </form>
    <form method="POST" style="display: contents;">
        <input type="hidden" name="inst" value="gmu">
        <button type="submit" class="tab-inst <?php echo $inst === 'gmu' ? 'active' : ''; ?>" style="font-family:inherit; cursor:pointer;">GMU Only</button>
    </form>
    <form method="POST" style="display: contents;">
        <input type="hidden" name="inst" value="gmit">
        <button type="submit" class="tab-inst <?php echo $inst === 'gmit' ? 'active' : ''; ?>" style="font-family:inherit; cursor:pointer;">GMIT Only</button>
    </form>
</div>

<!-- Filters -->
<form method="POST" class="filter-section">
    <input type="hidden" name="section" value="<?php echo $section; ?>">
    <input type="hidden" name="inst" value="<?php echo $inst; ?>">
    <div class="filter-grid">
        <div class="filter-item">
            <label>Search USN/Name</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student...">
        </div>
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
            <label>Semester</label>
            <select name="sem">
                <option value="">All (5-8)</option>
                <?php foreach ([5,6,7,8] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $sem_filter == $s ? 'selected' : ''; ?>>Semester <?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div style="margin-top:20px; display:flex; justify-content: space-between; align-items: center;">
        <span style="font-size:14px; color:#64748b">Results: <strong><?php echo $section === 'details' ? $total_records : count($aiReports); ?></strong></span>
        <div style="display:flex; gap:10px">
            <button type="submit" name="reset_filters" value="1" class="btn-secondary"><i class="fas fa-undo"></i> Reset Filters</button>
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
        </div>
    </div>
</form>

<?php if ($section === 'details'): ?>
<div class="table-container">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>USN</th><th>Name</th><th>Inst</th><th>Sem</th><th>PUC %</th><th>10th %</th><th>Discipline</th><th>Contact</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detailsStudents as $s): 
                    // Fetch Current Semester from student_sem_sgpa (GMIT & GMU verified)
                    $csStmt = $localDB->prepare("SELECT semester FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                    $csStmt->execute([$s['usn'], $s['institution']]);
                    $cs = $csStmt->fetchColumn();
                ?>
                <tr>
                    <td class="usn-font"><?php echo htmlspecialchars($s['usn']); ?></td>
                    <td><span class="student-name"><?php echo htmlspecialchars($s['name']); ?></span></td>
                    <td><span class="badge badge-<?php echo strtolower($s['institution']); ?>"><?php echo $s['institution']; ?></span></td>
                    <td style="font-weight:800; color:var(--primary-maroon)"><?php echo $cs ?: ($s['max_sem'] ?: '-'); ?></td>
                    <td style="font-weight:600; color:var(--success)"><?php echo $s['puc_percentage'] ? round($s['puc_percentage']).'%' : '-'; ?></td>
                    <td style="font-weight:600; color:var(--success)"><?php echo $s['sslc_percentage'] ? round($s['sslc_percentage']).'%' : '-'; ?></td>
                    <td style="font-size:12px"><?php echo htmlspecialchars($s['discipline']); ?></td>
                    <td style="font-size:12px; color:#64748b"><?php echo htmlspecialchars($s['student_mobile']); ?></td>
                    <td>
                        <button type="button" class="btn-view" onclick="openPortfolio('<?php echo htmlspecialchars($s['usn']); ?>','<?php echo htmlspecialchars($s['institution']); ?>','<?php echo htmlspecialchars($s['name']); ?>')">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <form method="POST" id=" paginationForm" style="display:none;"><input type="hidden" name="page" id="pageNum"></form>
    <div class="pagination">
        <?php
        $qShort = []; // Not needed for links since we use JS post-back
        
        if ($page > 1) {
            echo '<a href="javascript:void(0)" onclick="goToPage(' . ($page - 1) . ')" class="page-link">&laquo;</a>';
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
            echo '<a href="javascript:void(0)" onclick="goToPage(' . $i . ')" class="page-link ' . $active . '">' . $i . '</a>';
        }

        if ($end < $total_pages) {
            if ($end < $total_pages - 1) echo '<span style="color:#94a3b8; padding: 0 10px;">...</span>';
            echo '<a href="javascript:void(0)" onclick="goToPage(' . $total_pages . ')" class="page-link">' . $total_pages . '</a>';
        }

        if ($page < $total_pages) {
            echo '<a href="javascript:void(0)" onclick="goToPage(' . ($page + 1) . ')" class="page-link">&raquo;</a>';
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
<?php else: ?>
<div class="table-container">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th><th>Institution</th><th>Academic Context</th><th>Round</th><th>Score</th><th>Date</th><th>AI Round PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aiReports as $r): 
                    $score = (int)$r['score'];
                    $sClass = $score >= 80 ? 'score-high' : ($score >= 60 ? 'score-mid' : 'score-low');
                ?>
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--primary-maroon)"><?php echo htmlspecialchars($r['full_name'] ?? $r['student_name'] ?? 'Unknown'); ?></div>
                        <div style="font-size:12px; color:#94a3b8"><?php echo htmlspecialchars($r['usn']); ?></div>
                    </td>
                    <td><span class="badge badge-<?php echo strtolower($r['institution']); ?>"><?php echo $r['institution']; ?></span></td>
                    <td>
                        <div style="font-weight:600"><?php echo htmlspecialchars($r['company_name']); ?></div>
                        <div style="font-size:12px; color:#64748b"><?php echo htmlspecialchars($r['branch']); ?> (Sem <?php echo $r['current_sem']; ?>)</div>
                    </td>
                    <td><span class="badge" style="background:#f1f5f9; color:#475569"><?php echo htmlspecialchars($r['assessment_type']); ?></span></td>
                    <td><span class="score-badge <?php echo $sClass; ?>"><?php echo $score; ?>%</span></td>
                    <td style="font-size:13px"><?php echo date('d M Y', strtotime($r['started_at'])); ?></td>
                    <td>
                        <?php 
                        $pdfs = findStudentReportsVC($r['usn']);
                        foreach ($pdfs as $pdf):
                        ?>
                        <a href="../<?php echo $pdf['path']; ?>" target="_blank" class="round-tag <?php echo $pdf['type'] === 'HR' ? 'badge-gmit' : 'badge-gmu'; ?>">
                            <i class="far fa-file-pdf"></i> <?php echo $pdf['type']; ?>
                        </a>
                        <?php endforeach; ?>
                        <?php if (empty($pdfs)): ?><span style="color:#cbd5e1; font-size:12px">No PDF yet</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Portfolio Modal -->
<div id="portfolioModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closePortfolioModal()">&times;</span>
        <div style="display:flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
            <h2 style="color: var(--primary-maroon); margin: 0; font-size: 20px;">Student Portfolio</h2>
            <div id="portfolioMeta" style="color:#64748b; font-weight: 600; font-size: 14px;"></div>
        </div>
        <div style="margin-bottom: 20px; display:flex; gap: 10px;">
            <button type="button" class="tab-btn active" onclick="showPortfolioTab('Skill')" id="tabSkillBtn">Skills</button>
            <button type="button" class="tab-btn" onclick="showPortfolioTab('Project')" id="tabProjectBtn">Projects</button>
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
            const res = await fetch(`portfolio_details.php?usn=${usn}&institution=${institution}`);
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

    window.onclick = e => { if (e.target.id === 'portfolioModal') closePortfolioModal(); };
</script>

<?php renderVCFooter(); ?>
