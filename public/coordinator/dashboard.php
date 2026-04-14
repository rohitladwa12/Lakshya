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
$semester_filter = getCoordinatorSemesterFilters($department) ?: [1,8];
$discipline_filters = getCoordinatorDisciplineFilters($department);

$coordFilters = [
    'discipline' => $discipline_filters,
    'semesters' => $semester_filter
];
$students = $studentModel->getAllWithUsers($coordFilters);
$studentCount = count($students);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard - <?php echo APP_NAME; ?></title>
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
    
    <div class="navbar-spacer"></div>

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

            <a href="analytics.php" class="action-card secondary">
                <div class="action-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="action-title">Department Analytics</div>
                <div class="action-desc">Track department progress</div>
            </a>

            <!-- <a href="manage_tasks.php" class="action-card secondary">
                <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                <div class="action-title">Manage Tasks</div>
                <div class="action-desc">Track student progress</div>
            </a> -->

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
    </div>

</body>
</html>
