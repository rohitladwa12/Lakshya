<?php
/**
 * Global Admin Dashboard
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/Admin.php';
require_once __DIR__ . '/../../src/Models/LearningChapter.php';

// Require admin role
requireRole(ROLE_ADMIN);

$fullName = getFullName();

$adminModel = new Admin();
$stats = $adminModel->getDashboardStats();
$recentActivity = $adminModel->getRecentActivity(10);
$resumeStats = $adminModel->getResumeCompletionStats();

$chapterModel = new LearningChapter();
$chapters = $chapterModel->all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <!-- Modern Typography and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --shadow: 0 20px 40px rgba(0,0,0,0.05);
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
            border: 1px solid rgba(255,255,255,0.3);
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
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
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

        .icon-students { background: #E9EDFE; color: #4318FF; }
        .icon-jobs { background: #FFF4E5; color: #FF9920; }
        .icon-placements { background: #E2F9F2; color: #05CD99; }
        .icon-resolution { background: #F4ECFB; color: #8C2CE2; }

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
            grid-template-columns: 1fr 1.2fr 0.8fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1400px) {
            .analytics-grid { grid-template-columns: 1fr 1fr; }
            .col-3 { grid-column: span 2; }
        }

        @media (max-width: 900px) {
            .analytics-grid { grid-template-columns: 1fr; }
            .col-3 { grid-column: span 1; }
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

        .res-item { margin-bottom: 20px; }
        .res-label { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 8px; }
        .res-bar { height: 8px; background: #EEE; border-radius: 4px; overflow: hidden; }
        .res-progress { height: 100%; border-radius: 4px; transition: width 1s ease-in-out; }

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

        .badge-success { background: #E2F9F2; color: #05CD99; }
        .badge-warning { background: #FFF4E5; color: #FF9920; }

        /* Chart Tooltip Customization */
        #deptChart { max-height: 250px; }

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

        input:checked + .slider {
            background-color: var(--primary-maroon);
        }

        input:focus + .slider {
            box-shadow: 0 0 1px var(--primary-maroon);
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .maintenance-status {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            margin-left: 10px;
        }
        .status-active { background: #fee2e2; color: #ef4444; }
        .status-inactive { background: #e2f9f2; color: #05cd99; }

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
                    <div class="value"><?php echo number_format($stats['active_jobs'] + $stats['active_internships']); ?></div>
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
                <div class="metric-icon icon-students" style="background: #FFF4E5; color: #FF9920;"><i class="fas fa-graduation-cap"></i></div>
                <div class="metric-info">
                    <h3>Curriculum</h3>
                    <div class="value"><?php echo count($chapters); ?> <span style="font-size: 14px; font-weight: 500;">Chaps</span></div>
                </div>
            </a>

            <!-- Maintenance Mode Card -->
            <div class="metric-card">
                <div class="metric-icon" style="background: #fee2e2; color: #ef4444;"><i class="fas fa-hammer"></i></div>
                <div class="metric-info" style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>Maintenance</h3>
                        <label class="switch">
                            <input type="checkbox" id="maintToggle" <?php echo file_exists(ROOT_PATH . '/src/maintenance.lock') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div style="display: flex; align-items: center; margin-top: 5px;">
                        <span id="maintStatusLabel" class="maintenance-status <?php echo file_exists(ROOT_PATH . '/src/maintenance.lock') ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo file_exists(ROOT_PATH . '/src/maintenance.lock') ? 'ACTIVE' : 'INACTIVE'; ?>
                        </span>
                    </div>
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
                    <?php foreach($recentActivity as $activity): ?>
                        <div class="feed-item" data-search="<?php echo strtolower($activity['role'] . ' ' . $activity['company_name'] . ' ' . $activity['status']); ?>">
                            <div class="feed-icon <?php echo strtolower($activity['status']) === 'selected' ? 'icon-placements' : 'icon-jobs'; ?>">
                                <i class="fas <?php echo strtolower($activity['status']) === 'selected' ? 'fa-check-circle' : 'fa-paper-plane'; ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="font-weight: 700; font-size: 15px;"><?php echo htmlspecialchars($activity['role']); ?></span>
                                    <span style="font-size: 11px; color: var(--text-muted);"><?php echo timeAgo($activity['activity_date']); ?></span>
                                </div>
                                <div style="font-size: 13px; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($activity['company_name']); ?> • 
                                    <span style="font-weight: 600; color: <?php echo strtolower($activity['status']) === 'selected' ? '#05CD99' : '#3965ff'; ?>">
                                        <?php echo htmlspecialchars($activity['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Identity Registry -->
            <div class="panel col-3">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-shield-halved" style="color: var(--primary-dark);"></i> Identity Resolver
                    </div>
                </div>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 25px;">
                    Ensuring all students are mapped to official USNs across the multi-database cluster.
                </p>
                
                <div class="resolution-hub">
                    <div class="res-item">
                        <div class="res-label"><span>Official USN Matches</span><span>Verified</span></div>
                        <div class="res-bar"><div class="res-progress" style="width: 85%; background: #05CD99;"></div></div>
                    </div>
                    <div class="res-item">
                        <div class="res-label"><span>Aadhar/Legacy Fallbacks</span><span>Resolving</span></div>
                        <div class="res-bar"><div class="res-progress" style="width: 12%; background: #FF9920;"></div></div>
                    </div>
                    <div class="res-item">
                        <div class="res-label"><span>Unmapped Entries</span><span>Attention</span></div>
                        <div class="res-bar"><div class="res-progress" style="width: 3%; background: #ef4444;"></div></div>
                    </div>
                </div>

                <div style="margin-top: 40px; padding-top: 25px; border-top: 1px solid #F4F7FE;">
                    <a href="" style="display:block; text-align:center; padding: 15px; background: var(--bg-color); border-radius: 15px; color: var(--primary-maroon); text-decoration: none; font-weight: 700; border: 1px solid transparent; transition: var(--transition);" onmouseover="this.style.borderColor='var(--primary-maroon)'" onmouseout="this.style.borderColor='transparent'">
                        Audit Detailed Registry <i class="fas fa-arrow-right"></i>
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
        document.getElementById('streamSearch').addEventListener('input', function(e) {
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

        // Maintenance Toggle Logic
        document.getElementById('maintToggle').addEventListener('change', async function(e) {
            const isActive = e.target.checked;
            const label = document.getElementById('maintStatusLabel');
            
            if (isActive && !confirm("Warning: This will block ALL users (except admins) from accessing the site. Continue?")) {
                e.target.checked = false;
                return;
            }

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
                    alert(data.message);
                } else {
                    alert("Error: " + data.message);
                    e.target.checked = !isActive;
                }
            } catch (error) {
                alert("Connection failed.");
                e.target.checked = !isActive;
            }
        });
    </script>
</body>
</html>
