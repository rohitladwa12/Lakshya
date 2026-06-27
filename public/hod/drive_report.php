<?php
/**
 * HOD Campus Drive Detailed Report
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../../src/Models/StudentProfile.php';

$driveId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$driveId) {
    die("Invalid Drive ID.");
}

$fullName = getFullName();
$department = getDepartment() ?: 'CSE';
$discipline_filters = getCoordinatorDisciplineFilters($department);

$studentModel = new StudentProfile();
$semRange = getCoordinatorSemesterFilters($department);
$overallFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semRange
];
$students = $studentModel->getAllWithUsers($overallFilters);
$usns = array_column($students, 'usn');

if (empty($usns)) {
    die("No students found in your department.");
}

$db = getDB();

// Fetch drive info
$stmt = $db->prepare("
    SELECT 
        cd.*,
        jp.title as job_title,
        jp.id as job_id,
        c.name as company_name
    FROM campus_drives cd
    JOIN job_postings jp ON cd.job_id = jp.id
    LEFT JOIN companies c ON jp.company_id = c.id
    WHERE cd.id = ?
");
$stmt->execute([$driveId]);
$drive = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$drive) {
    die("Drive not found.");
}

// Fetch student performance for this drive (only for students in this HOD's department who applied)
$usnList = "'" . implode("','", array_map('addslashes', $usns)) . "'";

// Build USN map for names
$studentsByUsn = [];
foreach ($students as $s) {
    $usnKey = strtoupper(trim($s['usn']));
    $studentsByUsn[$usnKey] = $s['name'] ?? $s['usn'];
}

// We get everyone who applied from job_applications directly
$query = "
    SELECT 
        ja.student_id as usn,
        (SELECT MAX(score) FROM student_drive_attempts WHERE student_id = ja.student_id AND drive_id = ? AND round_type = 'Aptitude') as apt_score,
        (SELECT MAX(score) FROM student_drive_attempts WHERE student_id = ja.student_id AND drive_id = ? AND round_type = 'Technical') as tech_score,
        (SELECT MAX(score) FROM student_drive_attempts WHERE student_id = ja.student_id AND drive_id = ? AND round_type = 'HR') as hr_score,
        (SELECT COUNT(*) FROM student_drive_attempts WHERE student_id = ja.student_id AND drive_id = ?) as total_attempts
    FROM job_applications ja
    WHERE ja.job_id = ? AND ja.student_id IN ($usnList)
";
$stmtStudents = $db->prepare($query);
$stmtStudents->execute([$driveId, $driveId, $driveId, $driveId, $drive['job_id']]);
$appliedStudentsRaw = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

$appliedStudents = [];
foreach ($appliedStudentsRaw as $row) {
    $usnKey = strtoupper(trim($row['usn']));
    $row['name'] = $studentsByUsn[$usnKey] ?? $row['usn'];
    $appliedStudents[] = $row;
}

// Sort alphabetically by name
usort($appliedStudents, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive Report - <?php echo htmlspecialchars($drive['company_name']); ?></title>
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
        
        .main-content {
            padding: 40px 50px;
            max-width: 1400px;
            margin: 0 auto;
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
            margin-bottom: 25px;
        }

        .back-btn:hover {
            background-color: var(--bg-light);
            transform: translateX(-4px);
        }

        .drive-header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary-maroon);
            margin-bottom: 30px;
        }

        .drive-header h2 {
            font-size: 28px;
            color: var(--text-dark);
            font-weight: 800;
            margin-bottom: 8px;
        }

        .drive-meta {
            display: flex;
            gap: 20px;
            color: var(--text-muted);
            font-size: 15px;
        }

        .section-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 30px;
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
            background: #f8fafc;
        }
        
        td {
            padding: 16px 20px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
        }
        
        tr:last-child td { border-bottom: none; }

        .score-pill {
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            display: inline-block;
            background: #f1f5f9;
        }
        
        .score-pill.has-score { background: rgba(128,0,0,0.08); color: var(--primary-maroon); }
        .score-pill.no-score { background: #f1f5f9; color: var(--text-muted); }

        .status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-present { background: #f0fdf4; color: #166534; }
        .status-absent { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="main-content">
        <a href="campus_drives.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Campus Drives
        </a>

        <div class="drive-header">
            <h2><?php echo htmlspecialchars($drive['drive_name']); ?></h2>
            <div class="drive-meta">
                <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($drive['company_name']); ?></span>
                <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($drive['job_title']); ?></span>
                <span><i class="fas fa-users"></i> Applied Students: <strong><?php echo count($appliedStudents); ?></strong></span>
            </div>
        </div>

        <div class="section-card">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Student USN</th>
                            <th>Student Name</th>
                            <th>Attendance</th>
                            <th style="text-align:center;">Aptitude Score</th>
                            <th style="text-align:center;">Technical Score</th>
                            <th style="text-align:center;">HR Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appliedStudents)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No students from your department applied for this drive.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appliedStudents as $student): 
                                $isPresent = $student['total_attempts'] > 0;
                            ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($student['usn']); ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($student['name']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($isPresent): ?>
                                            <span class="status-badge status-present"><i class="fas fa-check-circle"></i> Present</span>
                                        <?php else: ?>
                                            <span class="status-badge status-absent"><i class="fas fa-times-circle"></i> Absent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($drive['aptitude_active']): ?>
                                            <span class="score-pill <?php echo $student['apt_score'] !== null ? 'has-score' : 'no-score'; ?>">
                                                <?php echo $student['apt_score'] !== null ? number_format($student['apt_score'], 1).'%' : 'N/A'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#cbd5e1; font-size: 12px; font-weight:600; text-transform:uppercase;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($drive['technical_active']): ?>
                                            <span class="score-pill <?php echo $student['tech_score'] !== null ? 'has-score' : 'no-score'; ?>">
                                                <?php echo $student['tech_score'] !== null ? number_format($student['tech_score'], 1).'%' : 'N/A'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#cbd5e1; font-size: 12px; font-weight:600; text-transform:uppercase;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($drive['hr_active']): ?>
                                            <span class="score-pill <?php echo $student['hr_score'] !== null ? 'has-score' : 'no-score'; ?>">
                                                <?php echo $student['hr_score'] !== null ? number_format($student['hr_score'], 1).'%' : 'N/A'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#cbd5e1; font-size: 12px; font-weight:600; text-transform:uppercase;">Disabled</span>
                                        <?php endif; ?>
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
