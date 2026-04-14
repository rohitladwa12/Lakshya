<?php
/**
 * Assigned Task Details Page
 * Students view task details and launch assessment
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);

$username = getUsername();
$institution = getInstitution();
$db = getDB();

// Handle POST from Dashboard
if (isPost() && isset($_POST['id'])) {
    SessionFilterHelper::setFilters('student_task_details', ['id' => $_POST['id']]);
    header("Location: assigned_task.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('student_task_details');
$taskId = isset($filters['id']) ? (int)$filters['id'] : 0;

if (!$taskId) {
    header('Location: dashboard.php');
    exit;
}

// Fetch task details
$stmt = $db->prepare("SELECT ct.*, dc.full_name as coordinator_name, dc.department
                      FROM coordinator_tasks ct
                      JOIN dept_coordinators dc ON ct.coordinator_id = dc.id
                      WHERE ct.id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    header('Location: dashboard.php');
    exit;
}

// Check if already completed
$stmt = $db->prepare("SELECT * FROM task_completions WHERE task_id = ? AND student_id = ?");
$stmt->execute([$taskId, $username]);
$completion = $stmt->fetch(PDO::FETCH_ASSOC);

// Build redirect URL based on task type
$redirectPages = [
    'aptitude' => 'ai_aptitude_test.php',
    'technical' => 'ai_technical_round.php',
    'hr' => 'ai_hr_round.php'
];
$redirectPage = $redirectPages[$task['task_type']] ?? 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($task['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #D4AF37;
            --white: #ffffff;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Outfit', sans-serif; 
            background: var(--bg-light); 
            color: var(--text-main); 
        }

        .navbar-spacer { height: 70px; }

        .container { 
            max-width: 800px; 
            margin: 40px auto; 
            padding: 0 20px; 
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .task-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
        }

        .task-type-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .task-type-badge.aptitude { background: #e3f2fd; color: #1976d2; }
        .task-type-badge.technical { background: #ffebee; color: #c62828; }
        .task-type-badge.hr { background: #e8f5e9; color: #2e7d32; }

        .task-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-maroon);
            margin-bottom: 12px;
        }

        .task-meta {
            color: var(--text-muted);
            font-size: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .info-item {
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .info-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .description {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 32px;
            line-height: 1.8;
        }

        .btn-start {
            width: 100%;
            background: var(--primary-maroon);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
        }

        .btn-start:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128,0,0,0.3);
        }

        .completion-card {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            padding: 32px;
            border-radius: 16px;
            text-align: center;
        }

        .completion-icon {
            font-size: 64px;
            color: #155724;
            margin-bottom: 16px;
        }

        .completion-title {
            font-size: 24px;
            font-weight: 700;
            color: #155724;
            margin-bottom: 12px;
        }

        .score-display {
            font-size: 48px;
            font-weight: 800;
            margin: 20px 0;
        }

        .score-high { color: #155724; }
        .score-medium { color: #856404; }
        .score-low { color: #721c24; }

        .completion-meta {
            color: #155724;
            font-size: 15px;
            margin-top: 16px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .deadline-warning {
            background: #fff3cd;
            color: #856404;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <div class="navbar-spacer"></div>
    
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <?php if ($completion): ?>
            <!-- Task Already Completed -->
            <div class="completion-card">
                <div class="completion-icon">✅</div>
                <div class="completion-title">Task Completed!</div>
                
                <?php 
                $scoreClass = 'score-medium';
                if ($completion['score'] >= 75) $scoreClass = 'score-high';
                elseif ($completion['score'] < 50) $scoreClass = 'score-low';
                ?>
                
                <div class="score-display <?php echo $scoreClass; ?>">
                    <?php echo number_format($completion['score'], 1); ?>%
                </div>
                
                <div class="completion-meta">
                    <p><strong>Completed On:</strong> <?php echo date('d M Y, h:i A', strtotime($completion['completed_at'])); ?></p>
                    <p><strong>Time Taken:</strong> <?php echo round($completion['time_taken'] / 60, 1); ?> minutes</p>
                    <p style="margin-top: 16px; font-size: 14px;">
                        Great job! Your coordinator can see your performance.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <!-- Task Not Yet Completed -->
            <div class="card">
                <div class="task-header">
                    <span class="task-type-badge <?php echo $task['task_type']; ?>">
                        <?php echo strtoupper($task['task_type']); ?> ROUND
                    </span>
                    <h1 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h1>
                    <div class="task-meta">
                        Assigned by: <strong><?php echo htmlspecialchars($task['coordinator_name']); ?></strong>
                        (<?php echo htmlspecialchars($task['department']); ?> Department)
                    </div>
                </div>

                <?php 
                $deadline = strtotime($task['deadline']);
                $now = time();
                $hoursLeft = ($deadline - $now) / 3600;
                if ($hoursLeft < 24 && $hoursLeft > 0): 
                ?>
                <div class="deadline-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Deadline approaching! <?php echo round($hoursLeft); ?> hours left</span>
                </div>
                <?php endif; ?>

                <div class="info-grid">
                    <?php if ($task['company_name']): ?>
                    <div class="info-item">
                        <div class="info-label">Company</div>
                        <div class="info-value"><?php echo htmlspecialchars($task['company_name']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="info-label">Deadline</div>
                        <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($task['deadline'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Question Type</div>
                        <div class="info-value"><?php echo $task['question_source'] === 'manual' ? 'Custom Questions' : 'AI Generated'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Assessment Type</div>
                        <div class="info-value"><?php echo ucfirst($task['task_type']); ?></div>
                    </div>
                </div>

                <?php if ($task['description']): ?>
                <div class="description">
                    <strong>Instructions:</strong><br>
                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo $redirectPage; ?>">
                    <input type="hidden" name="company" value="<?php echo htmlspecialchars($task['company_name'] ?: 'General'); ?>">
                    <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                    <button type="submit" class="btn-start" style="width:100%; cursor:pointer;">
                        <i class="fas fa-play-circle"></i>
                        Start Assessment
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
