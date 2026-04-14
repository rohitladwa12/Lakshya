<?php
/**
 * NQT Technical Round Interface
 * Premium dual-panel workspace for NQT coding challenges
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();

use App\Helpers\SessionFilterHelper;

requireLogin();

$fullName = getFullName();

// Handle POST from assigned_task.php or dashboard
if (isPost() && (isset($_POST['company']) || isset($_POST['task_id']))) {
    SessionFilterHelper::setFilters('nqt_technical', [
        'company' => $_POST['company'] ?? 'TCS NQT Practice',
        'task_id' => $_POST['task_id'] ?? 0
    ]);
    header("Location: nqt_technical_round.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('nqt_technical');
$companyName = $filters['company'] ?? 'TCS NQT Practice';
$taskId = $filters['task_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NQT Coding Workspace - Lakshya</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Code Mirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/dracula.min.css">
    
    <!-- html2pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --primary: #800000; /* Maroon */
            --secondary: #e9c66f; /* Gold */
            --dark: #0a0a0a;
            --panel: #161616;
            --glass: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.1);
            --text: #e0e0e0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: var(--dark); color: var(--text); height: 100vh; overflow: hidden; display: flex; flex-direction: column; }

        header {
            padding: 12px 30px;
            background: #000;
            border-bottom: 2px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .main-workspace { flex: 1; display: flex; overflow: hidden; }

        /* Left Panel */
        .interaction-panel {
            width: 38%;
            background: var(--panel);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .problem-container {
            padding: 24px;
            background: linear-gradient(180deg, rgba(128,0,0,0.05) 0%, transparent 100%);
            border-bottom: 1px solid var(--border);
            max-height: 45%;
            overflow-y: auto;
        }

        .chat-history { flex: 1; overflow-y: auto; padding: 20px; scroll-behavior: smooth; }

        .bubble { 
            max-width: 85%; margin-bottom: 16px; padding: 14px 18px; border-radius: 12px; 
            font-size: 0.95rem; line-height: 1.6;
        }
        .bubble.ai { 
            background: var(--glass); border: 1px solid var(--border); border-bottom-left-radius: 2px;
            color: #ccc;
        }
        .bubble.user { 
            background: var(--primary); color: white; margin-left: auto; border-bottom-right-radius: 2px;
        }

        .input-bar {
            padding: 20px;
            background: #000;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
        }

        /* Right Panel */
        .editor-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #1e1e1e;
        }

        .editor-header {
            padding: 8px 20px;
            background: #181818;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }

        .CodeMirror { height: 100% !important; font-family: 'JetBrains Mono', monospace; font-size: 14px; }

        .btn {
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-gold { background: var(--secondary); color: #000; }
        .btn-gold:hover { transform: translateY(-1px); background: #d4b45d; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: #888; }
        .btn-outline:hover { color: #fff; border-color: #fff; }

        /* Overlays */
        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            z-index: 1000; text-align: center;
        }
        .start-overlay { background: radial-gradient(circle, #1a0000 0%, #050505 100%); }
        .security-overlay { background: rgba(0,0,0,0.98); display: none; }

        .glass-card {
            background: rgba(20, 20, 20, 0.8);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            border: 1px solid var(--secondary);
            max-width: 500px;
            box-shadow: 0 0 40px rgba(128,0,0,0.3);
        }

        .loader-overlay {
            position: fixed; top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.85); z-index: 5000;
            display: none; flex-direction: column; align-items: center; justify-content: center;
        }

        .spinner {
            width: 40px; height: 40px; border: 3px solid var(--glass);
            border-top-color: var(--secondary); border-radius: 50%;
            animation: spin 1s infinite linear;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        input[type="text"] {
            flex: 1; background: #222; border: 1px solid var(--border);
            padding: 12px 18px; border-radius: 8px; color: #fff; outline: none;
        }
    </style>
</head>
<body>

    <header>
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-microchip" style="color: var(--secondary); font-size: 1.8rem;"></i>
            <div>
                <h2 style="font-size: 1.1rem; letter-spacing: 1px;">NQT TECHNICAL ROUND</h2>
                <div style="font-size: 0.75rem; color: #666; text-transform: uppercase;"><?php echo htmlspecialchars($fullName); ?></div>
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <div id="timerDisplay" style="font-size: 1.1rem; color: var(--secondary); font-weight: 600; font-family: 'JetBrains Mono', monospace; margin-right: 15px;"><i class="fas fa-clock"></i> 60:00</div>
            <button onclick="endSession()" class="btn btn-outline">FINISH ASSESSMENT</button>
        </div>
    </header>

    <div class="main-workspace">
        <div class="interaction-panel">
            <div class="problem-container" id="problemDisplay">
                <h3 style="color: var(--secondary); margin-bottom: 12px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Active Challenge</h3>
                <div id="pText" style="font-size: 0.95rem; line-height: 1.6; color: #bbb;">
                    Initializing assessment environment...
                </div>
            </div>
            
            <div class="chat-history" id="chatHistory">
                <!-- Messages -->
            </div>

            <div class="input-bar">
                <input type="text" id="userInput" placeholder="Ask AI regarding the challenge..." onkeypress="if(event.key=='Enter')sendMessage()">
                <button class="btn btn-gold" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>

        <div class="editor-panel">
            <div class="editor-header">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <i class="fas fa-code" style="color: #666;"></i>
                    <select id="langSelect" style="background:#222; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size: 0.8rem;">
                        <option value="python">Python 3</option>
                        <option value="javascript">JavaScript</option>
                        <option value="cpp">C++ 17</option>
                        <option value="java">Java 11</option>
                    </select>
                </div>
                <button class="btn btn-gold" onclick="submitCode()">
                    <i class="fas fa-play"></i> RUN & SUBMIT
                </button>
            </div>
            <textarea id="codeEditor"></textarea>
        </div>
    </div>

    <!-- Start Overlay -->
    <div id="startOverlay" class="overlay start-overlay">
        <div class="glass-card">
            <i class="fas fa-user-shield" style="font-size: 3.5rem; color: var(--secondary); margin-bottom: 20px;"></i>
            <h2 style="margin-bottom: 15px;">NQT Technical Simulation</h2>
            <p style="opacity: 0.7; font-size: 0.95rem; margin-bottom: 30px;">
                This assessment uses AI-proctoring. Ensure you are in a quiet environment. 
                Exiting fullscreen or switching tabs will result in a security violation.
            </p>
            <button onclick="beginSession()" class="btn btn-gold" style="width:100%; padding: 15px; justify-content: center; font-size: 1.1rem;">
                ENTER WORKSPACE
            </button>
        </div>
    </div>

    <!-- Security Overlay -->
    <div id="securityOverlay" class="overlay security-overlay">
        <div class="glass-card" style="border-color: var(--primary);">
            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;"></i>
            <h2 style="color: #fff;">Protocol Violation</h2>
            <p style="opacity: 0.8; margin-top: 15px; margin-bottom: 30px;">
                Assessment paused. You have exited the secure fullscreen environment. 
                Please return to proceed. Continued violations will be logged.
            </p>
            <button onclick="requestFullscreen()" class="btn btn-gold" style="width:100%; justify-content: center;">RESUME ROUND</button>
        </div>
    </div>

    <!-- Time Up Overlay -->
    <div id="timeUpOverlay" class="overlay security-overlay" style="z-index: 4000;">
        <div class="glass-card" style="border-color: var(--primary);">
            <i class="fas fa-clock" style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;"></i>
            <h2 style="color: #fff;">Time is over please exit</h2>
            <button onclick="endSession()" class="btn btn-gold" style="width:100%; justify-content: center; margin-top:20px;">EXIT ASSESSMENT</button>
        </div>
    </div>

    <div id="loader" class="loader-overlay">
        <div class="spinner"></div>
        <p id="loaderText" style="margin-top: 20px; font-weight: 500; letter-spacing: 1px;">EVALUATING SOLUTION...</p>
    </div>

    <!-- Code Mirror Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>

    <script>
        let editor, sessionId, currentProblem = "";
        let isAssessmentActive = false;
        let timeRemaining = 3600;
        let timerInterval;

        function startTimer() {
            timerInterval = setInterval(() => {
                timeRemaining--;
                let m = Math.floor(timeRemaining / 60).toString().padStart(2, '0');
                let s = (timeRemaining % 60).toString().padStart(2, '0');
                document.getElementById('timerDisplay').innerHTML = `<i class="fas fa-clock"></i> ${m}:${s}`;
                
                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    handleTimeUp();
                }
            }, 1000);
        }

        function handleTimeUp() {
            document.getElementById('timeUpOverlay').style.display = 'flex';
        }

        // Init Editor
        window.onload = () => {
            editor = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
                mode: "python",
                theme: "dracula",
                lineNumbers: true,
                autoCloseBrackets: true,
                indentUnit: 4
            });
            editor.setValue("# Write your solution logic here...\n\n");
        };

        async function beginSession() {
            document.getElementById('startOverlay').style.display = 'none';
            try {
                await requestFullscreen();
            } catch(e) { console.error(e); }
            startAPISession();
        }

        async function startAPISession() {
            const res = await apiCall({ action: 'start_session', task_id: "<?php echo $taskId; ?>" });
            if (res.success) {
                sessionId = res.session_id;
                isAssessmentActive = true;
                startTimer();
                addMessage('ai', "Welcome. I have prepared your first technical challenge. Focus on efficiency and edge cases.");
                loadQuestion();
            }
        }

        async function loadQuestion(userMsg = '') {
            const res = await apiCall({ action: 'get_question', session_id: sessionId, message: userMsg });
            if (res.success && res.data) {
                const q = res.data;
                currentProblem = q.problem_statement;
                
                document.getElementById('pText').innerHTML = `
                    <div style="font-weight: 600; color: #fff; margin-bottom: 10px;">${q.title || 'Coding Challenge'}</div>
                    ${q.problem_statement}<br><br>
                    <strong>Input:</strong> <code style="color: var(--secondary);">${q.example_input || 'N/A'}</code><br>
                    <strong>Output:</strong> <code style="color: var(--secondary);">${q.example_output || 'N/A'}</code>
                `;
                
                addMessage('ai', q.question || "Implement the logic described in the panel above.");
            }
        }

        async function sendMessage() {
            const input = document.getElementById('userInput');
            const msg = input.value.trim();
            if(!msg) return;
            addMessage('user', msg);
            input.value = "";
            loadQuestion(msg);
        }

        async function submitCode() {
            showLoader("VALIDATING LOGIC...");
            const code = editor.getValue();
            const lang = document.getElementById('langSelect').value;
            
            const res = await apiCall({
                action: 'submit_code',
                session_id: sessionId,
                code: code,
                language: lang,
                problem_statement: currentProblem
            });
            
            hideLoader();
            if (res.success) {
                const eval = res.result;
                addMessage('ai', `<strong>Evaluation Result:</strong> ${eval.score}/10<br>${eval.feedback}`);
                if (eval.score >= 8) {
                    addMessage('ai', "Great work. Preparing your next challenge...");
                    setTimeout(() => {
                        editor.setValue("");
                        loadQuestion("Proceed to next.");
                    }, 2500);
                }
            }
        }

        async function endSession() {
            if(!confirm("Are you sure? This will finalize your results and generate the report.")) return;
            
            isAssessmentActive = false;
            if(document.fullscreenElement) document.exitFullscreen().catch(()=>{});
            
            showLoader("GENERATING PERFORMANCE REPORT...");
            const res = await apiCall({ action: 'generate_report_data', session_id: sessionId });
            
            if (res.success) {
                // ... (rest of the logic remains same, just replacing the fail block below)
                const element = document.createElement('div');
                element.innerHTML = res.report_html;
                
                const opt = {
                    margin: 0.5,
                    filename: res.filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                };

                html2pdf().set(opt).from(element).outputPdf('blob').then(async (blob) => {
                    const formData = new FormData();
                    formData.append('action', 'save_pdf_report');
                    formData.append('session_id', sessionId);
                    formData.append('pdf', blob, res.filename);
                    
                    const upload = await fetch('nqt_technical_handler', { method: 'POST', body: formData });
                    const uploadRes = await upload.json();
                    
                    hideLoader();
                    if (uploadRes.success) {
                        html2pdf().set(opt).from(element).save();
                        alert("Assessment Completed. Your report has been saved.");
                        setTimeout(() => { window.location.href = 'dashboard'; }, 1500);
                    } else {
                        alert("Failed to save report to server: " + (uploadRes.message || "Unknown error"));
                    }
                });
            } else {
                hideLoader();
                alert("Critical Failure: " + (res.message || "Report generation failed. Please try again or contact support."));
            }
        }

        // --- Helpers & Security ---
        async function apiCall(data) {
            try {
                const formData = new FormData();
                for (const k in data) formData.append(k, data[k]);
                const response = await fetch('nqt_technical_handler', { 
                    method: 'POST', 
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                return await response.json();
            } catch (e) {
                console.error(e);
                return { success: false, message: e.message };
            }
        }

        function addMessage(role, text) {
            const div = document.createElement('div');
            div.className = `bubble ${role}`;
            div.innerHTML = text.replace(/\n/g, '<br>');
            document.getElementById('chatHistory').appendChild(div);
            document.getElementById('chatHistory').scrollTop = document.getElementById('chatHistory').scrollHeight;
        }

        function showLoader(text) {
            document.getElementById('loaderText').innerText = text;
            document.getElementById('loader').style.display = 'flex';
        }
        function hideLoader() { document.getElementById('loader').style.display = 'none'; }

        function requestFullscreen() {
            const el = document.documentElement;
            if (el.requestFullscreen) return el.requestFullscreen();
            if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
            if (el.msRequestFullscreen) return el.msRequestFullscreen();
        }

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && isAssessmentActive) {
                document.getElementById('securityOverlay').style.display = 'flex';
            } else {
                document.getElementById('securityOverlay').style.display = 'none';
            }
        });

        // Anti-Cheat
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && ['c','v','x','u'].includes(e.key.toLowerCase())) e.preventDefault();
            if (e.key === 'F12') e.preventDefault();
        });

    </script>
</body>
</html>
