<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

$db = getDB();

$driveId = isset($_GET['drive_id']) ? (int)$_GET['drive_id'] : 0;
if (!$driveId) {
    die("Invalid Drive ID specified.");
}

// Fetch drive configuration details
$stmt = $db->prepare("
    SELECT cd.*, jp.title as job_title, jp.id as job_id, c.name as company_name 
    FROM campus_drives cd
    JOIN job_postings jp ON cd.job_id = jp.id
    LEFT JOIN companies c ON jp.company_id = c.id
    WHERE cd.id = ?
");
$stmt->execute([$driveId]);
$drive = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$drive) {
    die("Recruitment drive not found.");
}

// Fetch all students who applied for the job
$stmt = $db->prepare("
    SELECT ja.student_id as usn, att.status as attendance_status
    FROM job_applications ja
    LEFT JOIN job_attendance att ON att.job_id = ja.job_id AND att.student_id = ja.student_id
    WHERE ja.job_id = ?
");
$stmt->execute([$drive['job_id']]);
$rawApplicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$studentModel = new StudentProfile();
$applicants = [];

foreach ($rawApplicants as $app) {
    $usn = $app['usn'];
    $profile = $studentModel->getByUserId($usn);
    
    if ($profile) {
        $app['name'] = $profile['name'] ?? 'Unknown Student';
        $app['branch'] = $profile['department'] ?? 'N/A';
        $app['academic_year'] = $profile['year_of_study'] ?? 'N/A';
        
        // Fetch current semester
        $app['sem'] = '?';
        $inst = $profile['institution'];
        $prefix = ($inst === INSTITUTION_GMU) ? DB_GMU_PREFIX : DB_GMIT_PREFIX;
        try {
            if ($inst === INSTITUTION_GMU) {
                $remoteDB = getDB('gmu');
                $stmtSem = $remoteDB->prepare("SELECT sem FROM {$prefix}ad_student_approved WHERE usn = ? ORDER BY academic_year DESC, sem DESC LIMIT 1");
                $stmtSem->execute([$usn]);
                $semRow = $stmtSem->fetch();
                $app['sem'] = $semRow ? $semRow['sem'] : '?';
            } else {
                $stmtCurr = $db->prepare("SELECT semester FROM student_sem_sgpa WHERE student_id = ? AND institution = ? AND is_current = 1 LIMIT 1");
                $stmtCurr->execute([$usn, INSTITUTION_GMIT]);
                $currSemRow = $stmtCurr->fetch();
                $app['sem'] = $currSemRow ? $currSemRow['semester'] : '?';
            }
        } catch (Exception $e) {}
        
        $applicants[] = $app;
    }
}

// Sort alphabetically by name
usort($applicants, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

// Fetch all attempts for this drive
$stmt = $db->prepare("
    SELECT * FROM student_drive_attempts 
    WHERE drive_id = ? 
    ORDER BY score DESC, started_at DESC
");
$stmt->execute([$driveId]);
$attemptsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group attempts by student USN and round type
$studentScores = [];
foreach ($attemptsList as $att) {
    $usn = $att['student_id'];
    $round = $att['round_type'];
    
    // Extract auto_submit_reason if present before unsetting details
    $attDetails = json_decode($att['details'] ?? '{}', true);
    $att['auto_submit_reason'] = $attDetails['auto_submit_reason'] ?? null;

    // Strip massive JSON payloads to keep HTML clean and fast
    unset($att['details'], $att['feedback'], $att['ai_analysis']);
    
    if (!isset($studentScores[$usn])) {
        $studentScores[$usn] = [
            'Aptitude' => ['best' => null, 'count' => 0, 'all' => []],
            'Technical' => ['best' => null, 'count' => 0, 'all' => []],
            'HR' => ['best' => null, 'count' => 0, 'all' => []]
        ];
    }
    
    $studentScores[$usn][$round]['count']++;
    $studentScores[$usn][$round]['all'][] = $att;
    if ($studentScores[$usn][$round]['best'] === null || $att['score'] > $studentScores[$usn][$round]['best']) {
        $studentScores[$usn][$round]['best'] = $att['score'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive Results & Preparedness | Placement Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #7C0000;
            --brand-dark: #4A0000;
            --brand-light: #F9F1F1;
            --gold: #C9972C;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --bg-light: #f3f4f6;
            --border-color: #e5e7eb;
            --ease-out: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            margin: 0;
            padding: 40px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: #fff;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #f3f4f6;
            transform: translateX(-2px);
        }

        .drive-details-header {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .company-name {
            display: inline-block;
            padding: 4px 8px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .drive-name-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--brand-dark);
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
        }

        .drive-subinfo {
            display: flex;
            gap: 24px;
            font-size: 14px;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .drive-subinfo span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .drive-subinfo strong {
            color: var(--text-dark);
        }

        /* Search Filter Section */
        .controls-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .search-wrapper {
            position: relative;
            max-width: 350px;
            width: 100%;
        }

        .search-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 15px;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px 12px 42px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #fff;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            transition: all 0.3s;
        }

        .search-input:focus {
            border-color: var(--brand);
            box-shadow: 0 4px 12px rgba(124, 0, 0, 0.08);
        }

        .filter-select {
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #fff;
            font-size: 14px;
            outline: none;
            font-family: inherit;
            cursor: pointer;
        }

        /* Table design */
        .table-container {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            margin-bottom: 40px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .details-table th {
            background: #fafafa;
            padding: 18px 20px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        .details-table td {
            padding: 18px 20px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .details-table tr:last-child td {
            border-bottom: none;
        }

        .std-name {
            font-weight: 700;
            color: var(--text-dark);
            margin: 0 0 3px 0;
        }

        .std-usn {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--text-muted);
        }

        .attendance-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .attendance-badge.present {
            background: #e8fbee;
            color: #166534;
        }

        .attendance-badge.absent {
            background: #fef2f2;
            color: #991b1b;
        }

        .attendance-badge.not-taken {
            background: #f3f4f6;
            color: var(--text-muted);
            font-style: italic;
        }

        .score-pill {
            display: inline-flex;
            flex-direction: column;
            background: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 10px;
            padding: 8px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 90px;
        }

        .score-pill:hover {
            border-color: var(--brand);
            background: var(--brand-light);
        }

        .score-val {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .score-val.no-score {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 13px;
        }

        .score-count {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .prep-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
        }

        .prep-badge.highly {
            background: #dcfce7;
            color: #15803d;
        }

        .prep-badge.moderate {
            background: #fef9c3;
            color: #a16207;
        }

        .prep-badge.needs {
            background: #fee2e2;
            color: #b91c1c;
        }

        .prep-badge.not-started {
            background: #f3f4f6;
            color: var(--text-muted);
            font-style: italic;
        }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: #fff;
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            position: relative;
            box-sizing: border-box;
            animation: modalFadeIn 0.3s var(--ease-out);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: var(--brand-dark);
            font-weight: 800;
        }

        .close-btn {
            font-size: 24px;
            color: var(--text-muted);
            cursor: pointer;
            background: none;
            border: none;
        }

        .close-btn:hover {
            color: var(--brand);
        }

        .attempts-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 300px;
            overflow-y: auto;
        }

        .attempt-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 10px;
            font-size: 13px;
        }

        .attempt-item strong {
            font-size: 14px;
            color: var(--brand);
        }

        .attempt-date {
            color: var(--text-muted);
            font-size: 11px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <?php include_once 'includes/navbar.php'; ?>

    <div class="header-container">
        <a href="campus_drives.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Drives
        </a>
    </div>

    <div class="drive-details-header">
        <span class="company-name"><?php echo htmlspecialchars($drive['company_name']); ?></span>
        <h1 class="drive-name-title"><?php echo htmlspecialchars($drive['drive_name']); ?></h1>
        
        <div class="drive-subinfo">
            <span><i class="fas fa-calendar-check"></i> Academic Year: <strong><?php echo htmlspecialchars($drive['academic_year']); ?></strong></span>
            <span><i class="fas fa-briefcase"></i> Job Title: <strong><?php echo htmlspecialchars($drive['job_title']); ?></strong></span>
            <?php if ($drive['deadline']): ?>
            <span><i class="fas fa-hourglass-half"></i> Deadline: <strong><?php echo date('M d, Y h:i A', strtotime($drive['deadline'])); ?></strong></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="controls-row">
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="studentSearch" class="search-input" placeholder="Search by student name or USN..." onkeyup="filterStudents()">
        </div>
        
        <select id="branchFilter" class="filter-select" onchange="filterStudents()">
            <option value="">-- All Branches --</option>
            <?php 
            $branches = array_unique(array_column($applicants, 'branch'));
            foreach ($branches as $branch): if ($branch):
            ?>
            <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
            <?php endif; endforeach; ?>
        </select>

        <select id="attendanceFilter" class="filter-select" onchange="filterStudents()">
            <option value="">-- All Attendance --</option>
            <option value="Present">Present</option>
            <option value="Absent">Absent</option>
            <option value="Not Taken">Attendance Not Taken</option>
        </select>

        <select id="prepFilter" class="filter-select" onchange="filterStudents()">
            <option value="">-- All Preparedness --</option>
            <option value="Highly">Highly Prepared (>=80%)</option>
            <option value="Moderate">Moderately Prepared (60%-79%)</option>
            <option value="Needs">Needs Improvement (<60%)</option>
            <option value="Not Started">Not Started</option>
        </select>
    </div>

    <div class="table-container">
        <table class="details-table">
            <thead>
                <tr>
                    <th>Student Details</th>
                    <th>Attendance</th>
                    <?php if ($drive['aptitude_active']): ?><th>Aptitude Best</th><?php endif; ?>
                    <?php if ($drive['technical_active']): ?><th>Technical Best</th><?php endif; ?>
                    <?php if ($drive['hr_active']): ?><th>HR Best</th><?php endif; ?>
                    <th>Preparedness</th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php 
                foreach ($applicants as $app): 
                    $usn = $app['usn'];
                    
                    // Calculate preparedness score based on active rounds
                    $activeRoundsCount = 0;
                    $totalScoreSum = 0;
                    
                    if ($drive['aptitude_active']) {
                        $activeRoundsCount++;
                        $totalScoreSum += $studentScores[$usn]['Aptitude']['best'] ?? 0;
                    }
                    if ($drive['technical_active']) {
                        $activeRoundsCount++;
                        $totalScoreSum += $studentScores[$usn]['Technical']['best'] ?? 0;
                    }
                    if ($drive['hr_active']) {
                        $activeRoundsCount++;
                        $totalScoreSum += $studentScores[$usn]['HR']['best'] ?? 0;
                    }
                    
                    $hasAttemptedAny = ($drive['aptitude_active'] && isset($studentScores[$usn]['Aptitude']['best']))
                                    || ($drive['technical_active'] && isset($studentScores[$usn]['Technical']['best']))
                                    || ($drive['hr_active'] && isset($studentScores[$usn]['HR']['best']));
                                    
                    $preparedness = 0;
                    if ($hasAttemptedAny && $activeRoundsCount > 0) {
                        $preparedness = round($totalScoreSum / $activeRoundsCount, 1);
                    }
                ?>
                <tr class="student-row" 
                    data-branch="<?php echo htmlspecialchars($app['branch'] ?? ''); ?>" 
                    data-attendance="<?php echo htmlspecialchars($app['attendance_status'] ?? 'Not Taken'); ?>"
                    data-prep="<?php echo !$hasAttemptedAny ? 'Not Started' : ($preparedness >= 80 ? 'Highly' : ($preparedness >= 60 ? 'Moderate' : 'Needs')); ?>">
                    <td>
                        <h4 class="std-name"><?php echo htmlspecialchars($app['name']); ?></h4>
                        <span class="std-usn"><?php echo htmlspecialchars($usn); ?></span>
                        <span style="font-size: 12px; color: var(--text-muted); margin-left: 10px;"><?php echo htmlspecialchars($app['branch'] ?? 'N/A'); ?> | Sem <?php echo htmlspecialchars($app['sem'] ?? 'N/A'); ?></span>
                    </td>
                    <td>
                        <?php if ($app['attendance_status'] === 'Present'): ?>
                            <span class="attendance-badge present"><i class="fas fa-check"></i> Present</span>
                        <?php elseif ($app['attendance_status'] === 'Absent'): ?>
                            <span class="attendance-badge absent"><i class="fas fa-times"></i> Absent</span>
                        <?php else: ?>
                            <span class="attendance-badge not-taken"><i class="fas fa-clock"></i> Not Taken</span>
                        <?php endif; ?>
                    </td>

                    <!-- APTITUDE ROUND SCORE -->
                    <?php if ($drive['aptitude_active']): 
                        $aptInfo = $studentScores[$usn]['Aptitude'] ?? null;
                        $aptBest = $aptInfo ? $aptInfo['best'] : null;
                        $aptCount = $aptInfo ? $aptInfo['count'] : 0;
                    ?>
                    <td>
                        <?php if ($aptBest !== null): ?>
                            <div class="score-pill" onclick='showAttemptsModal(<?php echo json_encode($app['name']); ?>, "Aptitude", <?php echo json_encode($aptInfo['all']); ?>)'>
                                <span class="score-val"><?php echo number_format($aptBest, 1); ?>%</span>
                                <span class="score-count"><?php echo $aptCount; ?> <?php echo $aptCount > 1 ? 'attempts' : 'attempt'; ?></span>
                            </div>
                        <?php else: ?>
                            <div class="score-pill" style="cursor: default; border-style: dashed;">
                                <span class="score-val no-score">N/A</span>
                                <span class="score-count">No attempt</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>

                    <!-- TECHNICAL ROUND SCORE -->
                    <?php if ($drive['technical_active']): 
                        $techInfo = $studentScores[$usn]['Technical'] ?? null;
                        $techBest = $techInfo ? $techInfo['best'] : null;
                        $techCount = $techInfo ? $techInfo['count'] : 0;
                    ?>
                    <td>
                        <?php if ($techBest !== null): ?>
                            <div class="score-pill" onclick='showAttemptsModal(<?php echo json_encode($app['name']); ?>, "Technical", <?php echo json_encode($techInfo['all']); ?>)'>
                                <span class="score-val"><?php echo number_format($techBest, 1); ?>%</span>
                                <span class="score-count"><?php echo $techCount; ?> <?php echo $techCount > 1 ? 'attempts' : 'attempt'; ?></span>
                            </div>
                        <?php else: ?>
                            <div class="score-pill" style="cursor: default; border-style: dashed;">
                                <span class="score-val no-score">N/A</span>
                                <span class="score-count">No attempt</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>

                    <!-- HR ROUND SCORE -->
                    <?php if ($drive['hr_active']): 
                        $hrInfo = $studentScores[$usn]['HR'] ?? null;
                        $hrBest = $hrInfo ? $hrInfo['best'] : null;
                        $hrCount = $hrInfo ? $hrInfo['count'] : 0;
                    ?>
                    <td>
                        <?php if ($hrBest !== null): ?>
                            <div class="score-pill" onclick='showAttemptsModal(<?php echo json_encode($app['name']); ?>, "HR", <?php echo json_encode($hrInfo['all']); ?>)'>
                                <span class="score-val"><?php echo number_format($hrBest, 1); ?>%</span>
                                <span class="score-count"><?php echo $hrCount; ?> <?php echo $hrCount > 1 ? 'attempts' : 'attempt'; ?></span>
                            </div>
                        <?php else: ?>
                            <div class="score-pill" style="cursor: default; border-style: dashed;">
                                <span class="score-val no-score">N/A</span>
                                <span class="score-count">No attempt</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>

                    <!-- PREPAREDNESS GAUGE -->
                    <td>
                        <?php if ($hasAttemptedAny): ?>
                            <span class="prep-badge <?php echo $preparedness >= 80 ? 'highly' : ($preparedness >= 60 ? 'moderate' : 'needs'); ?>">
                                <i class="fas <?php echo $preparedness >= 80 ? 'fa-circle-check' : ($preparedness >= 60 ? 'fa-triangle-exclamation' : 'fa-circle-exclamation'); ?>"></i>
                                <strong><?php echo $preparedness; ?>%</strong> 
                                (<?php echo $preparedness >= 80 ? 'Highly' : ($preparedness >= 60 ? 'Moderate' : 'Needs Focus'); ?>)
                            </span>
                        <?php else: ?>
                            <span class="prep-badge not-started">
                                <i class="fas fa-circle-minus"></i> Not Started
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; if (empty($applicants)): ?>
                <tr>
                    <td colspan="7" class="no-data">
                        <i class="fas fa-users-slash" style="font-size: 36px; color: var(--border-color); display: block; margin-bottom: 10px;"></i>
                        No students have applied for this job posting yet.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ATTEMPTS LIST MODAL -->
    <div id="attemptsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Student Round Attempts</h3>
                <button class="close-btn" onclick="closeAttemptsModal()">&times;</button>
            </div>
            <div id="modalBody" class="attempts-list">
                <!-- Populated via Javascript -->
            </div>
        </div>
    </div>

    <script>
        function filterStudents() {
            const query = document.getElementById('studentSearch').value.toLowerCase();
            const branch = document.getElementById('branchFilter').value;
            const attendance = document.getElementById('attendanceFilter').value;
            const prep = document.getElementById('prepFilter').value;
            
            const rows = document.querySelectorAll('.student-row');
            
            rows.forEach(row => {
                const name = row.querySelector('.std-name').textContent.toLowerCase();
                const usn = row.querySelector('.std-usn').textContent.toLowerCase();
                
                const rowBranch = row.getAttribute('data-branch');
                const rowAttendance = row.getAttribute('data-attendance');
                const rowPrep = row.getAttribute('data-prep');
                
                const matchesSearch = name.includes(query) || usn.includes(query);
                const matchesBranch = !branch || rowBranch === branch;
                const matchesAttendance = !attendance || rowAttendance === attendance;
                const matchesPrep = !prep || rowPrep === prep;
                
                if (matchesSearch && matchesBranch && matchesAttendance && matchesPrep) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Attempts Modal Functions
        function showAttemptsModal(studentName, roundName, attempts) {
            document.getElementById('modalTitle').innerHTML = studentName + ' - ' + roundName + ' Attempts';
            
            let html = '';
            if (!attempts || attempts.length === 0) {
                html = '<div class="no-data">No attempts found.</div>';
            } else {
                // Sort attempts by attempt_number desc
                attempts.sort((a, b) => b.attempt_number - a.attempt_number);
                
                attempts.forEach(att => {
                    const date = new Date(att.completed_at || att.started_at);
                    const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    let autoSubmitWarning = '';
                    if (att.auto_submit_reason) {
                        autoSubmitWarning = `<div style="color: #dc2626; font-size: 12px; margin-top: 4px; font-weight: 600;"><i class="fas fa-triangle-exclamation"></i> Auto-Submitted: ${att.auto_submit_reason}</div>`;
                    }
                    
                    html += `
                        <div class="attempt-item">
                            <div>
                                <strong>Attempt #${att.attempt_number}</strong>
                                <div class="attempt-date"><i class="fas fa-clock"></i> Completed: ${formattedDate}</div>
                                ${autoSubmitWarning}
                            </div>
                            <strong style="font-size: 16px; color: ${parseFloat(att.score) >= 80 ? '#15803d' : (parseFloat(att.score) >= 60 ? '#a16207' : '#b91c1c')}">${parseFloat(att.score).toFixed(1)}%</strong>
                        </div>
                    `;
                });
            }
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('attemptsModal').style.display = 'flex';
        }

        function closeAttemptsModal() {
            document.getElementById('attemptsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('attemptsModal');
            if (event.target == modal) {
                closeAttemptsModal();
            }
        }
    </script>
</body>
</html>
