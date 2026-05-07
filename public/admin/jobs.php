<?php
/**
 * Placements Management - Admin View
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/Models/JobPosting.php';

// Require admin role
requireRole(ROLE_ADMIN);

$fullName = getFullName();
$jobModel = new JobPosting();

// Handle Status Toggle (Converted to POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status' && isset($_POST['id'])) {
    $jobId = $_POST['id'];
    $currentJob = $jobModel->find($jobId);
    if ($currentJob) {
        $newStatus = ($currentJob['status'] === 'Active') ? 'Closed' : 'Active';
        $jobModel->update($jobId, ['status' => $newStatus]);
        header("Location: jobs.php?success=" . urlencode("Job status updated to $newStatus"));
        exit;
    }
}

$jobs = $jobModel->getAllWithCompany();

// Stats for the top row
$activeCount = 0;
$totalApps = 0;
foreach ($jobs as $j) {
    if ($j['status'] === 'Active') $activeCount++;
    $totalApps += $j['application_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placements Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-color); display: flex; min-height: 100vh; color: var(--text-dark); }

        .main-content { flex: 1; padding: 40px; width: 100%; max-width: 1600px; margin: 0 auto; }

        .glass-header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; padding: 25px 35px; border-radius: 30px;
            box-shadow: var(--shadow); border: 1px solid rgba(255,255,255,0.3);
        }

        .header-title h1 {
            font-size: 26px; font-weight: 800; letter-spacing: -1px;
            background: linear-gradient(135deg, var(--primary-maroon), var(--accent-blue));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* Stats Row */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: var(--white); padding: 20px; border-radius: 20px; box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 15px; border-bottom: 4px solid transparent;
        }
        .stat-card.active { border-color: #05CD99; }
        .stat-card.apps { border-color: var(--accent-blue); }
        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .icon-active { background: #E2F9F2; color: #05CD99; }
        .icon-apps { background: #E9EDFE; color: #4318FF; }

        /* Main Table Panel */
        .panel { background: var(--white); border-radius: 30px; padding: 35px; box-shadow: var(--shadow); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .panel-title { font-size: 19px; font-weight: 800; display: flex; align-items: center; gap: 12px; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 13px; font-weight: 600; border-bottom: 1px solid #F4F7FE; text-transform: uppercase; }
        .data-table td { padding: 18px 15px; border-bottom: 1px solid #F4F7FE; vertical-align: middle; }

        .company-info { display: flex; align-items: center; gap: 12px; }
        .company-logo { width: 36px; height: 36px; border-radius: 10px; background: #F4F7FE; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--primary-maroon); font-size: 12px; }

        .badge { padding: 6px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: #E2F9F2; color: #05CD99; }
        .badge-closed { background: #FFF4E5; color: #FF9920; }

        .app-count { background: #F4F7FE; padding: 4px 10px; border-radius: 8px; font-weight: 700; color: var(--accent-blue); font-size: 13px; }

        .btn-action { padding: 8px 12px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 12px; transition: var(--transition); border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: var(--primary-maroon); color: white; }
        .btn-secondary { background: #F4F7FE; color: var(--text-dark); }
        .btn-status { background: transparent; border: 1px solid #E0E5F2; color: var(--text-muted); }
        .btn-status:hover { border-color: var(--primary-maroon); color: var(--primary-maroon); }

        .btn-add { background: linear-gradient(135deg, var(--primary-maroon), var(--primary-dark)); color: white; padding: 12px 24px; border-radius: 15px; box-shadow: 0 10px 20px rgba(128,0,0,0.2); }

        .search-row { display: flex; gap: 15px; margin-bottom: 25px; }
        .search-input { flex: 1; padding: 12px 20px; border-radius: 15px; border: 1px solid #E0E5F2; background: #F4F7FE; font-family: inherit; }

        .back-link { color: var(--text-muted); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; transition: var(--transition); }
        .back-link:hover { color: var(--primary-maroon); transform: translateX(-5px); }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <header class="glass-header">
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
            <div class="header-title">
                <h1>Placements Pipeline</h1>
                <p>Managing direct campus drives and job application processing</p>
            </div>
                        <a href="#" class="btn-action btn-add"><i class="fas fa-plus-circle"></i> Create New Posting</a>
        </header>

        <div class="stats-grid">
            <div class="stat-card active">
                <div class="stat-icon icon-active"><i class="fas fa-satellite-dish"></i></div>
                <div>
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;">ACTIVE DRIVES</div>
                    <div style="font-size: 24px; font-weight: 800;"><?php echo $activeCount; ?></div>
                </div>
            </div>
            <div class="stat-card apps">
                <div class="stat-icon icon-apps"><i class="fas fa-file-signature"></i></div>
                <div>
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;">PENDING APPS</div>
                    <div style="font-size: 24px; font-weight: 800;"><?php echo number_format($totalApps); ?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-list-check" style="color: var(--accent-blue);"></i> Job Registry</div>
            </div>

            <div class="search-row">
                <input type="text" id="jobSearch" class="search-input" placeholder="Quick search by role, company, or requirements...">
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job Role & ID</th>
                        <th>Associated Company</th>
                        <th>Deadline</th>
                        <th>Applications</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="jobTableBody">
                    <?php foreach($jobs as $job): ?>
                        <tr class="job-row" data-search="<?php echo strtolower($job['title'] . ' ' . $job['company_name']); ?>">
                            <td>
                                <div style="font-weight: 800; font-size: 15px;"><?php echo htmlspecialchars($job['title']); ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);">REF_ID: #JOB<?php echo $job['id']; ?> • <?php echo $job['job_type']; ?></div>
                            </td>
                            <td>
                                <div class="company-info">
                                    <div class="company-logo"><?php echo strtoupper(substr($job['company_name'], 0, 1)); ?></div>
                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($job['company_name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 13px;">
                                    <?php echo date('d M, Y', strtotime($job['application_deadline'])); ?>
                                </div>
                                <?php 
                                    $deadline = strtotime($job['application_deadline']);
                                    $remaining = ($deadline - time()) / (60 * 60 * 24);
                                    if ($remaining < 3 && $remaining > 0) {
                                        echo '<div style="font-size: 11px; color: #ef4444; font-weight: 700;">Closing Soon!</div>';
                                    }
                                ?>
                            </td>
                            <td>
                                <span class="app-count"><?php echo $job['application_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo strtolower($job['status']) === 'active' ? 'badge-active' : 'badge-closed'; ?>">
                                    <?php echo $job['status']; ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn-action btn-status" title="Toggle Status">
                                            <i class="fas <?php echo $job['status'] === 'Active' ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
                                        </button>
                                    </form>
                                    <a href="#" class="btn-action btn-secondary"><i class="fas fa-pen-to-square"></i></a>
                                    <a href="#" class="btn-action btn-primary">Apps <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Real-time search
        document.getElementById('jobSearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.job-row');
            rows.forEach(row => {
                const text = row.getAttribute('data-search');
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>
</html>

