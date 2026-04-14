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

// Define Scope & Academic Filters
$coordFilters = [];
if ($view === 'local') {
    list($deptGmu, $deptGmit) = getCoordinatorDisciplineFilters($myDepartment);
    $coordFilters['discipline'] = [$deptGmu, $deptGmit];
    if ($inst_filter !== 'all') {
        $coordFilters['institution'] = ($inst_filter === 'GMIT') ? INSTITUTION_GMIT : INSTITUTION_GMU;
    } else {
        $coordFilters['institution'] = $myInst;
    }
} else {
    if ($inst_filter !== 'all') {
        $coordFilters['institution'] = ($inst_filter === 'GMIT') ? INSTITUTION_GMIT : INSTITUTION_GMU;
    }
}

if ($sem_filter > 0) $coordFilters['semesters'] = [$sem_filter];

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
    $limit = 20;
    $page = $persistedFilters['page'] ?? 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    $total_entries = count($leaderboard);
    $total_pages = ceil($total_entries / $limit);
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
            --primary: #1e293b; /* Dark Slate instead of Maroon */
            --accent: #800000; /* Subtle Maroon accent */
            --gold: #D4AF37;
            --bg: #fdfdfd;
            --white: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
        }

        .navbar-spacer { display: none; }
        
        .container {
            width: 100%;
            margin: 20px 0;
            padding: 0 40px;
        }

        .header {
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header p { color: var(--text-light); margin-top: 5px; }

        .tabs {
            display: flex;
            gap: 5px;
            background: #e2e8f0;
            padding: 5px;
            border-radius: 12px;
            margin-bottom: 30px;
            width: fit-content;
        }

        .tab-link {
            padding: 10px 25px;
            text-decoration: none;
            color: var(--text-light);
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .tab-link.active {
            background: var(--white);
            color: var(--accent);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
        }

        .filter-item label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
        }

        .filter-item select {
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            outline: none;
            font-family: inherit;
        }

        .btn-apply {
            background: #cbd5e1;
            color: #334155;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 18px;
            transition: all 0.2s;
        }
        .btn-apply:hover { background: #94a3b8; }

        .leaderboard-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            padding-bottom: 50px;
        }

        .page-link {
            padding: 8px 16px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            font-weight: 600;
            transition: all 0.2s;
        }

        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .page-link:hover:not(.active) {
            background: #f1f5f9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f1f5f9;
            text-align: left;
            padding: 15px 20px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .rank-box {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-light);
        }
        .rank-1 { color: #f59e0b; }
        .rank-2 { color: #94a3b8; }
        .rank-3 { color: #b45309; }

        .student-info h4 { margin: 0; font-size: 16px; }
        .student-info p { margin: 0; font-size: 12px; color: var(--text-light); }

        .score-pill {
            font-weight: 600;
            font-size: 14px;
        }

        .score-total {
            font-size: 17px;
            font-weight: 700;
            color: var(--text);
        }

        .progress-bar {
            width: 80px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 4px;
        }

        .progress-fill { height: 100%; background: var(--primary); }
        
        /* View SGPA Button */
        .btn-view-sgpa {
            background: #f1f5f9;
            color: var(--primary);
            border: 1px solid #e2e8f0;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-view-sgpa:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Modal Styles */
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
            position: relative; 
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            border-bottom: 1px solid #eee; 
            padding-bottom: 15px; 
        }
        .modal-header h3 { margin: 0; color: var(--primary); font-size: 18px; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }
        .close-modal:hover { color: var(--primary); }
        
        .sgpa-table { width: 100%; border-collapse: collapse; }
        .sgpa-table th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; padding: 12px; text-align: center; }
        .sgpa-table td { padding: 12px; text-align: center; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .sgpa-table tr:last-child td { border-bottom: none; }
        .sgpa-val { font-weight: 700; color: var(--primary); }

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

        .info-card {
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }
        .info-card i { color: #0369a1; font-size: 20px; margin-top: 2px; }
        .info-card p { margin: 0; font-size: 13px; color: #0c4a6e; line-height: 1.5; }
        .info-card strong { color: #0369a1; }
        
        .score-formula {
            display: flex;
            gap: 20px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .formula-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .formula-item .dot { width: 8px; height: 8px; border-radius: 50%; }

        .info-card {
            display: none;
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            flex-direction: column;
            gap: 15px;
        }
        .info-card.active { display: flex; }
        .info-card h4 { margin: 0; color: var(--text); font-size: 15px; }
        .info-card p { margin: 0; font-size: 13px; color: var(--text-light); line-height: 1.5; }
        
        .methodology-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .method-item {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
            background: white;
        }
        .method-item strong { display: block; color: var(--text); font-size: 13px; margin-bottom: 2px; }
        .method-item span { font-size: 11px; color: var(--text-light); }

        .btn-help {
            background: none;
            border: 1px solid var(--border);
            color: var(--text-light);
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-help:hover { background: #f1f5f9; color: var(--text); }

        /* Multi-select Tags */
        .multi-select-container {
            position: relative;
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 5px 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            min-height: 42px;
        }
        .selected-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            width: 100%;
        }
        .tag {
            background: #f1f5f9;
            color: var(--primary);
            padding: 2px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #e2e8f0;
        }
        .tag i { cursor: pointer; color: #94a3b8; }
        .tag i:hover { color: #ef4444; }
        .selected-tags input {
            border: none;
            outline: none;
            padding: 5px;
            font-size: 13px;
            flex: 1;
            min-width: 80px;
        }
        .skill-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .skill-option {
            padding: 10px 15px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .skill-option:hover { background: #f8fafc; color: var(--accent); }
        .skill-option.selected { display: none; }

        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .filters { flex-direction: column; }
            .hide-mobile { display: none; }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="header">
            <div>
                <h1>
                    Placement Leaderboard
                    <button class="btn-help" onclick="toggleInfo()">
                        <i class="fas fa-info-circle"></i> View Methodology
                    </button>
                </h1>
                <p>Tracking the top placement-ready students based on multi-dimensional scoring.</p>
            </div>
            <form id="viewTabForm" method="POST" class="tabs">
                <input type="hidden" name="view" id="viewInput" value="<?php echo $view; ?>">
                <button type="button" onclick="setView('local')" class="tab-link <?php echo $view === 'local' ? 'active' : ''; ?>" style="background:none; border:none; cursor:pointer;">Local (My Dept)</button>
                <button type="button" onclick="setView('global')" class="tab-link <?php echo $view === 'global' ? 'active' : ''; ?>" style="background:none; border:none; cursor:pointer;">Global (All Depts)</button>
            </form>
            <script>
                function setView(val) {
                    document.getElementById('viewInput').value = val;
                    document.getElementById('viewTabForm').submit();
                }
            </script>
        </div>

        <div id="methodologyCard" class="info-card">
            <div style="flex: 1;">
                <h4>How is the Total Score calculated?</h4>
                <p>We use a weighted scoring system that combines academic performance, interview preparedness, and practical experience.</p>
                <div class="methodology-grid">
                    <div class="method-item">
                        <strong>Academic (40%)</strong>
                        <span>SGPA × 10 (e.g. 8.5 = 85 points)</span>
                    </div>
                    <div class="method-item">
                        <strong>AI Assessments (40%)</strong>
                        <span>Average of Aptitude, Tech & HR (All Required)</span>
                    </div>
                    <div class="method-item">
                        <strong>Portfolio (20%)</strong>
                        <span>Skills (max 10) + Projects (max 4)</span>
                    </div>
                </div>
                <div style="margin-top: 12px; font-size: 11px; color: #64748b; background: white; padding: 8px 12px; border-radius: 6px; display: inline-block; border: 1px solid #eee;">
                    <i class="fas fa-exclamation-triangle" style="color: #ea580c;"></i> <strong>Challenge Mode:</strong> Unattempted AI tests count as 0%. Portfolio requires double the items for a perfect score.
                </div>
            </div>
        </div>

        <form class="filters flex-wrap" method="POST">
            <div class="filter-item" style="min-width: 150px;">
                <label>Institution</label>
                <select name="inst" onchange="this.form.submit()">
                    <option value="all">All</option>
                    <option value="gmu" <?php echo $inst_filter === 'gmu' ? 'selected' : ''; ?>>GMU</option>
                    <option value="gmit" <?php echo $inst_filter === 'gmit' ? 'selected' : ''; ?>>GMIT</option>
                </select>
            </div>
            <div class="filter-item" style="min-width: 120px;">
                <label>Semester</label>
                <select name="sem" onchange="this.form.submit()">
                    <option value="0" <?php echo $sem_filter === 0 ? 'selected' : ''; ?>>All Semesters</option>
                    <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $sem_filter === $i ? 'selected' : ''; ?>>Semester <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-item" style="min-width: 120px;">
                <label>Min Total</label>
                <select name="min_total">
                    <option value="">Any</option>
                    <?php foreach([50, 60, 70, 80, 90] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_total'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item" style="min-width: 120px;">
                <label>Min SGPA (All)</label>
                <select name="min_sgpa_all">
                    <option value="">Any</option>
                    <?php foreach([6.0, 6.5, 7.0, 7.5, 8.0, 8.5, 9.0] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_sgpa_all'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item" style="min-width: 120px;">
                <label>Min Aptitude</label>
                <select name="min_apt">
                    <option value="">Any</option>
                    <?php foreach([40, 50, 60, 70, 80, 90] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_apt'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item" style="min-width: 120px;">
                <label>Min Tech</label>
                <select name="min_tech">
                    <option value="">Any</option>
                    <?php foreach([40, 50, 60, 70, 80, 90] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_tech'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item" style="min-width: 120px;">
                <label>Min HR</label>
                <select name="min_hr">
                    <option value="">Any</option>
                    <?php foreach([40, 50, 60, 70, 80, 90] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_hr'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item" style="min-width: 250px;">
                <label>Required Skills (Select Multiple)</label>
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
            <div style="display: flex; gap: 10px; align-self: flex-end; margin-bottom: 5px;">
                <button type="submit" class="btn-apply" style="white-space: nowrap;">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="submit" name="reset_filters" value="1" class="btn-help" style="white-space: nowrap; border: 1px solid var(--border);">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>

        <div class="leaderboard-table">
            <table>
                <thead>
                    <tr>
                        <th width="40">Rank</th>
                        <th>Student Name & USN</th>
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
                            <td colspan="10" style="text-align:center; padding: 50px; color: var(--text-light);">
                                <i class="fas fa-search" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                No students found for the selected criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($display_leaderboard as $e): ?>
                        <tr>
                            <td>
                                <div class="rank-box <?php echo $e['rank'] <= 3 ? 'rank-'.$e['rank'] : ''; ?>">
                                    <?php echo $e['rank']; ?>
                                </div>
                            </td>
                            <td>
                                <div class="student-info">
                                    <h4 style="margin:0; font-size:13px; color:var(--text); white-space: nowrap;"><?php echo htmlspecialchars($e['name']); ?></h4>
                                    <p style="font-family: monospace; font-weight: 700; color: var(--primary); font-size: 11px;"><?php echo htmlspecialchars($e['usn']); ?></p>
                                    <span style="font-size: 9px; color: var(--text-light);"><?php echo htmlspecialchars($e['discipline']); ?></span>
                                </div>
                            </td>
                            <?php for($semNum=1; $semNum<=8; $semNum++): ?>
                                <td style="text-align: center;">
                                    <span style="font-weight: 700; font-size: 11px; color: <?php 
                                        $val = $e['academic_history'][$semNum]['sgpa'] ?? 0;
                                        echo $val >= 8.5 ? '#059669' : ($val >= 7.5 ? '#2563eb' : ($val > 0 ? '#d97706' : '#94a3b8'));
                                    ?>">
                                        <?php echo $val > 0 ? number_format($val, 2) : '-'; ?>
                                    </span>
                                </td>
                            <?php endfor; ?>
                            <td>
                                <div class="score-pill" style="color: <?php echo $e['aptitude'] >= 70 ? '#059669' : ($e['aptitude'] >= 40 ? '#d97706' : '#dc2626'); ?>;">
                                    <?php echo round($e['aptitude']); ?>%
                                </div>
                            </td>
                            <td>
                                <div class="score-pill" style="color: <?php echo $e['technical'] >= 70 ? '#059669' : ($e['technical'] >= 40 ? '#d97706' : '#dc2626'); ?>;">
                                    <?php echo round($e['technical']); ?>%
                                </div>
                            </td>
                            <td>
                                <div class="score-pill" style="color: #be185d;">
                                    <?php echo round($e['hr']); ?>%
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 200px;">
                                    <?php 
                                    $topSkills = array_slice($e['skills'], 0, 3);
                                    foreach($topSkills as $sk): ?>
                                        <span style="font-size: 9px; padding: 2px 6px; background: #f1f5f9; border-radius: 4px; color: #475569; border: 1px solid #e2e8f0;"><?php echo htmlspecialchars($sk); ?></span>
                                    <?php endforeach; ?>
                                    <?php if(count($e['skills']) > 3): ?>
                                        <span style="font-size: 9px; color: #94a3b8;">+<?php echo count($e['skills'])-3; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                             <div class="total-score-wrapper" 
                                     style="cursor: pointer;"
                                     onclick='showScoreBreakdown(<?php echo json_encode([
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
                                     ]); ?>)'>
                                    <span class="score-total"><?php echo $e['total']; ?></span>
                                    <i class="fas fa-calculator" style="font-size: 10px; color: var(--primary); opacity: 0.6;"></i>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
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
                // Fallback for initial tags
                const tags = document.querySelectorAll('.tag');
                tags.forEach(t => {
                    if(t.innerText.includes(skill)) t.remove();
                });
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
