<?php
/**
 * Student Aptitude Practice Page
 * Shows questions added by coordinators
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\SessionFilterHelper;

// Require student role
requireRole(ROLE_STUDENT);

$fullName = getFullName();
$db = getDB();

// Handle filter via POST
if (isPost() && isset($_POST['company'])) {
    SessionFilterHelper::setFilters('aptitude_practice', [
        'company' => $_POST['company'] ?: ''
    ]);
    header("Location: aptitude_practice.php");
    exit;
}

// Fetch unique companies to filter
$stmt = $db->query("SELECT DISTINCT company_name FROM manual_aptitude_questions ORDER BY company_name");
$companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch questions (with session filter)
$filters = SessionFilterHelper::getFilters('aptitude_practice');
$companyFilter = $filters['company'] ?? '';
$sql = "SELECT * FROM manual_aptitude_questions";
$params = [];
if (!empty($companyFilter)) {
    $sql .= " WHERE company_name = ?";
    $params[] = $companyFilter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aptitude Library - <?php echo APP_NAME; ?></title>
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
            --shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-light); color: var(--text-main); }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .page-header { margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h2 { font-size: 32px; color: var(--primary-maroon); font-weight: 800; }
        
        .filters { background: white; padding: 20px; border-radius: 20px; box-shadow: var(--shadow); margin-bottom: 30px; display: flex; gap: 15px; align-items: center; }
        .filter-select { padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-family: inherit; font-size: 14px; min-width: 200px; }

        .question-card { background: white; padding: 30px; border-radius: 24px; box-shadow: var(--shadow); margin-bottom: 25px; border-left: 6px solid var(--primary-gold); transition: transform 0.3s; }
        .question-card:hover { transform: translateY(-3px); }
        .company-badge { background: #fee2e2; color: #b91c1c; padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 800; text-transform: uppercase; display: inline-block; margin-bottom: 15px; }
        .question-text { font-size: 18px; font-weight: 600; margin-bottom: 25px; line-height: 1.6; color: #0f172a; }
        
        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .option { padding: 15px 20px; background: #f1f5f9; border: 2px solid transparent; border-radius: 14px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 10px; font-weight: 500; }
        .option:hover { background: #e2e8f0; border-color: #cbd5e0; }
        .option.correct { background: #dcfce7 !important; border-color: #22c55e !important; color: #166534 !important; }
        .option.wrong { background: #fee2e2 !important; border-color: #ef4444 !important; color: #991b1b !important; }
        
        .explanation-btn { background: none; border: none; color: var(--primary-maroon); font-weight: 700; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px; margin-top: 10px; }
        .explanation-box { display: none; margin-top: 20px; padding: 20px; background: #fffbeb; border-radius: 16px; border: 1px dashed #f59e0b; color: #92400e; font-size: 14px; line-height: 1.5; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; white-space: pre-wrap; word-break: break-word; }
        
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }
        .empty-state i { font-size: 50px; margin-bottom: 20px; opacity: 0.3; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h2>Aptitude Library</h2>
                <p style="color: var(--text-muted); margin-top: 5px;">Practice questions shared by your coordinators and past placements.</p>
            </div>
            <a href="dashboard" style="text-decoration: none; color: var(--primary-maroon); font-weight: 700; font-size: 14px;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="filters">
            <span style="font-weight: 700; font-size: 14px; color: var(--text-muted);">FILTER BY COMPANY:</span>
            <form id="filterForm" method="POST" action="aptitude_practice.php" style="display: contents;">
                <select name="company" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?php echo htmlspecialchars($comp); ?>" <?php echo $companyFilter === $comp ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($comp); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if (!empty($companyFilter)): ?>
                <form method="POST" action="aptitude_practice.php" style="display: contents;">
                    <input type="hidden" name="company" value="">
                    <button type="submit" style="background: none; border: none; font-size: 12px; color: #ef4444; font-weight: 600; cursor: pointer; padding: 0;">Clear Filter</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($questions)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No aptitude questions found for this criteria.</p>
            </div>
        <?php else: ?>
            <?php foreach ($questions as $q): ?>
                <div class="question-card" id="q-<?php echo $q['id']; ?>">
                    <span class="company-badge"><?php echo htmlspecialchars($q['company_name']); ?></span>
                    <div class="question-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
                    
                    <div class="options-grid">
                        <div class="option" onclick="checkAnswer(this, 'A', '<?php echo $q['correct_option']; ?>', <?php echo $q['id']; ?>)">
                            <strong>A.</strong> <?php echo htmlspecialchars($q['option_a']); ?>
                        </div>
                        <div class="option" onclick="checkAnswer(this, 'B', '<?php echo $q['correct_option']; ?>', <?php echo $q['id']; ?>)">
                            <strong>B.</strong> <?php echo htmlspecialchars($q['option_b']); ?>
                        </div>
                        <div class="option" onclick="checkAnswer(this, 'C', '<?php echo $q['correct_option']; ?>', <?php echo $q['id']; ?>)">
                            <strong>C.</strong> <?php echo htmlspecialchars($q['option_c']); ?>
                        </div>
                        <div class="option" onclick="checkAnswer(this, 'D', '<?php echo $q['correct_option']; ?>', <?php echo $q['id']; ?>)">
                            <strong>D.</strong> <?php echo htmlspecialchars($q['option_d']); ?>
                        </div>
                    </div>

                    <button class="explanation-btn" onclick="toggleExplanation(<?php echo $q['id']; ?>)">
                        <i class="fas fa-lightbulb"></i> View Explanation
                    </button>
                    <div class="explanation-box" id="exp-<?php echo $q['id']; ?>">
                        <strong style="display: block; margin-bottom: 8px; font-family: 'Outfit', sans-serif;">Correct Answer: Option <?php echo $q['correct_option']; ?></strong>
                        <?php echo htmlspecialchars($q['explanation'] ?: 'No explanation available.'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function checkAnswer(element, selected, correct, qId) {
            // Prevent multiple clicks
            const card = document.getElementById('q-' + qId);
            if (card.classList.contains('answered')) return;
            card.classList.add('answered');

            const options = card.querySelectorAll('.option');
            options.forEach(opt => {
                const optText = opt.innerText.trim();
                const optLetter = optText.split('.')[0];
                
                if (optLetter === correct) {
                    opt.classList.add('correct');
                } else if (optLetter === selected && selected !== correct) {
                    opt.classList.add('wrong');
                }
            });

            // Auto-show explanation on answer
            setTimeout(() => {
                toggleExplanation(qId, true);
            }, 500);
        }

        function toggleExplanation(qId, forceShow = false) {
            const exp = document.getElementById('exp-' + qId);
            if (forceShow) {
                exp.style.display = 'block';
            } else {
                exp.style.display = exp.style.display === 'block' ? 'none' : 'block';
            }
        }
    </script>
</body>
</html>

