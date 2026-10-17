<?php
/**
 * Placement Officer - AI Student Talent & Role Finder (Compact & Persistent)
 * Searches and ranks students based on target job roles, verified skills, and projects.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

use App\Helpers\SessionFilterHelper;

$pageId = 'officer_talent_finder';
$officerModel = new PlacementOfficer();

// Fetch disciplines for filter dropdown
$disciplines = [];
try {
    $disciplines = $officerModel->getDisciplines() ?: [];
} catch (Exception $e) {
    error_log("Error fetching disciplines: " . $e->getMessage());
}

// 1. Handle Reset Action
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    SessionFilterHelper::clearFilters($pageId);
    header('Location: find_students.php');
    exit;
}

// 2. Handle Search / Filter Submission & Persist into Session
if (isset($_GET['role']) || isset($_GET['institution']) || isset($_GET['discipline']) || isset($_GET['semester']) || isset($_GET['ai_fit']) || isset($_GET['reasoning']) || isset($_GET['page'])) {
    $searchState = [
        'role'        => trim((string)($_GET['role'] ?? '')),
        'institution' => trim((string)($_GET['institution'] ?? '')),
        'discipline'  => trim((string)($_GET['discipline'] ?? '')),
        'semester'    => trim((string)($_GET['semester'] ?? '')),
        'ai_fit'      => trim((string)($_GET['ai_fit'] ?? '')),
        'reasoning'   => trim((string)($_GET['reasoning'] ?? '')),
        'page'        => max(1, (int)($_GET['page'] ?? 1))
    ];
    SessionFilterHelper::setFilters($pageId, $searchState);
}

// 3. Retrieve Persisted Filters (Restores search on reload/refresh)
$savedFilters = SessionFilterHelper::getFilters($pageId);

$roleQuery   = $savedFilters['role'] ?? '';
$institution = $savedFilters['institution'] ?? '';
$discipline  = $savedFilters['discipline'] ?? '';
$semester    = $savedFilters['semester'] ?? '';
$aiFit       = $savedFilters['ai_fit'] ?? '';
$reasoning   = $savedFilters['reasoning'] ?? '';
$page        = max(1, (int)($savedFilters['page'] ?? 1));
$limit       = 25;

$filters = [
    'institution' => $institution,
    'discipline'  => $discipline,
    'semester'    => $semester,
    'ai_fit'      => $aiFit,
    'reasoning'   => $reasoning
];

$results = null;
if ($roleQuery !== '') {
    $results = $officerModel->searchStudentsByRoleAI($roleQuery, $filters, $page, $limit);

    // Handle Excel Export
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        $filename = 'student_candidates_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($roleQuery)) . '_' . date('Y-m-d') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>';
        echo '<table border="1" style="border-collapse:collapse; font-family:Arial,sans-serif;">';
        echo '<tr style="background:#7C0000; color:#FFFFFF; font-weight:bold;">';
        echo '<th>SL No</th><th>Name</th><th>USN</th><th>Institution</th><th>Department</th><th>Semester</th><th>Phone No</th><th>Email ID</th><th>Matching Skills</th><th>Matching Projects</th><th>AI Match Score</th><th>AI Reasoning</th><th>Resume Link</th>';
        echo '</tr>';

        $sl = 1;
        $exportData = $results['all_data'] ?? $results['data'] ?? [];
        foreach ($exportData as $s) {
            $skillsStr = implode(', ', array_map(function($sk) {
                return $sk['name'] . (!empty($sk['is_verified']) ? ' [Verified]' : '');
            }, $s['matched_skills'] ?? []));

            $projStr = implode(' | ', array_map(function($pr) {
                $t = $pr['title'];
                if (!empty($pr['tech'])) $t .= ' (' . $pr['tech'] . ')';
                return $t;
            }, $s['matched_projects'] ?? []));

            $resumeLink = !empty($s['has_resume']) ? '=HYPERLINK("' . $s['resume_url'] . '", "View Resume")' : 'No Resume';

            echo '<tr>';
            echo '<td style="text-align:center;">' . $sl++ . '</td>';
            echo '<td>' . htmlspecialchars((string)$s['name']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$s['usn']) . '</td>';
            echo '<td style="text-align:center;">' . htmlspecialchars((string)$s['institution']) . '</td>';
            echo '<td>' . htmlspecialchars((string)$s['discipline']) . '</td>';
            echo '<td style="text-align:center;">' . htmlspecialchars((string)$s['sem']) . '</td>';
            echo '<td>' . htmlspecialchars((string)($s['phone'] ?: '-')) . '</td>';
            echo '<td>' . htmlspecialchars((string)($s['email'] ?: '-')) . '</td>';
            echo '<td>' . htmlspecialchars($skillsStr ?: '-') . '</td>';
            echo '<td>' . htmlspecialchars($projStr ?: '-') . '</td>';
            echo '<td style="text-align:center;">' . (int)$s['match_score'] . '%</td>';
            echo '<td>' . htmlspecialchars((string)$s['ai_reasoning']) . '</td>';
            echo '<td>' . $resumeLink . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }
}

$officerName = $_SESSION['user']['full_name'] ?? 'Placement Officer';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Student Talent & Role Finder – <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #7C0000;
            --brand-dark: #5A0000;
            --brand-light: #FDF2F2;
            --gold: #B08D2C;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #94A3B8;
            --surface-bg: #FFFFFF;
            --page-bg: #F8FAFC;
            --radius-sm: 5px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: var(--page-bg);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
        }

        .talent-container {
            max-width: 1650px;
            margin: 0 auto;
            padding: 20px 28px;
        }

        /* Top Header Bar */
        .talent-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .talent-header__title h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.3px;
        }

        .talent-header__title h1 i {
            color: var(--brand);
            font-size: 19px;
        }

        .talent-header__title p {
            font-size: 12.5px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .talent-header__actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .btn-brand {
            background: var(--brand);
            color: #FFFFFF;
        }
        .btn-brand:hover {
            background: var(--brand-dark);
            color: #FFFFFF;
        }

        .btn-outline {
            background: #FFFFFF;
            border-color: var(--border-color);
            color: var(--text-secondary);
        }
        .btn-outline:hover {
            background: #F1F5F9;
            color: var(--text-primary);
            border-color: #CBD5E1;
        }

        .btn-success {
            background: #059669;
            color: #FFFFFF;
        }
        .btn-success:hover {
            background: #047857;
            color: #FFFFFF;
        }

        .btn-danger-light {
            background: #FEF2F2;
            color: #DC2626;
            border-color: #FECACA;
        }
        .btn-danger-light:hover {
            background: #FEE2E2;
        }

        /* Compact Search Card */
        .search-card {
            background: var(--surface-bg);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .search-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .search-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input-group i.search-icon {
            position: absolute;
            left: 14px;
            font-size: 15px;
            color: var(--brand);
        }

        .search-input-group input {
            width: 100%;
            padding: 10px 14px 10px 42px;
            font-size: 13.5px;
            border: 1.5px solid #CBD5E1;
            border-radius: var(--radius-sm);
            outline: none;
            transition: border-color 0.15s;
            font-family: inherit;
        }

        .search-input-group input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 2px rgba(124, 0, 0, 0.08);
        }

        .search-submit-btn {
            position: absolute;
            right: 5px;
            top: 5px;
            bottom: 5px;
            padding: 0 16px;
            font-size: 12.5px;
        }

        /* Role Suggestion Chips */
        .chips-container {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .chips-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chip-btn {
            background: #F1F5F9;
            color: var(--text-secondary);
            border: 1px solid #E2E8F0;
            padding: 3px 9px;
            border-radius: 14px;
            font-size: 11.5px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.12s ease;
            text-decoration: none;
        }

        .chip-btn:hover {
            background: var(--brand-light);
            color: var(--brand);
            border-color: #F87171;
        }

        /* Compact Filters Row */
        .filters-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-top: 10px;
            border-top: 1px solid #F1F5F9;
            flex-wrap: wrap;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-item label {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .filter-item select {
            padding: 6px 10px;
            font-size: 12.5px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: #FFFFFF;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }

        .filter-item select:focus {
            border-color: var(--brand);
        }

        /* Compact AI Role Card */
        .ai-summary-card {
            background: #FFFBFB;
            border: 1px solid #FECACA;
            border-left: 3px solid var(--brand);
            border-radius: var(--radius-sm);
            padding: 10px 16px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--brand);
            color: #FFFFFF;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .ai-tags-row {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            color: #334155;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 3px;
            font-weight: 500;
        }

        .tag-pill.core {
            background: #FEE2E2;
            border-color: #FCA5A5;
            color: #991B1B;
            font-weight: 600;
        }

        /* Table Card & High-Density Datatable */
        .table-card {
            background: var(--surface-bg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .table-header-info {
            padding: 10px 16px;
            background: #F8FAFC;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .candidate-count {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .candidate-count span {
            color: var(--brand);
        }

        /* Scrollable container with sticky header */
        .table-responsive {
            width: 100%;
            max-height: calc(100vh - 310px);
            min-height: 380px;
            overflow-y: auto;
            overflow-x: auto;
            position: relative;
        }

        .talent-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
            min-width: 1280px;
        }

        .talent-table th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #F1F5F9;
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 9px 8px;
            border-bottom: 2px solid #CBD5E1;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }

        .talent-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
            color: var(--text-primary);
            line-height: 1.35;
        }

        .talent-table tbody tr:hover {
            background-color: #F8FAFC;
        }

        /* Compact Column Specifics */
        .col-sl { width: 38px; text-align: center; font-weight: 600; color: var(--text-muted); }
        .col-name { min-width: 130px; max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; color: #0F172A; }
        .col-usn { width: 105px; font-family: monospace; font-size: 11.5px; font-weight: 600; color: var(--brand); white-space: nowrap; }
        .col-inst { width: 65px; text-align: center; }
        .col-dept { min-width: 110px; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 11.5px; color: var(--text-secondary); }
        .col-sem { width: 45px; text-align: center; font-weight: 600; font-size: 11.5px; color: var(--text-secondary); }
        .col-phone { width: 110px; font-family: monospace; font-size: 11px; white-space: nowrap; }
        .col-email { min-width: 130px; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 11px; color: var(--text-secondary); }
        .col-skills { min-width: 180px; max-width: 250px; }
        .col-projects { min-width: 230px; max-width: 320px; }
        .col-ai { min-width: 160px; max-width: 200px; }
        .col-resume { width: 75px; text-align: center; }

        /* Compact Badges */
        .inst-badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .inst-badge.gmit {
            background: #E0E7FF;
            color: #3730A3;
        }
        .inst-badge.gmu {
            background: #FCE7F3;
            color: #9D174D;
        }

        /* Compact Skill Pills */
        .skill-tag {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: #F1F5F9;
            color: #1E293B;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 10.5px;
            font-weight: 500;
            margin: 1px;
            border: 1px solid #E2E8F0;
            white-space: nowrap;
        }

        .skill-tag.core {
            background: #FEE2E2;
            color: #991B1B;
            border-color: #FCA5A5;
            font-weight: 600;
        }

        .skill-tag i.fa-check-circle {
            color: #059669;
            font-size: 9px;
        }

        /* Compact Projects: One-liners with quick detail modal */
        .project-compact-row {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .project-compact-row:last-child { margin-bottom: 0; }

        .proj-name {
            font-weight: 600;
            color: #1E293B;
            font-size: 11.5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .proj-tech-pill {
            font-size: 9.5px;
            color: var(--brand);
            background: #FFF1F2;
            border: 1px solid #FECDD3;
            padding: 0 4px;
            border-radius: 3px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .proj-btn-view {
            color: #64748B;
            cursor: pointer;
            font-size: 11px;
            padding: 1px 4px;
            border-radius: 3px;
            transition: color 0.12s;
            flex-shrink: 0;
        }
        .proj-btn-view:hover {
            color: var(--brand);
            background: #FEE2E2;
        }

        /* Compact AI Score */
        .ai-compact-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 700;
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 4px;
            color: #FFFFFF;
        }

        .ai-reason-compact {
            font-size: 10.5px;
            color: var(--text-secondary);
            margin-top: 2px;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Proof-of-Work Credibility Badges */
        .pow-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 9.5px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 3px;
            white-space: nowrap;
        }

        .pow-badge.verified {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }

        .pow-badge.unverified {
            background: #FFFBEB;
            color: #B45309;
            border: 1px solid #FDE68A;
        }

        /* Modal Styles for Full Project Details */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            backdrop-filter: blur(2px);
        }

        .modal-box {
            background: #FFFFFF;
            border-radius: var(--radius-md);
            max-width: 650px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 18px;
            color: #94A3B8;
            cursor: pointer;
        }
        .modal-close:hover { color: #0F172A; }

        /* AI Loading Overlay Styles */
        .ai-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 999999;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .ai-loading-overlay.active {
            display: flex;
            opacity: 1;
        }

        .ai-loading-modal {
            background: #FFFFFF;
            border-radius: 14px;
            padding: 32px 36px;
            width: 90%;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            position: relative;
            animation: modalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPopIn {
            0% { transform: scale(0.92) translateY(8px); opacity: 0; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }

        .ai-loading-radar {
            position: relative;
            width: 76px;
            height: 76px;
            margin: 0 auto 16px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .radar-core {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #7C0000 0%, #991B1B 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 20px;
            box-shadow: 0 6px 16px rgba(124, 0, 0, 0.35);
            z-index: 2;
            animation: brainThrob 1.8s infinite ease-in-out;
        }

        @keyframes brainThrob {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        .radar-pulse {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid rgba(124, 0, 0, 0.35);
            animation: radarRipple 2s infinite cubic-bezier(0.215, 0.61, 0.355, 1);
        }

        .radar-pulse.delay-1 {
            animation-delay: 0.8s;
        }

        @keyframes radarRipple {
            0% { transform: scale(0.6); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        .ai-loading-title {
            font-size: 16.5px;
            font-weight: 700;
            color: #0F172A;
            margin: 0 0 6px 0;
            letter-spacing: -0.3px;
        }

        .ai-loading-status {
            font-size: 12.5px;
            color: #475569;
            margin: 0 0 16px 0;
            min-height: 18px;
            transition: opacity 0.2s ease;
        }

        .ai-loading-progress-bar {
            width: 100%;
            height: 5px;
            background: #F1F5F9;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
            margin-bottom: 14px;
        }

        .ai-loading-progress-fill {
            width: 45%;
            height: 100%;
            background: linear-gradient(90deg, #7C0000, #C59B27, #059669);
            border-radius: 999px;
            position: absolute;
            animation: shimmerSlide 1.5s infinite ease-in-out;
        }

        @keyframes shimmerSlide {
            0% { left: -45%; width: 45%; }
            50% { width: 60%; }
            100% { left: 100%; width: 45%; }
        }

        .ai-loading-tips {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #64748B;
            background: #F8FAFC;
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid #E2E8F0;
        }

        /* Filter Item Input Enhancements */
        .filter-item input[type="text"] {
            padding: 6px 10px;
            font-size: 12.5px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: #FFFFFF;
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.12s;
        }

        .filter-item input[type="text"]:focus {
            border-color: var(--brand);
        }

        /* Pagination Bar */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #F8FAFC;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
        }

        .pagination-links {
            display: flex;
            gap: 4px;
        }

        .page-link {
            padding: 4px 9px;
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            font-size: 11.5px;
        }

        .page-link.active {
            background: var(--brand);
            color: #FFFFFF;
            border-color: var(--brand);
        }

        /* Empty State */
        .empty-state {
            padding: 48px 20px;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 36px;
            color: #CBD5E1;
            margin-bottom: 12px;
        }

        .empty-state h3 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .empty-state p {
            font-size: 12.5px;
            color: var(--text-muted);
            max-width: 480px;
            margin: 0 auto;
        }

        /* ============================================================== */
        /* PRINT STYLESHEET (A4 LANDSCAPE)                                */
        /* ============================================================== */
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm 6mm;
            }

            body {
                background: #FFFFFF !important;
                padding-left: 0 !important;
                padding-top: 0 !important;
                color: #000000 !important;
                font-size: 8.5pt !important;
            }

            #o-sidebar, 
            .talent-header__actions, 
            .search-card, 
            .ai-summary-card,
            .pagination-container,
            .btn,
            .modal-overlay,
            .no-print {
                display: none !important;
            }

            .talent-container {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .print-header-banner {
                display: block !important;
                border-bottom: 2px solid #7C0000;
                padding-bottom: 6px;
                margin-bottom: 10px;
            }

            .print-header-banner h2 {
                font-size: 14pt;
                color: #7C0000;
                margin: 0;
                text-transform: uppercase;
            }

            .print-header-banner p {
                font-size: 8pt;
                color: #334155;
                margin: 2px 0 0 0;
            }

            .table-card {
                border: none !important;
                box-shadow: none !important;
            }

            .table-responsive {
                max-height: none !important;
                overflow: visible !important;
                border: none !important;
            }

            .talent-table {
                min-width: 100% !important;
                width: 100% !important;
                border: 1px solid #000000 !important;
                font-size: 7.5pt !important;
            }

            .talent-table th {
                position: static !important;
                background: #E2E8F0 !important;
                color: #000000 !important;
                border: 1px solid #64748B !important;
                padding: 4px !important;
            }

            .talent-table td {
                border: 1px solid #CBD5E1 !important;
                padding: 4px !important;
                color: #000000 !important;
                page-break-inside: avoid !important;
            }

            .skill-tag {
                border: 1px solid #94A3B8 !important;
                background: none !important;
                color: #000000 !important;
                font-size: 7pt !important;
                padding: 0 3px !important;
            }

            .inst-badge {
                border: 1px solid #000000 !important;
                background: none !important;
                color: #000000 !important;
            }

            .ai-reason-compact {
                -webkit-line-clamp: none !important;
                display: block !important;
            }

            a { text-decoration: none !important; color: #000000 !important; }
        }

        .print-header-banner { display: none; }
    </style>
</head>
<body>

    <!-- Officer Sidebar Navbar -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="talent-container">

        <!-- Official Print Header (Only visible on paper / PDF) -->
        <div class="print-header-banner">
            <h2><?php echo APP_NAME; ?> – Campus Placement Cell</h2>
            <p>
                <strong>Candidate Shortlist Report:</strong> Target Role: <u><?php echo htmlspecialchars($roleQuery ?: 'General Profile'); ?></u>
                &nbsp;|&nbsp; <strong>Institution:</strong> <?php echo htmlspecialchars($institution ?: 'All (GMU & GMIT)'); ?>
                &nbsp;|&nbsp; <strong>Generated On:</strong> <?php echo date('d M Y, h:i A'); ?>
                &nbsp;|&nbsp; <strong>Officer:</strong> <?php echo htmlspecialchars($officerName); ?>
                &nbsp;|&nbsp; <strong>Total Candidates:</strong> <?php echo (int)($results['total'] ?? 0); ?>
            </p>
        </div>

        <!-- On-Screen Header -->
        <header class="talent-header no-print">
            <div class="talent-header__title">
                <h1><i class="fas fa-brain"></i> AI Student Talent & Role Finder</h1>
                <p>High-density candidate search by verified skills, portfolios, and AI semantic matching.</p>
            </div>
            <div class="talent-header__actions">
                <?php if ($roleQuery !== ''): ?>
                    <a href="find_students.php?reset=1" class="btn btn-danger-light" title="Reset current search and filters">
                        <i class="fas fa-undo"></i> <span>Reset Search</span>
                    </a>
                <?php endif; ?>

                <?php if (!empty($results['data'])): ?>
                    <button type="button" onclick="window.print()" class="btn btn-outline" title="Print candidate list on A4 Landscape">
                        <i class="fas fa-print"></i> <span>Print List</span>
                    </button>
                    <?php 
                    $exportQuery = $savedFilters;
                    $exportQuery['export'] = 'excel';
                    $exportUrl = 'find_students.php?' . http_build_query($exportQuery);
                    ?>
                    <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn btn-success" title="Download candidate list as Excel spreadsheet">
                        <i class="fas fa-file-excel"></i> <span>Export Excel</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Compact Search Card -->
        <section class="search-card no-print">
            <form action="find_students.php" method="GET" class="search-form">
                <div class="search-input-group">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="role" id="roleInput" 
                           placeholder="Type any target role (e.g. Mobile App Developer, Data Scientist, DevOps, Full Stack React, Cybersecurity)..." 
                           value="<?php echo htmlspecialchars($roleQuery); ?>" required autofocus>
                    <button type="submit" class="btn btn-brand search-submit-btn">
                        <i class="fas fa-sparkles"></i> <span>Find</span>
                    </button>
                </div>

                <!-- Quick Role Suggestions -->
                <div class="chips-container">
                    <span class="chips-label">Quick Suggestions:</span>
                    <a href="find_students.php?role=Mobile+App+Developer" class="chip-btn">📱 Mobile App Dev</a>
                    <a href="find_students.php?role=Data+Scientist" class="chip-btn">📊 Data Scientist</a>
                    <a href="find_students.php?role=Full+Stack+Developer" class="chip-btn">💻 Full Stack</a>
                    <a href="find_students.php?role=Cybersecurity" class="chip-btn">🔒 Cybersecurity</a>
                    <a href="find_students.php?role=Cloud+%26+DevOps+Engineer" class="chip-btn">☁️ Cloud & DevOps</a>
                    <a href="find_students.php?role=AI+%26+Machine+Learning+Engineer" class="chip-btn">🤖 AI / ML</a>
                    <a href="find_students.php?role=UI%2FUX+Designer" class="chip-btn">🎨 UI/UX Designer</a>
                    <a href="find_students.php?role=Embedded+%26+IoT+Engineer" class="chip-btn">⚡ Embedded / IoT</a>
                </div>

                <!-- Filters Row -->
                <div class="filters-row">
                    <div class="filter-item">
                        <label for="filterInstitution"><i class="fas fa-university"></i> Institution:</label>
                        <select name="institution" id="filterInstitution" onchange="showAiLoading('Filtering candidates by institution...'); this.form.submit()">
                            <option value="">All (GMU & GMIT)</option>
                            <option value="<?php echo INSTITUTION_GMU; ?>" <?php echo ($institution === INSTITUTION_GMU) ? 'selected' : ''; ?>>GMU</option>
                            <option value="<?php echo INSTITUTION_GMIT; ?>" <?php echo ($institution === INSTITUTION_GMIT) ? 'selected' : ''; ?>>GMIT</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="filterDiscipline"><i class="fas fa-graduation-cap"></i> Department:</label>
                        <select name="discipline" id="filterDiscipline" onchange="showAiLoading('Filtering candidates by department...'); this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($disciplines as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($discipline === $d) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="filterSemester"><i class="fas fa-layer-group"></i> Semester:</label>
                        <select name="semester" id="filterSemester" onchange="showAiLoading('Filtering candidates by semester...'); this.form.submit()">
                            <option value="">All Semesters</option>
                            <option value="5" <?php echo ($semester === '5') ? 'selected' : ''; ?>>5th Sem</option>
                            <option value="6" <?php echo ($semester === '6') ? 'selected' : ''; ?>>6th Sem</option>
                            <option value="7" <?php echo ($semester === '7') ? 'selected' : ''; ?>>7th Sem</option>
                            <option value="8" <?php echo ($semester === '8') ? 'selected' : ''; ?>>8th Sem</option>
                        </select>
                    </div>

                    <!-- AI Match & Fit Filter -->
                    <div class="filter-item">
                        <label for="filterAiFit"><i class="fas fa-brain" style="color:var(--brand);"></i> AI Fit & Proof-of-Work:</label>
                        <select name="ai_fit" id="filterAiFit" onchange="showAiLoading('Filtering candidates by AI match level & Proof-of-Work...'); this.form.submit()">
                            <option value="">All Candidates (Verified & Unverified)</option>
                            <option value="verified" <?php echo ($aiFit === 'verified') ? 'selected' : ''; ?>>🛡️ Verified Proof-of-Work Only (Viva Defended)</option>
                            <option value="top" <?php echo ($aiFit === 'top') ? 'selected' : ''; ?>>🔥 Top Tier Fit (85%+)</option>
                            <option value="strong" <?php echo ($aiFit === 'strong') ? 'selected' : ''; ?>>⭐ Strong Fit (70% - 84%)</option>
                            <option value="moderate" <?php echo ($aiFit === 'moderate') ? 'selected' : ''; ?>>⚡ Moderate Fit (50% - 69%)</option>
                        </select>
                    </div>

                    <!-- Filter by AI Reasoning & Competency -->
                    <div class="filter-item">
                        <label for="filterReasoning"><i class="fas fa-sparkles" style="color:var(--gold);"></i> Filter Reasoning / Tech:</label>
                        <div style="position:relative; display:inline-flex; align-items:center;">
                            <input type="text" name="reasoning" id="filterReasoning" 
                                   placeholder="e.g. Flutter, Security, ML..." 
                                   value="<?php echo htmlspecialchars($reasoning); ?>"
                                   style="padding:6px 26px 6px 10px; font-size:12px; width:180px;">
                            <?php if (!empty($reasoning)): ?>
                                <button type="button" onclick="clearReasoningFilter()" style="position:absolute; right:6px; background:none; border:none; color:#EF4444; cursor:pointer; font-size:12px;" title="Clear reasoning filter">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            <?php else: ?>
                                <button type="submit" style="position:absolute; right:6px; background:none; border:none; color:#94A3B8; cursor:pointer; font-size:11px;" title="Apply reasoning filter">
                                    <i class="fas fa-search"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($roleQuery !== ''): ?>
                        <div style="margin-left:auto; font-size:11.5px; color:var(--text-muted); display:flex; align-items:center; gap:8px;">
                            <?php if (!empty($aiFit) || !empty($reasoning) || !empty($institution) || !empty($discipline) || !empty($semester)): ?>
                                <a href="find_students.php?role=<?php echo urlencode($roleQuery); ?>" class="btn btn-outline" style="padding:2px 8px; font-size:10.5px;" title="Clear filters but keep role" onclick="showAiLoading('Resetting filters...')">
                                    <i class="fas fa-filter-circle-xmark"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                            <span><i class="fas fa-check-circle" style="color:#10B981;"></i> Session saved</span>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Compact AI Role Card -->
        <?php if (!empty($results['ai_profile']) && !empty($results['ai_profile']['core_skills'])): 
            $aiP = $results['ai_profile'];
        ?>
            <section class="ai-summary-card no-print">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="ai-badge"><i class="fas fa-microchip"></i> AI Match Profile</span>
                    <strong style="color:var(--brand); font-size:13px;"><?php echo htmlspecialchars($aiP['role_normalized'] ?? $roleQuery); ?></strong>
                    <span style="color:var(--text-muted); font-size:12px;">&bull;</span>
                    <span style="font-size:12px; color:var(--text-secondary);"><?php echo htmlspecialchars($aiP['role_summary'] ?? ''); ?></span>
                </div>

                <div class="ai-tags-row">
                    <?php foreach (array_slice($aiP['core_skills'], 0, 7) as $cs): ?>
                        <span class="tag-pill core"><i class="fas fa-check"></i> <?php echo htmlspecialchars($cs); ?></span>
                    <?php endforeach; ?>
                    <?php if (count($aiP['core_skills']) > 7): ?>
                        <span class="tag-pill">+<?php echo count($aiP['core_skills']) - 7; ?> more</span>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Results Table Card -->
        <?php if ($roleQuery !== ''): ?>
            <section class="table-card">
                <div class="table-header-info">
                    <div class="candidate-count">
                        Found <span><?php echo (int)($results['total'] ?? 0); ?></span> candidates for 
                        <strong>"<?php echo htmlspecialchars($roleQuery); ?>"</strong>
                        <?php if (!empty($aiFit) || !empty($reasoning)): ?>
                            <span style="font-weight:normal; font-size:11.5px; color:var(--text-secondary); margin-left:6px;">
                                (Filtered: 
                                <?php if (!empty($aiFit)): ?>
                                    <span class="tag-pill" style="font-size:10px; background:#FEF2F2; color:#991B1B; font-weight:600;">Fit: <?php echo htmlspecialchars($aiFit); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($reasoning)): ?>
                                    <span class="tag-pill" style="font-size:10px; background:#EFF6FF; color:#1D4ED8; font-weight:600;">Reasoning: "<?php echo htmlspecialchars($reasoning); ?>"</span>
                                <?php endif; ?>
                                )
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11.5px; color:var(--text-muted);">
                        Sorted by AI Match Score &bull; Showing 25 per page
                    </div>
                </div>

                <?php if (!empty($results['data'])): ?>
                    <div class="table-responsive">
                        <table class="talent-table">
                            <thead>
                                <tr>
                                    <th class="col-sl">SL</th>
                                    <th class="col-name">Name</th>
                                    <th class="col-usn">USN</th>
                                    <th class="col-inst">Inst</th>
                                    <th class="col-dept">Department</th>
                                    <th class="col-sem">Sem</th>
                                    <th class="col-phone">Phone No</th>
                                    <th class="col-email">Email ID</th>
                                    <th class="col-skills">Matching Skills</th>
                                    <th class="col-projects">Matching Projects</th>
                                    <th class="col-ai">AI Match & Reasoning</th>
                                    <th class="col-resume no-print">Resume</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sl = ($page - 1) * $limit + 1;
                                foreach ($results['data'] as $s): 
                                    $score = (int)($s['match_score'] ?? 50);
                                    $scoreBg = ($score >= 75) ? '#059669' : (($score >= 50) ? '#D97706' : '#64748B');
                                    $instClass = (strtoupper($s['institution']) === INSTITUTION_GMIT) ? 'gmit' : 'gmu';
                                    $deptDisplay = htmlspecialchars((string)($s['discipline'] ?: '-'));
                                ?>
                                    <tr>
                                        <!-- 1. SL No -->
                                        <td class="col-sl"><?php echo $sl++; ?></td>

                                        <!-- 2. Name -->
                                        <td class="col-name" title="<?php echo htmlspecialchars((string)$s['name']); ?>">
                                            <?php echo htmlspecialchars((string)$s['name']); ?>
                                        </td>

                                        <!-- 3. USN -->
                                        <td class="col-usn">
                                            <?php echo htmlspecialchars((string)$s['usn']); ?>
                                        </td>

                                        <!-- 4. Institution -->
                                        <td class="col-inst">
                                            <span class="inst-badge <?php echo $instClass; ?>">
                                                <?php echo htmlspecialchars((string)$s['institution']); ?>
                                            </span>
                                        </td>

                                        <!-- 5. Department -->
                                        <td class="col-dept" title="<?php echo $deptDisplay; ?>">
                                            <?php echo $deptDisplay; ?>
                                        </td>

                                        <!-- 6. Semester -->
                                        <td class="col-sem">
                                            <?php echo htmlspecialchars((string)($s['sem'] ?: '-')); ?><?php echo is_numeric($s['sem']) ? 'th' : ''; ?>
                                        </td>

                                        <!-- 7. Phone No -->
                                        <td class="col-phone">
                                            <?php if (!empty($s['phone']) && $s['phone'] !== 'N/A'): ?>
                                                <a href="tel:<?php echo htmlspecialchars($s['phone']); ?>" style="color:inherit; text-decoration:none;">
                                                    <?php echo htmlspecialchars($s['phone']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 8. Email ID -->
                                        <td class="col-email" title="<?php echo htmlspecialchars((string)$s['email']); ?>">
                                            <?php if (!empty($s['email']) && $s['email'] !== 'N/A'): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($s['email']); ?>" style="color:inherit; text-decoration:none;">
                                                    <?php echo htmlspecialchars($s['email']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 9. Matching Skills -->
                                        <td class="col-skills">
                                            <?php if (!empty($s['matched_skills'])): 
                                                $displayedSkills = array_slice($s['matched_skills'], 0, 3);
                                                $extraSkillsCount = count($s['matched_skills']) - 3;
                                            ?>
                                                <?php foreach ($displayedSkills as $sk): ?>
                                                    <span class="skill-tag <?php echo !empty($sk['is_core']) ? 'core' : ''; ?>" title="<?php echo htmlspecialchars($sk['name']); ?>">
                                                        <?php echo htmlspecialchars($sk['name']); ?>
                                                        <?php if (!empty($sk['is_verified'])): ?>
                                                            <i class="fas fa-check-circle" title="Verified Skill"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if ($extraSkillsCount > 0): ?>
                                                    <span class="skill-tag" style="background:#E2E8F0; color:#334155; font-weight:600;" 
                                                          title="<?php echo htmlspecialchars(implode(', ', array_column(array_slice($s['matched_skills'], 3), 'name'))); ?>">
                                                        +<?php echo $extraSkillsCount; ?> more
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted); font-size:10.5px;">Related Tools</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 10. Matching Projects -->
                                        <td class="col-projects">
                                            <?php if (!empty($s['matched_projects'])): 
                                                $displayedProjects = array_slice($s['matched_projects'], 0, 2);
                                            ?>
                                                <?php foreach ($displayedProjects as $pr): 
                                                    $projectJson = htmlspecialchars(json_encode([
                                                        'student' => $s['name'],
                                                        'usn' => $s['usn'],
                                                        'title' => $pr['title'],
                                                        'tech' => $pr['tech'] ?? '',
                                                        'summary' => $pr['summary'] ?? '',
                                                        'link' => $pr['link'] ?? '',
                                                        'is_verified' => $pr['is_verified'] ?? 0
                                                    ]), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                    <div class="project-compact-row">
                                                        <span class="proj-name" title="<?php echo htmlspecialchars($pr['title']); ?>">
                                                            &bull; <?php echo htmlspecialchars($pr['title']); ?>
                                                        </span>
                                                        <?php if (!empty($pr['is_verified'])): ?>
                                                            <span style="display:inline-flex; align-items:center; gap:2px; font-size:9.5px; font-weight:700; background:#ECFDF5; color:#059669; border:1px solid #A7F3D0; border-radius:4px; padding:0 4px; height:16px;" title="Viva-defended & verified project proof">🛡️ Defended</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($pr['tech'])): ?>
                                                            <span class="proj-tech-pill"><?php echo htmlspecialchars(mb_substr($pr['tech'], 0, 16)) . (mb_strlen($pr['tech']) > 16 ? '...' : ''); ?></span>
                                                        <?php endif; ?>
                                                        <span class="proj-btn-view" onclick='openProjectModal(<?php echo $projectJson; ?>)' title="View full project summary">
                                                            <i class="fas fa-info-circle"></i>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php if (count($s['matched_projects']) > 2): ?>
                                                    <div style="font-size:10px; color:var(--brand); font-weight:600; margin-top:2px;">
                                                        +<?php echo count($s['matched_projects']) - 2; ?> more project(s)
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted); font-size:10.5px;">No projects listed</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 11. AI Match & Reasoning -->
                                        <td class="col-ai">
                                            <?php 
                                            $pow = $s['proof_of_work'] ?? [];
                                            $aiReviewJson = htmlspecialchars(json_encode([
                                                'name' => $s['name'],
                                                'usn' => $s['usn'],
                                                'role' => $roleQuery,
                                                'score' => $score,
                                                'fit_level' => $s['fit_level'] ?? (($score >= 75) ? 'Top Tier Fit' : (($score >= 50) ? 'Strong Fit' : 'Potential')),
                                                'reasoning' => $s['ai_reasoning'] ?? '',
                                                'standout' => $s['standout_project'] ?? '',
                                                'proof_of_work' => $pow
                                            ]), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <div style="display:flex; align-items:center; gap:5px; flex-wrap:wrap;">
                                                <span class="ai-compact-badge" style="background:<?php echo $scoreBg; ?>;">
                                                    <?php echo $score; ?>%
                                                </span>
                                                <span style="font-size:10.5px; font-weight:700; color:var(--text-secondary);">
                                                    <?php echo htmlspecialchars($s['fit_level'] ?? (($score >= 75) ? 'Top Tier Fit' : (($score >= 50) ? 'Strong Fit' : 'Potential'))); ?>
                                                </span>
                                                <span class="proj-btn-view" style="margin-left:auto; color:var(--brand);" onclick='openAiReviewModal(<?php echo $aiReviewJson; ?>)' title="Read Deep AI Technical Evaluation & Verification Audit">
                                                    <i class="fas fa-brain"></i>
                                                </span>
                                            </div>
                                            <!-- Proof-of-Work Credibility Badge -->
                                            <div style="margin-top:3px;">
                                                <?php if (!empty($pow['viva_verified'])): ?>
                                                    <span class="pow-badge verified" title="Oral project defense verified with score <?php echo round($pow['viva_score']); ?>%">
                                                        <i class="fas fa-shield-check"></i> Verified Viva (<?php echo round($pow['viva_score']); ?>%)
                                                    </span>
                                                <?php elseif (!empty($pow['skills_verified']) && $pow['skills_verified'] > 0): ?>
                                                    <span class="pow-badge verified" title="<?php echo $pow['skills_verified']; ?> test-verified skills">
                                                        <i class="fas fa-user-check"></i> <?php echo $pow['skills_verified']; ?> Tests Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="pow-badge unverified" title="Self-reported claim: No project defense viva transcript on record">
                                                        <i class="fas fa-shield-exclamation"></i> Self-Reported Claim
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ai-reason-compact" title="<?php echo htmlspecialchars($s['ai_reasoning']); ?>" style="cursor:pointer;" onclick='openAiReviewModal(<?php echo $aiReviewJson; ?>)'>
                                                <i class="fas fa-sparkles" style="color:var(--gold); font-size:9.5px; margin-right:2px;"></i>
                                                <?php echo htmlspecialchars($s['ai_reasoning']); ?>
                                            </div>
                                        </td>

                                        <!-- 12. Resume -->
                                        <td class="col-resume no-print">
                                            <?php if (!empty($s['has_resume'])): ?>
                                                <a href="<?php echo htmlspecialchars($s['resume_url']); ?>" target="_blank" class="btn btn-outline" style="padding:3px 7px; font-size:11px; border-radius:3px;" title="View PDF Resume">
                                                    <i class="fas fa-file-pdf" style="color:#DC2626;"></i> PDF
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size:10.5px; color:var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if (($results['total_pages'] ?? 1) > 1): ?>
                        <div class="pagination-container no-print">
                            <div>
                                Page <strong><?php echo $page; ?></strong> of <strong><?php echo $results['total_pages']; ?></strong>
                            </div>
                            <div class="pagination-links">
                                <?php for ($p = 1; $p <= $results['total_pages']; $p++): 
                                    if ($p > 10 && $p < $results['total_pages']) continue;
                                    $pageQuery = $savedFilters;
                                    $pageQuery['page'] = $p;
                                    $pUrl = 'find_students.php?' . http_build_query($pageQuery);
                                ?>
                                    <a href="<?php echo htmlspecialchars($pUrl); ?>" class="page-link <?php echo ($p === $page) ? 'active' : ''; ?>">
                                        <?php echo $p; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Matching Candidates Found</h3>
                        <p>No candidates found matching "<strong><?php echo htmlspecialchars($roleQuery); ?></strong>" with the current filters. Try changing or clearing the institution/department filter.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <!-- Initial State -->
            <section class="table-card">
                <div class="empty-state">
                    <i class="fas fa-search-plus"></i>
                    <h3>Ready to Search Candidate Talent</h3>
                    <p>Enter any job role or click a popular suggestion chip above. The search persists across page reloads.</p>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Modal for Full Project Details -->
    <div id="projectModal" class="modal-overlay" onclick="closeProjectModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div>
                    <h3 id="modalProjectTitle">Project Details</h3>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:3px;">
                        Candidate: <strong id="modalCandidateName" style="color:var(--text-primary);"></strong> (<span id="modalCandidateUsn"></span>)
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeProjectModal()">&times;</button>
            </div>
            <div id="modalProjectBody" style="display:flex; flex-direction:column; gap:12px; font-size:13px; line-height:1.5;">
                <div id="modalProjectTechBox">
                    <strong style="color:var(--brand); font-size:11.5px; text-transform:uppercase;">Technologies / Tech Stack:</strong>
                    <div id="modalProjectTech" style="font-weight:600; margin-top:2px;"></div>
                </div>
                <div>
                    <strong style="color:#475569; font-size:11.5px; text-transform:uppercase;">Description / Summary:</strong>
                    <p id="modalProjectSummary" style="color:#1E293B; margin-top:4px; white-space:pre-line;"></p>
                </div>
                <div id="modalProjectLinkBox">
                    <a id="modalProjectLink" href="#" target="_blank" class="btn btn-outline" style="font-size:12px;">
                        <i class="fas fa-external-link-alt"></i> Open Project Link
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Deep AI Technical Evaluation -->
    <div id="aiReviewModal" class="modal-overlay" onclick="closeAiReviewModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()" style="max-width: 620px; border-top: 4px solid var(--brand);">
            <div class="modal-header">
                <div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="ai-badge" style="background:var(--brand);"><i class="fas fa-brain"></i> AI Technical Appraisal</span>
                        <span id="modalAiFitBadge" class="ai-compact-badge" style="background:#059669; font-size:11.5px; padding:2px 8px;"></span>
                    </div>
                    <h3 id="modalAiCandidateName" style="margin-top:8px; font-size:16px;">Candidate Name</h3>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
                        Target Role Evaluated: <strong id="modalAiTargetRole" style="color:var(--brand);"></strong> &bull; USN: <span id="modalAiUsn"></span>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeAiReviewModal()">&times;</button>
            </div>
            <div style="display:flex; flex-direction:column; gap:14px; font-size:13px; line-height:1.6; padding-top:4px;">
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:14px;">
                    <div style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:6px; display:flex; align-items:center; gap:5px;">
                        <i class="fas fa-sparkles" style="color:var(--gold);"></i> Technical Capability & Architecture Appraisal
                    </div>
                    <p id="modalAiAppraisalText" style="color:#0F172A; font-size:13.5px; margin:0; line-height:1.55;"></p>
                </div>

                <!-- Proof of Work & Anti-Fake Audit Box -->
                <div id="modalAiPowBox" style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:6px; padding:12px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                        <span style="font-size:11px; font-weight:700; color:#166534; text-transform:uppercase; display:flex; align-items:center; gap:5px;">
                            <i class="fas fa-shield-alt"></i> Proof-of-Work & Authenticity Audit
                        </span>
                        <span id="modalAiPowStatus" class="pow-badge verified" style="font-size:10.5px;"></span>
                    </div>
                    <div id="modalAiPowDetails" style="font-size:12px; color:#1E293B; line-height:1.5;"></div>
                    <div id="modalAiVivaFeedbackBox" style="margin-top:8px; padding-top:8px; border-top:1px dashed #86EFAC; font-size:11.5px; color:#14532D; display:none;">
                        <strong>Viva Defense Transcript Feedback:</strong>
                        <div id="modalAiVivaFeedbackText" style="font-style:italic; margin-top:2px;"></div>
                    </div>
                </div>

                <div id="modalAiStandoutBox" style="background:#FFFBFB; border:1px solid #FECACA; border-radius:6px; padding:12px;">
                    <div style="font-size:11px; font-weight:700; color:#991B1B; text-transform:uppercase; margin-bottom:4px; display:flex; align-items:center; gap:5px;">
                        <i class="fas fa-star" style="color:#F59E0B;"></i> Standout Evidence Project
                    </div>
                    <div id="modalAiStandoutText" style="font-weight:600; color:#1E293B; font-size:12.5px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openProjectModal(data) {
            document.getElementById('modalProjectTitle').innerText = data.title || 'Project Details';
            document.getElementById('modalCandidateName').innerText = data.student || '';
            document.getElementById('modalCandidateUsn').innerText = data.usn || '';
            
            const techEl = document.getElementById('modalProjectTech');
            const techBox = document.getElementById('modalProjectTechBox');
            if (data.tech) {
                techEl.innerText = data.tech;
                techBox.style.display = 'block';
            } else {
                techBox.style.display = 'none';
            }

            document.getElementById('modalProjectSummary').innerText = data.summary || 'No detailed description provided.';

            const linkEl = document.getElementById('modalProjectLink');
            const linkBox = document.getElementById('modalProjectLinkBox');
            if (data.link && data.link.startsWith('http')) {
                linkEl.href = data.link;
                linkBox.style.display = 'block';
            } else {
                linkBox.style.display = 'none';
            }

            document.getElementById('projectModal').style.display = 'flex';
        }

        function closeProjectModal(e) {
            document.getElementById('projectModal').style.display = 'none';
        }

        function openAiReviewModal(data) {
            document.getElementById('modalAiCandidateName').innerText = data.name || 'Candidate';
            document.getElementById('modalAiUsn').innerText = data.usn || '';
            document.getElementById('modalAiTargetRole').innerText = data.role || 'Target Role';
            
            const fitBadge = document.getElementById('modalAiFitBadge');
            fitBadge.innerText = (data.score || '85') + '% ' + (data.fit_level || 'Fit');
            const scoreNum = parseInt(data.score) || 75;
            fitBadge.style.background = (scoreNum >= 75) ? '#059669' : ((scoreNum >= 50) ? '#D97706' : '#64748B');

            document.getElementById('modalAiAppraisalText').innerText = data.reasoning || 'Detailed technical evaluation completed.';

            // Proof-of-Work & Authenticity Audit Population
            const powBox = document.getElementById('modalAiPowBox');
            const powStatus = document.getElementById('modalAiPowStatus');
            const powDetails = document.getElementById('modalAiPowDetails');
            const vivaBox = document.getElementById('modalAiVivaFeedbackBox');
            const vivaText = document.getElementById('modalAiVivaFeedbackText');
            const pow = data.proof_of_work || {};

            if (pow.viva_verified) {
                powBox.style.background = '#F0FDF4';
                powBox.style.borderColor = '#BBF7D0';
                powStatus.className = 'pow-badge verified';
                powStatus.style.background = '#DCFCE7';
                powStatus.style.color = '#15803D';
                powStatus.innerHTML = '<i class="fas fa-shield-check"></i> Verified Viva (' + Math.round(pow.viva_score || 0) + '%)';
                powDetails.innerHTML = '<strong>Authentic Proof-of-Work:</strong> Candidate successfully defended their project implementation against rigorous AI oral viva examination with a score of <strong>' + Math.round(pow.viva_score) + '%</strong>. Code claims are validated.';
                if (pow.viva_feedback) {
                    vivaText.innerText = pow.viva_feedback;
                    vivaBox.style.display = 'block';
                } else {
                    vivaBox.style.display = 'none';
                }
            } else if (pow.skills_verified && pow.skills_verified > 0) {
                powBox.style.background = '#EFF6FF';
                powBox.style.borderColor = '#BFDBFE';
                powStatus.className = 'pow-badge verified';
                powStatus.style.background = '#DBEAFE';
                powStatus.style.color = '#1D4ED8';
                powStatus.innerHTML = '<i class="fas fa-user-check"></i> ' + pow.skills_verified + ' Tests Verified';
                powDetails.innerHTML = '<strong>Assessment Verified:</strong> Candidate has completed <strong>' + pow.skills_verified + '</strong> verified skill/technical proctored assessments.';
                vivaBox.style.display = 'none';
            } else {
                powBox.style.background = '#FFFBEB';
                powBox.style.borderColor = '#FDE68A';
                powStatus.className = 'pow-badge unverified';
                powStatus.style.background = '#FEF3C7';
                powStatus.style.color = '#B45309';
                powStatus.innerHTML = '<i class="fas fa-shield-exclamation"></i> Self-Reported Claim';
                powDetails.innerHTML = '<strong>Caution (Self-Reported):</strong> Candidate claims skills and projects on their resume, but has <em>not yet completed an oral viva defense or proctored code verification</em>. Match score capped according to anti-fake bar.';
                vivaBox.style.display = 'none';
            }

            const standoutBox = document.getElementById('modalAiStandoutBox');
            const standoutEl = document.getElementById('modalAiStandoutText');
            if (data.standout && data.standout !== 'None') {
                standoutEl.innerText = data.standout;
                standoutBox.style.display = 'block';
            } else {
                standoutBox.style.display = 'none';
            }

            document.getElementById('aiReviewModal').style.display = 'flex';
        }

        function closeAiReviewModal(e) {
            document.getElementById('aiReviewModal').style.display = 'none';
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProjectModal();
                closeAiReviewModal();
            }
        });

        /* ============================================================== */
        /* AI LOADING OVERLAY CONTROLLER                                   */
        /* ============================================================== */
        let aiStatusInterval = null;
        const aiStatusMessages = [
            "Deconstructing target role & competency graph...",
            "Analyzing student repositories & project architectures...",
            "Evaluating verified viva defense transcripts & proof-of-work...",
            "Cross-referencing domain skills and framework competency...",
            "Synthesizing deep AI technical appraisals & fit ratings...",
            "Ranking top candidate talent..."
        ];

        function showAiLoading(customText) {
            const overlay = document.getElementById('aiLoadingOverlay');
            const statusEl = document.getElementById('aiLoadingStatus');
            if (!overlay) return;
            
            if (statusEl) {
                statusEl.innerText = customText || aiStatusMessages[0];
            }

            overlay.classList.add('active');

            let msgIdx = 0;
            if (aiStatusInterval) clearInterval(aiStatusInterval);
            aiStatusInterval = setInterval(function() {
                msgIdx = (msgIdx + 1) % aiStatusMessages.length;
                if (statusEl) {
                    statusEl.style.opacity = '0';
                    setTimeout(function() {
                        statusEl.innerText = aiStatusMessages[msgIdx];
                        statusEl.style.opacity = '1';
                    }, 200);
                }
            }, 1800);
        }

        function hideAiLoading() {
            const overlay = document.getElementById('aiLoadingOverlay');
            if (overlay) overlay.classList.remove('active');
            if (aiStatusInterval) clearInterval(aiStatusInterval);
        }

        function clearReasoningFilter() {
            const el = document.getElementById('filterReasoning');
            if (el) {
                el.value = '';
                showAiLoading('Refreshing candidate filter...');
                el.form.submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Form submit trigger
            const searchForm = document.querySelector('.search-form');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    const roleVal = document.getElementById('roleInput')?.value?.trim();
                    showAiLoading(roleVal ? 'Analyzing candidates for: "' + roleVal + '"...' : 'Analyzing candidates...');
                });
            }

            // Quick suggestion chips
            document.querySelectorAll('.chip-btn').forEach(function(chip) {
                chip.addEventListener('click', function() {
                    showAiLoading('Finding candidates for: ' + this.innerText.trim() + '...');
                });
            });

            // Pagination links
            document.querySelectorAll('.page-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (!this.classList.contains('active')) {
                        showAiLoading('Loading page ' + this.innerText.trim() + ' evaluations...');
                    }
                });
            });

            // Reset search button
            document.querySelectorAll('.btn-danger-light').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    showAiLoading('Resetting talent search & filters...');
                });
            });
        });

        // Hide overlay on browser back/forward navigation
        window.addEventListener('pageshow', function() {
            hideAiLoading();
        });
    </script>

    <!-- AI Intelligence Loading Overlay -->
    <div id="aiLoadingOverlay" class="ai-loading-overlay no-print">
        <div class="ai-loading-modal">
            <div class="ai-loading-radar">
                <div class="radar-pulse"></div>
                <div class="radar-pulse delay-1"></div>
                <div class="radar-core">
                    <i class="fas fa-brain"></i>
                </div>
            </div>
            <h3 class="ai-loading-title">AI Intelligence Engine at Work</h3>
            <p id="aiLoadingStatus" class="ai-loading-status">Analyzing student repositories & project architectures...</p>
            
            <div class="ai-loading-progress-bar">
                <div class="ai-loading-progress-fill"></div>
            </div>

            <div class="ai-loading-tips">
                <i class="fas fa-sparkles"></i> <span>Evaluating verified viva defenses, technical depth, and fit metrics</span>
            </div>
        </div>
    </div>

</body>
</html>
