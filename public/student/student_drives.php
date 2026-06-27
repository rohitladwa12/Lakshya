<?php
/**
 * Student - View Campus Drives
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$db = getDB();
$userId = getUserId();
$usn = getUsername(); // USN of student

// Fetch all campus drives the student is eligible for
$stmt = $db->prepare("
    SELECT cd.*, jp.title as job_title, jp.id as job_id, c.name as company_name, c.logo_url
    FROM campus_drives cd
    JOIN job_postings jp ON cd.job_id = jp.id
    LEFT JOIN companies c ON jp.company_id = c.id
    JOIN job_applications ja ON ja.job_id = jp.id
    WHERE ja.student_id = ?
    ORDER BY cd.created_at DESC
");
$stmt->execute([$usn]);
$drives = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Campus Drives | Placement Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #800000;
            --brand-dark: #5b1f1f;
            --brand-grad: linear-gradient(135deg, #800000 0%, #a52a2a 100%);
            --text-dark: #0f172a;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --surface: #ffffff;
            --bg: #f1f5f9;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 12px 32px rgba(128, 0, 0, 0.12);
            --radius: 16px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            padding-top: 80px;
            min-height: 100vh;
        }

        .page-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 36px 24px 60px;
        }

        .page-hero {
            background: var(--brand-grad);
            border-radius: 24px;
            padding: 36px 40px;
            margin-bottom: 36px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }

        .page-hero h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .page-hero p { color: rgba(255,255,255,0.85); font-size: 16px; font-weight: 500; }

        .drives-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        .drive-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .drive-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--brand);
        }

        .company-logo {
            width: 56px;
            height: 56px;
            background: var(--bg);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--brand);
            font-weight: 800;
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .company-logo img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }

        .drive-title { font-size: 18px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .job-title { font-size: 14px; font-weight: 600; color: var(--brand); margin-bottom: 12px; }

        .drive-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
            padding: 12px;
            background: var(--bg);
            border-radius: 10px;
        }

        .meta-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-mid); font-weight: 500; }
        .meta-item i { color: var(--brand); width: 16px; text-align: center; }

        .rounds-info {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }

        .round-badge {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(128,0,0,0.06);
            color: var(--brand);
            font-weight: 700;
            border: 1px solid rgba(128,0,0,0.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px dashed var(--border);
        }

        .empty-state i { font-size: 48px; color: var(--text-muted); margin-bottom: 16px; }
        .empty-state h3 { font-size: 20px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .empty-state p { color: var(--text-mid); }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="page-wrap">
        <div class="page-hero">
            <div style="position: relative; z-index: 2;">
                <h1>Campus Drives</h1>
                <p>Participate in recruitment drives for jobs you've applied to.</p>
            </div>
            <i class="fas fa-robot" style="font-size: 64px; opacity: 0.2;"></i>
        </div>

        <?php if (empty($drives)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No Campus Drives Available</h3>
                <p>You haven't been assigned to any campus recruitment drives yet. Once you apply to jobs, any associated assessment drives will appear here.</p>
            </div>
        <?php else: ?>
            <div class="drives-grid">
                <?php foreach ($drives as $drive): 
                    $companyInitial = strtoupper(substr($drive['company_name'] ?: 'C', 0, 1));
                    $deadlinePassed = $drive['deadline'] && strtotime($drive['deadline']) < time();
                ?>
                    <a href="student_drive.php?drive_id=<?php echo $drive['id']; ?>" class="drive-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div class="company-logo">
                                <?php if ($drive['logo_url']): ?>
                                    <img src="<?php echo htmlspecialchars($drive['logo_url']); ?>" alt="Logo">
                                <?php else: ?>
                                    <?php echo $companyInitial; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($deadlinePassed): ?>
                                <span style="background:#fee2e2; color:#ef4444; padding:4px 10px; border-radius:8px; font-size:11px; font-weight:700;">Closed</span>
                            <?php else: ?>
                                <span style="background:#dcfce7; color:#15803d; padding:4px 10px; border-radius:8px; font-size:11px; font-weight:700;">Active</span>
                            <?php endif; ?>
                        </div>

                        <div class="drive-title"><?php echo htmlspecialchars($drive['drive_name']); ?></div>
                        <div class="job-title"><?php echo htmlspecialchars($drive['company_name']) . ' - ' . htmlspecialchars($drive['job_title']); ?></div>

                        <div class="drive-meta">
                            <?php if ($drive['deadline']): ?>
                                <div class="meta-item"><i class="far fa-clock"></i> Deadline: <?php echo date('M d, Y h:i A', strtotime($drive['deadline'])); ?></div>
                            <?php endif; ?>
                            <div class="meta-item"><i class="fas fa-graduation-cap"></i> Batch: <?php echo htmlspecialchars($drive['academic_year']); ?></div>
                        </div>

                        <div class="rounds-info">
                            <?php if ($drive['aptitude_active']): ?>
                                <div class="round-badge">Aptitude</div>
                            <?php endif; ?>
                            <?php if ($drive['technical_active']): ?>
                                <div class="round-badge">Technical</div>
                            <?php endif; ?>
                            <?php if ($drive['hr_active']): ?>
                                <div class="round-badge">HR</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
