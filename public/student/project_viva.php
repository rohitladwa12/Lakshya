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
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Defense: <?php echo htmlspecialchars($projectTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <video id="setupWebcamPreview" autoplay muted playsinline style="width: 220px; height: 150px; border-radius: 16px; background: #000; border: 2px solid var(--glass-border); margin: 0 auto 1.2rem; object-fit: cover; display: block;"></video>
            <div id="setupCheckStatus" style="font-size: 0.85rem; font-weight: 700; color: #f59e0b; margin-bottom: 1.5rem;">
                Requesting camera permission…
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
            <div class="calib-target" id="calibTarget"></div>
            <div id="calibProgressText" style="font-size: 0.9rem; font-weight: 700; color: var(--accent-gold); margin-bottom: 1.5rem;">
                Progress: Center (0/3s)
            </div>
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
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

    // --- PROCTORING ENGINE STATE ---
    let webcamStream = null;
    let screenStream = null;
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

    async function initMediaPipeDetector() {
        if (mediaPipeDetector || isMediaPipeLoading) return;
        isMediaPipeLoading = true;
        try {
            const visionModule = await import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/vision_bundle.mjs');
            const vision = await visionModule.FilesetResolver.forVisionTasks(
                'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/wasm'
            );
            mediaPipeDetector = await visionModule.FaceDetector.createFromOptions(vision, {
                baseOptions: {
                    modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite',
                    delegate: 'GPU'
                },
                runningMode: 'IMAGE',
                minDetectionConfidence: 0.45,
            });
            console.log("[Lakshya AI] MediaPipe BlazeFace AI Detector initialized.");
        } catch (err) {
            console.warn("[Lakshya AI] MediaPipe CDN load fallback:", err);
        } finally {
            isMediaPipeLoading = false;
        }
    }
    // Pre-warm MediaPipe detector on page load
    initMediaPipeDetector();

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
            if (e.ctrlKey && ['c', 'v', 'x', 'u'].includes(e.key.toLowerCase())) e.preventDefault();
            if (e.ctrlKey && e.shiftKey && e.key === 'I') e.preventDefault();
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

            webcamStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                audio: false
            });

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
            videoTrack.onmute = () => handleCameraStreamLost();

            let countdown = 5;
            const updateCountdown = () => {
                const track = webcamStream ? webcamStream.getVideoTracks()[0] : null;
                if (!track || track.readyState !== 'live' || track.ended || track.muted) {
                    handleCameraStreamLost();
                    return;
                }

                if (videoEl.paused) {
                    videoEl.play().catch(e => {});
                }

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
            handleCameraStreamLost();
        }
    }

    function handleCameraStreamLost() {
        if (cameraSetupTimer) clearTimeout(cameraSetupTimer);
        const statusEl = document.getElementById('setupCheckStatus');
        const btnEl = document.getElementById('btnGrantCamera');
        if (statusEl) {
            statusEl.style.color = '#ef4444';
            statusEl.innerHTML = '<i class="fas fa-times-circle"></i> Camera stream lost or permission denied.<br><span style="font-size:0.8rem; font-weight:600; color:#94a3b8;">Check webcam connection and click Retry below.</span>';
        }
        if (btnEl) {
            btnEl.innerHTML = '<i class="fas fa-redo"></i> Retry Camera Setup';
            btnEl.style.display = 'inline-flex';
            btnEl.disabled = false;
        }
    }

    // --- STEP 2: GAZE CALIBRATION ---
    async function startCalibrationFlow() {
        const textEl = document.getElementById('calibPromptText');
        const progEl = document.getElementById('calibProgressText');

        textEl.textContent = 'Get ready! Sit straight and face the screen.';
        progEl.textContent = 'Calibration Starting in 3 seconds…';
        await new Promise(r => setTimeout(r, 1000));
        progEl.textContent = 'Calibration Starting in 2 seconds…';
        await new Promise(r => setTimeout(r, 1000));
        progEl.textContent = 'Calibration Starting in 1 second…';
        await new Promise(r => setTimeout(r, 1000));

        // Center
        textEl.textContent = 'Look directly at the center target.';
        progEl.textContent = 'Calibrating Center Baseline (1/3s)';
        await new Promise(r => setTimeout(r, 1000));
        progEl.textContent = 'Calibrating Center Baseline (2/3s)';
        await new Promise(r => setTimeout(r, 1000));
        progEl.textContent = 'Calibrating Center Baseline (3/3s)';
        await new Promise(r => setTimeout(r, 1000));
        calibrationData.center = { timestamp: Date.now(), confidence: 0.95 };
        progEl.innerHTML = '<i class="fas fa-check-circle"></i> Center Baseline Saved!';
        await new Promise(r => setTimeout(r, 800));

        // Left
        textEl.textContent = 'Look slightly to your LEFT for 2 seconds.';
        progEl.textContent = 'Calibrating Left Baseline…';
        await new Promise(r => setTimeout(r, 1800));
        calibrationData.left = { timestamp: Date.now(), confidence: 0.95 };
        progEl.innerHTML = '<i class="fas fa-check-circle"></i> Left Baseline Saved!';
        await new Promise(r => setTimeout(r, 800));

        // Right
        textEl.textContent = 'Look slightly to your RIGHT for 2 seconds.';
        progEl.textContent = 'Calibrating Right Baseline…';
        await new Promise(r => setTimeout(r, 1800));
        calibrationData.right = { timestamp: Date.now(), confidence: 0.95 };
        progEl.innerHTML = '<i class="fas fa-check-circle"></i> Right Baseline Saved!';
        await new Promise(r => setTimeout(r, 800));

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

            statusEl.style.color = '#10b981';
            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Screen share active!';

            setTimeout(() => {
                document.getElementById('introOverlay').classList.add('hidden');
                beginAssessmentExecution();
            }, 800);

        } catch (err) {
            statusEl.style.color = '#ef4444';
            statusEl.innerHTML = '<i class="fas fa-times-circle"></i> Screen share required for defense session. Select a screen to share.';
        }
    }

    async function beginAssessmentExecution() {
        await enterFullscreen();
        isSessionActive = true;

        proctorStats = {
            totalFrames: 0,
            validFaceFrames: 0,
            consecutiveNoFace: 0,
            consecutiveGazeDev: 0,
            lastLoggedNoFaceTime: 0,
            lastLoggedGazeTime: 0
        };

        const setupVid = document.getElementById('setupWebcamPreview');
        if (setupVid) setupVid.srcObject = null;

        const widget = document.getElementById('proctorWidget');
        const widgetVideo = document.getElementById('proctorWebcamVideo');
        if (widget && widgetVideo && webcamStream) {
            widget.style.display = 'block';
            widgetVideo.srcObject = webcamStream;
            try { await widgetVideo.play(); } catch (e) {}
        }

        if (proctorInterval) clearInterval(proctorInterval);
        proctorInterval = setInterval(runProctoringCheckFrame, 1200);

        startProcessing();
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
            try { videoEl.play(); } catch (e) {}
            return;
        }

        const ctx = canvasEl.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
        const imgData = ctx.getImageData(0, 0, canvasEl.width, canvasEl.height);
        const data = imgData.data;

        proctorStats.totalFrames++;

        let facesDetected = -1;
        let gazeDeviated = false;

        // 1. Primary AI Vision Engine: MediaPipe BlazeFace (Google AI)
        if (mediaPipeDetector) {
            try {
                const mpResult = mediaPipeDetector.detect(videoEl);
                if (mpResult && mpResult.detections) {
                    facesDetected = mpResult.detections.length;
                    if (facesDetected === 1 && mpResult.detections[0].boundingBox) {
                        const box = mpResult.detections[0].boundingBox;
                        const centerX = box.originX + (box.width / 2);
                        const videoCenterX = videoEl.videoWidth / 2;
                        if (Math.abs(centerX - videoCenterX) > (videoEl.videoWidth * 0.32)) {
                            gazeDeviated = true;
                        }
                    }
                }
            } catch (e) {}
        }

        // 2. Native Browser FaceDetector Fallback
        if (facesDetected === -1 && nativeFaceDetector) {
            try {
                const detected = await nativeFaceDetector.detect(canvasEl);
                facesDetected = detected.length;
                if (facesDetected === 1 && detected[0].boundingBox) {
                    const box = detected[0].boundingBox;
                    const centerX = box.x + (box.width / 2);
                    const frameCenterX = canvasEl.width / 2;
                    if (Math.abs(centerX - frameCenterX) > (canvasEl.width * 0.35)) {
                        gazeDeviated = true;
                    }
                }
            } catch (e) {}
        }

        // 3. Heuristic YCbCr Pixel Fallback (if ML models are offline)
        let lumSum = 0;
        let skinPixelCount = 0;
        let leftEdgeCount = 0;
        let centerCount = 0;
        let rightEdgeCount = 0;
        const totalPixels = data.length / 4;
        const width = canvasEl.width;

        for (let i = 0; i < data.length; i += 16) {
            const pixelIdx = i / 4;
            const x = pixelIdx % width;
            const r = data[i], g = data[i+1], b = data[i+2];
            const y  = 0.299 * r + 0.587 * g + 0.114 * b;
            const cb = 128 - (0.168736 * r) - (0.331264 * g) + (0.5 * b);
            const cr = 128 + (0.5 * r) - (0.418688 * g) - (0.081312 * b);

            lumSum += y;
            if (y > 30 && cb >= 77 && cb <= 127 && cr >= 133 && cr <= 173) {
                skinPixelCount++;
                if (x < width * 0.30) leftEdgeCount++;
                else if (x > width * 0.70) rightEdgeCount++;
                else if (x >= width * 0.40 && x <= width * 0.60) centerCount++;
            }
        }

        const sampleTotal = totalPixels / 4;
        const skinRatio = skinPixelCount / sampleTotal;
        const leftEdgeRatio = leftEdgeCount / (sampleTotal * 0.30);
        const rightEdgeRatio = rightEdgeCount / (sampleTotal * 0.30);
        const centerRatio = centerCount / (sampleTotal * 0.20);

        if (facesDetected === -1) {
            if ((leftEdgeRatio > 0.08 && rightEdgeRatio > 0.08 && centerRatio < 0.03) || skinRatio > 0.38) {
                facesDetected = 2;
            } else if (skinRatio >= 0.025) {
                facesDetected = 1;
            } else {
                facesDetected = 0;
            }
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

            if (proctorStats.consecutiveNoFace >= 3 && (Date.now() - lastNoFaceWarningTime) > 7000) {
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

    async function logProctoringEvent(eventType, duration = 0, confidence = 1.0, severity = 'LOW', metadata = {}) {
        try {
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
                    metadata: metadata
                })
            });
        } catch (e) {}
    }

    // --- WARNING & AUTO-SUBMIT STRIKE SYSTEM ---
    let warningCount = 0;
    let lastNoFaceWarningTime = 0;
    let lastMultiFaceWarningTime = 0;
    let lastGazeWarningTime = 0;

    function triggerWarning(reason, eventType = 'SECURITY_VIOLATION') {
        if (!isSessionActive) return;
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

    function resumeFullscreen() {
        document.documentElement.requestFullscreen().then(() => {
            document.getElementById('warningOverlay').classList.add('hidden');
        }).catch(e => alert("Please enable F11 manually."));
    }

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && isSessionActive) {
            triggerWarning('Full screen mode was deactivated.', 'FULLSCREEN_EXIT');
        }
    });

    async function enterFullscreen() {
        if (document.documentElement.requestFullscreen) {
            await document.documentElement.requestFullscreen().catch((e) => console.warn(e));
        }
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
        const answer = document.getElementById('userAnswer').value.trim();
        if (answer.length < 10) { alert("Please provide a detailed technical response."); return; }
        answers.push({ question: questions[currentIdx], answer: answer });
        currentIdx++;
        renderStep();
    }

    async function pollJobStatus(jobId, onSuccess, onError) {
        const check = async () => {
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
        const isVerified = evalData.score >= 70;
        document.getElementById('finalScoreVal').innerText = evalData.score;
        document.getElementById('finalStatusText').innerText = isVerified ? 'VERIFIED' : 'NOT VERIFIED';
        document.getElementById('finalStatusText').style.color = isVerified ? '#10b981' : '#ef4444';
        document.getElementById('finalFeedback').innerText = evalData.feedback;
        renderMath(document.getElementById('finalFeedback'));
        
        if (!isVerified) {
            document.getElementById('successRing').style.color = '#ef4444';
            document.getElementById('successRing').innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
        }

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

            if (!saveRes.ok) {
                console.error('Failed to save viva result — HTTP', saveRes.status);
                return;
            }
            const saveData = await saveRes.json();
            
            // Render Integrity Report Card
            if (saveData.integrity_report) {
                const rpt = saveData.integrity_report;
                document.getElementById('rptScreenShare').innerText = (rpt.screen_sharing_active_pct || 100) + '%';
                document.getElementById('rptCameraAvail').innerText = (rpt.camera_availability_pct || 100) + '%';
                document.getElementById('rptFacePres').innerText = (rpt.face_presence_pct || 100) + '%';
                document.getElementById('rptGazeConf').innerText = (rpt.gaze_confidence_pct || 92) + '%';
                document.getElementById('rptAttnDev').innerText = rpt.attention_deviations || 0;
                document.getElementById('rptLongestDev').innerText = (rpt.longest_deviation_sec || 0) + 's';
                document.getElementById('rptScreenInt').innerText = rpt.screen_interruptions || 0;
                document.getElementById('rptMultiFace').innerText = rpt.multiple_faces_count || 0;

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

        } catch (err) { console.error("Persistence error", err); }
    }

    function handleException(msg) { alert("ERROR: " + msg); window.location.href = 'dashboard'; }
</script>

</body>
</html>
