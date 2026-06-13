<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_STUDENT);

$db = getDB();
$userId = getUserId();
$usn = getUsername(); // USN of student

$driveId = isset($_GET['drive_id']) ? (int)$_GET['drive_id'] : 0;
if (!$driveId) {
    die("Invalid Campus Drive ID.");
}

// Fetch drive details
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

// Enforce: only applied students can access this drive
$stmt = $db->prepare("
    SELECT COUNT(*) FROM job_applications 
    WHERE job_id = ? AND student_id = ?
");
$stmt->execute([$drive['job_id'], $usn]);
$hasApplied = $stmt->fetchColumn() > 0;

if (!$hasApplied) {
    die("Access denied. Only students who have applied for this job posting can access this recruitment drive.");
}

// Fetch student details snapshot
try {
    $stmt = $db->prepare("
        SELECT ads.*, u.NAME as name, u.DISCIPLINE as branch 
        FROM ad_student_approved ads
        JOIN users u ON ads.usn = u.ID
        WHERE ads.usn = ?
    ");
    $stmt->execute([$usn]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $student = false;
}

if (!$student) {
    // Check local or remote backup
    $stmt = $db->prepare("
        SELECT u.ID as usn, u.NAME as name, u.DISCIPLINE as branch,
               'N/A' as sem, 'N/A' as year 
        FROM users u 
        WHERE u.ID = ?
    ");
    $stmt->execute([$usn]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch previous attempts
$stmt = $db->prepare("
    SELECT * FROM student_drive_attempts 
    WHERE drive_id = ? AND student_id = ? 
    ORDER BY round_type, attempt_number DESC
");
$stmt->execute([$driveId, $usn]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roundAttempts = [
    'Aptitude' => [],
    'Technical' => [],
    'HR' => []
];
foreach ($attempts as $att) {
    if (!isset($roundAttempts[$att['round_type']][$att['attempt_number']])) {
        $roundAttempts[$att['round_type']][$att['attempt_number']] = $att;
    }
}

$deadlineTime = $drive['deadline'] ? strtotime($drive['deadline']) : null;
$isClosed = $deadlineTime && ($deadlineTime < time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($drive['drive_name']); ?> | Placement Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --medium-gray: #e0e0e0;
            --dark-gray: #333333;
            --success-color: #2e7d32;
            --warning-color: #d84315;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light-gray);
            margin: 0;
            padding: 40px;
            color: var(--dark-gray);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: #fff;
            color: var(--dark-gray);
            border: 1px solid var(--medium-gray);
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 25px;
        }

        .btn-back:hover {
            background: #f1f1f1;
            transform: translateX(-2px);
        }

        .drive-header {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-maroon);
        }

        .company-label {
            display: inline-block;
            padding: 5px 10px;
            background: #ffebee;
            color: var(--primary-maroon);
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .drive-title {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 10px 0;
            color: var(--primary-dark);
        }

        .drive-info-row {
            display: flex;
            gap: 30px;
            font-size: 14px;
            color: #666;
            flex-wrap: wrap;
        }

        .drive-info-row span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .drive-info-row strong {
            color: var(--dark-gray);
        }

        .deadline-warning {
            color: var(--warning-color);
            font-weight: 700;
        }

        /* Rounds Grid */
        .rounds-section {
            margin-top: 30px;
        }

        .rounds-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--primary-dark);
        }

        .round-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--medium-gray);
            box-shadow: 0 2px 12px rgba(0,0,0,0.02);
            padding: 24px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.3s;
        }

        .round-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border-color: rgba(128, 0, 0, 0.15);
        }

        .round-card.disabled {
            background: #fafafa;
            opacity: 0.65;
            cursor: not-allowed;
        }

        .round-info {
            flex: 1;
        }

        .round-header-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .round-icon {
            font-size: 20px;
            color: var(--primary-maroon);
        }

        .round-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: var(--dark-gray);
        }

        .round-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .round-badge.enabled {
            background: #e8f5e9;
            color: var(--success-color);
        }

        .round-badge.disabled {
            background: #f5f5f5;
            color: #777;
        }

        .round-description {
            font-size: 14px;
            color: #666;
            margin: 0 0 12px 0;
            line-height: 1.5;
        }

        .round-meta-items {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #555;
        }

        .round-meta-items span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Attempts History inside Round Card */
        .attempts-box {
            margin-top: 15px;
            border-top: 1px dashed var(--medium-gray);
            padding-top: 12px;
        }

        .attempts-title {
            font-size: 12px;
            font-weight: 700;
            color: #777;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .attempts-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .attempt-tag {
            background: #f9fafb;
            border: 1px solid var(--medium-gray);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .attempt-tag strong {
            color: var(--primary-maroon);
        }

        /* Action Buttons */
        .round-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            min-width: 140px;
        }

        .btn-start {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 18px;
            background: var(--primary-maroon);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.15);
            transition: all 0.3s;
        }

        .btn-start:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(128, 0, 0, 0.25);
        }

        .btn-disabled-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 18px;
            background: #e0e0e0;
            color: #999;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>

        <div class="drive-header">
            <span class="company-label"><?php echo htmlspecialchars($drive['company_name']); ?></span>
            <h1 class="drive-title"><?php echo htmlspecialchars($drive['drive_name']); ?></h1>
            <div class="drive-info-row">
                <span><i class="fas fa-briefcase"></i> Role: <strong><?php echo htmlspecialchars($drive['job_title']); ?></strong></span>
                <span><i class="fas fa-user-graduate"></i> Academic Year: <strong><?php echo htmlspecialchars($drive['academic_year']); ?></strong></span>
                <?php if ($drive['deadline']): ?>
                <span class="<?php echo $isClosed ? 'deadline-warning' : ''; ?>">
                    <i class="fas fa-clock"></i> 
                    <?php if ($isClosed): ?>
                        Deadline Passed: <strong><?php echo date('M d, Y h:i A', strtotime($drive['deadline'])); ?></strong>
                    <?php else: ?>
                        Deadline: <strong><?php echo date('M d, Y h:i A', strtotime($drive['deadline'])); ?></strong>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounds-section">
            <h2 class="rounds-title">Assessment Rounds</h2>

            <!-- ROUND 1: APTITUDE -->
            <div class="round-card <?php echo !$drive['aptitude_active'] ? 'disabled' : ''; ?>">
                <div class="round-info">
                    <div class="round-header-row">
                        <i class="fas fa-calculator round-icon"></i>
                        <h3 class="round-name">Aptitude Round</h3>
                        <span class="round-badge <?php echo $drive['aptitude_active'] ? 'enabled' : 'disabled'; ?>">
                            <?php echo $drive['aptitude_active'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    <p class="round-description">Solve logical, quantitative, and verbal problems generated by AI to assess your reasoning speed.</p>
                    
                    <?php if ($drive['aptitude_active']): ?>
                    <div class="round-meta-items">
                        <span><i class="fas fa-list-ol"></i> Questions: <strong><?php echo $drive['aptitude_questions']; ?></strong></span>
                        <span><i class="fas fa-hourglass-half"></i> Duration: <strong><?php echo $drive['aptitude_duration']; ?> mins</strong></span>
                        <span><i class="fas fa-tags"></i> Topics: <strong><?php echo htmlspecialchars($drive['aptitude_topics']); ?></strong></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($roundAttempts['Aptitude'])): ?>
                    <div class="attempts-box">
                        <div class="attempts-title">Previous Attempts</div>
                        <div class="attempts-list">
                            <?php foreach ($roundAttempts['Aptitude'] as $att): ?>
                            <div class="attempt-tag">
                                Attempt #<?php echo $att['attempt_number']; ?>: <strong><?php echo number_format((float)($att['score'] ?? 0), 1); ?>%</strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="round-actions">
                    <?php if (!$drive['aptitude_active']): ?>
                        <span class="btn-disabled-action"><i class="fas fa-ban"></i> Disabled</span>
                    <?php elseif ($isClosed): ?>
                        <span class="btn-disabled-action"><i class="fas fa-lock"></i> Closed</span>
                    <?php else: ?>
                        <a href="student_drive_test.php?drive_id=<?php echo $driveId; ?>&round_type=Aptitude" class="btn-start">
                            <i class="fas fa-play"></i> 
                            <?php echo empty($roundAttempts['Aptitude']) ? 'Start Round' : 'Retake Round'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ROUND 2: TECHNICAL -->
            <div class="round-card <?php echo !$drive['technical_active'] ? 'disabled' : ''; ?>">
                <div class="round-info">
                    <div class="round-header-row">
                        <i class="fas fa-code round-icon"></i>
                        <h3 class="round-name">Technical Round</h3>
                        <span class="round-badge <?php echo $drive['technical_active'] ? 'enabled' : 'disabled'; ?>">
                            <?php echo $drive['technical_active'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    <p class="round-description">Technical question challenge assessing your domain competencies and foundational coding logic.</p>
                    
                    <?php if ($drive['technical_active']): ?>
                    <div class="round-meta-items">
                        <span><i class="fas fa-list-ol"></i> Questions: <strong><?php echo $drive['technical_questions']; ?></strong></span>
                        <span><i class="fas fa-hourglass-half"></i> Duration: <strong><?php echo $drive['technical_duration']; ?> mins</strong></span>
                        <span><i class="fas fa-tags"></i> Topics: <strong><?php echo htmlspecialchars($drive['technical_topics']); ?></strong></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($roundAttempts['Technical'])): ?>
                    <div class="attempts-box">
                        <div class="attempts-title">Previous Attempts</div>
                        <div class="attempts-list">
                            <?php foreach ($roundAttempts['Technical'] as $att): ?>
                            <div class="attempt-tag">
                                Attempt #<?php echo $att['attempt_number']; ?>: <strong><?php echo number_format($att['score'], 1); ?>%</strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="round-actions">
                    <?php if (!$drive['technical_active']): ?>
                        <span class="btn-disabled-action"><i class="fas fa-ban"></i> Disabled</span>
                    <?php elseif ($isClosed): ?>
                        <span class="btn-disabled-action"><i class="fas fa-lock"></i> Closed</span>
                    <?php else: ?>
                        <a href="ai_technical_round.php?drive_id=<?php echo $driveId; ?>" class="btn-start">
                            <i class="fas fa-play"></i> 
                            <?php echo empty($roundAttempts['Technical']) ? 'Start Round' : 'Retake Round'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ROUND 3: HR -->
            <div class="round-card <?php echo !$drive['hr_active'] ? 'disabled' : ''; ?>">
                <div class="round-info">
                    <div class="round-header-row">
                        <i class="fas fa-user-group round-icon"></i>
                        <h3 class="round-name">HR Round</h3>
                        <span class="round-badge <?php echo $drive['hr_active'] ? 'enabled' : 'disabled'; ?>">
                            <?php echo $drive['hr_active'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                    <p class="round-description">Situational judgment and behavioral check evaluating team alignment, communication, and fit.</p>
                    
                    <?php if ($drive['hr_active']): ?>
                    <div class="round-meta-items">
                        <span><i class="fas fa-list-ol"></i> Questions: <strong><?php echo $drive['hr_questions']; ?></strong></span>
                        <span><i class="fas fa-hourglass-half"></i> Duration: <strong><?php echo $drive['hr_duration']; ?> mins</strong></span>
                        <span><i class="fas fa-tags"></i> Topics: <strong><?php echo htmlspecialchars($drive['hr_topics']); ?></strong></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($roundAttempts['HR'])): ?>
                    <div class="attempts-box">
                        <div class="attempts-title">Previous Attempts</div>
                        <div class="attempts-list">
                            <?php foreach ($roundAttempts['HR'] as $att): ?>
                            <div class="attempt-tag">
                                Attempt #<?php echo $att['attempt_number']; ?>: <strong><?php echo number_format($att['score'], 1); ?>%</strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="round-actions">
                    <?php if (!$drive['hr_active']): ?>
                        <span class="btn-disabled-action"><i class="fas fa-ban"></i> Disabled</span>
                    <?php elseif ($isClosed): ?>
                        <span class="btn-disabled-action"><i class="fas fa-lock"></i> Closed</span>
                    <?php else: ?>
                        <a href="ai_hr_round.php?drive_id=<?php echo $driveId; ?>" class="btn-start">
                            <i class="fas fa-play"></i> 
                            <?php echo empty($roundAttempts['HR']) ? 'Start Round' : 'Retake Round'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
