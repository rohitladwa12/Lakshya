<?php
require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireLogin();

// Handle POST from Assigned Task
if (isPost() && (isset($_POST['company']) || isset($_POST['task_id']))) {
    SessionFilterHelper::setFilters('ai_technical_round', [
        'company' => $_POST['company'] ?? 'General',
        'concept' => $_POST['concept'] ?? '',
        'task_id' => $_POST['task_id'] ?? 0
    ]);
    header("Location: ai_technical_round.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('ai_technical_round');
$companyName = $filters['company'] ?? 'General';
$taskId = $filters['task_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Round - <?php echo htmlspecialchars($companyName); ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Code Mirror for Editor -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/dracula.min.css">
    <!-- html2pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>

    
    <style>
        :root {
            --bg-dark: #121212;
            --panel-bg: #1e1e1e;
            --primary: #800000; /* Maroon */
            --accent: #e9c66f; /* Gold */
            --text: #e0e0e0;
            --code-bg: #282a36;
        }

        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: var(--bg-dark); color: var(--text); font-family: 'Inter', sans-serif; height: 100vh; display: flex; flex-direction: column; }

        header {
            background: #181818;
            padding: 15px 30px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .role-badge { background: var(--accent); color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; }

        .main-container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* Left Split: Chat/Interaction */
        .chat-panel {
            width: 40%;
            background: var(--panel-bg);
            border-right: 1px solid #333;
            display: flex;
            flex-direction: column;
            padding: 0;
        }

        .chat-history {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scroll-behavior: smooth;
        }

        .message { margin-bottom: 20px; max-width: 90%; line-height: 1.5; font-size: 0.95rem; }
        .message.ai { align-self: flex-start; }
        .message.ai .bubble { 
            background: #2d2d2d; padding: 15px; border-radius: 12px 12px 12px 0; 
            border-left: 3px solid var(--accent);
        }
        .message.user { align-self: flex-end; margin-left: auto; }
        .message.user .bubble { 
            background: var(--primary); color: white; padding: 12px 18px; border-radius: 12px 12px 0 12px; 
        }

        .input-area {
            padding: 20px;
            background: #181818;
            border-top: 1px solid #333;
            display: flex;
            gap: 10px;
        }

        input[type="text"] {
            flex: 1;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 8px;
            background: #222;
            color: white;
            outline: none;
        }

        button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-send { background: var(--primary); color: white; }
        .btn-send:hover { background: #a00000; }

        /* Right Split: Coding Environment */
        .code-panel {
            width: 60%;
            background: var(--bg-dark);
            display: flex; /* Hidden by default if conceptual, but flex here for layout */
            flex-direction: column;
        }

        .code-header {
            padding: 10px 20px;
            background: #181818;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #333;
        }

        .code-wrapper { flex: 1; position: relative; }
        .CodeMirror { height: 100% !important; font-family: 'JetBrains Mono', monospace; font-size: 14px; }

        .console-panel {
            height: 150px;
            background: #1e1e1e;
            border-top: 1px solid #333;
            padding: 15px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
        }

        .console-title { font-size: 0.8rem; color: #888; margin-bottom: 5px; text-transform: uppercase; }
        .output { color: #aaa; }
        .output.success { color: #50fa7b; }
        .output.error { color: #ff5555; }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column;
        }

        .hidden { display: none !important; }

        /* Timer Styles */
        #sessionTimer {
            background: rgba(0,0,0,0.3);
            padding: 8px 15px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            color: var(--accent);
            border: 1px solid rgba(233, 198, 111, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #sessionTimer.locked { color: #ff5555; border-color: rgba(255, 85, 85, 0.3); }
        #sessionTimer.unlocked { color: #50fa7b; border-color: rgba(80, 250, 123, 0.3); }
        
        .btn-end { background: #333; color: white; margin-left: 15px; opacity: 1; transition: all 0.3s; }
        .btn-end:disabled { opacity: 0.3; cursor: not-allowed; }
        .btn-end.unlocked { background: var(--primary); color: white; animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(128, 0, 0, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(128, 0, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(128, 0, 0, 0); }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(8px);
            z-index: 5000;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            color: white;
            transition: opacity 0.5s;
        }
        .loading-spinner {
            width: 80px; height: 80px;
            border: 5px solid rgba(255,255,255,0.1);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <!-- Intro Overlay -->
    <div id="introOverlay" class="overlay">
        <div style="text-align: center; max-width: 600px; padding: 40px; background: #1e1e1e; border-radius: 16px; border: 1px solid #333;">
            <h1 style="color: var(--accent);">Technical Round</h1>
            <p>Role: <strong><?php echo htmlspecialchars($companyName); ?></strong></p>
            <p style="color: #aaa; margin: 20px 0;">
                Prepare for a rigorous technical assessment.<br>
                The AI interviewer is strict and expects precise answers.<br>
                You will face both <strong>Conceptual</strong> questions and <strong>Practical Industry Scenarios</strong>.
            </p>
            <div style="margin-bottom: 20px;">
                <input type="text" id="roleInput" placeholder="Enter specific role (e.g. Backend Dev)" value="Software Engineer" 
                       style="padding: 10px; width: 100%; max-width: 300px; text-align: center;">
            </div>
            <button onclick="startSession()" class="btn-send" style="padding: 15px 40px; font-size: 1.1rem;">Start Interface</button>
        </div>
    </div>

    <header>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-terminal" style="color: var(--accent); font-size: 1.5rem;"></i>
            <div>
                <h2 style="margin: 0; font-size: 1.2rem;">Technical Assessment</h2>
                <div style="font-size: 0.8rem; color: #888;"><?php echo htmlspecialchars($companyName); ?></div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div id="sessionTimer" class="locked">
                <i class="fas fa-clock"></i>
                <span id="timerText">Initializing...</span>
            </div>
            <span class="role-badge" id="roleBadge">Loading...</span>
            <button id="endSessionBtn" onclick="endSession()" class="btn-end" disabled title="Minimum 20 minutes required for assigned tasks">End Session</button>
        </div>
    </header>

    <div class="main-container">
        <!-- Chat Side -->
        <div class="chat-panel">
            <div class="chat-history" id="chatHistory">
                <!-- Messages go here -->
            </div>
            <div class="input-area">
                <input type="text" id="userInput" placeholder="Type your answer..." onkeypress="handleEnter(event)">
                <button class="btn-send" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>

        <!-- Code Side -->
        <div class="code-panel" id="codePanel" style="position: relative;">
            <!-- Locked Overlay -->
            <div id="editorLocked" style="position: absolute; top:0; left:0; width: 100%; height: 100%; z-index: 10; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; justify-content: center; align-items: center; color: #888;">
                <i class="fas fa-lock" style="font-size: 2rem; margin-bottom: 10px;"></i>
                <p>Editor Locked</p>
                <small>Waiting for Coding Challenge...</small>
            </div>

            <div class="code-header">
                <span id="problemTitle" style="font-weight: bold;">Coding Workspace</span>
                <div style="display: flex; gap: 10px;">
                    <select id="langSelect" style="background:#222; color:white; border:none; padding:5px;">
                        <option value="python">Python</option>
                        <option value="javascript">JavaScript</option>
                        <option value="java">Java</option>
                        <option value="cpp">C++</option>
                    </select>
                    <button onclick="runCode()" style="background: var(--accent); color: black;"><i class="fas fa-play"></i> Run & Check</button>
                </div>
            </div>
            <div class="code-wrapper">
                <textarea id="codeEditor"></textarea>
            </div>
            <div class="console-panel">
                <div class="console-title">Output Console</div>
                <div id="consoleOutput" class="output">// Execution results will appear here...</div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay hidden">
        <div class="loading-spinner"></div>
        <h2 style="margin: 0; letter-spacing: 2px;">GENERATING REPORT</h2>
        <p style="color: rgba(255,255,255,0.6); margin-top: 10px;">Please wait while AI evaluates your performance...</p>
    </div>

    <!-- Warning Overlay -->
    <div id="warningOverlay" class="overlay hidden" style="background: rgba(0,0,0,0.95); z-index: 2000;">
        <div style="text-align: center; max-width: 500px; padding: 30px; border: 2px solid var(--primary); background: #000; border-radius: 12px;">
            <i class="fas fa-exclamation-triangle" style="color: var(--primary); font-size: 3rem; margin-bottom: 20px;"></i>
            <h2 style="color: #fff; margin-bottom: 10px;">Security Violation</h2>
            <p style="color: #ccc; margin-bottom: 25px;">
                You have exited Full Screen mode. This is a violation of the assessment protocols.<br>
                Please return to full screen immediately to continue.
            </p>
            <button onclick="resumeFullscreen()" class="btn-send" style="width: 100%;">RESUME ASSESSMENT</button>
        </div>
    </div>

    <script>
        let sessionId = null;
        let company = "<?php echo addslashes($companyName); ?>";
        let editor;
        let currentProblem = null; 
        let isSessionActive = false; // Track session state
        let isProcessing = false;    // Fix: was undefined, caused loadNextQuestion() to return immediately
        let startTime = null;
        let isTaskId = <?php echo $taskId ? 'true' : 'false'; ?>;
        const MIN_REQUIRED_TIME = 20 * 60; // 20 minutes in seconds

        // Initialize CodeMirror
        window.onload = function() {
            editor = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
                mode: "python",
                theme: "dracula",
                lineNumbers: true,
                autoCloseBrackets: true
            });
            editor.setValue("# Waiting for a coding challenge...");

            // Fullscreen Enforcement Listener
            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement && isSessionActive) {
                    document.getElementById('warningOverlay').classList.remove('hidden');
                }
            });
        };

        // --- Security Measures: Disable Copy/Paste/Right-Click ---
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('cut', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());
        
        document.addEventListener('keydown', e => {
            // Disable Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+U, Ctrl+Shift+I (Inspect)
            if (e.ctrlKey && ['c', 'v', 'x', 'u'].includes(e.key.toLowerCase())) {
                e.preventDefault();
            }
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
            }
            // Disable F12
            if (e.key === 'F12') {
                e.preventDefault();
            }
        });

        function resumeFullscreen() {
            document.documentElement.requestFullscreen().then(() => {
                document.getElementById('warningOverlay').classList.add('hidden');
            }).catch(e => {
                alert("Please manually enable full screen (F11)");
            });
        }

        async function startSession() {
            const roleInput = document.getElementById('roleInput').value;
            
            // 1. Check for Active Session Resumption
            const checkRes = await apiCall({ action: 'check_active_session', company: company });
            
            if (checkRes.success && checkRes.has_active) {
                if (confirm("You have an active session for this company. Would you like to resume?")) {
                    sessionId = checkRes.session_id;
                    isSessionActive = true;
                    
                    // Sync start time from server
                    if (checkRes.started_at) {
                        startTime = new Date(checkRes.started_at.replace(/-/g, "/")).getTime();
                    } else {
                        startTime = Date.now();
                    }
                    
                    document.getElementById('introOverlay').classList.add('hidden');
                    document.getElementById('roleBadge').innerText = checkRes.role || roleInput;
                    
                    if (document.documentElement.requestFullscreen) {
                        await document.documentElement.requestFullscreen().catch((e) => console.log(e));
                    }
                    
                    startTimer();
                    updateState("Resuming technical session...", "neutral");
                    
                    // Re-render history
                    if (checkRes.history && checkRes.history.length > 0) {
                        checkRes.history.forEach(m => {
                            if (m.content) {
                                // If it's a structured JSON string from assistant, try parsing it
                                let text = m.content;
                                try {
                                    const parsed = JSON.parse(m.content);
                                    if (parsed.question) text = parsed.question;
                                    else if (parsed.problem_statement) text = parsed.problem_statement;
                                } catch(e) {}
                                addMessage(m.role === 'assistant' ? 'ai' : 'user', text);
                            }
                        });
                        addMessage('ai', "Continuing interview. Please look at the previous context.");
                    } else {
                        loadNextQuestion();
                    }
                    return;
                }
            }

            const role = roleInput;
            document.getElementById('roleBadge').innerText = role;
            document.getElementById('introOverlay').classList.add('hidden');
            
            // Fullscreen trigger
            if (document.documentElement.requestFullscreen) {
                await document.documentElement.requestFullscreen().catch((e) => console.log(e));
            }

            // API Call to start
            const res = await apiCall({ action: 'start_session', role: role, company: company, task_id: "<?php echo $taskId; ?>" });
            if (res.success) {
                sessionId = res.session_id;
                isSessionActive = true; 
                startTime = Date.now();
                startTimer();
                addMessage('ai', "Initializing environment... Ready for technical screening.");
                loadNextQuestion();
            }
        }

        function startTimer() {
            const timerContainer = document.getElementById('sessionTimer');
            const timerText = document.getElementById('timerText');
            const endBtn = document.getElementById('endSessionBtn');

            setInterval(() => {
                if (!isSessionActive) return;

                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                
                if (isTaskId && elapsed < MIN_REQUIRED_TIME) {
                    const remaining = MIN_REQUIRED_TIME - elapsed;
                    const mins = Math.floor(remaining / 60);
                    const secs = remaining % 60;
                    timerText.innerText = `Lock: ${mins}:${secs.toString().padStart(2, '0')}`;
                    timerContainer.className = 'locked';
                    endBtn.disabled = true;
                    endBtn.title = `You must wait ${mins}m ${secs}s more before ending this assigned task.`;
                } else {
                    const mins = Math.floor(elapsed / 60);
                    const secs = elapsed % 60;
                    timerText.innerText = `Duration: ${mins}:${secs.toString().padStart(2, '0')}`;
                    timerContainer.className = 'unlocked';
                    endBtn.disabled = false;
                    endBtn.classList.add('unlocked');
                    endBtn.title = "";
                }
            }, 1000);
        }

        async function loadNextQuestion(userMsg = '') {
            if (isProcessing) return;
            isProcessing = true;
            showTyping();
            
            try {
                const res = await apiCall({ action: 'get_question', session_id: sessionId, message: userMsg });
                
                if (res.success && res.job_id) {
                    pollJobStatus(res.job_id, (data) => {
                        hideTyping();
                        isProcessing = false;
                        processAIResponse(data);
                    }, (err) => {
                        hideTyping();
                        isProcessing = false;
                        alert("AI Error: " + err);
                    });
                } else {
                    hideTyping();
                    isProcessing = false;
                    alert("Failed to reach AI.");
                }
            } catch (e) {
                hideTyping();
                isProcessing = false;
                alert("Connection failed.");
            }
        }

        async function pollJobStatus(jobId, onSuccess, onError) {
            const poll = async () => {
                try {
                    const res = await fetch(`ai_job_status.php?job_id=${jobId}`);
                    const data = await res.json();
                    if (data.status === 'completed') onSuccess(data.result);
                    else if (data.status === 'failed') onError(data.error);
                    else setTimeout(poll, 1500);
                } catch (e) { onError("Polling error"); }
            };
            poll();
        }

                function processAIResponse(data) {
            // Because AIService returns data wrapped in various formats (e.g. {success: true, result: {...}} or {content: "..."})
            let payload = data;
            
            if (data.result && typeof data.result === 'object') {
                payload = data.result;
            } else if (data.content && typeof data.content === 'string') {
                try { payload = JSON.parse(data.content); } catch(e) {}
            }

            const isCode = payload.type === 'coding';
            const questionText = isCode ? payload.problem_statement : payload.question;
            const feedbackText = payload.feedback ? `**Evaluation:** ${payload.feedback}\n\n` : '';
            
            addMessage('ai', (feedbackText + (questionText || JSON.stringify(payload))).trim());

            // Save AI response to database to persist context
            apiCall({ action: 'append_ai_history', session_id: sessionId, message: JSON.stringify(payload) });

            // Handle Coding State
            if (isCode) {
                currentProblem = payload;
                activateCodingMode(payload);
            } else {
                currentProblem = null;
                document.getElementById('editorLocked').style.display = 'flex';
            }
        }

        async function sendMessage() {
            const input = document.getElementById('userInput');
            const txt = input.value.trim();
            if (!txt) return;

            addMessage('user', txt);
            input.value = '';

            // If we are in coding mode, user might be explaining logic, but actual code submission is via Run button.
            // However, we process chat normally.
            loadNextQuestion(txt);
        }

        async function runCode() {
            if (!currentProblem) {
                alert("No active coding challenge.");
                return;
            }

            const code = editor.getValue();
            const lang = document.getElementById('langSelect').value;
            const outputDiv = document.getElementById('consoleOutput');

            outputDiv.innerText = "Running test cases via AI Evaluator...";
            outputDiv.className = "output";

            const res = await apiCall({
                action: 'submit_code',
                session_id: sessionId,
                code: code,
                language: lang,
                problem_statement: currentProblem.problem_statement
            });

            // Fix: submit_code returns a job_id (async), not a sync result
            if (res.success && res.job_id) {
                outputDiv.innerText = "Evaluating... (this may take ~10s)";
                pollJobStatus(res.job_id, (result) => {
                    outputDiv.innerText = `${result.feedback}\n\nPassed: ${result.passed ? 'YES ✓' : 'NO ✗'}\nScore: ${result.score}/10`;
                    outputDiv.className = result.passed ? "output success" : "output error";
                    if (result.passed) {
                        setTimeout(() => {
                            alert("Great execution! Moving to next challenge.");
                            loadNextQuestion("Code submitted successfully. Ready for next.");
                        }, 2000);
                    }
                }, (err) => {
                    outputDiv.innerText = "Evaluation failed: " + err;
                    outputDiv.className = "output error";
                });
            } else {
                outputDiv.innerText = "Failed to submit code.";
                outputDiv.className = "output error";
            }
        }

        async function endSession() {
            if (!confirm("Are you sure? This will generate your report.")) return;
            
            isSessionActive = false; // Disable enforcement for report generation
            if (document.fullscreenElement) {
                document.exitFullscreen().catch(e => console.log(e));
            }

            document.getElementById('loadingOverlay').classList.remove('hidden');
            
            // 1. Get Report Data
            const res = await apiCall({ action: 'generate_report_data', session_id: sessionId });
            
            if (res.success) {
                // 2. Client-side PDF Generation
                const element = document.createElement('div');
                element.innerHTML = res.report_html; // Backend returns HTML string
                
                const opt = {
                    margin: 1,
                    filename: res.filename, // usn_sem.pdf
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                };

                // Generate and Upload
                html2pdf().set(opt).from(element).outputPdf('blob').then(async (pdfBlob) => {
                    const formData = new FormData();
                    formData.append('action', 'save_pdf_report');
                    formData.append('session_id', sessionId);
                    formData.append('pdf', pdfBlob, res.filename);
                    
                    const uploadRes = await fetch('ai_technical_handler', { method: 'POST', body: formData });
                    const uploadData = await uploadRes.json();
                    
                    if (uploadData.success) {
                        // Trigger download
                        html2pdf().set(opt).from(element).save();
                        setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);
                    } else {
                        document.getElementById('loadingOverlay').classList.add('hidden');
                        alert("Failed to save report to server.");
                    }
                });
            } else {
                document.getElementById('loadingOverlay').classList.add('hidden');
                alert("Failed to generate report data.");
            }
        }

        function activateCodingMode(data) {
            document.getElementById('editorLocked').style.display = 'none';
            
            document.getElementById('problemTitle').innerText = "CHALLENGE ACTIVE";
            
            // Set starter code or comment
            const starter = `# Problem: ${data.question}\n# Constraints: ${data.constraints || 'None'}\n# Write your solution below:\n\n`;
            editor.setValue(starter);
        }

        // --- Helpers ---
        async function apiCall(data) {
            try {
                const formData = new FormData();
                for (const k in data) formData.append(k, data[k]);
                
                const response = await fetch('ai_technical_handler', { method: 'POST', body: formData });
                return await response.json();
            } catch (e) {
                console.error(e);
                alert("Connection Error");
                return { success: false };
            }
        }

        function addMessage(role, text) {
            const div = document.createElement('div');
            div.className = `message ${role}`;
            
            // Simple Markdown parsing
            let html = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                           .replace(/\n/g, '<br>');
            
            div.innerHTML = `<div class="bubble">${html}</div>`;
            document.getElementById('chatHistory').appendChild(div);
            document.getElementById('chatHistory').scrollTop = document.getElementById('chatHistory').scrollHeight;
        }
        
        function showTyping() { /* optional loader */ }
        function hideTyping() { /* optional loader */ }
        
        function handleEnter(e) {
            if (e.key === 'Enter') sendMessage();
        }
    </script>
</body>
</html>

