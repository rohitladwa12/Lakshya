<?php
/**
 * Placement Officer Dashboard - Modernized
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

$userId   = getUserId();
$fullName = getFullName();

$officerModel = new PlacementOfficer();
$stats        = $officerModel->getDashboardStats();
$recentApps   = $officerModel->getRecentApplications(6);
$recentJobs   = $officerModel->getRecentJobs(6);

$placedModel = new CompanyPlacedStudent();
$placedStats = $placedModel->getStatistics();

// Mock data for Chart.js - in a real app, this would come from the database
$chartData = [
    'labels' => ['2021', '2022', '2023', '2024', '2025'],
    'placements' => [450, 520, 610, 750, 890]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard – <?php echo APP_NAME; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --brand: #7C0000;
            --brand-light: #A50000;
            --gold: #C9972C;
            --glass: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-dark);
            margin: 0;
            padding-top: 80px; /* Adjusted for new 70px navbar + 10px breathing room */
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header Section */
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .welcome-text h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(to right, var(--brand), var(--brand-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-text p {
            color: var(--text-muted);
            margin: 4px 0 0 0;
            font-size: 15px;
        }

        .hub-tabs {
            display: flex;
            gap: 2px;
            border-bottom: 2px solid var(--black);
            margin-bottom: 30px;
            background: #fff;
            position: sticky;
            top: 70px; /* Aligned with new 70px navbar */
            z-index: 10;
        }

        .quick-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: var(--brand);
            color: white;
            box-shadow: 0 4px 12px rgba(124, 0, 0, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(124, 0, 0, 0.3);
            background: var(--brand-light);
        }

        .btn-gold {
            background: var(--gold);
            color: white;
            box-shadow: 0 4px 12px rgba(201, 151, 44, 0.2);
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(201, 151, 44, 0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--glass);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--brand);
            display: block;
        }

        .stat-footer {
            margin-top: 12px;
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Main Content Layout */
        .dashboard-main {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .content-card {
            background: var(--glass);
            backdrop-filter: blur(8px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .card-header a {
            font-size: 13px;
            color: var(--brand);
            font-weight: 600;
            text-decoration: none;
        }

        /* Tables */
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .modern-table th {
            text-align: left;
            padding: 0 16px 8px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .modern-table td {
            padding: 16px;
            background: rgba(255, 255, 255, 0.5);
            border: none;
        }

        .modern-table tr td:first-child { border-radius: 12px 0 0 12px; }
        .modern-table tr td:last-child { border-radius: 0 12px 12px 0; }

        .modern-table tr:hover td {
            background: rgba(255, 255, 255, 0.8);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-blue { background: #eff6ff; color: #1d4ed8; }
        .badge-green { background: #f0fdf4; color: #16a34a; }

        /* Chart Area */
        #placementChart {
            max-height: 300px;
        }

        @media (max-width: 1024px) {
            .dashboard-main { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .welcome-section { flex-direction: column; align-items: flex-start; gap: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include_once 'includes/navbar.php'; ?>

<div class="dashboard-container">
    <!-- Header -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?php echo htmlspecialchars(explode(' ', (string)$fullName)[0]); ?> 👋</h1>
            <p>Welcome back to Lakshya Intelligence Hub. Here's your placement overview.</p>
        </div>
        <div class="quick-actions">
            <a href="upload_placed_students.php" class="btn btn-gold">
                <i class="fas fa-upload"></i> Upload Data
            </a>
            <a href="jobs.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Post New Job
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Active Job Openings</span>
            <span class="stat-value"><?php echo $stats['active_jobs']; ?></span>
            <div class="stat-footer">
                <i class="fas fa-briefcase"></i> Current live opportunities
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Pending Applications</span>
            <span class="stat-value"><?php echo $stats['pending_applications']; ?></span>
            <div class="stat-footer">
                <i class="fas fa-clock"></i> Awaiting review
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Students Placed</span>
            <span class="stat-value"><?php echo $stats['placed_students'] + $placedStats['total_placed']; ?></span>
            <div class="stat-footer">
                <i class="fas fa-graduation-cap"></i> Total across institutions
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-label">Companies</span>
            <span class="stat-value"><?php echo $stats['total_companies']; ?></span>
            <div class="stat-footer">
                <i class="fas fa-building"></i> Partnered organizations
            </div>
        </div>
    </div>

    <!-- Main Dashboard Row -->
    <div class="dashboard-main">
        <!-- Chart & Activity -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="content-card">
                <div class="card-header">
                    <h3>Placement Performance Trends</h3>
                    <div style="font-size: 13px; color: var(--text-muted);">Year over Year Growth</div>
                </div>
                <canvas id="placementChart"></canvas>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Applications</h3>
                    <a href="applications.php">View All Applications →</a>
                </div>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Position Applying For</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentApps as $app): ?>
                        <tr>
                            <td style="font-weight: 700;"><?php echo htmlspecialchars($app['student_name']); ?></td>
                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($app['job_title']); ?></td>
                            <td><span class="badge badge-blue"><?php echo $app['status']; ?></span></td>
                        </tr>
                        <?php endforeach; if (empty($recentApps)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No recent activity</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar: Recently Posted Jobs -->
        <div class="content-card">
            <div class="card-header">
                <h3>Latest Job Postings</h3>
                <a href="jobs.php">Manage →</a>
            </div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($recentJobs as $job): ?>
                <div style="padding: 16px; background: rgba(255, 255, 255, 0.4); border-radius: 16px; border: 1px solid var(--glass-border);">
                    <div style="font-weight: 700; margin-bottom: 4px;"><?php echo htmlspecialchars($job['title']); ?></div>
                    <div style="font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                    </div>
                    <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <span class="badge badge-green">Sem <?php echo htmlspecialchars($job['min_cgpa'] ? 'Req' : 'All'); ?></span>
                        <span style="font-size: 11px; color: #ef4444;">Due: <?php echo date('d M', strtotime($job['application_deadline'])); ?></span>
                    </div>
                </div>
                <?php endforeach; if (empty($recentJobs)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 20px;">No jobs posted recently</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Placement Chart
    const ctx = document.getElementById('placementChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartData['labels']); ?>,
            datasets: [{
                label: 'Students Placed',
                data: <?php echo json_encode($chartData['placements']); ?>,
                borderColor: '#7C0000',
                backgroundColor: 'rgba(124, 0, 0, 0.05)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#7C0000',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
</script>
</body>
</html>
</body>
</html>
