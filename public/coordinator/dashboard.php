<?php
/**
 * Department Coordinator Dashboard
 * Clean, modern overview with non-duplicated operations & analytics
 */

require_once __DIR__ . '/../../config/bootstrap.php';

requireRole(ROLE_DEPT_COORDINATOR);

$coordinatorId = getUserId();
$fullName = getFullName();
$department = getDepartment() ?: 'General';
list($deptGmu, $deptGmit) = getCoordinatorDisciplineFilters($department);
$deptLabel = ($deptGmu !== $deptGmit) ? $deptGmu . ' (GMU) & ' . $deptGmit . ' (GMIT)' : $department;
if (!$deptLabel) $deptLabel = 'General Dashboard';

$db = getDB();
$studentModel = new StudentProfile();
$semester_filter_all = getCoordinatorSemesterFilters($department) ?: [1, 2, 3, 4, 5, 6, 7, 8];
$discipline_filters = getCoordinatorDisciplineFilters($department);

$coordFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semester_filter_all
];

// 1. Total Academic Strength
$studentCount = (int)$studentModel->getTotalAcademicStrength($coordFilters);

// 2. Department student USNs for scoping queries
$students = $studentModel->getAllWithUsers($coordFilters);
$deptStudentUsns = [];
foreach ($students as $s) {
    $usn = trim((string)($s['usn'] ?? ''));
    if (!empty($usn)) {
        $deptStudentUsns[] = $usn;
    }
}
$deptStudentUsns = array_values(array_unique(array_filter($deptStudentUsns)));
if (empty($deptStudentUsns)) {
    $deptStudentUsns = ['__NONE__'];
}
$usnPlaceholders = implode(',', array_fill(0, count($deptStudentUsns), '?'));

// 3. Active Tasks created by coordinator
$totalTasks = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM coordinator_tasks WHERE coordinator_id = ?");
    $stmt->execute([$coordinatorId]);
    $totalTasks = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

// 4. Department Integrity Violations
$totalViolations = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM assessment_integrity_events WHERE student_id IN ($usnPlaceholders)");
    $stmt->execute($deptStudentUsns);
    $totalViolations = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

