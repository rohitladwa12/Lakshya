<?php
/**
 * AI Certification Viva (Verification)
 * Modernized with Glassmorphic UI and Async Queue Processing
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireLogin();

$userId = getUserId();
$username = getUsername();

// Handle POST from Dashboard
if (isPost() && (isset($_POST['id']))) {
    SessionFilterHelper::setFilters('certification_viva', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: certification_viva.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('certification_viva');
$portfolioId = $filters['id'] ?? 0;

// Fetch certification details
$stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
$stmt->execute([$portfolioId, $username]);
$cert = $stmt->fetch();

if (!$cert || $cert['category'] !== 'Certification') {
    header('Location: dashboard');
    exit;
}

$certTitle = $cert['title'];
$issuer = $cert['sub_title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certification Verification: <?php echo htmlspecialchars($certTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #4a0000;
            --accent-gold: #D4AF37;
            --bg-dark: #0f0c29;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-hover: rgba(255, 255, 255, 0.08);
            --text-main: #e0e0e0;
            --text-muted: #a0a0a0;
        }

        * { margin:0; padding:0; box-sizing:border-box; -webkit-user-select: none; user-select: none; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a, #020617);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        input, textarea { -webkit-user-select: text; user-select: text; }

        /* Glassmorphic Background Shapes */
        .bg-shape {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.4;
        }
        .shape-1 { width: 400px; height: 400px; background: var(--primary-maroon); top: -100px; right: -100px; }
        .shape-2 { width: 300px; height: 300px; background: #312e81; bottom: -50px; left: -50px; }

        .navbar {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar h1 {
            font-size: 1.2rem;
            font-weight: 600;
            background: linear-gradient(to right, #fff, var(--accent-gold));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .container {
            flex: 1;
            max-width: 900px;
            margin: 2rem auto;
            width: 95%;
            padding: 2.5rem;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .viva-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .viva-header h3 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 3rem;
        }

        .step-dot {
            width: 45px; height: 6px;
            border-radius: 10px;
            background: var(--glass-border);
            transition: all 0.5s ease;
        }
        .step-dot.active { background: var(--primary-maroon); box-shadow: 0 0 15px var(--primary-maroon); }
        .step-dot.completed { background: #10b981; }

        .question-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .question-label {
            color: var(--accent-gold);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .question-text {
            font-size: 1.4rem;
            font-weight: 500;
            line-height: 1.6;
            color: #fff;
        }

        .answer-area {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            color: #fff;
            font-family: inherit;
            font-size: 1.1rem;
            resize: none;
            min-height: 180px;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .answer-area:focus {
            outline: none;
            border-color: var(--primary-maroon);
            background: rgba(0, 0, 0, 0.3);
            box-shadow: 0 0 20px rgba(128, 0, 0, 0.2);
        }

        .btn-action {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.2rem;
            border: none;
            border-radius: 18px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 10px 20px -5px rgba(128, 0, 0, 0.4);
        }

        .btn-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px -10px rgba(128, 0, 0, 0.6);
        }

        .btn-action:active { transform: scale(0.98); }

        .loading-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: var(--bg-dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 32px;
            z-index: 50;
        }

        /* Pulse Animation for Spinner */
        .spinner-outer {
            width: 80px; height: 80px;
            border: 3px solid transparent;
            border-top: 3px solid var(--primary-maroon);
            border-bottom: 3px solid var(--accent-gold);
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .spinner-inner {
            width: 50px; height: 50px;
            border: 3px solid transparent;
            border-left: 3px solid #fff;
            border-radius: 50%;
            animation: spin-reverse 1s linear infinite;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        @keyframes spin-reverse { 100% { transform: rotate(-360deg); } }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(2, 6, 23, 0.95);
            backdrop-filter: blur(15px);
            z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            padding: 20px;
        }

        .results-card {
            text-align: center;
        }

        .score-circle {
            width: 150px; height: 150px;
            border-radius: 50%;
            border: 8px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0 auto 2rem;
            position: relative;
        }
        .score-val { font-size: 3rem; font-weight: 800; color: #fff; }
        .score-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; }

        .feedback-box {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: left;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-maroon);
        }

        .hidden { display: none !important; }

        @media (max-width: 600px) {
            .container { padding: 1.5rem; margin-top: 1rem; }
            .question-text { font-size: 1.1rem; }
            .btn-action { padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="bg-shape shape-1"></div>
<div class="bg-shape shape-2"></div>

<!-- Intro Overlay -->
<div id="introOverlay" class="overlay">
    <div style="text-align: center; max-width: 600px; padding: 3rem; background: var(--glass); backdrop-filter: blur(30px); border: 1px solid var(--glass-border); border-radius: 40px; box-shadow: 0 40px 100px rgba(0,0,0,0.8);">
        <div style="width: 80px; height: 80px; background: rgba(128, 0, 0, 0.1); border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; border: 1px solid var(--primary-maroon);">
            <i class="fas fa-certificate" style="font-size: 2.5rem; color: var(--primary-maroon);"></i>
        </div>
        <h1 style="color: #fff; margin-bottom: 1rem; font-size: 2rem;">Certification Verification</h1>
        <h2 style="color: var(--accent-gold); margin-bottom: 1.5rem; font-size: 1.2rem;"><?php echo htmlspecialchars($certTitle); ?></h2>
        
        <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 20px; margin-bottom: 2rem; text-align: left; font-size: 0.95rem; line-height: 1.6;">
            <p><i class="fas fa-shield-alt" style="color: #10b981; margin-right: 10px;"></i> This is a proctored, AI-led verification session.</p>
            <p><i class="fas fa-expand" style="color: #3b82f6; margin-right: 10px;"></i> Fullscreen mode is mandatory to ensure integrity.</p>
            <p><i class="fas fa-clock" style="color: #f59e0b; margin-right: 10px;"></i> 5 technical deep-dive questions will be presented.</p>
        </div>

        <button onclick="beginAssessment()" class="btn-action" style="font-size: 1.2rem; padding: 1.5rem;">
            AUTHENTICATE & BEGIN <i class="fas fa-arrow-right"></i>
        </button>
    </div>
</div>

<div class="navbar">
    <h1><i class="fas fa-shield-check"></i> <span>SECURE VERIFICATION ENGINE</span></h1>
    <a href="dashboard" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; transition: 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-muted)'">
        <i class="fas fa-times-circle"></i> CANCEL SESSION
    </a>
</div>

<div class="container" id="vivaContainer">
    <!-- Initial Loading -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner-outer">
            <div class="spinner-inner"></div>
        </div>
        <h2 id="loadingText" style="margin-top: 2rem; font-weight: 500; letter-spacing: 1px;">INITIALIZING AI AUDITOR...</h2>
        <p style="color: var(--text-muted); margin-top: 10px; font-size: 0.9rem;">This may take up to 30 seconds</p>
    </div>

    <!-- Interface -->
    <div id="vivaView" style="display: none;">
        <div class="viva-header">
            <p style="color: var(--accent-gold); font-size: 0.9rem; font-weight: 600; letter-spacing: 3px; margin-bottom: 10px;">VERIFICATION IN PROGRESS</p>
            <h3><?php echo htmlspecialchars($certTitle); ?></h3>
        </div>

        <div class="progress-steps" id="progressSteps">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <div class="question-card">
            <div class="question-label" id="stepCounterLabel">SCALING QUESTION 1/5</div>
            <div id="questionText" class="question-text">Loading verification criteria...</div>
        </div>

        <textarea id="userAnswer" class="answer-area" placeholder="Enter your detailed technical explanation here..."></textarea>

        <button id="btnSubmit" class="btn-action" onclick="submitAnswer()">
            CONFIRM & NEXT <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <!-- Results View -->
    <div id="resultsView" class="results-card" style="display: none;">
        <div id="successRing" style="font-size: 5rem; color: #10b981; margin-bottom: 2rem;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">Verification Analysis Complete</h2>
        <p style="color: var(--text-muted); margin-bottom: 3rem;">Results processed by Technical Audit AI</p>

        <div class="score-circle" id="scoreCircle">
            <div class="score-val" id="finalScoreVal">85</div>
            <div class="score-label">MATCH SCORE</div>
        </div>

        <div style="margin-bottom: 2.5rem;">
            <div style="font-size: 1.1rem; font-weight: 600; color: #fff; margin-bottom: 10px;">
                STATUS: <span id="finalStatusText" style="color: #10b981;">VERIFIED</span>
            </div>
            <div class="feedback-box" id="finalFeedback">
                Performance feedback will be displayed here...
            </div>
        </div>

        <a href="dashboard" class="btn-action" style="text-decoration: none; width: auto; margin: 0 auto; display: inline-flex; padding: 1.2rem 3rem; background: var(--glass); border: 1px solid var(--glass-border);">
            RETURN TO PORTFOLIO
        </a>
    </div>
</div>

<!-- Warning Overlay -->
<div id="warningOverlay" class="overlay hidden" style="background: rgba(2, 6, 23, 0.98); z-index: 2000;">
    <div style="text-align: center; max-width: 500px; padding: 3rem; border: 1px solid var(--primary-maroon); background: #000; border-radius: 30px; box-shadow: 0 0 50px rgba(128, 0, 0, 0.3);">
        <i class="fas fa-lock" style="color: var(--primary-maroon); font-size: 4rem; margin-bottom: 2rem;"></i>
        <h2 style="margin-bottom: 1rem; color: #fff;">Protocol Breach</h2>
        <p style="color: var(--text-muted); margin-bottom: 2.5rem; line-height: 1.6;">
            Fullscreen mode has been deactivated. For integrity purposes, all verification sessions must be conducted in isolated fullscreen environment.
        </p>
        <button onclick="resumeFullscreen()" class="btn-action" style="width: 100%;">RESUME SESSION</button>
    </div>
</div>

<script>
    // --- Global State ---
    let questions = [];
    let answers = [];
    let currentIdx = 0;
    let isSessionActive = false;
    const portfolioId = <?php echo $portfolioId; ?>;

    // --- Life Cycle Management ---

    document.addEventListener('DOMContentLoaded', () => {
        applyStrictSecurity();
    });

    function applyStrictSecurity() {
        // Block interaction that could allow cheating
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('cut', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());
        
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && ['c', 'v', 'x', 'u'].includes(e.key.toLowerCase())) e.preventDefault();
            if (e.ctrlKey && e.shiftKey && e.key === 'I') e.preventDefault();
            if (e.key === 'F12') e.preventDefault();
        });

        // Fullscreen monitoring
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && isSessionActive) {
                document.getElementById('warningOverlay').classList.remove('hidden');
            }
        });
    }

    async function beginAssessment() {
        document.getElementById('introOverlay').classList.add('hidden');
        await enterFullscreen();
        isSessionActive = true;
        startProcessing();
    }

    async function enterFullscreen() {
        if (document.documentElement.requestFullscreen) {
            await document.documentElement.requestFullscreen().catch((e) => console.warn("Fullscreen request failed", e));
        }
    }

    function resumeFullscreen() {
        document.documentElement.requestFullscreen().then(() => {
            document.getElementById('warningOverlay').classList.add('hidden');
        }).catch(e => {
            alert("Security Protocol: Please manually enable full screen (F11) to continue.");
        });
    }

    // --- AI Interaction Logic ---

    async function startProcessing() {
        try {
            const res = await fetch('certification_viva_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'generate_viva', portfolio_id: portfolioId })
            });
            const data = await res.json();

            if (data.success && data.job_id) {
                pollJobStatus(data.job_id, (questionsData) => {
                    const qList = Array.isArray(questionsData) ? questionsData : questionsData.questions;
                    questions = qList;
                    initializeViva();
                }, (err) => {
                    handleException("Failed to prepare verification questions: " + err);
                });
            } else {
                handleException(data.message || "Initialization failed.");
            }
        } catch (err) {
            handleException("Connection failed. Service unavailable.");
        }
    }

    function initializeViva() {
        document.getElementById('loadingOverlay').classList.add('hidden');
        document.getElementById('vivaView').style.display = 'block';
        renderStep();
    }

    function renderStep() {
        if (currentIdx >= questions.length) {
            finalizeAssessment();
            return;
        }

        const label = `VERIFICATION QUESTION ${currentIdx + 1}/${questions.length}`;
        document.getElementById('stepCounterLabel').innerText = label;
        document.getElementById('questionText').innerText = questions[currentIdx];
        document.getElementById('userAnswer').value = '';
        document.getElementById('userAnswer').focus();

        // Update Progress UI
        const dots = document.querySelectorAll('.step-dot');
        dots.forEach((dot, idx) => {
            if (idx === currentIdx) dot.className = 'step-dot active';
            else if (idx < currentIdx) dot.className = 'step-dot completed';
            else dot.className = 'step-dot';
        });

        if (currentIdx === questions.length - 1) {
            document.getElementById('btnSubmit').innerHTML = 'FINALIZE ASSESSMENT <i class="fas fa-check-double"></i>';
        }
    }

    function submitAnswer() {
        const answer = document.getElementById('userAnswer').value.trim();
        if (answer.length < 10) {
            alert("Academic Integrity Notice: Please provide a substantive technical response.");
            return;
        }

        answers.push({
            question: questions[currentIdx],
            answer: answer
        });

        currentIdx++;
        renderStep();
    }

    async function pollJobStatus(jobId, onSuccess, onError) {
        const check = async () => {
            try {
                const res = await fetch(`ai_job_status.php?job_id=${jobId}`);
                const data = await res.json();
                
                if (data.status === 'completed') {
                    onSuccess(data.result);
                } else if (data.status === 'failed') {
                    onError(data.error || "AI Generation Failed");
                } else {
                    setTimeout(check, 1500);
                }
            } catch (e) {
                onError("Signal interruption.");
            }
        };
        check();
    }

    async function finalizeAssessment() {
        isSessionActive = false; // Disable strict mode
        document.getElementById('vivaView').style.display = 'none';
        document.getElementById('loadingOverlay').classList.remove('hidden');
        document.getElementById('loadingText').innerText = 'ANALYZING TECHNICAL TRANSCRIPT...';

        try {
            const res = await fetch('certification_viva_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'submit_viva', 
                    portfolio_id: portfolioId, 
                    history: answers 
                })
            });
            const data = await res.json();

            if (data.success && data.job_id) {
                pollJobStatus(data.job_id, (evalData) => {
                    persistResult(evalData);
                }, (err) => {
                    handleException("Analysis failure: " + err);
                });
            } else {
                handleException("Analysis request failed.");
            }
        } catch (err) {
            handleException("Connection loss during evaluation.");
        }
    }

    async function persistResult(evalData) {
        // Show result on UI immediately
        document.getElementById('loadingOverlay').classList.add('hidden');
        document.getElementById('resultsView').style.display = 'block';
        
        const isVerified = (evalData.score >= 70);
        document.getElementById('finalScoreVal').innerText = evalData.score;
        document.getElementById('finalStatusText').innerText = isVerified ? 'VERIFIED' : 'NOT VERIFIED';
        document.getElementById('finalStatusText').style.color = isVerified ? '#10b981' : '#ef4444';
        document.getElementById('finalFeedback').innerText = evalData.feedback;
        
        if (!isVerified) {
            document.getElementById('successRing').style.color = '#ef4444';
            document.getElementById('successRing').innerHTML = '<i class="fas fa-exclamation-circle"></i>';
        }

        // Persist to Centralized Database
        try {
            await fetch('certification_viva_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_viva_result',
                    portfolio_id: portfolioId,
                    score: evalData.score,
                    feedback: evalData.feedback,
                    history: answers
                })
            });
        } catch (err) {
            console.error("Secondary Persistence Failure", err);
        }
    }

    function handleException(msg) {
        alert("CRITICAL ERROR: " + msg);
        window.location.href = 'dashboard';
    }
</script>

</body>
</html>

