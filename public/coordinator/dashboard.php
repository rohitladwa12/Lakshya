<?php
/**
 * Department Coordinator Dashboard
 * Clean and minimal overview
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

$fullName = getFullName();
$department = getDepartment() ?: 'General';
list($deptGmu, $deptGmit) = getCoordinatorDisciplineFilters($department);
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;
if (!$deptLabel) $deptLabel = 'General Dashboard';

$studentModel = new StudentProfile();
$semester_filter_all = getCoordinatorSemesterFilters($department) ?: [1, 8];
// Query actual max sem with students (not theoretical max like 8)
try {
    $dbDash = getDB('gmu');
    $disc_dash = getCoordinatorDisciplineFilters($department);
    $ph_d = implode(',', array_fill(0, count($disc_dash), '?'));
    $ph_s = implode(',', array_fill(0, count($semester_filter_all), '?'));
    $stmtMs = $dbDash->prepare("SELECT MAX(sem) FROM " . DB_GMU_PREFIX . "ad_student_approved WHERE discipline IN ($ph_d) AND sem IN ($ph_s)");
    $stmtMs->execute(array_merge($disc_dash, $semester_filter_all));
    $actualMaxSem = (int)($stmtMs->fetchColumn() ?: max($semester_filter_all));
} catch (Exception $e) {
    $actualMaxSem = max($semester_filter_all);
}
$semester_filter = [$actualMaxSem];
$discipline_filters = getCoordinatorDisciplineFilters($department);

$coordFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semester_filter
];

// Use a more inclusive counting method to match the Students Report (Academic Strength)
$studentCount = $studentModel->getTotalAcademicStrength($coordFilters);

// Fetch recent feedback from department students
$feedbacks = [];
if (!empty($discipline_filters)) {
    try {
        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($discipline_filters), '?'));
        $stmt = $db->prepare("SELECT * FROM portal_feedback WHERE branch IN ($placeholders) ORDER BY created_at DESC LIMIT 5");
        $stmt->execute($discipline_filters);
        $feedbacks = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching coordinator dashboard feedbacks: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard - <?php echo APP_NAME; ?></title>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --primary-gold-dark: #b59228;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border-color: #e2e8f0;
            
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
            --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }
        
        .navbar-spacer { height: 70px; }
        
        .main-content { 
            max-width: 1280px;
            margin: 0 auto;
            padding: 40px 24px 80px 24px;
        }
        
        .page-header { 
            margin-bottom: 32px; 
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 24px;
        }
        
        .page-header h2 { 
            font-size: 32px; 
            color: var(--primary-maroon); 
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .page-header p { 
            color: var(--text-muted); 
            font-size: 14px; 
            font-weight: 500;
        }
        
        .stats-card {
            background: var(--white);
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.03);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: var(--transition);
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stats-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary-maroon), #600000);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }
        
        .stats-info h3 {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .stats-info p {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background: var(--white);
            padding: 28px 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.03);
            text-decoration: none;
            color: var(--text-main);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(128, 0, 0, 0.1);
        }
        
        .action-card.primary {
            background: linear-gradient(135deg, var(--primary-maroon), #600000);
            color: var(--white);
            border: none;
        }
        
        .action-card.primary:hover {
            box-shadow: 0 15px 30px rgba(128, 0, 0, 0.25);
        }
        
        .action-card.secondary {
            background: linear-gradient(135deg, var(--white), #fdfbf7);
            border: 1px solid rgba(212, 175, 55, 0.2);
        }
        
        .action-card.secondary:hover {
            border-color: var(--primary-gold);
            box-shadow: 0 15px 30px rgba(212, 175, 55, 0.15);
        }
        
        .action-card.jobs-btn {
            background: linear-gradient(135deg, #10b981, #047857);
            color: var(--white);
            border: none;
        }
        
        .action-card.jobs-btn:hover {
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.25);
        }
        
        .action-icon {
            font-size: 26px;
            transition: var(--transition);
        }
        
        .action-card:hover .action-icon {
            transform: scale(1.1);
        }
        
        .action-card.primary .action-icon {
            color: var(--primary-gold);
        }
        
        .action-card.secondary .action-icon {
            color: var(--primary-gold-dark);
        }
        
        .action-card.jobs-btn .action-icon {
            color: #a7f3d0;
        }
        
        .action-card:not(.primary):not(.secondary):not(.jobs-btn) .action-icon {
            color: var(--primary-maroon);
        }
        
        .action-title {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.2px;
        }
        
        .action-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
        }
        
        .action-card.primary .action-desc,
        .action-card.jobs-btn .action-desc {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Feedback Section */
        .feedback-section {
            background: var(--white);
            padding: 32px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.03);
            margin-top: 40px;
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
        }
        
        .feedback-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .feedback-header a {
            color: var(--primary-maroon);
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .feedback-header a:hover {
            color: #600000;
            transform: translateX(3px);
        }
        
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        
        .feedback-table th {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        
        .feedback-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            vertical-align: middle;
        }
        
        .feedback-table tr {
            transition: var(--transition);
        }
        
        .feedback-table tr:hover td {
            background-color: #fdfafa;
        }
        
        .feedback-student-name {
            font-weight: 700;
            color: var(--text-main);
            font-size: 14px;
        }
        
        .feedback-student-meta {
            font-size: 11px;
            color: var(--text-light);
            margin-top: 3px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h2>Dashboard</h2>
            <p><?php echo htmlspecialchars($deptLabel); ?> • Semesters <?php echo min($semester_filter) . '-' . max($semester_filter); ?></p>
        </div>

        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-info">
                <h3>Total Students</h3>
                <p><?php echo (int) $studentCount; ?></p>
            </div>
        </div>

        <div class="quick-actions">
            <a href="assign_task.php" class="action-card primary">
                <div class="action-icon"><i class="fas fa-tasks"></i></div>
                <div class="action-title">Assign Tasks</div>
                <div class="action-desc">Assign assessments to students</div>
            </a>

            <a href="manage_tasks.php" class="action-card secondary">
                <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                <div class="action-title">Manage Tasks</div>
                <div class="action-desc">Track student progress</div>
            </a>

            <a href="analytics.php?reset=1" class="action-card secondary">
                <div class="action-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="action-title">Department Analytics</div>
                <div class="action-desc">Track department progress</div>
            </a>

            <a href="leaderboard.php" class="action-card secondary">
                <div class="action-icon"><i class="fas fa-trophy"></i></div>
                <div class="action-title">Student Leaderboard</div>
                <div class="action-desc">Department & Global rankings</div>
            </a>

            <a href="students_report.php?section=details&inst=all" class="action-card">
                <div class="action-icon"><i class="fas fa-list-check"></i></div>
                <div class="action-title">All Students</div>
                <div class="action-desc">View student details</div>
            </a>

            <a href="add_aptitude.php" class="action-card">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="action-title">Add Aptitude</div>
                <div class="action-desc">Create aptitude questions</div>
            </a>

            <a href="add_coding.php" class="action-card">
                <div class="action-icon"><i class="fas fa-code"></i></div>
                <div class="action-title">Add Coding</div>
                <div class="action-desc">Create coding problems</div>
            </a>

            <a href="jobs.php" class="action-card jobs-btn">
                <div class="action-icon"><i class="fas fa-briefcase"></i></div>
                <div class="action-title">Jobs & Internships</div>
                <div class="action-desc">Track department applications</div>
            </a>
        </div>

        <!-- Recent Student Feedback Card -->
        <div class="feedback-section">
            <div class="feedback-header">
                <h3>
                    <i class="fas fa-comments" style="color: var(--primary-maroon);"></i> Recent Student Feedback
                </h3>
                <a href="feedback.php">View All Feedback →</a>
            </div>
            
            <?php if (empty($feedbacks)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                    <p style="font-size: 14px; font-weight: 500;">No student feedback received yet.</p>
                </div>
            <?php else: ?>
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Comments</th>
                            <th>Suggested Feature</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $fb): ?>
                            <tr>
                                <td>
                                    <div class="feedback-student-name"><?php echo htmlspecialchars($fb['student_name'] ?? 'N/A'); ?></div>
                                    <div class="feedback-student-meta">
                                        <?php echo htmlspecialchars(($fb['institution'] ?? 'GMU') . (($fb['current_sem'] ?? null) ? ' • Sem ' . $fb['current_sem'] : '')); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $fb['general_comments'] ? htmlspecialchars(substr($fb['general_comments'], 0, 80)) . (strlen($fb['general_comments']) > 80 ? '...' : '') : '<span style="font-style:italic;opacity:0.6;">None</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($fb['new_feature_title']): ?>
                                        <strong style="color: var(--primary-maroon); font-weight: 600;"><?php echo htmlspecialchars($fb['new_feature_title']); ?></strong>
                                    <?php else: ?>
                                        <span style="font-style:italic;opacity:0.6;">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>

