<?php
/**
 * AI Project Viva (Defense) — Dual-Stream Proctoring & Integrity System
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
$portfolioId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($filters['id'] ?? 0);

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
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Defense: <?php echo htmlspecialchars($projectTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="../js/lakshya_dialogs.js?v=<?php echo APP_VERSION; ?>"></script>
    <!-- KaTeX for equation rendering -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
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

        .results-card { text-align: center; }

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

        /* Calibration Target */
        .calib-target {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #ef4444;
            margin: 20px auto;
            box-shadow: 0 0 25px #ef4444;
            animation: pulse-ring 1.5s infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }

        /* Floating Proctor Widget */
        .proctor-widget {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 170px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            z-index: 999;
            backdrop-filter: blur(10px);
            cursor: grab;
            user-select: none;
            -webkit-user-select: none;
            touch-action: none;
        }
        .proctor-widget.is-dragging {
            cursor: grabbing !important;
            opacity: 0.88;
            box-shadow: 0 24px 48px rgba(0,0,0,0.85);
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
            background: rgba(2, 6, 23, 0.8);
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

        /* Integrity Card */
        .integrity-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        .integrity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        .integrity-metric {
            background: rgba(0,0,0,0.3);
            padding: 12px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        .integrity-metric .label { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; }
        .integrity-metric .val { font-size: 1rem; font-weight: 700; color: #fff; }
        .integrity-pill {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .integrity-pill.success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .integrity-pill.warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }

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

<!-- 3-Step Setup Intro Overlay -->
<div id="introOverlay" class="overlay">
    <div style="text-align: center; max-width: 620px; width: 90%; padding: 2.5rem; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(30px); border: 1px solid var(--glass-border); border-radius: 40px; box-shadow: 0 40px 100px rgba(0,0,0,0.8);">
        
        <div style="width: 70px; height: 70px; background: rgba(128, 0, 0, 0.15); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; border: 1px solid var(--primary-maroon);">
            <i class="fas fa-shield-alt" style="font-size: 2.2rem; color: var(--primary-maroon);"></i>
        </div>
        <h1 style="color: #fff; margin-bottom: 0.5rem; font-size: 1.8rem;">Project Defense Verification</h1>
        <h2 style="color: var(--accent-gold); margin-bottom: 1.5rem; font-size: 1.1rem;"><?php echo htmlspecialchars($projectTitle); ?></h2>

        <!-- Step Indicator -->
        <div style="display: flex; justify-content: space-around; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px; font-size: 0.85rem; font-weight: 700;">
            <span id="stepTab1" style="color: var(--primary-maroon);"><i class="fas fa-video"></i> 1. Camera</span>
            <span id="stepTab2" style="color: #64748b;"><i class="fas fa-crosshairs"></i> 2. Calibration</span>
            <span id="stepTab3" style="color: #64748b;"><i class="fas fa-desktop"></i> 3. Screen Share</span>
        </div>

        <!-- Step 1 View: Camera Setup -->
        <div id="stepView1">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">
                Enable your webcam feed to establish active identity monitoring during the defense session.
            </p>
            <video id="setupWebcamPreview" autoplay muted playsinline style="width: 220px; height: 150px; border-radius: 16px; background: #000; border: 2px solid var(--glass-border); margin: 0 auto 1.2rem; object-fit: cover; display: block; transform: scaleX(-1);"></video>
            <div id="setupCheckStatus" style="font-size: 0.85rem; font-weight: 700; color: #f59e0b; margin-bottom: 1.5rem;">
                Click the button below to enable your camera.
            </div>
            <button id="btnGrantCamera" onclick="initCameraSetup()" class="btn-action">
                Enable Camera & Continue <i class="fas fa-arrow-right"></i>
            </button>
        </div>

        <!-- Step 2 View: Baseline Calibration -->
        <div id="stepView2" class="hidden">
            <h3 style="font-size: 1.1rem; color: #fff; margin-bottom: 8px;">Gaze Baseline Calibration</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;" id="calibPromptText">
                Look directly at the center target below.
            </p>
            <video id="calibWebcamPreview" autoplay muted playsinline style="width: 220px; height: 150px; border-radius: 16px; background: #000; border: 2px solid var(--glass-border); margin: 0 auto 0.8rem; object-fit: cover; display: block; transform: scaleX(-1);"></video>
            <div class="calib-target" id="calibTarget"></div>
            <div id="calibProgressText" style="font-size: 0.9rem; font-weight: 700; color: var(--accent-gold); margin-bottom: 1.5rem;">
                Progress: Center (0/3s)
            </div>
            <button id="btnRetryCalib" onclick="startCalibrationFlow()" class="btn-action hidden">
                <i class="fas fa-redo"></i> Retry Calibration
            </button>
        </div>

        <!-- Step 3 View: Screen Share Setup -->
        <div id="stepView3" class="hidden">
            <h3 style="font-size: 1.1rem; color: #fff; margin-bottom: 8px;">Screen Share Verification</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">
                Select your display screen to enable screen integrity verification during your defense.
            </p>
            <div id="screenCheckStatus" style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted); margin-bottom: 1.5rem;">
                Screen capture stream ready to initialize.
            </div>
            <button onclick="initScreenShare()" class="btn-action">
                Share Screen & Begin Defense <i class="fas fa-arrow-right"></i>
            </button>
        </div>

    </div>
</div>

<div class="navbar">
    <h1><i class="fas fa-shield-alt"></i> <span>SECURE DEFENSE ENGINE</span></h1>
    <div style="display: flex; align-items: center; gap: 15px;">
        <span id="warningBadgeNav" style="background: rgba(255,255,255,0.1); padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
            <i class="fas fa-shield-alt" style="color: #10b981;"></i> Warnings: <span id="warningCountNavText">0</span> / 3
        </span>
        <a href="dashboard" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; transition: 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-muted)'">
            <i class="fas fa-times-circle"></i> EXIT DEFENSE
        </a>
    </div>
</div>

<div class="container" id="vivaContainer">
    <!-- Initial Loading -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner-outer"><div class="spinner-inner"></div></div>
        <h2 id="loadingText" style="margin-top: 2rem; font-weight: 500; letter-spacing: 1px;">PREPARING ANALYTICAL SUITE...</h2>
        <p style="color: var(--text-muted); margin-top: 10px; font-size: 0.9rem;">Connecting to Technical Evaluator AI</p>
    </div>

    <!-- Interface -->
    <div id="vivaView" style="display: none;">
        <div class="viva-header">
            <p style="color: var(--accent-gold); font-size: 0.9rem; font-weight: 600; letter-spacing: 3px; margin-bottom: 10px;">DEFENSE IN PROGRESS</p>
            <h3><?php echo htmlspecialchars($projectTitle); ?></h3>
        </div>

        <div class="progress-steps" id="progressSteps">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <div class="question-card">
            <div class="question-label" id="stepCounterLabel">DEFENSE QUESTION 1/5</div>
            <div id="questionText" class="question-text">Initializing parameters...</div>
        </div>

        <textarea id="userAnswer" class="answer-area" placeholder="Enter your technical project explanation..."></textarea>

        <button id="btnSubmit" class="btn-action" onclick="submitAnswer()">
            CONFIRM & NEXT <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <!-- Results View -->
    <div id="resultsView" class="results-card" style="display: none;">
        <div id="successRing" style="font-size: 5rem; color: #10b981; margin-bottom: 2rem;"><i class="fas fa-award"></i></div>
        <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">Defense Evaluation Finalized</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Analysis generated by AI Evaluation Engine</p>

        <div class="score-circle">
            <div class="score-val" id="finalScoreVal">0</div>
            <div class="score-label">DEFENSE SCORE</div>
        </div>

        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.1rem; font-weight: 600; color: #fff; margin-bottom: 10px;">
                STATUS: <span id="finalStatusText" style="color: #10b981;">VERIFIED</span>
            </div>
            <div class="feedback-box" id="finalFeedback">Loading evaluation results...</div>
        </div>

        <!-- Integrity Report Card -->
        <div class="integrity-card" id="integrityReportCard">
            <h3 style="font-size: 1rem; color: #fff; font-weight: 700; margin-bottom: 12px;">
                <i class="fas fa-shield-alt" style="color: #10b981; margin-right: 6px;"></i> Defense Assessment Integrity Audit
            </h3>
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
                    <div class="label">Screen Interruptions</div>
                    <div class="val" id="rptScreenInt">0</div>
                </div>
                <div class="integrity-metric">
                    <div class="label">Multi-Face</div>
                    <div class="val" id="rptMultiFace">0</div>
                </div>
            </div>
        </div>

        <a href="dashboard" class="btn-action" style="text-decoration: none; width: auto; margin: 0 auto; display: inline-flex; padding: 1.2rem 3rem; background: var(--glass); border: 1px solid var(--glass-border);">
            RETURN TO DASHBOARD
        </a>
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
<div id="warningOverlay" class="overlay hidden" style="background: rgba(2, 6, 23, 0.98); z-index: 2000;">
    <div style="text-align: center; max-width: 500px; padding: 3rem; border: 1px solid var(--primary-maroon); background: #000; border-radius: 30px; box-shadow: 0 0 50px rgba(128, 0, 0, 0.3);">
        <i id="warningIcon" class="fas fa-exclamation-triangle" style="color: var(--primary-maroon); font-size: 4rem; margin-bottom: 1.5rem;"></i>
        <h2 id="warningTitle" style="margin-bottom: 1rem; color: #fff;">Protocol Breach</h2>
        <p id="warningMessage" style="color: var(--text-muted); margin-bottom: 2.5rem; line-height: 1.6;">
            Fullscreen mode has been deactivated. This assessment requires an isolated environment to maintain academic integrity.
        </p>
        <button id="warningBtn" onclick="resumeFullscreen()" class="btn-action" style="width: 100%;">RESUME DEFENSE</button>
    </div>
</div>

<!-- Canvas for frame analysis -->
<canvas id="proctorAnalysisCanvas" width="320" height="240" style="display: none;"></canvas>

<script>
    let questions = [];
    let answers = [];
    let currentIdx = 0;
    let isSessionActive = false;
    const portfolioId = <?php echo $portfolioId; ?>;
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    let isFinalizing = false; // last answer + 3rd-strike auto-submit must not both submit

    // --- PROCTORING ENGINE STATE ---
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
                    console.log(`[Lakshya AI] MediaPipe BlazeFace AI Detector initialized (${delegate}).`);
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
    // Pre-warm MediaPipe detector on page load
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

    function renderMath(element) {
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(element, {
                delimiters: [
                    {left: "$$", right: "$$", display: true},
                    {left: "$", right: "$", display: false},
                    {left: "\\(", right: "\\)", display: false},
                    {left: "\\[", right: "\\]", display: true}
                ],
                throwOnError: false
            });
        } else {
            setTimeout(() => renderMath(element), 100);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyStrictSecurity();
    });

    function applyStrictSecurity() {
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
    }

    // --- STEP 1: CAMERA SETUP ---
    let cameraSetupTimer = null;

    async function initCameraSetup() {
        const statusEl = document.getElementById('setupCheckStatus');
        const videoEl = document.getElementById('setupWebcamPreview');
        const btnEl = document.getElementById('btnGrantCamera');

        if (cameraSetupTimer) clearTimeout(cameraSetupTimer);

        try {
            statusEl.textContent = 'Requesting camera permission…';
            
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

            videoTrack.onended = () => handleCameraStreamLost();
            // macOS mutes the track for a moment while the FaceTime camera warms up or switches
            // (Continuity Camera, video effects). That is not a lost camera — only 'ended' is fatal.
            videoTrack.onmute = () => console.warn("[Lakshya AI] Camera track temporarily muted.");

            let countdown = 5;
            let waitedForFrames = 0;
            const updateCountdown = () => {
                const track = webcamStream ? webcamStream.getVideoTracks()[0] : null;
                if (!track || track.readyState !== 'live') {
                    handleCameraStreamLost();
                    return;
                }

                if (videoEl.paused) {
                    videoEl.play().catch(e => {});
                }

                // Don't start the countdown until the preview is actually showing a picture
                if (videoEl.videoWidth === 0 || track.muted) {
                    waitedForFrames++;
                    statusEl.style.color = '#f59e0b';
                    statusEl.innerHTML = waitedForFrames < 10
                        ? '<i class="fas fa-spinner fa-spin"></i> Waiting for camera picture…'
                        : '<i class="fas fa-exclamation-triangle"></i> Camera opened but no picture is coming through.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Close other apps using the camera (FaceTime, Zoom, Teams). On a Mac, check System Settings → Privacy &amp; Security → Camera and make sure your browser is allowed.</span>';
                    if (waitedForFrames === 10 && btnEl) {
                        btnEl.innerHTML = '<i class="fas fa-redo"></i> Retry Camera Setup';
                        btnEl.style.display = 'inline-flex';
                    }
                    cameraSetupTimer = setTimeout(updateCountdown, 500);
                    return;
                }
                statusEl.style.color = '#10b981';
                if (btnEl) btnEl.style.display = 'none';

                if (countdown > 0) {
                    statusEl.innerHTML = `<i class="fas fa-check-circle"></i> Camera Active! Position yourself comfortably.<br><span style="color:var(--accent-gold); font-size: 0.85rem; font-weight: 700;">Calibration starting in ${countdown}s…</span>`;
                    countdown--;
                    cameraSetupTimer = setTimeout(updateCountdown, 1000);
                } else {
                    document.getElementById('stepView1').classList.add('hidden');
                    document.getElementById('stepView2').classList.remove('hidden');
                    document.getElementById('stepTab1').style.color = '#64748b';
                    document.getElementById('stepTab2').style.color = 'var(--primary-maroon)';
                    startCalibrationFlow();
                }
            };

            updateCountdown();

        } catch (err) {
            console.error("Project Viva Camera Error:", err);
            handleCameraStreamLost(err);
        }
    }

    function handleCameraStreamLost(err = null) {
        if (cameraSetupTimer) clearTimeout(cameraSetupTimer);
        const statusEl = document.getElementById('setupCheckStatus');
        const btnEl = document.getElementById('btnGrantCamera');
        
        let errMsg = '<i class="fas fa-times-circle"></i> Camera stream lost or permission denied.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Check webcam connection and click Retry below.</span>';

        if (err) {
            if (err.message === 'INSECURE_CONTEXT') {
                errMsg = '<i class="fas fa-times-circle"></i> Camera blocked: Insecure Context.<br><span style="font-size:0.8rem; font-weight:600; color:#ef4444;">Browsers require HTTPS or localhost for camera access. Please use https:// or access via localhost.</span>';
            } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                errMsg = '<i class="fas fa-times-circle"></i> Camera permission was denied.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Click the 🔒 icon in your browser address bar, allow Camera access, and click Retry. On a Mac, also enable your browser under System Settings → Privacy &amp; Security → Camera, then restart the browser.</span>';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                errMsg = '<i class="fas fa-times-circle"></i> No camera device detected.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Please plug in a webcam and click Retry below.</span>';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                errMsg = '<i class="fas fa-times-circle"></i> Camera is currently in use.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Another app (Zoom, Teams, or another tab) is using the webcam. Please close it and click Retry.</span>';
            } else if (err.name === 'SecurityError') {
                errMsg = '<i class="fas fa-times-circle"></i> Camera access restricted by security policy.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Permissions policy or browser configuration is blocking camera access.</span>';
            } else if (err.message === 'MEDIA_NOT_SUPPORTED') {
                errMsg = '<i class="fas fa-times-circle"></i> This browser does not support camera access.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Please use the latest Chrome, Edge, Firefox or Safari.</span>';
            }
        }

        if (statusEl) {
            statusEl.style.color = '#ef4444';
            statusEl.innerHTML = errMsg;
        }
        if (btnEl) {
            btnEl.innerHTML = '<i class="fas fa-redo"></i> Retry Camera Setup';
            btnEl.style.display = 'inline-flex';
            btnEl.disabled = false;
        }
    }

    // --- STEP 2: GAZE CALIBRATION ---
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
            progEl.style.color = 'var(--accent-gold)';
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
                progEl.innerHTML = '<i class="fas fa-user-slash"></i> We can\'t see your face clearly.<br><span style="font-size:0.8rem; color:#94a3b8;">Sit facing the screen, keep your whole face inside the preview and make sure the room is well lit (avoid a bright window behind you).</span>';
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
        progEl.style.color = 'var(--accent-gold)';
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
            progEl.style.color = 'var(--accent-gold)';
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

        try {
            await fetch('project_viva_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ action: 'save_calibration', portfolio_id: portfolioId, calibration: calibrationData })
            });
        } catch (e) {}

        document.getElementById('stepView2').classList.add('hidden');
        document.getElementById('stepView3').classList.remove('hidden');
        document.getElementById('stepTab2').style.color = '#64748b';
        document.getElementById('stepTab3').style.color = 'var(--primary-maroon)';
    }

    // --- STEP 3: SCREEN SHARE INITIALIZATION ---
    async function initScreenShare() {
        const statusEl = document.getElementById('screenCheckStatus');

        try {
            statusEl.textContent = 'Prompting screen selection…';
            screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: { displaySurface: 'monitor', cursor: 'always' },
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

            screenTrack.onended = () => {
                triggerWarning('Screen sharing was stopped by the user.', 'SCREEN_SHARE_STOPPED');
            };

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
            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Screen share active!';


            setTimeout(() => {
                document.getElementById('introOverlay').classList.add('hidden');
                beginAssessmentExecution();
            }, 800);

        } catch (err) {
            console.error("Project Viva Screen Share Error:", err);
            statusEl.style.color = '#ef4444';
            if (err && err.name === 'NotAllowedError' && /system/i.test(err.message || '')) {
                // Chrome reports "Permission denied by system" when macOS Screen Recording access is off
                statusEl.innerHTML = '<i class="fas fa-times-circle"></i> Your computer blocked screen sharing.<br><span style="font-size:0.85rem; color:#94a3b8;">On a Mac: open System Settings → Privacy &amp; Security → Screen &amp; System Audio Recording, enable your browser, then fully quit and reopen the browser.</span>';
            } else if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                statusEl.innerHTML = '<i class="fas fa-times-circle"></i> This browser does not support screen sharing. Please use the latest Chrome, Edge, Firefox or Safari on a computer.';
            } else {
                statusEl.innerHTML = '<i class="fas fa-times-circle"></i> Screen share required for defense session. Select a screen to share.<br><span style="font-size:0.85rem; color:#94a3b8;">If the picker never appeared on a Mac, check System Settings → Privacy &amp; Security → Screen &amp; System Audio Recording.</span>';
            }
        }
    }

    async function beginAssessmentExecution() {
        await enterFullscreen();
        isSessionActive = true;
        // Keep the PHP session alive while answers are typed — an expired session would lose the submission
        if (!window._sessionKeepAlive) {
            window._sessionKeepAlive = setInterval(() => {
                if (!isSessionActive) return;
                fetch('project_viva_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ action: 'ping' })
                }).catch(() => {});
            }, 4 * 60 * 1000);
        }

        proctorStats = {
            totalFrames: 0,
            validFaceFrames: 0,
            consecutiveNoFace: 0,
            consecutiveMultiFace: 0,
            consecutiveGazeDev: 0,
            lastLoggedNoFaceTime: 0,
            lastLoggedGazeTime: 0
        };

        const setupVid = document.getElementById('setupWebcamPreview');
        if (setupVid) setupVid.srcObject = null;
        const calibVid = document.getElementById('calibWebcamPreview');
        if (calibVid) calibVid.srcObject = null;

        // Browsers (Safari especially) refuse fullscreen once the click that started screen share has expired.
        // Ask for one more click instead of silently running outside fullscreen — this is not a strike.
        if (!getFullscreenElement()) showFullscreenPrompt();

        const widget = document.getElementById('proctorWidget');
        const widgetVideo = document.getElementById('proctorWebcamVideo');
        if (widget && widgetVideo && webcamStream) {
            widget.style.display = 'block';
            widgetVideo.srcObject = webcamStream;
            enableDraggableWidget(widget);
            try { await widgetVideo.play(); } catch (e) {}
        }

        if (proctorInterval) clearInterval(proctorInterval);
        proctorInterval = setInterval(runProctoringCheckFrame, 1200);

        startProcessing();
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

    // --- PROCTORING FRAME MONITORING LOOP ---
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
                    triggerWarning(`Multiple faces (${facesDetected}) detected in camera stream. Defense must be conducted individually.`, 'MULTI_FACE');
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
                triggerWarning('No face detected in camera stream. Candidate must remain visible throughout the defense.', 'NO_FACE');
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

            await fetch('project_viva_handler.php', {
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

    // --- WARNING & AUTO-SUBMIT STRIKE SYSTEM ---
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
            if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br>You have exited full screen or interrupted proctoring.<br>You have <strong>2 chances remaining</strong> before your defense is automatically submitted.`;
            if (btnEl) {
                btnEl.textContent = 'RESUME DEFENSE';
                btnEl.onclick = resumeFullscreen;
                btnEl.style.display = 'inline-flex';
            }
            if (overlay) overlay.classList.remove('hidden');
        } else if (warningCount === 2) {
            if (iconEl) iconEl.className = 'fas fa-exclamation-circle';
            if (iconEl) iconEl.style.color = '#f97316';
            if (titleEl) titleEl.textContent = 'CRITICAL SECURITY WARNING — WARNING 2 OF 3';
            if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br><span style="color: #f97316; font-weight: 700;">FINAL CHANCE REMAINING!</span> You have 1 chance remaining. One more violation will instantly auto-submit your defense.`;
            if (btnEl) {
                btnEl.textContent = 'RESUME DEFENSE';
                btnEl.onclick = resumeFullscreen;
                btnEl.style.display = 'inline-flex';
            }
            if (overlay) overlay.classList.remove('hidden');
        } else {
            // Count 3 / Auto-submit
            if (iconEl) iconEl.className = 'fas fa-ban';
            if (iconEl) iconEl.style.color = '#ef4444';
            if (titleEl) titleEl.textContent = 'MAXIMUM VIOLATIONS EXCEEDED — COUNT 3 OF 3';
            if (msgEl) msgEl.innerHTML = `<strong>${escapeHtml(reason)}</strong><br><br><span style="color: #ef4444; font-weight: 700;">Maximum allowed integrity violations (3/3) reached. Your defense session is being automatically submitted now.</span>`;
            if (btnEl) {
                btnEl.innerHTML = '<i class="fas fa-arrow-left"></i> RETURN TO DASHBOARD';
                btnEl.onclick = () => { window.location.href = 'dashboard'; };
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = '#ef4444';
            }
            if (overlay) overlay.classList.remove('hidden');

            setTimeout(() => {
                finalizeAssessment(true);
            }, 1500);
        }
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

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
        if (iconEl) { iconEl.className = 'fas fa-expand'; iconEl.style.color = 'var(--accent-gold)'; }
        if (titleEl) titleEl.textContent = 'Full Screen Required';
        if (msgEl) msgEl.innerHTML = 'Click the button below to enter full screen and begin your defense. This is not counted as a warning.';
        if (btnEl) {
            btnEl.textContent = 'ENTER FULL SCREEN';
            btnEl.onclick = resumeFullscreen;
            btnEl.style.display = 'inline-flex';
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
        await requestFullscreenCompat().catch((e) => console.warn(e));
    }

    async function startProcessing() {
        try {
            const res = await fetch('project_viva_handler.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ action: 'generate_viva', portfolio_id: portfolioId })
            });
            const data = await res.json();
            if (!data.success) {
                handleException(data.message || "Failed to generate questions.");
                return;
            }

            if (data.direct && (data.questions || data.result)) {
                const qRaw = data.questions || data.result;
                const qList = Array.isArray(qRaw) ? qRaw : (qRaw.questions || qRaw.result || []);
                questions = qList;
                if (questions.length === 0) {
                    handleException("No questions generated. Please try again.");
                    return;
                }
                initializeViva();
            } else if (data.job_id) {
                pollJobStatus(data.job_id, (qData) => {
                    if (qData.success === false) {
                        handleException("AI Error: " + (qData.message || "Failed to generate questions."));
                        return;
                    }
                    const qList = Array.isArray(qData) ? qData : (qData.questions || []);
                    questions = qList;
                    if (questions.length === 0) {
                        handleException("No questions generated. Please try again.");
                        return;
                    }
                    initializeViva();
                }, (err) => handleException("Generation Failure: " + err));
            } else {
                handleException(data.message || "Failed to generate questions.");
            }
        } catch (err) { handleException("Network Error"); }
    }

    function initializeViva() {
        document.getElementById('loadingOverlay').classList.add('hidden');
        document.getElementById('vivaView').style.display = 'block';
        renderStep();
    }

    function renderStep() {
        if (currentIdx >= questions.length) { finalizeAssessment(); return; }
        document.getElementById('stepCounterLabel').innerText = `DEFENSE QUESTION ${currentIdx + 1}/${questions.length}`;
        document.getElementById('questionText').innerText = questions[currentIdx];
        renderMath(document.getElementById('questionText'));
        document.getElementById('userAnswer').value = '';
        document.getElementById('userAnswer').focus();

        const dots = document.querySelectorAll('.step-dot');
        dots.forEach((dot, idx) => {
            if (idx === currentIdx) dot.className = 'step-dot active';
            else if (idx < currentIdx) dot.className = 'step-dot completed';
            else dot.className = 'step-dot';
        });

        if (currentIdx === questions.length - 1) {
            document.getElementById('btnSubmit').innerHTML = 'SUBMIT FOR EVALUATION <i class="fas fa-check-double"></i>';
        }
    }

    function submitAnswer() {
        if (isFinalizing || currentIdx >= questions.length) return;
        const answer = document.getElementById('userAnswer').value.trim();
        if (answer.length < 10) {
            LakshyaDialog.alert("Please provide a detailed technical response (at least 10 characters).", { type: 'warning', title: 'Answer Too Short' });
            return;
        }
        answers.push({ question: questions[currentIdx], answer: answer });
        currentIdx++;
        renderStep();
    }

    async function pollJobStatus(jobId, onSuccess, onError) {
        let polls = 0;
        const check = async () => {
            // Stop after ~5 minutes instead of polling forever if the job is stuck
            if (++polls > 200) { onError("The AI took too long to respond. Please try again."); return; }
            try {
                const res = await fetch(`ai_job_status.php?job_id=${jobId}`);
                const data = await res.json();
                if (data.success === false) {
                    onError(data.message || "Job error");
                    return;
                }
                if (data.status === 'completed') onSuccess(data.result);
                else if (data.status === 'failed') onError(data.error);
                else setTimeout(check, 1500);
            } catch (e) { onError("Poll interrupted"); }
        };
        check();
    }

    async function finalizeAssessment(isAuto = false) {
        if (isFinalizing) return;
        isFinalizing = true;
        isSessionActive = false;
        clearInterval(proctorInterval);

        const widget = document.getElementById('proctorWidget');
        if (widget) widget.style.display = 'none';

        if (webcamStream) webcamStream.getTracks().forEach(t => t.stop());
        if (screenStream) screenStream.getTracks().forEach(t => t.stop());

        document.getElementById('warningOverlay').classList.add('hidden');
        document.getElementById('vivaView').style.display = 'none';
        document.getElementById('loadingOverlay').classList.remove('hidden');
        document.getElementById('loadingText').innerText = isAuto ? 'AUTO-SUBMITTING DUE TO SECURITY VIOLATIONS...' : 'EVALUATING DEFENSE ARGUMENTS...';

        try {
            const res = await fetch('project_viva_handler.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ action: 'submit_viva', portfolio_id: portfolioId, history: answers })
            });
            const data = await res.json();
            if (!data.success) { handleException(data.message || "Submission failed."); return; }

            if (data.direct && data.result) {
                persistResult(data.result, isAuto);
            } else if (data.job_id) {
                pollJobStatus(data.job_id, (evalData) => {
                    if (evalData.success === false) {
                        handleException("Evaluation Error: " + (evalData.message || "Failed to grade defense."));
                        return;
                    }
                    persistResult(evalData, isAuto);
                }, (err) => handleException(err));
            } else {
                handleException("Submission failed");
            }
        } catch (err) { handleException("Network error"); }
    }

    async function persistResult(evalData, isAuto = false) {
        document.getElementById('warningOverlay').classList.add('hidden');
        document.getElementById('loadingOverlay').classList.add('hidden');
        document.getElementById('resultsView').style.display = 'block';
        // The server applies integrity penalties and decides verification — show its result, not the raw AI score
        const showOutcome = (score, isVerified, statusText) => {
            document.getElementById('finalScoreVal').innerText = score;
            document.getElementById('finalStatusText').innerText = statusText || (isVerified ? 'VERIFIED' : 'NOT VERIFIED');
            document.getElementById('finalStatusText').style.color = isVerified ? '#10b981' : '#ef4444';
            const ring = document.getElementById('successRing');
            ring.style.color = isVerified ? '#10b981' : '#ef4444';
            ring.innerHTML = isVerified ? '<i class="fas fa-award"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
        };
        showOutcome(evalData.score ?? '--', false, 'SAVING RESULT…');
        document.getElementById('finalStatusText').style.color = '#f59e0b';
        document.getElementById('finalFeedback').innerText = evalData.feedback || '';
        renderMath(document.getElementById('finalFeedback'));

        const clientFacePct = proctorStats.totalFrames > 0 
            ? Math.round((proctorStats.validFaceFrames / proctorStats.totalFrames) * 100)
            : 100;

        try {
            const saveRes = await fetch('project_viva_handler.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: 'save_viva_result',
                    portfolio_id: portfolioId,
                    score: evalData.score,
                    feedback: evalData.feedback,
                    history: answers,
                    auto_submitted: isAuto ? 1 : 0,
                    strike_count: warningCount,
                    client_face_presence_pct: clientFacePct
                })
            });

            const saveData = await saveRes.json().catch(() => ({ success: false }));
            if (!saveRes.ok || !saveData.success) {
                console.error('Failed to save viva result — HTTP', saveRes.status, saveData.message);
                showOutcome('--', false, 'NOT SAVED');
                await LakshyaDialog.alert(saveData.message || 'Your result could not be saved. Please retake the defense from the dashboard.', {
                    type: 'error', title: 'Result Not Saved'
                });
                return;
            }
            showOutcome(saveData.final_score ?? evalData.score, !!saveData.is_verified);
            
            // Render Integrity Report Card
            if (saveData.integrity_report) {
                const rpt = saveData.integrity_report;
                // ?? not ||: a real 0% must not display as 100%
                document.getElementById('rptScreenShare').innerText = (rpt.screen_sharing_active_pct ?? 100) + '%';
                document.getElementById('rptCameraAvail').innerText = (rpt.camera_availability_pct ?? 100) + '%';
                document.getElementById('rptFacePres').innerText = (rpt.face_presence_pct ?? 100) + '%';
                document.getElementById('rptGazeConf').innerText = (rpt.gaze_confidence_pct ?? 92) + '%';
                document.getElementById('rptAttnDev').innerText = rpt.attention_deviations ?? 0;
                document.getElementById('rptLongestDev').innerText = (rpt.longest_deviation_sec ?? 0) + 's';
                document.getElementById('rptScreenInt').innerText = rpt.screen_interruptions ?? 0;
                document.getElementById('rptMultiFace').innerText = rpt.multiple_faces_count ?? 0;

                const pill = document.getElementById('rptStatusPill');
                if (pill) {
                    pill.innerText = 'Integrity Status: ' + (rpt.integrity_status || 'Verified');
                    if (rpt.auto_submitted || rpt.strike_count >= 3 || rpt.screen_interruptions > 0) {
                        pill.className = 'integrity-pill warning';
                    } else {
                        pill.className = 'integrity-pill success';
                    }
                }
            }

        } catch (err) {
            console.error("Persistence error", err);
            showOutcome('--', false, 'NOT SAVED');
            LakshyaDialog.alert('Network error while saving your result. Please check your connection and retake the defense from the dashboard.', { type: 'error', title: 'Result Not Saved' });
        }
    }

    async function handleException(msg) {
        // Stop proctoring so no strike / auto-submit fires while the error is on screen
        isSessionActive = false;
        if (proctorInterval) clearInterval(proctorInterval);
        await LakshyaDialog.alert(String(msg), { type: 'error', title: 'Error', okText: 'Return to Dashboard' });
        window.location.href = 'dashboard';
    }
</script>

</body>
</html>
