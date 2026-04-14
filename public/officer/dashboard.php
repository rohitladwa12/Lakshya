<?php
/**
 * Placement Officer Dashboard
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require placement officer role
requireRole(ROLE_PLACEMENT_OFFICER);

$userId = getUserId();
$fullName = getFullName();

$officerModel = new PlacementOfficer();
$stats = $officerModel->getDashboardStats();
$recentApplications = $officerModel->getRecentApplications(5);
$recentJobs = $officerModel->getRecentJobs(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --secondary-gold: #f7f3b7;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --medium-gray: #e0e0e0;
            --dark-gray: #333333;
            --medium-gray: #e0e0e0;
            --dark-gray: #333333;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            /* Layout handled by navbar.php */
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .header h2 {
            font-size: 28px;
            color: var(--primary-maroon);
        }

        .user-pill {
            background: var(--white);
            padding: 8px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow);
        }

        .user-pill .avatar {
            width: 35px;
            height: 35px;
            background: var(--primary-maroon);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary-gold);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-maroon);
        }

        /* Tables and Sections */
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .section-card {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }

        .section-header h3 {
            font-size: 18px;
            color: var(--primary-maroon);
        }

        .btn-view-all {
            color: var(--primary-gold);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-size: 13px;
            text-transform: uppercase;
            color: #888;
            border-bottom: 1px solid #eee;
        }

        td {
            padding: 15px 12px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #f9f9f9;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-active { background: #e3fcef; color: #00875a; }
        .badge-applied { background: #deebff; color: #0747a6; }
        .badge-placed { background: #fffecb; color: #827b00; }

        @media (max-width: 1024px) {
            .dashboard-sections { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include_once 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div>
                <h2>Welcome Back, Officer!</h2>
                <p style="color: #666; margin-top: 5px;">Here's what's happening today.</p>
            </div>
            <div class="user-pill">
                <div class="avatar"><?php echo strtoupper(substr((string)$fullName, 0, 1)); ?></div>
                <span style="font-weight: 600; color: #333;"><?php echo htmlspecialchars((string)$fullName); ?></span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Jobs</div>
                <div class="stat-value"><?php echo $stats['active_jobs']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">New Applications</div>
                <div class="stat-value"><?php echo $stats['pending_applications']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Students Placed</div>
                <div class="stat-value"><?php echo $stats['placed_students']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Companies</div>
                <div class="stat-value"><?php echo $stats['total_companies']; ?></div>
            </div>
        </div>

        <div class="dashboard-sections">
            <!-- Recent Applications -->
            <div class="section-card">
                <div class="section-header">
                    <h3>Recent Applications</h3>
                    <a href="applications.php" class="btn-view-all">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentApplications as $app): ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($app['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                            <td>
                                <span class="status-badge badge-applied"><?php echo $app['status']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; if (empty($recentApplications)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #999;">No recent applications</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Jobs -->
            <div class="section-card">
                <div class="section-header">
                    <h3>Recently Posted Jobs</h3>
                    <a href="jobs.php" class="btn-view-all">Manage Jobs</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Company</th>
                            <th>Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentJobs as $job): ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($job['title']); ?></td>
                            <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                            <td style="color: #666;"><?php echo date('d M', strtotime($job['application_deadline'])); ?></td>
                        </tr>
                        <?php endforeach; if (empty($recentJobs)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #999;">No recent jobs</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
