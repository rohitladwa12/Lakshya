<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_PLACEMENT_OFFICER);

$db = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $jobId = (int)$_POST['job_id'];
            $driveName = trim($_POST['drive_name']);
            $academicYear = trim($_POST['academic_year']);
            $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

            $aptitudeActive = isset($_POST['aptitude_active']) ? 1 : 0;
            $aptitudeTopics = trim($_POST['aptitude_topics']);
            $aptitudeQuestions = (int)$_POST['aptitude_questions'];
            $aptitudeDuration = (int)$_POST['aptitude_duration'];
            $aptitudeThreshold = (int)($_POST['aptitude_threshold'] ?? 60);

            $technicalActive = isset($_POST['technical_active']) ? 1 : 0;
            $technicalTopics = trim($_POST['technical_topics']);
            $technicalQuestions = (int)$_POST['technical_questions'];
            $technicalDuration = (int)$_POST['technical_duration'];
            $technicalThreshold = (int)($_POST['technical_threshold'] ?? 60);

            $hrActive = isset($_POST['hr_active']) ? 1 : 0;
            $hrTopics = trim($_POST['hr_topics']);
            $hrQuestions = (int)$_POST['hr_questions'];
            $hrDuration = (int)$_POST['hr_duration'];
            $hrThreshold = (int)($_POST['hr_threshold'] ?? 60);

            $stmt = $db->prepare("
                INSERT INTO campus_drives 
                (job_id, academic_year, drive_name, deadline, 
                 aptitude_active, aptitude_topics, aptitude_questions, aptitude_duration, aptitude_threshold,
                 technical_active, technical_topics, technical_questions, technical_duration, technical_threshold,
                 hr_active, hr_topics, hr_questions, hr_duration, hr_threshold)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $jobId, $academicYear, $driveName, $deadline,
                $aptitudeActive, $aptitudeTopics, $aptitudeQuestions, $aptitudeDuration, $aptitudeThreshold,
                $technicalActive, $technicalTopics, $technicalQuestions, $technicalDuration, $technicalThreshold,
                $hrActive, $hrTopics, $hrQuestions, $hrDuration, $hrThreshold
            ]);
            header("Location: campus_drives.php?success=1");
            exit;
        } elseif ($action === 'update') {
            $driveId = (int)$_POST['drive_id'];
            $driveName = trim($_POST['drive_name']);
            $academicYear = trim($_POST['academic_year']);
            $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

            $aptitudeActive = isset($_POST['aptitude_active']) ? 1 : 0;
            $aptitudeTopics = trim($_POST['aptitude_topics']);
            $aptitudeQuestions = (int)$_POST['aptitude_questions'];
            $aptitudeDuration = (int)$_POST['aptitude_duration'];
            $aptitudeThreshold = (int)($_POST['aptitude_threshold'] ?? 60);

            $technicalActive = isset($_POST['technical_active']) ? 1 : 0;
            $technicalTopics = trim($_POST['technical_topics']);
            $technicalQuestions = (int)$_POST['technical_questions'];
            $technicalDuration = (int)$_POST['technical_duration'];
            $technicalThreshold = (int)($_POST['technical_threshold'] ?? 60);

            $hrActive = isset($_POST['hr_active']) ? 1 : 0;
            $hrTopics = trim($_POST['hr_topics']);
            $hrQuestions = (int)$_POST['hr_questions'];
            $hrDuration = (int)$_POST['hr_duration'];
            $hrThreshold = (int)($_POST['hr_threshold'] ?? 60);

            $stmt = $db->prepare("
                UPDATE campus_drives SET
                    drive_name = ?, academic_year = ?, deadline = ?,
                    aptitude_active = ?, aptitude_topics = ?, aptitude_questions = ?, aptitude_duration = ?, aptitude_threshold = ?,
                    technical_active = ?, technical_topics = ?, technical_questions = ?, technical_duration = ?, technical_threshold = ?,
                    hr_active = ?, hr_topics = ?, hr_questions = ?, hr_duration = ?, hr_threshold = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $driveName, $academicYear, $deadline,
                $aptitudeActive, $aptitudeTopics, $aptitudeQuestions, $aptitudeDuration, $aptitudeThreshold,
                $technicalActive, $technicalTopics, $technicalQuestions, $technicalDuration, $technicalThreshold,
                $hrActive, $hrTopics, $hrQuestions, $hrDuration, $hrThreshold,
                $driveId
            ]);
            header("Location: campus_drives.php?success=2");
            exit;
        } elseif ($action === 'delete') {
            $driveId = (int)$_POST['drive_id'];
            $stmt = $db->prepare("DELETE FROM campus_drives WHERE id = ?");
            $stmt->execute([$driveId]);
            header("Location: campus_drives.php?success=3");
            exit;
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
}

