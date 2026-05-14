<?php
/**
 * Global Admin Dashboard
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Admin.php';
require_once __DIR__ . '/../../src/Models/LearningChapter.php';

// 1. Essential Auth (Fast)
requireRole(ROLE_ADMIN);

$fullName = getFullName();

// 2. Start Immediate Rendering (Skeleton)
if (!headers_sent()) {
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    ob_end_flush(); 
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/skeleton.css?v=<?php echo APP_VERSION; ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Instant Skeleton Loader -->
    <div id="skeletonScreen" class="skeleton-screen">
        <div class="skeleton-header shimmer"></div>
        <div class="skeleton-body" style="grid-template-columns: 1fr;">
            <div class="skeleton-main">
                <div class="skeleton-stats">
                    <div class="skeleton-stat shimmer"></div>
                    <div class="skeleton-stat shimmer"></div>
                    <div class="skeleton-stat shimmer"></div>
                    <div class="skeleton-stat shimmer"></div>
                </div>
                <div class="skeleton-bento">
                    <div class="skeleton-card shimmer"></div>
                    <div class="skeleton-card shimmer"></div>
                </div>
            </div>
        </div>
    </div>
<?php
// Flush skeleton
ob_flush();
flush();

// 3. Heavy DB Queries (Slow)
$adminModel = new Admin();
$stats = $adminModel->getDashboardStats();
$recentActivity = $adminModel->getRecentActivity(10);
$resumeStats = $adminModel->getResumeCompletionStats();

$chapterModel = new LearningChapter();
$chapters = $chapterModel->all();

// Fetch System Settings for Feature Toggles
$db = getDB();
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Live server status checks
$gmuStatus = Database::checkConnection('gmu');
$gmitStatus = Database::checkConnection('gmit');
?>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --accent-blue: #4318ff;
            --bg-color: #f4f7fe;
            --white: #ffffff;
            --text-dark: #2b3674;
            --text-muted: #a3aed1;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-color);
            display: flex;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .main-content {
            flex: 1;
            padding: 40px;
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Modern Glass Header */
        .glass-header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding: 25px 35px;
            border-radius: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header-title h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--primary-maroon), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Metrics Area */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: var(--white);
            padding: 28px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 22px;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .metric-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary-gold);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
        }

        .metric-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .icon-students {
            background: #E9EDFE;
            color: #4318FF;
        }

        .icon-jobs {
            background: #FFF4E5;
            color: #FF9920;
        }

        .icon-placements {
            background: #E2F9F2;
            color: #05CD99;
        }

        .icon-resolution {
            background: #F4ECFB;
            color: #8C2CE2;
        }

        .metric-info h3 {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-info .value {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        /* Analytics Layout */
        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1100px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel {
            background: var(--white);
            border-radius: 30px;
            padding: 35px;
            box-shadow: var(--shadow);
            position: relative;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .panel-title {
            font-size: 19px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Identity Progress Hub */
        .resolution-hub {
            margin-top: 20px;
        }

        .res-item {
            margin-bottom: 20px;
        }

        .res-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .res-bar {
            height: 8px;
            background: #EEE;
            border-radius: 4px;
            overflow: hidden;
        }

        .res-progress {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }

        /* Activity Feed */
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .feed-item {
            display: flex;
            gap: 15px;
            padding: 18px 0;
            border-bottom: 1px solid #F4F7FE;
            transition: var(--transition);
        }

        .feed-item:hover {
            background: #FAFBFF;
            padding-left: 10px;
        }

        .feed-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border-radius: 14px;
            border: 1px solid #E0E5F2;
            font-family: 'Outfit', sans-serif;
            background: #F4F7FE;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success {
            background: #E2F9F2;
            color: #05CD99;
        }

        .badge-warning {
            background: #FFF4E5;
            color: #FF9920;
        }

        /* Chart Tooltip Customization */
        #deptChart {
            max-height: 250px;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary-maroon);
        }

        input:focus+.slider {
            box-shadow: 0 0 1px var(--primary-maroon);
        }

        input:checked+.slider:before {
            transform: translateX(24px);
        }

        .maintenance-status {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            margin-left: 10px;
        }

        .status-active {
            background: #fee2e2;
            color: #ef4444;
        }

        .status-inactive {
            background: #e2f9f2;
            color: #05cd99;
        }

        .section-header {
            margin: 40px 0 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-header .line {
            flex: 1;
            height: 1px;
            background: #E0E5F2;
        }

        @keyframes pulse-dot {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5);
            }

            70% {
                box-shadow: 0 0 0 5px rgba(34, 197, 94, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Header Section -->
        <header class="glass-header">
            <div class="header-title">
                <h1>Platform Command Center</h1>
                <p>Monitoring <?php echo number_format($stats['total_students']); ?> students across GMU and GMIT</p>
            </div>

            <div style="display: flex; gap: 20px; align-items: center;">
                <!-- Server Status Badges -->
                <div style="display: flex; gap: 8px; align-items: center;">
                    <?php
                    $servers = ['GMU' => $gmuStatus, 'GMIT' => $gmitStatus];
                    foreach ($servers as $name => $status):
                        $ok = $status['ok'];
                        $bg = $ok ? '#dcfce7' : '#fee2e2';
                        $color = $ok ? '#15803d' : '#b91c1c';
                        $dot = $ok ? '#22c55e' : '#ef4444';
                        $label = $ok ? 'Online' : 'Down';
                        $title = $ok ? "{$name} server is reachable" : "Error: " . htmlspecialchars($status['error'] ?? 'Unreachable');
                        ?>
                        <div title="<?php echo $title; ?>"
                            style="display:flex;align-items:center;gap:6px;background:<?php echo $bg; ?>;color:<?php echo $color; ?>;padding:6px 12px;border-radius:50px;font-size:12px;font-weight:700;cursor:default;">
                            <span
                                style="width:7px;height:7px;border-radius:50%;background:<?php echo $dot; ?>;<?php echo $ok ? 'animation:pulse-dot 2s infinite;' : ''; ?>"></span>
                            <?php echo $name; ?>     <?php echo $label; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align: right;">
                    <div style="font-weight: 700;"><?php echo htmlspecialchars($fullName); ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">Global Principal Admin</div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
            </div>
        </header>


        <!-- KPI Grid -->
        <div class="metrics-grid">
            <a href="users.php" class="metric-card" style="text-decoration: none;">
                <div class="metric-icon icon-students"><i class="fas fa-users-viewfinder"></i></div>
                <div class="metric-info">
                    <h3>Reach</h3>
                    <div class="value"><?php echo number_format($stats['total_students']); ?></div>
                </div>
            </a>

            <a href="jobs.php" class="metric-card" style="text-decoration: none;">
                <div class="metric-icon icon-jobs"><i class="fas fa-rocket"></i></div>
                <div class="metric-info">
                    <h3>Live Postings</h3>
                    <div class="value">
                        <?php echo number_format($stats['active_jobs'] + $stats['active_internships']); ?></div>
                </div>
            </a>

            <a href="jobs.php" class="metric-card" style="text-decoration: none;">
                <div class="metric-icon icon-placements"><i class="fas fa-trophy"></i></div>
                <div class="metric-info">
                    <h3>Total Placed</h3>
                    <div class="value"><?php echo number_format($stats['placed_students']); ?></div>
                </div>
            </a>

            <a href="resumes.php" class="metric-card" style="text-decoration: none;">
                <div class="metric-icon icon-resolution"><i class="fas fa-fingerprint"></i></div>
                <div class="metric-info">
                    <h3>Resolution</h3>
                    <div class="value"><?php echo $resumeStats['percentage']; ?>%</div>
                </div>
            </a>

            <a href="learning.php" class="metric-card" style="text-decoration: none;">
                <div class="metric-icon icon-students" style="background: #FFF4E5; color: #FF9920;"><i
                        class="fas fa-graduation-cap"></i></div>
                <div class="metric-info">
                    <h3>Curriculum</h3>
                    <div class="value"><?php echo count($chapters); ?> <span
                            style="font-size: 14px; font-weight: 500;">Chapters</span></div>
                </div>
            </a>

            <!-- Maintenance Mode Card -->
            <div class="metric-card">
                <div class="metric-icon" style="background: #fee2e2; color: #ef4444;"><i class="fas fa-hammer"></i>
                </div>
                <div class="metric-info" style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>Maintenance</h3>
                        <label class="switch">
                            <input type="checkbox" id="maintToggle" <?php echo file_exists(ROOT_PATH . '/src/maintenance.lock') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div style="display: flex; align-items: center; margin-top: 5px;">
                        <span id="maintStatusLabel"
                            class="maintenance-status <?php echo file_exists(ROOT_PATH . '/src/maintenance.lock') ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo file_exists(ROOT_PATH . '/src/maintenance.lock') ? 'ACTIVE' : 'INACTIVE'; ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- AI Feature Control Section -->
        <div class="panel" style="margin-bottom: 30px;">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-sliders" style="color: #7c3aed;"></i> AI Feature Control
                </div>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Toggle to disable features
                    for students</span>
            </div>
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; padding: 20px 0 5px;">

                <!-- Mock AI -->
                <div
                    style="display: flex; align-items: center; justify-content: space-between; background: #fff7ed; padding: 16px 20px; border-radius: 16px; border: 1px solid #fed7aa;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 40px; height: 40px; background: #ffedd5; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #ea580c;">
                            <i class="fas fa-fire"></i></div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: #2b3674;">Mock AI Interview</div>
                            <div style="font-size: 0.78rem; color: #a3aed1;">AI Interview Simulator</div>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" onchange="toggleAI('feature_mock_ai', this)" <?php echo ($settings['feature_mock_ai'] ?? 'enabled') === 'enabled' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Company Guide -->
                <div
                    style="display: flex; align-items: center; justify-content: space-between; background: #f0f9ff; padding: 16px 20px; border-radius: 16px; border: 1px solid #bae6fd;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 40px; height: 40px; background: #e0f2fe; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                            <i class="fas fa-graduation-cap"></i></div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: #2b3674;">Company Guide</div>
                            <div style="font-size: 0.78rem; color: #a3aed1;">AI Placement Guide</div>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" onchange="toggleAI('feature_company_guide', this)" <?php echo ($settings['feature_company_guide'] ?? 'enabled') === 'enabled' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Resume Builder -->
                <div
                    style="display: flex; align-items: center; justify-content: space-between; background: #fff1f2; padding: 16px 20px; border-radius: 16px; border: 1px solid #fecdd3;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 40px; height: 40px; background: #ffe4e6; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #e11d48;">
                            <i class="fas fa-file-invoice"></i></div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: #2b3674;">Resume Builder</div>
                            <div style="font-size: 0.78rem; color: #a3aed1;">AI Resume Generator</div>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" onchange="toggleAI('feature_resume_builder', this)" <?php echo ($settings['feature_resume_builder'] ?? 'enabled') === 'enabled' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Profile Analyser -->
                <div
                    style="display: flex; align-items: center; justify-content: space-between; background: #f5f3ff; padding: 16px 20px; border-radius: 16px; border: 1px solid #ddd6fe;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 40px; height: 40px; background: #ede9fe; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #7c3aed;">
                            <i class="fas fa-robot"></i></div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: #2b3674;">Profile Analyser</div>
                            <div style="font-size: 0.78rem; color: #a3aed1;">Career Architect AI</div>
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" onchange="toggleAI('feature_profile_analyzer', this)" <?php echo ($settings['feature_profile_analyzer'] ?? 'enabled') === 'enabled' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

            </div>
        </div>

        <!-- Analytics Grid -->
        <div class="analytics-grid">
            <!-- Left: Chart -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-chart-pie" style="color: var(--accent-blue);"></i> Resume Mastery
                    </div>
                </div>
                <canvas id="deptChart"></canvas>
                <div style="text-align: center; margin-top: 25px;">
                    <div style="font-size: 32px; font-weight: 800; color: var(--primary-maroon);">
                        <?php echo $resumeStats['total_built']; ?>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">PORTFOLIOS CREATED</div>
                </div>
            </div>

            <!-- Middle: Live Activity -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-bolt" style="color: var(--primary-gold);"></i> Live Activity Stream
                    </div>
                    <span class="badge badge-success">Live</span>
                </div>

                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="streamSearch" placeholder="Filter activities, companies, or roles...">
                </div>

                <div class="activity-feed" id="activityFeed">
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="feed-item"
                            data-search="<?php echo strtolower($activity['role'] . ' ' . $activity['company_name'] . ' ' . $activity['status']); ?>">
                            <div
                                class="feed-icon <?php echo strtolower($activity['status']) === 'selected' ? 'icon-placements' : 'icon-jobs'; ?>">
                                <i
                                    class="fas <?php echo strtolower($activity['status']) === 'selected' ? 'fa-check-circle' : 'fa-paper-plane'; ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display:flex; justify-content:space-between;">
                                    <span
                                        style="font-weight: 700; font-size: 15px;"><?php echo htmlspecialchars($activity['role']); ?></span>
                                    <span
                                        style="font-size: 11px; color: var(--text-muted);"><?php echo timeAgo($activity['activity_date']); ?></span>
                                </div>
                                <div style="font-size: 13px; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($activity['company_name']); ?> •
                                    <span
                                        style="font-weight: 600; color: <?php echo strtolower($activity['status']) === 'selected' ? '#05CD99' : '#3965ff'; ?>">
                                        <?php echo htmlspecialchars($activity['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #F4F7FE; text-align: center;">
                    <a href="resumes.php"
                        style="color: var(--primary-maroon); text-decoration: none; font-weight: 700; font-size: 14px;">
                        View All Student Resumes <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data for Chart.js
        const deptData = <?php echo json_encode($resumeStats['by_department']); ?>;

        const ctx = document.getElementById('deptChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: deptData.map(d => d.department),
                datasets: [{
                    data: deptData.map(d => d.count),
                    backgroundColor: [
                        '#4318FF', '#05CD99', '#FF9920', '#8C2CE2', '#EF4444',
                        '#2b3674', '#a3aed1', '#e9c66f', '#800000', '#5b1f1f'
                    ],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        backgroundColor: '#1B2559',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 }
                    }
                },
                cutout: '75%',
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Real-time Activity Filter
        document.getElementById('streamSearch').addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.feed-item');

            items.forEach(item => {
                const text = item.getAttribute('data-search');
                if (text.includes(term)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // --- GLOBAL SECURITY INTERCEPTOR ---
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ""; ?>';
        
        // Intercept all 'fetch' calls to automatically add CSRF tokens to POST requests
        const originalFetch = window.fetch;
        window.fetch = function() {
            let [resource, config] = arguments;
            if (config && config.method && config.method.toUpperCase() === 'POST') {
                if (!config.headers) config.headers = {};
                if (!(config.headers instanceof Headers)) {
                    config.headers['X-CSRF-TOKEN'] = CSRF_TOKEN;
                } else {
                    config.headers.set('X-CSRF-TOKEN', CSRF_TOKEN);
                }
            }
            return originalFetch(resource, config);
        };

        // --- Existing Admin Logic ---
        document.getElementById('maintToggle').addEventListener('change', async function(e) {
            const isActive = e.target.checked;
            const label = document.getElementById('maintStatusLabel');

            try {
                const formData = new FormData();
                formData.append('action', 'toggle');

                const response = await fetch('maintenance_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    label.innerText = data.status === 'on' ? 'ACTIVE' : 'INACTIVE';
                    label.className = `maintenance-status ${data.status === 'on' ? 'status-active' : 'status-inactive'}`;
                    // Success feedback is now reflected in the label change immediately
                } else {
                    console.error("Maintenance toggle failed: " + data.message);
                    e.target.checked = !isActive;
                }
            } catch (error) {
                console.error("Maintenance connection failed", error);
                e.target.checked = !isActive;
            }
        });

        // AI Feature Toggles
        async function toggleAI(key, el) {
            const status = el.checked ? 'enabled' : 'disabled';
            try {
                const formData = new FormData();
                formData.append('key', key);
                formData.append('value', status);
                formData.append('action', 'update_setting');

                const res = await fetch('settings_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                if (!data.success) {
                    alert('Error: ' + data.message);
                    el.checked = !el.checked;
                }
            } catch (e) {
                console.error('Feature toggle network error', e);
                el.checked = !el.checked;
            }
        }

        // Hide Skeleton Screen after page load
        window.addEventListener('load', function() {
            const skeleton = document.getElementById('skeletonScreen');
            if (skeleton) {
                setTimeout(() => {
                    skeleton.classList.add('hidden');
                    setTimeout(() => skeleton.remove(), 500);
                }, 300); 
            }
        });
    </script>
</body>

</html>