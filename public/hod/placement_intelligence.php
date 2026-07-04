<?php
/**
 * HOD Department Placement Intelligence Dashboard (Department Command Center)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../src/Services/DepartmentIntelligenceService.php';

$department = getDepartment() ?: 'CSE';
$disciplineFilters = getCoordinatorDisciplineFilters($department);
$deptService = new \App\Services\DepartmentIntelligenceService();

// Handle AJAX cache refresh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'refresh_cache') {
    header('Content-Type: application/json');
    try {
        $res = $deptService->generateCache($department, $disciplineFilters);
        echo json_encode($res);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch cached data
$cachedData = $deptService->getCachedData($department);

// If no cache, auto-generate it once (so HOD doesn't see a blank page)
if (!$cachedData) {
    $res = $deptService->generateCache($department, $disciplineFilters);
    if ($res['success']) {
        $cachedData = $deptService->getCachedData($department);
    }
}

$overview = $cachedData['data']['overview'] ?? [];
$semBreakdown = $cachedData['data']['semester_breakdown'] ?? [];
$riskSummary = $cachedData['data']['risk_summary'] ?? [];
$skillAnalytics = $cachedData['data']['skill_analytics'] ?? [];
$careerDistribution = $cachedData['data']['career_distribution'] ?? [];
$aiInsights = $cachedData['data']['ai_insights'] ?? [];
$students = $cachedData['students'] ?? [];
$totalStudents = $cachedData['total_students'] ?? 0;
$generatedAt = $cachedData['generated_at'] ?? 'Never';

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Placement Intelligence - <?php echo htmlspecialchars($department); ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --dark-maroon: #5b1f1f;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            
            --ready: #10b981;
            --improving: #f59e0b;
            --at-risk: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            overflow-x: hidden;
            padding-top: 80px;
        }

        .main-content {
            padding: 40px 50px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Dashboard Header */
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 25px 35px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .header-title h2 {
            font-size: 26px;
            color: var(--primary-maroon);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-refresh {
            background: var(--primary-maroon);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
            transition: var(--transition);
        }

        .btn-refresh:hover {
            background: var(--dark-maroon);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(128, 0, 0, 0.3);
        }

        .btn-refresh.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        .cache-info {
            font-size: 12px;
            color: var(--text-muted);
            text-align: right;
        }

        /* Overview Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .stat-card.ready-card { border-left: 5px solid var(--ready); }
        .stat-card.improving-card { border-left: 5px solid var(--improving); }
        .stat-card.risk-card { border-left: 5px solid var(--at-risk); }
        .stat-card.avg-card { border-left: 5px solid var(--primary-gold); }

        .stat-details h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .stat-details .stat-val {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .stat-details .stat-sub {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .stat-icon {
            font-size: 28px;
            opacity: 0.15;
            color: var(--text-dark);
        }

        .ready-card .stat-icon { color: var(--ready); opacity: 0.2; }
        .improving-card .stat-icon { color: var(--improving); opacity: 0.2; }
        .risk-card .stat-icon { color: var(--at-risk); opacity: 0.2; }
        .avg-card .stat-icon { color: var(--primary-gold); opacity: 0.2; }

        /* Secondary metrics row */
        .secondary-metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .sec-metric-card {
            background: white;
            padding: 18px 22px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sec-metric-info span {
            display: block;
        }

        .sec-metric-info .lbl {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .sec-metric-info .val {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-dark);
            margin-top: 4px;
        }

        .sec-metric-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* Row layouts */
        .row-grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            align-items: start;
        }

        .row-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            align-items: start;
        }

        .dashboard-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            padding: 30px;
            height: 100%;
        }

        .dashboard-card h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary-maroon);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .dashboard-card h3 i {
            color: var(--primary-gold);
        }

        /* Distribution bar */
        .dist-bar-container {
            display: flex;
            height: 24px;
            border-radius: 12px;
            overflow: hidden;
            margin: 20px 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }

        .dist-fill {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: 800;
            transition: var(--transition);
        }

        .dist-fill.ready { background: var(--ready); }
        .dist-fill.improving { background: var(--improving); }
        .dist-fill.at-risk { background: var(--at-risk); }

        .dist-legend {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        /* AI Insights list */
        .insight-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .insight-item {
            display: flex;
            gap: 12px;
            padding: 15px;
            border-radius: 12px;
            background: #fafafb;
            border-left: 4px solid var(--primary-gold);
            font-size: 14px;
            line-height: 1.5;
        }

        .insight-item.success { border-left-color: var(--ready); background: rgba(16, 185, 129, 0.02); }
        .insight-item.info { border-left-color: #0066cc; background: rgba(0, 102, 204, 0.02); }
        .insight-item.warning { border-left-color: var(--improving); background: rgba(245, 158, 11, 0.02); }
        .insight-item.danger { border-left-color: var(--at-risk); background: rgba(239, 68, 68, 0.02); }

        .insight-item i {
            margin-top: 3px;
            font-size: 16px;
        }

        .insight-item.success i { color: var(--ready); }
        .insight-item.info i { color: #0066cc; }
        .insight-item.warning i { color: var(--improving); }
        .insight-item.danger i { color: var(--at-risk); }

        /* Risk Analytics List */
        .risk-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .risk-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            border-radius: 12px;
            background: #fafafb;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .risk-row:hover {
            transform: translateX(4px);
            border-color: var(--primary-gold);
            background: #fffdf9;
        }

        .risk-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 14px;
        }

        .risk-count-badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            color: white;
        }

        /* Career Distribution list */
        .career-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .career-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-radius: 12px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .career-row:hover {
            border-color: var(--primary-gold);
            background: #fffdf9;
            transform: translateY(-1px);
        }

        .career-name {
            font-weight: 700;
            font-size: 14px;
        }

        .career-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .career-stats strong {
            color: var(--primary-maroon);
        }

        /* Skill Analytics list */
        .skill-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            max-height: 380px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .skill-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-radius: 10px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .skill-row:hover {
            border-color: var(--primary-gold);
            background: #fffdf9;
        }

        .skill-name {
            font-weight: 600;
            font-size: 13px;
        }

        .skill-pct {
            font-size: 11px;
            font-weight: 800;
            background: rgba(128, 0, 0, 0.05);
            color: var(--primary-maroon);
            padding: 2px 6px;
            border-radius: 5px;
        }

        /* Matrix / Quadrant Scatter Plot */
        .matrix-chart-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* Student Roster Table */
        .roster-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            padding: 30px;
            margin-bottom: 40px;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 8px 15px;
            width: 300px;
        }

        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            margin-left: 8px;
            width: 100%;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-select {
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: white;
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            outline: none;
            cursor: pointer;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #fafafb;
            padding: 14px 20px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-dark);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr.clickable-row {
            cursor: pointer;
            transition: var(--transition);
        }

        tr.clickable-row:hover {
            background: rgba(128, 0, 0, 0.02);
        }

        .score-cell {
            font-weight: 700;
        }

        .badge-readiness {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-readiness.ready { background: rgba(16, 185, 129, 0.1); color: var(--ready); }
        .badge-readiness.improving { background: rgba(245, 158, 11, 0.1); color: var(--improving); }
        .badge-readiness.at-risk { background: rgba(239, 68, 68, 0.1); color: var(--at-risk); }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: white;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 1200px;
            height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(30px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px 30px;
            background: var(--primary-maroon);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-gold);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 26px;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-modal:hover {
            color: var(--primary-gold);
            transform: scale(1.1);
        }

        .modal-body-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            background: var(--bg-light);
        }

        /* Modal Iframe Sandbox for Student Showcase */
        .report-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="header-title">
                <h2><i class="fas fa-shield-halved"></i> Department Placement Intelligence Center</h2>
                <p>Derived career insights, risk matrix, and dynamic interventions for <?php echo htmlspecialchars($department); ?> cohort</p>
            </div>
            <div class="header-actions">
                <div class="cache-info">
                    <strong>Last calculated:</strong><br>
                    <span id="cache-time"><?php echo htmlspecialchars($generatedAt); ?></span>
                </div>
                <button class="btn-refresh" id="btn-refresh" onclick="refreshCache()">
                    <i class="fas fa-sync-alt"></i> Refresh Analytics
                </button>
            </div>
        </div>

        <?php if (empty($overview)): ?>
            <div class="dashboard-card" style="text-align: center; padding: 50px;">
                <i class="fas fa-database" style="font-size: 48px; color: var(--text-muted); margin-bottom: 20px;"></i>
                <h4>No Placement Intelligence Data Available</h4>
                <p style="color: var(--text-muted); margin-top: 10px;">Please click the "Refresh Analytics" button above to run calculations for your department cohort.</p>
            </div>
        <?php else: ?>
            <!-- Overview Cards -->
            <div class="stats-grid">
                <div class="stat-card ready-card">
                    <div class="stat-details">
                        <h3>Placement Ready</h3>
                        <div class="stat-val"><?php echo $overview['placement_ready'] ?? 0; ?></div>
                        <div class="stat-sub"><?php echo $totalStudents > 0 ? round(($overview['placement_ready'] / $totalStudents) * 100) : 0; ?>% of department</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-card improving-card">
                    <div class="stat-details">
                        <h3>Improving</h3>
                        <div class="stat-val"><?php echo $overview['improving'] ?? 0; ?></div>
                        <div class="stat-sub"><?php echo $totalStudents > 0 ? round(($overview['improving'] / $totalStudents) * 100) : 0; ?>% of department</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                </div>
                <div class="stat-card risk-card">
                    <div class="stat-details">
                        <h3>High Risk</h3>
                        <div class="stat-val"><?php echo $overview['at_risk'] ?? 0; ?></div>
                        <div class="stat-sub"><?php echo $totalStudents > 0 ? round(($overview['at_risk'] / $totalStudents) * 100) : 0; ?>% need support</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-card avg-card">
                    <div class="stat-details">
                        <h3>Avg Readiness</h3>
                        <div class="stat-val"><?php echo $overview['avg_readiness'] ?? 0; ?>%</div>
                        <div class="stat-sub">Based on dynamic subscores</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>

            <!-- Secondary Metrics -->
            <div class="secondary-metrics-grid">
                <div class="sec-metric-card">
                    <div class="sec-metric-info">
                        <span class="lbl">Coding Readiness</span>
                        <span class="val"><?php echo $overview['avg_coding'] ?? 0; ?>%</span>
                    </div>
                    <div class="sec-metric-icon" style="background: rgba(128, 0, 0, 0.05); color: var(--primary-maroon);"><i class="fas fa-code"></i></div>
                </div>
                <div class="sec-metric-card">
                    <div class="sec-metric-info">
                        <span class="lbl">Resume Quality</span>
                        <span class="val"><?php echo $overview['avg_resume'] ?? 0; ?>/100</span>
                    </div>
                    <div class="sec-metric-icon" style="background: rgba(212, 175, 55, 0.08); color: var(--primary-gold);"><i class="fas fa-file-alt"></i></div>
                </div>
                <div class="sec-metric-card">
                    <div class="sec-metric-info">
                        <span class="lbl">Mock Interviews</span>
                        <span class="val"><?php echo $overview['avg_communication'] ?? 0; ?>%</span>
                    </div>
                    <div class="sec-metric-icon" style="background: #e3f2fd; color: #1976d2;"><i class="fas fa-comments"></i></div>
                </div>
                <div class="sec-metric-card">
                    <div class="sec-metric-info">
                        <span class="lbl">Projects & Git</span>
                        <span class="val"><?php echo $overview['avg_project'] ?? 0; ?>%</span>
                    </div>
                    <div class="sec-metric-icon" style="background: #e8f5e9; color: #2e7d32;"><i class="fas fa-tasks"></i></div>
                </div>
            </div>

            <!-- Row 1: Distribution & AI Insights -->
            <div class="row-grid-2">
                <div class="dashboard-card">
                    <h3><i class="fas fa-chart-pie"></i> Placement Readiness Distribution</h3>
                    <div class="dist-bar-container">
                        <?php 
                        $readyPct = $totalStudents > 0 ? ($overview['placement_ready'] / $totalStudents) * 100 : 0;
                        $improvingPct = $totalStudents > 0 ? ($overview['improving'] / $totalStudents) * 100 : 0;
                        $riskPct = $totalStudents > 0 ? ($overview['at_risk'] / $totalStudents) * 100 : 0;
                        ?>
                        <?php if ($readyPct > 0): ?>
                            <div class="dist-fill ready" style="width: <?php echo $readyPct; ?>%;"><?php echo round($readyPct); ?>%</div>
                        <?php endif; ?>
                        <?php if ($improvingPct > 0): ?>
                            <div class="dist-fill improving" style="width: <?php echo $improvingPct; ?>%;"><?php echo round($improvingPct); ?>%</div>
                        <?php endif; ?>
                        <?php if ($riskPct > 0): ?>
                            <div class="dist-fill at-risk" style="width: <?php echo $riskPct; ?>%;"><?php echo round($riskPct); ?>%</div>
                        <?php endif; ?>
                    </div>
                    <div class="dist-legend">
                        <div class="legend-item"><div class="legend-dot" style="background: var(--ready);"></div> Placement Ready (>= 70%)</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--improving);"></div> Improving (45% - 69%)</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--at-risk);"></div> High Risk (< 45%)</div>
                    </div>

                    <!-- Readiness Matrix / Scatter Plot Chart -->
                    <div style="margin-top: 30px;">
                        <h4 style="font-size: 14px; color: var(--text-muted); margin-bottom: 15px; text-transform: uppercase;">Placement Readiness Matrix</h4>
                        <div class="matrix-chart-wrapper">
                            <canvas id="matrixChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h3><i class="fas fa-brain"></i> AI Department Observations</h3>
                    <div class="insight-list">
                        <?php foreach ($aiInsights as $insight): ?>
                            <div class="insight-item <?php echo htmlspecialchars($insight['type'] ?? 'info'); ?>">
                                <i class="fas <?php echo htmlspecialchars($insight['icon'] ?? 'fa-info-circle'); ?>"></i>
                                <div><?php echo htmlspecialchars($insight['text'] ?? ''); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Row 2: Risk Analytics & Skill Coverage -->
            <div class="row-grid-2">
                <div class="dashboard-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Risk & Intervention Center</h3>
                    <div class="risk-list">
                        <?php foreach ($riskSummary as $key => $r): ?>
                            <div class="risk-row" onclick="filterRosterByRisk('<?php echo $key; ?>')">
                                <div class="risk-info">
                                    <i class="fas <?php echo htmlspecialchars($r['icon']); ?>" style="color: <?php echo $r['color']; ?>; width: 20px; text-align: center;"></i>
                                    <span><?php echo htmlspecialchars($r['label']); ?></span>
                                </div>
                                <span class="risk-count-badge" style="background: <?php echo $r['color']; ?>;"><?php echo $r['count']; ?> Students</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h3><i class="fas fa-laptop-code"></i> Department Skill Coverage</h3>
                    <div class="skill-list">
                        <?php foreach ($skillAnalytics as $s): ?>
                            <div class="skill-row" onclick="filterRosterBySkill('<?php echo htmlspecialchars($s['skill']); ?>')">
                                <span class="skill-name"><?php echo htmlspecialchars($s['skill']); ?></span>
                                <span class="skill-pct"><?php echo $s['percentage']; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Row 3: Career Distribution -->
            <div class="row-grid-3">
                <div class="dashboard-card" style="grid-column: span 3;">
                    <h3><i class="fas fa-route"></i> Career Paths & Fit Alignment</h3>
                    <div class="career-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                        <?php foreach ($careerDistribution as $c): ?>
                            <div class="career-row" onclick="filterRosterByCareer('<?php echo htmlspecialchars($c['role']); ?>')">
                                <div>
                                    <div class="career-name"><?php echo htmlspecialchars($c['role']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Top aligned role</div>
                                </div>
                                <div class="career-stats">
                                    <span>Aligned: <strong><?php echo $c['count']; ?></strong></span>
                                    <span>Avg Match: <strong><?php echo $c['avg_match']; ?>%</strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Semester comparison table -->
            <div class="dashboard-card" style="margin-bottom: 30px;">
                <h3><i class="fas fa-columns"></i> Semester & Batch Cohort Analysis</h3>
                <div class="table-responsive">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Semester</th>
                                <th>Cohort Size</th>
                                <th>Avg Placement Readiness</th>
                                <th>Avg Coding Score</th>
                                <th>Avg Mock Interview</th>
                                <th>Avg Project Quality</th>
                                <th>Avg Resume Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($semBreakdown as $sem => $data): ?>
                                <tr>
                                    <td style="font-weight: 700;">Semester <?php echo $sem; ?></td>
                                    <td><?php echo $data['count']; ?> Students</td>
                                    <td class="score-cell" style="color: var(--primary-maroon);"><?php echo $data['avg_readiness']; ?>%</td>
                                    <td><?php echo $data['avg_coding']; ?>%</td>
                                    <td><?php echo $data['avg_communication']; ?>%</td>
                                    <td><?php echo $data['avg_project']; ?>%</td>
                                    <td><?php echo $data['avg_resume']; ?>/100</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Student Roster Table -->
            <div class="roster-card" id="student-roster">
                <div class="table-controls">
                    <h3 style="margin-bottom: 0; color: var(--primary-maroon); font-weight: 800;"><i class="fas fa-users"></i> Department Student Roster</h3>
                    <div class="filter-group">
                        <div class="search-box">
                            <i class="fas fa-search" style="color: var(--text-muted);"></i>
                            <input type="text" id="rosterSearch" oninput="applyFilters()" placeholder="Search USN or Name...">
                        </div>
                        <select class="filter-select" id="filterReadiness" onchange="applyFilters()">
                            <option value="">All Readiness Levels</option>
                            <option value="ready">Placement Ready</option>
                            <option value="improving">Improving</option>
                            <option value="at_risk">High Risk</option>
                        </select>
                        <select class="filter-select" id="filterSemester" onchange="applyFilters()">
                            <option value="">All Semesters</option>
                            <option value="5">Semester 5</option>
                            <option value="6">Semester 6</option>
                            <option value="7">Semester 7</option>
                            <option value="8">Semester 8</option>
                        </select>
                        <button class="btn-refresh" style="background: #fafafb; color: var(--text-dark); border: 1px solid var(--border-color); padding: 8px 15px; box-shadow: none;" onclick="resetFilters()">
                            Reset Filters
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>Semester</th>
                                <th>Readiness Score</th>
                                <th>Coding Score</th>
                                <th>Resume Score</th>
                                <th>Mock Interview</th>
                                <th>Project Quality</th>
                                <th>Backlogs</th>
                            </tr>
                        </thead>
                        <tbody id="rosterTableBody">
                            <?php foreach ($students as $s): ?>
                                <tr 
                                    data-id="<?php echo htmlspecialchars($s['student_id']); ?>" 
                                    data-name="<?php echo htmlspecialchars(strtolower($s['name'])); ?>"
                                    data-usn="<?php echo htmlspecialchars(strtolower($s['student_id'])); ?>"
                                    data-risk="<?php echo htmlspecialchars($s['risk_level']); ?>"
                                    data-sem="<?php echo htmlspecialchars($s['semester']); ?>"
                                    data-careers="<?php echo htmlspecialchars(implode(',', array_column($s['top_careers'], 'role'))); ?>"
                                    data-skills="<?php echo htmlspecialchars(strtolower(implode(',', $s['skills']))); ?>">
                                    <td>
                                        <div style="font-weight: 700; color: #111;"><?php echo htmlspecialchars($s['name']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted); font-family: monospace; margin-top: 2px;"><?php echo htmlspecialchars($s['student_id']); ?></div>
                                    </td>
                                    <td>Sem <?php echo htmlspecialchars($s['semester']); ?></td>
                                    <td>
                                        <span class="badge-readiness <?php echo htmlspecialchars($s['risk_level']); ?>">
                                            <?php echo htmlspecialchars($s['readiness_score']); ?>%
                                        </span>
                                    </td>
                                    <td class="score-cell"><?php echo htmlspecialchars($s['coding_score']); ?>%</td>
                                    <td><?php echo htmlspecialchars($s['resume_score']); ?>/100</td>
                                    <td><?php echo htmlspecialchars($s['communication_score']); ?>%</td>
                                    <td><?php echo htmlspecialchars($s['project_score']); ?>%</td>
                                    <td style="<?php echo $s['backlogs'] > 0 ? 'color: var(--at-risk); font-weight: 700;' : 'color: var(--text-muted);'; ?>">
                                        <?php echo htmlspecialchars($s['backlogs']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Student PICE Report Modal -->
    <div class="modal" id="reportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-brain"></i> Student Placement Intelligence Showcase</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div style="flex: 1; overflow: hidden; position: relative;">
                <iframe id="reportIframe" class="report-frame" src=""></iframe>
            </div>
        </div>
    </div>

    <script>
        // Matrix chart
        <?php if (!empty($overview)): ?>
        const ctx = document.getElementById('matrixChart').getContext('2d');
        const matrixData = <?php echo json_encode(array_map(function($s) {
            // Apply deterministic jitter based on USN hash to separate overlapping dots
            $hashVal = crc32($s['student_id']);
            $jitterX = ($hashVal % 13 - 6) * 0.9; // jitter range [-5.4%, +5.4%]
            $jitterY = (floor($hashVal / 13) % 13 - 6) * 0.9;
            
            $displayX = max(4, min(96, $s['coding_score'] + $jitterX));
            $displayY = max(4, min(96, $s['communication_score'] + $jitterY));

            return [
                'x' => $displayX,
                'y' => $displayY,
                'real_x' => $s['coding_score'],
                'real_y' => $s['communication_score'],
                'name' => $s['name'],
                'usn' => $s['student_id'],
                'readiness' => $s['readiness_score']
            ];
        }, $students)); ?>;

        const quadrantPlugin = {
            id: 'quadrantPlugin',
            beforeDraw(chart) {
                const {ctx, chartArea: {top, right, bottom, left}, scales: {x, y}} = chart;
                ctx.save();
                
                const midX = x.getPixelForValue(50);
                const midY = y.getPixelForValue(50);
                
                // Draw quadrant separator lines
                ctx.strokeStyle = 'rgba(128, 0, 0, 0.15)';
                ctx.lineWidth = 2;
                ctx.setLineDash([5, 5]);
                
                ctx.beginPath();
                ctx.moveTo(midX, top);
                ctx.lineTo(midX, bottom);
                ctx.stroke();
                
                ctx.beginPath();
                ctx.moveTo(left, midY);
                ctx.lineTo(right, midY);
                ctx.stroke();
                ctx.setLineDash([]);
                
                // Draw Quadrant labels
                ctx.fillStyle = 'rgba(30, 41, 59, 0.4)';
                ctx.font = '700 10px "Outfit", sans-serif';
                
                // Top Right
                ctx.fillText('PLACEMENT READY', midX + 12, top + 18);
                // Bottom Right
                ctx.fillText('TECH ONLY SPECIALISTS', midX + 12, bottom - 12);
                // Top Left
                ctx.fillText('COMMUNICATION ONLY', left + 12, top + 18);
                // Bottom Left
                ctx.fillText('CRITICAL INTERVENTION', left + 12, bottom - 12);
                
                ctx.restore();
            }
        };

        new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Students',
                    data: matrixData,
                    backgroundColor: matrixData.map(d => {
                        if (d.readiness >= 70) return '#10b981'; // ready
                        if (d.readiness >= 45) return '#f59e0b'; // improving
                        return '#ef4444'; // at risk
                    }),
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'Coding Readiness Score (%)', font: { family: 'Outfit', weight: 'bold' } }
                    },
                    y: {
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'Mock HR / Comm Score (%)', font: { family: 'Outfit', weight: 'bold' } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const pt = context.raw;
                                return `${pt.name} (${pt.usn}) | Readiness: ${pt.readiness}% (Coding: ${pt.real_x}%, Comm: ${pt.real_y}%)`;
                            }
                        }
                    }
                }
            },
            plugins: [quadrantPlugin]
        });
        <?php endif; ?>

        // Refresh cache AJAX call
        function refreshCache() {
            const btn = document.getElementById('btn-refresh');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Recalculating...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'refresh_cache');

            fetch('placement_intelligence.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error recalculating dashboard data: ' + data.message);
                    btn.classList.remove('loading');
                    btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Analytics';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred during recalculation.');
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Analytics';
                btn.disabled = false;
            });
        }

        // Open Student Report Modal
        function openStudentReport(usn) {
            const modal = document.getElementById('reportModal');
            const iframe = document.getElementById('reportIframe');
            
            iframe.src = '../student/showcase.php?student_id=' + encodeURIComponent(usn) + '&iframe=1';
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('reportModal');
            const iframe = document.getElementById('reportIframe');
            iframe.src = '';
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Filters
        let activeRiskFilter = '';
        let activeSkillFilter = '';
        let activeCareerFilter = '';

        function applyFilters() {
            const search = document.getElementById('rosterSearch').value.toLowerCase();
            const readiness = document.getElementById('filterReadiness').value;
            const semester = document.getElementById('filterSemester').value;

            const rows = document.querySelectorAll('#rosterTableBody tr');

            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const usn = row.getAttribute('data-usn');
                const risk = row.getAttribute('data-risk');
                const sem = row.getAttribute('data-sem');
                const careers = row.getAttribute('data-careers');
                const skills = row.getAttribute('data-skills');

                let match = true;

                if (search && !name.includes(search) && !usn.includes(search)) {
                    match = false;
                }
                if (readiness && risk !== readiness) {
                    match = false;
                }
                if (semester && sem !== semester) {
                    match = false;
                }

                // Custom risk filters
                if (activeRiskFilter) {
                    const studentId = row.getAttribute('data-id');
                    const hasRisk = isStudentInRiskGroup(studentId, activeRiskFilter);
                    if (!hasRisk) match = false;
                }

                // Custom skill filter
                if (activeSkillFilter && !skills.includes(activeSkillFilter.toLowerCase())) {
                    match = false;
                }

                // Custom career filter
                if (activeCareerFilter && !careers.includes(activeCareerFilter)) {
                    match = false;
                }

                row.style.display = match ? '' : 'none';
            });
        }

        // Lookup function to check if student is in the current active risk group
        function isStudentInRiskGroup(usn, riskKey) {
            const riskData = <?php echo json_encode(array_map(function($r) {
                return array_column($r['students'], 'student_id');
            }, $riskSummary)); ?>;
            return riskData[riskKey] && riskData[riskKey].includes(usn);
        }

        function filterRosterByRisk(riskKey) {
            resetFilters(false);
            activeRiskFilter = riskKey;
            
            // Highlight list header
            document.getElementById('student-roster').scrollIntoView({ behavior: 'smooth' });
            applyFilters();
        }

        function filterRosterBySkill(skill) {
            resetFilters(false);
            activeSkillFilter = skill;
            document.getElementById('student-roster').scrollIntoView({ behavior: 'smooth' });
            applyFilters();
        }

        function filterRosterByCareer(career) {
            resetFilters(false);
            activeCareerFilter = career;
            document.getElementById('student-roster').scrollIntoView({ behavior: 'smooth' });
            applyFilters();
        }

        function resetFilters(clearInputs = true) {
            activeRiskFilter = '';
            activeSkillFilter = '';
            activeCareerFilter = '';
            
            if (clearInputs) {
                document.getElementById('rosterSearch').value = '';
                document.getElementById('filterReadiness').value = '';
                document.getElementById('filterSemester').value = '';
            }
            
            applyFilters();
        }
    </script>
</body>
</html>
