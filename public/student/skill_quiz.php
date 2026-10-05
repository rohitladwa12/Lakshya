<?php
/**
 * AI Skill Verification Quiz — Dual-Stream Proctoring & Integrity Engine
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
$portfolioId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($filters['id'] ?? 0);

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
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Verification: <?php echo htmlspecialchars($skillName); ?> - <?php echo APP_NAME; ?></title>
    <!-- In-page dialogs (replaces native alert/confirm) — must load before other page scripts -->
    <script src="../js/lakshya_dialogs.js?v=<?php echo APP_VERSION; ?>"></script>
    <!-- Resilience & Cache Busting -->
    <script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- KaTeX for equation rendering -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
    <script src="report_question.js?v=<?php echo APP_VERSION; ?>"></script>
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
            user-select: none;
        }
        input, textarea { user-select: text; }

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
            display: block;
            text-align: center;
            padding: 10px;
        }

        .congrats-icon {
            font-size: 4.5rem;
            margin-bottom: 1rem;
        }

        .score-badge {
            display: inline-block;
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary-maroon);
            margin: 1.2rem 0;
            padding: 10px 32px;
            background: #fff5f5;
            border-radius: 50px;
            border: 2px solid var(--primary-maroon);
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
            background: rgba(0,0,0,0.92);
            z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column;
            overflow-y: auto;
            padding: 20px;
        }
        .hidden { display: none !important; }

        /* Proctor Floating Widget */
        .proctor-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 170px;
            background: #1e293b;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            z-index: 9000;
            display: none;
            cursor: grab;
            user-select: none;
            -webkit-user-select: none;
            touch-action: none;
        }
        .proctor-widget.is-dragging {
            cursor: grabbing !important;
            opacity: 0.88;
            box-shadow: 0 16px 36px rgba(0,0,0,0.6);
        }
        .proctor-widget video {
            width: 100%;
            height: 110px;
            object-fit: cover;
            background: #000;
        }
        .proctor-widget-bar {
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .proctor-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

        /* Integrity Report Card */
        .integrity-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .integrity-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 14px;
        }
        .integrity-metric {
            background: #fff;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .integrity-metric .label {
            font-weight: 600;
            color: #64748b;
        }
        .integrity-metric .val {
            font-weight: 800;
            color: #1e293b;
        }
        .integrity-pill {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 14px;
        }
        .integrity-pill.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .integrity-pill.warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

        /* Calibration Target Dot */
        .calib-target {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: #ef4444;
            border: 4px solid #fff;
            box-shadow: 0 0 20px rgba(239,68,68,0.8);
            margin: 15px auto;
            animation: calibPulse 1s infinite alternate;
        }
        @keyframes calibPulse { from { transform: scale(0.9); } to { transform: scale(1.15); } }

        /* Floating Proctor Widget */
        .proctor-widget {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 170px;
            background: rgba(15, 23, 42, 0.95);
            border: 2px solid var(--primary-maroon);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(10px);
        }
        .proctor-widget video {
            width: 100%;
            height: 110px;
            object-fit: cover;
            display: block;
            background: #000;
        }
        .proctor-widget-bar {
            padding: 8px 12px;
            background: rgba(2, 6, 23, 0.9);
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .proctor-dot {
            width: 7px; height: 7px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px #10b981;
            animation: proctor-blink 1.5s infinite;
        }
        @keyframes proctor-blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
    </style>
</head>
<body>

<!-- Setup & Calibration Modal Overlay -->
<div id="introOverlay" class="overlay">
    <div style="text-align: center; max-width: 650px; width: 95%; padding: 32px; background: white; border-radius: 24px; box-shadow: var(--shadow); border: 2px solid var(--primary-maroon);">
        <h1 style="color: var(--primary-maroon); font-size: 1.8rem; margin-bottom: 8px;">
            <i class="fas fa-shield-alt"></i> Assessment Proctoring Setup
        </h1>
        <h2 style="font-size: 1.2rem; color: #475569; margin-bottom: 20px;"><?php echo htmlspecialchars($skillName); ?> Verification</h2>

        <!-- Step Indicator -->
        <div style="display: flex; justify-content: space-around; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; font-size: 0.82rem; font-weight: 700;">
            <span id="stepTab1" style="color: var(--primary-maroon);"><i class="fas fa-video"></i> 1. Camera</span>
            <span id="stepTab2" style="color: #94a3b8;"><i class="fas fa-crosshairs"></i> 2. Calibration</span>
            <span id="stepTab3" style="color: #94a3b8;"><i class="fas fa-desktop"></i> 3. Screen Share</span>
        </div>

        <!-- Step 1 View: Camera & Quality Check -->
        <div id="stepView1">
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 16px;">
                We verify that you are facing the screen during the assessment. Local privacy-preserving processing is performed in your browser.
            </p>
            <video id="setupWebcamPreview" autoplay muted playsinline style="width: 240px; height: 160px; border-radius: 14px; background: #000; border: 2px solid #e2e8f0; margin-bottom: 14px; object-fit: cover; transform: scaleX(-1);"></video>
            <div id="setupCheckStatus" style="font-size: 0.85rem; font-weight: 700; color: #d97706; margin-bottom: 16px;">
                Click the button below to enable your camera.
            </div>
            <button id="btnGrantCamera" onclick="initCameraSetup()" class="btn btn-next" style="width: 100%; justify-content: center;">Enable Camera & Continue</button>
        </div>

        <!-- Step 2 View: Gaze & Pose Calibration -->
        <div id="stepView2" class="hidden">
            <h3 style="font-size: 1rem; color: #1e293b; margin-bottom: 8px;">Camera Baseline Calibration</h3>
            <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 12px;" id="calibPromptText">
                Look directly at the red center dot below for 3 seconds to establish your baseline.
            </p>
            <video id="calibWebcamPreview" autoplay muted playsinline style="width: 240px; height: 160px; border-radius: 14px; background: #000; border: 2px solid #e2e8f0; margin-bottom: 8px; object-fit: cover; transform: scaleX(-1);"></video>
            <div class="calib-target" id="calibTarget"></div>
            <div id="calibProgressText" style="font-size: 0.85rem; font-weight: 700; color: var(--primary-maroon); margin-bottom: 16px;">
                Progress: Center (0/3s)
            </div>
            <button id="btnRetryCalib" onclick="startCalibrationFlow()" class="btn btn-next hidden" style="width: 100%; justify-content: center;">↻ Retry Calibration</button>
        </div>

        <!-- Step 3 View: Screen Capture Selection -->
        <div id="stepView3" class="hidden">
            <h3 style="font-size: 1rem; color: #1e293b; margin-bottom: 8px;">Screen Share Verification</h3>
            <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 16px;">
                Select your assessment screen to enable authoritative screen integrity verification.
            </p>
            <div id="screenCheckStatus" style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 20px;">
                Screen share track ready to initialize.
            </div>
            <button onclick="initScreenShare()" class="btn btn-next" style="width: 100%; justify-content: center;">Share Screen & Start Assessment</button>
        </div>

    </div>
</div>

<div class="navbar">
    <h1><i class="fas fa-shield-alt"></i> Skill Verification</h1>
    <div style="display: flex; align-items: center; gap: 15px;">
        <span id="warningBadgeNav" style="background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> Warnings: <span id="warningCountNavText">0</span> / 3</span>
        <a href="dashboard" style="color: white; text-decoration: none;"><i class="fas fa-times"></i> Exit</a>
    </div>
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

        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <div id="questionsContainer"></div>

        <div style="display: flex; justify-content: space-between; margin-top: 2rem;">
            <button class="btn" id="btnPrev" onclick="prevQuestion()" style="visibility: hidden;"><i class="fas fa-arrow-left"></i> Previous</button>
            <button class="btn btn-next" id="btnNext" onclick="nextQuestion()">Next <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- Results View -->
    <div id="resultsView" style="display: none;">
        <div class="results-card">
            <div class="congrats-icon" id="congratsIcon"><i class="fas fa-check-circle"></i></div>
            <h1 id="resultsTitle">Skill Verified!</h1>
            <p id="resultsMessage" style="color: #666; margin-bottom: 1.5rem;"></p>
            
            <div class="score-badge" id="resultsScore">0%</div>

            <!-- Integrity Report Card -->
            <div class="integrity-card">
                <h3><i class="fas fa-shield-alt"></i> Assessment Integrity Audit</h3>
                <div class="integrity-pill success" id="rptStatusPill">Integrity Status: Verified</div>
                <div class="integrity-grid">
                    <div class="integrity-metric">
                        <div class="label">Screen Share</div>
                        <div class="val" id="rptScreenShare">100%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Camera Feed</div>
                        <div class="val" id="rptCameraAvail">100%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Face Presence</div>
                        <div class="val" id="rptFacePres">100%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Gaze Confidence</div>
                        <div class="val" id="rptGazeConf">92%</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Deviations</div>
                        <div class="val" id="rptAttnDev">0</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Max Deviation</div>
                        <div class="val" id="rptLongestDev">0s</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Screen Stops</div>
                        <div class="val" id="rptScreenInt">0</div>
                    </div>
                    <div class="integrity-metric">
                        <div class="label">Multi-Face</div>
                        <div class="val" id="rptMultiFace">0</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                <a href="dashboard" class="btn btn-next">Return to Dashboard</a>
                <button class="btn" onclick="toggleReview()" style="background: #f1f5f9; color: #334155;"><i class="fas fa-list"></i> Review Answers</button>
            </div>
        </div>

        <div class="review-section" id="reviewContainer">
            <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-file-alt"></i> Question Review</h2>
            <div id="reviewList"></div>
        </div>
    </div>
</div>

<!-- Floating Proctor Widget -->
<div id="proctorWidget" class="proctor-widget" style="display: none;">
    <video id="proctorWebcamVideo" autoplay muted playsinline></video>
    <div class="proctor-widget-bar" style="flex-direction: column; align-items: flex-start; gap: 2px;">
        <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
            <span><span class="proctor-dot"></span> Proctor Active</span>
            <span id="proctorWidgetStatus" style="font-weight: 800; color: #10b981;">100%</span>
        </div>
        <div id="proctorWidgetLabel" style="font-size: 9px; font-weight: 700; color: #10b981;"><i class="fas fa-user-check"></i> Face In Frame</div>
    </div>
</div>

<!-- Warning Overlay -->
<div id="warningOverlay" class="overlay hidden" style="background: rgba(0,0,0,0.95); z-index: 2000;">
    <div style="text-align: center; max-width: 500px; padding: 30px; border: 2px solid var(--primary-maroon); background: #000; border-radius: 12px; color: white;">
        <i id="warningIcon" class="fas fa-exclamation-triangle" style="color: var(--primary-maroon); font-size: 3rem; margin-bottom: 20px;"></i>
        <h2 id="warningTitle" style="margin-bottom: 10px;">Security Violation</h2>
        <p id="warningMessage" style="color: #ccc; margin-bottom: 25px;">
            You have exited Full Screen mode or interrupted screen sharing. This event has been logged in your assessment integrity report.<br>
            Please return to full screen immediately to continue.
        </p>
        <button id="warningBtn" onclick="resumeFullscreen()" class="btn" style="width: 100%; justify-content: center; background: var(--primary-maroon); color: white;">RESUME ASSESSMENT</button>
    </div>
</div>

<!-- Canvas for frame analysis -->
<canvas id="proctorAnalysisCanvas" width="320" height="240" style="display: none;"></canvas>

<script>
    const portfolioId = <?php echo $portfolioId; ?>;
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

    // --- PROCTORING & ATTENTION ENGINE STATE ---
    let webcamStream = null;
    let screenStream = null;
    let screenVideoEl = null;
    let proctorInterval = null;
    let calibrationData = { center: null, left: null, right: null, up: null, down: null };


    let proctorStats = {
        totalFrames: 0,
        validFaceFrames: 0,
        consecutiveNoFace: 0,
        consecutiveMultiFace: 0,
        consecutiveGazeDev: 0,
        lastLoggedNoFaceTime: 0,
        lastLoggedGazeTime: 0
    };

    let nativeFaceDetector = null;
    if ('FaceDetector' in window) {
        try {
            nativeFaceDetector = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 5 });
        } catch (e) {}
    }

    let mediaPipeDetector = null;
    let isMediaPipeLoading = false;

    // Pinned so the JS bundle and the WASM files always come from the same release
    const MEDIAPIPE_VERSION = '0.10.14';

    async function initMediaPipeDetector(delegates = ['GPU', 'CPU']) {
        if (mediaPipeDetector || isMediaPipeLoading) return;
        isMediaPipeLoading = true;
        try {
            const visionModule = await import(`https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MEDIAPIPE_VERSION}/vision_bundle.mjs`);
            const vision = await visionModule.FilesetResolver.forVisionTasks(
                `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MEDIAPIPE_VERSION}/wasm`
            );
            // The GPU delegate fails on some Macs / Safari builds, so fall back to CPU
            for (const delegate of delegates) {
                try {
                    mediaPipeDetector = await visionModule.FaceDetector.createFromOptions(vision, {
                        baseOptions: {
                            modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite',
                            delegate: delegate
                        },
                        runningMode: 'IMAGE',
                        minDetectionConfidence: 0.45,
                    });
                    console.log(`[Lakshya AI] MediaPipe BlazeFace detector initialized (${delegate}).`);
                    break;
                } catch (delegateErr) {
                    console.warn(`[Lakshya AI] MediaPipe ${delegate} delegate failed:`, delegateErr);
                }
            }
        } catch (err) {
            console.warn("[Lakshya AI] MediaPipe CDN load fallback:", err);
        } finally {
            isMediaPipeLoading = false;
        }
    }
    initMediaPipeDetector();

    const sleep = (ms) => new Promise(r => setTimeout(r, ms));

    // Gives the AI detector a few seconds to finish loading before calibration relies on it
    async function waitForFaceDetector(timeoutMs) {
        const start = Date.now();
        while (!mediaPipeDetector && isMediaPipeLoading && Date.now() - start < timeoutMs) {
            await sleep(200);
        }
    }

    /**
     * Counts faces in the current video frame.
     * Returns { count, centerX (0..1 of frame width, or null), source }.
     */
    async function detectFaces(videoEl, canvasEl) {
        if (mediaPipeDetector) {
            try {
                const mpResult = mediaPipeDetector.detect(videoEl);
                const detections = (mpResult && mpResult.detections) || [];
                const box = detections.length === 1 ? detections[0].boundingBox : null;
                return {
                    count: detections.length,
                    centerX: box ? (box.originX + box.width / 2) / videoEl.videoWidth : null,
                    source: 'mediapipe'
                };
            } catch (e) {
                // GPU context can be lost at runtime (common on Safari) — rebuild on CPU
                console.warn("[Lakshya AI] MediaPipe detect failed, switching to CPU:", e);
                try { mediaPipeDetector.close(); } catch (closeErr) {}
                mediaPipeDetector = null;
                initMediaPipeDetector(['CPU']);
            }
        }

        const ctx = canvasEl.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

        if (nativeFaceDetector) {
            try {
                const detected = await nativeFaceDetector.detect(canvasEl);
                const box = detected.length === 1 ? detected[0].boundingBox : null;
                return {
                    count: detected.length,
                    centerX: box ? (box.x + box.width / 2) / canvasEl.width : null,
                    source: 'native'
                };
            } catch (e) {}
        }

        // Heuristic YCbCr skin-pixel fallback (if ML models are offline).
        // It cannot tell one close face from two, so it never reports more than one face.
        const data = ctx.getImageData(0, 0, canvasEl.width, canvasEl.height).data;
        let skinPixelCount = 0;
        for (let i = 0; i < data.length; i += 16) {
            const r = data[i], g = data[i+1], b = data[i+2];
            const y  = 0.299 * r + 0.587 * g + 0.114 * b;
            const cb = 128 - (0.168736 * r) - (0.331264 * g) + (0.5 * b);
            const cr = 128 + (0.5 * r) - (0.418688 * g) - (0.081312 * b);
            if (y > 30 && cb >= 77 && cb <= 127 && cr >= 133 && cr <= 173) skinPixelCount++;
        }
        const skinRatio = skinPixelCount / (data.length / 16);
        return { count: skinRatio >= 0.025 ? 1 : 0, centerX: null, source: 'heuristic' };
    }

    // Security Measures: Disable Copy/Paste/Right-Click
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('copy', e => e.preventDefault());
    document.addEventListener('cut', e => e.preventDefault());
    document.addEventListener('paste', e => e.preventDefault());
    document.addEventListener('keydown', e => {
        // metaKey = Cmd on macOS
        if ((e.ctrlKey || e.metaKey) && ['c', 'v', 'x', 'u'].includes(e.key.toLowerCase())) e.preventDefault();
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'i') e.preventDefault();
        if (e.metaKey && e.altKey && e.key.toLowerCase() === 'i') e.preventDefault();
        if (e.key === 'F12') e.preventDefault();
    });

    let isSessionActive = false;

    // --- STEP 1: CAMERA PERMISSION & SETUP ---
    let cameraSetupTimer = null;

    async function initCameraSetup() {
        const statusEl = document.getElementById('setupCheckStatus');
        const videoEl = document.getElementById('setupWebcamPreview');
        const btnEl = document.getElementById('btnGrantCamera');

        if (cameraSetupTimer) clearTimeout(cameraSetupTimer);

        try {
            statusEl.textContent = 'Requesting camera permission…';
            
            // Clean old stream if any
            if (webcamStream) {
                try { webcamStream.getTracks().forEach(t => t.stop()); } catch(e){}
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (window.isSecureContext === false) {
                    throw new Error("INSECURE_CONTEXT");
                }
                throw new Error("MEDIA_NOT_SUPPORTED");
            }

            try {
                webcamStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                    audio: false
                });
            } catch (firstErr) {
                console.warn("Primary camera constraints failed, attempting fallback...", firstErr);
                webcamStream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: false
                });
            }

            // Store persistent global reference to prevent JS Garbage Collection
            window.activeWebcamStream = webcamStream;

            const videoTrack = webcamStream.getVideoTracks()[0];
            if (!videoTrack) throw new Error("No video track found.");

            videoEl.srcObject = webcamStream;
            videoEl.onloadedmetadata = () => videoEl.play().catch(e => {});
            if (videoEl.readyState >= 1) {
                videoEl.play().catch(e => {});
            }

            statusEl.style.color = '#10b981';
            if (btnEl) btnEl.style.display = 'none';

            // Track health monitors
            videoTrack.onended = () => handleCameraStreamLost();
            // macOS mutes the track for a moment while the FaceTime camera warms up or switches
            // (Continuity Camera, video effects). That is not a lost camera — only 'ended' is fatal.
            videoTrack.onmute = () => console.warn("[Lakshya AI] Camera track temporarily muted.");

            // 5-second countdown so student can position themselves comfortably
            let countdown = 5;
            let waitedForFrames = 0;

            const updateCountdown = () => {
                // Check if camera stream is still alive and playing
                const track = webcamStream ? webcamStream.getVideoTracks()[0] : null;
                if (!track || track.readyState !== 'live') {
                    handleCameraStreamLost();
                    return;
                }

                // Force video play if paused
                if (videoEl.paused) {
                    videoEl.play().catch(e => {});
                }

                // Don't start the countdown until the preview is actually showing a picture
                if (videoEl.videoWidth === 0 || track.muted) {
                    waitedForFrames++;
                    statusEl.style.color = '#d97706';
                    statusEl.innerHTML = waitedForFrames < 10
                        ? '<i class="fas fa-spinner fa-spin"></i> Waiting for camera picture…'
                        : '⚠ Camera opened but no picture is coming through.<br><span style="font-size:0.8rem; font-weight:600; color:#64748b;">Close other apps using the camera (FaceTime, Zoom, Teams). On a Mac, check System Settings → Privacy &amp; Security → Camera and make sure your browser is allowed.</span>';
                    if (waitedForFrames === 10 && btnEl) {
                        btnEl.textContent = '↻ Retry Camera Setup';
                        btnEl.style.display = 'inline-flex';
                    }
                    cameraSetupTimer = setTimeout(updateCountdown, 500);
                    return;
                }
                statusEl.style.color = '#10b981';
                if (btnEl) btnEl.style.display = 'none';

                if (countdown > 0) {
                    statusEl.innerHTML = `✓ Camera Active! Position yourself comfortably.<br><span style="color:var(--primary-maroon); font-size: 0.95rem; font-weight: 800;">Calibration starting in ${countdown} seconds…</span>`;
                    countdown--;
                    cameraSetupTimer = setTimeout(updateCountdown, 1000);
                } else {
                    document.getElementById('stepView1').classList.add('hidden');
                    document.getElementById('stepView2').classList.remove('hidden');
                    document.getElementById('stepTab1').style.color = '#94a3b8';
                    document.getElementById('stepTab2').style.color = 'var(--primary-maroon)';
                    startCalibrationFlow();
                }
            };

            updateCountdown();

        } catch (err) {
            console.error("Skill Quiz Camera Error:", err);
            handleCameraStreamLost(err);
        }
    }

    function handleCameraStreamLost(err = null) {
        if (cameraSetupTimer) clearTimeout(cameraSetupTimer);
        const statusEl = document.getElementById('setupCheckStatus');
        const btnEl = document.getElementById('btnGrantCamera');
        
        let errMsg = '❌ Camera stream lost or permission denied.<br><span style="font-size:0.8rem; font-weight:600; color:#64748b;">Please check your webcam connection and click Retry Camera below.</span>';

        if (err) {
            if (err.message === 'INSECURE_CONTEXT') {
                errMsg = '❌ Camera blocked: Insecure Context.<br><span style="font-size:0.8rem; font-weight:600; color:#ef4444;">Browsers require HTTPS or localhost for camera access. Please use https:// or access via localhost.</span>';
            } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                errMsg = '❌ Camera permission was denied.<br><span style="font-size:0.8rem; font-weight:600; color:#ef4444;">Click the 🔒 icon in your browser address bar, allow Camera access, and click Retry. On a Mac, also enable your browser under System Settings → Privacy &amp; Security → Camera, then restart the browser.</span>';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                errMsg = '❌ No camera device detected.<br><span style="font-size:0.8rem; font-weight:600; color:#64748b;">Please plug in a webcam and click Retry Camera below.</span>';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                errMsg = '❌ Camera is currently in use.<br><span style="font-size:0.8rem; font-weight:600; color:#64748b;">Another app (Zoom, Teams, or another tab) is using the webcam. Please close it and click Retry.</span>';
            } else if (err.name === 'SecurityError') {
                errMsg = '❌ Camera access restricted by security policy.<br><span style="font-size:0.8rem; font-weight:600; color:#64748b;">Permissions policy or browser configuration is blocking camera access.</span>';
            } else if (err.message === 'MEDIA_NOT_SUPPORTED') {
                errMsg = '❌ This browser does not support camera access.<br><span style="font-size:0.8rem; font-weight:600; color:#64748b;">Please use the latest Chrome, Edge, Firefox or Safari.</span>';
            }
        }

        if (statusEl) {
            statusEl.style.color = '#ef4444';
            statusEl.innerHTML = errMsg;
        }
        if (btnEl) {
            btnEl.textContent = '↻ Retry Camera Setup';
            btnEl.style.display = 'inline-flex';
            btnEl.disabled = false;
        }
    }

    // --- STEP 2: STUDENT CALIBRATION FLOW ---
    let isCalibrating = false;
    // Gaze threshold (fraction of frame width) — widened after calibration if the student's natural range is larger
    let gazeDeviationThreshold = 0.32;

    // Samples the live camera for durationMs and reports how often a single face was seen
    async function sampleCalibrationPose(durationMs, onTick) {
        const videoEl = document.getElementById('calibWebcamPreview');
        const canvasEl = document.getElementById('proctorAnalysisCanvas');
        let frames = 0, faceFrames = 0, multiFrames = 0, source = 'none';
        const centers = [];
        const start = Date.now();

        while (Date.now() - start < durationMs) {
            await sleep(250);
            if (onTick) onTick(Math.min(durationMs, Date.now() - start));
            if (!videoEl || videoEl.readyState < 2 || videoEl.videoWidth === 0) continue;
            const result = await detectFaces(videoEl, canvasEl);
            frames++;
            source = result.source;
            if (result.count === 1) {
                faceFrames++;
                if (result.centerX !== null) centers.push(result.centerX);
            } else if (result.count > 1) {
                multiFrames++;
            }
        }

        return {
            timestamp: Date.now(),
            frames: frames,
            confidence: frames > 0 ? Math.round((faceFrames / frames) * 100) / 100 : 0,
            multi_face_frames: multiFrames,
            face_center_x: centers.length ? centers.reduce((a, b) => a + b, 0) / centers.length : null,
            detector: source
        };
    }

    // Runs one pose, retrying until a face is visible. Returns the pose data, or null if the student must retry.
    async function calibratePose(label, prompt, durationMs) {
        const textEl = document.getElementById('calibPromptText');
        const progEl = document.getElementById('calibProgressText');
        const maxAttempts = 3;

        for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            textEl.textContent = prompt;
            progEl.style.color = 'var(--primary-maroon)';
            const pose = await sampleCalibrationPose(durationMs, (elapsed) => {
                progEl.textContent = `Calibrating ${label} Baseline (${Math.ceil(elapsed / 1000)}/${Math.round(durationMs / 1000)}s)`;
            });

            if (pose.frames === 0) {
                progEl.style.color = '#ef4444';
                progEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No camera picture received. Retrying…';
            } else if (pose.multi_face_frames > pose.frames / 2) {
                progEl.style.color = '#ef4444';
                progEl.innerHTML = '<i class="fas fa-users-slash"></i> More than one face detected. Only you should be in front of the camera.';
            } else if (pose.confidence >= 0.4) {
                progEl.style.color = '#10b981';
                progEl.innerHTML = `<i class="fas fa-check-circle"></i> ${label} Baseline Saved!`;
                await sleep(700);
                return pose;
            } else {
                progEl.style.color = '#ef4444';
                progEl.innerHTML = '<i class="fas fa-user-slash"></i> We can\'t see your face clearly.<br><span style="font-size:0.8rem; color:#64748b;">Sit facing the screen, keep your whole face inside the preview and make sure the room is well lit (avoid a bright window behind you).</span>';
            }
            await sleep(2500);
        }
        return null;
    }

    async function startCalibrationFlow() {
        if (isCalibrating) return;
        isCalibrating = true;

        const textEl = document.getElementById('calibPromptText');
        const progEl = document.getElementById('calibProgressText');
        const retryBtn = document.getElementById('btnRetryCalib');
        const calibVideo = document.getElementById('calibWebcamPreview');
        if (retryBtn) retryBtn.classList.add('hidden');

        // Keep the face preview visible during calibration
        if (calibVideo && webcamStream && calibVideo.srcObject !== webcamStream) {
            calibVideo.srcObject = webcamStream;
            calibVideo.play().catch(e => {});
        }

        textEl.textContent = 'Get ready! Sit straight and face the screen.';
        progEl.style.color = 'var(--primary-maroon)';
        if (!mediaPipeDetector && isMediaPipeLoading) {
            progEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading AI face detector…';
            await waitForFaceDetector(10000);
        }
        for (let s = 3; s >= 1; s--) {
            progEl.textContent = `Calibration Starting in ${s} second${s > 1 ? 's' : ''}…`;
            await sleep(1000);
        }

        const center = await calibratePose('Center', 'Look directly at the center target.', 3000);
        if (!center) {
            isCalibrating = false;
            textEl.textContent = 'Calibration could not detect your face.';
            if (retryBtn) retryBtn.classList.remove('hidden');
            return;
        }
        calibrationData.center = center;

        // Side poses only widen the gaze tolerance, so a weak result there is not fatal
        calibrationData.left = await sampleCalibrationPose(2000, () => {
            textEl.textContent = 'Turn your head slightly to your LEFT.';
            progEl.style.color = 'var(--primary-maroon)';
            progEl.textContent = 'Calibrating Left Baseline…';
        });
        progEl.innerHTML = '<i class="fas fa-check-circle"></i> Left Baseline Saved!';
        await sleep(700);

        calibrationData.right = await sampleCalibrationPose(2000, () => {
            textEl.textContent = 'Turn your head slightly to your RIGHT.';
            progEl.textContent = 'Calibrating Right Baseline…';
        });
        progEl.innerHTML = '<i class="fas fa-check-circle"></i> Right Baseline Saved!';
        await sleep(700);

        const baseX = center.face_center_x;
        const sideSpread = [calibrationData.left.face_center_x, calibrationData.right.face_center_x]
            .filter(x => x !== null && baseX !== null)
            .map(x => Math.abs(x - baseX));
        if (sideSpread.length) {
            gazeDeviationThreshold = Math.min(0.42, Math.max(0.32, Math.max(...sideSpread) + 0.08));
        }
        isCalibrating = false;

        // Save calibration to backend
        try {
            await fetch('skill_verification_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ action: 'save_calibration', portfolio_id: portfolioId, calibration: calibrationData })
            });
        } catch (e) {}

        document.getElementById('stepView2').classList.add('hidden');
        document.getElementById('stepView3').classList.remove('hidden');
        document.getElementById('stepTab2').style.color = '#94a3b8';
        document.getElementById('stepTab3').style.color = 'var(--primary-maroon)';
    }


    // --- STEP 3: SCREEN SHARE INITIALIZATION ---
    async function initScreenShare() {
        const statusEl = document.getElementById('screenCheckStatus');
        statusEl.style.color = '#d97706';
        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Requesting screen share selection…';

        try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: { displaySurface: "monitor", cursor: "always" },
                audio: false
            });

            const screenTrack = screenStream.getVideoTracks()[0];
            const settings = screenTrack && screenTrack.getSettings ? screenTrack.getSettings() : {};

            if (settings.displaySurface && settings.displaySurface !== 'monitor') {
                screenStream.getTracks().forEach(t => t.stop());
                screenStream = null;
                statusEl.style.color = '#ef4444';
                statusEl.innerHTML = '<i class="fas fa-ban"></i> <strong>Entire Screen Required!</strong><br><span style="font-size:0.85rem; color:#ef4444;">You selected a single tab or window. You must choose <strong>"Entire Screen"</strong> to proceed.</span>';
                return;
            }

            setupScreenTrackListener(screenTrack);

            if (!screenVideoEl) {
                screenVideoEl = document.createElement('video');
                screenVideoEl.autoplay = true;
                screenVideoEl.muted = true;
                screenVideoEl.playsInline = true;
                screenVideoEl.style.display = 'none';
                document.body.appendChild(screenVideoEl);
            }
            screenVideoEl.srcObject = screenStream;
            screenVideoEl.play().catch(() => {});

            statusEl.style.color = '#10b981';
            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Screen Share Active! Starting assessment…';


            setTimeout(() => {
                document.getElementById('introOverlay').style.display = 'none';
                beginAssessmentExecution();
            }, 1000);

        } catch (err) {
            console.error("Skill Quiz Screen Share Error:", err);
            statusEl.style.color = '#ef4444';
            if (err && err.name === 'NotAllowedError' && /system/i.test(err.message || '')) {
                // Chrome reports "Permission denied by system" when macOS Screen Recording access is off
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Your computer blocked screen sharing.<br><span style="font-size:0.85rem; color:#64748b;">On a Mac: open System Settings → Privacy &amp; Security → Screen &amp; System Audio Recording, enable your browser, then fully quit and reopen the browser.</span>';
            } else if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> This browser does not support screen sharing. Please use the latest Chrome, Edge, Firefox or Safari on a computer.';
            } else {
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Screen share permission is required to proceed.<br><span style="font-size:0.85rem; color:#64748b;">If the picker never appeared on a Mac, check System Settings → Privacy &amp; Security → Screen &amp; System Audio Recording.</span>';
            }
        }
    }

    async function beginAssessmentExecution() {
        document.getElementById('quizView').style.display = 'block';
        await enterFullscreen();
        isSessionActive = true;
        // Keep the PHP session alive while answers are typed — an expired session would lose the submission
        if (!window._sessionKeepAlive) {
            window._sessionKeepAlive = setInterval(() => {
                if (!isSessionActive) return;
                fetch('skill_verification_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ action: 'ping' })
                }).catch(() => {});
            }, 4 * 60 * 1000);
        }
        // The quiz timer starts in initQuiz() once questions arrive, so AI generation time doesn't eat into it

        // Detach setup video preview so stream switches cleanly to proctorWebcamVideo
        const setupVid = document.getElementById('setupWebcamPreview');
        if (setupVid) setupVid.srcObject = null;
        const calibVid = document.getElementById('calibWebcamPreview');
        if (calibVid) calibVid.srcObject = null;

        // Browsers (Safari especially) refuse fullscreen once the click that started screen share has expired.
        // Ask for one more click instead of silently running outside fullscreen — this is not a strike.
        if (!getFullscreenElement()) showFullscreenPrompt();

        // Show floating proctor widget
        const widget = document.getElementById('proctorWidget');
        const widgetVideo = document.getElementById('proctorWebcamVideo');
        if (widget && widgetVideo && webcamStream) {
            widget.style.display = 'block';
            widgetVideo.srcObject = webcamStream;
            enableDraggableWidget(widget);
            try {
                await widgetVideo.play();
            } catch (e) {}
        }

        // Start continuous AI monitoring loop (every 1.2s)
        if (proctorInterval) clearInterval(proctorInterval);
        proctorInterval = setInterval(runProctoringCheckFrame, 1200);

        startQuiz();
    }

    function enableDraggableWidget(el) {
        if (!el || el._dragInit) return;
        el._dragInit = true;
        let isDragging = false;
        let startX = 0, startY = 0, initialLeft = 0, initialTop = 0;

        const onStart = (e) => {
            if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') return;
            isDragging = true;
            el.classList.add('is-dragging');
            const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
            const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;
            const rect = el.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;
            startX = clientX;
            startY = clientY;

            el.style.bottom = 'auto';
            el.style.right = 'auto';
            el.style.left = `${initialLeft}px`;
            el.style.top = `${initialTop}px`;
        };

        const onMove = (e) => {
            if (!isDragging) return;
            const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
            const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;
            const dx = clientX - startX;
            const dy = clientY - startY;

            let newLeft = initialLeft + dx;
            let newTop = initialTop + dy;
            const maxLeft = Math.max(0, window.innerWidth - el.offsetWidth - 8);
            const maxTop = Math.max(0, window.innerHeight - el.offsetHeight - 8);

            newLeft = Math.max(8, Math.min(maxLeft, newLeft));
            newTop = Math.max(8, Math.min(maxTop, newTop));

            el.style.left = `${newLeft}px`;
            el.style.top = `${newTop}px`;
            if (e.cancelable) e.preventDefault();
        };

        const onEnd = () => {
            if (!isDragging) return;
            isDragging = false;
            el.classList.remove('is-dragging');
        };

        el.addEventListener('mousedown', onStart);
        window.addEventListener('mousemove', onMove, { passive: false });
        window.addEventListener('mouseup', onEnd);
        el.addEventListener('touchstart', onStart, { passive: true });
        window.addEventListener('touchmove', onMove, { passive: false });
        window.addEventListener('touchend', onEnd);
    }

    // --- REAL-TIME PROCTORING FRAME MONITORING ENGINE ---
    async function runProctoringCheckFrame() {
        if (!isSessionActive || !webcamStream) return;

        const videoEl = document.getElementById('proctorWebcamVideo');
        const canvasEl = document.getElementById('proctorAnalysisCanvas');
        const statusWidgetEl = document.getElementById('proctorWidgetStatus');
        const statusLabelEl = document.getElementById('proctorWidgetLabel');

        if (!videoEl || !canvasEl) return;

        if (videoEl.paused || videoEl.ended || videoEl.videoWidth === 0 || videoEl.videoHeight === 0) {
            videoEl.play().catch(e => {});
            return;
        }

        proctorStats.totalFrames++;

        const detection = await detectFaces(videoEl, canvasEl);
        const facesDetected = detection.count;
        // The pixel heuristic is unreliable (lighting, skin tone), so it needs a longer streak before a strike
        const noFaceStrikeFrames = detection.source === 'heuristic' ? 6 : 3;

        // Compare against the student's own calibrated position, not the frame centre,
        // so sitting slightly off-centre (common with laptop cameras) isn't flagged as looking away
        let gazeDeviated = false;
        if (facesDetected === 1 && detection.centerX !== null) {
            const baselineX = (calibrationData.center && calibrationData.center.face_center_x !== null && calibrationData.center.face_center_x !== undefined)
                ? calibrationData.center.face_center_x
                : 0.5;
            gazeDeviated = Math.abs(detection.centerX - baselineX) > gazeDeviationThreshold;
        }

        // 4. Evaluate Detection Results & Fire Strike Warnings
        if (facesDetected >= 1) {
            proctorStats.validFaceFrames++;
            proctorStats.consecutiveNoFace = 0;

            if (facesDetected > 1) {
                proctorStats.consecutiveMultiFace++;
                if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-users-slash"></i> Multi-Face (${facesDetected})</span>`;
                
                if (proctorStats.consecutiveMultiFace >= 2 && (Date.now() - lastMultiFaceWarningTime) > 7000) {
                    lastMultiFaceWarningTime = Date.now();
                    triggerWarning(`Multiple faces (${facesDetected}) detected in camera stream. Assessment must be taken alone.`, 'MULTI_FACE');
                }
            } else {
                proctorStats.consecutiveMultiFace = 0;

                if (gazeDeviated) {
                    proctorStats.consecutiveGazeDev++;
                    if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#f59e0b;"><i class="fas fa-eye-slash"></i> Looking Away</span>`;
                    if (proctorStats.consecutiveGazeDev >= 3 && (Date.now() - lastGazeWarningTime) > 7000) {
                        lastGazeWarningTime = Date.now();
                        triggerWarning('Gaze deviation detected. Please look directly at your screen.', 'GAZE_DEVIATION');
                    }
                } else {
                    proctorStats.consecutiveGazeDev = 0;
                    if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#10b981;"><i class="fas fa-user-check"></i> Face In Frame</span>`;
                }
            }

        } else {
            // facesDetected === 0 -> NO FACE!
            proctorStats.consecutiveNoFace++;
            proctorStats.consecutiveMultiFace = 0;
            proctorStats.consecutiveGazeDev = 0;

            if (statusLabelEl) statusLabelEl.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-user-slash"></i> NO FACE DETECTED</span>`;

            if (proctorStats.consecutiveNoFace >= noFaceStrikeFrames && (Date.now() - lastNoFaceWarningTime) > 7000) {
                lastNoFaceWarningTime = Date.now();
                triggerWarning('No face detected in camera stream. Candidate must remain visible throughout the test.', 'NO_FACE');
            }
        }

        const liveScore = Math.min(100, Math.max(0, Math.round((proctorStats.validFaceFrames / proctorStats.totalFrames) * 100)));
        if (statusWidgetEl) {
            statusWidgetEl.textContent = liveScore + '%';
            statusWidgetEl.style.color = liveScore >= 80 ? '#10b981' : (liveScore >= 60 ? '#f59e0b' : '#ef4444');
        }
    }

    let snapshotCanvas = null;

    async function logProctoringEvent(eventType, duration = 0, confidence = 1.0, severity = 'LOW', metadata = {}) {
        try {
            let snapshot = null;
            const videoEl = document.getElementById('proctorWebcamVideo');
            // Separate canvas: resizing the 320x240 analysis canvas to screen size (5K px on Retina Macs)
            // made every later face-check frame huge and slow
            const canvasEl = snapshotCanvas || (snapshotCanvas = document.createElement('canvas'));
            const isTabOrScreenEvent = ['TAB_SWITCH', 'WINDOW_BLUR', 'FULLSCREEN_EXIT', 'SCREEN_SHARE_STOPPED'].includes(eventType);

            if (isTabOrScreenEvent && screenVideoEl && screenVideoEl.videoWidth > 0 && canvasEl) {
                const screenScale = Math.min(1, 1600 / (screenVideoEl.videoWidth || 1280));
                const sw = Math.round((screenVideoEl.videoWidth || 1280) * screenScale);
                const sh = Math.round((screenVideoEl.videoHeight || 720) * screenScale);
                canvasEl.width = sw;
                canvasEl.height = sh;
                const ctx = canvasEl.getContext('2d');
                ctx.drawImage(screenVideoEl, 0, 0, sw, sh);

                if (videoEl && videoEl.videoWidth > 0) {
                    const pipW = Math.min(320, Math.floor(sw * 0.25));
                    const pipH = Math.floor(pipW * ((videoEl.videoHeight || 480) / (videoEl.videoWidth || 640)));
                    const pipX = sw - pipW - 16;
                    const pipY = sh - pipH - 16;
                    ctx.fillStyle = '#000000';
                    ctx.fillRect(pipX - 3, pipY - 3, pipW + 6, pipH + 6);
                    ctx.drawImage(videoEl, pipX, pipY, pipW, pipH);

                    ctx.fillStyle = '#ef4444';
                    ctx.font = 'bold 13px sans-serif';
                    ctx.fillText('🔴 CAM + SCREEN EVIDENCE', pipX + 8, pipY + 20);
                }
                snapshot = canvasEl.toDataURL('image/jpeg', 0.82);
            } else if (videoEl && canvasEl && videoEl.videoWidth > 0) {
                canvasEl.width = videoEl.videoWidth || 640;
                canvasEl.height = videoEl.videoHeight || 480;
                const ctx = canvasEl.getContext('2d');
                ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
                snapshot = canvasEl.toDataURL('image/jpeg', 0.85);
            }

            await fetch('skill_verification_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({
                    action: 'log_proctoring_event',
                    portfolio_id: portfolioId,
                    event_type: eventType,
                    duration: duration,
                    confidence: confidence,
                    severity: severity,
                    metadata: metadata,
                    snapshot: snapshot
                })
            });
        } catch (e) {}
    }

    let warningCount = 0;
    let lastNoFaceWarningTime = 0;
    let lastMultiFaceWarningTime = 0;
    let lastGazeWarningTime = 0;

    let lastStrikeTime = 0;

    function triggerWarning(reason, eventType = 'SECURITY_VIOLATION') {
        if (!isSessionActive) return;
        // One action (e.g. Cmd+Tab) fires blur, visibilitychange and fullscreenchange together — count it once
        if (Date.now() - lastStrikeTime < 3000) return;
        lastStrikeTime = Date.now();
        warningCount++;
        logProctoringEvent(eventType, 0, 1.0, 'HIGH', { strike_count: warningCount, reason: reason });

        const badgeNavText = document.getElementById('warningCountNavText');
        const badgeNav = document.getElementById('warningBadgeNav');
        if (badgeNavText) badgeNavText.textContent = warningCount;
        if (badgeNav) {
            if (warningCount === 1) badgeNav.style.background = 'rgba(245, 158, 11, 0.3)';
            else if (warningCount >= 2) badgeNav.style.background = 'rgba(239, 68, 68, 0.4)';
        }

        const iconEl = document.getElementById('warningIcon');
        const titleEl = document.getElementById('warningTitle');
        const msgEl = document.getElementById('warningMessage');
        const btnEl = document.getElementById('warningBtn');
        const overlay = document.getElementById('warningOverlay');

        if (warningCount === 1) {
            if (iconEl) iconEl.className = 'fas fa-exclamation-triangle';
            if (iconEl) iconEl.style.color = '#f59e0b';
            if (titleEl) titleEl.textContent = 'SECURITY VIOLATION — WARNING 1 OF 3';
            if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br>You have exited full screen or interrupted proctoring.<br>You have <strong>2 chances remaining</strong> before your test is automatically submitted.`;
            if (btnEl) {
                btnEl.textContent = 'RESUME ASSESSMENT';
                btnEl.onclick = resumeFullscreen;
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = 'var(--primary-maroon)';
            }
            if (overlay) overlay.classList.remove('hidden');
        } else if (warningCount === 2) {
            if (iconEl) iconEl.className = 'fas fa-exclamation-circle';
            if (iconEl) iconEl.style.color = '#f97316';
            if (titleEl) titleEl.textContent = 'CRITICAL SECURITY WARNING — WARNING 2 OF 3';
            if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br><span style="color: #f97316; font-weight: 700;">FINAL CHANCE REMAINING!</span> You have 1 chance remaining. One more violation will instantly auto-submit your assessment.`;
            if (btnEl) {
                btnEl.textContent = 'RESUME ASSESSMENT';
                btnEl.onclick = resumeFullscreen;
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = 'var(--primary-maroon)';
            }
            if (overlay) overlay.classList.remove('hidden');
        } else {
            // Count 3 / Auto-submit
            if (iconEl) iconEl.className = 'fas fa-ban';
            if (iconEl) iconEl.style.color = '#ef4444';
            if (titleEl) titleEl.textContent = 'MAXIMUM VIOLATIONS EXCEEDED — COUNT 3 OF 3';
            if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br><span style="color: #ef4444; font-weight: 700;">Maximum allowed integrity violations (3/3) reached. Your assessment is being automatically submitted now.</span>`;
            if (btnEl) {
                btnEl.innerHTML = '<i class="fas fa-arrow-left"></i> RETURN TO DASHBOARD';
                btnEl.onclick = () => { window.location.href = 'dashboard'; };
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = '#ef4444';
            }
            if (overlay) overlay.classList.remove('hidden');

            setTimeout(() => {
                submitQuiz(true, 'violation');
            }, 1500);
        }
    }

    // Monitor screen track ending
    const setupScreenTrackListener = (screenTrack) => {
        screenTrack.onended = () => {
            triggerWarning('Screen sharing was stopped by the user.', 'SCREEN_SHARE_STOPPED');
        };
    };

    // Safari < 16.4 only has the webkit-prefixed Fullscreen API
    function getFullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || null;
    }

    // The fullscreen transition on macOS can briefly blur the window; don't count that as a violation
    let fullscreenGraceUntil = 0;

    function requestFullscreenCompat() {
        fullscreenGraceUntil = Date.now() + 1500;
        const el = document.documentElement;
        if (el.requestFullscreen) return el.requestFullscreen();
        if (el.webkitRequestFullscreen) {
            el.webkitRequestFullscreen();
            return Promise.resolve();
        }
        return Promise.reject(new Error('Fullscreen API not supported'));
    }

    function showFullscreenPrompt() {
        const iconEl = document.getElementById('warningIcon');
        const titleEl = document.getElementById('warningTitle');
        const msgEl = document.getElementById('warningMessage');
        const btnEl = document.getElementById('warningBtn');
        if (iconEl) { iconEl.className = 'fas fa-expand'; iconEl.style.color = '#f59e0b'; }
        if (titleEl) titleEl.textContent = 'Full Screen Required';
        if (msgEl) msgEl.innerHTML = 'Click the button below to enter full screen and begin your assessment. This is not counted as a warning.';
        if (btnEl) {
            btnEl.textContent = 'ENTER FULL SCREEN';
            btnEl.onclick = resumeFullscreen;
            btnEl.style.display = 'inline-flex';
            btnEl.style.background = 'var(--primary-maroon)';
        }
        document.getElementById('warningOverlay').classList.remove('hidden');
    }

    function resumeFullscreen() {
        requestFullscreenCompat().then(() => {
            document.getElementById('warningOverlay').classList.add('hidden');
        }).catch(e => {
            // alert() would blur the window and cause another strike, so show the hint inline
            const msgEl = document.getElementById('warningMessage');
            if (msgEl && !msgEl.querySelector('.fs-blocked-hint')) {
                msgEl.innerHTML += '<br><br><span class="fs-blocked-hint" style="color:#f59e0b;">Your browser blocked full screen. Please click the button again, and allow full screen if your browser asks.</span>';
            }
        });
    }

    function onFullscreenChange() {
        if (!getFullscreenElement() && isSessionActive) {
            triggerWarning('Full screen mode was deactivated.', 'FULLSCREEN_EXIT');
        }
    }
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && isSessionActive && Date.now() > fullscreenGraceUntil) {
            setTimeout(() => {
                triggerWarning('Tab or application switch detected via Taskbar.', 'TAB_SWITCH');
            }, 120);
        }
    });

    window.addEventListener('blur', () => {
        if (isSessionActive && Date.now() > fullscreenGraceUntil) {
            setTimeout(() => {
                triggerWarning('Window focus lost. Candidate clicked outside test window or opened taskbar app.', 'WINDOW_BLUR');
            }, 120);
        }
    });


    async function enterFullscreen() {
        await requestFullscreenCompat().catch((e) => console.log(e));
    }

    let questions = [];
    let userAnswers = [];
    let currentIdx = 0;
    let timerInterval;
    let timeRemaining = 600;

    async function startQuiz() {
        try {
            const res = await fetch('skill_verification_handler.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: `action=generate_quiz&portfolio_id=${portfolioId}&csrf_token=${CSRF_TOKEN}`
            });
            
            const responseText = await res.text();
            let data = JSON.parse(responseText);

            if (data.success) {
                questions = data.questions;
                userAnswers = new Array(questions.length).fill(null);
                renderQuestions();
                initQuiz();
            } else {
                isSessionActive = false; // no strikes while the error dialog is shown
                await LakshyaDialog.alert(data.message || 'Could not generate the quiz.', { type: 'error', title: 'Error', okText: 'Return to Dashboard' });
                window.location.href = 'dashboard';
            }
        } catch (err) {
            console.error(err);
            isSessionActive = false;
            await LakshyaDialog.alert('Connection error. Failed to start quiz.', { type: 'error', title: 'Connection Error', okText: 'Return to Dashboard' });
            window.location.href = 'dashboard';
        }
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
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
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div class="question-text" style="margin-bottom:0; flex: 1;">${escapeHtml(q.question)}</div>
                </div>
                <div class="options-grid">${optionsHTML}</div>
            `;
            container.appendChild(div);
        });
    }

    function selectOption(qIdx, oIdx) {
        userAnswers[qIdx] = oIdx;
        const options = document.querySelectorAll(`#qCard_${qIdx} .option-btn`);
        options.forEach((btn, idx) => {
            if (idx === oIdx) btn.classList.add('selected');
            else btn.classList.remove('selected');
        });

        if (qIdx < questions.length - 1) {
            // Only auto-advance if the student is still on this question (a double-click or a
            // manual "Next" within 400ms would otherwise skip a question)
            setTimeout(() => { if (currentIdx === qIdx) nextQuestion(); }, 400);
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

    let isSubmittingQuiz = false;

    // reason: 'violation' (3 strikes) or 'time' (timer expired); only violations are reported as auto_submitted,
    // because the handler marks auto_submitted attempts as "Maximum Security Violations Exceeded"
    async function submitQuiz(isAuto = false, reason = 'violation') {
        if (isSubmittingQuiz) return;
        if (!isAuto) {
            const ok = await LakshyaDialog.confirm('Submit your answers for verification?', { title: 'Submit Quiz', okText: 'Submit', cancelText: 'Keep Working' });
            // The timer or a strike may have auto-submitted while the dialog was open
            if (!ok || isSubmittingQuiz) return;
        }
        isSubmittingQuiz = true;

        isSessionActive = false;
        clearInterval(timerInterval);
        clearInterval(proctorInterval);

        // Hide floating proctor widget
        const widget = document.getElementById('proctorWidget');
        if (widget) widget.style.display = 'none';

        // Stop media tracks
        if (webcamStream) webcamStream.getTracks().forEach(t => t.stop());
        if (screenStream) screenStream.getTracks().forEach(t => t.stop());

        document.getElementById('quizView').style.display = 'none';
        document.getElementById('loadingView').style.display = 'flex';
        document.getElementById('loadingText').innerText = !isAuto ? 'Verifying Answers & Integrity Log...'
            : (reason === 'time' ? "Time's up! Submitting your answers..." : 'Auto-submitting due to security violations...');

        try {
            const formData = new URLSearchParams();
            formData.append('action', 'submit_quiz');
            formData.append('portfolio_id', portfolioId);
            formData.append('csrf_token', CSRF_TOKEN);
            if (isAuto && reason === 'violation') {
                formData.append('auto_submitted', '1');
            }
            formData.append('strike_count', warningCount);

            const clientFacePct = proctorStats.totalFrames > 0 
                ? Math.round((proctorStats.validFaceFrames / proctorStats.totalFrames) * 100)
                : 100;
            formData.append('client_face_presence_pct', clientFacePct);

            userAnswers.forEach((ans, i) => formData.append(`answers[${i}]`, ans !== null ? ans : -1));

            const res = await fetch('skill_verification_handler.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                showResults(data);
            } else {
                await LakshyaDialog.alert(data.message || 'Submission failed.', { type: 'error', title: 'Submission Failed', okText: 'Return to Dashboard' });
                window.location.href = 'dashboard';
            }
        } catch (err) {
            console.error(err);
            await LakshyaDialog.alert('Connection error during submission.', { type: 'error', title: 'Connection Error', okText: 'Return to Dashboard' });
            window.location.href = 'dashboard';
        }
    }

    function showResults(data) {
        document.getElementById('warningOverlay').classList.add('hidden');
        document.getElementById('loadingView').style.display = 'none';
        document.getElementById('quizView').style.display = 'none';
        
        const resultsView = document.getElementById('resultsView');
        if (resultsView) resultsView.style.display = 'block';

        const score = Math.round(data.score ?? 0);
        const correctCount = data.correct_count ?? 0;
        const totalCount = data.total_count ?? (questions?.length || 10);

        const resultsScoreEl = document.getElementById('resultsScore');
        if (resultsScoreEl) {
            resultsScoreEl.innerText = `${score}% (${correctCount}/${totalCount} Correct)`;
        }
        
        const passed = Boolean(data.passed);
        const icon = document.getElementById('congratsIcon');
        const title = document.getElementById('resultsTitle');
        const msg = document.getElementById('resultsMessage');

        if (passed) {
            if (icon) {
                icon.style.color = '#10b981';
                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            }
            if (title) {
                title.innerText = 'Skill Verified Successfully!';
                title.style.color = '#10b981';
            }
            if (msg) {
                msg.innerHTML = `Congratulations! You scored <strong>${score}%</strong> (${correctCount}/${totalCount} correct answers). Your proficiency has been <strong>officially verified</strong> and recorded in your portfolio.`;
            }
        } else {
            if (icon) {
                icon.style.color = '#ef4444';
                icon.innerHTML = '<i class="fas fa-times-circle"></i>';
            }
            if (title) {
                title.innerText = 'Skill Verification Not Achieved';
                title.style.color = '#ef4444';
            }
            if (msg) {
                msg.innerHTML = `You scored <strong>${score}%</strong> (${correctCount}/${totalCount} correct). A minimum score of <strong>70%</strong> is required to verify this skill. You may retake the assessment after preparation.`;
            }
        }

        // Render Integrity Report Details
        if (data.integrity_report) {
            const rpt = data.integrity_report;
            if (document.getElementById('rptScreenShare')) document.getElementById('rptScreenShare').innerText = (rpt.screen_sharing_active_pct || 100) + '%';
            if (document.getElementById('rptCameraAvail')) document.getElementById('rptCameraAvail').innerText = (rpt.camera_availability_pct || 100) + '%';
            if (document.getElementById('rptFacePres')) document.getElementById('rptFacePres').innerText = (rpt.face_presence_pct || 100) + '%';
            if (document.getElementById('rptGazeConf')) document.getElementById('rptGazeConf').innerText = (rpt.gaze_confidence_pct || 92) + '%';
            if (document.getElementById('rptAttnDev')) document.getElementById('rptAttnDev').innerText = rpt.attention_deviations || 0;
            if (document.getElementById('rptLongestDev')) document.getElementById('rptLongestDev').innerText = (rpt.longest_deviation_sec || 0) + 's';
            if (document.getElementById('rptScreenInt')) document.getElementById('rptScreenInt').innerText = rpt.screen_interruptions || 0;
            if (document.getElementById('rptMultiFace')) document.getElementById('rptMultiFace').innerText = rpt.multiple_faces_count || 0;

            const pill = document.getElementById('rptStatusPill');
            if (pill) {
                pill.innerText = 'Integrity Status: ' + (rpt.integrity_status || (passed ? 'Verified' : 'Completed'));
                if (rpt.auto_submitted || rpt.strike_count >= 3 || rpt.screen_interruptions > 0) {
                    pill.className = 'integrity-pill warning';
                } else {
                    pill.className = 'integrity-pill success';
                }
            }
        }

        // Render Review List
        const reviewList = document.getElementById('reviewList');
        if (reviewList && data.results) {
            reviewList.innerHTML = '';
            data.results.forEach((r, idx) => {
                const div = document.createElement('div');
                div.style.marginBottom = '20px';
                div.style.padding = '16px 20px';
                div.style.borderRadius = '14px';
                div.style.background = r.is_correct ? '#f0fdf4' : '#fef2f2';
                div.style.border = `1.5px solid ${r.is_correct ? '#86efac' : '#fca5a5'}`;
                div.style.textAlign = 'left';
                
                div.innerHTML = `
                    <div style="font-weight:700; margin-bottom: 8px; font-size: 1rem; color: #1e293b;">Question ${idx+1}: ${escapeHtml(r.question)}</div>
                    <div style="font-size:0.95rem; margin-bottom: 4px;">
                        <strong>Your Answer:</strong> <span style="font-weight:600; color: ${r.is_correct ? '#16a34a' : '#dc2626'};">${r.user_answer !== -1 ? 'Option ' + String.fromCharCode(65 + r.user_answer) : 'Unanswered'}</span> ${r.is_correct ? '<i class="fas fa-check-circle" style="color:#16a34a;"></i> Correct' : '<i class="fas fa-times-circle" style="color:#dc2626;"></i> Incorrect'}
                    </div>
                    ${!r.is_correct ? `<div style="font-size:0.95rem; margin-bottom: 6px; color: #15803d;"><strong>Correct Answer:</strong> Option ${String.fromCharCode(65 + r.correct_answer)}</div>` : ''}
                    <div class="explanation" style="margin-top: 10px; background: rgba(255,255,255,0.8); border-left: 4px solid var(--primary-maroon); padding: 10px 14px; border-radius: 8px;"><strong>Explanation:</strong> ${escapeHtml(r.explanation || 'N/A')}</div>
                `;
                reviewList.appendChild(div);
            });
        }
    }

    function toggleReview() {
        const container = document.getElementById('reviewContainer');
        container.style.display = container.style.display === 'none' ? 'block' : 'none';
        if (container.style.display === 'block') {
            container.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        const timeEl = document.getElementById('timeRemaining');
        if (timeEl) {
            const mins = Math.floor(timeRemaining / 60);
            const secs = timeRemaining % 60;
            timeEl.innerText = `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        timerInterval = setInterval(() => {
            timeRemaining--;
            if (timeEl) {
                const mins = Math.floor(timeRemaining / 60);
                const secs = timeRemaining % 60;
                timeEl.innerText = `${mins}:${secs.toString().padStart(2, '0')}`;
            }
            
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                submitQuiz(true, 'time');
            }
        }, 1000);
    }

    function initQuiz() {
        document.getElementById('loadingView').style.display = 'none';
        document.getElementById('quizView').style.display = 'block';
        updateNav();
        startTimer();
    }
</script>

</body>
</html>
