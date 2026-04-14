<?php
/**
 * TCS NQT Test Engine
 * Specialized interface for NQT practice sessions
 */

require_once __DIR__ . '/../../config/bootstrap.php';

// Ensure user is logged in
requireLogin();

$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCS NQT Practice - Lakshya</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .intro-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
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
            max-width: 650px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            border-top: 8px solid var(--primary);
        }

        .intro-card h1 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
            letter-spacing: -1px;
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
            width: 100%;
        }

        .btn-start:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(128, 0, 0, 0.4);
            background: #a00000;
        }

        /* Interface */
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
            align-items: center;
            overflow-y: auto;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
        }

        .question-card {
            width: 100%;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
            transition: all 0.3s;
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

        .loader {
            width: 80px;
            height: 80px;
            border: 5px solid var(--glass);
            border-top-color: var(--secondary);
            border-radius: 50%;
            animation: spin 1s infinite linear;
            margin-bottom: 20px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Review Section */
        .review-section {
            width: 100%;
            max-width: 800px;
            margin-top: 20px;
            text-align: left;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .review-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .review-opt {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 5px 0;
            font-size: 0.95rem;
            background: rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
        }

        .review-opt.correct { background: rgba(39, 174, 96, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .review-opt.wrong { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
    </style>
</head>
<body>

    <!-- Intro Overlay -->
    <div class="intro-overlay" id="introOverlay">
        <div class="intro-card">
            <h1 style="color: var(--primary);"><i class="fas fa-graduation-cap"></i> TCS NQT PRACTICE</h1>
            <p style="font-weight: 600; color: #666; margin-bottom: 20px;">CHOOSE YOUR ASSESSMENT MODULE</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: left; margin-bottom: 30px;">
                <div class="module-choice" onclick="selectMode('foundation')" id="mode_foundation" style="border: 2px solid #ddd; padding: 20px; border-radius: 16px; cursor: pointer; transition: 0.3s; background: #fff;">
                    <h3 style="color: var(--primary); margin-bottom: 10px;">Foundation</h3>
                    <p style="font-size: 0.85rem; color: #666;">• 40 Minutes<br>• Numerical, Verbal, Reasoning<br>• Core Eligibility</p>
                </div>
                <div class="module-choice" onclick="selectMode('advanced')" id="mode_advanced" style="border: 2px solid #ddd; padding: 20px; border-radius: 16px; cursor: pointer; transition: 0.3s; background: #fff;">
                    <h3 style="color: var(--secondary); margin-bottom: 10px;">Advanced</h3>
                    <p style="font-size: 0.85rem; color: #666;">• 40 Minutes<br>• Advanced Quant & Reasoning<br>• Digital/Prime Prep</p>
                </div>
            </div>

            <button class="btn-start" onclick="startTest()" id="startBtn" disabled style="opacity: 0.5;">Select a Module to Start</button>
            <p style="font-size: 0.8rem; color: #999; margin-top: 15px;">Note: Coding practice is available in the Technical Round.</p>
        </div>
    </div>

    <!-- Main Test Interface -->
    <div class="test-container" id="testUI" style="display: none;">
        <div class="header">
            <div>
                <h2 style="color: var(--secondary);">TCS NQT | Aptitude</h2>
                <p style="font-size: 0.9rem; opacity: 0.8;"><?php echo htmlspecialchars($fullName); ?></p>
            </div>
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="timer" id="timerDisplay">30:00</div>
        </div>

        <div class="question-area" id="questionArea">
            <div class="loader"></div>
            <p>Initializing NQT Engine and generating cognitive challenges...</p>
        </div>

        <div class="footer">
            <button class="btn-nav" id="prevBtn" onclick="prevQuestion()" disabled>Previous</button>
            <div id="qCounter">Question 1 of 30</div>
            <button class="btn-nav" id="nextBtn" onclick="nextQuestion()">Next</button>
            <button class="btn-submit" id="submitBtn" onclick="submitTest()">Finalize & Submit</button>
        </div>
    </div>

    <!-- Results Area -->
    <div class="test-container" id="resultsUI" style="display: none; align-items: center; justify-content: center; text-align: center;">
        <div style="width: 200px; height: 200px; border-radius: 50%; border: 10px solid var(--secondary); display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 30px;">
            <div class="score-val" id="finalScore" style="font-size: 4rem; font-weight: 700; color: var(--secondary);">0%</div>
        </div>
        <h1>Assessment Completed</h1>
        <p id="resultMsg" style="margin-bottom: 30px; font-size: 1.2rem; color: #ccc;">Analyzing results...</p>
        
        <div id="resultDetails" class="review-section"></div>

        <button class="btn-start" onclick="window.location.href='dashboard'">Return to Dashboard</button>
    </div>

    <script>
        let questions = [];
        let userAnswers = {};
        let currentIdx = 0;
        let timeLeft = 0;
        let timerInterval;
        let selectedMode = '';

        function selectMode(mode) {
            selectedMode = mode;
            document.querySelectorAll('.module-choice').forEach(el => el.style.borderColor = '#ddd');
            document.getElementById('mode_' + mode).style.borderColor = (mode === 'foundation' ? 'var(--primary)' : 'var(--secondary)');
            
            const btn = document.getElementById('startBtn');
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerText = 'Launch ' + mode.charAt(0).toUpperCase() + mode.slice(1) + ' Session';
            
            timeLeft = 40 * 60;
            document.getElementById('timerDisplay').innerText = '40:00';
        }

        async function startTest() {
            try {
                if (document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                }
            } catch (e) {}

            document.getElementById('introOverlay').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('introOverlay').style.display = 'none';
                document.getElementById('testUI').style.display = 'flex';
                document.querySelector('.header h2').innerText = 'TCS NQT | ' + selectedMode.charAt(0).toUpperCase() + selectedMode.slice(1);
            }, 500);

            loadQuestions();
        }

        async function loadQuestions() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_questions');
                formData.append('mode', selectedMode);

                const response = await fetch('nqt_aptitude_handler', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    questions = data.questions;
                    renderQuestion();
                    startTimer();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (e) {
                alert('Connection error. Please try again.');
            }
        }

        function renderQuestion() {
            const q = questions[currentIdx];
            const area = document.getElementById('questionArea');
            
            let optionsHtml = '';
            q.options.forEach((opt, i) => {
                const isSelected = userAnswers[currentIdx] === i ? 'selected' : '';
                optionsHtml += `<button class="option-btn ${isSelected}" onclick="selectOption(${i})">${opt}</button>`;
            });

            area.innerHTML = `
                <div class="question-card">
                    <p style="color: var(--secondary); margin-bottom: 10px; letter-spacing: 2px;">${q.category.toUpperCase()}</p>
                    <h2 class="q-text">${q.question}</h2>
                    <div class="options-grid">${optionsHtml}</div>
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

        function nextQuestion() { if (currentIdx < questions.length - 1) { currentIdx++; renderQuestion(); } }
        function prevQuestion() { if (currentIdx > 0) { currentIdx--; renderQuestion(); } }

        function startTimer() {
            timerInterval = setInterval(() => {
                timeLeft--;
                const mins = Math.floor(timeLeft / 60);
                const secs = timeLeft % 60;
                document.getElementById('timerDisplay').innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                if (timeLeft <= 0) { clearInterval(timerInterval); submitTest(); }
            }, 1000);
        }

        async function submitTest() {
            clearInterval(timerInterval);
            document.getElementById('testUI').style.display = 'none';
            document.getElementById('resultsUI').style.display = 'flex';
            
            try {
                const formData = new FormData();
                formData.append('action', 'submit_test');
                formData.append('answers', JSON.stringify(userAnswers));
                formData.append('questions', JSON.stringify(questions));
                formData.append('mode', selectedMode);

                const response = await fetch('nqt_aptitude_handler', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    document.getElementById('finalScore').innerText = Math.round(data.score) + '%';
                    document.getElementById('resultMsg').innerText = `Success! You answered ${data.correct} out of ${data.total} correctly.`;

                    if (data.results && data.results.questions) {
                        let html = '';
                        data.results.questions.forEach((q, idx) => {
                            const userAns = data.results.user_answers[idx];
                            const correctAns = parseInt(q.answer);
                            html += `<div class="review-card"><div style="margin-bottom: 10px;">Q${idx+1}: ${q.question}</div>`;
                            q.options.forEach((opt, optIdx) => {
                                let cls = 'review-opt';
                                let icon = '';
                                if (optIdx === correctAns) { cls += ' correct'; icon = '✅'; }
                                else if (optIdx == userAns) { cls += ' wrong'; icon = '❌'; }
                                html += `<div class="${cls}"><span>${opt}</span><span>${icon}</span></div>`;
                            });
                            html += `</div>`;
                        });
                        document.getElementById('resultDetails').innerHTML = html;
                    }
                }
            } catch (e) { document.getElementById('resultMsg').innerText = 'Error saving results.'; }
        }
    </script>
</body>
</html>
