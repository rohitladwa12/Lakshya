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
            --terminal-bg: #1e1e2f;
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
        }

        /* HackerRank Top Toolbar */
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
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            background: #fff5f5;
            color: var(--primary-maroon);
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

        .timer-badge {
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Main split layout */
        .workspace-split {
            display: flex;
            flex: 1;
            min-height: 0;
            gap: 15px;
        }

        /* Left Panel - Problem */
        .panel-problem {
            width: 30%;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 20px;
            overflow-y: auto;
        }

        /* Accordion style */
        .accordion-header {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .accordion-header:hover {
            background: #edf2f7;
        }
        .accordion-header.active {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
        }

        .accordion-content {
            padding: 15px;
            font-size: 0.92rem;
            line-height: 1.6;
            color: #4a5568;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 6px 6px;
            margin-top: -8px;
            margin-bottom: 12px;
            display: none;
        }

        .example-block {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 6px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
            margin-top: 6px;
            line-height: 1.5;
            color: #2d3748;
        }

        /* Right Panel - Editor */
        .panel-editor {
            width: 70%;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 15px;
        }

        .editor-label {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        /* Tabbed Bottom Panel (Spans full width below) */
        .bottom-panel-wrap {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 38%; /* default resizable height */
            min-height: 48px;
            flex-shrink: 0;
            position: relative;
            transition: height 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .bottom-panel-wrap.collapsed {
            height: 48px !important;
            overflow: hidden;
        }

        .panel-resizer {
            height: 4px;
            background: #e2e8f0;
            cursor: row-resize;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
        }
        .panel-resizer:hover {
            background: var(--primary-maroon);
        }

        .tab-bar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            height: 44px;
            padding: 0 15px;
            flex-shrink: 0;
            margin-top: 4px; /* offset resizer bar */
        }

        .tab-bar {
            display: flex;
            gap: 4px;
            height: 100%;
            align-items: flex-end;
        }

        .tab-btn {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            height: 38px;
            transition: all 0.15s ease;
        }
        .tab-btn:hover {
            color: var(--text-dark);
            background: rgba(0,0,0,0.02);
            border-radius: 4px 4px 0 0;
        }
        .tab-btn.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
            font-weight: 700;
        }

        .control-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .control-btn:hover {
            background: #edf2f7;
            color: var(--text-dark);
        }

        .tab-content-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            min-height: 0;
        }

        .tab-pane {
            display: none;
            height: 100%;
        }
        .tab-pane.active {
            display: block;
        }

        /* Output console */
        .terminal-console {
            background: var(--terminal-bg);
            color: #edf2f7;
            font-family: 'JetBrains Mono', monospace;
            padding: 15px;
            border-radius: 6px;
            height: 100%;
            overflow-y: auto;
            white-space: pre-wrap;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* AI Mentor Layout inside Bottom Panel */
        .mentor-bottom-layout {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 15px;
            height: 100%;
        }

        .mentor-card-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px 15px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
        }

        .mentor-card-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .mentor-card-body {
            font-size: 0.88rem;
            line-height: 1.5;
            color: #4a5568;
        }

        .status-badge {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .progress-dots {
            font-family: monospace;
            font-size: 1.1rem;
            color: var(--primary-maroon);
            letter-spacing: 1px;
        }

        .qa-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
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
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="ide-container">
        <!-- Top Toolbar -->
        <div class="ide-toolbar">
            <div class="toolbar-left">
                <span class="toolbar-title"><?php echo htmlspecialchars($problem['title']); ?></span>
                <span class="difficulty-badge">
                    <?php echo $problem['difficulty']; ?>
                </span>
                <div class="timer-badge">
                    <i class="far fa-clock"></i> <span id="timerText">00:00:00</span>
                </div>
            </div>

            <div class="toolbar-right">
                <select id="languageSelect" onchange="changeLanguage()" class="toolbar-select">
                    <option value="javascript" <?php echo $savedLanguage === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                    <option value="python" <?php echo $savedLanguage === 'python' ? 'selected' : ''; ?>>Python</option>
                    <option value="java" <?php echo $savedLanguage === 'java' ? 'selected' : ''; ?>>Java</option>
                    <option value="c" <?php echo $savedLanguage === 'c' ? 'selected' : ''; ?>>C</option>
                    <option value="cpp" <?php echo $savedLanguage === 'cpp' ? 'selected' : ''; ?>>C++</option>
                </select>

                <button class="tb-btn" id="practiceModeToggle" onclick="togglePracticeMode()" title="Toggle between Learning Mode and Competitive Mode"><i class="fas fa-graduation-cap"></i> Mode: Learning</button>

                <button class="tb-btn" onclick="resetEditor()"><i class="fas fa-undo"></i> Reset</button>
                <button class="tb-btn" onclick="saveProgress()"><i class="fas fa-save"></i> Save</button>
                <button class="tb-btn tb-btn-primary" onclick="runCode()"><i class="fas fa-play"></i> Run Code</button>
                <button class="tb-btn tb-btn-success" onclick="submitSolution()"><i class="fas fa-check-circle"></i> Submit</button>
                <button class="tb-btn" onclick="switchBottomTab('mentor')"><i class="fas fa-robot"></i> AI Mentor</button>
            </div>
        </div>

        <!-- Upper Workspace Split (Problem & Editor) -->
        <div class="workspace-split">
            <!-- Left Panel: Accordion Problem -->
            <div class="panel-problem">
                <!-- Section 1: Problem Statement -->
                <div class="accordion-header active" data-target="sec-statement" onclick="toggleAccordion('sec-statement')">
                    <span>📄 Problem Statement</span>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content" id="sec-statement" style="display: block;">
                    <p style="white-space: pre-wrap; color: #2d3748;"><?php echo htmlspecialchars($problem['problem_statement']); ?></p>
                </div>

                <!-- Section 2: Input & Output Format -->
                <div class="accordion-header" data-target="sec-formats" onclick="toggleAccordion('sec-formats')">
                    <span>⚙️ Input & Output Format</span>
                    <i class="fas fa-chevron-right accordion-icon"></i>
                </div>
                <div class="accordion-content" id="sec-formats">
                    <p><strong>Input Format:</strong> Dynamic parameter values matching solution signature.</p>
                    <p style="margin-top: 10px;"><strong>Output Format:</strong> Returned variable output evaluated correctly.</p>
                </div>

                <!-- Section 3: Constraints -->
                <?php if ($problem['constraints']): ?>
                <div class="accordion-header" data-target="sec-constraints" onclick="toggleAccordion('sec-constraints')">
                    <span>🔒 Constraints</span>
                    <i class="fas fa-chevron-right accordion-icon"></i>
                </div>
                <div class="accordion-content" id="sec-constraints">
                    <pre style="white-space: pre-wrap; font-family: monospace; font-size: 0.85rem; color:#4a5568;"><?php echo htmlspecialchars($problem['constraints']); ?></pre>
                </div>
                <?php endif; ?>

                <!-- Section 4: Sample Examples -->
                <?php if ($problem['example_input'] && $problem['example_output']): ?>
                <div class="accordion-header" data-target="sec-examples" onclick="toggleAccordion('sec-examples')">
                    <span>💡 Sample Input & Output</span>
                    <i class="fas fa-chevron-right accordion-icon"></i>
                </div>
                <div class="accordion-content" id="sec-examples">
                    <div class="example-block">
                        <strong>Sample Input:</strong><br><?php echo htmlspecialchars($problem['example_input']); ?><br><br>
                        <strong>Sample Output:</strong><br><?php echo htmlspecialchars($problem['example_output']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 5: Concept Focus -->
                <div class="accordion-header" data-target="sec-concept" onclick="toggleAccordion('sec-concept')">
                    <span>📖 Concept Explanation</span>
                    <i class="fas fa-chevron-right accordion-icon"></i>
                </div>
                <div class="accordion-content" id="sec-concept">
                    <p><?php echo nl2br(htmlspecialchars($problem['concept_explanation'])); ?></p>
                </div>
            </div>

            <!-- Right Panel: Editor -->
            <div class="panel-editor">
                <div class="editor-label">Source Code</div>
                <textarea id="codeEditor"><?php echo htmlspecialchars($savedCode); ?></textarea>
            </div>
        </div>

        <!-- Tabbed Bottom Panel (Test cases & AI Mentor details) -->
        <div class="bottom-panel-wrap" id="bottomPanel">
            <div class="panel-resizer" id="bottomPanelResizer"></div>

            <!-- Tab Buttons Header -->
            <div class="tab-bar-container">
                <div class="tab-bar">
                    <button class="tab-btn" id="tab-testcases" onclick="switchBottomTab('testcases')">
                        <i class="fas fa-vials"></i> Test Cases
                    </button>
                    <button class="tab-btn active" id="tab-output" onclick="switchBottomTab('output')">
                        <i class="fas fa-terminal"></i> Output
                    </button>
                    <button class="tab-btn" id="tab-mentor" onclick="switchBottomTab('mentor')">
                        <i class="fas fa-robot"></i> AI Mentor
                    </button>
                    <button class="tab-btn" id="tab-complexity" onclick="switchBottomTab('complexity')">
                        <i class="fas fa-chart-line"></i> Complexity
                    </button>
                    <button class="tab-btn" id="tab-reflection" onclick="switchBottomTab('reflection')">
                        <i class="fas fa-award"></i> Reflection
                    </button>
                    <button class="tab-btn" id="tab-console" onclick="switchBottomTab('console')">
                        <i class="fas fa-keyboard"></i> Custom Input
                    </button>
                </div>
                <div>
                    <button class="control-btn" onclick="toggleBottomPanel()" title="Collapse Panel">
                        <i class="fas fa-chevron-down" id="togglePanelIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Tab Content Pane -->
            <div class="tab-content-container">
                <!-- Tab: Test Cases -->
                <div class="tab-pane" id="pane-testcases">
                    <div style="display: flex; gap: 15px;">
                        <div style="flex: 1; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color);">
                            <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Sample Input</h5>
                            <pre style="margin: 0; font-family: monospace; font-size: 0.85rem;"><?php echo htmlspecialchars($problem['example_input'] ?: 'N/A'); ?></pre>
                        </div>
                        <div style="flex: 1; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color);">
                            <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Sample Output</h5>
                            <pre style="margin: 0; font-family: monospace; font-size: 0.85rem;"><?php echo htmlspecialchars($problem['example_output'] ?: 'N/A'); ?></pre>
                        </div>
                    </div>
                </div>

                <!-- Tab: Output -->
                <div class="tab-pane active" id="pane-output">
                    <div class="terminal-console" id="outputConsole">
                        Console is empty. Click "Run Code" to execute tests.
                    </div>
                </div>

                <!-- Tab: AI Mentor -->
                <div class="tab-pane" id="pane-mentor">
                    <div class="mentor-bottom-layout">
                        <!-- Left Block: Coach Suggestion -->
                        <div class="mentor-card-box">
                            <span class="mentor-card-title">💡 Mentor Suggestion</span>
                            <div class="mentor-card-body" id="mentorSuggestionText">
                                Write code in the editor and click Run Code. The AI Coding Mentor watches your actions and reports logic advice without giving away the final solution!
                            </div>
                        </div>

                        <!-- Center Block: Hints & Socratic Questions -->
                        <div class="mentor-card-box">
                            <span class="mentor-card-title">🤔 Thinking Focus</span>
                            <div class="status-container" style="display:flex; gap:8px; margin-top: 5px;">
                                <div id="statusSyntax" class="status-badge" style="background:#e6f4ea; color:#1e7e34;">Syntax Good</div>
                                <div id="statusLogic" class="status-badge" style="background:#fff4e5; color:#b76e00;">Logic Review</div>
                            </div>
                            <div class="mentor-card-body" style="font-weight: 600; margin-top: 5px;" id="mentorThinkingQuestion">
                                No questions evaluated yet.
                            </div>
                            <div class="mentor-card-body" style="font-size: 0.8rem; font-style: italic; color:#718096; margin-top: 5px;" id="mentorCurrentHint">
                                Locked Hint.
                            </div>
                        </div>

                        <!-- Right Block: Quick Coach Actions -->
                        <div class="mentor-card-box" style="justify-content: space-between;">
                            <div>
                                <span class="mentor-card-title">Hint Level Progress</span>
                                <div style="margin-top: 5px;">
                                    <span class="progress-dots" id="hintLevelDots">○○○○○○○</span>
                                </div>
                            </div>
                            <div class="qa-grid">
                                <button class="tb-btn" style="justify-content:center; padding: 5px;" onclick="triggerAnalyze()"><i class="fas fa-brain"></i> Analyze</button>
                                <button class="tb-btn" style="justify-content:center; padding: 5px;" onclick="triggerNextHint()"><i class="far fa-lightbulb"></i> Next Hint</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Complexity -->
                <div class="tab-pane" id="pane-complexity">
                    <div style="display: flex; gap: 15px; height: 100%;">
                        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); flex: 1;">
                            <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Time Complexity</h5>
                            <div id="complexityTime" style="font-weight: 800; color: var(--primary-maroon); font-size: 1.1rem;">O(?)</div>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); flex: 1;">
                            <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Space Complexity</h5>
                            <div id="complexitySpace" style="font-weight: 800; color: var(--primary-maroon); font-size: 1.1rem;">O(?)</div>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); flex: 2; overflow-y: auto;">
                            <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Optimization Advice</h5>
                            <p id="complexityAdvice" style="font-size: 0.85rem; line-height: 1.5; color: #4a5568;">Run code analysis to receive optimization suggestions.</p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Reflection -->
                <div class="tab-pane" id="pane-reflection">
                    <div style="display: flex; gap: 15px; height: 100%; overflow-y: auto;">
                        <div style="background: #f0fff4; padding: 12px; border-radius: 6px; border: 1px solid #c6f6d5; flex: 1.2;">
                            <h5 style="color: #22543d; font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Accomplishments</h5>
                            <p id="reflectionAchievements" style="font-size: 0.85rem; line-height: 1.5; color: #22543d;">No reflections evaluated yet. Submit code to check mastery.</p>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); flex: 1;">
                            <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Next Concept Recommendations</h5>
                            <p id="reflectionRecommendation" style="font-size: 0.85rem; line-height: 1.5; color: #4a5568;">N/A</p>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); flex: 1;">
                            <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800; margin-bottom: 5px;">Learning Verification Solution</h5>
                            <button class="tb-btn" style="width: 100%; justify-content: center; font-size: 0.8rem;" onclick="viewSolution()"><i class="fas fa-eye"></i> View Solution Approaches</button>
                            <div id="solutionContent" style="display: none; margin-top: 10px; font-size: 0.8rem;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Console Custom Input -->
                <div class="tab-pane" id="pane-console">
                    <div style="display: flex; flex-direction: column; gap: 6px; height: 100%;">
                        <h5 style="color: var(--text-muted); font-size: 0.75rem; font-weight: 800;">Provide Custom Input Params</h5>
                        <textarea id="customInput" placeholder="Enter custom inputs here..." style="width: 100%; flex: 1; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; font-family: monospace; font-size: 0.85rem; outline: none; resize: none;"></textarea>
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
        let currentQuizAnswer = null;
        let cachedFeedback = null;
        let previousLanguage = 'javascript';

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

            // Init vertical resizer
            initResizer();
        });

        // Elapsed upward timer
        function initTimer() {
            let startTime = Date.now();
            setInterval(() => {
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

        // Resizable panel logic
        function initResizer() {
            const resizer = document.getElementById('bottomPanelResizer');
            const bottomPanel = document.getElementById('bottomPanel');
            let isResizing = false;

            resizer.addEventListener('mousedown', (e) => {
                isResizing = true;
                document.body.style.cursor = 'row-resize';
                bottomPanel.style.transition = 'none';
            });

            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;
                const container = document.querySelector('.ide-container');
                const containerRect = container.getBoundingClientRect();
                const newHeight = containerRect.bottom - e.clientY;
                
                if (newHeight > 48 && newHeight < containerRect.height * 0.75) {
                    bottomPanel.style.height = `${newHeight}px`;
                    if (newHeight <= 50) {
                        bottomPanel.classList.add('collapsed');
                        document.getElementById('togglePanelIcon').className = 'fas fa-chevron-up';
                    } else {
                        bottomPanel.classList.remove('collapsed');
                        document.getElementById('togglePanelIcon').className = 'fas fa-chevron-down';
                    }
                }
            });

            document.addEventListener('mouseup', () => {
                if (isResizing) {
                    isResizing = false;
                    document.body.style.cursor = 'default';
                    bottomPanel.style.transition = 'height 0.25s cubic-bezier(0.4, 0, 0.2, 1)';
                    editor.refresh();
                }
            });
        }

        // Accordion switch logic
        function toggleAccordion(sectionId) {
            const content = document.getElementById(sectionId);
            const headers = document.querySelectorAll('.accordion-header');
            
            const isVisible = content.style.display === 'block';
            
            // Close all
            document.querySelectorAll('.accordion-content').forEach(c => c.style.display = 'none');
            headers.forEach(h => {
                h.classList.remove('active');
                h.querySelector('.accordion-icon').className = 'fas fa-chevron-right accordion-icon';
            });

            // Open selected
            if (!isVisible) {
                content.style.display = 'block';
                const currentHeader = Array.from(headers).find(h => h.dataset.target === sectionId);
                currentHeader.classList.add('active');
                currentHeader.querySelector('.accordion-icon').className = 'fas fa-chevron-down accordion-icon';
            }
        }

        // Tab switcher
        function switchBottomTab(tabName) {
            const bottomPanel = document.getElementById('bottomPanel');
            if (bottomPanel.classList.contains('collapsed')) {
                bottomPanel.classList.remove('collapsed');
                bottomPanel.style.height = '38%';
                document.getElementById('togglePanelIcon').className = 'fas fa-chevron-down';
            }

            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            document.getElementById(`tab-${tabName}`).classList.add('active');
            document.getElementById(`pane-${tabName}`).classList.add('active');
            
            setTimeout(() => editor.refresh(), 50);
        }

        // Collapse / Expand toggle
        function toggleBottomPanel() {
            const bottomPanel = document.getElementById('bottomPanel');
            const icon = document.getElementById('togglePanelIcon');

            if (bottomPanel.classList.contains('collapsed')) {
                bottomPanel.classList.remove('collapsed');
                bottomPanel.style.height = '38%';
                icon.className = 'fas fa-chevron-down';
            } else {
                bottomPanel.classList.add('collapsed');
                icon.className = 'fas fa-chevron-up';
            }
            setTimeout(() => editor.refresh(), 260);
        }

        let practiceMode = 'learning';

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

        function getStarterCodes() {
            return practiceMode === 'learning' ? starterCodesLearning : starterCodesCompetitive;
        }

        function togglePracticeMode() {
            if (confirm('Toggling mode will reset the editor to load the starter template for the new mode. Do you want to continue?')) {
                const btn = document.getElementById('practiceModeToggle');
                const language = document.getElementById('languageSelect').value;
                
                if (practiceMode === 'learning') {
                    practiceMode = 'competitive';
                    btn.innerHTML = '<i class="fas fa-trophy"></i> Mode: Competitive';
                    btn.style.borderColor = 'var(--primary-maroon)';
                    btn.style.color = 'var(--primary-maroon)';
                } else {
                    practiceMode = 'learning';
                    btn.innerHTML = '<i class="fas fa-graduation-cap"></i> Mode: Learning';
                    btn.style.borderColor = '';
                    btn.style.color = '';
                }

                editor.setValue('');
                setStarterCode(language);
            }
        }

        function changeLanguage() {
            const language = document.getElementById('languageSelect').value;
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

        async function runCode() {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;
            const output = document.getElementById('outputConsole');

            switchBottomTab('output');
            output.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executing logic...';

            if (language === 'javascript') {
                try {
                    const logs = [];
                    const originalLog = console.log;
                    console.log = (...args) => logs.push(args.join(' '));

                    const customInputVal = document.getElementById('customInput') ? document.getElementById('customInput').value : '';
                    let runWrapper = code;

                    if (practiceMode === 'learning') {
                        // In Learning Mode, we automatically append a call to solve() using standard/custom inputs
                        runWrapper += `\nconsole.log(solve(${JSON.stringify(customInputVal)}));`;
                    } else {
                        // Mock fs module for competitive stdin in the browser
                        runWrapper = `
                            const require = (mod) => {
                                if (mod === 'fs') {
                                    return {
                                        readFileSync: () => ${JSON.stringify(customInputVal)}
                                    };
                                }
                                throw new Error("Module not found: " + mod);
                            };
                            ${code}
                        `;
                    }

                    const result = eval(runWrapper);
                    console.log = originalLog;
                    
                    let finalOutput = logs.join('\n');
                    output.textContent = finalOutput || 'Code executed successfully (no output)';
                    
                    // Request feedback conceptually
                    setTimeout(() => {
                        requestMentorFeedback('analyze');
                    }, 800);

                } catch (error) {
                    output.textContent = '❌ Execution Error: ' + error.message;
                    setTimeout(() => {
                        requestMentorFeedback('analyze');
                    }, 800);
                }
            } else {
                const customInputVal = document.getElementById('customInput') ? document.getElementById('customInput').value : '';
                try {
                    const response = await fetch('coding_handler', {
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
                            output.textContent = '❌ Compiler/Runtime Error:\n' + runResult.stderr;
                        } else {
                            output.textContent = runResult.stdout || 'Code executed successfully (no stdout)';
                        }
                    } else {
                        output.textContent = '❌ Simulation failed to execute.';
                    }

                    // Request feedback conceptually
                    setTimeout(() => {
                        requestMentorFeedback('analyze');
                    }, 800);

                } catch (error) {
                    output.textContent = '❌ Connection error: ' + error.message;
                }
            }
        }

        async function submitSolution() {
            switchBottomTab('reflection');
            document.getElementById('reflectionAchievements').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting solution and performing reflection analysis...';
            
            await saveProgress('solved');
            requestMentorFeedback('reflection');
        }

        async function saveProgress(status = 'attempted') {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;

            try {
                const response = await fetch('coding_handler', {
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
                const response = await fetch('coding_handler', {
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

            // 1. Sync Syntax / Logic status badges
            const syntaxTag = document.getElementById('statusSyntax');
            const logicTag = document.getElementById('statusLogic');
            
            if (fb.syntax_analysis && fb.syntax_analysis.valid) {
                syntaxTag.style.background = '#e6f4ea';
                syntaxTag.style.color = '#1e7e34';
                syntaxTag.textContent = 'Syntax Good';
            } else {
                syntaxTag.style.background = '#fce8e6';
                syntaxTag.style.color = '#d93025';
                syntaxTag.textContent = 'Syntax Issue';
            }

            if (fb.logic_analysis && fb.logic_analysis.valid) {
                logicTag.style.background = '#e6f4ea';
                logicTag.style.color = '#1e7e34';
                logicTag.textContent = 'Logic Good';
            } else {
                logicTag.style.background = '#fff4e5';
                logicTag.style.color = '#b76e00';
                logicTag.textContent = 'Logic Review';
            }

            // 2. Render Mentor tab items
            if (fb.learning_feedback) {
                document.getElementById('mentorSuggestionText').textContent = fb.learning_feedback;
            }

            document.getElementById('mentorThinkingQuestion').textContent = 
                (fb.socratic_questions && fb.socratic_questions.length > 0) ? fb.socratic_questions[0] : "What is the expected outcome of the main logic block?";
            
            document.getElementById('mentorCurrentHint').textContent = fb.hint || "Locked Hint. Click 'Next Hint' to unlock progressive clues.";

            // Dots UI
            let dots = '';
            for (let i = 1; i <= 7; i++) {
                dots += i <= hintLevel ? '●' : '○';
            }
            document.getElementById('hintLevelDots').textContent = dots;

            // 3. Render Complexity Tab items
            if (fb.complexity_analysis) {
                document.getElementById('complexityTime').textContent = fb.complexity_analysis.time || 'O(?)';
                document.getElementById('complexitySpace').textContent = fb.complexity_analysis.space || 'O(?)';
                document.getElementById('complexityAdvice').textContent = fb.complexity_analysis.advice || 'No complexity suggestions recorded.';
            }

            // 4. Render Reflection Tab items
            if (fb.reflection) {
                document.getElementById('reflectionAchievements').innerHTML = `
                    <strong>Key Achievements:</strong><br>${fb.reflection.achievements ? fb.reflection.achievements.join('<br>') : 'N/A'}<br><br>
                    <strong>Concepts Mastered:</strong><br>${fb.reflection.concepts_learned ? fb.reflection.concepts_learned.join('<br>') : 'N/A'}<br><br>
                    <strong>Mistakes Resolved:</strong><br>${fb.reflection.mistakes_fixed ? fb.reflection.mistakes_fixed.join('<br>') : 'N/A'}
                `;
                document.getElementById('reflectionRecommendation').textContent = fb.reflection.next_recommendation || 'No recommendations recorded.';
            }
        }

        // Quick Coach Actions
        function triggerAnalyze() {
            switchBottomTab('mentor');
            requestMentorFeedback('analyze');
        }

        function triggerNextHint() {
            switchBottomTab('mentor');
            requestMentorFeedback('hint');
            if (hintLevel < 7) {
                hintLevel++;
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
                const response = await fetch('coding_handler', {
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

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
