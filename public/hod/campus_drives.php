<?php
/**
 * HOD Campus Drives Page
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

$fullName = getFullName();
$department = getDepartment() ?: 'CSE';
$discipline_filters = getCoordinatorDisciplineFilters($department);
$deptGmu = $discipline_filters[0] ?? $department;
$deptGmit = $discipline_filters[1] ?? $department;
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;

$studentModel = new StudentProfile();
$semRange = getCoordinatorSemesterFilters($department);
$overallFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semRange
];

$students = $studentModel->getAllWithUsers($overallFilters);
$usns = array_column($students, 'usn');

$db = getDB();

// Fetch Campus Drives Summary for the Department
$drivesSummary = [];
try {
    $stmt = $db->query("
        SELECT 
            cd.id as drive_id,
            cd.drive_name,
            jp.title as job_title,
            jp.id as job_id,
            c.name as company_name,
            cd.aptitude_active,
            cd.technical_active,
            cd.hr_active,
            cd.deadline
        FROM campus_drives cd
        JOIN job_postings jp ON cd.job_id = jp.id
        LEFT JOIN companies c ON jp.company_id = c.id
        ORDER BY cd.created_at DESC
    ");
    $allDrives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($usns)) {
        $usnList = "'" . implode("','", array_map('addslashes', $usns)) . "'";
        foreach ($allDrives as $drive) {
            // Applied
            $stmtApps = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM job_applications WHERE job_id = ? AND student_id IN ($usnList)");
            $stmtApps->execute([$drive['job_id']]);
            $applied = (int)$stmtApps->fetchColumn();
            
            // Present
            $stmtAtt = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM student_drive_attempts WHERE drive_id = ? AND student_id IN ($usnList)");
            $stmtAtt->execute([$drive['drive_id']]);
            $present = (int)$stmtAtt->fetchColumn();
            
            // Scores (Average of max score per student per round)
            $scores = ['Aptitude' => null, 'Technical' => null, 'HR' => null];
            foreach (['Aptitude', 'Technical', 'HR'] as $round) {
                $stmtScore = $db->prepare("
                    SELECT AVG(max_score) FROM (
                        SELECT MAX(score) as max_score 
                        FROM student_drive_attempts 
                        WHERE drive_id = ? AND round_type = ? AND student_id IN ($usnList)
                        GROUP BY student_id
                    ) as sub
                ");
                $stmtScore->execute([$drive['drive_id'], $round]);
                $avg = $stmtScore->fetchColumn();
                if ($avg !== null) {
                    $scores[$round] = round($avg, 1);
                }
            }
            
            $drivesSummary[] = [
                'drive_id' => $drive['drive_id'],
                'drive_name' => $drive['drive_name'],
                'job_title' => $drive['job_title'],
                'company_name' => $drive['company_name'],
                'deadline' => $drive['deadline'],
                'applied' => $applied,
                'present' => $present,
                'aptitude_avg' => $scores['Aptitude'],
                'technical_avg' => $scores['Technical'],
                'hr_avg' => $scores['HR'],
                'apt_active' => $drive['aptitude_active'],
                'tech_active' => $drive['technical_active'],
                'hr_active' => $drive['hr_active']
            ];
        }
    }
} catch (Exception $e) {
    error_log("Failed to fetch drives summary: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Drives - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 20px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        .navbar-spacer { height: 80px; }
        
        .main-content {
            padding: 40px 50px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h2 {
            font-size: 32px;
            color: var(--primary-maroon);
            font-weight: 800;
            margin-bottom: 4px;
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 15px;
        }

        .back-btn {
            background-color: white;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .back-btn:hover {
            background-color: var(--bg-light);
            transform: translateX(-4px);
        }

        .section-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .section-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-card h3 i {
            color: var(--primary-maroon);
        }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        
        th {
            padding: 16px 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 18px 20px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
            vertical-align: middle;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .coord-name-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .coord-name {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 15px;
        }
        
        .coord-email {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .stat-count-pill {
            background-color: #f1f5f9;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 800;
            color: var(--primary-maroon);
            display: inline-block;
            font-size: 15px;
        }

        .clickable-row {
            transition: var(--transition);
        }
        .clickable-row:hover {
            background-color: rgba(128, 0, 0, 0.02);
        }
        
        .badge-type {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="page-header">
            <div>
                <h2>Campus Drives Performance</h2>
                <p><?php echo htmlspecialchars($deptLabel); ?> • Drive specific application and score metrics</p>
            </div>
        </div>

        <div class="section-card">
            <h3><i class="fas fa-building"></i> Company Assessments Overview</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%">Drive Info</th>
                            <th style="text-align: center;">Students Applied</th>
                            <th style="text-align: center;">Students Present</th>
                            <th style="text-align: center;">Aptitude Avg</th>
                            <th style="text-align: center;">Technical Avg</th>
                            <th style="text-align: center;">HR Avg</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($drivesSummary)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); font-weight: 500; padding: 40px;">
                                    <div style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"><i class="fas fa-folder-open"></i></div>
                                    No campus drives found for your department students.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($drivesSummary as $ds): ?>
                                <tr class="clickable-row">
                                    <td>
                                        <div class="coord-name-wrap">
                                            <div class="coord-name"><?php echo htmlspecialchars($ds['drive_name']); ?></div>
                                            <div class="coord-email"><?php echo htmlspecialchars($ds['company_name']); ?> • <?php echo htmlspecialchars($ds['job_title']); ?></div>
                                            <?php if ($ds['deadline']): ?>
                                                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;"><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($ds['deadline'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="stat-count-pill" style="background-color:#e0f2fe; color:#0369a1;"><?php echo $ds['applied']; ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="stat-count-pill" style="background-color:#f0fdf4; color:#166534;"><?php echo $ds['present']; ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($ds['apt_active']): ?>
                                            <span style="font-weight: 800; font-size: 15px; color: <?php echo $ds['aptitude_avg'] !== null ? 'var(--primary-maroon)' : 'var(--text-muted)'; ?>;">
                                                <?php echo $ds['aptitude_avg'] !== null ? $ds['aptitude_avg'] . '%' : '—'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-type" style="background: #f1f5f9; color: #94a3b8;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($ds['tech_active']): ?>
                                            <span style="font-weight: 800; font-size: 15px; color: <?php echo $ds['technical_avg'] !== null ? 'var(--primary-maroon)' : 'var(--text-muted)'; ?>;">
                                                <?php echo $ds['technical_avg'] !== null ? $ds['technical_avg'] . '%' : '—'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-type" style="background: #f1f5f9; color: #94a3b8;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($ds['hr_active']): ?>
                                            <span style="font-weight: 800; font-size: 15px; color: <?php echo $ds['hr_avg'] !== null ? 'var(--primary-maroon)' : 'var(--text-muted)'; ?>;">
                                                <?php echo $ds['hr_avg'] !== null ? $ds['hr_avg'] . '%' : '—'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-type" style="background: #f1f5f9; color: #94a3b8;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="drive_report.php?id=<?php echo $ds['drive_id']; ?>" style="background: var(--bg-light); color: var(--primary-maroon); border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition);" onmouseover="this.style.background='rgba(128,0,0,0.05)'; this.style.borderColor='var(--primary-maroon)';" onmouseout="this.style.background='var(--bg-light)'; this.style.borderColor='var(--border-color)';">
                                            <i class="fas fa-eye"></i> View Report
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
