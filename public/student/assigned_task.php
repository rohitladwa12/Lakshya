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
// Fetch student profile unconditionally to resolve dual-key identifiers
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$checkModel = new StudentProfile();
$userId = getUserId();
$history = $checkModel->getAcademicHistory($userId, $institution ?: 'GMU');
$mainProfile = $history[0] ?? null;

$studentIdentifiers = array_unique(array_filter([$username, $mainProfile['usn'] ?? '', $mainProfile['aadhar'] ?? '']));

// Check if already completed
$tcPlaceholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
$stmt = $db->prepare("SELECT * FROM task_completions WHERE task_id = ? AND student_id IN ($tcPlaceholders)");
$stmt->execute(array_merge([$taskId], $studentIdentifiers));
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
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
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
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); 
            color: var(--text-main); 
            min-height: 100vh;
        }

        .navbar-spacer { height: 70px; }

        .container { 
            max-width: 800px; 
            margin: 40px auto; 
            padding: 0 20px; 
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .task-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 32px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .task-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }

        .task-type-badge.aptitude { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .task-type-badge.technical { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .task-type-badge.hr { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        .difficulty-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .difficulty-badge.low { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .difficulty-badge.medium { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
        .difficulty-badge.high { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .task-title {
            font-size: 36px;
            font-weight: 800;
            color: var(--primary-maroon);
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .task-meta {
            color: var(--text-muted);
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 100px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .info-item {
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .info-item:hover {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
            transform: translateY(-2px);
        }

        .info-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
        }

        .concept-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }

        .concept-tag:hover {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
            transform: scale(1.05);
        }

        .description {
            padding: 24px;
            background: #fffbeb;
            border-left: 4px solid var(--primary-gold);
            border-radius: 16px;
            margin-bottom: 40px;
            line-height: 1.8;
            font-size: 15px;
            color: #451a03;
        }

        .btn-start {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, #600000 100%);
            color: white;
            padding: 20px 32px;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
            box-shadow: 0 10px 25px -5px rgba(128, 0, 0, 0.3);
        }

        .btn-start:hover {
            background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px rgba(128, 0, 0, 0.4);
        }

        .completion-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #bbf7d0;
            padding: 40px;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 20px 40px -15px rgba(22, 163, 74, 0.1);
            margin-bottom: 24px;
        }

        .completion-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .completion-title {
            font-size: 26px;
            font-weight: 800;
            color: #14532d;
            margin-bottom: 12px;
        }

        .score-display {
            font-size: 56px;
            font-weight: 900;
            margin: 24px 0;
            letter-spacing: -1px;
        }

        .score-high { color: #15803d; }
        .score-medium { color: #b45309; }
        .score-low { color: #b91c1c; }

        .completion-meta {
            color: #166534;
            font-size: 15px;
            margin-top: 16px;
            line-height: 1.6;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 24px;
            padding: 10px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        .back-link:hover {
            transform: translateX(-4px);
            color: #600000;
            box-shadow: 0 6px 12px -1px rgba(0,0,0,0.1);
        }

        .deadline-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #b45309;
            padding: 16px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.05);
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

        <!-- Task Info Card (Always Visible) -->
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

            <div class="info-grid">
                <?php if ($task['company_name']): ?>
                <div class="info-item">
                    <div class="info-label">Company</div>
                    <div class="info-value"><?php echo htmlspecialchars($task['company_name']); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">Difficulty</div>
                    <div class="info-value">
                        <span class="difficulty-badge <?php echo strtolower($task['difficulty'] ?? 'medium'); ?>">
                            <i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i>
                            <?php echo htmlspecialchars($task['difficulty'] ?? 'Medium'); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Deadline</div>
                    <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($task['deadline'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Question Type</div>
                    <div class="info-value"><?php echo $task['question_source'] === 'manual' ? 'Custom Questions' : 'AI Generated'; ?></div>
                </div>
                
                <?php if (!empty($task['concept'])): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <div class="info-label">Target Concepts</div>
                    <div class="concepts-list" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                        <?php 
                        $concepts = array_map('trim', explode(',', $task['concept']));
                        foreach ($concepts as $c): 
                            if (empty($c)) continue;
                        ?>
                            <span class="concept-tag">#<?php echo htmlspecialchars($c); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($task['description']): ?>
            <div class="description">
                <strong>Instructions:</strong><br>
                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($completion): ?>
            <!-- Task Already Completed Details -->
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
            <!-- Task Launch Button -->
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

            <?php $isExpired = $deadline < $now; ?>
            <form method="POST" action="<?php echo $redirectPage; ?>">
                <input type="hidden" name="company" value="<?php echo htmlspecialchars($task['company_name'] ?: 'General'); ?>">
                <input type="hidden" name="concept" value="<?php echo htmlspecialchars($task['concept'] ?: ''); ?>">
                <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                <?php if ($isExpired): ?>
                    <button type="button" class="btn-start" style="width:100%; cursor:not-allowed; background: #64748b; opacity: 0.7;" disabled>
                        <i class="fas fa-clock-rotate-left"></i>
                        DEADLINE EXPIRED
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn-start" style="width:100%; cursor:pointer;">
                        <i class="fas fa-play-circle"></i>
                        Start Assessment
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

