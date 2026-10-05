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
        'concept' => $_POST['concept'] ?? '',
        'task_id' => $_POST['task_id'] ?? 0
    ]);
    header("Location: ai_aptitude_test.php");
    exit;
}

$filters = SessionFilterHelper::getFilters('ai_aptitude_test');
$companyName = !empty($_GET['company']) ? clean($_GET['company']) : ($filters['company'] ?? 'General');
$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : ($filters['task_id'] ?? 0);
$concept = !empty($_GET['concept']) ? clean($_GET['concept']) : ($filters['concept'] ?? '');
if (empty($concept) && $taskId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT concept FROM coordinator_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $concept = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {}
}
$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Aptitude Test - <?php echo htmlspecialchars($companyName); ?></title>
    <!-- Resilience & Cache Busting -->
    <script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- In-page dialogs (replaces native alert/confirm popups) -->
    <script src="../js/lakshya_dialogs.js?v=<?php echo APP_VERSION; ?>"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
    <script src="report_question.js?v=<?php echo APP_VERSION; ?>"></script>
    <script src="../js/proctor.js?v=<?php echo APP_VERSION; ?>"></script>
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
            background: rgba(0, 0, 0, 0.9);
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
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
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
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            background: rgba(255, 255, 255, 0.1);
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
            align-items: center;
            /* Keeps everything centered horizontally */
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
            margin: auto 0;
            /* Vertically centers content if short, allows top-alignment and scrolling if long */
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            padding-top: 15px;
            padding-right: 175px; /* Safe space to prevent overlap with floating proctor webcam */
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 10;
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
            padding: 14px 36px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1.05rem;
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
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 1024px), (max-height: 850px) {
            .test-container {
                padding: 20px 30px;
            }

            .q-text {
                font-size: 1.35rem;
                margin-bottom: 20px;
            }

            .options-grid {
                gap: 14px;
                margin-bottom: 20px;
            }

            .option-btn {
                padding: 16px 20px;
                font-size: 1.05rem;
            }

            .footer {
                padding-right: 175px;
            }
        }

        @media (max-width: 768px) {
            .options-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .test-container {
                padding: 15px;
            }

            .q-text {
                font-size: 1.2rem;
            }

            .footer {
                padding-right: 0;
                padding-bottom: 10px;
            }
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
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .review-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            background: rgba(0, 0, 0, 0.2);
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
            <?php if (!empty($concept)): ?>
                <p>Topic / Concept: <strong><?php echo htmlspecialchars($concept); ?></strong></p>
            <?php endif; ?>
            <div style="text-align: left; margin: 30px 0; background: #f8f9fa; padding: 20px; border-radius: 12px;">
                <p>• 40 Questions (<?php echo !empty($concept) ? 'AI Sourced' : 'Database Sourced'; ?>)</p>
                <p>• 40 Minutes Total Time</p>
                <p>• Full-screen experience recommended</p>
                <p>• Questions focus on <?php echo !empty($concept) ? htmlspecialchars($concept) : 'company awareness'; ?> & aptitude</p>
                <div id="proctor-env-box" style="margin-top: 15px; padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;">
                    <div style="font-weight: 600; color: var(--primary);"><i class="fas fa-shield-alt"></i> AI Proctoring Active</div>
                    <div id="proctor-env-status" style="margin-top: 4px; color: #64748b; font-size: 0.85rem;">Camera & baseline calibration will initialize on start.</div>
                </div>
            </div>
            <button class="btn-start" id="btnStartTest" onclick="startTest()">Initialize Test Environment</button>
            <p style="margin-top: 20px; font-size: 0.9rem; color: #888;">By clicking start, you agree to follow the
                assessment protocols.</p>
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
            <p id="loaderText" style="text-align: center;">Loading assessment questions...</p>
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
    </div>

    <!-- Warning Overlay (must not be inside #resultsUI, which is display:none during the test) -->
    <div id="warningOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.95); z-index: 9999; align-items: center; justify-content: center;">
        <div style="text-align: center; max-width: 500px; padding: 30px; border: 2px solid var(--primary); background: #1a1a1a; border-radius: 16px; color: #fff;">
            <i class="fas fa-exclamation-triangle" style="color: #e74c3c; font-size: 3rem; margin-bottom: 20px;"></i>
            <h2 id="warningTitle" style="color: #fff; margin-bottom: 10px;">Security Violation</h2>
            <p id="warningText" style="color: #ccc; margin-bottom: 25px;">
                You have left the test window or exited Full Screen mode. This violation has been logged with an immediate camera snapshot.<br>
                Please return to full screen immediately to continue.
            </p>
            <button id="warningBtn" onclick="resumeFullscreen()" class="btn-start" style="width: 100%; margin-top: 0;">RESUME ASSESSMENT</button>
        </div>
    </div>

    <script>
        window.CSRF_TOKEN = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
        let questions = [];
        let userAnswers = {};
        let currentIdx = 0;
        let timeLeft = 40 * 60; // 40 minutes for 40 questions
        let timerInterval;
        // json_encode (not addslashes) so a value containing a closing script tag or a line break can't break the script
        const companyName = <?php echo json_encode((string)$companyName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const conceptName = <?php echo json_encode((string)($concept ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const taskIdStr = "<?php echo (int)$taskId; ?>";

        let testStarted = false;
        let isSubmitting = false;
        let proctorEngine = null;
        let proctorReady = false; // camera calibrated + monitoring started (don't redo it on a retry)

        function escapeHtml(text) {
            return String(text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        // Initialize Proctoring Engine
        try {
            proctorEngine = new ProctoringEngine({
                studentId: <?php echo json_encode((string)getUsername(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                assessmentId: <?php echo (int)($taskId ?: 1); ?>,
                assessmentType: 'aptitude',
                apiEndpoint: 'proctor_handler.php',
                onWarning: (data) => {
                    console.warn("Proctor Warning:", data);
                },
                onAutoSubmit: () => {
                    console.error("Proctor: Assessment auto-terminated due to maximum integrity violations.");
                    submitTest();
                }
            });
        } catch (e) {
            console.error("ProctoringEngine instantiation error:", e);
        }

        function renderMath(element) {
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(element, {
                    delimiters: [
                        { left: "$$", right: "$$", display: true },
                        { left: "$", right: "$", display: false },
                        { left: "\\(", right: "\\)", display: false },
                        { left: "\\[", right: "\\]", display: true }
                    ],
                    throwOnError: false
                });
            } else {
                setTimeout(() => renderMath(element), 100);
            }
        }

        // Question text is plain text that may contain comparisons like "a<b" —
        // inserted raw, the browser parsed those as tags and swallowed the rest of
        // the question/options. Escape everything, then re-allow simple formatting tags.
        function safeText(s) {
            // '&' is left alone: stored questions contain entities like &quot;
            return String(s ?? '')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/&lt;(\/?(?:b|i|u|br|sup|sub|strong|em|small))\s*\/?&gt;/gi, '<$1>');
        }

        function shuffleArray(arr) {
            for (let i = arr.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [arr[i], arr[j]] = [arr[j], arr[i]];
            }
        }

        async function startTest() {
            const startBtn = document.getElementById('btnStartTest') || document.querySelector('.btn-start');
            const statusEl = document.getElementById('proctor-env-status');
            const isMac = /Mac/i.test(navigator.platform || navigator.userAgent);
            // Prevent a second click while camera/calibration is in progress
            if (startBtn) startBtn.disabled = true;

            // 1. Initialize Proctoring & Camera (skipped on a retry once calibration already passed)
            if (proctorEngine && !proctorReady) {
                if (statusEl) statusEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--primary);"></i> Initializing camera & proctoring session...';

                try {
                    // On a calibration retry the camera is already open — re-opening it would leak the old stream
                    if (!proctorEngine._stream) {
                        const camReady = await proctorEngine.init();
                        if (!camReady) {
                            // The engine wrote the specific reason (denied / in use / not found) into the status line
                            const engineMsg = statusEl ? statusEl.textContent.trim() : '';
                            if (startBtn) { startBtn.disabled = false; startBtn.textContent = 'Retry Camera'; }
                            if (statusEl) statusEl.innerHTML = '<span style="color:#e74c3c;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> '
                                + escapeHtml(engineMsg || 'Camera access required. Please allow camera in browser and retry.') + '</span>'
                                + (isMac
                                    ? '<br><span style="font-size:0.8rem;color:#64748b;">On a Mac: open System Settings → Privacy &amp; Security → Camera, turn on your browser, then quit and reopen the browser. Close FaceTime, Zoom or Teams if they are using the camera.</span>'
                                    : '<br><span style="font-size:0.8rem;color:#64748b;">Click the camera icon in the address bar to allow access, and close other apps that are using the camera.</span>');
                            return;
                        }
                    }

                    if (statusEl) statusEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--primary);"></i> Calibrating posture & identity baseline... Keep your face centered in the camera preview (bottom-right).';
                    const envCheck = await proctorEngine.runEnvCheck();
                    if (!envCheck.passed) {
                        if (startBtn) { startBtn.disabled = false; startBtn.textContent = 'Retry Calibration'; }
                        const reason = (envCheck.reasons && envCheck.reasons.length) ? envCheck.reasons.join(' ') : 'Camera calibration failed. Please ensure face is centered and lighting is adequate.';
                        if (statusEl) statusEl.innerHTML = `<span style="color:#e74c3c;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> ${escapeHtml(reason)}</span><br><span style="font-size:0.8rem;color:#64748b;">Face the screen with your face fully visible in the preview, add light in front of you, then click Retry Calibration.</span>`;
                        return;
                    }

                    proctorEngine.start();
                    proctorReady = true;
                } catch (pErr) {
                    console.warn("Proctor init warning:", pErr);
                }
            }

            // 2. Fullscreen — usually refused here because the Start click was long ago (camera + calibration),
            // so the student then gets an in-page "Enter Full Screen" prompt instead (not a violation)
            await requestFullscreenCompat().catch(() => console.log('Fullscreen failed'));

            document.getElementById('introOverlay').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('introOverlay').style.display = 'none';
                document.getElementById('testUI').style.display = 'flex';
                if (!getFullscreenElement() && !isSubmitting) showSecurityOverlay(true);
            }, 700);

            loadQuestions();
        }

        let loadAttempts = 0;
        const MAX_AUTO_RETRIES = 2;

        function showLoadError(msg) {
            const area = document.getElementById('questionArea');
            area.innerHTML = `
                <div style="text-align:center; max-width: 500px;">
                    <i class="fas fa-triangle-exclamation" style="font-size:2.5rem; color:var(--error); margin-bottom:15px;"></i>
                    <p style="margin-bottom:20px; color:#eee;">${escapeHtml(msg)}</p>
                    <button class="btn-start" onclick="retryLoadQuestions()">Retry Loading Questions</button>
                </div>`;
        }

        function showLoader(text) {
            document.getElementById('questionArea').innerHTML =
                `<div class="loader"></div><p style="text-align: center;">${escapeHtml(text)}</p>`;
        }

        function retryLoadQuestions() {
            showLoader('Loading assessment questions...');
            loadQuestions();
        }

        function beginTest(qs) {
            if (testStarted) return;
            if (!Array.isArray(qs) || qs.length === 0) {
                showLoadError('No questions are available for this assessment. Please contact your coordinator.');
                return;
            }
            testStarted = true;
            questions = qs;
            renderQuestion();
            startTimer();
        }

        async function loadQuestions() {
            loadAttempts++;
            try {
                const formData = new FormData();
                formData.append('action', 'get_questions');
                formData.append('company_name', companyName);
                formData.append('concept', conceptName);
                formData.append('task_id', taskIdStr); // Fix: coordinator task_id must be sent so manual questions are fetched
                formData.append('csrf_token', window.CSRF_TOKEN);

                const response = await fetch('ai_aptitude_handler.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN },
                    body: formData
                });

                let data;
                const responseClone = response.clone();
                try {
                    data = await response.json();
                } catch (jsonErr) {
                    showLoadError('Unable to load assessment questions. Please refresh.');
                    return;
                }
                if (data.success && data.job_id) {
                    // Poll for AI questions while we have DB questions ready
                    const dbQuestions = data.db_questions || [];
                    const pollStartedAt = Date.now();
                    showLoader('Generating your ' + (conceptName || 'aptitude') + ' questions with AI... this can take up to 2 minutes.');
                    const pollInterval = setInterval(async () => {
                        // Never poll forever: 40 questions are generated in batches of
                        // 10 (~30s each), so allow 4 minutes before falling back to DB questions
                        if (Date.now() - pollStartedAt > 240000) {
                            clearInterval(pollInterval);
                            if (dbQuestions.length > 0) beginTest(dbQuestions);
                            else showLoadError('The AI question generator timed out. Please retry.');
                            return;
                        }
                        try {
                            const statusRes = await fetch(`ai_job_status.php?job_id=${data.job_id}`).then(r => r.json());
                            if (statusRes.status === 'completed') {
                                clearInterval(pollInterval);
                                // Extract payload robustly
                                let resultPayload = statusRes.result;
                                if (resultPayload && resultPayload.result && typeof resultPayload.result === 'object') {
                                    resultPayload = resultPayload.result;
                                } else if (resultPayload && resultPayload.content && typeof resultPayload.content === 'string') {
                                    try { resultPayload = JSON.parse(resultPayload.content); } catch (e) { }
                                }

                                const aiQuestions = Array.isArray(resultPayload) ? resultPayload : (resultPayload?.questions || []);
                                if (aiQuestions.length > 0) {
                                    shuffleArray(aiQuestions);
                                    beginTest(aiQuestions);
                                } else if (dbQuestions.length > 0) {
                                    shuffleArray(dbQuestions);
                                    beginTest(dbQuestions);
                                } else {
                                    showLoadError('No questions are available.');
                                }
                            } else if (statusRes.status === 'failed' || statusRes.success === false) {
                                clearInterval(pollInterval);
                                // AI generation failed — fall back to DB questions only
                                if (dbQuestions.length > 0) {
                                    shuffleArray(dbQuestions);
                                    beginTest(dbQuestions);
                                } else {
                                    showLoadError('The AI question generator failed. Please retry.');
                                }
                            }
                        } catch (e) { console.error('Polling error:', e); }
                    }, 2000);
                } else if (data.success) {
                    beginTest(data.questions);
                } else {
                    showLoadError('Error: ' + (data.message || 'Failed to load questions.'));
                }
            } catch (e) {
                console.error('loadQuestions network failure:', e);
                if (loadAttempts <= MAX_AUTO_RETRIES) {
                    showLoader('Connection hiccup — retrying (' + loadAttempts + '/' + MAX_AUTO_RETRIES + ')...');
                    setTimeout(loadQuestions, 2500);
                } else {
                    showLoadError('Connection error. Please check your internet and retry.');
                }
            }
        }

        window.reportCurrentQuestion = function () {
            const q = questions[currentIdx];
            window.openQuestionReportModal({
                test_type: 'mock_ai',
                test_id: (taskIdStr !== '0' ? taskIdStr : 'aptitude'),
                question_text: q.question,
                options: q.options,
                correct_answer: q.answer,
                user_answer: userAnswers[currentIdx]
            });
        };

        function renderQuestion() {
            const q = questions[currentIdx];
            if (!q || !Array.isArray(q.options)) {
                console.error('Malformed question at index', currentIdx, q);
                showLoadError('This question could not be displayed. Please retry the test.');
                return;
            }
            const area = document.getElementById('questionArea');

            let optionsHtml = '';
            q.options.forEach((opt, i) => {
                const isSelected = userAnswers[currentIdx] === i ? 'selected' : '';
                optionsHtml += `
                    <button class="option-btn ${isSelected}" onclick="selectOption(${i})">
                        ${safeText(opt)}
                    </button>
                `;
            });

            area.innerHTML = `
                <div class="question-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span class="q-number">SECTION: ${safeText(q.category || 'General Aptitude')}</span>
                        <a href="javascript:void(0)" onclick="reportCurrentQuestion()" style="color: var(--secondary); text-decoration: none; font-size: 0.9rem; font-weight: 600;"><i class="fas fa-flag"></i> Report Issue</a>
                    </div>
                    <h2 class="q-text">${safeText(q.question)}</h2>
                    <div class="options-grid">
                        ${optionsHtml}
                    </div>
                </div>
            `;

            updateUI();
            renderMath(area);
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
            if (timerInterval) clearInterval(timerInterval);
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

        // --- Fullscreen helpers (Safari < 16.4 only has the webkit-prefixed API) ---
        function getFullscreenElement() {
            return document.fullscreenElement || document.webkitFullscreenElement || null;
        }

        // The macOS fullscreen transition briefly blurs the window; don't treat that as leaving the test
        let fullscreenGraceUntil = 0;

        function requestFullscreenCompat() {
            fullscreenGraceUntil = Date.now() + 1500;
            if (proctorEngine && typeof proctorEngine.noteFullscreenRequest === 'function') {
                proctorEngine.noteFullscreenRequest();
            }
            const el = document.documentElement;
            if (el.requestFullscreen) return el.requestFullscreen();
            if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
                return Promise.resolve();
            }
            return Promise.reject(new Error('Fullscreen API not supported'));
        }

        // isPrompt = true: plain "enter full screen" request, not a violation
        function showSecurityOverlay(isPrompt) {
            const titleEl = document.getElementById('warningTitle');
            const textEl = document.getElementById('warningText');
            const btnEl = document.getElementById('warningBtn');
            if (isPrompt) {
                titleEl.textContent = 'Full Screen Required';
                textEl.textContent = 'Click the button below to enter full screen and continue your assessment. This is not counted as a warning.';
                btnEl.textContent = 'ENTER FULL SCREEN';
            } else {
                titleEl.textContent = 'Security Violation';
                textEl.innerHTML = 'You have left the test window or exited Full Screen mode. This violation has been logged with an immediate camera snapshot.<br>Please return to full screen immediately to continue.';
                btnEl.textContent = 'RESUME ASSESSMENT';
            }
            document.getElementById('warningOverlay').style.display = 'flex';
        }

        function resumeFullscreen() {
            requestFullscreenCompat().then(() => {
                document.getElementById('warningOverlay').style.display = 'none';
            }).catch(() => {
                // Keep the overlay and explain inline (a native popup would blur the window again)
                const textEl = document.getElementById('warningText');
                if (textEl && !textEl.querySelector('.fs-blocked-hint')) {
                    textEl.insertAdjacentHTML('beforeend', '<br><br><span class="fs-blocked-hint" style="color:#f59e0b;">Your browser blocked full screen. Please click the button again, and allow full screen if your browser asks.</span>');
                }
            });
        }

        // These only show the overlay — strikes are counted by the proctoring engine itself
        function onFullscreenChange() {
            if (!getFullscreenElement() && testStarted && !isSubmitting) {
                showSecurityOverlay(false);
            } else if (getFullscreenElement()) {
                document.getElementById('warningOverlay').style.display = 'none';
            }
        }
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden' && testStarted && !isSubmitting && Date.now() > fullscreenGraceUntil) {
                showSecurityOverlay(false);
            }
        });

        window.addEventListener('blur', () => {
            if (testStarted && !isSubmitting && Date.now() > fullscreenGraceUntil) {
                showSecurityOverlay(false);
            }
        });

        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('cut', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());

        document.addEventListener('keydown', e => {
            const key = (e.key || '').toLowerCase();
            // metaKey = Cmd on macOS
            if ((e.ctrlKey || e.metaKey) && ['c', 'v', 'x', 'u'].includes(key)) {
                e.preventDefault();
            }
            // Inspect: Ctrl+Shift+I / Cmd+Shift+I / Cmd+Option+I (Option changes e.key on Mac, so check e.code)
            if (((e.ctrlKey || e.metaKey) && e.shiftKey && (key === 'i' || e.code === 'KeyI')) ||
                (e.metaKey && e.altKey && e.code === 'KeyI')) {
                e.preventDefault();
            }
            if (e.key === 'F12') {
                e.preventDefault();
            }
        });

        async function submitTest() {
            if (isSubmitting) return;
            isSubmitting = true;
            if (timerInterval) clearInterval(timerInterval);
            if (proctorEngine) {
                try { proctorEngine.stop(); } catch (e) { }
            }

            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerText = 'Submitting...';
            }

            document.getElementById('warningOverlay').style.display = 'none';
            document.getElementById('testUI').style.display = 'none';
            document.getElementById('resultsUI').style.display = 'flex';

            try {
                const formData = new FormData();
                formData.append('action', 'submit_test');
                formData.append('company_name', companyName);
                formData.append('answers', JSON.stringify(userAnswers));
                formData.append('questions', JSON.stringify(questions));
                formData.append('task_id', taskIdStr);
                formData.append('time_taken', 40 * 60 - timeLeft); // Fix: Send actual time taken to coordinator dashboard
                formData.append('proctor_token', proctorEngine ? (proctorEngine._token || '') : '');
                formData.append('csrf_token', window.CSRF_TOKEN);

                const response = await fetch('ai_aptitude_handler.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN },
                    body: formData
                });

                let data;
                try {
                    data = await response.json();
                } catch (jsonErr) {
                    data = { success: false, message: 'Invalid response from server. Please retry.' };
                }

                if (data.success) {
                    document.getElementById('finalScore').innerText = Math.round(data.score) + '%';
                    let breakdownText = `You answered ${data.correct} out of ${data.total} questions correctly.`;
                    if (data.penalty_pct > 0) {
                        breakdownText += ` (Raw Score: ${data.raw_score}% | Integrity Penalty: -${data.penalty_pct}%)`;
                    }
                    document.getElementById('resultMsg').innerText = breakdownText;


                    // Render Review Section
                    if (data.results && data.results.questions) {
                        // Store finalized questions for review reporting
                        questions = data.results.questions;
                        userAnswers = data.results.user_answers;
                        window.reportReviewQuestion = function (idx) {
                            const q = questions[idx];
                            const userAns = userAnswers[idx];
                            window.openQuestionReportModal({
                                test_type: 'mock_ai',
                                test_id: (taskIdStr !== '0' ? taskIdStr : 'aptitude'),
                                question_text: q.question,
                                options: q.options,
                                correct_answer: q.answer,
                                user_answer: userAns
                            });
                        };

                        let html = '<div class="review-section">';
                        data.results.questions.forEach((q, idx) => {
                            const userAns = data.results.user_answers[idx];
                            const correctAns = parseInt(q.answer);

                            html += `<div class="review-card">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                    <div class="review-q">Q${idx + 1}: ${safeText(q.question)}</div>
                                    <button class="btn-control" style="padding: 4px 10px; font-size: 0.8rem; background: transparent; border-color: rgba(255,255,255,0.1); color: var(--secondary); cursor: pointer;" onclick="reportReviewQuestion(${idx})"><i class="fas fa-flag"></i> Report</button>
                                </div>`;

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

                                html += `<div class="${cls}"><span>${safeText(opt)}</span> <span>${icon}</span></div>`;
                            });

                            // Add Explanation
                            if (q.explanation) {
                                html += `<div class="review-explanation"><strong>💡 Reason:</strong> ${safeText(q.explanation)}</div>`;
                            }

                            html += `</div>`;
                        });
                        html += '</div>';
                        document.getElementById('resultDetails').innerHTML = html;
                        renderMath(document.getElementById('resultDetails'));
                    }
                } else {
                    document.getElementById('resultMsg').innerHTML = `
                        <span style="color:#ef4444;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> ${escapeHtml(data.message || 'Submission failed. Please click retry.')}</span>
                        <div style="margin-top:15px;">
                            <button onclick="retrySubmitTest()" class="btn-nav" style="background:var(--secondary);color:#000;border:none;font-weight:700;padding:10px 24px;cursor:pointer;">Retry Submission</button>
                        </div>
                    `;
                }
            } catch (e) {
                document.getElementById('resultMsg').innerHTML = `
                    <span style="color:#ef4444;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Connection error on submission.</span>
                    <div style="margin-top:15px;">
                        <button onclick="retrySubmitTest()" class="btn-nav" style="background:var(--secondary);color:#000;border:none;font-weight:700;padding:10px 24px;cursor:pointer;">Retry Submission</button>
                    </div>
                `;
            }
        }

        function retrySubmitTest() {
            isSubmitting = false;
            document.getElementById('resultMsg').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Re-attempting test submission...';
            submitTest();
        }
    </script>
</body>

</html>