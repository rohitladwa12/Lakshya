<?php
/**
 * Student - My Applications
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Load models
$jobAppModel = new JobApplication();
$internAppModel = new InternshipApplication();

// Get applications
$jobApps = $jobAppModel->getByStudent($userId);
$internApps = $internAppModel->getByStudent($userId);

// Helper function for status badge
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Applied': return 'badge-info';
        case 'Under Review': return 'badge-warning';
        case 'Shortlisted': return 'badge-primary';
        case 'Interview Scheduled': return 'badge-indigo';
        case 'Selected': return 'badge-success';
        case 'Rejected': return 'badge-danger';
        case 'Withdrawn': return 'badge-secondary';
        default: return 'badge-light';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - <?php echo APP_NAME; ?></title>
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
            --gradient: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light-gray);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header { margin-bottom: 30px; }
        .page-header h2 { font-size: 32px; color: var(--dark-gray); }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .tab {
            padding: 15px 30px;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .app-card {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .app-info { display: flex; gap: 20px; align-items: center; }
        
        .company-logo {
            width: 60px; height: 60px; border-radius: 8px;
            background: var(--light-gray);
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; color: var(--primary-maroon); font-size: 24px;
        }
        
        .title { font-size: 18px; font-weight: 600; color: var(--dark-gray); margin-bottom: 5px; }
        .company { color: #666; font-size: 14px; }
        .date { color: #999; font-size: 13px; margin-top: 5px; }
        
        .badge {
            padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: 600;
        }
        
        .badge-info { background: #e3f2fd; color: #1e88e5; }
        .badge-warning { background: #fff8e1; color: #ff8f00; }
        .badge-primary { background: #e8eaf6; color: #3f51b5; }
        .badge-indigo { background: #f3e5f5; color: #7b1fa2; }
        .badge-success { background: #e8f5e9; color: #2e7d32; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-secondary { background: #f5f5f5; color: #616161; }
        
        .actions { display: flex; gap: 10px; }
        .btn {
            padding: 8px 16px; border-radius: 6px; font-weight: 600;
            text-decoration: none; cursor: pointer; font-size: 13px; border: none;
        }
        .btn-outline { border: 1px solid var(--medium-gray); color: #666; background: white; }
        .btn-outline:hover { background: var(--light-gray); }
        
        .empty-state {
            text-align: center; padding: 60px; background: white; border-radius: 12px;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>📝 My Applications</h2>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('jobs')">Job Applications (<?php echo count($jobApps); ?>)</div>
            <div class="tab" onclick="switchTab('internships')">Internships (<?php echo count($internApps); ?>)</div>
        </div>
        
        <div id="jobs-content" class="tab-content active">
            <?php if (empty($jobApps)): ?>
                <div class="empty-state">
                    <h3>No Job Applications</h3>
                    <p>You haven't applied for any jobs yet. <a href="jobs.php">Browse Jobs</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($jobApps as $app): ?>
                    <div class="app-card">
                        <div class="app-info">
                            <div class="company-logo"><?php echo strtoupper(substr($app['company_name'], 0, 2)); ?></div>
                            <div>
                                <div class="title"><?php echo htmlspecialchars($app['job_title']); ?></div>
                                <div class="company"><?php echo htmlspecialchars($app['company_name']); ?> | <?php echo $app['job_type']; ?></div>
                                <div class="date">Applied on <?php echo date('d M Y', strtotime($app['applied_at'])); ?></div>
                            </div>
                        </div>
                        <div class="status">
                            <span class="badge <?php echo getStatusBadgeClass($app['status']); ?>">
                                <?php echo $app['status']; ?>
                            </span>
                        </div>
                        <div class="actions">
                            <a href="job_details.php?id=<?php echo $app['job_id']; ?>" class="btn btn-outline">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div id="internships-content" class="tab-content">
            <?php if (empty($internApps)): ?>
                <div class="empty-state">
                    <h3>No Internship Applications</h3>
                    <p>You haven't applied for any internships yet. <a href="internships.php">Find Internships</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($internApps as $app): ?>
                    <div class="app-card">
                        <div class="app-info">
                            <div class="company-logo"><?php echo strtoupper(substr($app['company_name'], 0, 2)); ?></div>
                            <div>
                                <div class="title"><?php echo htmlspecialchars($app['internship_title']); ?></div>
                                <div class="company"><?php echo htmlspecialchars($app['company_name']); ?> | <?php echo $app['duration']; ?></div>
                                <div class="date">Applied on <?php echo date('d M Y', strtotime($app['applied_at'])); ?></div>
                            </div>
                        </div>
                        <div class="status">
                            <span class="badge <?php echo getStatusBadgeClass($app['status']); ?>">
                                <?php echo $app['status']; ?>
                            </span>
                        </div>
                        <div class="actions">
                            <a href="internship_details.php?id=<?php echo $app['internship_id']; ?>" class="btn btn-outline">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'jobs') {
                document.querySelectorAll('.tab')[0].classList.add('active');
                document.getElementById('jobs-content').classList.add('active');
            } else {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('internships-content').classList.add('active');
            }
        }
    </script>
</body>
</html>

