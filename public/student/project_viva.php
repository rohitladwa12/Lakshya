<?php
/**
 * AI Project Viva (Defense)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireLogin();

$userId = getUserId();
$username = getUsername();

// Handle POST from Dashboard
if (isPost() && (isset($_POST['id']))) {
    SessionFilterHelper::setFilters('project_viva', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: project_viva.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('project_viva');
$portfolioId = $filters['id'] ?? 0;

// Fetch project details
$stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
$stmt->execute([$portfolioId, $username]);
$project = $stmt->fetch();

if (!$project || $project['category'] !== 'Project') {
    header('Location: dashboard');
    exit;
}

$projectTitle = $project['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Defense: <?php echo htmlspecialchars($projectTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #4a0000;
            --accent-gold: #D4AF37;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        * { margin:0; padding:0; box-sizing:border-box; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-light);
            color: #2d3436;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        input, textarea { -webkit-user-select: text; -moz-user-select: text; -ms-user-select: text; user-select: text; }

        .navbar {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            flex: 1;
            max-width: 800px;
            margin: 2rem auto;
            width: 90%;
            padding: 2.5rem;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow);
            position: relative;
        }

        .viva-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .step-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: #eee;
            transition: 0.3s;
        }

        .step-dot.active { background: var(--primary-maroon); transform: scale(1.2); }
        .step-dot.completed { background: #00875a; }

        .question-box {
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff9f9;
            border-left: 5px solid var(--primary-maroon);
            border-radius: 12px;
        }

        .answer-area {
            width: 100%;
            padding: 1.5rem;
            border: 2px solid #eee;
            border-radius: 16px;
            font-family: inherit;
            font-size: 1.05rem;
            resize: vertical;
            min-height: 150px;
            transition: 0.3s;
            margin-bottom: 1.5rem;
        }

        .answer-area:focus {
            outline: none;
            border-color: var(--primary-maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.05);
        }

        .btn-submit {
            background: var(--primary-maroon);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(128,0,0,0.2); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .loading-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 24px;
            z-index: 10;
        }

        .spinner {
            width: 50px; height: 50px;
            border: 5px solid #eee;
            border-top: 5px solid var(--primary-maroon);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .results-view {
            text-align: center;
        }

        .feedback-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 16px;
            text-align: left;
            margin-top: 1.5rem;
            border-left: 5px solid #0052cc;
        }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column;
        }
        .hidden { display: none !important; }
    </style>
</head>
<body>

<!-- Intro Overlay -->
<div id="introOverlay" class="overlay">
    <div style="text-align: center; max-width: 600px; padding: 40px; background: white; border-radius: 24px; box-shadow: var(--shadow); border: 2px solid var(--primary-maroon);">
        <h1 style="color: var(--primary-maroon); margin-bottom: 20px;"><i class="fas fa-microphone"></i> Project Defense</h1>
        <h2 style="margin-bottom: 15px;"><?php echo htmlspecialchars($projectTitle); ?></h2>
        <p style="color: #666; margin-bottom: 30px;">
            This is a proctored AI Viva session. Exiting full screen or switching tabs will be flagged.<br><br>
            <strong>Project:</strong> <?php echo htmlspecialchars($projectTitle); ?><br>
            <strong>Format:</strong> 5 Analytical Questions<br>
            <strong>Evaluator:</strong> Senior AI Technical Lead
        </p>
        <button onclick="beginAssessment()" class="btn-submit" style="padding: 18px 50px; font-size: 1.2rem; width: 100%; justify-content: center;">START DEFENSE</button>
    </div>
</div>

<div class="navbar">
    <h1><i class="fas fa-microphone"></i> Project Defense (AI Viva)</h1>
    <a href="dashboard" style="color: white; text-decoration: none;"><i class="fas fa-times"></i> Exit</a>
</div>

<div class="container" id="vivaContainer">
    <!-- Initial Loading -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner" style="margin-bottom: 20px;"></div>
        <h2 id="loadingText">Preparing Defense Questions...</h2>
    </div>

    <!-- Viva Interaction -->
    <div id="vivaView" style="display: none;">
        <div class="viva-header">
            <h3>Defending: <?php echo htmlspecialchars($projectTitle); ?></h3>
            <p style="color: #666; margin-top: 5px;">Question <span id="currentStepNum">1</span> of 5</p>
        </div>

        <div class="progress-steps" id="progressSteps">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <div id="questionText" class="question-box">Loading question...</div>

        <textarea id="userAnswer" class="answer-area" placeholder="Provide a detailed technical explanation..."></textarea>

        <button id="btnSubmit" class="btn-submit" onclick="submitAnswer()">
            Submit Answer <i class="fas fa-arrow-right"></i>
        </button>
    </div>

    <!-- Results -->
    <div id="resultsView" class="results-view" style="display: none;">
        <div style="font-size: 4rem; color: #00875a; margin-bottom: 1rem;">
            <i class="fas fa-award"></i>
        </div>
        <h2 id="resultsTitle">Defense Evaluation Complete</h2>
        <div style="margin: 2rem 0;">
            <div style="font-size: 1.2rem; font-weight: 600;">Status: <span id="finalStatus" style="color: #00875a;">VERIFIED</span></div>
            <p id="finalFeedback" style="color: #666; margin-top: 10px;"></p>
        </div>
        <a href="dashboard" class="btn-submit" style="text-decoration: none; margin: 0 auto;">Return to Dashboard</a>
    </div>
</div>

<!-- Warning Overlay -->
<div id="warningOverlay" class="overlay hidden" style="background: rgba(0,0,0,0.95); z-index: 2000;">
    <div style="text-align: center; max-width: 500px; padding: 30px; border: 2px solid var(--primary-maroon); background: #000; border-radius: 12px; color: white;">
        <i class="fas fa-exclamation-triangle" style="color: var(--primary-maroon); font-size: 3rem; margin-bottom: 20px;"></i>
        <h2 style="margin-bottom: 10px;">Security Violation</h2>
        <p style="color: #ccc; margin-bottom: 25px;">
            You have exited Full Screen mode. This is a violation of the assessment protocols.<br>
            Please return to full screen immediately to continue.
        </p>
        <button onclick="resumeFullscreen()" class="btn-submit" style="width: 100%; justify-content: center;">RESUME ASSESSMENT</button>
    </div>
</div>

<script>
    // Ensure DOM is loaded before attaching event listeners
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, applying security measures...');
        
        // --- Security Measures: Disable Copy/Paste/Right-Click ---
        document.addEventListener('contextmenu', e => {
            e.preventDefault();
            console.log('Right-click blocked');
        });
        document.addEventListener('copy', e => {
            e.preventDefault();
            console.log('Copy blocked');
        });
        document.addEventListener('cut', e => {
            e.preventDefault();
            console.log('Cut blocked');
        });
        document.addEventListener('paste', e => {
            e.preventDefault();
            console.log('Paste blocked');
        });
        
        document.addEventListener('keydown', e => {
            // Disable Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+U, Ctrl+Shift+I (Inspect)
            if (e.ctrlKey && ['c', 'v', 'x', 'u'].includes(e.key.toLowerCase())) {
                e.preventDefault();
                console.log('Keyboard shortcut blocked:', e.key);
            }
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                console.log('Ctrl+Shift+I blocked');
            }
            // Disable F12
            if (e.key === 'F12') {
                e.preventDefault();
                console.log('F12 blocked');
            }
        });
        
        console.log('Security measures applied successfully');
    });

    let isSessionActive = false;

    async function beginAssessment() {
        document.getElementById('introOverlay').classList.add('hidden');
        await enterFullscreen();
        isSessionActive = true;
        startViva();
    }

    function resumeFullscreen() {
        document.documentElement.requestFullscreen().then(() => {
            document.getElementById('warningOverlay').classList.add('hidden');
        }).catch(e => {
            alert("Please manually enable full screen (F11)");
        });
    }

    // Monitor fullscreen changes
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && isSessionActive) {
            document.getElementById('warningOverlay').classList.remove('hidden');
        }
    });

    // Enter fullscreen on start
    async function enterFullscreen() {
        if (document.documentElement.requestFullscreen) {
            await document.documentElement.requestFullscreen().catch((e) => console.log(e));
        }
    }

    let questions = [];
    let answers = [];
    let currentIdx = 0;
    const portfolioId = <?php echo $portfolioId; ?>;

    async function startViva() {
        try {
            const res = await fetch('project_viva_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=generate_viva&portfolio_id=${portfolioId}`
            });
            const data = await res.json();

            if (data.success && data.job_id) {
                pollJobStatus(data.job_id, (aiData) => {
                    questions = aiData; // This is the questions array
                    showQuestion();
                    document.getElementById('loadingOverlay').style.display = 'none';
                    document.getElementById('vivaView').style.display = 'block';
                }, (err) => {
                    alert("AI Error: " + err);
                    window.location.href = 'dashboard';
                });
            } else {
                alert('Error: ' + data.message);
                window.location.href = 'dashboard';
            }
        } catch (err) {
            console.error(err);
            alert('Connection error.');
            window.location.href = 'dashboard';
        }
    }

    function showQuestion() {
        if (currentIdx >= questions.questions.length) {
            finishViva();
            return;
        }
        document.getElementById('currentStepNum').innerText = currentIdx + 1;
        document.getElementById('questionText').innerText = questions.questions[currentIdx];
        document.getElementById('userAnswer').value = '';
        
        // Update dots
        const dots = document.querySelectorAll('.step-dot');
        dots.forEach((dot, idx) => {
            if (idx === currentIdx) dot.className = 'step-dot active';
            else if (idx < currentIdx) dot.className = 'step-dot completed';
            else dot.className = 'step-dot';
        });
    }

    function submitAnswer() {
        const answer = document.getElementById('userAnswer').value.trim();
        if (!answer) {
            alert("Please provide an answer before continuing.");
            return;
        }
        
        answers.push({
            question: questions.questions[currentIdx],
            answer: answer
        });
        
        currentIdx++;
        showQuestion();
    }

    async function pollJobStatus(jobId, onSuccess, onError) {
        const poll = async () => {
            try {
                const res = await fetch(`ai_job_status.php?job_id=${jobId}`);
                if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);
                const data = await res.json();
                if (data.status === 'completed') onSuccess(data.result);
                else if (data.status === 'failed') onError(data.error);
                else setTimeout(poll, 1500);
            } catch (e) { 
                console.error("Polling failure", e);
                onError("Polling problem: " + e.message); 
            }
        };
        poll();
    }

    async function finishViva() {
        isSessionActive = false; // Disable security enforcement
        document.getElementById('vivaView').style.display = 'none';
        document.getElementById('loadingOverlay').style.display = 'flex';
        document.getElementById('loadingText').innerText = 'AI is Evaluating Your Defense...';

        try {
            const res = await fetch('project_viva_handler', {
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
                    // Final Save after evaluation
                    saveFinalVivaResult(evalData);
                }, (err) => {
                    alert("Evaluation failed: " + err);
                    window.location.href = 'dashboard';
                });
            } else {
                alert('Evaluation error.');
                window.location.href = 'dashboard';
            }
        } catch (err) {
            console.error(err);
            alert('Connection error during evaluation.');
            window.location.href = 'dashboard';
        }
    }

    async function saveFinalVivaResult(evalData) {
        // Show local results first
        document.getElementById('loadingOverlay').style.display = 'none';
        document.getElementById('resultsView').style.display = 'block';
        document.getElementById('finalStatus').innerText = evalData.score >= 70 ? 'VERIFIED ✅' : 'NOT VERIFIED';
        document.getElementById('finalStatus').style.color = evalData.score >= 70 ? '#00875a' : '#e74c3c';
        document.getElementById('finalFeedback').innerText = evalData.feedback;

        // Persist to database
        try {
            const res = await fetch('project_viva_handler', {
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
            const data = await res.json();
            if (!data.success) {
                console.error("Save failed:", data.message);
            } else {
                console.log("Persistence successful");
                // Optional: Update UI to show "Saved"
                const savedBadge = document.createElement('div');
                savedBadge.innerHTML = '<i class="fas fa-check-circle"></i> Result saved to profile';
                savedBadge.style.color = '#00875a';
                savedBadge.style.fontSize = '0.9rem';
                savedBadge.style.marginTop = '10px';
                document.getElementById('resultsTitle').after(savedBadge);
            }
        } catch (err) {
            console.error("Persistence connection error:", err);
        }
    }

    window.onload = () => {
        // Just show intro
    };
</script>

</body>
</html>
