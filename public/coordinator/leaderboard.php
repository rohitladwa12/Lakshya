<?php
/**
 * Coordinator - Student Leaderboard
 * Rankings based on Academic, AI Assessment, and Portfolio quality
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Helpers/SessionFilterHelper.php';
use App\Services\LeaderboardService;
use App\Helpers\SessionFilterHelper;

requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$myDepartment = getDepartment();
$myInst = getInstitution();

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SessionFilterHelper::handlePostToSession('coord_leaderboard', $_POST, 'leaderboard.php');
}

// Retrieve from Session
$persistedFilters = SessionFilterHelper::getFilters('coord_leaderboard');

$view = $persistedFilters['view'] ?? 'local';
$inst_filter = $persistedFilters['inst'] ?? 'all';
$sem_filter = (int)($persistedFilters['sem'] ?? 0);
$selected_skills = $persistedFilters['skills'] ?? [];

$studentModel = new StudentProfile();
$officerModel = new PlacementOfficer();
$all_skills = LeaderboardService::getAllAvailableSkills();

// Fetch Default Academic Year
$dbGmu = getDB('gmu');
$defaultYearQuery = "SELECT MAX(academic_year) as max_year FROM ad_student_approved";
$yearRes = $dbGmu->query($defaultYearQuery);
$defaultYearData = $yearRes->fetch();
$defaultAcademicYear = $defaultYearData['max_year'] ?? date('Y') . '-' . (date('Y') + 1);

// Define Scope & Academic Filters
$coordFilters = [];
if ($view === 'local') {
    list($deptGmu, $deptGmit) = getCoordinatorDisciplineFilters($myDepartment);
    $coordFilters['discipline'] = [$deptGmu, $deptGmit];
    if ($inst_filter !== 'all') {
        $coordFilters['institution'] = (strtoupper($inst_filter) === 'GMIT') ? INSTITUTION_GMIT : INSTITUTION_GMU;
    } else {
        $coordFilters['institution'] = $myInst;
    }
} else {
    if ($inst_filter !== 'all') {
        $coordFilters['institution'] = (strtoupper($inst_filter) === 'GMIT') ? INSTITUTION_GMIT : INSTITUTION_GMU;
    }
}

// Enforce coordinator semester scope (Only Semesters 5, 6, 7, 8 are eligible)
if ($sem_filter > 0) {
    // If specific semester selected, must be within 5-8
    $coordFilters['semesters'] = [max(5, min(8, $sem_filter))];
} else {
    // If "Any" selected, default to the whole 5-8 range
    $coordFilters['semesters'] = [5, 6, 7, 8];
}

// Map advanced performance filters from session
if (isset($persistedFilters['min_sgpa_all']) && $persistedFilters['min_sgpa_all'] !== '') $coordFilters['min_sgpa_all'] = (float)$persistedFilters['min_sgpa_all'];
if (isset($persistedFilters['min_apt']) && $persistedFilters['min_apt'] !== '') $coordFilters['min_aptitude'] = (float)$persistedFilters['min_apt'];
if (isset($persistedFilters['min_tech']) && $persistedFilters['min_tech'] !== '') $coordFilters['min_technical'] = (float)$persistedFilters['min_tech'];
if (isset($persistedFilters['min_hr']) && $persistedFilters['min_hr'] !== '') $coordFilters['min_hr'] = (float)$persistedFilters['min_hr'];
if (isset($persistedFilters['min_total']) && $persistedFilters['min_total'] !== '') $coordFilters['min_total'] = (float)$persistedFilters['min_total'];
if (!empty($selected_skills)) $coordFilters['required_skills'] = $selected_skills;

// 1. Fetch Students (Filtered by Semester/Institution)
$students = $studentModel->getAllWithUsers($coordFilters);
if (empty($students)) {
    $leaderboard = [];
    $display_leaderboard = [];
    $total_entries = 0;
    $total_pages = 0;
} else {
    $usns = array_column($students, 'usn');
    $usnList = "'" . implode("','", array_map('addslashes', $usns)) . "'";

    // 2-4. Fetch Rankings using Centralized Service for Absolute Consistency
    $leaderboard = LeaderboardService::getRankings($coordFilters);

    $display_leaderboard = $leaderboard; // No pagination in this snippet, keeping it simple
    $total_entries = count($leaderboard);

    // Sort by Total Score Descending (with SGPA as tie-breaker)
    usort($leaderboard, function($a, $b) {
        if ($b['total'] == $a['total']) {
            return $b['sgpa'] <=> $a['sgpa'];
        }
        return $b['total'] <=> $a['total'];
    });

    // Assign Rank
    foreach ($leaderboard as $idx => &$entry) {
        $entry['rank'] = $idx + 1;
    }
    unset($entry);

    // Pagination
    $showAll = (bool)($persistedFilters['show_all'] ?? false);
    $limit = $showAll ? count($leaderboard) : 20;
    $page = $persistedFilters['page'] ?? 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $total_entries = count($leaderboard);
    $total_pages = $showAll ? 1 : ceil($total_entries / $limit);
    $display_leaderboard = array_slice($leaderboard, $offset, $limit);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e293b;
            --accent: #800000;
            --bg: #f8fafc;
            --white: #ffffff;
            --text: #0f172a;
            --text-light: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            line-height:1.5;
        }

        .container {
            width: 100%;
            padding: 30px 40px;
            box-sizing: border-box;
        }

        .header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header p { color: var(--text-light); margin: 5px 0 0 0; font-size:14px; }

        .filters {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            align-items: end;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .filter-item label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .filter-item select, .filter-item input {
            width: 100%;
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 13px;
            background: #fff;
            color: var(--text);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            grid-column: 1 / -1;
            justify-content: flex-end;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            user-select: none;
        }

        .btn i { font-size: 16px; }

        .btn-primary { 
            background: var(--primary); 
            color: white; 
            border-color: var(--primary); 
            box-shadow: 0 4px 6px -1px rgba(30, 41, 59, 0.1);
        }
        .btn-primary:hover { 
            background: #334155; 
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(30, 41, 59, 0.15);
        }

        .btn-secondary { 
            background: white; 
            color: #475569; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-secondary:hover { 
            background: #f8fafc; 
            color: var(--text); 
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .btn-success { 
            background: #059669; 
            color: white; 
            border-color: #059669;
            box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.1);
        }
        .btn-success:hover { 
            background: #047857; 
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.15);
        }
        .btn:active { transform: translateY(0); }

        .leaderboard-table {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc;
            text-align: left;
            padding: 12px 15px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            border-bottom: 2px solid var(--border);
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            vertical-align: middle;
        }
        tr:hover { background: #fbfcfd; }

        .rank-num { font-weight: 700; color: #64748b; font-size: 15px; }
        .student-name { font-weight: 600; color: var(--text); display: block; }
        .student-usn { font-family: monospace; color: var(--text-light); font-size: 12px; }
        
        .score-val { font-weight: 600; text-align: center; display: block; }
        .total-badge {
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 700;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .total-badge:hover {
            background: white;
            border-color: #cbd5e1;
            transform: scale(1.05);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 25px;
        }
        .page-link {
            padding: 6px 12px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-light);
            font-size: 13px;
        }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* Multi-select Tags */
        .multi-select-container { position: relative; }
        .selected-tags {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 4px 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            min-height: 38px;
            align-items: center;
        }
        .tag {
            background: #f1f5f9;
            color: var(--text);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .tag i { cursor: pointer; opacity: 0.5; }
        .tag i:hover { opacity: 1; color: var(--accent); }
        .selected-tags input { border: none; outline: none; padding: 4px; font-size: 12px; flex: 1; }
        .skill-dropdown {
            position: absolute; top: 100%; left: 0; width: 100%;
            background: white; border: 1px solid var(--border);
            z-index: 100; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto;
        }
        .skill-option { padding: 8px 12px; font-size: 13px; cursor: pointer; }
        .skill-option:hover { background: #f8fafc; }

        .methodology-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            margin-bottom: 25px;
            overflow: hidden;
            display: none;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .methodology-card.active { display: block; }
        .methodology-header {
            background: #f8fafc;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .methodology-body { padding: 20px; }
        .rule-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .rule-item { 
            padding: 12px; 
            border-radius: 12px; 
            background: #f1f5f9; 
            font-size: 12px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .rule-item.warning { background: #fff1f2; color: #991b1b; border: 1px solid #fecaca; }
        .rule-icon { width: 24px; height: 24px; border-radius: 6px; background: white; display: flex; align-items: center; justify-content: center; shrink: 0; }

        /* Breakdown Modal specific */
        .breakdown-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .breakdown-row:last-child { border-bottom: none; border-top: 2px solid #e2e8f0; margin-top: 10px; padding-top: 15px; }
        .breakdown-label { font-size: 14px; color: #64748b; }
        .breakdown-val { font-weight: 700; color: var(--text); }
        .breakdown-total { font-size: 18px; color: var(--primary); }

        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; top: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            align-items: center; 
            justify-content: center; 
            backdrop-filter: blur(4px);
        }
        .modal-content { 
            background: white; 
            padding: 30px; 
            border-radius: 20px; 
            width: 450px; 
            max-width: 90%; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); 
        }
        .modal-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
        }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; }
        /* Custom Modal for Push to Pool */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            margin-bottom: 20px;
        }
        .modal-header h3 {
            margin: 0;
            color: var(--text);
            font-size: 1.25rem;
        }
        .modal-field {
            margin-bottom: 15px;
        }
        .modal-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-light);
        }
        .modal-field input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .modal-footer {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="header">
            <div>
                <h1>Placement Leaderboard</h1>
                <p>Tracking the top placement-ready students based on multi-dimensional scoring.</p>
            </div>
            <form id="viewTabForm" method="POST" class="btn-group">
                <input type="hidden" name="view" id="viewInput" value="<?php echo $view; ?>">
                <button type="button" onclick="setView('local')" class="btn <?php echo $view === 'local' ? 'btn-primary' : 'btn-secondary'; ?>">Local</button>
                <button type="button" onclick="setView('global')" class="btn <?php echo $view === 'global' ? 'btn-primary' : 'btn-secondary'; ?>">Global</button>
            </form>
            <script>
                function setView(val) {
                    document.getElementById('viewInput').value = val;
                    document.getElementById('viewTabForm').submit();
                }
            </script>
        </div>

        <div class="methodology-card" id="methodologyCard">
            <div class="methodology-header">
                <span style="font-weight: 700; color: var(--text);"><i class="fas fa-brain" style="color: var(--primary); margin-right: 8px;"></i> Scoring Methodology</span>
                <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px;" onclick="toggleInfo()">Close</button>
            </div>
            <div class="methodology-body">
                <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">
                    The leaderboard uses a high-performance weighting system: <strong>70% AI Pillar Score</strong> and <strong>30% Portfolio Rigor</strong>.
                </p>
                <div class="rule-grid">
                    <div class="rule-item">
                        <div class="rule-icon"><i class="fas fa-check" style="color: #10b981;"></i></div>
                        <div><strong>Verification Rule:</strong> Each verified Skill (+2 pts) and Project (+5 pts) contributes to the 30% Portfolio weight.</div>
                    </div>
                    <div class="rule-item">
                        <div class="rule-icon"><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i></div>
                        <div><strong>Cubic Participation:</strong> Missing any AI round (Apt, Tech, HR) applies an exponential penalty (pow 3) to the AI score.</div>
                    </div>
                    <div class="rule-item warning" style="grid-column: span 2;">
                        <div class="rule-icon" style="background: #fee2e2;"><i class="fas fa-clock"></i></div>
                        <div><strong>Inactivity Decay:</strong> Students lose 1 point from their total for every 24 hours of inactivity between assessments. Policy active from April 30, 2026.</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
            <button type="button" onclick="toggleInfo()" class="btn btn-secondary" style="font-size: 12px;">
                <i class="fas fa-info-circle"></i> View Scoring Rules
            </button>
        </div>
        <form class="filters" method="POST">
            <div class="filter-item">
                <label>Institution</label>
                <select name="inst" onchange="this.form.submit()">
                    <option value="all">All</option>
                    <option value="gmu" <?php echo $inst_filter === 'gmu' ? 'selected' : ''; ?>>GMU</option>
                    <option value="gmit" <?php echo $inst_filter === 'gmit' ? 'selected' : ''; ?>>GMIT</option>
                </select>
            </div>
            <div class="filter-item">
                <label>Semester</label>
                <select name="sem" onchange="this.form.submit()">
                    <option value="0">Any</option>
                    <?php 
                    $sems = getCoordinatorSemesterFilters($myDepartment);
                    foreach($sems as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $sem_filter == $s ? 'selected' : ''; ?>>Sem <?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Min SGPA</label>
                <select name="min_sgpa_all" onchange="this.form.submit()">
                    <option value="">Any</option>
                    <?php foreach([6, 7, 8, 9] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_sgpa_all'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Min Aptitude %</label>
                <select name="min_apt" onchange="this.form.submit()">
                    <option value="">Any</option>
                    <?php foreach([40, 50, 60, 70, 80, 90] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_apt'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Min Technical %</label>
                <select name="min_tech" onchange="this.form.submit()">
                    <option value="">Any</option>
                    <?php foreach([40, 50, 60, 70, 80, 90] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_tech'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Min HR %</label>
                <select name="min_hr" onchange="this.form.submit()">
                    <option value="">Any</option>
                    <?php foreach([40, 50, 60, 70, 80, 90] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_hr'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Academic Year</label>
                <div style="font-weight: 600; color: var(--primary); padding: 8px 0;"><?php echo htmlspecialchars($defaultAcademicYear); ?></div>
                <input type="hidden" id="defaultAcademicYear" value="<?php echo htmlspecialchars($defaultAcademicYear); ?>">
            </div>
            <div class="filter-item">
                <label>View Mode</label>
                <div style="display: flex; align-items: center; gap: 8px; padding: 8px 0;">
                    <input type="checkbox" name="show_all" id="showAllCheck" value="1" <?php echo ($showAll ?? false) ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <label for="showAllCheck" style="margin: 0; text-transform: none; font-weight: 600; cursor: pointer;">Show All</label>
                </div>
            </div>
            <div class="filter-item">
                <label>Min Total Score</label>
                <input type="number" name="min_total" value="<?php echo htmlspecialchars($persistedFilters['min_total'] ?? ''); ?>" placeholder="e.g. 70" onchange="this.form.submit()">
            </div>
            <div class="filter-item" style="grid-column: span 2;">
                <label>Skills Required</label>
                <div class="multi-select-container">
                    <div class="selected-tags" id="selectedTags">
                        <?php foreach($selected_skills as $sk): if(empty($sk)) continue; ?>
                            <div class="tag">
                                <?php echo htmlspecialchars($sk); ?>
                                <i class="fas fa-times" onclick="removeTag('<?php echo addslashes($sk); ?>')"></i>
                                <input type="hidden" name="skills[]" value="<?php echo htmlspecialchars($sk); ?>">
                            </div>
                        <?php endforeach; ?>
                        <input type="text" id="skillSearch" placeholder="Search skills..." autocomplete="off">
                    </div>
                    <div class="skill-dropdown" id="skillDropdown">
                        <?php foreach($all_skills as $sk): ?>
                            <div class="skill-option" onclick="addTag('<?php echo addslashes($sk); ?>')"><?php echo htmlspecialchars($sk); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="submit" name="reset_filters" value="1" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button type="button" onclick="pushSelectedToPool()" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> Push to Pool
                </button>
            </div>
        </form>


        <div class="leaderboard-table">
            <table>
                <thead>
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="selectAllStudents" onclick="toggleAllCheckboxes(this)">
                        </th>
                        <th width="40">Rank</th>
                        <th>Name</th>
                        <th width="100">USN</th>
                        <th width="140">Branch</th>
                        <?php for($i=1; $i<=8; $i++): ?>
                            <th width="35" style="text-align: center;">S<?php echo $i; ?></th>
                        <?php endfor; ?>
                        <th width="70">Aptitude</th>
                        <th width="70">Technical</th>
                        <th width="70">HR</th>
                        <th>Skills</th>
                        <th width="80">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($display_leaderboard)): ?>
                        <tr>
                            <td colspan="18" style="text-align:center; padding: 50px; color: var(--text-light);">
                                <i class="fas fa-search" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                No students found for the selected criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($display_leaderboard as $e): ?>
                        <tr data-usn="<?php echo htmlspecialchars($e['usn']); ?>">
                            <td style="text-align: center;">
                                <input type="checkbox" class="student-select" value="<?php echo htmlspecialchars($e['usn']); ?>">
                            </td>
                            <td>
                                <span class="rank-num"><?php echo $e['rank']; ?></span>
                            </td>
                            <td>
                                <span class="student-name"><?php echo htmlspecialchars($e['name']); ?></span>
                            </td>
                            <td>
                                <span class="student-usn"><?php echo htmlspecialchars($e['usn']); ?></span>
                            </td>
                            <td>
                                <span style="font-size: 11px; color: var(--text-light); font-weight: 500;"><?php echo htmlspecialchars($e['discipline']); ?></span>
                            </td>
                            <?php for($semNum=1; $semNum<=8; $semNum++): ?>
                                <td style="text-align: center;">
                                    <?php 
                                        $val = $e['academic_history'][$semNum]['sgpa'] ?? 0;
                                    ?>
                                    <span style="font-weight: 500; color: <?php echo $val > 0 ? 'var(--text)' : '#cbd5e1'; ?>">
                                        <?php echo $val > 0 ? number_format($val, 2) : '-'; ?>
                                    </span>
                                </td>
                            <?php endfor; ?>
                            <td style="text-align: center;"><span class="score-val"><?php echo round($e['aptitude']); ?>%</span></td>
                            <td style="text-align: center;"><span class="score-val"><?php echo round($e['technical']); ?>%</span></td>
                            <td style="text-align: center;"><span class="score-val"><?php echo round($e['hr']); ?>%</span></td>
                            <td>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php 
                                    $topSkills = array_slice($e['skills'], 0, 2);
                                    foreach($topSkills as $sk): ?>
                                        <span class="tag"><?php echo htmlspecialchars($sk); ?></span>
                                    <?php endforeach; ?>
                                    <?php if(count($e['skills']) > 2): ?>
                                        <span style="font-size: 10px; color: #94a3b8;">+<?php echo count($e['skills'])-2; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="total-badge" 
                                     style="cursor: pointer;"
                                     onclick='showScoreBreakdown(<?php echo htmlspecialchars(json_encode([
                                         "name" => $e["name"],
                                         "apt" => round($e["aptitude"], 1),
                                         "tech" => round($e["technical"], 1),
                                         "hr" => round($e["hr"], 1),
                                         "ai_avg" => round($e["ai_avg"], 1),
                                         "ai_count" => $e["ai_count"],
                                         "ai_pts" => round($e["ai_avg"] * 0.7, 1),
                                         "port_raw" => $e["portfolio"],
                                         "port_pts" => round($e["portfolio"] * 0.3, 1),
                                         "total" => $e["total"]
                                     ]), ENT_QUOTES, "UTF-8"); ?>)'>
                                    <?php echo $e['total']; ?>
                                    <i class="fas fa-calculator" style="font-size: 9px; opacity: 0.5;"></i>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1 && !($showAll ?? false)): ?>
        <form method="POST" id="paginationForm" style="display:none;"><input type="hidden" name="page" id="pageNum"></form>
        <div class="pagination">
            <?php
            if ($page > 1) {
                echo '<a href="javascript:void(0)" onclick="goToPage(' . ($page - 1) . ')" class="page-link">&laquo;</a>';
            }

            $range = 2;
            $start = max(1, $page - $range);
            $end = min($total_pages, $page + $range);

            if ($start > 1) {
                echo '<a href="javascript:void(0)" onclick="goToPage(1)" class="page-link">1</a>';
                if ($start > 2) echo '<span style="padding: 8px; color: #94a3b8;">...</span>';
            }

            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '<a href="javascript:void(0)" onclick="goToPage(' . $i . ')" class="page-link ' . $active . '">' . $i . '</a>';
            }

            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span style="padding: 8px; color: #94a3b8;">...</span>';
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
        <!-- Push to Pool Modal -->
        <div id="pushModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-layer-group" style="color: var(--primary);"></i> Finalize Placement Pool</h3>
                </div>
                <div class="modal-field">
                    <label>Academic Year</label>
                    <input type="text" id="modalAcademicYear" placeholder="e.g. 2025-26" style="text-transform: uppercase;">
                </div>
                <div class="modal-field">
                    <label>Company Name</label>
                    <input type="text" id="modalCompanyName" placeholder="e.g. CAMPUS DRIVE 2026" style="text-transform: uppercase;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePushModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmPushToPool()">
                        Confirm & Push
                    </button>
                </div>
            </div>
        </div>
    <!-- SGPA Details Modal -->
    <div id="sgpaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalStudentName">Academic History</h3>
                <button class="close-modal" onclick="closeSgpaModal()">&times;</button>
            </div>
            <div id="sgpaLoading" style="text-align: center; padding: 30px; display: none;">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; color: var(--primary);"></i>
                <p style="margin-top: 10px; color: #64748b; font-size: 13px;">Loading data...</p>
            </div>
            <div id="sgpaContent">
                <table class="sgpa-table">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>SGPA</th>
                            <th>Academic Year</th>
                        </tr>
                    </thead>
                    <tbody id="sgpaTableBody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Score Breakdown Modal -->
    <div id="breakdownModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="breakdownName">Score Calculation</h3>
                <button class="close-modal" onclick="closeBreakdownModal()">&times;</button>
            </div>
            <div id="breakdownBody">
                <div class="breakdown-row">
                    <span class="breakdown-label" id="aiLabel">AI Pipeline (Base × 0.7)</span>
                    <span class="breakdown-val" id="aiBreakdown"></span>
                </div>
                <div class="breakdown-row">
                    <span class="breakdown-label">Portfolio Rigor (Base × 0.3)</span>
                    <span class="breakdown-val" id="portBreakdown"></span>
                </div>
                <div class="breakdown-row" style="border-top: 2px solid #e2e8f0; margin-top: 10px; padding-top: 15px; border-bottom: none;">
                    <strong class="breakdown-label" style="color: var(--primary);">Final Weighted Total</strong>
                    <strong class="breakdown-total" id="totalBreakdown" style="font-size: 18px; color: var(--primary); font-weight: 800;"></strong>
                </div>
            </div>
            <p style="font-size: 11px; color: #94a3b8; margin-top: 20px; text-align: center;">
                Formula: (AI_Base × 0.7) + (Port_Score × 0.3) | Note: AI_Base uses Extreme Square-Count Penalty.
            </p>
        </div>
    </div>

    <script>
        function toggleInfo() {
            document.getElementById('methodologyCard').classList.toggle('active');
        }

        async function showSgpaDetails(usn, name) {
            const modal = document.getElementById('sgpaModal');
            const tbody = document.getElementById('sgpaTableBody');
            const nameDisplay = document.getElementById('modalStudentName');
            
            nameDisplay.innerText = "Academic History: " + name;
            tbody.innerHTML = '';
            modal.style.display = 'flex';
            
            document.getElementById('sgpaLoading').style.display = 'block';
            document.getElementById('sgpaContent').style.display = 'none';

            try {
                const res = await fetch('leaderboard_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_academic_history', student_id: usn })
                });
                const data = await res.json();
                
                document.getElementById('sgpaLoading').style.display = 'none';
                document.getElementById('sgpaContent').style.display = 'block';

                if (data.success && data.history && data.history.length > 0) {
                    data.history.forEach(sem => {
                        const row = `
                            <tr>
                                <td>Semester ${sem.semester}</td>
                                <td class="sgpa-val">${sem.sgpa}</td>
                                <td style="color: #64748b; font-size: 12px;">${sem.academic_year || '-'}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="padding: 30px; color: #94a3b8;">No academic history found.</td></tr>';
                }
            } catch (err) {
                document.getElementById('sgpaLoading').style.display = 'none';
                document.getElementById('sgpaContent').style.display = 'block';
                tbody.innerHTML = '<tr><td colspan="3" style="padding: 30px; color: #ef4444;">Error loading data.</td></tr>';
            }
        }

        function closeSgpaModal() {
            document.getElementById('sgpaModal').style.display = 'none';
        }

        function showScoreBreakdown(data) {
            document.getElementById('breakdownName').innerText = "Calculation: " + data.name;
            document.getElementById('aiLabel').innerText = `AI Pipeline (${data.ai_avg} × 0.7)`;
            document.getElementById('aiBreakdown').innerText = data.ai_pts + " pts";
            document.getElementById('portBreakdown').innerText = data.port_pts + " pts";
            document.getElementById('totalBreakdown').innerText = data.total;
            
            document.getElementById('breakdownModal').style.display = 'flex';
        }

        function closeBreakdownModal() {
            document.getElementById('breakdownModal').style.display = 'none';
        }

        // Multi-skill Tag Logic
        const skillSearch = document.getElementById('skillSearch');
        const skillDropdown = document.getElementById('skillDropdown');
        const options = document.querySelectorAll('.skill-option');

        skillSearch.addEventListener('focus', () => {
            skillDropdown.style.display = 'block';
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.multi-select-container')) {
                skillDropdown.style.display = 'none';
            }
        });

        skillSearch.addEventListener('input', (e) => {
            const val = e.target.value.toLowerCase();
            options.forEach(opt => {
                const text = opt.innerText.toLowerCase();
                opt.style.display = text.includes(val) ? 'block' : 'none';
            });
        });

        function addTag(skill) {
            const container = document.getElementById('selectedTags');
            
            // Check if already exists
            const existing = container.querySelectorAll('input[type="hidden"]');
            for(let ex of existing) {
                if(ex.value === skill) {
                    skillSearch.value = '';
                    skillDropdown.style.display = 'none';
                    return;
                }
            }

            const tag = document.createElement('div');
            tag.className = 'tag';
            tag.innerHTML = `
                ${skill}
                <i class="fas fa-times" onclick="removeTag('${skill}', this)"></i>
                <input type="hidden" name="skills[]" value="${skill}">
            `;
            container.insertBefore(tag, skillSearch);
            skillSearch.value = '';
            skillDropdown.style.display = 'none';
        }

        function removeTag(skill, el) {
            if(el) {
                el.parentElement.remove();
            } else {
                // Find by value if el not provided (for existing tags)
                const tags = document.querySelectorAll('.tag');
                tags.forEach(t => {
                    if(t.innerText.trim().startsWith(skill)) t.remove();
                });
            }
        }

        // --- Multi-Select & Push Logic ---
        function toggleAllCheckboxes(master) {
            const checkboxes = document.querySelectorAll('.student-select');
            checkboxes.forEach(cb => cb.checked = master.checked);
        }

        let pendingUsns = [];

        function pushSelectedToPool() {
            const selectedBoxes = document.querySelectorAll('.student-select:checked');
            pendingUsns = Array.from(selectedBoxes).map(cb => cb.value);

            if (pendingUsns.length === 0) {
                alert("Please select at least one student.");
                return;
            }

            // Load from localStorage or defaults
            const savedYear = localStorage.getItem('lastAcademicYear');
            const savedCompany = localStorage.getItem('lastCompanyName');

            document.getElementById('modalAcademicYear').value = savedYear || document.getElementById('defaultAcademicYear').value;
            document.getElementById('modalCompanyName').value = savedCompany || "";
            document.getElementById('pushModal').style.display = 'flex';
        }

        function closePushModal() {
            document.getElementById('pushModal').style.display = 'none';
        }

        async function confirmPushToPool() {
            const academicYear = document.getElementById('modalAcademicYear').value.trim().toUpperCase();
            const companyName = document.getElementById('modalCompanyName').value.trim().toUpperCase();

            if (!academicYear || !companyName) {
                alert("Both Academic Year and Company Name are required.");
                return;
            }

            if (!confirm(`Push ${pendingUsns.length} students to the pool for ${companyName}?`)) {
                return;
            }

            // Remember for next time
            localStorage.setItem('lastAcademicYear', academicYear);
            localStorage.setItem('lastCompanyName', companyName);

            closePushModal();
            const pushBtn = document.querySelector('button[onclick="pushSelectedToPool()"]');
            const originalHtml = pushBtn.innerHTML;
            pushBtn.disabled = true;
            pushBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';

            try {
                const res = await fetch('leaderboard_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'push_to_pool', 
                        usns: pendingUsns,
                        academic_year: academicYear,
                        company_name: companyName
                    })
                });
                const data = await res.json();
                
                pushBtn.disabled = false;
                pushBtn.innerHTML = originalHtml;

                if (data.success) {
                    alert(`Successfully pushed ${data.count} student(s) to the pool.`);
                    // Uncheck all
                    document.getElementById('selectAllStudents').checked = false;
                    document.querySelectorAll('.student-select').forEach(cb => cb.checked = false);
                } else {
                    alert("Error: " + (data.message || "Failed to push students."));
                }
            } catch (err) {
                pushBtn.disabled = false;
                pushBtn.innerHTML = originalHtml;
                alert("Connection error occurred.");
            }
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const sgpaModal = document.getElementById('sgpaModal');
            const breakdownModal = document.getElementById('breakdownModal');
            if (event.target == sgpaModal) closeSgpaModal();
            if (event.target == breakdownModal) closeBreakdownModal();
        }
    </script>
</body>
</html>
