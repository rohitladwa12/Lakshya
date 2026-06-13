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
            --white: #ffffff;
            --bg-light: #f8fafc;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
        }
        
        .navbar-spacer { height: 70px; }
        
        .main-content { 
            /* Layout handled by navbar.php */
        }
        
        .page-header { 
            margin-bottom: 40px; 
        }
        
        .page-header h2 { 
            font-size: 32px; 
            color: var(--primary-maroon); 
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .page-header p { 
            color: #64748b; 
            font-size: 15px; 
        }
        
        .stats-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-maroon), #600000);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .stats-info h3 {
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .stats-info p {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary-maroon);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }
        
        .action-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            text-decoration: none;
            color: #1e293b;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .action-card.primary {
            background: linear-gradient(135deg, var(--primary-maroon), #600000);
            color: white;
        }
        
        .action-card.secondary {
            background: linear-gradient(135deg, var(--primary-gold), #c4a137);
            color: #1e293b;
        }
        
        .action-icon {
            font-size: 28px;
            opacity: 0.9;
        }
        
        .action-title {
            font-size: 16px;
            font-weight: 700;
        }
        
        .action-desc {
            font-size: 13px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    
    <!-- <div class="navbar-spacer"></div> -->

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
        </div>

        <!-- Recent Student Feedback Card -->
        <div style="background: white; padding: 24px; border-radius: 16px; box-shadow: var(--shadow); margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">
                <h3 style="font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-comments" style="color: var(--primary-maroon);"></i> Recent Student Feedback
                </h3>
                <a href="feedback.php" style="color: var(--primary-maroon); font-weight: 700; font-size: 13px; text-decoration: none;">View All Feedback →</a>
            </div>
            
            <?php if (empty($feedbacks)): ?>
                <div style="text-align: center; padding: 20px; color: #64748b;">
                    <p style="font-size: 14px; font-weight: 500;">No student feedback received yet.</p>
                </div>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Student</th>
                            <th style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Comments</th>
                            <th style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Suggested Feature</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $fb): ?>
                            <tr>
                                <td style="padding: 14px 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px;">
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($fb['student_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                        <?php echo htmlspecialchars(($fb['institution'] ?? 'GMU') . (($fb['current_sem'] ?? null) ? ' • Sem ' . $fb['current_sem'] : '')); ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #64748b; line-height: 1.4;">
                                    <?php echo $fb['general_comments'] ? htmlspecialchars(substr($fb['general_comments'], 0, 80)) . (strlen($fb['general_comments']) > 80 ? '...' : '') : '<span style="font-style:italic;opacity:0.6;">None</span>'; ?>
                                </td>
                                <td style="padding: 14px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #64748b; line-height: 1.4;">
                                    <?php if ($fb['new_feature_title']): ?>
                                        <strong style="color: var(--primary-maroon);"><?php echo htmlspecialchars($fb['new_feature_title']); ?></strong>
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