// Fetch all existing drives
$stmt = $db->query("
    SELECT cd.*, jp.title AS job_title, c.name as company_name,
           (SELECT COUNT(DISTINCT student_id) FROM job_applications ja WHERE ja.job_id = cd.job_id) as total_applicants,
           (SELECT COUNT(DISTINCT student_id) FROM student_drive_attempts sda WHERE sda.drive_id = cd.id) as students_participated
    FROM campus_drives cd
    JOIN job_postings jp ON cd.job_id = jp.id
    LEFT JOIN companies c ON jp.company_id = c.id
    ORDER BY cd.created_at DESC
");
$drives = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all job postings that don't have a campus drive yet
$stmt = $db->query("
    SELECT jp.id, jp.title AS job_title, jp.eligible_years, jp.academic_year, jp.company_id,
           jp.location, jp.application_deadline, jp.min_cgpa, jp.status, jp.job_type, jp.work_mode,
           jp.salary_min, jp.salary_max, jp.description,
           c.name as company_name
    FROM job_postings jp
    JOIN companies c ON jp.company_id = c.id
    WHERE NOT EXISTS (SELECT 1 FROM campus_drives cd WHERE cd.job_id = jp.id)
    ORDER BY jp.created_at DESC, c.name ASC
");
$availableJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$academicYears = array_values(array_unique(array_filter(
    array_map(fn($j) => trim((string)($j['academic_year'] ?? '')), $availableJobs),
    fn($y) => $y !== ''
)));
rsort($academicYears);
$hasJobsWithoutYear = count(array_filter(
    $availableJobs,
    fn($j) => trim((string)($j['academic_year'] ?? '')) === ''
)) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Drives | Placement Portal</title>
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
        }

        .main-content {
            padding: 40px 50px;
            max-width: 1500px;
            margin: 0 auto;
        }

        .tabs-header {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .tab-btn:hover {
            color: var(--brand);
        }

        .tab-btn.active {
            color: var(--brand);
            border-bottom-color: var(--brand);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--brand-dark);
            margin: 0 0 6px 0;
            letter-spacing: -0.5px;
        }

        .header-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
        }

        .btn-create-drive {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            border-radius: 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(124, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .btn-create-drive:hover {
            background: var(--brand-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 0, 0, 0.3);
        }

        .alerts-container {
            margin-bottom: 25px;
        }

        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .alert-success {
            background: #e8fbee;
            color: #166534;
            border: 1px solid #b7ebc6;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Drives List */
        .drives-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .drive-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 24px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.3s;
        }

        .drive-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
            border-color: rgba(124, 0, 0, 0.15);
        }

        .drive-card-header {
            margin-bottom: 16px;
        }

        .drive-tag {
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

        .academic-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #eff6ff;
            color: #1e40af;
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            margin-bottom: 8px;
            margin-left: 6px;
        }

        .drive-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0 0 6px 0;
        }

        .drive-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        .drive-deadline {
            margin-top: 10px;
            font-size: 12px;
            color: #d97706;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .drive-rounds-info {
            background: #fdfdfd;
            border: 1px solid #f3f4f6;
            border-radius: 12px;
            padding: 12px 16px;
            margin: 16px 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .round-status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }

        .round-status-row span.round-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .round-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .round-badge.active {
            background: #e8fbee;
            color: #166534;
        }

        .round-badge.inactive {
            background: #f3f4f6;
            color: var(--text-muted);
        }

        .drive-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
        }

        .drive-stats strong {
            color: var(--text-dark);
        }

        .drive-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .btn-card-action {
            flex: 1;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            box-sizing: border-box;
            border: none;
        }

        .btn-view-report {
            background: var(--brand);
            color: #fff;
        }

        .btn-view-report:hover {
            background: var(--brand-dark);
        }

        .btn-edit-drive {
            background: #f3f4f6;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }

        .btn-edit-drive:hover {
            background: #e5e7eb;
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
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            box-sizing: border-box;
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            animation: modalFadeIn 0.3s var(--ease-out);
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--brand-dark);
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

        /* Form styling */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--brand);
        }

        /* Collapsible Round Config Blocks */
        .round-config-block {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            background: #fafafa;
        }

        .round-config-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .round-config-header strong {
            font-size: 14px;
            color: var(--brand-dark);
        }

        .switch-container {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .switch-input {
            width: 36px;
            height: 20px;
            appearance: none;
            background: #ccc;
            outline: none;
            border-radius: 10px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }

        .switch-input:checked {
            background: var(--brand);
        }

        .switch-input::before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            top: 2px;
            left: 2px;
            transition: all 0.3s;
        }

        .switch-input:checked::before {
            left: 18px;
        }

        .round-fields {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .round-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .no-data {
            grid-column: 1 / -1;
            text-align: center;
            background: #fff;
            padding: 60px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
        }

        .no-data i {
            font-size: 44px;
            margin-bottom: 12px;
            color: var(--border-color);
            display: block;
        }

        .section-heading {
            font-size: 18px;
            font-weight: 800;
            color: var(--brand-dark);
            margin: 0 0 16px 0;
        }

        .job-posting-card {
            background: #fff;
            border-radius: 16px;
            border: 1px dashed #d1d5db;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .job-posting-card .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .job-posting-card .job-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-badge.active { background: #e8fbee; color: #166534; }
        .status-badge.closed { background: #fef2f2; color: #991b1b; }
        .status-badge.draft { background: #f3f4f6; color: #6b7280; }

        .btn-setup-drive {
            margin-top: auto;
            padding: 10px 14px;
            background: var(--brand-light);
            color: var(--brand);
            border: 1px solid rgba(124, 0, 0, 0.15);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-setup-drive:hover {
            background: var(--brand);
            color: #fff;
        }
    </style>
</head>
<body>

    <?php include_once 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="header-container">
        <div class="header-title">
            <h1>Campus Recruitment Drives</h1>
            <p>Design multi-stage assessments with topics and questions tailored for applied students</p>
        </div>
        <?php if (!empty($availableJobs)): ?>
        <button class="btn-create-drive" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Create Campus Drive
        </button>
        <?php endif; ?>
    </div>

    <div class="alerts-container">
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] == 1): ?>
                <div class="alert alert-success">Recruitment drive configured and published successfully!</div>
            <?php elseif ($_GET['success'] == 2): ?>
                <div class="alert alert-success">Recruitment drive updated successfully.</div>
            <?php elseif ($_GET['success'] == 3): ?>
                <div class="alert alert-success">Recruitment drive deleted successfully.</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-error">Error: <?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>
    </div>

    <div class="tabs-header">
        <button class="tab-btn active" onclick="switchTab('unconfigured', this)">
            <i class="fas fa-briefcase"></i> Job Postings (<?php echo count($availableJobs); ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('configured', this)">
            <i class="fas fa-laptop-code"></i> Configured Campus Drives (<?php echo count($drives); ?>)
        </button>
    </div>

    <div id="tab-unconfigured" class="tab-content active">
        <?php if (!empty($availableJobs)): ?>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 20px;">
                Ready to be configured as campus drives.
            </p>
            <div class="drives-grid" style="margin-top: 0;">
                <?php foreach ($availableJobs as $job): ?>
                <div class="job-posting-card">
                    <div>
                        <span class="drive-tag"><?php echo htmlspecialchars($job['company_name']); ?></span>
                        <?php if (!empty(trim((string)($job['academic_year'] ?? '')))): ?>
                            <span class="academic-badge"><?php echo htmlspecialchars($job['academic_year']); ?></span>
                        <?php endif; ?>
                        <span class="status-badge <?php echo strtolower($job['status'] ?? 'active'); ?>">
                            <?php echo htmlspecialchars($job['status'] ?? 'Active'); ?>
                        </span>
                    </div>
                    <h3 class="drive-title"><?php echo htmlspecialchars($job['job_title']); ?></h3>
                    <div class="job-meta">
                        <?php if (!empty($job['location'])): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($job['job_type'])): ?>
                            <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($job['job_type']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($job['work_mode'])): ?>
                            <span><i class="fas fa-laptop-house"></i> <?php echo htmlspecialchars($job['work_mode']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($job['min_cgpa'])): ?>
                            <span><i class="fas fa-graduation-cap"></i> Min SGPA: <?php echo htmlspecialchars($job['min_cgpa']); ?>+</span>
                        <?php endif; ?>
                        <?php if (!empty($job['application_deadline'])): ?>
                            <span><i class="fas fa-calendar-alt"></i> Apply by: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($job['salary_min']) && !empty($job['salary_max'])): ?>
                            <span><i class="fas fa-indian-rupee-sign"></i>
                                <?php echo number_format($job['salary_min'] / 100000, 1); ?>L – <?php echo number_format($job['salary_max'] / 100000, 1); ?>L
                            </span>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-setup-drive" onclick="openCreateModalForJob(<?php echo (int)$job['id']; ?>)">
                        <i class="fas fa-plus"></i> Set Up Campus Drive
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-check-circle"></i>
                All job postings have been configured.
            </div>
        <?php endif; ?>
    </div>

    <div id="tab-configured" class="tab-content">
        <div class="drives-grid" style="margin-top: 0;">
        <?php foreach ($drives as $drive): ?>
        <div class="drive-card">
            <div class="drive-card-header">
                <div>
                    <span class="drive-tag"><?php echo htmlspecialchars($drive['company_name']); ?></span>
                    <span class="academic-badge"><?php echo htmlspecialchars($drive['academic_year']); ?></span>
                </div>
                <h3 class="drive-title"><?php echo htmlspecialchars($drive['drive_name']); ?></h3>
                <p class="drive-subtitle"><i class="fas fa-briefcase"></i> Job Title: <?php echo htmlspecialchars($drive['job_title']); ?></p>
                <?php if ($drive['deadline']): ?>
                <div class="drive-deadline">
                    <i class="fas fa-calendar-alt"></i> Deadline: <?php echo date('M d, Y h:i A', strtotime($drive['deadline'])); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="drive-rounds-info">
                <div class="round-status-row">
                    <span class="round-name"><i class="fas fa-file-invoice"></i> Aptitude Round</span>
                    <span class="round-badge <?php echo $drive['aptitude_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $drive['aptitude_active'] ? 'Active' : 'Disabled'; ?>
                    </span>
                </div>
                <div class="round-status-row">
                    <span class="round-name"><i class="fas fa-code"></i> Technical Round</span>
                    <span class="round-badge <?php echo $drive['technical_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $drive['technical_active'] ? 'Active' : 'Disabled'; ?>
                    </span>
                </div>
                <div class="round-status-row">
                    <span class="round-name"><i class="fas fa-users-rectangle"></i> HR Round</span>
                    <span class="round-badge <?php echo $drive['hr_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $drive['hr_active'] ? 'Active' : 'Disabled'; ?>
                    </span>
                </div>
            </div>

            <div class="drive-stats">
                <span>Applied: <strong><?php echo $drive['total_applicants']; ?></strong></span>
                <span>Attempts: <strong><?php echo $drive['students_participated']; ?></strong></span>
            </div>

            <div class="drive-actions">
                <a href="drive_details.php?drive_id=<?php echo $drive['id']; ?>" class="btn-card-action btn-view-report">
                    <i class="fas fa-chart-line"></i> View Report
                </a>
                <button class="btn-card-action btn-edit-drive" onclick='openEditModal(<?php echo json_encode($drive); ?>)'>
                    <i class="fas fa-cog"></i> Configure
                </button>
            </div>
        </div>
            <?php endforeach; if (empty($drives)): ?>
            <div class="no-data">
                <i class="fas fa-laptop-code"></i>
                No campus drives configured yet. Create a drive for your job postings to get started.
            </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- CREATE DRIVE MODAL -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Campus Drive</h2>
                <button class="close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form action="campus_drives.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="create_academic_year_select">Academic Year</label>
                    <select id="create_academic_year_select" class="form-control" required onchange="onAcademicYearSelect(this)">
                        <option value="">-- Select Academic Year --</option>
                        <?php if ($hasJobsWithoutYear): ?>
                            <option value="__none__">Not Specified</option>
                        <?php endif; ?>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="academic_year" id="create_academic_year" class="form-control" style="margin-top: 8px; display: none;" placeholder="Enter academic year (e.g. 2025-26)">
                </div>

                <div class="form-group">
                    <label for="create_company_select">Company</label>
                    <select id="create_company_select" class="form-control" required disabled onchange="onCompanySelect(this)">
                        <option value="">-- Select Company --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="job_id">Job Posting</label>
                    <select name="job_id" id="job_id" class="form-control" required disabled onchange="onJobSelect(this)">
                        <option value="">-- Choose Job --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="create_drive_name">Drive Name</label>
                    <input type="text" name="drive_name" id="create_drive_name" class="form-control" required placeholder="e.g. Google Campus Hiring 2026">
                </div>

                <div class="form-group">
                    <label for="create_deadline">Drive Submission Deadline</label>
                    <input type="datetime-local" name="deadline" id="create_deadline" class="form-control" required>
                </div>

                <!-- ROUND CONFIG BLOCK: APTITUDE -->
                <div class="round-config-block">
                    <div class="round-config-header">
                        <strong>1. Aptitude Round</strong>
                        <div class="switch-container">
                            <input type="checkbox" name="aptitude_active" id="create_apt_active" class="switch-input" value="1" onchange="toggleRoundFields('create_apt')">
                        </div>
                    </div>
                    <div id="create_apt_fields" class="round-fields" style="display: none;">
                        <div class="form-group">
                            <label>Aptitude Topics (AI generated questions will target these)</label>
                            <input type="text" name="aptitude_topics" class="form-control" placeholder="e.g. Quantitative, Logical, Verbal" value="Quantitative, Logical, Verbal">
                        </div>
                        <div class="round-fields-grid">
                            <div class="form-group">
                                <label>Questions count</label>
                                <input type="number" name="aptitude_questions" class="form-control" value="10" min="5" max="30">
                            </div>
                            <div class="form-group">
                                <label>Duration (Minutes)</label>
                                <input type="number" name="aptitude_duration" class="form-control" value="20" min="5" max="60">
                            </div>
                            <div class="form-group">
                                <label>Eligibility Score (%)</label>
                                <input type="number" name="aptitude_threshold" class="form-control" value="60" min="1" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROUND CONFIG BLOCK: TECHNICAL -->
                <div class="round-config-block">
                    <div class="round-config-header">
                        <strong>2. Technical Round</strong>
                        <div class="switch-container">
                            <input type="checkbox" name="technical_active" id="create_tech_active" class="switch-input" value="1" onchange="toggleRoundFields('create_tech')">
                        </div>
                    </div>
                    <div id="create_tech_fields" class="round-fields" style="display: none;">
                        <div class="form-group">
                            <label>Technical Topics (Core languages, frameworks or skills)</label>
                            <input type="text" name="technical_topics" class="form-control" placeholder="e.g. Java, DBMS, OOPs" value="Java, DBMS, OOPs">
                        </div>
                        <div class="round-fields-grid">
                            <div class="form-group">
                                <label>Questions count</label>
                                <input type="number" name="technical_questions" class="form-control" value="10" min="5" max="30">
                            </div>
                            <div class="form-group">
                                <label>Duration (Minutes)</label>
                                <input type="number" name="technical_duration" class="form-control" value="20" min="5" max="60">
                            </div>
                            <div class="form-group">
                                <label>Eligibility Score (%)</label>
                                <input type="number" name="technical_threshold" class="form-control" value="60" min="1" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROUND CONFIG BLOCK: HR -->
                <div class="round-config-block">
                    <div class="round-config-header">
                        <strong>3. HR Round</strong>
                        <div class="switch-container">
                            <input type="checkbox" name="hr_active" id="create_hr_active" class="switch-input" value="1" onchange="toggleRoundFields('create_hr')">
                        </div>
                    </div>
                    <div id="create_hr_fields" class="round-fields" style="display: none;">
                        <div class="form-group">
                            <label>HR Topics / Focus (Behavioral traits or culture focus)</label>
                            <input type="text" name="hr_topics" class="form-control" placeholder="e.g. Behavioral, Problem Solving, Communication" value="Behavioral, Problem Solving, Communication">
                        </div>
                        <div class="round-fields-grid">
                            <div class="form-group">
                                <label>Questions count</label>
                                <input type="number" name="hr_questions" class="form-control" value="10" min="5" max="30">
                            </div>
                            <div class="form-group">
                                <label>Duration (Minutes)</label>
                                <input type="number" name="hr_duration" class="form-control" value="20" min="5" max="60">
                            </div>
                            <div class="form-group">
                                <label>Eligibility Score (%)</label>
                                <input type="number" name="hr_threshold" class="form-control" value="60" min="1" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-create-drive" style="width: 100%; justify-content: center; margin-top: 10px;">
                    <i class="fas fa-check"></i> Create Drive
                </button>
            </form>
        </div>
    </div>

    <!-- CONFIGURE / EDIT DRIVE MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Configure Campus Drive</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form action="campus_drives.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="drive_id" id="edit_drive_id">
                
                <div class="form-group">
                    <label>Job Posting</label>
                    <input type="text" id="edit_job_name" class="form-control" readonly style="background: #fafafa; border-color: #eee;">
                </div>

                <div class="form-group">
                    <label for="edit_drive_name">Drive Name</label>
                    <input type="text" name="drive_name" id="edit_drive_name" class="form-control" required placeholder="e.g. Google Campus Hiring 2026">
                </div>

                <div class="form-group">
                    <label for="edit_academic_year">Target Academic Year</label>
                    <input type="text" name="academic_year" id="edit_academic_year" class="form-control" required placeholder="e.g. 2025-2026">
                </div>

                <div class="form-group">
                    <label for="edit_deadline">Drive Submission Deadline</label>
                    <input type="datetime-local" name="deadline" id="edit_deadline" class="form-control" required>
                </div>

                <!-- ROUND CONFIG BLOCK: APTITUDE -->
                <div class="round-config-block">
                    <div class="round-config-header">
                        <strong>1. Aptitude Round</strong>
                        <div class="switch-container">
                            <input type="checkbox" name="aptitude_active" id="edit_apt_active" class="switch-input" value="1" onchange="toggleRoundFields('edit_apt')">
                        </div>
                    </div>
                    <div id="edit_apt_fields" class="round-fields" style="display: none;">
                        <div class="form-group">
                            <label>Aptitude Topics (AI generated questions will target these)</label>
                            <input type="text" name="aptitude_topics" id="edit_aptitude_topics" class="form-control" placeholder="e.g. Quantitative, Logical, Verbal">
                        </div>
                        <div class="round-fields-grid">
                            <div class="form-group">
                                <label>Questions count</label>
                                <input type="number" name="aptitude_questions" id="edit_aptitude_questions" class="form-control" min="5" max="30">
                            </div>
                            <div class="form-group">
                                <label>Duration (Minutes)</label>
                                <input type="number" name="aptitude_duration" id="edit_aptitude_duration" class="form-control" min="5" max="60">
                            </div>
                            <div class="form-group">
                                <label>Eligibility Score (%)</label>
                                <input type="number" name="aptitude_threshold" id="edit_aptitude_threshold" class="form-control" min="1" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROUND CONFIG BLOCK: TECHNICAL -->
                <div class="round-config-block">
                    <div class="round-config-header">
                        <strong>2. Technical Round</strong>
                        <div class="switch-container">
                            <input type="checkbox" name="technical_active" id="edit_tech_active" class="switch-input" value="1" onchange="toggleRoundFields('edit_tech')">
                        </div>
                    </div>
                    <div id="edit_tech_fields" class="round-fields" style="display: none;">
                        <div class="form-group">
                            <label>Technical Topics (Core languages, frameworks or skills)</label>
                            <input type="text" name="technical_topics" id="edit_technical_topics" class="form-control" placeholder="e.g. Java, DBMS, OOPs">
                        </div>
                        <div class="round-fields-grid">
                            <div class="form-group">
                                <label>Questions count</label>
                                <input type="number" name="technical_questions" id="edit_technical_questions" class="form-control" min="5" max="30">
                            </div>
                            <div class="form-group">
                                <label>Duration (Minutes)</label>
                                <input type="number" name="technical_duration" id="edit_technical_duration" class="form-control" min="5" max="60">
                            </div>
                            <div class="form-group">
                                <label>Eligibility Score (%)</label>
                                <input type="number" name="technical_threshold" id="edit_technical_threshold" class="form-control" min="1" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROUND CONFIG BLOCK: HR -->
                <div class="round-config-block">
                    <div class="round-config-header">
                        <strong>3. HR Round</strong>
                        <div class="switch-container">
                            <input type="checkbox" name="hr_active" id="edit_hr_active" class="switch-input" value="1" onchange="toggleRoundFields('edit_hr')">
                        </div>
                    </div>
                    <div id="edit_hr_fields" class="round-fields" style="display: none;">
                        <div class="form-group">
                            <label>HR Topics / Focus (Behavioral traits or culture focus)</label>
                            <input type="text" name="hr_topics" id="edit_hr_topics" class="form-control" placeholder="e.g. Behavioral, Problem Solving, Communication">
                        </div>
                        <div class="round-fields-grid">
                            <div class="form-group">
                                <label>Questions count</label>
                                <input type="number" name="hr_questions" id="edit_hr_questions" class="form-control" min="5" max="30">
                            </div>
                            <div class="form-group">
                                <label>Duration (Minutes)</label>
                                <input type="number" name="hr_duration" id="edit_hr_duration" class="form-control" min="5" max="60">
                            </div>
                            <div class="form-group">
                                <label>Eligibility Score (%)</label>
                                <input type="number" name="hr_threshold" id="edit_hr_threshold" class="form-control" min="1" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-create-drive" style="flex: 2; justify-content: center;">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="button" class="btn-card-action btn-edit-drive" style="flex: 1; background: #fee2e2; border-color: #fca5a5; color: #b91c1c; font-weight: 700;" onclick="confirmDeleteDrive()">
                        <i class="fas fa-trash-can"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden form for deletion -->
    <form id="deleteForm" action="campus_drives.php" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="drive_id" id="delete_drive_id">
    </form>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        const availableJobs = <?php echo json_encode($availableJobs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function toggleRoundFields(prefix) {
            const isActive = document.getElementById(prefix + '_active').checked;
            const fieldsBlock = document.getElementById(prefix + '_fields');
            if (isActive) {
                fieldsBlock.style.display = 'block';
            } else {
                fieldsBlock.style.display = 'none';
            }
        }

        function syncAcademicYearField(year) {
            const yearInput = document.getElementById('create_academic_year');
            if (year === '__none__') {
                yearInput.style.display = 'block';
                yearInput.required = true;
                if (!yearInput.value) {
                    yearInput.value = '';
                }
            } else {
                yearInput.style.display = 'none';
                yearInput.required = false;
                yearInput.value = year === '' ? '' : year;
            }
        }

        function jobMatchesYear(job, year) {
            const jobYear = (job.academic_year || '').trim();
            if (year === '__none__') return jobYear === '';
            return jobYear === year;
        }

        function onAcademicYearSelect(selectElement) {
            const year = selectElement.value;
            syncAcademicYearField(year);

            const companySelect = document.getElementById('create_company_select');
            const jobSelect = document.getElementById('job_id');

            companySelect.innerHTML = '<option value="">-- Select Company --</option>';
            jobSelect.innerHTML = '<option value="">-- Choose Job --</option>';
            jobSelect.disabled = true;
            document.getElementById('create_drive_name').value = '';

            if (!year) {
                companySelect.disabled = true;
                return;
            }

            const companies = new Map();
            availableJobs
                .filter(job => jobMatchesYear(job, year))
                .forEach(job => {
                    if (!companies.has(String(job.company_id))) {
                        companies.set(String(job.company_id), job.company_name);
                    }
                });

            companies.forEach((name, id) => {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = name;
                companySelect.appendChild(option);
            });

            companySelect.disabled = companies.size === 0;

            if (companies.size === 1) {
                companySelect.selectedIndex = 1;
                onCompanySelect(companySelect);
            }
        }

        function onCompanySelect(selectElement) {
            const companyId = selectElement.value;
            const year = document.getElementById('create_academic_year_select').value;
            const jobSelect = document.getElementById('job_id');

            jobSelect.innerHTML = '<option value="">-- Choose Job --</option>';
            document.getElementById('create_drive_name').value = '';

            if (!companyId || !year) {
                jobSelect.disabled = true;
                return;
            }

            availableJobs
                .filter(job => jobMatchesYear(job, year) && String(job.company_id) === String(companyId))
                .forEach(job => {
                    const option = document.createElement('option');
                    option.value = job.id;
                    option.textContent = job.job_title;
                    option.setAttribute('data-batch', job.academic_year || '');
                    option.setAttribute('data-company', job.company_name || '');
                    jobSelect.appendChild(option);
                });

            jobSelect.disabled = jobSelect.options.length <= 1;

            if (jobSelect.options.length === 2) {
                jobSelect.selectedIndex = 1;
                onJobSelect(jobSelect);
            }
        }

        function onJobSelect(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            if (!selectedOption || !selectElement.value) return;
            
            const batch = selectedOption.getAttribute('data-batch') || '';
            const company = selectedOption.getAttribute('data-company') || '';
            
            const yearInput = document.getElementById('create_academic_year');
            if (document.getElementById('create_academic_year_select').value === '__none__') {
                if (!yearInput.value.trim()) {
                    yearInput.value = batch;
                }
            } else {
                yearInput.value = batch;
            }
            document.getElementById('create_drive_name').value = company + ' Campus Selection ' + new Date().getFullYear();
        }

        function resetCreateForm() {
            document.getElementById('create_academic_year_select').value = '';
            const yearInput = document.getElementById('create_academic_year');
            yearInput.value = '';
            yearInput.style.display = 'none';
            yearInput.required = false;
            document.getElementById('create_company_select').innerHTML = '<option value="">-- Select Company --</option>';
            document.getElementById('create_company_select').disabled = true;
            document.getElementById('job_id').innerHTML = '<option value="">-- Choose Job --</option>';
            document.getElementById('job_id').disabled = true;
            document.getElementById('create_drive_name').value = '';
        }

        function preselectJobInCreateModal(jobId) {
            const job = availableJobs.find(j => String(j.id) === String(jobId));
            if (!job) return;

            const yearSelect = document.getElementById('create_academic_year_select');
            const jobYear = (job.academic_year || '').trim();
            yearSelect.value = jobYear !== '' ? jobYear : '__none__';
            onAcademicYearSelect(yearSelect);

            const companySelect = document.getElementById('create_company_select');
            companySelect.value = String(job.company_id);
            onCompanySelect(companySelect);

            const jobSelect = document.getElementById('job_id');
            jobSelect.value = String(job.id);
            onJobSelect(jobSelect);
        }

        // Create Modal Actions
        function openCreateModal() {
            if (!availableJobs.length) {
                alert('No job postings are available for a new campus drive. Add a job posting first, or all jobs already have drives configured.');
                return;
            }
            resetCreateForm();
            document.getElementById('createModal').style.display = 'flex';
        }

        function openCreateModalForJob(jobId) {
            if (!availableJobs.length) return;
            resetCreateForm();
            document.getElementById('createModal').style.display = 'flex';
            preselectJobInCreateModal(jobId);
        }
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }

        // Edit Modal Actions
        function openEditModal(drive) {
            document.getElementById('edit_drive_id').value = drive.id;
            document.getElementById('edit_job_name').value = drive.company_name + ' - ' + drive.job_title;
            document.getElementById('edit_drive_name').value = drive.drive_name;
            document.getElementById('edit_academic_year').value = drive.academic_year;
            
            if (drive.deadline) {
                // Convert to local datetime string format for datetime-local input
                const date = new Date(drive.deadline);
                const localStr = date.getFullYear() + '-' + 
                                 String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                                 String(date.getDate()).padStart(2, '0') + 'T' + 
                                 String(date.getHours()).padStart(2, '0') + ':' + 
                                 String(date.getMinutes()).padStart(2, '0');
                document.getElementById('edit_deadline').value = localStr;
            } else {
                document.getElementById('edit_deadline').value = '';
            }

            // Aptitude Round
            const aptActive = parseInt(drive.aptitude_active) === 1;
            document.getElementById('edit_apt_active').checked = aptActive;
            document.getElementById('edit_aptitude_topics').value = drive.aptitude_topics || 'Quantitative, Logical, Verbal';
            document.getElementById('edit_aptitude_questions').value = drive.aptitude_questions || 10;
            document.getElementById('edit_aptitude_duration').value = drive.aptitude_duration || 20;
            document.getElementById('edit_aptitude_threshold').value = drive.aptitude_threshold || 60;
            toggleRoundFields('edit_apt');

            // Technical Round
            const techActive = parseInt(drive.technical_active) === 1;
            document.getElementById('edit_tech_active').checked = techActive;
            document.getElementById('edit_technical_topics').value = drive.technical_topics || 'Java, DBMS, OOPs';
            document.getElementById('edit_technical_questions').value = drive.technical_questions || 10;
            document.getElementById('edit_technical_duration').value = drive.technical_duration || 20;
            document.getElementById('edit_technical_threshold').value = drive.technical_threshold || 60;
            toggleRoundFields('edit_tech');

            // HR Round
            const hrActive = parseInt(drive.hr_active) === 1;
            document.getElementById('edit_hr_active').checked = hrActive;
            document.getElementById('edit_hr_topics').value = drive.hr_topics || 'Behavioral, Problem Solving, Communication';
            document.getElementById('edit_hr_questions').value = drive.hr_questions || 10;
            document.getElementById('edit_hr_duration').value = drive.hr_duration || 20;
            document.getElementById('edit_hr_threshold').value = drive.hr_threshold || 60;
            toggleRoundFields('edit_hr');

            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDeleteDrive() {
            const driveId = document.getElementById('edit_drive_id').value;
            if (confirm("Are you absolutely sure you want to delete this campus recruitment drive? All student attempts and records will be deleted forever.")) {
                document.getElementById('delete_drive_id').value = driveId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const editModal = document.getElementById('editModal');
            if (event.target == createModal) {
                closeCreateModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