// 5. Department Student Feedback Count & Recent Entries
$totalFeedback = 0;
$feedbacks = [];
if (!empty($discipline_filters)) {
    try {
        $placeholders = implode(',', array_fill(0, count($discipline_filters), '?'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM portal_feedback WHERE branch IN ($placeholders)");
        $stmt->execute($discipline_filters);
        $totalFeedback = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT * FROM portal_feedback WHERE branch IN ($placeholders) ORDER BY created_at DESC LIMIT 5");
        $stmt->execute($discipline_filters);
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-maroon-dark: #600000;
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
        
        .main-content { 
            max-width: 1320px;
            margin: 0 auto;
            padding: 40px 28px 80px 28px;
        }
        
        .page-header { 
            margin-bottom: 30px; 
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 22px;
        }
        
        .page-header h2 { 
            font-size: 30px; 
            color: var(--primary-maroon); 
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        
        .page-header p { 
            color: var(--text-muted); 
            font-size: 14px; 
            font-weight: 500;
        }

        /* Top 4 KPI Cards */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }

        .kpi-card {
            background: var(--white);
            padding: 22px 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 18px;
            transition: var(--transition);
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .kpi-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .kpi-info h3 {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }

        .kpi-info p {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
        }

        /* Section Headings */
        .section-heading {
            font-size: 13.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 32px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-heading i {
            color: var(--primary-maroon);
            font-size: 15px;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background: var(--white);
            padding: 24px 22px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.04);
            text-decoration: none;
            color: var(--text-main);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(128, 0, 0, 0.15);
        }
        
        .action-card.primary {
            background: linear-gradient(135deg, var(--primary-maroon), var(--primary-maroon-dark));
            color: var(--white);
            border: none;
        }
        
        .action-card.primary:hover {
            box-shadow: 0 15px 30px rgba(128, 0, 0, 0.25);
        }

        .action-card.primary .action-icon {
            color: var(--primary-gold);
        }
        
        .action-card.highlight {
            background: linear-gradient(135deg, #059669, #047857);
            color: var(--white);
            border: none;
        }
        
        .action-card.highlight:hover {
            box-shadow: 0 15px 30px rgba(5, 150, 105, 0.25);
        }

        .action-card.highlight .action-icon {
            color: #a7f3d0;
        }

        .action-icon {
            font-size: 24px;
            color: var(--primary-maroon);
            transition: var(--transition);
        }
        
        .action-card:hover .action-icon {
            transform: scale(1.1);
        }
        
        .action-title {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.2px;
        }
        
        .action-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.45;
        }
        
        .action-card.primary .action-desc,
        .action-card.highlight .action-desc {
            color: rgba(255, 255, 255, 0.85);
        }
        
        /* Feedback Section */
        .feedback-section {
            background: var(--white);
            padding: 30px 28px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.03);
            margin-top: 40px;
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 14px;
        }
        
        .feedback-header h3 {
            font-size: 17px;
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
            padding: 14px 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            vertical-align: middle;
        }
        
        .feedback-table tr:hover td {
            background-color: #fdfafa;
        }
        
        .feedback-student-name {
            font-weight: 700;
            color: var(--text-main);
            font-size: 13.5px;
        }
        
        .feedback-student-meta {
            font-size: 11px;
            color: var(--text-light);
            margin-top: 2px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h2>Dashboard</h2>
            <p><?php echo htmlspecialchars($deptLabel); ?> • Semesters <?php echo min($semester_filter_all) . '-' . max($semester_filter_all); ?></p>
        </div>

        <!-- 4 Balanced KPI Cards -->
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fdf2f2; color:var(--primary-maroon);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="kpi-info">
                    <h3>Total Students</h3>
                    <p><?php echo number_format($studentCount); ?></p>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eff6ff; color:#2563eb;">
                    <i class="fas fa-list-check"></i>
                </div>
                <div class="kpi-info">
                    <h3>Assigned Tasks</h3>
                    <p><?php echo number_format($totalTasks); ?></p>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fef2f2; color:#dc2626;">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="kpi-info">
                    <h3>Integrity Violations</h3>
                    <p><?php echo number_format($totalViolations); ?></p>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fffbeb; color:#d97706;">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="kpi-info">
                    <h3>Student Feedback</h3>
                    <p><?php echo number_format($totalFeedback); ?></p>
                </div>
            </div>
        </div>

        <!-- Section 1: Academic & Assessment Management -->
        <div class="section-heading">
            <i class="fas fa-tasks"></i> Academic & Assessment Operations
        </div>
        <div class="quick-actions-grid">
            <a href="assign_task.php" class="action-card primary">
                <div class="action-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="action-title">Assign Tasks</div>
                <div class="action-desc">Assign assessments, quizzes & vivas to student batches</div>
            </a>

            <a href="manage_tasks.php" class="action-card">
                <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                <div class="action-title">Manage Tasks</div>
                <div class="action-desc">Track student submission progress, scores & deadlines</div>
            </a>

            <a href="add_aptitude.php" class="action-card">
                <div class="action-icon"><i class="fas fa-circle-question"></i></div>
                <div class="action-title">Add Aptitude Questions</div>
                <div class="action-desc">Create multiple choice quantitative and reasoning questions</div>
            </a>

            <a href="add_coding.php" class="action-card">
                <div class="action-icon"><i class="fas fa-code"></i></div>
                <div class="action-title">Add Coding Problems</div>
                <div class="action-desc">Create programming challenges with test cases</div>
            </a>
        </div>

        <!-- Section 2: AI Monitoring & Proctoring -->
        <div class="section-heading">
            <i class="fas fa-shield-halved"></i> AI Monitoring & Proctoring Audit
        </div>
        <div class="quick-actions-grid">
            <a href="proctoring.php" class="action-card">
                <div class="action-icon" style="color:#dc2626;"><i class="fas fa-shield-halved"></i></div>
                <div class="action-title">AI Proctoring Audit</div>
                <div class="action-desc">Review cheating telemetry, snapshot evidence & strike logs</div>
            </a>

            <a href="ai_monitor.php" class="action-card">
                <div class="action-icon" style="color:#0284c7;"><i class="fas fa-robot"></i></div>
                <div class="action-title">Live Student Monitor</div>
                <div class="action-desc">Real-time student portal activity and online monitoring</div>
            </a>
        </div>

        <!-- Section 3: Performance & Placements -->
        <div class="section-heading">
            <i class="fas fa-chart-pie"></i> Performance & Placements
        </div>
        <div class="quick-actions-grid">
            <a href="analytics.php?reset=1" class="action-card">
                <div class="action-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="action-title">Department Analytics</div>
                <div class="action-desc">Comprehensive batch performance metrics & skill breakdowns</div>
            </a>

            <a href="leaderboard.php" class="action-card">
                <div class="action-icon" style="color:var(--primary-gold-dark);"><i class="fas fa-trophy"></i></div>
                <div class="action-title">Student Leaderboard</div>
                <div class="action-desc">Department and institutional student rankings</div>
            </a>

            <a href="jobs.php" class="action-card highlight">
                <div class="action-icon"><i class="fas fa-briefcase"></i></div>
                <div class="action-title">Jobs & Internships</div>
                <div class="action-desc">Track company placement drives & student applications</div>
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
                                    <?php if (!empty($fb['new_feature_title'])): ?>
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
