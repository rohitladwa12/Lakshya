<?php
/**
 * AI Aptitude Test Page
 * High-end fullscreen MCQ interface
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\SessionFilterHelper;

// Ensure user is logged in
requireLogin();

// Handle POST from Assigned Task
if (isPost() && (isset($_POST['company']) || isset($_POST['task_id']))) {
    SessionFilterHelper::setFilters('ai_aptitude_test', [
        'company' => $_POST['company'] ?? 'General',
        'task_id' => $_POST['task_id'] ?? 0
    ]);
    header("Location: ai_aptitude_test.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('ai_aptitude_test');
$companyName = $filters['company'] ?? 'General';
$taskId = $filters['task_id'] ?? 0;
$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Aptitude Test - <?php echo htmlspecialchars($companyName); ?></title>
    <!-- Resilience & Cache Busting -->
    <script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800000;
            --secondary: #e9c66f;
            --dark: #1a1a1a;
            --light: #f4f4f4;
            --white: #ffffff;
            --success: #27ae60;
            --error: #e74c3c;
            --glass: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: radial-gradient(circle at center, #2d0000 0%, #1a1a1a 100%);
            color: var(--white);
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .test-container {
            width: 100%;
            height: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            padding: 40px;
            position: relative;
        }

        /* Fullscreen Overlay Style */
        .intro-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            transition: opacity 0.5s ease;
        }

        .intro-card {
            background: var(--white);
            color: var(--dark);
            padding: 50px;
            border-radius: 24px;
            max-width: 600px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .intro-card h1 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .btn-start {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 30px;
            box-shadow: 0 10px 20px rgba(128, 0, 0, 0.3);
        }

        .btn-start:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(128, 0, 0, 0.4);
            background: #a00000;
        }

        /* Test Interface */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .company-info h2 {
            font-size: 1.5rem;
            color: var(--secondary);
        }

        .timer {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary);
            font-variant-numeric: tabular-nums;
        }

        .progress-container {
            flex: 1;
            margin: 0 40px;
            height: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary), #fff);
            width: 0%;
            transition: width 0.3s ease;
        }

        .question-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center; /* Keeps everything centered horizontally */
            overflow-y: auto;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
            scrollbar-width: thin;
            scrollbar-color: var(--glass) transparent;
        }

        .question-area::-webkit-scrollbar {
            width: 6px;
        }
        .question-area::-webkit-scrollbar-thumb {
            background: var(--glass);
            border-radius: 10px;
        }

        .question-card {
            width: 100%;
            animation: fadeIn 0.5s ease;
            margin: auto 0; /* Vertically centers content if short, allows top-alignment and scrolling if long */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .q-number {
            font-size: 1.1rem;
            color: var(--secondary);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .q-text {
            font-size: 1.6rem;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 30px;
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            width: 100%;
            margin-bottom: 30px;
        }

        .option-btn {
            background: var(--glass);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 25px;
            border-radius: 16px;
            color: var(--white);
            font-size: 1.2rem;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .option-btn:hover {
            background: rgba(233, 198, 111, 0.1);
            border-color: var(--secondary);
        }

        .option-btn.selected {
            background: var(--secondary);
            color: var(--dark);
            border-color: var(--secondary);
            font-weight: 600;
        }

        .footer {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .btn-nav {
            background: transparent;
            color: var(--white);
            border: 1px solid var(--white);
            padding: 12px 30px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .btn-nav:hover {
            background: var(--white);
            color: var(--dark);
        }

        .btn-submit {
            background: var(--success);
            color: var(--white);
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            display: none;
        }

        /* Results Screen */
        .results-overlay {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .score-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 10px solid var(--secondary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }

        .score-val {
            font-size: 4rem;
            font-weight: 700;
            color: var(--secondary);
        }

        .loader {
            width: 80px;
            height: 80px;
            border: 5px solid var(--glass);
            border-top-color: var(--secondary);
            border-radius: 50%;
            animation: spin 1s infinite linear;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .options-grid { grid-template-columns: 1fr; }
            .test-container { padding: 20px; }
            .q-text { font-size: 1.5rem; }
        }

        /* Review Section */
        .review-section {
            width: 100%;
            max-width: 800px;
            margin-top: 20px;
            text-align: left;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
            margin-bottom: 20px;
        }
        
        .review-section::-webkit-scrollbar {
            width: 8px;
        }
        .review-section::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
        }

        .review-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .review-q {
            font-size: 1.1rem;
            margin-bottom: 10px;
            font-weight: 600;
            color: #eee;
        }

        .review-opt {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 5px 0;
            font-size: 0.95rem;
            background: rgba(0,0,0,0.2);
            color: #aaa;
            display: flex;
            justify-content: space-between;
        }

        .review-opt.correct {
            background: rgba(39, 174, 96, 0.2);
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }

        .review-opt.wrong {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }

        .review-explanation {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #ccc;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            border-left: 3px solid var(--secondary);
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>

    <!-- Intro Overlay -->
    <div class="intro-overlay" id="introOverlay">
        <div class="intro-card">
            <p style="color: #666; font-weight: 600;">TRANSITIONING TO EXAM MODE</p>
            <h1>AI Aptitude Assessment</h1>
            <p>Company: <strong><?php echo htmlspecialchars($companyName); ?></strong></p>
            <div style="text-align: left; margin: 30px 0; background: #f8f9fa; padding: 20px; border-radius: 12px;">
                <p>• 40 Questions (36 DB + 4 AI)</p>
                <p>• 40 Minutes Total Time</p>
                <p>• Full-screen experience recommended</p>
                <p>• Questions focus on company awareness & technical depth</p>
            </div>
            <button class="btn-start" onclick="startTest()">Initialize Test Environment</button>
            <p style="margin-top: 20px; font-size: 0.9rem; color: #888;">By clicking start, you agree to follow the assessment protocols.</p>
        </div>
    </div>

    <!-- Main Test Interface -->
    <div class="test-container" id="testUI" style="display: none;">
        <div class="header">
            <div class="company-info">
                <h2><?php echo htmlspecialchars($companyName); ?> | Aptitude</h2>
                <p>Candidate: <?php echo htmlspecialchars($fullName); ?></p>
            </div>
            
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>

            <div class="timer" id="timerDisplay">40:00</div>
        </div>

        <div class="question-area" id="questionArea">
            <div class="loader" id="mainLoader"></div>
            <p id="loaderText" style="text-align: center;">Reseaching company sector and generating custom domain assessment...</p>
        </div>

        <div class="footer">
            <button class="btn-nav" id="prevBtn" onclick="prevQuestion()" disabled>Previous</button>
            <div id="qCounter">Question 1 of 40</div>
            <button class="btn-nav" id="nextBtn" onclick="nextQuestion()">Next</button>
            <button class="btn-submit" id="submitBtn" onclick="submitTest()">Finalize & Submit</button>
        </div>
    </div>

    <!-- Results Area -->
    <div class="test-container results-overlay" id="resultsUI" style="display: none;">
        <div class="score-circle">
            <div class="score-val" id="finalScore">0%</div>
            <div style="font-weight: 600;">OVERALL</div>
        </div>
        <h1>Assessment Completed</h1>
        <p id="resultMsg" style="margin-bottom: 30px; font-size: 1.2rem; color: #ccc;">Checking your performance...</p>
        
        <div id="resultDetails"></div>

        <button class="btn-start" onclick="window.location.href='dashboard'">Return to Dashboard</button>
    </div>

    <script>
        let questions = [];
        let userAnswers = {};
        let currentIdx = 0;
        let timeLeft = 40 * 60; // 40 minutes for 40 questions
        let timerInterval;
        const companyName = "<?php echo addslashes($companyName); ?>";

        function shuffleArray(arr) {
            for (let i = arr.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [arr[i], arr[j]] = [arr[j], arr[i]];
            }
        }

        async function startTest() {
            // Fullscreen
            try {
                if (document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                }
            } catch (e) { console.log('Fullscreen failed'); }

            document.getElementById('introOverlay').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('introOverlay').style.display = 'none';
                document.getElementById('testUI').style.display = 'flex';
            }, 500);

            loadQuestions();
        }

        async function loadQuestions() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_questions');
                formData.append('company_name', companyName);
                formData.append('task_id', "<?php echo $taskId; ?>"); // Fix: coordinator task_id must be sent so manual questions are fetched

                const response = await fetch('ai_aptitude_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success && data.job_id) {
                    // Poll for AI questions while we have DB questions ready
                    const dbQuestions = data.db_questions || [];
                    const pollInterval = setInterval(async () => {
                        try {
                            const statusRes = await fetch(`ai_job_status.php?job_id=${data.job_id}`).then(r => r.json());
                            if (statusRes.status === 'completed') {
                                clearInterval(pollInterval);
                                // Extract payload robustly
                                let resultPayload = statusRes.result;
                                if (resultPayload && resultPayload.result && typeof resultPayload.result === 'object') {
                                    resultPayload = resultPayload.result;
                                } else if (resultPayload && resultPayload.content && typeof resultPayload.content === 'string') {
                                    try { resultPayload = JSON.parse(resultPayload.content); } catch(e) {}
                                }
                                
                                const aiQuestions = Array.isArray(resultPayload) ? resultPayload : (resultPayload?.questions || []);
                                questions = [...dbQuestions, ...aiQuestions];
                                // Shuffle final set
                                shuffleArray(questions);
                                renderQuestion();
                                startTimer();
                            } else if (statusRes.status === 'failed') {
                                clearInterval(pollInterval);
                                // AI generation failed — fall back to DB questions only
                                questions = dbQuestions;
                                shuffleArray(questions);
                                renderQuestion();
                                startTimer();
                            }
                        } catch (e) { console.error('Polling error:', e); }
                    }, 2000);
                } else if (data.success) {
                    questions = data.questions;
                    renderQuestion();
                    startTimer();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (e) {
                alert('Connection error. Please check your internet.');
            }
        }

        function renderQuestion() {
            const q = questions[currentIdx];
            const area = document.getElementById('questionArea');
            
            let optionsHtml = '';
            q.options.forEach((opt, i) => {
                const isSelected = userAnswers[currentIdx] === i ? 'selected' : '';
                optionsHtml += `
                    <button class="option-btn ${isSelected}" onclick="selectOption(${i})">
                        ${opt}
                    </button>
                `;
            });

            area.innerHTML = `
                <div class="question-card">
                    <p class="q-number">SECTION: ${q.category || 'General Aptitude'}</p>
                    <h2 class="q-text">${q.question}</h2>
                    <div class="options-grid">
                        ${optionsHtml}
                    </div>
                </div>
            `;

            updateUI();
        }

        function selectOption(idx) {
            userAnswers[currentIdx] = idx;
            renderQuestion();
        }

        function updateUI() {
            const progress = ((currentIdx + 1) / questions.length) * 100;
            document.getElementById('progressBar').style.width = `${progress}%`;
            document.getElementById('qCounter').innerText = `Question ${currentIdx + 1} of ${questions.length}`;
            
            document.getElementById('prevBtn').disabled = currentIdx === 0;
            
            if (currentIdx === questions.length - 1) {
                document.getElementById('nextBtn').style.display = 'none';
                document.getElementById('submitBtn').style.display = 'block';
            } else {
                document.getElementById('nextBtn').style.display = 'block';
                document.getElementById('submitBtn').style.display = 'none';
            }
        }

        function nextQuestion() {
            if (currentIdx < questions.length - 1) {
                currentIdx++;
                renderQuestion();
            }
        }

        function prevQuestion() {
            if (currentIdx > 0) {
                currentIdx--;
                renderQuestion();
            }
        }

        function startTimer() {
            timerInterval = setInterval(() => {
                timeLeft--;
                const mins = Math.floor(timeLeft / 60);
                const secs = timeLeft % 60;
                document.getElementById('timerDisplay').innerText = 
                    `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    submitTest();
                }
            }, 1000);
        }

        async function submitTest() {
            clearInterval(timerInterval);
            
            document.getElementById('testUI').style.display = 'none';
            document.getElementById('resultsUI').style.display = 'flex';
            
            try {
                const formData = new FormData();
                formData.append('action', 'submit_test');
                formData.append('company_name', companyName);
                formData.append('answers', JSON.stringify(userAnswers));
                formData.append('questions', JSON.stringify(questions));
                formData.append('task_id', "<?php echo $taskId; ?>");
                formData.append('time_taken', 40 * 60 - timeLeft); // Fix: Send actual time taken to coordinator dashboard

                const response = await fetch('ai_aptitude_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    document.getElementById('finalScore').innerText = Math.round(data.score) + '%';
                    document.getElementById('resultMsg').innerText = 
                        `Success! You answered ${data.correct} out of ${data.total} questions correctly.`;

                    // Render Review Section
                    if (data.results && data.results.questions) {
                        let html = '<div class="review-section">';
                        data.results.questions.forEach((q, idx) => {
                            const userAns = data.results.user_answers[idx];
                            const correctAns = parseInt(q.answer);
                            
                            html += `<div class="review-card">
                                <div class="review-q">Q${idx+1}: ${q.question}</div>`;
                                
                            q.options.forEach((opt, optIdx) => {
                                let cls = 'review-opt';
                                let icon = '';
                                
                                if (optIdx === correctAns) {
                                    cls += ' correct';
                                    icon = '✅';
                                } else if (optIdx == userAns) {
                                    cls += ' wrong';
                                    icon = '❌';
                                }
                                
                                html += `<div class="${cls}"><span>${opt}</span> <span>${icon}</span></div>`;
                            });
                            
                            // Add Explanation
                            if (q.explanation) {
                                html += `<div class="review-explanation"><strong>💡 Reason:</strong> ${q.explanation}</div>`;
                            }
                            
                            html += `</div>`;
                        });
                        html += '</div>';
                        document.getElementById('resultDetails').innerHTML = html;
                    }
                } else {
                    document.getElementById('resultMsg').innerText = 'Submission failed but your progress was saved.';
                }
            } catch (e) {
                document.getElementById('resultMsg').innerText = 'Connection error on submission.';
            }
        }
    </script>
</body>
</html>
