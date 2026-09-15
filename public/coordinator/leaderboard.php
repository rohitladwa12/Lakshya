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

// Force Refresh Cache if requested
if (isset($_GET['refresh_cache'])) {
    LeaderboardService::clearCache();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle POST State (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_page'])) {
    if (isset($_POST['reset_filters'])) {
        SessionFilterHelper::clearFilters('coord_leaderboard');
    } else {
        SessionFilterHelper::handlePostToSession('coord_leaderboard', $_POST, 'leaderboard.php');
    }
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
$available_branches = array_values(array_unique(getCoordinatorDisciplineFilters($myDepartment)));
$branch_filter_val = clean($persistedFilters['branch'] ?? '');
$semester_filters_all = getCoordinatorSemesterFilters($myDepartment);

if ($view === 'local') {
    // Local: Scope restricted to Coordinator's Department Disciplines & Semesters
    if (!empty($branch_filter_val) && in_array($branch_filter_val, $available_branches)) {
        $coordFilters['discipline'] = [$branch_filter_val];
    } else {
        $coordFilters['discipline'] = $available_branches;
    }

    if ($sem_filter > 0 && in_array($sem_filter, $semester_filters_all)) {
        $coordFilters['semesters'] = [$sem_filter];
    } else {
        $coordFilters['semesters'] = $semester_filters_all;
    }
} else {
    // Global: Full University/Cross-Institutional ranking matching Student Global view
    if (!empty($branch_filter_val)) {
        $coordFilters['discipline'] = [$branch_filter_val];
    }
    if ($sem_filter > 0) {
        $coordFilters['semesters'] = [$sem_filter];
    }
}

if ($inst_filter === 'gmu') {
    $coordFilters['institution'] = INSTITUTION_GMU;
} elseif ($inst_filter === 'gmit') {
    $coordFilters['institution'] = INSTITUTION_GMIT;
}

// Map advanced performance filters from session
if (isset($persistedFilters['min_sgpa_all']) && $persistedFilters['min_sgpa_all'] !== '') $coordFilters['min_sgpa_all'] = (float)$persistedFilters['min_sgpa_all'];
if (isset($persistedFilters['min_apt']) && $persistedFilters['min_apt'] !== '') $coordFilters['min_aptitude'] = (float)$persistedFilters['min_apt'];
if (isset($persistedFilters['min_tech']) && $persistedFilters['min_tech'] !== '') $coordFilters['min_technical'] = (float)$persistedFilters['min_tech'];
if (isset($persistedFilters['min_hr']) && $persistedFilters['min_hr'] !== '') $coordFilters['min_hr'] = (float)$persistedFilters['min_hr'];
if (isset($persistedFilters['min_total']) && $persistedFilters['min_total'] !== '') $coordFilters['min_total'] = (float)$persistedFilters['min_total'];
if (!empty($selected_skills)) $coordFilters['required_skills'] = $selected_skills;

// Fetch Rankings using Centralized Service for Absolute Consistency with Student Leaderboard (cached 5 mins)
$leaderboard = LeaderboardService::getRankingsWithHistory($coordFilters);

// Summary KPI calculations
$total_count = count($leaderboard);
$top_candidate = $total_count > 0 ? $leaderboard[0] : null;
$avg_score = $total_count > 0 ? round(array_sum(array_column($leaderboard, 'total')) / $total_count, 1) : 0;
$avg_tech = $total_count > 0 ? round(array_sum(array_column($leaderboard, 'technical')) / $total_count, 1) : 0;
$avg_apt = $total_count > 0 ? round(array_sum(array_column($leaderboard, 'aptitude')) / $total_count, 1) : 0;
$avg_hr = $total_count > 0 ? round(array_sum(array_column($leaderboard, 'hr')) / $total_count, 1) : 0;

$showAll = (bool)($persistedFilters['show_all'] ?? false);
$limit = $showAll ? max(1, count($leaderboard)) : 25;
$total_entries = count($leaderboard);
$total_pages = $showAll ? 1 : (int)ceil($total_entries / $limit);

$page = (int)($persistedFilters['page'] ?? 1);
if ($page < 1) $page = 1;
if ($total_pages > 0 && $page > $total_pages) $page = $total_pages;

$offset = ($page - 1) * $limit;
$display_leaderboard = array_slice($leaderboard, $offset, $limit);

// Active filters count for chip summary
$activeFiltersCount = 0;
if ($inst_filter !== 'all') $activeFiltersCount++;
if ($sem_filter > 0) $activeFiltersCount++;
if (!empty($branch_filter_val)) $activeFiltersCount++;
if (!empty($persistedFilters['min_sgpa_all'])) $activeFiltersCount++;
if (!empty($persistedFilters['min_apt'])) $activeFiltersCount++;
if (!empty($persistedFilters['min_tech'])) $activeFiltersCount++;
if (!empty($persistedFilters['min_hr'])) $activeFiltersCount++;
if (!empty($persistedFilters['min_total'])) $activeFiltersCount++;
if (!empty($selected_skills)) $activeFiltersCount += count($selected_skills);

/**
 * Render Table Rows for Initial Load & AJAX Pagination
 */
function renderLeaderboardRows($display_leaderboard) {
    ob_start();
    if (empty($display_leaderboard)): ?>
        <tr>
            <td colspan="20" style="text-align:center; padding: 60px 20px; color: var(--text-muted);">
                <i class="fas fa-user-slash" style="font-size: 38px; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                <div style="font-size: 16px; font-weight: 700; color: var(--primary);">No candidates found</div>
                <div style="font-size: 13px; margin-top: 4px;">Try adjusting your search criteria or resetting filters.</div>
            </td>
        </tr>
    <?php else:
        foreach ($display_leaderboard as $e): ?>
            <tr data-usn="<?php echo htmlspecialchars($e['usn']); ?>">
                <td style="text-align: center;">
                    <input type="checkbox" class="student-select" value="<?php echo htmlspecialchars($e['usn']); ?>" onchange="updateBulkDock()" style="cursor: pointer;">
                </td>
                
                <td style="text-align: center;">
                    <?php if ($e['rank'] == 1): ?>
                        <span class="rank-medal-gold" title="Rank 1 Leader"><i class="fas fa-crown"></i> 1</span>
                    <?php elseif ($e['rank'] == 2): ?>
                        <span class="rank-medal-silver" title="Rank 2"><i class="fas fa-medal"></i> 2</span>
                    <?php elseif ($e['rank'] == 3): ?>
                        <span class="rank-medal-bronze" title="Rank 3"><i class="fas fa-award"></i> 3</span>
                    <?php else: ?>
                        <span class="rank-badge-num">#<?php echo $e['rank']; ?></span>
                    <?php endif; ?>
                </td>

                <td>
                    <div class="candidate-cell">
                        <?php if (!empty($e['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($e['photo']); ?>" class="candidate-avatar-thumb" alt="<?php echo htmlspecialchars($e['name']); ?>" onerror="this.outerHTML='<div class=\'candidate-avatar-initials\'><?php echo strtoupper(substr($e['name'], 0, 1)); ?></div>'">
                        <?php else: ?>
                            <div class="candidate-avatar-initials"><?php echo strtoupper(substr($e['name'], 0, 1)); ?></div>
                        <?php endif; ?>
                        
                        <div class="candidate-info-block">
                            <span class="candidate-name-text"><?php echo htmlspecialchars($e['name']); ?></span>
                            <span class="candidate-usn-text"><?php echo htmlspecialchars($e['usn']); ?></span>
                        </div>
                    </div>
                </td>

                <td>
                    <span style="font-size: 11.5px; font-weight: 600; color: #475569;"><?php echo htmlspecialchars($e['discipline']); ?></span>
                </td>

                <td style="text-align: center;">
                    <span class="badge-sem"><?php echo !empty($e['sem']) ? 'Sem ' . $e['sem'] : '-'; ?></span>
                </td>

                <td style="text-align: center;">
                    <span class="badge-inst badge-inst-<?php echo strtolower($e['institution']); ?>">
                        <?php echo htmlspecialchars($e['institution']); ?>
                    </span>
                </td>

                <?php for($semNum=1; $semNum<=8; $semNum++): 
                    $val = $e['academic_history'][$semNum]['sgpa'] ?? 0;
                ?>
                    <td style="text-align: center;">
                        <?php if ($val > 0): ?>
                            <span class="badge-sgpa" onclick="showSgpaDetails('<?php echo htmlspecialchars($e['usn']); ?>', '<?php echo addslashes($e['name']); ?>')" title="Semester <?php echo $semNum; ?> SGPA: <?php echo number_format($val, 2); ?> (Click for history)">
                                <?php echo number_format($val, 2); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #cbd5e1;">-</span>
                        <?php endif; ?>
                    </td>
                <?php endfor; ?>

                <!-- Technical Pillar -->
                <td>
                    <div class="pillar-meter-wrap">
                        <span class="pillar-val-txt"><?php echo round($e['technical']); ?>%</span>
                        <div class="pillar-meter-bar">
                            <div class="pillar-meter-fill fill-tech" style="width: <?php echo min(100, max(0, $e['technical'])); ?>%;"></div>
                        </div>
                    </div>
                </td>

                <!-- Aptitude Pillar -->
                <td>
                    <div class="pillar-meter-wrap">
                        <span class="pillar-val-txt"><?php echo round($e['aptitude']); ?>%</span>
                        <div class="pillar-meter-bar">
                            <div class="pillar-meter-fill fill-apt" style="width: <?php echo min(100, max(0, $e['aptitude'])); ?>%;"></div>
                        </div>
                    </div>
                </td>

                <!-- HR Pillar -->
                <td>
                    <div class="pillar-meter-wrap">
                        <span class="pillar-val-txt"><?php echo round($e['hr']); ?>%</span>
                        <div class="pillar-meter-bar">
                            <div class="pillar-meter-fill fill-hr" style="width: <?php echo min(100, max(0, $e['hr'])); ?>%;"></div>
                        </div>
                    </div>
                </td>

                <!-- Skills Tags (constrained to prevent overflow) -->
                <td style="max-width: 200px; width: 200px;">
                    <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 195px; align-items: center;">
                        <?php 
                        $topSkills = array_slice($e['skills'], 0, 2);
                        foreach($topSkills as $sk): ?>
                            <span class="skill-tag-pill" title="<?php echo htmlspecialchars($sk); ?>">
                                <?php echo htmlspecialchars($sk); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if(count($e['skills']) > 2): ?>
                            <span style="font-size: 10px; color: #64748b; font-weight: 700; background: #e2e8f0; padding: 2px 5px; border-radius: 4px; white-space: nowrap; cursor: default;" title="<?php echo htmlspecialchars(implode(', ', array_slice($e['skills'], 2))); ?>">
                                +<?php echo count($e['skills'])-2; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </td>

                <!-- Total Score Pill -->
                <td style="text-align: right; white-space: nowrap;">
                    <div class="total-score-pill" 
                         onclick='showScoreBreakdown(<?php echo htmlspecialchars(json_encode([
                             "name" => $e["name"],
                             "discipline" => $e["discipline"],
                             "sem" => $e["sem"],
                             "apt" => round($e["aptitude"], 1),
                             "tech" => round($e["technical"], 1),
                             "hr" => round($e["hr"], 1),
                             "ai_avg" => round($e["ai_avg"], 1),
                             "ai_pts" => round($e["ai_avg"] * 0.7, 1),
                             "port_raw" => $e["portfolio"],
                             "port_skills" => $e["skills_count"] ?? count($e["skills"]),
                             "port_projects" => $e["projects_count"] ?? 0,
                             "port_pts" => round($e["portfolio"] * 0.3, 1),
                             "base_total" => $e["base_total"] ?? round(($e["ai_avg"] * 0.7) + ($e["portfolio"] * 0.3), 1),
                             "attempts" => $e["total_attempts"] ?? $e["ai_count"],
                             "activity_bonus" => $e["activity_bonus"] ?? 0,
                             "inactivity_decay" => $e["inactivity_decay_pct"] ?? 0,
                             "total" => $e["total"]
                         ]), ENT_QUOTES, "UTF-8"); ?>)'
                         title="Click to view full calculation breakdown">
                        <span><?php echo $e['total']; ?></span>
                        <i class="fas fa-calculator"></i>
                    </div>
                </td>
            </tr>
        <?php endforeach;
    endif;
    return ob_get_clean();
}

/**
 * Render Pagination Container HTML
 */
function renderLeaderboardPagination($page, $total_pages, $offset, $limit, $total_entries) {
    if ($total_pages <= 1) return '';
    ob_start(); ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($total_entries, $offset + $limit); ?></strong> of <strong><?php echo number_format($total_entries); ?></strong> candidates
        </div>
        <div class="pagination-links">
            <?php
            if ($page > 1) {
                echo '<a href="javascript:void(0)" onclick="goToPage(' . ($page - 1) . ')" class="page-link-btn">&laquo; Prev</a>';
            }

            $range = 2;
            $start = max(1, $page - $range);
            $end = min($total_pages, $page + $range);

            if ($start > 1) {
                echo '<a href="javascript:void(0)" onclick="goToPage(1)" class="page-link-btn">1</a>';
                if ($start > 2) echo '<span style="padding: 6px 8px; color: #94a3b8;">...</span>';
            }

            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '<a href="javascript:void(0)" onclick="goToPage(' . $i . ')" class="page-link-btn ' . $active . '">' . $i . '</a>';
            }

            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span style="padding: 6px 8px; color: #94a3b8;">...</span>';
                echo '<a href="javascript:void(0)" onclick="goToPage(' . $total_pages . ')" class="page-link-btn">' . $total_pages . '</a>';
            }

            if ($page < $total_pages) {
                echo '<a href="javascript:void(0)" onclick="goToPage(' . ($page + 1) . ')" class="page-link-btn">Next &raquo;</a>';
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Handle Fast AJAX Pagination Requests
if (isset($_GET['ajax_page']) || isset($_POST['ajax_page'])) {
    $targetPage = (int)($_REQUEST['page'] ?? 1);
    if ($targetPage < 1) $targetPage = 1;
    if ($total_pages > 0 && $targetPage > $total_pages) $targetPage = $total_pages;

    $targetOffset = ($targetPage - 1) * $limit;
    $targetSlice = array_slice($leaderboard, $targetOffset, $limit);

    // Save page into session
    if (!isset($_SESSION['coord_leaderboard'])) $_SESSION['coord_leaderboard'] = [];
    $_SESSION['coord_leaderboard']['page'] = $targetPage;

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'page' => $targetPage,
        'total_pages' => $total_pages,
        'total_entries' => $total_entries,
        'rows_html' => renderLeaderboardRows($targetSlice),
        'pagination_html' => renderLeaderboardPagination($targetPage, $total_pages, $targetOffset, $limit, $total_entries)
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Leaderboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f172a;
            --primary-light: #1e293b;
            --maroon: #800000;
            --maroon-dark: #660000;
            --maroon-subtle: #fff1f2;
            --accent: #2563eb;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --border-focus: #94a3b8;
            --gold: #f59e0b;
            --silver: #94a3b8;
            --bronze: #d97706;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.06), 0 4px 6px -4px rgba(0, 0, 0, 0.06);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 90px;
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 24px 32px;
        }

        /* --- Header & Scope Bar --- */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 24px;
        }

        .page-title-area {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-icon-badge {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 8px 16px -4px rgba(245, 158, 11, 0.35);
        }

        .page-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .page-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* View Mode Segmented Control */
        .view-segmented-control {
            display: inline-flex;
            background: #e2e8f0;
            padding: 4px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
        }

        .view-segmented-btn {
            border: none;
            background: transparent;
            padding: 8px 18px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .view-segmented-btn.active {
            background: #ffffff;
            color: var(--primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .btn-rules {
            background: #ffffff;
            color: var(--primary-light);
            border: 1px solid var(--border-color);
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: var(--shadow-sm);
        }

        .btn-rules:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .btn-rules.active {
            background: #eff6ff;
            color: #2563eb;
            border-color: #93c5fd;
        }

        .btn-push-main {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            border: none;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);
        }

        .btn-push-main:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(5, 150, 105, 0.35);
        }

        /* --- KPI Summary Cards --- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .kpi-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .kpi-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .kpi-icon-pill {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .kpi-val {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.02em;
            line-height: 1;
        }

        .kpi-subtext {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* KPI Variants */
        .kpi-blue .kpi-icon-pill { background: #eff6ff; color: #2563eb; }
        .kpi-gold .kpi-icon-pill { background: #fffbeb; color: #d97706; }
        .kpi-emerald .kpi-icon-pill { background: #ecfdf5; color: #059669; }
        .kpi-purple .kpi-icon-pill { background: #f5f3ff; color: #7c3aed; }

        /* --- Top 3 Podium Showcase --- */
        .podium-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .podium-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .podium-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .podium-card.rank-1 {
            background: linear-gradient(180deg, #ffffff 0%, #fffdf7 100%);
            border: 2px solid #fde68a;
            box-shadow: 0 8px 20px -4px rgba(245, 158, 11, 0.15);
        }

        .podium-card.rank-2 {
            border: 2px solid #e2e8f0;
        }

        .podium-card.rank-3 {
            border: 2px solid #fed7aa;
        }

        .podium-badge-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .podium-medal-badge {
            font-size: 12px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .medal-1 { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .medal-2 { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .medal-3 { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }

        .podium-user-block {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .podium-avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: var(--shadow-sm);
        }

        .podium-avatar-placeholder {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #1e293b;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            box-shadow: var(--shadow-sm);
        }

        .podium-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }

        .podium-usn {
            font-size: 12px;
            color: var(--text-muted);
            font-family: ui-monospace, monospace;
            margin-top: 2px;
        }

        .podium-pillars-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            background: #f8fafc;
            padding: 8px 10px;
            border-radius: 10px;
            margin-bottom: 14px;
            border: 1px solid #f1f5f9;
        }

        .podium-pillar-item {
            text-align: center;
        }

        .podium-pillar-lbl {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
        }

        .podium-pillar-val {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
        }

        .podium-score-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
        }

        .podium-total-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .podium-total-val {
            font-size: 20px;
            font-weight: 800;
            color: var(--maroon);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* --- Scoring Methodology Drawer --- */
        .methodology-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            margin-bottom: 24px;
            overflow: hidden;
            display: none;
            box-shadow: var(--shadow-md);
            animation: slideDown 0.25s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .methodology-card.active { display: block; }

        .methodology-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .methodology-header span {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .methodology-body {
            padding: 20px 24px;
        }

        .rule-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-top: 14px;
        }

        .rule-item {
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 12.5px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            color: #334155;
            line-height: 1.4;
        }

        .rule-item.warning {
            background: #fff1f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .rule-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            font-size: 14px;
        }

        /* --- Filter Panel --- */
        .filter-panel {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
        }

        .filter-item label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .filter-item select, 
        .filter-item input[type="text"],
        .filter-item input[type="number"] {
            width: 100%;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            font-family: inherit;
            color: var(--text-main);
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .filter-item select:focus, 
        .filter-item input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Multi-select Tags */
        .multi-select-container { position: relative; }

        .selected-tags {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 4px 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            min-height: 38px;
            align-items: center;
        }

        .tag-badge {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #dbeafe;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .tag-badge i {
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .tag-badge i:hover {
            opacity: 1;
            color: #ef4444;
        }

        .selected-tags input {
            border: none;
            outline: none;
            padding: 4px;
            font-size: 12px;
            flex: 1;
            min-width: 90px;
            font-family: inherit;
        }

        .skill-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid var(--border-color);
            z-index: 100;
            display: none;
            box-shadow: var(--shadow-lg);
            border-radius: 0 0 10px 10px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 2px;
        }

        .skill-option {
            padding: 8px 12px;
            font-size: 12.5px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .skill-option:hover {
            background: #f1f5f9;
            color: #2563eb;
        }

        .filter-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 14px;
            margin-top: 6px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .active-chips-area {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .chip-count {
            font-size: 12px;
            font-weight: 700;
            color: #2563eb;
            background: #eff6ff;
            padding: 3px 10px;
            border-radius: 12px;
            border: 1px solid #dbeafe;
        }

        .filter-btn-group {
            display: flex;
            gap: 10px;
        }

        .btn-filter {
            padding: 8px 16px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: #475569;
        }

        .btn-filter:hover {
            background: #f8fafc;
            color: var(--text-main);
            border-color: #cbd5e1;
        }

        .btn-filter-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .btn-filter-primary:hover {
            background: var(--primary-light);
            color: #ffffff;
        }

        /* --- Floating Bulk Action Dock --- */
        .bulk-dock {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            color: white;
            padding: 12px 24px;
            border-radius: 40px;
            box-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.35);
            z-index: 1500;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
            opacity: 0;
        }

        .bulk-dock.active {
            transform: translateX(-50%) translateY(0);
            pointer-events: auto;
            opacity: 1;
        }

        .bulk-dock-badge {
            background: #2563eb;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .bulk-dock-btn {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .bulk-dock-btn:hover {
            box-shadow: 0 0 15px rgba(5, 150, 105, 0.5);
            transform: scale(1.02);
        }

        /* --- Modern Data Table --- */
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table.leaderboard-tbl {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
            table-layout: auto;
        }

        table.leaderboard-tbl th {
            background: #f8fafc;
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        table.leaderboard-tbl td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: var(--text-main);
        }

        table.leaderboard-tbl tr:last-child td {
            border-bottom: none;
        }

        table.leaderboard-tbl tr:hover {
            background: #f8fafc;
        }

        /* Rank Badges */
        .rank-medal-gold { font-size: 18px; color: #f59e0b; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; }
        .rank-medal-silver { font-size: 18px; color: #94a3b8; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; }
        .rank-medal-bronze { font-size: 18px; color: #d97706; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; }
        .rank-badge-num {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 6px;
        }

        /* Candidate Identity Cell */
        .candidate-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }

        .candidate-avatar-thumb {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .candidate-avatar-initials {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #1e293b;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        .candidate-info-block {
            display: flex;
            flex-direction: column;
        }

        .candidate-name-text {
            font-weight: 700;
            color: var(--primary);
            font-size: 13.5px;
            line-height: 1.2;
        }

        .candidate-usn-text {
            font-size: 11.5px;
            color: var(--text-muted);
            font-family: ui-monospace, monospace;
            margin-top: 1px;
        }

        /* Badges */
        .badge-inst {
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-inst-gmu { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-inst-gmit { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }

        .badge-sem {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            background: #f1f5f9;
            padding: 2px 7px;
            border-radius: 6px;
        }

        .badge-sgpa {
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 4px;
            transition: all 0.15s;
        }

        .badge-sgpa:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Pillar Meters */
        .pillar-meter-wrap {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 60px;
        }

        .pillar-meter-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .pillar-meter-fill {
            height: 100%;
            border-radius: 2px;
        }

        .fill-tech { background: #3b82f6; }
        .fill-apt { background: #10b981; }
        .fill-hr { background: #8b5cf6; }

        .pillar-val-txt {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--primary);
        }

        /* Verified Skill Tag */
        .skill-tag-pill {
            background: #f1f5f9;
            color: #334155;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 5px;
            max-width: 90px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
            border: 1px solid #e2e8f0;
        }

        /* Score Badge */
        .total-score-pill {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            padding: 5px 10px;
            border-radius: 9px;
            font-weight: 800;
            font-size: 14px;
            color: var(--maroon);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }

        .total-score-pill:hover {
            border-color: var(--maroon);
            background: var(--maroon-subtle);
            transform: scale(1.06);
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
        }

        .total-score-pill i {
            font-size: 10px;
            opacity: 0.6;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            background: #ffffff;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pagination-info {
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .pagination-links {
            display: flex;
            gap: 6px;
        }

        .page-link-btn {
            padding: 6px 12px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 12.5px;
            font-weight: 600;
            transition: all 0.15s;
        }

        .page-link-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .page-link-btn.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* --- Modals --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(8px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-window {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header-bar {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
        }

        .modal-header-bar h3 {
            font-size: 17px;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close-btn {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 16px;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }

        .modal-close-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .modal-body-area {
            padding: 24px;
            max-height: calc(85vh - 120px);
            overflow-y: auto;
        }

        .modal-footer-bar {
            padding: 16px 24px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Breakdown Modal specific styling */
        .calc-breakdown-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }

        .calc-card-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 12.5px;
            color: #64748b;
        }

        .calc-row strong {
            color: #0f172a;
        }

        .calc-subtotal-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 8px;
            margin-top: 8px;
            border-top: 1px dashed #cbd5e1;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }

        .calc-final-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.25);
        }

        .calc-final-banner .lbl {
            font-size: 13px;
            font-weight: 700;
            color: #94a3b8;
        }

        .calc-final-banner .val {
            font-size: 26px;
            font-weight: 800;
            color: #38bdf8;
        }

        /* Academic History Table */
        .sgpa-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sgpa-table th {
            background: #f8fafc;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }

        .sgpa-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        .sgpa-score-chip {
            font-weight: 700;
            color: #0f172a;
            background: #eff6ff;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .podium-container { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .dashboard-container { padding: 16px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .kpi-grid { grid-template-columns: 1fr; }
            .rule-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="dashboard-container">
        
        <!-- Header & Scope Controls -->
        <div class="page-header">
            <div class="page-title-area">
                <div class="page-icon-badge">
                    <i class="fas fa-trophy"></i>
                </div>
                <div>
                    <h1 class="page-title">Placement Leaderboard</h1>
                    <p class="page-subtitle">
                        <span><i class="fas fa-layer-group"></i> Multi-factor AI Performance & Portfolio Rigor Engine</span>
                        <span>•</span>
                        <span>Scope: <strong><?php echo $view === 'local' ? htmlspecialchars($myDepartment) . ' (Department)' : 'All Institutions (Global)'; ?></strong></span>
                    </p>
                </div>
            </div>

            <div class="header-actions">
                <!-- Local vs Global Segmented Control -->
                <form id="viewTabForm" method="POST" style="margin: 0;">
                    <input type="hidden" name="view" id="viewInput" value="<?php echo $view; ?>">
                    <div class="view-segmented-control">
                        <button type="button" onclick="setView('local')" class="view-segmented-btn <?php echo $view === 'local' ? 'active' : ''; ?>">
                            <i class="fas fa-building-user"></i> Local Dept
                        </button>
                        <button type="button" onclick="setView('global')" class="view-segmented-btn <?php echo $view === 'global' ? 'active' : ''; ?>">
                            <i class="fas fa-globe"></i> Global Pool
                        </button>
                    </div>
                </form>
                <script>
                    function setView(val) {
                        document.getElementById('viewInput').value = val;
                        document.getElementById('viewTabForm').submit();
                    }
                </script>

                <!-- Rules Drawer Button -->
                <a href="leaderboard.php?refresh_cache=1" class="btn-rules" style="text-decoration:none;" title="Force refresh calculation cache"><i class="fas fa-arrows-rotate" style="color: #059669;"></i> Refresh</a>
                <button type="button" class="btn-rules" id="rulesBtn" onclick="toggleInfo()">
                    <i class="fas fa-brain" style="color: #2563eb;"></i> Scoring Rules
                </button>

                <!-- Push to Placement Pool Button -->
                <button type="button" onclick="pushSelectedToPool()" class="btn-push-main">
                    <i class="fas fa-paper-plane"></i> Push to Pool
                </button>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-blue">
                <div class="kpi-top">
                    <span class="kpi-label">Ranked Candidates</span>
                    <div class="kpi-icon-pill"><i class="fas fa-users"></i></div>
                </div>
                <div class="kpi-val"><?php echo number_format($total_count); ?></div>
                <div class="kpi-subtext">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    <span><?php echo $view === 'local' ? 'Local department cohort' : 'Global institutional pool'; ?></span>
                </div>
            </div>

            <div class="kpi-card kpi-gold">
                <div class="kpi-top">
                    <span class="kpi-label">Top Ranked Leader</span>
                    <div class="kpi-icon-pill"><i class="fas fa-crown"></i></div>
                </div>
                <div class="kpi-val" style="font-size: 22px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo $top_candidate ? htmlspecialchars($top_candidate['name']) : 'N/A'; ?>
                </div>
                <div class="kpi-subtext">
                    <i class="fas fa-star" style="color: #f59e0b;"></i>
                    <span>Score: <strong><?php echo $top_candidate ? $top_candidate['total'] . ' pts' : '0'; ?></strong> (<?php echo $top_candidate ? htmlspecialchars($top_candidate['usn']) : '-'; ?>)</span>
                </div>
            </div>

            <div class="kpi-card kpi-emerald">
                <div class="kpi-top">
                    <span class="kpi-label">Cohort Avg Score</span>
                    <div class="kpi-icon-pill"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="kpi-val"><?php echo $avg_score; ?> <span style="font-size: 14px; color: #94a3b8; font-weight: 600;">/ 100</span></div>
                <div class="kpi-subtext">
                    <i class="fas fa-balance-scale" style="color: #059669;"></i>
                    <span>Weighted Composite Benchmark</span>
                </div>
            </div>

            <div class="kpi-card kpi-purple">
                <div class="kpi-top">
                    <span class="kpi-label">AI Pillar Averages</span>
                    <div class="kpi-icon-pill"><i class="fas fa-microchip"></i></div>
                </div>
                <div class="kpi-val" style="font-size: 18px; display: flex; gap: 8px; align-items: baseline;">
                    <span style="color: #3b82f6; font-size: 17px;">T: <?php echo round($avg_tech); ?>%</span>
                    <span style="color: #10b981; font-size: 17px;">A: <?php echo round($avg_apt); ?>%</span>
                    <span style="color: #8b5cf6; font-size: 17px;">H: <?php echo round($avg_hr); ?>%</span>
                </div>
                <div class="kpi-subtext">
                    <i class="fas fa-robot" style="color: #7c3aed;"></i>
                    <span>Technical • Aptitude • HR Pillars</span>
                </div>
            </div>
        </div>

        <!-- Top 3 Podium Showcase (Top 3 on page 1 or initial load) -->
        <?php if ($total_count >= 3 && ($page === 1 || empty($page))): ?>
        <div class="podium-container">
            <?php 
            $topThree = array_slice($leaderboard, 0, 3);
            $medals = [
                1 => ['cls' => 'medal-1', 'icon' => 'fa-crown', 'txt' => 'Rank #1 Gold Leader', 'card' => 'rank-1'],
                2 => ['cls' => 'medal-2', 'icon' => 'fa-medal', 'txt' => 'Rank #2 Silver', 'card' => 'rank-2'],
                3 => ['cls' => 'medal-3', 'icon' => 'fa-award', 'txt' => 'Rank #3 Bronze', 'card' => 'rank-3']
            ];
            foreach ($topThree as $idx => $cand): 
                $rankNum = $idx + 1;
                $m = $medals[$rankNum];
            ?>
                <div class="podium-card <?php echo $m['card']; ?>" 
                     onclick='showScoreBreakdown(<?php echo htmlspecialchars(json_encode([
                         "name" => $cand["name"],
                         "discipline" => $cand["discipline"],
                         "sem" => $cand["sem"],
                         "apt" => round($cand["aptitude"], 1),
                         "tech" => round($cand["technical"], 1),
                         "hr" => round($cand["hr"], 1),
                         "ai_avg" => round($cand["ai_avg"], 1),
                         "ai_pts" => round($cand["ai_avg"] * 0.7, 1),
                         "port_raw" => $cand["portfolio"],
                         "port_skills" => $cand["skills_count"] ?? count($cand["skills"]),
                         "port_projects" => $cand["projects_count"] ?? 0,
                         "port_pts" => round($cand["portfolio"] * 0.3, 1),
                         "base_total" => $cand["base_total"] ?? round(($cand["ai_avg"] * 0.7) + ($cand["portfolio"] * 0.3), 1),
                         "attempts" => $cand["total_attempts"] ?? $cand["ai_count"],
                         "activity_bonus" => $cand["activity_bonus"] ?? 0,
                         "inactivity_decay" => $cand["inactivity_decay_pct"] ?? 0,
                         "total" => $cand["total"]
                     ]), ENT_QUOTES, "UTF-8"); ?>)'>
                    
                    <div class="podium-badge-top">
                        <span class="podium-medal-badge <?php echo $m['cls']; ?>">
                            <i class="fas <?php echo $m['icon']; ?>"></i> <?php echo $m['txt']; ?>
                        </span>
                        <span class="badge-inst badge-inst-<?php echo strtolower($cand['institution']); ?>">
                            <?php echo htmlspecialchars($cand['institution']); ?>
                        </span>
                    </div>

                    <div class="podium-user-block">
                        <?php if (!empty($cand['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($cand['photo']); ?>" class="podium-avatar" alt="<?php echo htmlspecialchars($cand['name']); ?>" onerror="this.outerHTML='<div class=\'podium-avatar-placeholder\'><?php echo strtoupper(substr($cand['name'], 0, 1)); ?></div>'">
                        <?php else: ?>
                            <div class="podium-avatar-placeholder"><?php echo strtoupper(substr($cand['name'], 0, 1)); ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="podium-name" title="<?php echo htmlspecialchars($cand['name']); ?>"><?php echo htmlspecialchars($cand['name']); ?></div>
                            <div class="podium-usn"><?php echo htmlspecialchars($cand['usn']); ?> • <?php echo htmlspecialchars($cand['discipline']); ?></div>
                        </div>
                    </div>

                    <div class="podium-pillars-row">
                        <div class="podium-pillar-item">
                            <div class="podium-pillar-lbl">Tech</div>
                            <div class="podium-pillar-val" style="color: #2563eb;"><?php echo round($cand['technical']); ?>%</div>
                        </div>
                        <div class="podium-pillar-item">
                            <div class="podium-pillar-lbl">Apt</div>
                            <div class="podium-pillar-val" style="color: #059669;"><?php echo round($cand['aptitude']); ?>%</div>
                        </div>
                        <div class="podium-pillar-item">
                            <div class="podium-pillar-lbl">HR</div>
                            <div class="podium-pillar-val" style="color: #7c3aed;"><?php echo round($cand['hr']); ?>%</div>
                        </div>
                    </div>

                    <div class="podium-score-row">
                        <span class="podium-total-label"><i class="fas fa-calculator" style="margin-right: 4px;"></i> Total Score</span>
                        <span class="podium-total-val"><?php echo $cand['total']; ?> <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">pts</span></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Scoring Methodology Card (Collapsible) -->
        <div class="methodology-card" id="methodologyCard">
            <div class="methodology-header">
                <span><i class="fas fa-brain" style="color: #2563eb;"></i> Scoring Methodology & Dynamic Weighting Architecture</span>
                <button type="button" class="modal-close-btn" onclick="toggleInfo()">&times;</button>
            </div>
            <div class="methodology-body">
                <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5;">
                    The Placement Leaderboard evaluates student candidates using a verified multi-factor scoring formula: 
                    <strong>70% AI Assessments Pipeline + 30% Portfolio Rigor</strong>, boosted with <strong>Consistency Bonuses</strong> and adjusted for <strong>Inactivity Decay</strong>.
                </p>
                <div class="rule-grid">
                    <div class="rule-item">
                        <div class="rule-icon" style="color: #2563eb; background: #eff6ff;"><i class="fas fa-laptop-code"></i></div>
                        <div>
                            <strong style="color: #0f172a; display: block; margin-bottom: 2px;">70% AI Assessments Pipeline</strong>
                            Composite evaluation across 3 pillars: <strong>45% Technical</strong> + <strong>30% Aptitude</strong> + <strong>25% HR & Soft Skills</strong>, weighted across multi-attempt confidence curves.
                        </div>
                    </div>
                    <div class="rule-item">
                        <div class="rule-icon" style="color: #059669; background: #ecfdf5;"><i class="fas fa-award"></i></div>
                        <div>
                            <strong style="color: #0f172a; display: block; margin-bottom: 2px;">30% Portfolio Rigor</strong>
                            Practical proof of capability: Verified Skills (<strong>+2 pts</strong> each, up to 50) + Verified Projects (<strong>+5 pts</strong> each, up to 50). Max raw score: 100.
                        </div>
                    </div>
                    <div class="rule-item">
                        <div class="rule-icon" style="color: #0284c7; background: #e0f2fe;"><i class="fas fa-bolt"></i></div>
                        <div>
                            <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Practice & Consistency Bonus</strong>
                            Encourages continuous mock & task attempts up to <strong>+5.0 bonus points</strong> via non-linear growth: <code>5.0 × (1 - e^(-attempts / 8))</code>.
                        </div>
                    </div>
                    <div class="rule-item warning">
                        <div class="rule-icon" style="color: #ef4444; background: #fee2e2;"><i class="fas fa-clock"></i></div>
                        <div>
                            <strong style="color: #991b1b; display: block; margin-bottom: 2px;">Inactivity Decay</strong>
                            Students maintain full readiness with a <strong>7-day grace period (0% decay)</strong>. After 7 days of inactivity, a <strong>10%/week</strong> decay applies (max 50%).
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="filter-panel">
            <form class="filter-grid" method="POST" id="filterForm">
                <div class="filter-item">
                    <label><i class="fas fa-university"></i> Institution</label>
                    <select name="inst" onchange="this.form.submit()">
                        <option value="all">All Institutions</option>
                        <option value="gmu" <?php echo $inst_filter === 'gmu' ? 'selected' : ''; ?>>GMU</option>
                        <option value="gmit" <?php echo $inst_filter === 'gmit' ? 'selected' : ''; ?>>GMIT</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-graduation-cap"></i> Semester</label>
                    <select name="sem" onchange="this.form.submit()">
                        <option value="0">All Semesters</option>
                        <?php 
                        $sems = getCoordinatorSemesterFilters($myDepartment);
                        foreach($sems as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $sem_filter == $s ? 'selected' : ''; ?>>Semester <?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (count($available_branches) > 1): ?>
                <div class="filter-item">
                    <label><i class="fas fa-code-branch"></i> Specialization</label>
                    <select name="branch" onchange="this.form.submit()">
                        <option value="">All Branches</option>
                        <?php foreach ($available_branches as $ab): ?>
                            <option value="<?php echo htmlspecialchars($ab); ?>" <?php echo ($persistedFilters['branch'] ?? '') === $ab ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ab); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filter-item">
                    <label><i class="fas fa-star-half-alt"></i> Min SGPA</label>
                    <select name="min_sgpa_all" onchange="this.form.submit()">
                        <option value="">Any SGPA</option>
                        <?php foreach([6, 7, 8, 9] as $v): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_sgpa_all'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>.0+ SGPA</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-laptop-code"></i> Min Tech %</label>
                    <select name="min_tech" onchange="this.form.submit()">
                        <option value="">Any Tech</option>
                        <?php foreach([50, 60, 70, 80, 90] as $v): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_tech'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-brain"></i> Min Apt %</label>
                    <select name="min_apt" onchange="this.form.submit()">
                        <option value="">Any Aptitude</option>
                        <?php foreach([50, 60, 70, 80, 90] as $v): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_apt'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-comments"></i> Min HR %</label>
                    <select name="min_hr" onchange="this.form.submit()">
                        <option value="">Any HR</option>
                        <?php foreach([50, 60, 70, 80, 90] as $v): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($persistedFilters['min_hr'] ?? '') == $v ? 'selected' : ''; ?>><?php echo $v; ?>%+</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-calculator"></i> Min Total Score</label>
                    <input type="number" name="min_total" value="<?php echo htmlspecialchars($persistedFilters['min_total'] ?? ''); ?>" placeholder="e.g. 70" onchange="this.form.submit()">
                </div>

                <div class="filter-item" style="grid-column: span 2;">
                    <label><i class="fas fa-tags"></i> Required Skills</label>
                    <div class="multi-select-container">
                        <div class="selected-tags" id="selectedTags">
                            <?php foreach($selected_skills as $sk): if(empty($sk)) continue; ?>
                                <div class="tag-badge">
                                    <span><?php echo htmlspecialchars($sk); ?></span>
                                    <i class="fas fa-times" onclick="removeTag('<?php echo addslashes($sk); ?>', this)"></i>
                                    <input type="hidden" name="skills[]" value="<?php echo htmlspecialchars($sk); ?>">
                                </div>
                            <?php endforeach; ?>
                            <input type="text" id="skillSearch" placeholder="Search skills to filter..." autocomplete="off">
                        </div>
                        <div class="skill-dropdown" id="skillDropdown">
                            <?php foreach($all_skills as $sk): ?>
                                <div class="skill-option" onclick="addTag('<?php echo addslashes($sk); ?>')"><?php echo htmlspecialchars($sk); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-calendar-alt"></i> Academic Year</label>
                    <div style="font-weight: 700; color: var(--primary); padding: 9px 0; font-size: 13px;">
                        <?php echo htmlspecialchars($defaultAcademicYear); ?>
                    </div>
                    <input type="hidden" id="defaultAcademicYear" value="<?php echo htmlspecialchars($defaultAcademicYear); ?>">
                </div>

                <div class="filter-actions">
                    <div class="active-chips-area">
                        <?php if ($activeFiltersCount > 0): ?>
                            <span class="chip-count"><i class="fas fa-filter"></i> <?php echo $activeFiltersCount; ?> Active Filter<?php echo $activeFiltersCount > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-muted); margin-left: 8px;">
                            <input type="checkbox" name="show_all" id="showAllCheck" value="1" <?php echo ($showAll ?? false) ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <span>Show All Rows (No Pagination)</span>
                        </label>
                    </div>

                    <div class="filter-btn-group">
                        <button type="submit" class="btn-filter btn-filter-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <button type="submit" name="reset_filters" value="1" class="btn-filter">
                            <i class="fas fa-rotate-left"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modern Responsive Leaderboard Data Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="leaderboard-tbl">
                    <thead>
                        <tr>
                            <th width="36" style="text-align: center;">
                                <input type="checkbox" id="selectAllStudents" onclick="toggleAllCheckboxes(this)" style="cursor: pointer;">
                            </th>
                            <th width="60" style="text-align: center;">Rank</th>
                            <th>Candidate</th>
                            <th width="110">Specialization</th>
                            <th width="65" style="text-align: center;">Sem</th>
                            <th width="65" style="text-align: center;">Inst</th>
                            <?php for($i=1; $i<=8; $i++): ?>
                                <th width="38" style="text-align: center;" title="Semester <?php echo $i; ?> SGPA">S<?php echo $i; ?></th>
                            <?php endfor; ?>
                            <th width="75">Technical</th>
                            <th width="75">Aptitude</th>
                            <th width="75">HR & Soft</th>
                            <th width="200" style="max-width: 200px;">Verified Skills</th>
                            <th width="90" style="text-align: right; white-space: nowrap;">Total Score</th>
                        </tr>
                    </thead>
                    <tbody>
<?php echo renderLeaderboardRows($display_leaderboard); ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
                        <!-- Pagination Bar -->
            <form method="POST" id="paginationForm" style="display:none;"><input type="hidden" name="page" id="pageNum"></form>
            <div id="paginationWrapper">
                <?php if (!($showAll ?? false)) echo renderLeaderboardPagination($page, $total_pages, $offset, $limit, $total_entries); ?>
            </div>
            <script>
                async function goToPage(n) {
                    const tbody = document.querySelector('.leaderboard-tbl tbody');
                    const pagWrap = document.getElementById('paginationWrapper');
                    if (!tbody) return;

                    // Smooth subtle loading state
                    tbody.style.transition = 'opacity 0.15s ease';
                    tbody.style.opacity = '0.35';
                    tbody.style.pointerEvents = 'none';

                    try {
                        const res = await fetch('leaderboard.php?ajax_page=1&page=' + encodeURIComponent(n), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        const data = await res.json();
                        
                        if (data.success) {
                            tbody.innerHTML = data.rows_html;
                            if (pagWrap && data.pagination_html !== undefined) {
                                pagWrap.innerHTML = data.pagination_html;
                            }
                            const selectAll = document.getElementById('selectAllStudents');
                            if (selectAll) selectAll.checked = false;
                            if (typeof updateBulkDock === 'function') updateBulkDock();

                            // Smooth scroll table into view
                            const tblCard = document.querySelector('.table-card');
                            if (tblCard) {
                                tblCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        } else {
                            throw new Error('Invalid response');
                        }
                    } catch (err) {
                        console.warn('AJAX pagination fallback:', err);
                        document.getElementById('pageNum').value = n;
                        document.getElementById('paginationForm').submit();
                    } finally {
                        tbody.style.opacity = '1';
                        tbody.style.pointerEvents = 'auto';
                    }
                }
            </script>
        </div>
    </div>

    <!-- Floating Bulk Action Dock -->
    <div class="bulk-dock" id="bulkDock">
        <span class="bulk-dock-badge" id="selectedCountBadge">0 selected</span>
        <button type="button" class="bulk-dock-btn" onclick="pushSelectedToPool()">
            <i class="fas fa-paper-plane"></i> Push Selected to Pool
        </button>
        <button type="button" onclick="deselectAll()" style="background: none; border: none; color: #94a3b8; font-size: 12px; cursor: pointer; text-decoration: underline;">
            Deselect
        </button>
    </div>

    <!-- Push to Pool Modal -->
    <div id="pushModal" class="modal-overlay">
        <div class="modal-window">
            <div class="modal-header-bar">
                <h3><i class="fas fa-layer-group" style="color: #059669;"></i> Finalize Placement Pool</h3>
                <button type="button" class="modal-close-btn" onclick="closePushModal()">&times;</button>
            </div>
            <div class="modal-body-area">
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                    Export and finalize the selected candidate pool for active placement drives.
                </p>
                <div class="filter-item" style="margin-bottom: 14px;">
                    <label>Academic Year</label>
                    <input type="text" id="modalAcademicYear" placeholder="e.g. 2025-26" style="text-transform: uppercase;">
                </div>
                <div class="filter-item">
                    <label>Company / Drive Name</label>
                    <input type="text" id="modalCompanyName" placeholder="e.g. TATA TCS DIGITAL DRIVE 2026" style="text-transform: uppercase;">
                </div>
            </div>
            <div class="modal-footer-bar">
                <button type="button" class="btn-filter" onclick="closePushModal()">Cancel</button>
                <button type="button" class="btn-filter btn-filter-primary" style="background: #059669; border-color: #059669;" onclick="confirmPushToPool()">
                    <i class="fas fa-check"></i> Confirm & Push
                </button>
            </div>
        </div>
    </div>

    <!-- SGPA Academic History Modal -->
    <div id="sgpaModal" class="modal-overlay">
        <div class="modal-window" style="max-width: 480px;">
            <div class="modal-header-bar">
                <h3 id="modalStudentName"><i class="fas fa-graduation-cap" style="color: #2563eb;"></i> Academic History</h3>
                <button type="button" class="modal-close-btn" onclick="closeSgpaModal()">&times;</button>
            </div>
            <div class="modal-body-area">
                <div id="sgpaLoading" style="text-align: center; padding: 30px; display: none;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; color: var(--accent);"></i>
                    <p style="margin-top: 10px; color: var(--text-muted); font-size: 13px;">Loading academic history...</p>
                </div>
                <div id="sgpaContent">
                    <table class="sgpa-table">
                        <thead>
                            <tr>
                                <th>Semester</th>
                                <th style="text-align: center;">SGPA</th>
                                <th>Academic Year</th>
                            </tr>
                        </thead>
                        <tbody id="sgpaTableBody">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Score Breakdown Modal -->
    <div id="breakdownModal" class="modal-overlay">
        <div class="modal-window" style="max-width: 520px;">
            <div class="modal-header-bar">
                <div>
                    <h3 id="breakdownName">Score Calculation Breakdown</h3>
                    <p id="breakdownMeta" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"></p>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeBreakdownModal()">&times;</button>
            </div>
            <div class="modal-body-area">
                <!-- 1. AI Assessment Pipeline -->
                <div class="calc-breakdown-card">
                    <div class="calc-card-title">
                        <span><i class="fas fa-robot" style="color: #3b82f6;"></i> 1. AI Assessment Pipeline (70% Weight)</span>
                        <span id="aiPillarsAvg" style="color: #3b82f6; font-weight: 800;"></span>
                    </div>
                    <div class="calc-row">
                        <span>Technical Assessment (45% weight)</span>
                        <span id="bdTech"></span>
                    </div>
                    <div class="calc-row">
                        <span>Aptitude Assessment (30% weight)</span>
                        <span id="bdApt"></span>
                    </div>
                    <div class="calc-row">
                        <span>HR & Soft Skills (25% weight)</span>
                        <span id="bdHr"></span>
                    </div>
                    <div class="calc-subtotal-row">
                        <span id="aiSubtotalLabel">Weighted AI Base (Avg × 70%)</span>
                        <span id="aiBreakdown" style="color: #2563eb;"></span>
                    </div>
                </div>

                <!-- 2. Portfolio Rigor -->
                <div class="calc-breakdown-card">
                    <div class="calc-card-title">
                        <span><i class="fas fa-briefcase" style="color: #10b981;"></i> 2. Portfolio Rigor (30% Weight)</span>
                        <span id="portRawTotal" style="color: #10b981; font-weight: 800;"></span>
                    </div>
                    <div class="calc-row">
                        <span>Verified Skills (+2 pts each, max 50)</span>
                        <span id="bdSkills"></span>
                    </div>
                    <div class="calc-row">
                        <span>Verified Projects (+5 pts each, max 50)</span>
                        <span id="bdProjects"></span>
                    </div>
                    <div class="calc-subtotal-row">
                        <span id="portSubtotalLabel">Weighted Portfolio (Score × 30%)</span>
                        <span id="portBreakdown" style="color: #059669;"></span>
                    </div>
                </div>

                <!-- 3. Modifiers & Bonus -->
                <div class="calc-breakdown-card" style="margin-bottom: 0;">
                    <div class="calc-card-title">
                        <span><i class="fas fa-sliders-h" style="color: #f59e0b;"></i> 3. Dynamic Modifiers</span>
                    </div>
                    <div class="calc-row">
                        <span>Base Total (AI 70% + Portfolio 30%)</span>
                        <strong id="bdBaseTotal"></strong>
                    </div>
                    <div class="calc-row">
                        <span>Practice & Consistency Bonus <i class="fas fa-bolt" style="color: #0284c7; font-size: 11px;"></i></span>
                        <span id="bdActivityBonus" style="color: #0284c7; font-weight: 700;"></span>
                    </div>
                    <div class="calc-row">
                        <span>Inactivity Decay (Past 7-day grace period)</span>
                        <span id="bdInactivityDecay"></span>
                    </div>
                </div>

                <!-- Final Score Box -->
                <div class="calc-final-banner">
                    <div>
                        <div class="lbl">Final Weighted Total</div>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">Formula: (Base + Bonus) × (1 - Decay)</div>
                    </div>
                    <div class="val" id="totalBreakdown"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleInfo() {
            const card = document.getElementById('methodologyCard');
            const btn = document.getElementById('rulesBtn');
            card.classList.toggle('active');
            if (btn) btn.classList.toggle('active');
        }

        async function showSgpaDetails(usn, name) {
            const modal = document.getElementById('sgpaModal');
            const tbody = document.getElementById('sgpaTableBody');
            const nameDisplay = document.getElementById('modalStudentName');
            
            nameDisplay.innerHTML = `<i class="fas fa-graduation-cap" style="color: #2563eb;"></i> Academic History: ${name}`;
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
                                <td><strong>Semester ${sem.semester}</strong></td>
                                <td style="text-align: center;"><span class="sgpa-score-chip">${sem.sgpa}</span></td>
                                <td style="color: #64748b; font-size: 12px;">${sem.academic_year || '-'}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="padding: 24px; text-align:center; color: #94a3b8;">No academic history found.</td></tr>';
                }
            } catch (err) {
                document.getElementById('sgpaLoading').style.display = 'none';
                document.getElementById('sgpaContent').style.display = 'block';
                tbody.innerHTML = '<tr><td colspan="3" style="padding: 24px; text-align:center; color: #ef4444;">Error loading data.</td></tr>';
            }
        }

        function closeSgpaModal() {
            document.getElementById('sgpaModal').style.display = 'none';
        }

        function showScoreBreakdown(data) {
            document.getElementById('breakdownName').innerText = "Score Breakdown: " + data.name;
            document.getElementById('breakdownMeta').innerText = (data.discipline || '') + (data.sem ? ' • Semester ' + data.sem : '');
            
            // AI Assessments
            const techWeighted = (data.tech * 0.45).toFixed(1);
            const aptWeighted = (data.apt * 0.30).toFixed(1);
            const hrWeighted = (data.hr * 0.25).toFixed(1);
            
            document.getElementById('bdTech').innerHTML = `<strong>${data.tech}%</strong> <span style="color:#94a3b8; font-size:11px;">(${techWeighted} pts)</span>`;
            document.getElementById('bdApt').innerHTML = `<strong>${data.apt}%</strong> <span style="color:#94a3b8; font-size:11px;">(${aptWeighted} pts)</span>`;
            document.getElementById('bdHr').innerHTML = `<strong>${data.hr}%</strong> <span style="color:#94a3b8; font-size:11px;">(${hrWeighted} pts)</span>`;
            document.getElementById('aiPillarsAvg').innerText = `${data.ai_avg} / 100`;
            document.getElementById('aiSubtotalLabel').innerText = `Weighted AI Base (${data.ai_avg} × 70%)`;
            document.getElementById('aiBreakdown').innerText = `+${data.ai_pts} pts`;
            
            // Portfolio
            const skillsPts = Math.min(50, (data.port_skills || 0) * 2);
            const projPts = Math.min(50, (data.port_projects || 0) * 5);
            document.getElementById('bdSkills').innerHTML = `<strong>${data.port_skills || 0}</strong> verified <span style="color:#94a3b8; font-size:11px;">(${skillsPts} pts)</span>`;
            document.getElementById('bdProjects').innerHTML = `<strong>${data.port_projects || 0}</strong> verified <span style="color:#94a3b8; font-size:11px;">(${projPts} pts)</span>`;
            document.getElementById('portRawTotal').innerText = `${data.port_raw} / 100`;
            document.getElementById('portSubtotalLabel').innerText = `Weighted Portfolio (${data.port_raw} × 30%)`;
            document.getElementById('portBreakdown').innerText = `+${data.port_pts} pts`;
            
            // Modifiers
            document.getElementById('bdBaseTotal').innerText = `${data.base_total} pts`;
            document.getElementById('bdActivityBonus').innerText = data.activity_bonus > 0 ? `+${data.activity_bonus} pts (${data.attempts} attempts)` : `+0.0 pts (${data.attempts} attempts)`;
            document.getElementById('bdInactivityDecay').innerHTML = data.inactivity_decay > 0 
                ? `<span style="color:#ef4444; font-weight:700;">-${data.inactivity_decay}%</span>` 
                : `<span style="color:#10b981; font-weight:700;">0% (Active)</span>`;
            
            // Total
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

        if (skillSearch) {
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
        }

        function addTag(skill) {
            const container = document.getElementById('selectedTags');
            const existing = container.querySelectorAll('input[type="hidden"]');
            for(let ex of existing) {
                if(ex.value === skill) {
                    skillSearch.value = '';
                    skillDropdown.style.display = 'none';
                    return;
                }
            }

            const tag = document.createElement('div');
            tag.className = 'tag-badge';
            tag.innerHTML = `
                <span>${skill}</span>
                <i class="fas fa-times" onclick="removeTag('${skill}', this)"></i>
                <input type="hidden" name="skills[]" value="${skill}">
            `;
            container.insertBefore(tag, skillSearch);
            skillSearch.value = '';
            skillDropdown.style.display = 'none';
            document.getElementById('filterForm').submit();
        }

        function removeTag(skill, el) {
            if(el) {
                el.parentElement.remove();
            } else {
                const tags = document.querySelectorAll('.tag-badge');
                tags.forEach(t => {
                    if(t.innerText.trim().startsWith(skill)) t.remove();
                });
            }
            document.getElementById('filterForm').submit();
        }

        // Checkbox & Bulk Actions
        function toggleAllCheckboxes(master) {
            const checkboxes = document.querySelectorAll('.student-select');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateBulkDock();
        }

        function updateBulkDock() {
            const selected = document.querySelectorAll('.student-select:checked');
            const dock = document.getElementById('bulkDock');
            const badge = document.getElementById('selectedCountBadge');
            
            if (selected.length > 0) {
                badge.innerText = `${selected.length} selected`;
                dock.classList.add('active');
            } else {
                dock.classList.remove('active');
            }
        }

        function deselectAll() {
            document.querySelectorAll('.student-select').forEach(cb => cb.checked = false);
            const master = document.getElementById('selectAllStudents');
            if (master) master.checked = false;
            updateBulkDock();
        }

        let pendingUsns = [];

        function pushSelectedToPool() {
            const selectedBoxes = document.querySelectorAll('.student-select:checked');
            pendingUsns = Array.from(selectedBoxes).map(cb => cb.value);

            if (pendingUsns.length === 0) {
                alert("Please select at least one candidate from the list.");
                return;
            }

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

            if (!confirm(`Push ${pendingUsns.length} candidate(s) to the placement pool for "${companyName}"?`)) {
                return;
            }

            localStorage.setItem('lastAcademicYear', academicYear);
            localStorage.setItem('lastCompanyName', companyName);

            closePushModal();
            const pushBtn = document.querySelector('.btn-push-main');
            const originalHtml = pushBtn ? pushBtn.innerHTML : '';
            if (pushBtn) {
                pushBtn.disabled = true;
                pushBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';
            }

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
                
                if (pushBtn) {
                    pushBtn.disabled = false;
                    pushBtn.innerHTML = originalHtml;
                }

                if (data.success) {
                    alert(`Successfully pushed ${data.count} candidate(s) to the placement pool.`);
                    deselectAll();
                } else {
                    alert("Error: " + (data.message || "Failed to push candidates."));
                }
            } catch (err) {
                if (pushBtn) {
                    pushBtn.disabled = false;
                    pushBtn.innerHTML = originalHtml;
                }
                alert("Connection error occurred.");
            }
        }

        // Close modal on backdrop click
        window.onclick = function(event) {
            const sgpaModal = document.getElementById('sgpaModal');
            const breakdownModal = document.getElementById('breakdownModal');
            const pushModal = document.getElementById('pushModal');
            if (event.target == sgpaModal) closeSgpaModal();
            if (event.target == breakdownModal) closeBreakdownModal();
            if (event.target == pushModal) closePushModal();
        }
    </script>
</body>
</html>