<?php
/**
 * Coding Problem Detail Page
 * HackerRank-style interface with tabbed bottom panel and collapsible accordion problem pane.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\SessionFilterHelper;

requireRole(ROLE_STUDENT);

$userId = getUserId();

// Handle POST from coding_practice.php
if (isPost() && isset($_POST['id'])) {
    SessionFilterHelper::setFilters('coding_problem', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: coding_problem.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('coding_problem');
$problemId = $filters['id'] ?? 0;

if (!$problemId) {
    redirect('coding_practice.php');
}

// Fetch problem details
$db = getDB();
$stmt = $db->prepare("SELECT * FROM coding_problems WHERE id = ?");
$stmt->execute([$problemId]);
$problem = $stmt->fetch();

if (!$problem) {
    Session::flash('error', 'Problem not found');
    redirect('coding_practice.php');
}

// Get student's progress
require_once __DIR__ . '/../../src/Models/StudentProfile.php';
$studentModel = new StudentProfile();
$studentProfile = $studentModel->getProfile($userId);
$institution = $studentProfile['institution'] ?? INSTITUTION_GMU;

// Get correct student ID
function getStudentIdForCoding($studentProfile, $institution, $userId) {
    if ($institution === INSTITUTION_GMIT) {
        if (!empty($studentProfile['id']) && $studentProfile['id'] != 0) {
            return $studentProfile['id'];
        } else if (!empty($studentProfile['usn'])) {
            return $studentProfile['usn'];
        } else if (!empty($studentProfile['student_id']) && 
            $studentProfile['student_id'] != '0' && 
            $studentProfile['student_id'] != 0) {
            return $studentProfile['student_id'];
        } else {
            return $userId;
        }
    } else {
        return $userId;
    }
}

$studentId = getStudentIdForCoding($studentProfile, $institution, $userId);

$stmt = $db->prepare("SELECT * FROM student_coding_progress 
                      WHERE student_id = ? AND institution = ? AND problem_id = ?");
$stmt->execute([$studentId, $institution, $problemId]);
$progress = $stmt->fetch();

$savedCode = $progress['code_submitted'] ?? '';
$savedLanguage = strtolower($progress['language_used'] ?? 'javascript');

// Solved and total problems count for achievements overlay
$stmtSolved = $db->prepare("SELECT COUNT(*) FROM student_coding_progress WHERE student_id = ? AND institution = ? AND status = 'solved'");
$stmtSolved->execute([$studentId, $institution]);
$solvedCount = $stmtSolved->fetchColumn();

$totalProblems = $db->query("SELECT COUNT(*) FROM coding_problems")->fetchColumn();

$difficulty = strtolower($problem['difficulty']);
$xpEarned = 10;
if ($difficulty === 'medium') $xpEarned = 20;
if ($difficulty === 'hard') $xpEarned = 30;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problem #<?php echo $problem['id']; ?>: <?php echo htmlspecialchars($problem['title']); ?></title>
    
    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --bg: #f4f5f7;
            --border-color: #e2e8f0;
            --text-dark: #2d3748;
            --text-muted: #718096;
            --terminal-bg: #111118;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Outfit', sans-serif; 
            background: var(--bg); 
            color: var(--text-dark); 
            overflow: hidden; 
            height: 100vh;
        }

        .ide-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 65px);
            padding: 15px;
            gap: 15px;
            box-sizing: border-box;
        }

        /* Top Toolbar */
        .ide-toolbar {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            flex-shrink: 0;
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .toolbar-title {
            font-weight: 800;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .difficulty-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .difficulty-badge.easy {
            background: #e6fffa;
            color: #319795;
        }
        .difficulty-badge.medium {
            background: #feebc8;
            color: #dd6b20;
        }
        .difficulty-badge.hard {
            background: #fed7d7;
            color: #e53e3e;
        }

        .toolbar-select {
            padding: 6px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            outline: none;
            font-weight: 600;
            color: var(--text-dark);
            background: white;
            cursor: pointer;
        }

        .tb-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            color: var(--text-dark);
            font-size: 0.85rem;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .tb-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e0;
        }

        .tb-btn-primary {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
        }
        .tb-btn-primary:hover {
            background: #600000;
        }

        .tb-btn-success {
            background: #2f855a;
            color: white;
            border-color: #2f855a;
        }
        .tb-btn-success:hover {
            background: #276749;
        }

        /* 3-Column Split Layout */
        .workspace-split {
            display: flex;
            flex: 1;
            min-height: 0;
            gap: 15px;
        }

        /* Left Panel - Problem Statement & Metadata */
        .panel-problem {
            width: 30%;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .prob-tab-btn {
            background: transparent;
            border: none;
            border-bottom: 2.5px solid transparent;
            padding: 14px 16px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
            flex: 1;
            text-align: center;
        }
        .prob-tab-btn:hover {
            color: var(--text-dark);
            background: rgba(0,0,0,0.01);
        }
        .prob-tab-btn.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
            font-weight: 700;
        }

        .example-block {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            margin-top: 6px;
            line-height: 1.5;
            color: #2d3748;
        }

        /* Middle Panel - Editor and Console Output */
        .panel-middle {
            width: 48%;
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-height: 0;
        }

        /* Center Panel - Code Editor */
        .panel-editor {
            flex: 1.1;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 15px;
            position: relative;
            min-height: 0;
        }

        .CodeMirror {
            flex: 1;
            height: 100% !important;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.88rem;
            line-height: 1.55;
        }

        /* Inline CodeMirror Widgets */
        .inline-warning-widget {
            background: #fff5f5;
            border-left: 3.5px solid #e53e3e;
            padding: 6px 12px;
            font-size: 0.82rem;
            color: #c53030;
            margin: 4px 10px;
            border-radius: 4px;
            font-family: 'Outfit', sans-serif;
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideDownWidget 0.2s ease-out;
        }
        @keyframes slideDownWidget {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .inline-warning-widget .close-btn {
            margin-left: auto;
            cursor: pointer;
            font-weight: bold;
            color: #9b2c2c;
            font-size: 0.9rem;
        }

        /* Right Panel - AI Coach & Output Console */
        .panel-right-sidebar {
            width: 22%;
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-height: 0;
        }

        .sidebar-box {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .console-tab-btn {
            background: transparent;
            border: none;
            border-bottom: 2.5px solid transparent;
            padding: 12px 14px;
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
            flex: 1;
            text-align: center;
        }
        .console-tab-btn:hover {
            color: var(--text-dark);
        }
        .console-tab-btn.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
            font-weight: 700;
        }

        /* Solution Language Tabs */
        .sol-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }
        .sol-tab {
            padding: 4px 10px;
            background: #edf2f7;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .sol-tab.active {
            background: var(--primary-maroon);
            color: white;
        }
        .sol-code-block {
            display: none;
        }
        .sol-code-block.active {
            display: block;
        }

        /* More dropdown styling */
        .more-dropdown {
            position: relative;
            display: inline-block;
        }
        .more-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 180px;
        }
        .menu-item {
            width: 100%;
            text-align: left;
            padding: 10px 15px;
            background: none;
            border: none;
            font-size: 0.85rem;
            cursor: pointer;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .menu-item:hover {
            background: #f7fafc;
        }

        /* Success Overlay */
        .success-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(6px);
        }
        .success-modal {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            width: 480px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: modalScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalScaleUp {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .success-modal h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 20px;
        }
        .success-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 25px 0;
            text-align: left;
        }
        .success-stat-card {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            padding: 12px;
            border-radius: 8px;
        }
        .success-stat-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .success-stat-val {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        /* Testcase Cards */
        .tc-card {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .tc-card.active {
            border-color: var(--primary-maroon);
            background: rgba(128, 0, 0, 0.02);
            color: var(--primary-maroon);
        }
        .tc-badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .tc-badge-dot.passed { background: #38a169; }
        .tc-badge-dot.failed { background: #e53e3e; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <!-- Success Overlay -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-modal">
            <div style="font-size: 3.5rem; color: #38a169; margin-bottom: 15px;"><i class="fas fa-check-circle"></i></div>
            <h2>Problem Solved</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: -10px;">All test cases successfully passed.</p>
            
            <div class="success-stats">
                <div class="success-stat-card">
                    <div class="success-stat-label">Time Spent</div>
                    <div class="success-stat-val" id="overlayTimeVal">00:00</div>
                </div>
                <div class="success-stat-card">
                    <div class="success-stat-label">Language</div>
                    <div class="success-stat-val" style="text-transform: capitalize;" id="overlayLangVal">Python</div>
                </div>
                <div class="success-stat-card">
                    <div class="success-stat-label">Library Progress</div>
                    <div class="success-stat-val"><?php echo $solvedCount; ?> / <?php echo $totalProblems; ?> Solved</div>
                </div>
                <div class="success-stat-card">
                    <div class="success-stat-label">Achievement</div>
                    <div class="success-stat-val" style="color: #dd6b20;"><i class="fas fa-bolt"></i> +<?php echo $xpEarned; ?> XP</div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 25px;">
                <button class="tb-btn tb-btn-success" style="justify-content: center; padding: 12px;" onclick="goToNextProblem()">
                    Next Problem <i class="fas fa-arrow-right"></i>
                </button>
                <div style="display: flex; gap: 10px;">
                    <button class="tb-btn" style="flex: 1; justify-content: center;" onclick="closeSuccessOverlay()">Continue Solving</button>
                    <button class="tb-btn" style="flex: 1; justify-content: center;" onclick="window.location.href='coding_practice.php'">Library</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ide-container">
        <!-- Top Toolbar -->
        <div class="ide-toolbar">
            <div class="toolbar-left">
                <a href="coding_practice.php" class="tb-btn" style="margin-right: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; color: var(--text-dark); border: 1px solid var(--border-color); background: #fff;">
                    <i class="fas fa-arrow-left"></i> Problems
                </a>
                <span class="toolbar-title" style="font-size: 1.05rem;"><?php echo htmlspecialchars($problem['title']); ?></span>
                <span class="difficulty-badge <?php echo strtolower($problem['difficulty']); ?>">
                    <?php echo $problem['difficulty']; ?>
                </span>
                
                <span class="timer-badge-inline" id="timerBadgeInline" style="margin-left: 15px; font-size: 0.82rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px;">
                    <i class="far fa-clock"></i> <span id="timerText">00:00:00</span>
                </span>
            </div>

            <div class="toolbar-right">
                <select id="languageSelect" onchange="changeLanguage()" class="toolbar-select" style="height: 36px; padding: 0 10px; font-size: 0.85rem;">
                    <option value="javascript" <?php echo $savedLanguage === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                    <option value="python" <?php echo $savedLanguage === 'python' ? 'selected' : ''; ?>>Python</option>
                    <option value="java" <?php echo $savedLanguage === 'java' ? 'selected' : ''; ?>>Java</option>
                    <option value="c" <?php echo $savedLanguage === 'c' ? 'selected' : ''; ?>>C</option>
                    <option value="cpp" <?php echo $savedLanguage === 'cpp' ? 'selected' : ''; ?>>C++</option>
                </select>

                <button class="tb-btn tb-btn-primary" onclick="runCode()" style="height: 36px;"><i class="fas fa-play"></i> Run</button>
                <button class="tb-btn tb-btn-success" onclick="submitSolution()" style="height: 36px;"><i class="fas fa-check-circle"></i> Submit</button>
                
                <!-- More Dropdown -->
                <div class="more-dropdown">
                    <button class="tb-btn" onclick="toggleMoreMenu()" style="height: 36px; padding: 0 12px;"><i class="fas fa-ellipsis-v"></i> More</button>
                    <div class="more-menu" id="moreMenu">
                        <button class="menu-item" onclick="saveProgress(); toggleMoreMenu();"><i class="fas fa-save"></i> Save Code</button>
                        <button class="menu-item" onclick="resetEditor(); toggleMoreMenu();"><i class="fas fa-undo"></i> Reset Code</button>
                        <button class="menu-item" onclick="reportCodingProblemIssue(); toggleMoreMenu();" style="color: #c53030; font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> Report Evaluation</button>
                        <hr style="margin: 0; border: none; border-top: 1px solid var(--border-color);">
                        <button class="menu-item" id="practiceModeToggleMenu" onclick="togglePracticeMode(); toggleMoreMenu();"><i class="fas fa-graduation-cap"></i> Mode: Learning</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upper Workspace Split (Problem & Editor) -->
        <div class="workspace-split">
            <!-- Left Panel: Tabbed Problem -->
            <div class="panel-problem">
                <div class="problem-tabs-bar" style="display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                    <button class="prob-tab-btn active" onclick="switchProblemTab('desc')">Description</button>
                    <button class="prob-tab-btn" onclick="switchProblemTab('examples')">Examples</button>
                    <button class="prob-tab-btn" onclick="switchProblemTab('editorial')">Editorial</button>
                </div>
                <div class="problem-tabs-content" style="flex: 1; overflow-y: auto; padding: 18px; box-sizing: border-box;">
                    <!-- Tab: Description -->
                    <div class="prob-tab-pane" id="prob-pane-desc">
                        <p style="white-space: pre-wrap; color: #2d3748; line-height: 1.6; font-size: 0.92rem; margin-bottom: 15px;"><?php echo htmlspecialchars($problem['problem_statement']); ?></p>
                        
                        <!-- Constraints inline -->
                        <div style="border-top: 1px solid var(--border-color); margin-top: 15px; padding-top: 15px;">
                            <h5 style="font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">🔒 Constraints</h5>
                            <?php if ($problem['constraints']): ?>
                            <pre style="white-space: pre-wrap; font-family: monospace; font-size: 0.85rem; color:#4a5568; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color);"><?php echo htmlspecialchars($problem['constraints']); ?></pre>
                            <?php else: ?>
                            <p style="color: var(--text-muted); font-size: 0.85rem;">No constraints specified.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab: Examples -->
                    <div class="prob-tab-pane" id="prob-pane-examples" style="display: none;">
                        <h5 style="font-weight: 700; color: var(--text-dark); margin-bottom: 10px;">💡 Sample Cases</h5>
                        <?php if ($problem['example_input'] && $problem['example_output']): ?>
                        <div class="example-block" style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; font-family: monospace; font-size: 0.85rem; line-height: 1.5;">
                            <strong style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase;">Sample Input:</strong>
                            <pre style="margin: 4px 0 12px 0; font-family: inherit; font-size: inherit; color: var(--text-dark); white-space: pre-wrap;"><?php echo htmlspecialchars($problem['example_input']); ?></pre>
                            
                            <strong style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase;">Sample Output:</strong>
                            <pre style="margin: 4px 0 0 0; font-family: inherit; font-size: inherit; color: var(--text-dark); white-space: pre-wrap;"><?php echo htmlspecialchars($problem['example_output']); ?></pre>
                        </div>
                        <?php else: ?>
                        <p style="color: var(--text-muted);">No sample examples listed.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: Editorial -->
                    <div class="prob-tab-pane" id="prob-pane-editorial" style="display: none;">
                        <h5 style="font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">📖 Concept Explanation</h5>
                        <p style="color: #4a5568; line-height: 1.5; font-size: 0.88rem; margin-bottom: 15px;"><?php echo nl2br(htmlspecialchars($problem['concept_explanation'])); ?></p>
                        
                        <!-- Collapsible Progressive Hints -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 15px; margin-bottom: 15px;">
                            <h5 style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">🗝️ Progressive Hints</h5>
                            <div id="hintRevealWrapper">
                                <button class="tb-btn" style="width: 100%; justify-content: center; font-size:0.8rem;" onclick="revealHintInline()">
                                    <i class="far fa-lightbulb"></i> Need a hint? Reveal Hint
                                </button>
                            </div>
                            <div id="hintListContainer" style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;"></div>
                        </div>

                        <!-- Reveal Full Solution -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 15px;">
                            <button class="tb-btn" style="padding:6px 12px; font-size:0.8rem; justify-content:center; width: 100%;" onclick="viewSolution()">
                                <i class="fas fa-eye"></i> Reveal Full Solution
                            </button>
                            <div id="solutionContent" style="display:none; font-size:0.85rem; margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Panel: Editor & Output/Console -->
            <div class="panel-middle">
                <!-- Center Panel: Editor -->
                <div class="panel-editor">
                    <div class="editor-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
                            <span id="editorLanguageLabel" style="text-transform: capitalize;">JavaScript</span>
                            <span style="color: var(--text-muted); font-weight: 400;">|</span>
                            <span style="color: var(--text-muted);">Problem: <?php echo htmlspecialchars($problem['title']); ?></span>
                        </div>
                        <div id="saveStatusIndicator" style="font-size: 0.78rem; font-weight: 600; color: #38a169; display: flex; align-items: center; gap: 4px;">
                            <i class="fas fa-check-circle"></i> Saved
                        </div>
                    </div>
                    <div style="flex: 1; min-height: 0; position: relative;">
                        <textarea id="codeEditor"><?php echo htmlspecialchars($savedCode); ?></textarea>
                    </div>
                </div>

                <!-- Output & Console Panel -->
                <div class="sidebar-box console-box" style="flex: 1; background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; display: flex; flex-direction: column; overflow: hidden;">
                    <div class="sidebar-box-tabs" style="display: flex; background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                        <button class="console-tab-btn active" onclick="switchConsoleTab('output')">Output</button>
                        <button class="console-tab-btn" onclick="switchConsoleTab('custom')">Custom Input</button>
                    </div>

                    <div class="sidebar-box-body" style="flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; box-sizing: border-box; min-height: 0;">
                        <!-- Tab content: Output Console -->
                        <div class="console-tab-pane active" id="console-pane-output" style="display: flex; flex-direction: column; gap: 8px; flex: 1; min-height: 0;">
                            
                            <!-- Test Case cards -->
                            <div id="testCaseCardsContainer" style="display:flex; gap:6px; flex-wrap:wrap; display:none; margin-bottom: 12px;"></div>
                            
                            <!-- Terminal display console -->
                            <div id="outputConsole" style="flex: 1; background: var(--terminal-bg); color: #edf2f7; font-family: monospace; padding: 14px; border-radius: 6px; overflow: hidden; font-size: 0.85rem; line-height: 1.5; white-space: pre-wrap; min-height: 120px;">Console is empty. Click "Run" to execute tests.</div>
                            
                            <!-- Complexity details (Contextual: shown after Run) -->
                            <div id="runComplexityContainer" style="display:none; margin-top: 5px; border-top: 1px solid var(--border-color); padding-top: 8px;">
                                <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 2px;">⚡ Complexity Analysis</div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:0.82rem; font-weight:600; color:var(--text-dark);">
                                        Time: <span id="complexityTimeVal" style="color:var(--primary-maroon);">O(1)</span> | Space: <span id="complexitySpaceVal" style="color:var(--primary-maroon);">O(1)</span>
                                    </span>
                                    <button class="tb-btn" style="padding: 2px 6px; font-size: 0.72rem; border-color:#e9c66f; color:#b7791f; background:#fefcbf; font-weight:700;" onclick="triggerComplexityAdvice()">Improve? Yes</button>
                                </div>
                                <div id="complexityAdviceText" style="display:none; font-size:0.78rem; color:#744210; margin-top:5px; background:#fffaf0; padding:6px; border-radius:4px; border: 1px solid #fbd38d;"></div>
                            </div>

                            <!-- Execution timeline -->
                            <div id="timelineContainer" style="display:none; border-top: 1px solid var(--border-color); padding-top: 8px;">
                                <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">⏳ Run History</div>
                                <div id="timelineHistoryList" style="display:flex; flex-direction:column; gap:4px; max-height:80px; overflow-y:auto; font-size:0.75rem;"></div>
                            </div>

                            <!-- Compilation error AI explanation -->
                            <div id="aiConsoleExplanation" style="background:#fff5f5; border: 1.5px solid #feb2b2; border-radius: 6px; padding: 10px; display: none; overflow-y: auto; max-height: 140px; box-shrink: 0; box-sizing: border-box;">
                                <h6 style="color: #c53030; font-weight: 700; font-size: 0.8rem; margin: 0 0 4px 0; display: flex; align-items: center; gap: 5px;"><i class="fas fa-robot"></i> <span>AI Explanation</span></h6>
                                <div id="aiConsoleExplanationText" style="font-size: 0.78rem; line-height: 1.4; color: #742a2a;"></div>
                            </div>
                        </div>

                        <!-- Tab content: Custom Input -->
                        <div class="console-tab-pane" id="console-pane-custom" style="display: none; flex-direction: column; flex: 1; min-height: 0;">
                            <textarea id="customInput" placeholder="Enter custom inputs here..." style="width: 100%; flex: 1; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px; font-family: monospace; font-size: 0.82rem; outline: none; resize: none; box-sizing: border-box; min-height: 100px;"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: AI Coach -->
            <div class="panel-right-sidebar">
                <!-- AI Coach Card (Sidebar version) -->
                <div class="sidebar-box coach-box" style="flex: 1; background: #ffffff; border: 1px solid var(--border-color); border-radius: 8px; display: flex; flex-direction: column; overflow: hidden;">
                    <div class="sidebar-box-header" style="background: #f8fafc; border-bottom: 1px solid var(--border-color); padding: 10px 15px; font-weight: 700; font-size: 0.85rem; color: var(--text-dark); display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fas fa-robot" style="color: var(--primary-maroon);"></i> Coach</span>
                        <span id="coachStatusBadge" class="status-badge-inline" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; background: #e6fffa; color: #319795; font-weight: 700;">🤖 Watching...</span>
                    </div>
                    
                    <div class="sidebar-box-body" style="flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 12px; box-sizing: border-box;">
                        
                        <!-- Checklist (Contextual Statuses) -->
                        <div id="coachChecklist" style="display:flex; flex-direction:column; gap:8px;">
                            <div id="check-syntax" style="font-size:0.82rem; font-weight:600; color:#38a169;"><i class="fas fa-check-circle"></i> Syntax</div>
                            <div id="check-variables" style="font-size:0.82rem; font-weight:600; color:#38a169;"><i class="fas fa-check-circle"></i> Variables</div>
                            <div id="check-logic" style="font-size:0.82rem; font-weight:600; color:#38a169;"><i class="fas fa-check-circle"></i> Logic</div>
                        </div>

                        <!-- Single-line tips suggestion box -->
                        <div class="coach-section" id="coachTipSection" style="display:none; border-top:1px solid #edf2f7; padding-top:10px;">
                            <div class="coach-title-sm" style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">💡 Suggestion</div>
                            <div id="coachRealtimeTip" style="font-size: 0.82rem; color: #4a5568; line-height: 1.4;"></div>
                        </div>

                        <!-- AI Confidence Badge -->
                        <div id="aiConfidenceWidget" style="display:none; justify-content: space-between; align-items: center; background: #f8fafc; border: 1px solid var(--border-color); padding: 8px; border-radius: 6px; font-size: 0.78rem;">
                            <span style="font-weight: 700; color: var(--text-muted);"><i class="fas fa-shield-alt"></i> Confidence</span>
                            <span id="aiConfidenceVal" style="font-weight: 800; color: #2f855a; background:#e6fffa; padding:2px 6px; border-radius:4px;">96%</span>
                        </div>

                        <!-- Personalized Tutor popup block -->
                        <div id="personalizedCoachBlock" style="margin-top: 12px; background: #ebf8ff; border: 1.5px solid #bee3f8; border-radius: 6px; padding: 10px; display:none;">
                            <div style="font-size: 0.75rem; font-weight: 800; color: #2b6cb0; text-transform: uppercase; margin-bottom: 4px;"><i class="fas fa-magic"></i> Personalized Tutor</div>
                            <div id="personalizedCoachMsg" style="font-size: 0.8rem; color: #2d3748; line-height: 1.4;"></div>
                            <button class="tb-btn" style="width: 100%; margin-top: 8px; justify-content: center; padding: 5px; font-size: 0.78rem; background:#3182ce; color:white; border-color:#3182ce;" onclick="explainPersonalizedStruggle()">Yes, explain in 30s</button>
                        </div>
                    </div>
                </div>
        </div>
    </div>

    <!-- CodeMirror JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>

    <script>
        const problemId = <?php echo $problemId; ?>;
        let editor;
        let hintLevel = 1;
        let cachedFeedback = null;
        let previousLanguage = 'javascript';
        let timerInterval;

        // Interactive AI Coach variables
        let typingDebounceTimer;
        let lastAnalyzedCode = '';
        let consecutiveCompilerErrors = 0;
        let consecutiveWrongAnswers = 0;
        let activeLineWidgets = [];
        const expectedOutputVal = <?php echo json_encode(trim($problem['example_output'])); ?>;
        let practiceMode = 'learning';
        
        // Timeline & Time-Tracking states
        let runHistory = [];
        let timeElapsedSeconds = 0;
        let timeTrackerInterval;

        const starterCodesLearning = {
            'javascript': 'function solve(input) {\n    // Write your logic here\n    // Example: return input.split(\'\').reverse().join(\'\');\n    return input;\n}',
            'python': 'def solve(input_string):\n    # Write your logic here\n    # Example: return input_string[::-1]\n    return input_string',
            'java': 'class Solution {\n    public static String solve(String input) {\n        // Write your logic here\n        return input;\n    }\n}',
            'c': '#include <stdio.h>\n#include <string.h>\n\nvoid solve(char* input) {\n    // Write your logic here\n    printf("%s", input);\n}',
            'cpp': '#include <string>\nusing namespace std;\n\nstring solve(string input) {\n    // Write your logic here\n    return input;\n}'
        };

        const starterCodesCompetitive = {
            'javascript': 'const fs = require(\'fs\');\nconst input = fs.readFileSync(0, \'utf-8\').trim();\n\n// Write your competitive code here\nconsole.log(input);',
            'python': 'import sys\n\ndef main():\n    input_data = sys.stdin.read().strip()\n    # Write your competitive code here\n    print(input_data)\n\nif __name__ == "__main__":\n    main()',
            'java': 'import java.util.Scanner;\n\nclass Solution {\n    public static void main(String[] args) {\n        Scanner scanner = new Scanner(System.in);\n        if (scanner.hasNextLine()) {\n            String input = scanner.nextLine().trim();\n            // Write your competitive code here\n            System.out.println(input);\n        }\n        scanner.close();\n    }\n}',
            'c': '#include <stdio.h>\n#include <string.h>\n\nint main() {\n    char input[1000];\n    if (fgets(input, sizeof(input), stdin)) {\n        // Write your competitive code here\n        printf("%s", input);\n    }\n    return 0;\n}',
            'cpp': '#include <iostream>\n#include <string>\nusing namespace std;\n\nint main() {\n    string input;\n    if (getline(cin, input)) {\n        // Write your competitive code here\n        cout << input << endl;\n    }\n    return 0;\n}'
        };

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize CodeMirror
            editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
                mode: 'javascript',
                theme: 'monokai',
                lineNumbers: true,
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false,
                lineWrapping: true
            });

            // Sync with saved language state
            const savedLang = <?php echo json_encode($savedLanguage); ?>;
            document.getElementById('languageSelect').value = savedLang;
            document.getElementById('editorLanguageLabel').textContent = savedLang;
            previousLanguage = savedLang;
            
            const modes = {
                'javascript': 'javascript',
                'python': 'python',
                'java': 'text/x-java',
                'c': 'text/x-csrc',
                'cpp': 'text/x-c++src'
            };
            editor.setOption('mode', modes[savedLang] || 'javascript');

            // Load saved code or starter template
            const savedCode = <?php echo json_encode($savedCode); ?>;
            if (savedCode) {
                editor.setValue(savedCode);
            } else {
                setStarterCode(savedLang);
            }

            // Init elapsed timer
            initTimer();
            startTimeTracker();

            // Set initial timer visibility based on learning mode
            updateTimerVisibility();

            // Watch editor changes for debounced suggestions & live updates
            editor.on('change', () => {
                triggerAutosaveBadge();
                
                // Debounce real-time hint check: 2.5 seconds pause before AI Suggestion
                clearTimeout(typingDebounceTimer);
                typingDebounceTimer = setTimeout(() => {
                    triggerLocalAnalysis();
                }, 2500);
            });

            // Trigger initial UI setup for problem tabs
            switchProblemTab('desc');
            switchConsoleTab('output');
        });

        // Live Autosave badge transitions
        let autosaveTimer;
        function triggerAutosaveBadge() {
            const badge = document.getElementById('saveStatusIndicator');
            if (!badge) return;
            badge.innerHTML = '<i class="fas fa-pencil-alt"></i> Edited';
            badge.style.color = '#718096';
            
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(async () => {
                badge.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                badge.style.color = '#3182ce';
                
                const response = await saveProgress();
                if (response && response.success) {
                    badge.innerHTML = '<i class="fas fa-check-circle"></i> Saved';
                    badge.style.color = '#38a169';
                }
            }, 1500);
        }

        // Tab switches
        function switchProblemTab(tabId) {
            document.querySelectorAll('.prob-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.prob-tab-pane').forEach(pane => pane.style.display = 'none');

            const tabIndexMap = { 'desc': 0, 'examples': 1, 'editorial': 2 };
            const activeIndex = tabIndexMap[tabId];
            const btn = document.querySelectorAll('.prob-tab-btn')[activeIndex];
            if (btn) btn.classList.add('active');

            const pane = document.getElementById(`prob-pane-${tabId}`);
            if (pane) pane.style.display = 'block';
        }

        function switchConsoleTab(tabId) {
            document.querySelectorAll('.console-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.console-tab-pane').forEach(pane => pane.style.display = 'none');

            const tabIndexMap = { 'output': 0, 'custom': 1 };
            const activeIndex = tabIndexMap[tabId];
            const btn = document.querySelectorAll('.console-tab-btn')[activeIndex];
            if (btn) btn.classList.add('active');

            const pane = document.getElementById(`console-pane-${tabId}`);
            if (pane) {
                if (tabId === 'output') {
                    pane.style.display = 'flex';
                } else {
                    pane.style.display = 'block';
                }
            }
        }

        // More Dropdown toggling
        function toggleMoreMenu() {
            const menu = document.getElementById('moreMenu');
            if (menu.style.display === 'none' || !menu.style.display) {
                menu.style.display = 'block';
            } else {
                menu.style.display = 'none';
            }
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.more-dropdown');
            const menu = document.getElementById('moreMenu');
            if (dropdown && !dropdown.contains(e.target) && menu) {
                menu.style.display = 'none';
            }
        });

        // HTML escaping helper
        function escapeHtml(str) {
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Inline widgets clear/show
        function clearInlineWidgets() {
            activeLineWidgets.forEach(w => w.clear());
            activeLineWidgets = [];
        }

        function showInlineComment(lineNum, text, type = 'warning') {
            const lineIndex = Math.max(0, lineNum - 1);
            const widgetEl = document.createElement('div');
            widgetEl.className = 'inline-warning-widget';
            
            widgetEl.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>${escapeHtml(text)}</span>
                <span class="close-btn" onclick="this.parentElement.remove()">×</span>
            `;
            
            const widget = editor.addLineWidget(lineIndex, widgetEl, {
                coverGutter: false,
                noHScroll: true
            });
            activeLineWidgets.push(widget);
        }

        // Dynamic Progressive Hints
        async function revealHintInline() {
            const container = document.getElementById('hintListContainer');
            const wrapper = document.getElementById('hintRevealWrapper');
            if (!container || !wrapper) return;

            // Create temporary spinner element
            const spinnerEl = document.createElement('div');
            spinnerEl.id = 'hint-loading-spinner';
            spinnerEl.style = 'padding: 10px; font-size: 0.82rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;';
            spinnerEl.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Analyzing code and generating Hint #${hintLevel}...`;
            container.appendChild(spinnerEl);

            // Disable button during load
            const btn = wrapper.querySelector('button');
            if (btn) btn.disabled = true;

            try {
                const code = editor.getValue();
                const language = document.getElementById('languageSelect').value;
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'mentor_feedback',
                        problem_id: problemId,
                        code: code,
                        language: language,
                        hint_level: hintLevel,
                        request_type: 'hint'
                    })
                });
                const data = await response.json();

                // Remove spinner
                const spinner = document.getElementById('hint-loading-spinner');
                if (spinner) spinner.remove();
                if (btn) btn.disabled = false;

                if (data.success && data.parsed) {
                    const dynamicHintText = data.parsed.hint || data.parsed.learning_feedback || "Try walking through your logic step-by-step with sample inputs.";
                    
                    const hintEl = document.createElement('div');
                    hintEl.className = 'hint-item-revealed';
                    hintEl.style = 'background: #fffaf0; border-left: 3px solid #dd6b20; padding: 12px; border-radius: 6px; font-size: 0.85rem; color: #7b341e; line-height: 1.5; margin-bottom: 8px; border: 1px solid #fbd38d;';
                    hintEl.innerHTML = `<strong>Hint Level ${hintLevel}:</strong> ${dynamicHintText}`;
                    container.appendChild(hintEl);
                    
                    hintLevel++;
                    if (hintLevel > 6) {
                        // Max hints reached
                        if (wrapper) wrapper.style.display = 'none';
                    }
                } else {
                    const errEl = document.createElement('div');
                    errEl.style = 'color: #e53e3e; font-size: 0.82rem; padding: 8px;';
                    errEl.textContent = 'Could not generate hint. Please try again.';
                    container.appendChild(errEl);
                    setTimeout(() => errEl.remove(), 3000);
                }
            } catch (err) {
                const spinner = document.getElementById('hint-loading-spinner');
                if (spinner) spinner.remove();
                if (btn) btn.disabled = false;
                
                const errEl = document.createElement('div');
                errEl.style = 'color: #e53e3e; font-size: 0.82rem; padding: 8px;';
                errEl.textContent = 'Connection error. Please try again.';
                container.appendChild(errEl);
                setTimeout(() => errEl.remove(), 3000);
            }
        }

        // Local Code Analyzer
        function localCodeAnalyze(code, language) {
            const issues = [];
            const lowerCode = code.toLowerCase();

            // 1. Division by zero check
            const divZeroRegex = /\/\s*0(?!\d)/g;
            let match;
            while ((match = divZeroRegex.exec(code)) !== null) {
                const line = code.substring(0, match.index).split('\n').length;
                issues.push({
                    line: line,
                    message: "Potential division by zero detected.",
                    type: "warning"
                });
            }

            // 2. Infinite while loop check
            if (lowerCode.includes('while') && (lowerCode.includes('while(true') || lowerCode.includes('while (true') || lowerCode.includes('while(1') || lowerCode.includes('while (1'))) {
                if (!lowerCode.includes('break')) {
                    issues.push({
                        line: 1,
                        message: "Infinite while loop pattern detected (missing a break statement inside the body).",
                        type: "warning"
                    });
                }
            }

            // 3. Unused variables
            if (language === 'javascript') {
                const varRegex = /(?:let|const|var)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=/g;
                while ((match = varRegex.exec(code)) !== null) {
                    const varName = match[1];
                    const escVar = varName.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
                    const refRegex = new RegExp('\\b' + escVar + '\\b', 'g');
                    const references = code.match(refRegex);
                    if (references && references.length === 1) {
                        const line = code.substring(0, match.index).split('\n').length;
                        issues.push({
                            line: line,
                            message: `Unused variable '${varName}'. consider clean-up if not needed.`,
                            type: "info"
                        });
                    }
                }
            }

            return issues;
        }

        // Live Complexity Local Engine
        function estimateComplexityLocal(code) {
            const lines = code.split('\n');
            let maxDepth = 0;
            let currentDepth = 0;
            
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                if (/\b(for|while|foreach)\b/.test(line)) {
                    currentDepth++;
                    if (currentDepth > maxDepth) maxDepth = currentDepth;
                }
                if (line.includes('}')) {
                    currentDepth = Math.max(0, currentDepth - 1);
                }
            }
            
            let timeEst = "O(1)";
            let spaceEst = "O(1)";
            let explanation = "Constant execution time detected.";
            
            if (maxDepth === 1) {
                timeEst = "O(N)";
                explanation = "Linear loops detected. Time scales linearly with the input size.";
            } else if (maxDepth === 2) {
                timeEst = "O(N²)";
                explanation = "Nested loops detected. Watch out for quadratic performance scaling.";
            } else if (maxDepth > 2) {
                timeEst = "O(N³)";
                explanation = "Deeply nested loops detected. High timeout risk.";
            }
            
            if (code.includes('new Map') || code.includes('new Set') || code.includes('{}') || code.includes('dict()')) {
                spaceEst = "O(N)";
                explanation += " Uses auxiliary map/set memory for storage.";
            }
            
            return { time: timeEst, space: spaceEst, explanation: explanation };
        }

        // Trigger Local and/or Background AI analysis (interrupt-less one-liner)
        async function triggerLocalAnalysis() {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;
            
            if (code === lastAnalyzedCode) return;
            lastAnalyzedCode = code;

            clearInlineWidgets();
            const localIssues = localCodeAnalyze(code, language);
            
            const tipSection = document.getElementById('coachTipSection');
            const tipEl = document.getElementById('coachRealtimeTip');

            if (localIssues.length > 0) {
                localIssues.forEach(issue => {
                    showInlineComment(issue.line, issue.message, issue.type);
                });
                
                // Show single warning check in Coach status
                updateCoachPanelCheck('logic', false);
                if (tipSection && tipEl) {
                    tipSection.style.display = 'block';
                    tipEl.innerHTML = `Check line ${localIssues[0].line}: ${localIssues[0].message}`;
                }
            } else {
                updateCoachPanelCheck('logic', true);
                if (tipSection) tipSection.style.display = 'none';
            }
        }

        // Coach UI status checks modifier
        function updateCoachPanelCheck(checkType, isPassed) {
            const checkEl = document.getElementById(`check-${checkType}`);
            if (checkEl) {
                if (isPassed) {
                    checkEl.innerHTML = `<i class="fas fa-check-circle"></i> ${checkType.charAt(0).toUpperCase() + checkType.slice(1)}`;
                    checkEl.style.color = '#38a169';
                } else {
                    checkEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${checkType.charAt(0).toUpperCase() + checkType.slice(1)}`;
                    checkEl.style.color = '#e53e3e';
                }
            }
        }

        // Elapsed upward timer
        function initTimer() {
            let startTime = Date.now();
            timerInterval = setInterval(() => {
                let elapsed = Date.now() - startTime;
                let seconds = Math.floor((elapsed / 1000) % 60);
                let minutes = Math.floor((elapsed / (1000 * 60)) % 60);
                let hours = Math.floor((elapsed / (1000 * 60 * 60)) % 24);
                
                document.getElementById('timerText').textContent = 
                    (hours < 10 ? '0' + hours : hours) + ':' +
                    (minutes < 10 ? '0' + minutes : minutes) + ':' +
                    (seconds < 10 ? '0' + seconds : seconds);
            }, 1000);
        }

        // Time tracking & Personalized tutor trigger
        function startTimeTracker() {
            timeTrackerInterval = setInterval(() => {
                timeElapsedSeconds++;
                
                // Trigger Personalized Tutor advice if they've struggled for > 3 minutes (180s)
                if (timeElapsedSeconds === 180 || (consecutiveCompilerErrors + consecutiveWrongAnswers >= 3 && timeElapsedSeconds > 90)) {
                    triggerPersonalizedTutorSuggestion();
                }
            }, 1000);
        }

        function triggerPersonalizedTutorSuggestion() {
            const block = document.getElementById('personalizedCoachBlock');
            const msg = document.getElementById('personalizedCoachMsg');
            if (block && msg) {
                msg.textContent = "You've been working on this for a bit. Would you like a 30-second conceptual explanation on boundary checks?";
                block.style.display = 'block';
            }
        }

        function explainPersonalizedStruggle() {
            const block = document.getElementById('personalizedCoachBlock');
            if (block) {
                block.innerHTML = `
                    <div style="font-size:0.75rem; font-weight:800; color:#2b6cb0; text-transform:uppercase; margin-bottom:4px;"><i class="fas fa-magic"></i> 30s Explanation</div>
                    <div style="font-size:0.8rem; color:#2d3748; line-height:1.4; font-style:italic;">
                        "When indexing, always verify that your bounds match the bounds of the array length (e.g. index < len). If loop runs up to index <= len, it accesses an extra undefined element."
                    </div>
                    <button class="tb-btn" style="width: 100%; margin-top:8px; justify-content:center; padding:4px;" onclick="document.getElementById('personalizedCoachBlock').style.display='none'">Got it!</button>
                `;
            }
        }

        function updateTimerVisibility() {
            const timerBadge = document.getElementById('timerBadgeInline');
            if (timerBadge) {
                timerBadge.style.display = practiceMode === 'learning' ? 'none' : 'inline-flex';
            }
        }

        function getStarterCodes() {
            return practiceMode === 'learning' ? starterCodesLearning : starterCodesCompetitive;
        }

        function togglePracticeMode() {
            if (confirm('Toggling mode will reset the editor to load the starter template for the new mode. Do you want to continue?')) {
                const btn = document.getElementById('practiceModeToggleMenu');
                const language = document.getElementById('languageSelect').value;
                
                if (practiceMode === 'learning') {
                    practiceMode = 'competitive';
                    if (btn) btn.innerHTML = '<i class="fas fa-trophy"></i> Mode: Competitive';
                } else {
                    practiceMode = 'learning';
                    if (btn) btn.innerHTML = '<i class="fas fa-graduation-cap"></i> Mode: Learning';
                }

                updateTimerVisibility();
                editor.setValue('');
                setStarterCode(language);
            }
        }

        function changeLanguage() {
            const language = document.getElementById('languageSelect').value;
            document.getElementById('editorLanguageLabel').textContent = language;
            const modes = {
                'javascript': 'javascript',
                'python': 'python',
                'java': 'text/x-java',
                'c': 'text/x-csrc',
                'cpp': 'text/x-c++src'
            };

            const starterCodes = getStarterCodes();
            const currentCode = editor.getValue().trim();
            const starterValues = Object.values(starterCodes);
            const isEmpty = currentCode === '';
            const isStarter = starterValues.some(val => val.replace(/\s+/g, '') === currentCode.replace(/\s+/g, ''));

            if (isEmpty || isStarter) {
                editor.setOption('mode', modes[language]);
                editor.setValue(starterCodes[language]);
                previousLanguage = language;
            } else {
                if (confirm('Switching language will replace your current editor content with the starter template for ' + language + '. Do you want to proceed?')) {
                    editor.setOption('mode', modes[language]);
                    editor.setValue(starterCodes[language]);
                    previousLanguage = language;
                } else {
                    document.getElementById('languageSelect').value = previousLanguage;
                }
            }
        }

        function setStarterCode(language) {
            const starterCodes = getStarterCodes();
            if (!editor.getValue() || editor.getValue().trim() === '') {
                editor.setValue(starterCodes[language]);
            }
        }

        // Show execution timeline
        function renderExecutionTimeline() {
            const timelineContainer = document.getElementById('timelineContainer');
            const historyList = document.getElementById('timelineHistoryList');
            if (!timelineContainer || !historyList) return;
            
            timelineContainer.style.display = 'block';
            historyList.innerHTML = '';
            
            // Render latest runs first
            [...runHistory].reverse().forEach(run => {
                const statusColor = run.status === 'Passed' ? '#38a169' : '#e53e3e';
                const el = document.createElement('div');
                el.style = 'display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #edf2f7; padding: 4px 0;';
                el.innerHTML = `
                    <span style="font-weight:700; color:var(--text-dark);">Run #${run.num} <span style="font-weight:normal; color:var(--text-muted); font-size:0.7rem;">(${run.time})</span></span>
                    <span style="text-transform:capitalize; font-weight:600; color:var(--text-muted);">${run.language}</span>
                    <span style="font-weight:700; color:${statusColor};">${run.status}</span>
                    <span style="font-weight:600; color:var(--text-muted);">${run.duration} ms</span>
                `;
                historyList.appendChild(el);
            });
        }

        // Interactive Test Case Cards
        let currentTestCases = [];

        function renderTestCaseCards(cases) {
            const cardsContainer = document.getElementById('testCaseCardsContainer');
            if (!cardsContainer) return;
            
            cardsContainer.style.display = 'flex';
            cardsContainer.innerHTML = '';
            currentTestCases = cases;

            cases.forEach((tc, idx) => {
                const activeClass = idx === 0 ? 'active' : '';
                const card = document.createElement('button');
                card.className = `tc-card ${activeClass}`;
                card.onclick = () => switchTestCase(idx);
                card.innerHTML = `
                    <span class="tc-badge-dot ${tc.passed ? 'passed' : 'failed'}"></span>
                    Test Case ${idx + 1}
                `;
                cardsContainer.appendChild(card);
            });

            // Show first testcase details
            switchTestCase(0);
        }

        function switchTestCase(idx) {
            // Highlight active card
            const container = document.getElementById('testCaseCardsContainer');
            if (container) {
                container.querySelectorAll('.tc-card').forEach((card, cidx) => {
                    if (cidx === idx) {
                        card.classList.add('active');
                    } else {
                        card.classList.remove('active');
                    }
                });
            }

            const detailsBox = document.getElementById('outputConsole');
            if (!detailsBox || !currentTestCases[idx]) return;

            const tc = currentTestCases[idx];
            const expectedColorStr = `<span style="color:#38a169; font-weight:700;">🟢 ${escapeHtml(tc.expected)}</span>`;
            const actualColorStr = tc.passed 
                ? `<span style="color:#38a169; font-weight:700;">🟢 ${escapeHtml(tc.actual)}</span>`
                : `<span style="color:#e53e3e; font-weight:700;">🔴 ${escapeHtml(tc.actual)}</span>`;

            detailsBox.innerHTML = `
                <div style="margin-bottom: 8px;"><strong>Status:</strong> ${tc.passed ? '<span style="color:#48bb78; font-weight:700;">Passed</span>' : '<span style="color:#f56565; font-weight:700;">Failed</span>'}</div>
                <div style="margin-bottom: 8px; background:#1e1e2f; padding:8px; border-radius:4px;">
                    <div style="color:#718096; font-size:0.75rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Input</div>
                    <pre style="margin:0; font-family:inherit; color:#e2e8f0; white-space:pre-wrap;">${escapeHtml(tc.input || '')}</pre>
                </div>
                <div style="margin-bottom: 8px; background:#1e1e2f; padding:8px; border-radius:4px;">
                    <div style="color:#718096; font-size:0.75rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Expected Output</div>
                    <pre style="margin:0; font-family:inherit; white-space:pre-wrap;">${expectedColorStr}</pre>
                </div>
                <div style="background:#1e1e2f; padding:8px; border-radius:4px;">
                    <div style="color:#718096; font-size:0.75rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Your Output</div>
                    <pre style="margin:0; font-family:inherit; white-space:pre-wrap;">${actualColorStr}</pre>
                </div>
            `;
        }

        // Show context AI confidence
        function renderAIConfidence(score, likelyIssue) {
            const widget = document.getElementById('aiConfidenceWidget');
            const val = document.getElementById('aiConfidenceVal');
            if (widget && val) {
                val.textContent = `${score}%`;
                widget.style.display = 'flex';
            }
        }

        // Show/hide complexity post-run
        function renderRunComplexity(timeComplexity, spaceComplexity) {
            const complexityContainer = document.getElementById('runComplexityContainer');
            const timeVal = document.getElementById('complexityTimeVal');
            const spaceVal = document.getElementById('complexitySpaceVal');
            const adviceBox = document.getElementById('complexityAdviceText');

            if (complexityContainer && timeVal && spaceVal) {
                timeVal.textContent = timeComplexity;
                spaceVal.textContent = spaceComplexity;
                complexityContainer.style.display = 'block';
                if (adviceBox) adviceBox.style.display = 'none'; // reset detail box
            }
        }

        function triggerComplexityAdvice() {
            const adviceBox = document.getElementById('complexityAdviceText');
            if (!adviceBox) return;
            
            if (adviceBox.style.display === 'block') {
                adviceBox.style.display = 'none';
            } else {
                adviceBox.style.display = 'block';
                const code = editor.getValue();
                const est = estimateComplexityLocal(code);
                adviceBox.textContent = `Advice: ${est.explanation}`;
            }
        }

        async function explainErrorAutomatically(errMessage) {
            const explanationDiv = document.getElementById('aiConsoleExplanation');
            const explanationText = document.getElementById('aiConsoleExplanationText');
            if (!explanationDiv || !explanationText) return;
            
            explanationDiv.style.background = '#fff5f5';
            explanationDiv.style.borderColor = '#feb2b2';
            explanationText.style.color = '#742a2a';
            explanationDiv.querySelector('h6').innerHTML = '<i class="fas fa-robot"></i> <span>AI Coach Explanation</span>';
            explanationDiv.querySelector('h6').style.color = '#c53030';
            explanationDiv.style.display = 'block';
            explanationText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Coach is translating compiler error...';
            
            try {
                const code = editor.getValue();
                const language = document.getElementById('languageSelect').value;
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'mentor_feedback',
                        problem_id: problemId,
                        code: code,
                        language: language,
                        hint_level: hintLevel,
                        request_type: 'analyze',
                        execution_result: errMessage
                    })
                });
                const data = await response.json();
                if (data.success && data.parsed) {
                    cachedFeedback = data.parsed;
                    renderAIConfidence(92, "Syntax/Compilation Error");
                    if (cachedFeedback.syntax_analysis && cachedFeedback.syntax_analysis.message) {
                        explanationText.textContent = cachedFeedback.syntax_analysis.message;
                    } else if (cachedFeedback.learning_feedback) {
                        explanationText.textContent = cachedFeedback.learning_feedback;
                    } else {
                        explanationText.textContent = "It looks like a syntax/compilation error. Try checking variable names, matching braces, or parameter signatures.";
                    }
                }
            } catch (err) {
                explanationText.textContent = "Failed to load explanation.";
            }
        }

        async function explainWrongAnswerAutomatically(actualOutput) {
            const explanationDiv = document.getElementById('aiConsoleExplanation');
            const explanationText = document.getElementById('aiConsoleExplanationText');
            if (!explanationDiv || !explanationText) return;
            
            explanationDiv.style.background = '#fffaf0';
            explanationDiv.style.borderColor = '#fbd38d';
            explanationText.style.color = '#7b341e';
            explanationDiv.querySelector('h6').innerHTML = '<i class="fas fa-robot"></i> <span>AI Logic Hint</span>';
            explanationDiv.querySelector('h6').style.color = '#dd6b20';
            explanationDiv.style.display = 'block';
            explanationText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing logic gap...';
            
            try {
                const code = editor.getValue();
                const language = document.getElementById('languageSelect').value;
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'mentor_feedback',
                        problem_id: problemId,
                        code: code,
                        language: language,
                        hint_level: hintLevel,
                        request_type: 'hint',
                        execution_result: `Expected: ${expectedOutputVal}\nActual: ${actualOutput}`
                    })
                });
                const data = await response.json();
                if (data.success && data.parsed) {
                    cachedFeedback = data.parsed;
                    renderAIConfidence(96, "Loop boundary index mismatch");
                    explanationText.textContent = cachedFeedback.hint || cachedFeedback.learning_feedback || "Your output does not match the expected answer. Check if your loop boundaries or base cases are off-by-one.";
                }
            } catch (err) {
                explanationText.textContent = "Failed to load logic hint.";
            }
        }

        async function runCode() {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;
            const output = document.getElementById('outputConsole');
            const explanationDiv = document.getElementById('aiConsoleExplanation');

            if (explanationDiv) explanationDiv.style.display = 'none';

            switchConsoleTab('output');
            output.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executing...';
            
            let isCorrect = false;
            let runDuration = Math.floor(Math.random() * 25) + 10; // 10-35ms
            let localCases = [];

            if (language === 'javascript') {
                try {
                    const logs = [];
                    const originalLog = console.log;
                    console.log = (...args) => logs.push(args.join(' '));

                    const customInputVal = document.getElementById('customInput') ? document.getElementById('customInput').value : '';
                    let runWrapper = code;

                    if (practiceMode === 'learning') {
                        runWrapper += `\nconsole.log(solve(${JSON.stringify(customInputVal)}));`;
                    } else {
                        runWrapper = `
                            const require = (mod) => {
                                if (mod === 'fs') {
                                    return {
                                        readFileSync: () => ${JSON.stringify(customInputVal)}
                                    };
                                }
                                throw new Error("Module not found: " + mod);
                            };
                            \n${code}
                        `;
                    }

                    eval(runWrapper);
                    console.log = originalLog;
                    
                    let finalOutput = logs.join('\n');
                    const actualOut = (finalOutput || '').trim();
                    const cleanActual = actualOut.split('\n').pop() || '';
                    
                    if (cleanActual.toLowerCase().includes(expectedOutputVal.toLowerCase()) || 
                        cleanActual.replace(/\s+/g, '') === expectedOutputVal.replace(/\s+/g, '')) {
                        isCorrect = true;
                        consecutiveCompilerErrors = 0;
                        consecutiveWrongAnswers = 0;
                    } else {
                        consecutiveCompilerErrors = 0;
                        consecutiveWrongAnswers++;
                        explainWrongAnswerAutomatically(actualOut);
                    }

                    // Format multi-test cases cards output
                    localCases = [
                        { input: 'Sample Case', expected: expectedOutputVal, actual: actualOut, passed: isCorrect }
                    ];
                    renderTestCaseCards(localCases);

                } catch (error) {
                    output.innerHTML = `<span style="color:#e53e3e; font-weight:700;">❌ Execution Error</span>\n\n${error.message}`;
                    consecutiveCompilerErrors++;
                    consecutiveWrongAnswers = 0;
                    explainErrorAutomatically(error.message);
                }
            } else {
                const customInputVal = document.getElementById('customInput') ? document.getElementById('customInput').value : '';
                try {
                    const response = await fetch('coding_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'run_code',
                            problem_id: problemId,
                            code: code,
                            language: language,
                            custom_input: customInputVal,
                            practice_mode: practiceMode
                        })
                    });

                    const data = await response.json();
                    if (data.success && data.parsed) {
                        const runResult = data.parsed;
                        if (runResult.stderr) {
                            output.innerHTML = `<span style="color:#e53e3e; font-weight:700;">❌ Compiler/Runtime Error</span>\n\n${runResult.stderr}`;
                            consecutiveCompilerErrors++;
                            consecutiveWrongAnswers = 0;
                            explainErrorAutomatically(runResult.stderr);
                        } else {
                            const actualOut = (runResult.stdout || '').trim();
                            
                            if (actualOut.toLowerCase().includes(expectedOutputVal.toLowerCase()) || 
                                actualOut.replace(/\s+/g, '') === expectedOutputVal.replace(/\s+/g, '')) {
                                isCorrect = true;
                                consecutiveCompilerErrors = 0;
                                consecutiveWrongAnswers = 0;
                            } else {
                                consecutiveCompilerErrors = 0;
                                consecutiveWrongAnswers++;
                                explainWrongAnswerAutomatically(actualOut);
                            }

                            localCases = [
                                { input: customInputVal || 'Sample Input', expected: expectedOutputVal, actual: actualOut, passed: isCorrect }
                            ];
                            renderTestCaseCards(localCases);
                        }
                    } else {
                        output.textContent = '❌ Simulation failed to execute.';
                    }
                } catch (error) {
                    output.textContent = '❌ Connection error: ' + error.message;
                }
            }

            // Sync run history timeline
            runHistory.push({
                num: runHistory.length + 1,
                time: new Date().toTimeString().split(' ')[0],
                language: language,
                status: isCorrect ? 'Passed' : 'Wrong Answer',
                duration: runDuration
            });
            renderExecutionTimeline();

            // Populate complexity after run
            const localEst = estimateComplexityLocal(code);
            renderRunComplexity(localEst.time, localEst.space);

            // Update coach panel status checks
            updateCoachPanelCheck('syntax', consecutiveCompilerErrors === 0);
            updateCoachPanelCheck('variables', true);
            updateCoachPanelCheck('logic', isCorrect);
        }

        async function submitSolution() {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;
            const output = document.getElementById('outputConsole');

            switchConsoleTab('output');
            output.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting solution and validating against test cases...';

            let isCorrect = false;
            let actualOut = '';

            if (language === 'javascript') {
                try {
                    const logs = [];
                    const originalLog = console.log;
                    console.log = (...args) => logs.push(args.join(' '));

                    const customInputVal = document.getElementById('customInput') ? document.getElementById('customInput').value : '';
                    let runWrapper = code;

                    if (practiceMode === 'learning') {
                        runWrapper += `\nconsole.log(solve(${JSON.stringify(customInputVal)}));`;
                    } else {
                        runWrapper = `
                            const require = (mod) => {
                                if (mod === 'fs') {
                                    return {
                                        readFileSync: () => ${JSON.stringify(customInputVal)}
                                    };
                                }
                                throw new Error("Module not found: " + mod);
                            };
                            \n${code}
                        `;
                    }

                    eval(runWrapper);
                    console.log = originalLog;
                    
                    const finalOutput = logs.join('\n');
                    actualOut = (finalOutput || '').trim();
                    const cleanActual = actualOut.split('\n').pop() || '';
                    
                    if (cleanActual.toLowerCase().includes(expectedOutputVal.toLowerCase()) || 
                        cleanActual.replace(/\s+/g, '') === expectedOutputVal.replace(/\s+/g, '')) {
                        isCorrect = true;
                    }
                } catch (error) {
                    actualOut = error.message;
                }
            } else {
                const customInputVal = document.getElementById('customInput') ? document.getElementById('customInput').value : '';
                try {
                    const response = await fetch('coding_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'run_code',
                            problem_id: problemId,
                            code: code,
                            language: language,
                            custom_input: customInputVal,
                            practice_mode: practiceMode
                        })
                    });

                    const data = await response.json();
                    if (data.success && data.parsed) {
                        const runResult = data.parsed;
                        if (!runResult.stderr) {
                            actualOut = (runResult.stdout || '').trim();
                            if (actualOut.toLowerCase().includes(expectedOutputVal.toLowerCase()) || 
                                actualOut.replace(/\s+/g, '') === expectedOutputVal.replace(/\s+/g, '')) {
                                isCorrect = true;
                            }
                        } else {
                            actualOut = runResult.stderr;
                        }
                    }
                } catch (error) {
                    actualOut = error.message;
                }
            }

            if (isCorrect) {
                if (timerInterval) clearInterval(timerInterval);
                if (timeTrackerInterval) clearInterval(timeTrackerInterval);
                
                output.innerHTML = `<span style="color:#38a169; font-weight:700;">✓ Solution Accepted</span>\n\nAll test cases successfully passed! Click "View / Continue" to keep exploring.`;
                await saveProgress('solved');
                
                // Show stunning success modal overlay
                triggerSuccessOverlay();
            } else {
                output.innerHTML = `<span style="color:#e53e3e; font-weight:700;">❌ Validation Failed</span>\n\nExpected:\n${expectedOutputVal}\n\nYour Output:\n${actualOut}`;
            }
        }

        // Overlay Triggers
        function triggerSuccessOverlay() {
            const overlay = document.getElementById('successOverlay');
            const overlayTime = document.getElementById('overlayTimeVal');
            const overlayLang = document.getElementById('overlayLangVal');
            const language = document.getElementById('languageSelect').value;

            if (overlay) {
                if (overlayTime) overlayTime.textContent = document.getElementById('timerText').textContent;
                if (overlayLang) overlayLang.textContent = language;
                overlay.style.display = 'flex';
            }
        }

        function closeSuccessOverlay() {
            const overlay = document.getElementById('successOverlay');
            if (overlay) overlay.style.display = 'none';
        }

        function goToNextProblem() {
            window.location.href = 'coding_practice.php';
        }

        async function saveProgress(status = 'attempted') {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;

            try {
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'save_progress',
                        problem_id: problemId,
                        code: code,
                        language: language,
                        status: status
                    })
                });
                return await response.json();
            } catch (error) {
                console.error('Error saving progress:', error);
            }
        }

        function resetEditor() {
            if (confirm('Are you sure you want to reset the editor? All changes will be lost.')) {
                const language = document.getElementById('languageSelect').value;
                editor.setValue('');
                setStarterCode(language);
            }
        }

        async function requestMentorFeedback(requestType) {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;
            const consoleOutput = document.getElementById('outputConsole').innerText;

            try {
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'mentor_feedback',
                        problem_id: problemId,
                        code: code,
                        language: language,
                        hint_level: hintLevel,
                        request_type: requestType,
                        execution_result: consoleOutput
                    })
                });

                const data = await response.json();
                if (data.success && data.parsed) {
                    cachedFeedback = data.parsed;
                    renderMentorOutputs();
                }
            } catch (error) {
                console.error('Error fetching mentor feedback:', error);
            }
        }

        function renderMentorOutputs() {
            const fb = cachedFeedback;
            if (!fb) return;

            const tipEl = document.getElementById('coachRealtimeTip');
            if (tipEl && fb.learning_feedback) {
                tipEl.textContent = fb.learning_feedback;
            }
        }

        async function viewSolution() {
            const solutionContent = document.getElementById('solutionContent');
            
            if (solutionContent.style.display === 'block') {
                solutionContent.style.display = 'none';
                return;
            }

            solutionContent.innerHTML = '<p style="text-align: center; padding: 10px;"><i class="fas fa-spinner fa-spin"></i> Fetching AI solutions...</p>';
            solutionContent.style.display = 'block';

            try {
                const response = await fetch('coding_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'generate_solution',
                        problem_id: problemId
                    })
                });

                const data = await response.json();
                
                if (data.success && data.solutions) {
                    const { beginner, optimized } = data.solutions;
                    
                    const renderMultiLangCode = (codeObj, type) => {
                        const langs = ['javascript', 'python', 'java', 'cpp'];
                        const langNames = {
                            'javascript': 'JavaScript',
                            'python': 'Python',
                            'java': 'Java',
                            'cpp': 'C++'
                        };
                        
                        const langEl = document.getElementById('languageSelect');
                        const currentLang = langEl ? langEl.value.toLowerCase() : 'javascript';
                        const activeLang = langs.includes(currentLang) ? currentLang : 'javascript';

                        let tabsHtml = `<div class="sol-tabs">`;
                        let codesHtml = `<div class="sol-codes">`;

                        langs.forEach(lang => {
                            const isActive = lang === activeLang ? 'active' : '';
                            tabsHtml += `<div class="sol-tab ${isActive}" onclick="switchSolutionLang('${lang}', '${type}')">${langNames[lang]}</div>`;
                            
                            codesHtml += `
                                <div id="sol-code-${type}-${lang}" class="sol-code-block ${isActive}">
                                    <pre style="margin: 5px 0; background: #2d3748; color: #fff; padding: 10px; border-radius: 6px; overflow-x: auto; font-family: monospace; font-size: 0.75rem;">${escapeHtml(codeObj[lang] || '')}</pre>
                                </div>`;
                        });

                        tabsHtml += `</div>`;
                        codesHtml += `</div>`;
                        
                        return tabsHtml + codesHtml;
                    };

                    solutionContent.innerHTML = `
                        <div style="margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 10px;">
                            <h6 style="font-weight: 700; color: #2f855a;">🎓 Beginner Approach</h6>
                            <p style="margin: 3px 0 10px 0; font-size: 0.8rem; line-height: 1.4;">${beginner.why_function}</p>
                            ${renderMultiLangCode(beginner.code, 'beginner')}
                            
                            <h6 style="font-weight: 700; color: #2b6cb0; margin-top: 15px;">⚡ Optimized Approach</h6>
                            <p style="margin: 3px 0 10px 0; font-size: 0.8rem; line-height: 1.4;">${optimized.goal}</p>
                            ${renderMultiLangCode(optimized.code, 'optimized')}
                        </div>
                    `;
                } else {
                    solutionContent.innerHTML = '<p style="color:#f56565;">❌ Failed to load solution.</p>';
                }
            } catch (error) {
                console.error('Error viewSolution:', error);
                solutionContent.innerHTML = '<p style="color:#f56565;">❌ Connection error.</p>';
            }
        }

        function switchSolutionLang(lang, type) {
            const container = document.getElementById(`sol-code-${type}-${lang}`).parentElement.parentElement;
            container.querySelectorAll(`.sol-tab`).forEach(tab => {
                if (tab.textContent.toLowerCase() === lang.replace('cpp', 'c++') || (lang === 'cpp' && tab.textContent === 'C++')) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });

            container.querySelectorAll(`.sol-code-block`).forEach(block => {
                if (block.id === `sol-code-${type}-${lang}`) {
                    block.classList.add('active');
                } else {
                    block.classList.remove('active');
                }
            });
        }
    </script>
    <script src="report_question.js?v=<?php echo APP_VERSION; ?>"></script>
    <script>
        function reportCodingProblemIssue() {
            if (typeof openQuestionReportModal === 'function') {
                openQuestionReportModal({
                    test_type: 'coding_problem',
                    test_id: problemId,
                    question_text: "Problem Title: " + <?php echo json_encode($problem['title']); ?> + "\n\n" + <?php echo json_encode($problem['problem_statement']); ?>,
                    options: null,
                    correct_answer: <?php echo json_encode($problem['example_output'] ?? 'N/A'); ?>,
                    user_answer: (typeof editor !== 'undefined' && editor) ? editor.getValue() : ''
                });
            } else {
                alert('Reporting utility is loading or not available.');
            }
        }
    </script>
</body>
</html>
