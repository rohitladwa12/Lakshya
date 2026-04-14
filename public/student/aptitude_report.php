<?php
/**
 * Unified AI Assessment Report
 * Displays results for Aptitude, Technical, and HR assessments
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Require student role
use App\Helpers\SessionFilterHelper;

requireLogin();

$userId = getUserId();
$studentIdForDb = getStudentIdForAssessment();
$db = getDB();

// Handle POST from assessment completion or dashboard
if (isPost() && isset($_POST['id'])) {
    SessionFilterHelper::setFilters('aptitude_report', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: aptitude_report.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('aptitude_report');
$id = $filters['id'] ?? 0;

// Fetch assessment details (student_id is USN for GMIT, user_id for GMU)
$stmt = $db->prepare("SELECT * FROM unified_ai_assessments WHERE id = ? AND student_id = ?");
$stmt->execute([$id, $studentIdForDb]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    die("Assessment record not found or access denied.");
}

$details = json_decode($assessment['details'], true) ?? [];
$questions = $details['questions'] ?? [];
$userAnswers = $details['user_answers'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assessment Report - <?php echo htmlspecialchars($assessment['company_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #800000;
            --secondary: #e9c66f;
            --dark: #1a1a1a;
            --card-bg: #262626;
            --white: #ffffff;
            --gray: #888888;
            --success: #27ae60;
            --error: #e74c3c;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: #121212; color: var(--white); line-height: 1.6; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, #4a0000 100%);
            padding: 40px;
            border-radius: 24px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .header h1 { font-size: 2.5rem; color: var(--secondary); margin-bottom: 10px; }
        .header p { opacity: 0.8; font-size: 1.1rem; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .stat-card .label { color: var(--gray); font-size: 0.9rem; text-transform: uppercase; margin-bottom: 10px; }
        .stat-card .value { font-size: 2.5rem; font-weight: 700; color: var(--secondary); }

        .section {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
        }

        .section-title { font-size: 1.5rem; color: var(--secondary); margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }

        .meta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .meta-item { background: rgba(255,255,255,0.03); padding: 15px 20px; border-radius: 12px; }
        .meta-item span { color: var(--gray); font-size: 0.85rem; display: block; }
        .meta-item strong { font-size: 1.1rem; }

        .question-list { display: flex; flex-direction: column; gap: 20px; }
        .question-item {
            background: rgba(255,255,255,0.02);
            padding: 25px;
            border-radius: 16px;
            border-left: 5px solid transparent;
        }
        .question-item.correct { border-left-color: var(--success); }
        .question-item.wrong { border-left-color: var(--error); }

        .q-header { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .q-text { font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; }
        
        .options-review { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .opt { padding: 12px 20px; border-radius: 10px; background: rgba(255,255,255,0.05); font-size: 0.95rem; }
        .opt.user-choice { border: 1px solid #fff; }
        .opt.correct-choice { background: rgba(39, 174, 96, 0.2); color: var(--success); font-weight: 600; }
        .opt.wrong-choice { background: rgba(231, 76, 60, 0.2); color: var(--error); }

        .explanation { margin-top: 20px; padding: 15px; background: rgba(233, 198, 111, 0.05); border-radius: 10px; font-size: 0.9rem; border: 1px dashed var(--secondary); }

        .btn-group { display: flex; justify-content: center; gap: 20px; margin-top: 40px; }
        .btn { padding: 15px 35px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--secondary); color: var(--dark); }
        .btn-outline { border: 1px solid var(--white); color: var(--white); }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }

        @media (max-width: 768px) {
            .meta-info, .options-review { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <p>AI ASSESSMENT PERFORMANCE</p>
        <h1><?php echo htmlspecialchars($assessment['assessment_type']); ?> Round</h1>
        <p><?php echo htmlspecialchars($assessment['company_name']); ?> • Completed on <?php echo date('d M Y, h:i A', strtotime($assessment['completed_at'])); ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Overall Score</div>
            <div class="value"><?php echo round($assessment['score']); ?>%</div>
        </div>
        <div class="stat-card">
            <div class="label">Total Questions</div>
            <div class="value"><?php echo $assessment['total_marks']; ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Status</div>
            <div class="value" style="color: var(--success); font-size: 1.5rem;"><?php echo strtoupper($assessment['status']); ?></div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title"><i class="fas fa-user-graduate"></i> Candidate Information</h2>
        <div class="meta-info">
            <div class="meta-item">
                <span>FullName</span>
                <strong><?php echo htmlspecialchars($assessment['student_name']); ?></strong>
            </div>
            <div class="meta-item">
                <span>USN / ID</span>
                <strong><?php echo htmlspecialchars($assessment['usn']); ?></strong>
            </div>
            <div class="meta-item">
                <span>Branch / Department</span>
                <strong><?php echo htmlspecialchars($assessment['branch'] ?: 'N/A'); ?></strong>
            </div>
            <div class="meta-item">
                <span>Current Semester</span>
                <strong><?php echo htmlspecialchars($assessment['current_sem'] ?: 'N/A'); ?></strong>
            </div>
        </div>
    </div>

    <?php if (!empty($questions)): ?>
    <div class="section">
        <h2 class="section-title"><i class="fas fa-list-check"></i> Question Review</h2>
        <div class="question-list">
            <?php foreach ($questions as $idx => $q): 
                $userAns = $userAnswers[$idx] ?? -1;
                $correctAns = $q['answer'];
                $isCorrect = ($userAns == $correctAns);
            ?>
            <div class="question-item <?php echo $isCorrect ? 'correct' : 'wrong'; ?>">
                <div class="q-header">
                    <span>Question <?php echo $idx + 1; ?></span>
                    <span style="color: <?php echo $isCorrect ? 'var(--success)' : 'var(--error)'; ?>; font-weight: 700;">
                        <?php echo $isCorrect ? 'CORRECT' : 'INCORRECT'; ?>
                    </span>
                </div>
                <div class="q-text"><?php echo htmlspecialchars($q['question']); ?></div>
                <div class="options-review">
                    <?php foreach ($q['options'] as $oIdx => $opt): 
                        $class = '';
                        if ($oIdx == $correctAns) $class = 'correct-choice';
                        elseif ($oIdx == $userAns) $class = 'wrong-choice user-choice';
                    ?>
                    <div class="opt <?php echo $class; ?>">
                        <?php echo htmlspecialchars($opt); ?>
                        <?php if ($oIdx == $userAns) echo ' <small>(Your Answer)</small>'; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($q['explanation'])): ?>
                <div class="explanation">
                    <strong>EXPLANATION:</strong><br>
                    <?php echo htmlspecialchars($q['explanation']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="btn-group">
        <a href="company_ai_prep" class="btn btn-outline">Back to Prep Portal</a>
        <a href="dashboard" class="btn btn-primary">Go to Dashboard</a>
        <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print Report</button>
    </div>
</div>

</body>
</html>
