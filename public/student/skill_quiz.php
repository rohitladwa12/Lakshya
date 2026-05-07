 <?php
/**
 * AI Skill Verification Quiz
 */

require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireLogin();

$userId = getUserId();
$username = getUsername();

// Handle POST from Dashboard
if (isPost() && (isset($_POST['id']))) {
    SessionFilterHelper::setFilters('skill_quiz', [
        'id' => $_POST['id'] ?? 0
    ]);
    header("Location: skill_quiz.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('skill_quiz');
$portfolioId = $filters['id'] ?? 0;

// Fetch skill details
$stmt = getDB()->prepare("SELECT * FROM student_portfolio WHERE id = ? AND student_id = ?");
$stmt->execute([$portfolioId, $username]);
$skillItem = $stmt->fetch();

if (!$skillItem || $skillItem['category'] !== 'Skill') {
    header('Location: dashboard');
    exit;
}

$skillName = $skillItem['title'];
$skillLevel = $skillItem['sub_title'] ?: 'Intermediate';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Verification: <?php echo htmlspecialchars($skillName); ?> - <?php echo APP_NAME; ?></title>
    <!-- Resilience & Cache Busting -->
    <script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>
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
            padding: 2rem;
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .timer {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-maroon);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-bar {
            height: 6px;
            background: #eee;
            border-radius: 10px;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary-maroon);
            width: 0%;
            transition: width 0.4s ease;
        }

        .question-card {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        .question-card.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .question-text {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            line-height: 1.4;
        }

        .options-grid {
            display: grid;
            gap: 12px;
        }

        .option-btn {
            background: #fdfdfd;
            border: 2px solid #eee;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            text-align: left;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .option-btn:hover {
            border-color: var(--primary-maroon);
            background: #fffafa;
        }

        .option-btn.selected {
            background: var(--primary-maroon);
            color: white;
            border-color: var(--primary-maroon);
        }

        .btn-nav {
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-next { background: var(--primary-maroon); color: white; }
        .btn-next:disabled { opacity: 0.5; cursor: not-allowed; }

        .loading-overlay {
            position: absolute;
            top: 0; left:0; width: 100%; height: 100%;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .spinner {
            width: 50px; height: 50px;
            border: 5px solid #eee;
            border-top: 5px solid var(--primary-maroon);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 2rem;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .results-card {
            display: none;
            text-align: center;
        }

        .score-circle {
            width: 150px; height: 150px;
            border-radius: 50%;
            border: 10px solid #eee;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 2rem auto;
        }

        .explanation {
            background: #f0f7ff;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 10px;
            border-left: 4px solid #0052cc;
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
        <h1 style="color: var(--primary-maroon); margin-bottom: 20px;"><i class="fas fa-shield-alt"></i> Skill Verification</h1>
        <h2 style="margin-bottom: 15px;"><?php echo htmlspecialchars($skillName); ?></h2>
        <p style="color: #666; margin-bottom: 30px;">
            This is a proctored assessment. Exiting full screen or switching tabs will be flagged as a violation.<br><br>
            <strong>Skill:</strong> <?php echo htmlspecialchars($skillName); ?><br>
            <strong>Level:</strong> <?php echo htmlspecialchars($skillLevel); ?><br>
            <strong>Questions:</strong> 10 MCQs<br>
            <strong>Time Limit:</strong> 10 Minutes
        </p>
        <button onclick="beginAssessment()" class="btn btn-next" style="padding: 18px 50px; font-size: 1.2rem; width: 100%; justify-content: center;">START ASSESSMENT</button>
    </div>
</div>

<div class="navbar">
    <h1><i class="fas fa-shield-alt"></i> Skill Verification</h1>
    <a href="dashboard" style="color: white; text-decoration: none;"><i class="fas fa-times"></i> Exit</a>
</div>

<div class="container">
    <!-- Loading View -->
    <div id="loadingView" class="loading-overlay">
        <div class="spinner"></div>
        <h2 id="loadingText">AI is Generating Your Quiz...</h2>
        <p style="margin-top: 1rem; color: #666;">Analyzing <strong><?php echo htmlspecialchars($skillName); ?></strong> (<?php echo htmlspecialchars($skillLevel); ?> level)</p>
    </div>

    <!-- Quiz View -->
    <div id="quizView" style="display: none;">
        <div class="quiz-header">
            <div>
                <h2 style="font-size: 1.5rem;"><?php echo htmlspecialchars($skillName); ?> Verification</h2>
                <p id="questionInfo" style="color: #666; font-size: 0.9rem;">Question 1 of 10</p>
            </div>
            <div class="timer"><i class="fas fa-clock"></i> <span id="timeRemaining">10:00</span></div>
        </div>

        <div class="progress-bar"><div id="progressFill" class="progress-fill"></div></div>

        <div id="questionsContainer"></div>

        <div class="btn-nav">
            <button class="btn" style="background: #eee;" onclick="prevQuestion()" id="btnPrev">Previous</button>
            <button class="btn btn-next" onclick="nextQuestion()" id="btnNext">Next</button>
        </div>
    </div>

    <!-- Results View -->
    <div id="resultsView" class="results-card">
        <div id="congratsIcon" style="font-size: 4rem; color: #00875a; margin-bottom: 1rem;">
            <i class="fas fa-trophy"></i>
        </div>
        <h2 id="resultsTitle">Verification Complete!</h2>
        <div class="score-circle">
            <span id="resultsScore" style="font-size: 2.5rem; font-weight: 700;">85%</span>
            <span style="font-size: 0.8rem; color: #666; text-transform: uppercase;">Final Score</span>
        </div>
        <p id="resultsMessage" style="font-size: 1.1rem; margin-bottom: 2rem;"></p>
        
        <div style="display: flex; gap: 10px; justify-content: center;">
            <a href="dashboard" class="btn btn-next" style="text-decoration: none;">Return to Dashboard</a>
            <button class="btn" style="background: #eee;" onclick="toggleReview()">Review Answers</button>
        </div>

        <div id="reviewContainer" style="display: none; margin-top: 2rem; text-align: left; padding-top: 2rem; border-top: 1px solid #eee;">
            <h3>Review Answers</h3>
            <div id="reviewList"></div>
        </div>
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
        <button onclick="resumeFullscreen()" class="btn" style="width: 100%; justify-content: center; background: var(--primary-maroon); color: white;">RESUME ASSESSMENT</button>
    </div>
</div>

<script>
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

    let isSessionActive = true;

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
    let userAnswers = [];
    let currentIdx = 0;
    let timerInterval;
    let timeRemaining = 600; // 10 minutes

    async function beginAssessment() {
        document.getElementById('introOverlay').classList.add('hidden');
        await enterFullscreen();
        isSessionActive = true;
        startQuiz();
    }

    const portfolioId = <?php echo $portfolioId; ?>;

    async function startQuiz() {
        try {
            console.log('Starting quiz fetch...');
            const res = await fetch('skill_verification_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=generate_quiz&portfolio_id=${portfolioId}`
            });
            
            console.log('Fetch response status:', res.status);
            console.log('Fetch response ok:', res.ok);
            
            const responseText = await res.text();
            console.log('Raw response:', responseText);
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
                console.log('Parsed JSON data:', data);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response was not valid JSON:', responseText);
                alert('Server returned invalid response. Check console for details.');
                return;
            }

            if (data.success && data.job_id) {
                // Polling for Skill Quiz questions
                const pollInterval = setInterval(async () => {
                    try {
                        const statusRes = await fetch(`ai_job_status.php?job_id=${data.job_id}`).then(r => r.json());
                        if (statusRes.success && statusRes.status === 'completed') {
                            clearInterval(pollInterval);
                            const result = statusRes.result;
                            questions = result.questions;
                            userAnswers = new Array(questions.length).fill(null);
                            renderQuestions();
                            initQuiz();
                        } else if (statusRes.status === 'failed') {
                            clearInterval(pollInterval);
                            alert('AI generation failed: ' + statusRes.error);
                            window.location.href = 'dashboard';
                        }
                    } catch (e) {
                        console.error("Polling error:", e);
                    }
                }, 2000);
            } else if (data.success) {
                console.log('Quiz generated successfully, questions count:', data.questions?.length);
                questions = data.questions;
                userAnswers = new Array(questions.length).fill(null);
                renderQuestions();
                initQuiz();
            } else {
                console.log('Server returned error:', data.message);
                alert('Error: ' + data.message);
                window.location.href = 'dashboard';
            }
        } catch (err) {
            console.error('Fetch error:', err);
            console.error('Error details:', err.message);
            alert('Connection error. Failed to start quiz.');
            window.location.href = 'dashboard';
        }
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function renderQuestions() {
        const container = document.getElementById('questionsContainer');
        questions.forEach((q, qIdx) => {
            const div = document.createElement('div');
            div.className = `question-card ${qIdx === 0 ? 'active' : ''}`;
            div.id = `qCard_${qIdx}`;
            
            let optionsHTML = '';
            q.options.forEach((opt, oIdx) => {
                optionsHTML += `
                    <button class="option-btn" onclick="selectOption(${qIdx}, ${oIdx})" id="opt_${qIdx}_${oIdx}">
                        <div style="width: 30px; height: 30px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;">${String.fromCharCode(65 + oIdx)}</div>
                        <span>${escapeHtml(opt)}</span>
                    </button>
                `;
            });

            div.innerHTML = `
                <div class="question-text">${escapeHtml(q.question)}</div>
                <div class="options-grid">${optionsHTML}</div>
            `;
            container.appendChild(div);
        });
    }

    // ... (rest of functions) ...

    function selectOption(qIdx, oIdx) {
        userAnswers[qIdx] = oIdx;
        
        // UI update
        const options = document.querySelectorAll(`#qCard_${qIdx} .option-btn`);
        options.forEach((btn, idx) => {
            if (idx === oIdx) btn.classList.add('selected');
            else btn.classList.remove('selected');
        });

        // Automatically go to next after 500ms if not last question
        if (qIdx < questions.length - 1) {
            setTimeout(() => nextQuestion(), 500);
        }
    }

    function updateNav() {
        document.getElementById('questionInfo').innerText = `Question ${currentIdx + 1} of ${questions.length}`;
        const progress = ((currentIdx + 1) / questions.length) * 100;
        document.getElementById('progressFill').style.width = `${progress}%`;

        document.getElementById('btnPrev').style.visibility = currentIdx === 0 ? 'hidden' : 'visible';
        document.getElementById('btnNext').innerText = currentIdx === questions.length - 1 ? 'Finish Quiz' : 'Next';
    }

    function nextQuestion() {
        if (currentIdx < questions.length - 1) {
            document.getElementById(`qCard_${currentIdx}`).classList.remove('active');
            currentIdx++;
            document.getElementById(`qCard_${currentIdx}`).classList.add('active');
            updateNav();
        } else {
            submitQuiz();
        }
    }

    function prevQuestion() {
        if (currentIdx > 0) {
            document.getElementById(`qCard_${currentIdx}`).classList.remove('active');
            currentIdx--;
            document.getElementById(`qCard_${currentIdx}`).classList.add('active');
            updateNav();
        }
    }

    async function submitQuiz() {
        if (!confirm('Submit your answers for verification?')) return;

        isSessionActive = false; // Disable security enforcement
        clearInterval(timerInterval);
        document.getElementById('quizView').style.display = 'none';
        document.getElementById('loadingView').style.display = 'flex';
        document.getElementById('loadingText').innerText = 'Verifying Your Answers...';

        try {
            const formData = new URLSearchParams();
            formData.append('action', 'submit_quiz');
            userAnswers.forEach((ans, i) => formData.append(`answers[${i}]`, ans !== null ? ans : -1));

            const res = await fetch('skill_verification_handler', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                showResults(data);
            } else {
                alert('Submission failed: ' + data.message);
                window.location.href = 'dashboard';
            }
        } catch (err) {
            console.error(err);
            alert('Connection error during submission.');
            window.location.href = 'dashboard';
        }
    }

    function showResults(data) {
        document.getElementById('loadingView').style.display = 'none';
        document.getElementById('resultsView').style.display = 'block';

        const score = Math.round(data.score);
        document.getElementById('resultsScore').innerText = score + '%';
        
        const passed = data.passed;
        const icon = document.getElementById('congratsIcon');
        const title = document.getElementById('resultsTitle');
        const msg = document.getElementById('resultsMessage');

        if (passed) {
            icon.style.color = '#00875a';
            icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            title.innerText = 'Skill Verified! ✅';
            msg.innerHTML = `Congratulations! Your proficiency in <strong>${questions[0]?.skill || 'this skill'}</strong> has been officially verified and added to your profile.`;
        } else {
            icon.style.color = '#e74c3c';
            icon.innerHTML = '<i class="fas fa-times-circle"></i>';
            title.innerText = 'Verification Failed';
            msg.innerHTML = `You scored ${score}%. You need at least 70% to verify this skill. Don't worry, you can study and try again later!`;
        }

        // Render Review
        const reviewList = document.getElementById('reviewList');
        data.results.forEach((r, idx) => {
            const div = document.createElement('div');
            div.style.marginBottom = '20px';
            div.style.padding = '15px';
            div.style.borderRadius = '12px';
            div.style.background = r.is_correct ? '#f6ffed' : '#fff1f0';
            div.style.border = `1px solid ${r.is_correct ? '#b7eb8f' : '#ffa39e'}`;
            
            div.innerHTML = `
                <div style="font-weight:700; margin-bottom: 5px;">Question ${idx+1}: ${escapeHtml(r.question)}</div>
                <div style="font-size:0.9rem;">
                    Your Answer: <span style="font-weight:600;">${r.user_answer !== -1 ? 'Option ' + String.fromCharCode(65 + r.user_answer) : 'Unanswered'}</span> ${r.is_correct ? '✅' : '❌'}
                </div>
                ${!r.is_correct ? `<div style="font-size:0.9rem;">Correct Answer: <span style="font-weight:600;">Option ${String.fromCharCode(65 + r.correct_answer)}</span></div>` : ''}
                <div class="explanation"><strong>Explanation:</strong> ${escapeHtml(r.explanation)}</div>
            `;
            reviewList.appendChild(div);
        });
    }

    function toggleReview() {
        const container = document.getElementById('reviewContainer');
        container.style.display = container.style.display === 'none' ? 'block' : 'none';
        if (container.style.display === 'block') {
            container.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function initQuiz() {
        document.getElementById('loadingView').style.display = 'none';
        document.getElementById('quizView').style.display = 'block';
        updateNav();

        timerInterval = setInterval(() => {
            timeRemaining--;
            const mins = Math.floor(timeRemaining / 60);
            const secs = timeRemaining % 60;
            document.getElementById('timeRemaining').innerText = `${mins}:${secs.toString().padStart(2, '0')}`;
            
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                submitQuiz();
            }
        }, 1000);
    }

    window.onload = async () => {
        // Check if there's an existing session via a lightweight check
        try {
            const res = await fetch('skill_verification_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=check_session&portfolio_id=${portfolioId}`
            });
            const data = await res.json();
            if (data.success && data.has_active) {
                if (confirm("You have an active session for this quiz. Would you like to resume?")) {
                    document.getElementById('introOverlay').classList.add('hidden');
                    questions = data.questions;
                    userAnswers = new Array(questions.length).fill(null);
                    // Fill previously answered questions if any (optional enhancement)
                    renderQuestions();
                    initQuiz();
                    isSessionActive = true;
                    enterFullscreen();
                }
            }
        } catch (e) {
            console.warn("Session check failed", e);
        }
    };
</script>

</body>
</html>

