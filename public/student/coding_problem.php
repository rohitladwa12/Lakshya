<?php
/**
 * Coding Problem Detail Page
 * Dual-mode interface: Practice Mode & Learn Mode
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
$savedLanguage = $progress['language_used'] ?? 'javascript';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($problem['title']); ?> - Coding Practice</title>
    
    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-gold: #e9c66f;
            --white: #ffffff;
            --bg: #f0f2f5;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Outfit', 'Inter', 'Segoe UI', sans-serif; 
            background: var(--bg); 
            color: #333; 
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .problem-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .problem-title {
            font-size: 2.2rem;
            color: var(--primary-maroon);
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .difficulty-badge {
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .difficulty-easy { background: #E6F4EA; color: #1E7E34; border: 1px solid rgba(30, 126, 52, 0.2); }
        .difficulty-medium { background: #FFF4E5; color: #B76E00; border: 1px solid rgba(183, 110, 0, 0.2); }
        .difficulty-hard { background: #FCE8E6; color: #D93025; border: 1px solid rgba(217, 48, 37, 0.2); }

        .mode-toggle {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .mode-btn {
            flex: 1;
            padding: 14px 25px;
            border: 2px solid #f0f0f0;
            background: #fafafa;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            color: #666;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .mode-btn.active {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);
            transform: translateY(-2px);
        }
        
        .mode-btn:hover:not(.active):not(:disabled) {
            border-color: #ddd;
            background: #fff;
        }

        .split-view {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .panel {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 25px;
            overflow-y: auto;
            max-height: calc(100vh - 300px);
        }

        .panel h3 {
            color: var(--primary-maroon);
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .section {
            margin-bottom: 25px;
        }

        .section h4 {
            color: #555;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .code-example {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }

        .editor-panel {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 20px;
        }

        .editor-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }

        select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary-maroon);
            color: white;
        }

        .btn-primary:hover {
            background: #600000;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .output-console {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            min-height: 100px;
            margin-top: 15px;
            white-space: pre-wrap;
        }

        .learn-mode-content {
            display: none;
        }

        .learn-mode-content.active {
            display: block;
        }

        .concept-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary-maroon);
            margin-bottom: 20px;
        }

        .dry-run-step {
            background: #fff;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .complexity-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #e3fcef;
            color: #00875a;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 10px;
        }

        .CodeMirror {
            height: 400px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }

        @media (max-width: 1024px) {
            .split-view {
                grid-template-columns: 1fr;
            }
        }

        /* Solution Language Tabs */
        .sol-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .sol-tab {
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            background: #f5f5f5;
            color: #666;
            border: 1px solid #ddd;
        }
        .sol-tab:hover {
            background: #eee;
        }
        .sol-tab.active {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
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

    <div class="container">
        <!-- Problem Header -->
        <div class="problem-header">
            <div>
                <div class="problem-title"><?php echo htmlspecialchars($problem['title']); ?></div>
                <span style="color: #666; font-size: 0.9rem;">Category: <?php echo htmlspecialchars($problem['category']); ?></span>
            </div>
            <span class="difficulty-badge difficulty-<?php echo strtolower($problem['difficulty']); ?>">
                <?php echo $problem['difficulty']; ?>
            </span>
        </div>

        <!-- Mode Toggle -->
        <div class="mode-toggle">
            <button class="mode-btn" onclick="return false;" style="opacity: 0.7; cursor: not-allowed; position: relative; background: #f8f9fa; border-style: dashed;">
                💻 Practice Mode
                <span style="position: absolute; top: -10px; right: -10px; background: #FFD700; color: #800000; font-size: 0.65rem; padding: 2px 8px; border-radius: 4px; font-weight: bold; transform: rotate(8deg); box-shadow: 0 4px 10px rgba(0,0,0,0.1);">BETA</span>
            </button>
            <button class="mode-btn active" onclick="switchMode('learn')">
                📚 Learn Mode
            </button>
        </div>

        <!-- Practice Mode (Coming Soon) -->
        <div id="practiceMode" class="split-view" style="display: none;">
            <!-- Problem Description -->
            <div class="panel">
                <h3>Problem Statement</h3>
                
                <div class="section">
                    <p><?php echo nl2br(htmlspecialchars($problem['problem_statement'])); ?></p>
                </div>

                <?php if ($problem['constraints']): ?>
                <div class="section">
                    <h4>Constraints</h4>
                    <p><?php echo nl2br(htmlspecialchars($problem['constraints'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($problem['example_input'] && $problem['example_output']): ?>
                <div class="section">
                    <h4>Example</h4>
                    <div class="code-example">
                        <strong>Input:</strong> <?php echo htmlspecialchars($problem['example_input']); ?><br>
                        <strong>Output:</strong> <?php echo htmlspecialchars($problem['example_output']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Code Editor -->
            <div class="editor-panel">
                <div class="editor-controls">
                    <select id="languageSelect" onchange="changeLanguage()">
                        <option value="javascript" <?php echo $savedLanguage === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                        <option value="python" <?php echo $savedLanguage === 'python' ? 'selected' : ''; ?>>Python</option>
                        <option value="java" <?php echo $savedLanguage === 'java' ? 'selected' : ''; ?>>Java</option>
                        <option value="cpp" <?php echo $savedLanguage === 'cpp' ? 'selected' : ''; ?>>C++</option>
                    </select>
                    
                    <button class="btn btn-primary" onclick="runCode()">▶ Run Code</button>
                    <button class="btn btn-secondary" onclick="saveProgress()">💾 Save</button>
                </div>

                <textarea id="codeEditor"><?php echo htmlspecialchars($savedCode); ?></textarea>

                <div class="output-console" id="outputConsole">
                    Output will appear here...
                </div>
            </div>
        </div>

        <!-- Learn Mode -->
        <div id="learnMode" class="learn-mode-content active">
            <div class="panel">
                <h3>📖 Concept Explanation</h3>
                
                <div class="concept-card">
                    <h4>Core Concept</h4>
                    <p><?php echo nl2br(htmlspecialchars($problem['concept_explanation'])); ?></p>
                </div>

                <?php if ($problem['time_complexity'] || $problem['space_complexity']): ?>
                <div class="section">
                    <h4>Complexity Analysis</h4>
                    <?php if ($problem['time_complexity']): ?>
                        <span class="complexity-badge">⏱️ Time: <?php echo htmlspecialchars($problem['time_complexity']); ?></span>
                    <?php endif; ?>
                    <?php if ($problem['space_complexity']): ?>
                        <span class="complexity-badge">💾 Space: <?php echo htmlspecialchars($problem['space_complexity']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="section">
                    <h4>💡 Solution Approach</h4>
                    <button class="btn btn-primary" onclick="viewSolution()">View Solution</button>
                    <div id="solutionContent" style="display: none; margin-top: 20px;">
                        <!-- Solution will be loaded here -->
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

        // Initialize CodeMirror
        document.addEventListener('DOMContentLoaded', () => {
            editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
                mode: 'javascript',
                theme: 'monokai',
                lineNumbers: true,
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false,
                lineWrapping: true
            });

            // Set saved code if exists
            const savedCode = <?php echo json_encode($savedCode); ?>;
            if (savedCode) {
                editor.setValue(savedCode);
            } else {
                // Set starter code based on language
                setStarterCode('javascript');
            }
        });

        function switchMode(mode) {
            const practiceMode = document.getElementById('practiceMode');
            const learnMode = document.getElementById('learnMode');
            const buttons = document.querySelectorAll('.mode-btn');

            buttons.forEach(btn => btn.classList.remove('active'));

            if (mode === 'practice') {
                return; // Mode disabled
            } else {
                practiceMode.style.display = 'none';
                learnMode.classList.add('active');
                buttons[1].classList.add('active');
            }
        }

        function changeLanguage() {
            const language = document.getElementById('languageSelect').value;
            const modes = {
                'javascript': 'javascript',
                'python': 'python',
                'java': 'text/x-java',
                'cpp': 'text/x-c++src'
            };

            editor.setOption('mode', modes[language]);
            setStarterCode(language);
        }

        function setStarterCode(language) {
            const starterCodes = {
                'javascript': '// Write your solution here\nfunction solve(input) {\n    // Your code\n    return result;\n}\n\n// Test cases\nconsole.log("Result:", solve("test"));',
                'python': '# Practice Mode: Syntax & Logic only\n# (Execution disabled for security)\ndef solve(input):\n    # Your logic here\n    return None\n\n# Example call\nprint(solve("test"))',
                'java': '// Practice Mode: Syntax & Logic only\n// (Execution disabled for security)\npublic class Solution {\n    public static void solve(String input) {\n        // Your logic here\n    }\n}',
                'cpp': '// Practice Mode: Syntax & Logic only\n// (Execution disabled for security)\n#include <iostream>\nusing namespace std;\n\nstring solve(string input) {\n    // Your logic here\n    return "";\n}'
            };

            if (!editor.getValue() || editor.getValue().trim() === '' || Object.values(starterCodes).includes(editor.getValue())) {
                editor.setValue(starterCodes[language]);
            }
        }

        async function runCode() {
            const code = editor.getValue();
            const language = document.getElementById('languageSelect').value;
            const output = document.getElementById('outputConsole');

            output.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            if (language === 'javascript') {
                // Run JavaScript locally in browser
                try {
                    const logs = [];
                    const originalLog = console.log;
                    console.log = (...args) => logs.push(args.join(' '));

                    // Use a function wrapper to capture return values and handle scoping
                    const result = eval(code);

                    console.log = originalLog;
                    
                    let finalOutput = logs.join('\n');
                    if (result !== undefined) {
                        finalOutput += (finalOutput ? '\n\n' : '') + 'Returned: ' + JSON.stringify(result);
                    }
                    
                    output.textContent = finalOutput || 'Code executed successfully (no output)';
                    output.style.color = '#d4d4d4';
                } catch (error) {
                    output.textContent = '❌ Execution Error: ' + error.message;
                    output.style.color = '#ff6b6b';
                }
            } else {
                // Inform user about local-only policy
                const langNames = { 'python': 'Python', 'java': 'Java', 'cpp': 'C++' };
                output.innerHTML = `
                    <div style="color: #ecc94b; margin-bottom: 10px;">
                        <i class="fas fa-info-circle"></i> <strong>Local Practice Mode</strong>
                    </div>
                    <p style="color: #cbd5e0; font-size: 0.9rem; line-height: 1.4;">
                        Execution for <strong>${langNames[language]}</strong> is disabled to keep the server secure and efficient. 
                        Use this space to practice your syntax and logic! <br><br>
                        <em>Tip: Use <strong>JavaScript</strong> if you want to see live output.</em>
                    </p>`;
            }
        }

        async function saveProgress() {
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
                        status: 'attempted'
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('✅ Progress saved!');
                } else {
                    alert('❌ Failed to save: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error saving progress');
                console.error(error);
            }
        }

        async function viewSolution() {
            const solutionContent = document.getElementById('solutionContent');
            
            if (solutionContent.style.display === 'block') {
                solutionContent.style.display = 'none';
                return;
            }

            solutionContent.innerHTML = '<p style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Generating detailed solutions with AI...</p>';
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
                    
                    // Helper to render language tabs and code blocks
                    const renderMultiLangCode = (codeObj, type) => {
                        const langs = ['javascript', 'python', 'java', 'cpp'];
                        const langNames = {
                            'javascript': 'JavaScript',
                            'python': 'Python',
                            'java': 'Java',
                            'cpp': 'C++'
                        };
                        
                        // Default to current editor language or javascript
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
                                    <div class="code-example" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; overflow-x: auto; box-shadow: inset 0 2px 10px rgba(0,0,0,0.3);">
                                        <pre style="margin: 0; white-space: pre-wrap; font-family: 'Consolas', 'Monaco', monospace; line-height: 1.5;">${escapeHtml(codeObj[lang] || '// No code generated for this language')}</pre>
                                    </div>
                                </div>`;
                        });

                        tabsHtml += `</div>`;
                        codesHtml += `</div>`;
                        
                        return tabsHtml + codesHtml;
                    };

                    // Helper to format strings/arrays into bullet points
                    const formatList = (content) => {
                        if (!content) return 'N/A';
                        const items = Array.isArray(content) ? content : String(content).split('\n');
                        return items.map(p => {
                            const trimmed = p.trim().replace(/^[-*•]\s*/, '');
                            return trimmed ? `• ${trimmed}<br>` : '';
                        }).join('');
                    };
                    
                    solutionContent.innerHTML = `
                        <!-- Beginner Approach -->
                        <div class="concept-card" style="border-left-color: #00875a; background: #f0fff4;">
                            <h3 style="color: #00875a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-graduation-cap"></i> 🎓 Beginner Approach
                            </h3>

                            <div style="margin-bottom: 25px;">
                                <h4 style="color: #333; font-size: 1.1rem; margin-bottom: 8px;">🤔 Why use a function?</h4>
                                <p style="color: #555; line-height: 1.6;">${beginner.why_function}</p>
                            </div>

                            <div style="margin-bottom: 25px;">
                                <h4 style="color: #333; font-size: 1.1rem; margin-bottom: 8px;">📋 The Plan (Step-by-Step)</h4>
                                <div style="color: #555; line-height: 1.6; background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                                    ${formatList(beginner.plan)}
                                </div>
                            </div>

                            <div style="margin-bottom: 25px;">
                                <h4 style="color: #333; font-size: 1.1rem; margin-bottom: 8px;">📦 Variable Breakdown</h4>
                                <div style="color: #555; line-height: 1.6; font-size: 0.95rem;">
                                    ${formatList(beginner.variables)}
                                </div>
                            </div>

                            <div style="margin-bottom: 25px;">
                                <h4 style="color: #333; font-size: 1.1rem; margin-bottom: 8px;">💻 The Code</h4>
                                ${renderMultiLangCode(beginner.code, 'beginner')}
                            </div>

                            <div style="margin-bottom: 10px;">
                                <h4 style="color: #333; font-size: 1.1rem; margin-bottom: 8px;">🔄 Why this logic/loop?</h4>
                                <p style="color: #555; line-height: 1.6;">${beginner.why_logic}</p>
                            </div>
                        </div>

                        <!-- Optimized Approach -->
                        <div class="concept-card" style="margin-top: 30px; border-left-color: #0052cc; background: #f0f7ff;">
                            <h3 style="color: #0052cc; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-bolt"></i> ⚡ Optimized Approach
                            </h3>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                                    <h4 style="color: #333; font-size: 1rem; margin-bottom: 5px;">🎯 The Goal</h4>
                                    <p style="color: #555; font-size: 0.9rem;">${optimized.goal}</p>
                                </div>
                                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                                    <h4 style="color: #333; font-size: 1rem; margin-bottom: 5px;">🛠️ Technique</h4>
                                    <p style="color: #555; font-size: 0.9rem;">${optimized.technique}</p>
                                </div>
                            </div>

                            <div style="margin-bottom: 25px;">
                                <h4 style="color: #333; font-size: 1.1rem; margin-bottom: 8px;">⚖️ Trade-off & Why it's better</h4>
                                <p style="color: #555; line-height: 1.6;">${optimized.tradeoff}</p>
                            </div>

                            <div style="margin-bottom: 10px;">
                                <h4 style="color: #333; font-size: 1.1rem; margin-bottom: 8px;">🚀 Optimized Code</h4>
                                ${renderMultiLangCode(optimized.code, 'optimized')}
                            </div>
                        </div>
                    `;
                } else {
                    solutionContent.innerHTML = `
                        <div class="concept-card" style="background: #ffe9e9; border-left-color: #bf2600;">
                            <p>❌ Failed to generate solution: ${data.message || 'Unknown error'}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error generating solution:', error);
                solutionContent.innerHTML = `
                    <div class="concept-card" style="background: #ffe9e9; border-left-color: #bf2600;">
                        <p>❌ Error generating solution. Please try again.</p>
                    </div>
                `;
            }
        }

        function switchSolutionLang(lang, type) {
            // Update tabs
            const tabs = document.querySelectorAll(`.sol-tab`);
            // We need to find the correct tabs for this type
            // But actually we can just find the parent
            const container = document.getElementById(`sol-code-${type}-${lang}`).parentElement.parentElement;
            const typeTabs = container.querySelectorAll(`.sol-tab`);
            typeTabs.forEach(tab => {
                if (tab.textContent.toLowerCase() === lang.replace('cpp', 'c++') || (lang === 'cpp' && tab.textContent === 'C++')) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });

            // Update code blocks
            const blocks = container.querySelectorAll(`.sol-code-block`);
            blocks.forEach(block => {
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

