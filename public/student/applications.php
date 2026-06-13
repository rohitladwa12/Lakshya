<?php
/**
 * Student - My Applications
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$userId = getUserId();
$fullName = getFullName();

// Load models
$jobAppModel = new JobApplication();
$internAppModel = new InternshipApplication();

// Get applications
$jobApps = $jobAppModel->getByStudent($userId);
$internApps = $internAppModel->getByStudent($userId);

$db = getDB();
$jobIds = array_column($jobApps, 'job_id');
$activeDrives = [];
if (!empty($jobIds)) {
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $stmt = $db->prepare("
        SELECT id, job_id, drive_name, deadline 
        FROM campus_drives 
        WHERE job_id IN ($placeholders)
    ");
    $stmt->execute($jobIds);
    $drivesList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($drivesList as $d) {
        $activeDrives[$d['job_id']] = $d;
    }
}

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #800000;
            --brand-dark: #5b1f1f;
            --brand-grad: linear-gradient(135deg, #800000 0%, #a52a2a 100%);
            --brand-light: #fff5f5;
            --text-dark: #0f172a;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --surface: #ffffff;
            --bg: #f1f5f9;
            --radius: 16px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            padding-top: 80px;
            min-height: 100vh;
        }

        .page-wrap { max-width: 1280px; margin: 0 auto; padding: 36px 24px 60px; }

        .page-hero {
            background: var(--brand-grad);
            border-radius: 24px;
            padding: 36px 40px;
            margin-bottom: 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .page-hero h1 { font-size: 28px; font-weight: 800; margin-bottom: 6px; }
        .page-hero p { color: rgba(255,255,255,0.75); font-size: 15px; }

        .tabs { display: flex; gap: 10px; margin-bottom: 24px; }
        .tab-btn {
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            background: var(--surface);
            border: 1.5px solid var(--border);
            color: var(--text-mid);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: var(--brand-light);
            color: var(--brand);
            border-color: var(--brand);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .app-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
        
        .app-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 24px;
            display: flex; flex-direction: column; gap: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .app-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(128,0,0,0.12);
        }
        .app-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .co-avatar {
            width: 48px; height: 48px; border-radius: 12px;
            background: var(--brand-light); color: var(--brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 800;
        }
        .app-title { flex: 1; }
        .app-title h3 { font-size: 17px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .app-title p { font-size: 13px; color: var(--text-mid); font-weight: 500; }

        .badge {
            padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase;
        }
        .badge-info { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
        .badge-warning { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
        .badge-primary { background: #dbeafe; color: #2563eb; border: 1px solid #bfdbfe; }
        .badge-indigo { background: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe; }
        .badge-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .badge-danger { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .badge-secondary { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .app-meta { font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }

        .drive-box {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px;
            font-size: 13px; display: flex; justify-content: space-between; align-items: center;
        }
        
        .btn-view {
            width: 100%; display: block; text-align: center;
            padding: 10px; border-radius: 10px; background: var(--bg);
            color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 13px;
            border: 1px solid var(--border); transition: all 0.2s;
        }
        .btn-view:hover { background: var(--border); }
        .btn-drive {
            background: var(--brand-grad); color: #fff; padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 12px;
        }
        .btn-drive:hover { opacity: 0.9; }

        .empty-state { text-align: center; padding: 60px 20px; background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); }
        .empty-state i { font-size: 48px; color: var(--border); margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; }
        .empty-state p { font-size: 14px; color: var(--text-muted); margin-bottom: 20px; }
        .empty-state a { color: var(--brand); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="page-wrap">
        <div class="page-hero">
            <div>
                <h1><i class="fas fa-folder-open" style="margin-right:10px; opacity:0.8;"></i>My Applications</h1>
                <p>Track your job and internship applications and their progress.</p>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('jobs')">Job Applications (<?php echo count($jobApps); ?>)</button>
            <button class="tab-btn" onclick="switchTab('internships')">Internship Applications (<?php echo count($internApps); ?>)</button>
        </div>

        <!-- JOBS TAB -->
        <div id="jobs-content" class="tab-content active">
            <?php if (empty($jobApps)): ?>
                <div class="empty-state">
                    <i class="fas fa-briefcase"></i>
                    <h3>No Job Applications</h3>
                    <p>You haven't applied to any jobs yet.</p>
                    <a href="jobs.php">Browse Jobs &rarr;</a>
                </div>
            <?php else: ?>
                <div class="app-grid">
                    <?php foreach ($jobApps as $app): 
                        $initials = strtoupper(substr($app['company_name'], 0, 2));
                        $drive = $activeDrives[$app['job_id']] ?? null;
                    ?>
                    <div class="app-card">
                        <div class="app-header">
                            <div class="co-avatar"><?php echo $initials; ?></div>
                            <div class="app-title">
                                <h3><?php echo htmlspecialchars($app['job_title']); ?></h3>
                                <p><?php echo htmlspecialchars($app['company_name']); ?></p>
                            </div>
                            <span class="badge <?php echo getStatusBadgeClass($app['status']); ?>"><?php echo $app['status']; ?></span>
                        </div>
                        <div class="app-meta">
                            <i class="far fa-calendar-alt"></i> Applied on <?php echo date('d M Y', strtotime($app['applied_at'])); ?>
                        </div>
                        
                        <?php if ($drive): ?>
                        <div class="drive-box">
                            <div>
                                <strong><i class="fas fa-rocket"></i> Campus Drive</strong>
                                <br><span style="color:var(--text-muted);"><?php echo htmlspecialchars($drive['drive_name']); ?></span>
                            </div>
                            <a href="student_drive.php?drive_id=<?php echo $drive['id']; ?>" class="btn-drive">Enter Drive</a>
                        </div>
                        <?php endif; ?>

                        <a href="job_details.php?id=<?php echo $app['job_id']; ?>" class="btn-view">View Job Details</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- INTERNSHIPS TAB -->
        <div id="internships-content" class="tab-content">
            <?php if (empty($internApps)): ?>
                <div class="empty-state">
                    <i class="fas fa-laptop-code"></i>
                    <h3>No Internship Applications</h3>
                    <p>You haven't applied to any internships yet.</p>
                    <a href="internships.php">Browse Internships &rarr;</a>
                </div>
            <?php else: ?>
                <div class="app-grid">
                    <?php foreach ($internApps as $app): 
                        $initials = strtoupper(substr($app['company_name'], 0, 2));
                    ?>
                    <div class="app-card">
                        <div class="app-header">
                            <div class="co-avatar"><?php echo $initials; ?></div>
                            <div class="app-title">
                                <h3><?php echo htmlspecialchars($app['internship_title']); ?></h3>
                                <p><?php echo htmlspecialchars($app['company_name']); ?></p>
                            </div>
                            <span class="badge <?php echo getStatusBadgeClass($app['status']); ?>"><?php echo $app['status']; ?></span>
                        </div>
                        <div class="app-meta">
                            <i class="far fa-calendar-alt"></i> Applied on <?php echo date('d M Y', strtotime($app['applied_at'])); ?>
                        </div>

                        <a href="internship_details.php?id=<?php echo $app['internship_id']; ?>" class="btn-view">View Details</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            if (tab === 'jobs') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                document.getElementById('jobs-content').classList.add('active');
            } else {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                document.getElementById('internships-content').classList.add('active');
            }
        }
    </script>
</body>
</html>