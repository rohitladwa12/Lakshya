<?php
require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireLogin();

$driveId = isset($_GET['drive_id']) ? (int) $_GET['drive_id'] : 0;
$companyName = 'General';
$taskId = 0;
$concept = '';
$roleName = 'Software Engineer';

if ($driveId > 0) {
    $db = getDB();
    $usn = getUsername();
    // Fetch drive details
    $stmt = $db->prepare("
        SELECT cd.*, jp.title as job_title, jp.id as job_id, c.name as company_name 
        FROM campus_drives cd
        JOIN job_postings jp ON cd.job_id = jp.id
        LEFT JOIN companies c ON jp.company_id = c.id
        WHERE cd.id = ?
    ");
    $stmt->execute([$driveId]);
    $drive = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$drive) {
        die("Recruitment drive not found.");
    }
    // Enforce applied check
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE job_id = ? AND student_id = ?
    ");
    $stmt->execute([$drive['job_id'], $usn]);
    if ($stmt->fetchColumn() == 0) {
        die("Access denied. Only applied students can access this recruitment drive round.");
    }

    // Check if the round is active
    if (!$drive['hr_active']) {
        die("The HR Round for this recruitment drive is currently disabled.");
    }

    $companyName = $drive['company_name'];
    $roleName = $drive['job_title'];
} else {
    // Handle POST from Assigned Task
    if (isPost() && (isset($_POST['company']) || isset($_POST['task_id']))) {
        SessionFilterHelper::setFilters('ai_hr_round', [
            'company' => $_POST['company'] ?? 'General',
            'concept' => $_POST['concept'] ?? '',
            'task_id' => $_POST['task_id'] ?? 0
        ]);
        header("Location: ai_hr_round.php");
        exit;
    }

    $filters = SessionFilterHelper::getFilters('ai_hr_round');
    $companyName = $filters['company'] ?? 'General';
    $taskId = $filters['task_id'] ?? 0;
    $concept = $filters['concept'] ?? '';
    if (empty($concept) && $taskId) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT concept FROM coordinator_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $concept = $stmt->fetchColumn() ?: '';
        } catch (Exception $e) {}
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Round - <?php echo htmlspecialchars($companyName); ?></title>
    <!-- Resilience & Cache Busting -->
    <script src="resilience.js?v=<?php echo APP_VERSION; ?>"></script>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- html2pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --bg-dark: #0f0c29;
            --primary: #c10505;
            /* Maroon */
            --accent: #ffd700;
            /* Gold */
            --glass: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
            --text: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        /* Fullscreen Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            z-index: 2000;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            transition: opacity 0.5s ease;
        }

        .hidden {
            display: none !important;
            opacity: 0;
            pointer-events: none;
        }

        /* Main UI */
        .main-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* Avatar Container */
        .avatar-container {
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.6);
            border: 2px solid var(--border);
            transition: all 0.3s;
            margin-bottom: 2rem;
        }

        .avatar-img {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            border: 3px solid rgba(255, 255, 255, 0.1);
        }

        /* Pulse Animation for Speaking/Listening */
        @keyframes pulse-speak {
            0% {
                box-shadow: 0 0 0 0 rgba(233, 198, 111, 0.4);
                border-color: var(--accent);
                transform: scale(1);
            }

            50% {
                box-shadow: 0 0 0 40px rgba(233, 198, 111, 0);
                border-color: var(--accent);
                transform: scale(1.02);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(233, 198, 111, 0);
                border-color: var(--accent);
                transform: scale(1);
            }
        }

        @keyframes pulse-listen {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4);
                border-color: #4CAF50;
                transform: scale(1);
            }

            50% {
                box-shadow: 0 0 0 50px rgba(76, 175, 80, 0);
                border-color: #4CAF50;
                transform: scale(1.05);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
                border-color: #4CAF50;
                transform: scale(1);
            }
        }

        .state-speaking {
            animation: pulse-speak 1.5s infinite;
        }

        .state-listening {
            animation: pulse-listen 1.5s infinite;
        }

        /* Controls */
        .controls {
            margin-top: 40px;
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .btn-mic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: none;
            background: var(--glass);
            border: 1px solid var(--border);
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .btn-mic:hover {
            transform: scale(1.1);
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-mic:active {
            transform: scale(0.95);
        }

        .btn-mic.active {
            background: #4CAF50;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.4);
        }

        .btn-mic.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit-answer {
            height: 56px;
            padding: 0 32px;
            border-radius: 28px;
            border: none;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: none;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-submit-answer:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(76, 175, 80, 0.5);
            background: linear-gradient(135deg, #66BB6A, #388E3C);
        }

        .btn-submit-answer:active {
            transform: scale(0.97);
        }

        .btn-submit-answer.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit-answer .submit-hint {
            font-size: 0.7rem;
            opacity: 0.8;
            font-weight: 400;
            text-transform: none;
            letter-spacing: 0;
        }

        @keyframes submitPulse {
            0%, 100% { box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); }
            50% { box-shadow: 0 4px 25px rgba(76, 175, 80, 0.6); }
        }

        .btn-submit-answer.has-content {
            animation: submitPulse 2s ease-in-out infinite;
        }

        .btn-end {
            background: rgba(193, 5, 5, 0.8);
        }

        .btn-end:hover {
            background: #ff4444;
            box-shadow: 0 0 20px rgba(255, 68, 68, 0.4);
        }

        /* Status Text */
        .status-text {
            margin-top: 10px;
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.6);
            height: 30px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* Captions — removed, using right-side transcript panel only */
        .caption-box {
            display: none !important;
        }

        /* Timer Styles */
        #sessionTimer {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--border);
            z-index: 100;
        }

        #sessionTimer.locked {
            color: #ff5555;
        }

        #sessionTimer.unlocked {
            color: #50fa7b;
        }

        .btn-end {
            border: 1px solid transparent;
            transition: all 0.3s;
        }

        .btn-end:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            filter: grayscale(1);
        }

        .btn-end.unlocked {
            background: var(--primary);
            animation: pulse-end 2s infinite;
            border-color: var(--accent);
        }

        @keyframes pulse-end {
            0% {
                box-shadow: 0 0 0 0 rgba(193, 5, 5, 0.7);
            }

            70% {
                box-shadow: 0 0 0 15px rgba(193, 5, 5, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(193, 5, 5, 0);
            }
        }

        /* Score Modal */
        #scoreModal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .score-card {
            background: linear-gradient(145deg, #1e1e2f, #11111a);
            padding: 50px;
            border-radius: 24px;
            text-align: center;
            border: 2px solid #333;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            max-width: 500px;
            width: 90%;
            color: white;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes popIn {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .score-title {
            font-size: 24px;
            color: #aaa;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .score-number {
            font-size: 80px;
            font-weight: 900;
            color: #10b981;
            line-height: 1;
            text-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
            margin-bottom: 5px;
        }

        .score-percentage {
            font-size: 30px;
            font-weight: 700;
            color: #10b981;
        }

        .score-zero {
            color: #ef4444;
            text-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
        }

        .score-desc {
            font-size: 16px;
            color: #bbb;
            margin-bottom: 40px;
        }

        .btn-continue {
            background: #800000;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            box-shadow: 0 10px 20px rgba(128, 0, 0, 0.3);
        }

        .btn-continue:hover {
            background: #a50000;
            transform: translateY(-3px);
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(8px);
            z-index: 5000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            transition: opacity 0.5s;
        }

        .loading-spinner {
            width: 80px;
            height: 80px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Real-time Transcript Layout Fixes */
        .transcript-panel {
            position: absolute;
            right: 30px;
            top: 100px;
            width: 320px;
            max-height: 65vh;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            z-index: 10;
            transition: all 0.3s ease;
        }

        .transcript-panel h3 {
            margin: 0 0 15px 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .transcript-scroll {
            flex: 1;
            overflow-y: auto;
            padding-right: 10px;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .transcript-scroll::-webkit-scrollbar {
            width: 4px;
        }

        .transcript-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .transcript-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .transcript-line {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .transcript-line b {
            color: var(--accent);
            font-size: 0.75rem;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }

        .transcript-line.user b {
            color: #4CAF50;
        }

        .transcript-line.ai b {
            color: var(--accent);
        }

        @media (max-width: 1200px) {
            .transcript-panel {
                position: relative;
                right: auto;
                top: auto;
                width: 90%;
                max-width: 600px;
                margin: 20px 0;
                max-height: 200px;
            }
        }
    </style>
</head>

<body>

    <!-- Intro Overlay -->
    <div id="introOverlay" class="overlay">
        <div
            style="text-align: center; max-width: 600px; padding: 40px; background: #1e1e1e; border-radius: 16px; border: 1px solid #333;">
            <div style="font-size: 4rem; margin-bottom: 20px;">🤝</div>
            <h1 style="color: var(--accent);">HR Round</h1>
            <p>Role: <strong><?php echo htmlspecialchars($companyName); ?></strong></p>
            <p style="color: #aaa; margin: 20px 0;">
                This is a speech-to-speech behavioral interview.<br>
                The AI will assess your communication confidence, cultural fit, and problem-solving examples.<br>
                <strong>Please allow Microphone Access.</strong>
            </p>
            <input type="text" id="roleInput" placeholder="Specific Role (e.g. Manager)"
                value="<?php echo htmlspecialchars($roleName); ?>"
                style="padding: 10px; width: 200px; text-align: center; margin-bottom: 20px; <?php echo $driveId > 0 ? 'display:none;' : ''; ?>">
            <br>
            <button onclick="startSession()"
                style="padding: 15px 40px; font-size: 1.1rem; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer;">Start
                Interview</button>
        </div>
    </div>

    <div id="loadingOverlay" class="loading-overlay hidden">
        <div class="loading-spinner"></div>
        <h2 style="margin: 0; letter-spacing: 2px;">GENERATING REPORT</h2>
        <p style="color: rgba(255,255,255,0.6); margin-top: 10px;">Please wait while AI evaluates your behavioral
            performance...</p>
    </div>

    <!-- Score Modal -->
    <div id="scoreModal" class="hidden">
        <div class="score-card">
            <div class="score-title">Assessment Complete</div>
            <div>
                <span id="finalScoreNum" class="score-number">0</span><span id="finalScorePct"
                    class="score-percentage">%</span>
            </div>
            <div class="score-desc">Your HR interview performance has been evaluated.</div>
            <button class="btn-continue" onclick="closeSession()">Continue</button>
        </div>
    </div>

    <!-- Security Warning Overlay -->
    <div id="warningOverlay" class="overlay hidden">
        <div style="text-align: center;">
            <i class="fas fa-exclamation-triangle"
                style="color: var(--primary); font-size: 4rem; margin-bottom: 20px;"></i>
            <h2 style="color: #fff;">Video/Audio Integrity Check</h2>
            <p style="color: #ccc;">Please return to full screen to continue the interview.</p>
            <button onclick="resumeFullscreen()"
                style="padding: 10px 30px; background: var(--primary); color: white; border: none; border-radius: 5px; margin-top: 20px; cursor: pointer;">RESUME</button>
        </div>
    </div>

    <div class="main-container">
        <div id="sessionTimer" class="locked">
            <i class="fas fa-clock"></i>
            <span id="timerText">Initializing...</span>
        </div>

        <div id="avatar" class="avatar-container">
            <img src="../assets/img/ai/hr_persona.png" alt="HR AI" class="avatar-img">
        </div>

        <div id="statusText" class="status-text">Initializing...</div>



        <!-- Real-time transcript -->
        <div id="transcriptPanel" class="transcript-panel">
            <h3><i class="fas fa-align-left"></i> Live Transcript</h3>
            <div id="transcriptScroll" class="transcript-scroll"></div>
        </div>

        <div class="controls">
            <button id="micBtn" class="btn-mic disabled" onclick="toggleMic()"><i
                    class="fas fa-microphone-slash"></i></button>
            <button id="submitAnswerBtn" class="btn-submit-answer disabled" onclick="submitVoiceAnswer()">
                <i class="fas fa-paper-plane"></i>
                <span>Submit Answer</span>
            </button>
            <button id="endBtn" class="btn-mic btn-end" onclick="endSession()" disabled
                title="Minimum 20 minutes required for assigned tasks"><i class="fas fa-phone-slash"></i></button>
        </div>

        <!-- Text input fallback for mic issues -->
        <div id="textInputArea" style="display:none; margin-top:15px; width:70%; max-width:600px;">
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="text" id="textInput" placeholder="Type your answer here and press Enter..."
                    style="flex:1; padding:12px 16px; border-radius:12px; border:1px solid var(--border); background:rgba(255,255,255,0.08); color:white; font-size:1rem; backdrop-filter:blur(5px);"
                    onkeydown="if(event.key==='Enter' && this.value.trim()){submitTextAnswer(); event.preventDefault();}">
                <button onclick="submitTextAnswer()"
                    style="padding:12px 20px; border-radius:12px; border:none; background:var(--primary); color:white; cursor:pointer; font-weight:600;">Send</button>
            </div>
            <div id="micErrorMsg"
                style="display:none; color:#ff6b6b; font-size:0.85rem; margin-top:8px; text-align:center;"></div>
            <div style="text-align:center; margin-top:10px;">
                <button id="retryQuestionBtn" onclick="retryQuestion()"
                    style="display:none; padding:10px 24px; border-radius:12px; border:1px solid var(--accent); background:transparent; color:var(--accent); cursor:pointer; font-weight:600;">
                    <i class="fas fa-rotate-right"></i> Retry Question
                </button>
            </div>
        </div>
    </div>

    <script>
        window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    </script>
    <script src="<?php echo APP_URL; ?>/js/security_interceptor.js?v=<?php echo APP_VERSION; ?>"></script>
    <script>
        const SILENCE_MS = 45000; // 45s — only used for a gentle reminder, NOT auto-submit

        function showMicError(msg) {
            const area = document.getElementById('textInputArea');
            const errMsg = document.getElementById('micErrorMsg');
            if (area) area.style.display = 'block';
            if (errMsg) { errMsg.style.display = 'block'; errMsg.textContent = msg; }
        }

        function submitTextAnswer() {
            const input = document.getElementById('textInput');
            const text = input?.value?.trim();
            if (!text) return;
            input.value = '';
            if (currentState === State.LISTENING || currentState === State.WAITING || currentState === State.ERROR) {
                finalizeUserTranscriptLine(text);
                transitionTo(State.PROCESSING);
                loadNextQuestion(text);
                accumulatedTranscript = '';
                currentUtterance = '';
                resetConfidenceStats();
            }
        }

        const State = {
            IDLE: 'IDLE',
            AI_SPEAKING: 'AI_SPEAKING',
            WAITING: 'WAITING',
            LISTENING: 'LISTENING',
            PROCESSING: 'PROCESSING',
            ERROR: 'ERROR',
            ENDED: 'ENDED'
        };

        // WAITING→PROCESSING is required for the text-input fallback (typing an
        // answer while the mic is preparing), PROCESSING→WAITING for recovering
        // after a failed question load / failed report generation, and
        // ERROR→WAITING/PROCESSING so a mic failure never dead-ends the session.
        const ValidTransitions = {
            [State.IDLE]: [State.AI_SPEAKING, State.ENDED],
            [State.AI_SPEAKING]: [State.WAITING, State.PROCESSING, State.ERROR, State.ENDED],
            [State.WAITING]: [State.LISTENING, State.PROCESSING, State.AI_SPEAKING, State.ERROR, State.ENDED],
            [State.LISTENING]: [State.PROCESSING, State.ERROR, State.ENDED],
            [State.PROCESSING]: [State.AI_SPEAKING, State.WAITING, State.ERROR, State.ENDED],
            [State.ERROR]: [State.LISTENING, State.WAITING, State.PROCESSING, State.AI_SPEAKING, State.ENDED],
            [State.ENDED]: []
        };

        let currentState = State.IDLE;
        let sessionId = null;
        let company = "<?php echo addslashes($companyName); ?>";
        let driveId = <?php echo $driveId; ?>;
        let concept = "<?php echo addslashes($concept); ?>";
        let isSessionActive = false;
        let recognition;
        let synth = window.speechSynthesis;
        let isListening = false;
        let silenceTimer = null;
        let currentUtterance = '';
        let accumulatedTranscript = '';
        let lastSpeechTimestamp = Date.now();
        let userInterimEl = null;

        let startTime = null;
        let isTaskId = <?php echo $taskId ? 'true' : 'false'; ?>;
        const MIN_REQUIRED_TIME = 20 * 60;

        let voices = [];
        let speechQueue = [];
        let speechWatchdog = null;

        let confidenceCount = 0;
        let confidenceMean = 0;
        let confidenceM2 = 0;
        let confidenceMin = 1.0;
        let confidenceMax = 0.0;

        let telemetryOnstartCount = 0;
        let telemetryOnerrorCount = 0;
        let telemetrySilenceTimeoutCount = 0;
        let telemetryVADSpeechTime = 0;
        let telemetrySubmissionReasons = [];

        let timeStateTransitionToListening = 0;
        let timeMicOpened = 0;
        let timeFirstTranscriptReceived = 0;
        let timeToFirstTranscriptLogged = false;

        let telemetryEventLog = [];

        function logTelemetryEvent(eventName) {
            const timeDiff = startTime ? (Date.now() - startTime) : 0;
            telemetryEventLog.push({
                t: timeDiff,
                event: eventName
            });
        }

        function calculateVoiceHealthScore() {
            let score = 100;
            score -= (telemetryOnerrorCount * 15);
            const stats = getConfidenceStats();
            if (stats.count > 0 && stats.mean < 0.75) {
                score -= Math.min(25, Math.floor((0.75 - stats.mean) * 100));
            }
            score -= (reconnectAttempts * 8);
            return Math.max(0, score);
        }

        function getTelemetryPayload() {
            return JSON.stringify({
                schema_version: 2,
                voice_metrics: {
                    browser: navigator.userAgent,
                    onstart_events: telemetryOnstartCount,
                    onerror_events: telemetryOnerrorCount,
                    silence_timeouts: telemetrySilenceTimeoutCount,
                    vad_speech_time_ms: telemetryVADSpeechTime,
                    submission_reasons: telemetrySubmissionReasons,
                    confidence_stats: getConfidenceStats(),
                    voice_health_score: calculateVoiceHealthScore()
                },
                event_log: telemetryEventLog
            });
        }

        function updateConfidenceStats(confidence) {
            if (confidence <= 0) return;
            confidenceCount++;
            const delta = confidence - confidenceMean;
            confidenceMean += delta / confidenceCount;
            const delta2 = confidence - confidenceMean;
            confidenceM2 += delta * delta2;
            confidenceMin = Math.min(confidenceMin, confidence);
            confidenceMax = Math.max(confidenceMax, confidence);
        }

        function resetConfidenceStats() {
            confidenceCount = 0;
            confidenceMean = 0;
            confidenceM2 = 0;
            confidenceMin = 1.0;
            confidenceMax = 0.0;
            timeToFirstTranscriptLogged = false;
            timeStateTransitionToListening = 0;
            timeMicOpened = 0;
            timeFirstTranscriptReceived = 0;
        }

        function getConfidenceVariance() {
            return confidenceCount > 1 ? confidenceM2 / (confidenceCount - 1) : 0;
        }

        function getConfidenceStats() {
            return {
                count: confidenceCount,
                mean: confidenceMean,
                variance: getConfidenceVariance(),
                stdDev: Math.sqrt(getConfidenceVariance()),
                min: confidenceMin,
                max: confidenceMax
            };
        }

        let audioCtx = null;
        let micStream = null;              // Raw getUserMedia stream — must be stopped on session end
        let micPermanentlyBlocked = false; // Set on not-allowed / audio-capture errors
        let micSource = null;
        let analyser = null;
        let animationFrameId = null;

        function releaseAudioResources() {
            if (recognition) {
                try { recognition.abort(); } catch (e) { }
            }
            if (micStream) {
                try { micStream.getTracks().forEach(t => t.stop()); } catch (e) { }
                micStream = null;
            }
            if (audioCtx) {
                try { audioCtx.close(); } catch (e) { }
                audioCtx = null;
            }
        }

        let ambientNoiseFloor = 0.01;
        let dynamicThreshold = 0.02;
        const DYNAMIC_THRESHOLD_MULTIPLIER = 2.5;

        let lastFrameTime = 0;
        const FRAME_INTERVAL_MS = 66;

        function initializeSessionAudio() {
            if (audioCtx) return;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showMicError('Your browser does not support microphone access. Please use Chrome or Edge.');
                return;
            }

            navigator.mediaDevices.getUserMedia({ audio: true })
                .then((stream) => {
                    micStream = stream;
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    micSource = audioCtx.createMediaStreamSource(stream);
                    analyser = audioCtx.createAnalyser();
                    analyser.fftSize = 512;

                    micSource.connect(analyser);

                    setTimeout(() => calibrateNoiseFloor(), 1000);
                })
                .catch((err) => {
                    console.error("Audio Context initialization failed:", err);
                    if (err && (err.name === 'NotAllowedError' || err.name === 'NotFoundError' || err.name === 'NotReadableError')) {
                        micPermanentlyBlocked = true;
                    }
                    showMicError('Microphone access denied or unavailable. You can type your answers using the text box below.');
                });
        }

        function calibrateNoiseFloor() {
            if (!analyser) return;
            const bufferLength = analyser.fftSize;
            const dataArray = new Uint8Array(bufferLength);
            let samples = [];
            let count = 0;

            const interval = setInterval(() => {
                analyser.getByteTimeDomainData(dataArray);
                let sum = 0;
                for (let i = 0; i < bufferLength; i++) {
                    const normalized = (dataArray[i] - 128) / 128;
                    sum += normalized * normalized;
                }
                const rms = Math.sqrt(sum / bufferLength);
                samples.push(rms);
                count++;
                if (count >= 15) {
                    clearInterval(interval);
                    const avgRms = samples.reduce((a, b) => a + b, 0) / samples.length;
                    ambientNoiseFloor = Math.max(avgRms, 0.005);
                    dynamicThreshold = ambientNoiseFloor * DYNAMIC_THRESHOLD_MULTIPLIER;
                }
            }, 100);
        }

        function trackEnergyLoop(timestamp) {
            if (currentState !== State.LISTENING || !analyser) {
                animationFrameId = requestAnimationFrame(trackEnergyLoop);
                return;
            }

            if (timestamp - lastFrameTime < FRAME_INTERVAL_MS) {
                animationFrameId = requestAnimationFrame(trackEnergyLoop);
                return;
            }
            lastFrameTime = timestamp;

            const bufferLength = analyser.fftSize;
            const dataArray = new Uint8Array(bufferLength);
            analyser.getByteTimeDomainData(dataArray);

            let sum = 0;
            for (let i = 0; i < bufferLength; i++) {
                const normalized = (dataArray[i] - 128) / 128;
                sum += normalized * normalized;
            }
            const rms = Math.sqrt(sum / bufferLength);

            if (rms > dynamicThreshold) {
                lastSpeechTimestamp = Date.now();
                telemetryVADSpeechTime += FRAME_INTERVAL_MS;
            } else {
                if (currentUtterance.length === 0) {
                    ambientNoiseFloor = (ambientNoiseFloor * 0.95) + (rms * 0.05);
                    dynamicThreshold = Math.max(ambientNoiseFloor * DYNAMIC_THRESHOLD_MULTIPLIER, 0.015);
                }
            }

            animationFrameId = requestAnimationFrame(trackEnergyLoop);
        }

        function transitionTo(newState) {
            if (currentState !== newState && !ValidTransitions[currentState].includes(newState)) {
                return;
            }
            logTelemetryEvent(newState);

            switch (currentState) {
                case State.LISTENING:
                    isListening = false;
                    if (recognition) {
                        try { recognition.abort(); } catch (e) { }
                    }
                    clearSilenceTimer();
                    if (animationFrameId) cancelAnimationFrame(animationFrameId);
                    break;

                case State.AI_SPEAKING:
                    stopSpeaking();
                    break;
            }

            currentState = newState;

            switch (newState) {
                case State.AI_SPEAKING:
                    updateState("AI Speaking...", "speaking");
                    document.getElementById('micBtn').classList.add('disabled');
                    break;

                case State.WAITING:
                    timeStateTransitionToListening = Date.now();
                    if (micPermanentlyBlocked || !recognition) {
                        // Mic unusable — stay in WAITING and rely on the text-input
                        // fallback (submitTextAnswer accepts the WAITING state).
                        updateState("Type your answer below", "neutral");
                        showMicError('Microphone is unavailable. Type your answer in the box below and press Enter.');
                        break;
                    }
                    updateState("Preparing...", "neutral");
                    document.getElementById('micBtn').classList.add('disabled');
                    try { recognition.start(); } catch (e) {
                        console.warn('Recognition start failed in WAITING, retrying...', e);
                        setTimeout(() => {
                            if (currentState !== State.WAITING || micPermanentlyBlocked) return;
                            try { recognition.start(); } catch (e2) {
                                showMicError('Microphone could not start. Use the text box to type your answer.');
                            }
                        }, 500);
                    }
                    break;

                case State.LISTENING:
                    isListening = true;
                    updateState("Listening... Click Submit Answer when done", "listening");
                    document.getElementById('micBtn').innerHTML = '<i class="fas fa-microphone"></i>';
                    document.getElementById('micBtn').classList.add('active');
                    document.getElementById('micBtn').classList.remove('disabled');
                    // Show and enable the submit button
                    const submitBtn = document.getElementById('submitAnswerBtn');
                    if (submitBtn) { submitBtn.style.display = 'flex'; submitBtn.classList.remove('disabled'); }
                    lastSpeechTimestamp = Date.now();
                    if (analyser) animationFrameId = requestAnimationFrame(trackEnergyLoop);
                    if (!userInterimEl) addUserInterimLine("");
                    break;

                case State.PROCESSING:
                    updateState("Processing...", "neutral");
                    document.getElementById('micBtn').classList.add('disabled');
                    { const sb = document.getElementById('submitAnswerBtn'); if (sb) { sb.style.display = 'none'; sb.classList.add('disabled'); sb.classList.remove('has-content'); } }
                    break;

                case State.ERROR:
                    updateState("Connection issue. Retrying...", "neutral");
                    document.getElementById('micBtn').classList.add('disabled');
                    recoverRecognition();
                    break;

                case State.ENDED:
                    updateState("Completed", "neutral");
                    document.getElementById('micBtn').innerHTML = '<i class="fas fa-microphone-slash"></i>';
                    document.getElementById('micBtn').classList.remove('active');
                    document.getElementById('micBtn').classList.add('disabled');
                    stopTimer();
                    stopHealthMonitor();
                    clearSpeechWatchdog();
                    stopSpeaking();
                    releaseAudioResources();
                    break;
            }
        }

        window.onload = () => {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                alert("Web Speech API not supported.");
                return;
            }
            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            const loadVoices = () => { voices = synth.getVoices(); };
            loadVoices();
            if (synth.onvoiceschanged !== undefined) synth.onvoiceschanged = loadVoices;

            recognition.onstart = () => {
                logTelemetryEvent("MIC_OPEN");
                telemetryOnstartCount++;
                recoveryInProgress = false;
                timeMicOpened = Date.now();
                if (currentState === State.WAITING) transitionTo(State.LISTENING);
            };

            recognition.onend = () => {
                logTelemetryEvent("MIC_CLOSE");
                document.getElementById('micBtn').innerHTML = '<i class="fas fa-microphone-slash"></i>';
                document.getElementById('micBtn').classList.remove('active');
                if (currentState === State.LISTENING) transitionTo(State.ERROR);
                if (userInterimEl && !userInterimEl.classList.contains('final')) {
                    if (currentState === State.PROCESSING) { userInterimEl.remove(); userInterimEl = null; }
                }
            };

            recognition.onresult = (event) => {
                if (currentState !== State.LISTENING) return;
                reconnectAttempts = 0; // Successful speech recognized! Reset error count.
                let interimTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        logTelemetryEvent("TRANSCRIPT_RECEIVED");
                        accumulatedTranscript += event.results[i][0].transcript + ' ';
                        if (event.results[i][0].confidence > 0) updateConfidenceStats(event.results[i][0].confidence);
                        if (!timeToFirstTranscriptLogged) {
                            timeFirstTranscriptReceived = Date.now();
                            timeToFirstTranscriptLogged = true;
                        }
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
                lastSpeechTimestamp = Date.now();
                if (accumulatedTranscript || interimTranscript) {
                    clearSilenceTimer();
                    silenceTimer = setTimeout(() => onSilenceComplete(), SILENCE_MS);
                    // Enable and highlight the submit button when there is content
                    const sb = document.getElementById('submitAnswerBtn');
                    if (sb) { sb.classList.remove('disabled'); sb.classList.add('has-content'); }
                }
                currentUtterance = (accumulatedTranscript + interimTranscript).trim();
                updateUserInterimLine(currentUtterance);
                showCaption(currentUtterance);
            };

            recognition.onerror = (event) => {
                logTelemetryEvent("ERROR_" + event.error);
                telemetryOnerrorCount++;
                console.warn('SpeechRecognition error:', event.error);
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed' || event.error === 'audio-capture') {
                    // Permission denied or no usable microphone — recovery attempts
                    // are pointless; surface it and switch to the text fallback.
                    micPermanentlyBlocked = true;
                    showMicError('Microphone access is blocked or no microphone was found. Enable it in your browser (lock icon → Site settings → Microphone → Allow) and refresh, or type your answers below.');
                    updateState("Mic unavailable — type your answer", "neutral");
                    return;
                }
                if (event.error === 'no-speech' || event.error === 'network') {
                    reconnectAttempts++;
                    transitionTo(State.ERROR);
                }
            };

            document.addEventListener('fullscreenchange', () => {
                const warning = document.getElementById('warningOverlay');
                if (!document.fullscreenElement && isSessionActive) {
                    warning.classList.remove('hidden');
                    speak("Please return to full screen.");
                } else if (document.fullscreenElement) warning.classList.add('hidden');
            });

            window.addEventListener('beforeunload', () => {
                if (isSessionActive) transitionTo(State.ENDED);
                releaseAudioResources(); // always stop mic tracks, even after ENDED
            });
        };

        function resumeFullscreen() {
            document.documentElement.requestFullscreen().then(() => {
                document.getElementById('warningOverlay').classList.add('hidden');
            });
        }

        async function startSession() {
            const role = document.getElementById('roleInput').value;
            document.getElementById('introOverlay').classList.add('hidden');
            if (document.documentElement.requestFullscreen) await document.documentElement.requestFullscreen().catch(e => e);

            // Resume an active session if one exists — a refresh mid-interview
            // previously orphaned the row and reset the 20-minute lock.
            const checkRes = await apiCall({ action: 'check_active_session', company: company, drive_id: driveId });
            if (checkRes.success && checkRes.has_active) {
                sessionId = checkRes.session_id;
                isSessionActive = true;
                startTime = Date.now() - ((checkRes.elapsed_seconds || 0) * 1000);
                initializeSessionAudio();
                startTimer();
                startHealthMonitor();
                // Always show text input as an alternative to voice
                const tArea = document.getElementById('textInputArea');
                if (tArea) tArea.style.display = 'block';
                const tInput = document.getElementById('textInput');
                if (tInput) tInput.placeholder = 'Or type your answer here and press Enter...';
                (checkRes.history || []).forEach(m => {
                    if (m && m.content) appendToTranscript(m.role === 'assistant' ? 'ai' : 'user', m.content, false);
                });
                transitionTo(State.AI_SPEAKING);
                loadNextQuestion('', true); // retry flag: resuming must not record a fake skip
                return;
            }

            const res = await apiCall({ action: 'start_session', role: role, company: company, task_id: "<?php echo $taskId; ?>", drive_id: driveId, concept: concept });
            if (res.success) {
                sessionId = res.session_id;
                isSessionActive = true;
                startTime = Date.now();
                initializeSessionAudio();
                startTimer();
                startHealthMonitor();
                // Always show text input as an alternative to voice
                const tArea = document.getElementById('textInputArea');
                if (tArea) tArea.style.display = 'block';
                const tInput = document.getElementById('textInput');
                if (tInput) tInput.placeholder = 'Or type your answer here and press Enter...';
                transitionTo(State.AI_SPEAKING);
                loadNextQuestion("");
            } else {
                // Previously a failed start left a dead black screen with no feedback
                alert('Could not start the interview: ' + (res.message || 'Unknown error') + '\nPlease try again.');
                document.getElementById('introOverlay').classList.remove('hidden');
                document.getElementById('introOverlay').style.opacity = '1';
            }
        }

        let totalDurationTimer = null;
        function startTimer() {
            totalDurationTimer = setInterval(() => {
                if (!isSessionActive) return;
                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                if (isTaskId && elapsed < MIN_REQUIRED_TIME) {
                    const remaining = MIN_REQUIRED_TIME - elapsed;
                    document.getElementById('timerText').innerText = `Lock: ${Math.floor(remaining / 60)}:${(remaining % 60).toString().padStart(2, '0')}`;
                    document.getElementById('sessionTimer').className = 'locked';
                } else {
                    document.getElementById('timerText').innerText = `Duration: ${Math.floor(elapsed / 60)}:${(elapsed % 60).toString().padStart(2, '0')}`;
                    document.getElementById('sessionTimer').className = 'unlocked';
                    document.getElementById('endBtn').disabled = false;
                }
            }, 1000);
        }

        function stopTimer() { clearInterval(totalDurationTimer); }

        const QUESTION_POLL_INTERVAL_MS = 2000;
        // Must exceed the worker's worst case: AIService retries 3× with a 90s
        // cURL timeout (~272s) before a job resolves to completed/failed.
        const QUESTION_POLL_TIMEOUT_MS = 300000;

        async function loadNextQuestion(userMsg, isRetry = false) {
            transitionTo(State.PROCESSING);
            hideRetryButton();
            const payload = { action: 'get_question', session_id: sessionId, message: userMsg };
            if (isRetry) payload.retry = 1;
            const res = await apiCall(payload);
            if (res.success && res.job_id) {
                pollForQuestion(res.job_id);
            } else if (res.success && res.result) {
                // Synchronous fallback path (queue/worker unavailable server-side)
                handleQuestionPayload(res.result);
            } else {
                handleQuestionFailure(res.message || 'Failed to reach the AI service.');
            }
        }

        function handleQuestionPayload(data) {
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (e) { }
            }
            if (!data || data.success === false || !data.question) {
                handleQuestionFailure((data && data.message) || 'The AI could not generate a question.');
                return;
            }
            const text = (data.feedback ? data.feedback + ". " : "") + data.question;
            appendToTranscript('ai', data.question, false);
            speak(text);
            apiCall({ action: 'append_ai_history', session_id: sessionId, message: data.question });
        }

        function pollForQuestion(jobId) {
            const startedAt = Date.now();
            let consecutiveErrors = 0;
            const pollInterval = setInterval(async () => {
                if (currentState === State.ENDED) { clearInterval(pollInterval); return; }
                if (Date.now() - startedAt > QUESTION_POLL_TIMEOUT_MS) {
                    clearInterval(pollInterval);
                    handleQuestionFailure('The AI is taking too long to respond.');
                    return;
                }
                try {
                    const statusRes = await fetch(`ai_job_status.php?job_id=${jobId}`).then(r => r.json());
                    consecutiveErrors = 0;
                    if (statusRes.success === false) {
                        clearInterval(pollInterval);
                        handleQuestionFailure(statusRes.message || 'The question request was lost.');
                    } else if (statusRes.status === 'completed') {
                        clearInterval(pollInterval);
                        handleQuestionPayload(statusRes.result);
                    } else if (statusRes.status === 'failed') {
                        clearInterval(pollInterval);
                        handleQuestionFailure(statusRes.error || 'AI question generation failed.');
                    }
                } catch (e) {
                    console.error('Polling error:', e);
                    consecutiveErrors++;
                    if (consecutiveErrors >= 5) {
                        clearInterval(pollInterval);
                        handleQuestionFailure('Connection lost while waiting for the question.');
                    }
                }
            }, QUESTION_POLL_INTERVAL_MS);
        }

        function handleQuestionFailure(msg) {
            console.error('Question load failed:', msg);
            logTelemetryEvent("QUESTION_LOAD_FAILED");
            if (currentState === State.ENDED) return;
            // WAITING re-arms the mic AND makes the text-input fallback accept
            // submissions (it only fires in WAITING/LISTENING).
            transitionTo(State.WAITING);
            updateState("Question failed to load", "neutral");
            showMicError(msg + ' Click "Retry Question" below to try again.');
            showRetryButton();
        }

        function showRetryButton() {
            const b = document.getElementById('retryQuestionBtn');
            if (b) b.style.display = 'inline-block';
            const area = document.getElementById('textInputArea');
            if (area) area.style.display = 'block';
        }

        function hideRetryButton() {
            const b = document.getElementById('retryQuestionBtn');
            if (b) b.style.display = 'none';
        }

        function retryQuestion() {
            loadNextQuestion('', true);
        }

        function clearSilenceTimer() { if (silenceTimer) { clearTimeout(silenceTimer); silenceTimer = null; } }

        function onSilenceComplete() {
            silenceTimer = null;
            if (currentState !== State.LISTENING) return;
            const text = currentUtterance.trim();
            if (text) {
                // Gentle reminder instead of auto-submit
                logTelemetryEvent("SILENCE_REMINDER");
                telemetrySilenceTimeoutCount++;
                updateState("Paused — Click Submit Answer when you're done", "listening");
                // Pulse the submit button to draw attention
                const sb = document.getElementById('submitAnswerBtn');
                if (sb) sb.classList.add('has-content');
            }
        }

        function submitVoiceAnswer() {
            if (currentState !== State.LISTENING && currentState !== State.WAITING) return;
            const text = (currentUtterance || accumulatedTranscript || '').trim();
            if (!text) {
                // Also check the text input fallback
                const textInput = document.getElementById('textInput');
                if (textInput && textInput.value.trim()) {
                    submitTextAnswer();
                    return;
                }
                updateState("Nothing to submit — speak or type your answer first", "listening");
                return;
            }
            logTelemetryEvent("MANUAL_SUBMIT_VOICE");
            telemetrySubmissionReasons.push("manual_voice");
            clearSilenceTimer();
            finalizeUserTranscriptLine(text);
            transitionTo(State.PROCESSING);
            loadNextQuestion(text);
            accumulatedTranscript = '';
            currentUtterance = '';
            resetConfidenceStats();
        }

        function processUserAnswer(text) {
            logTelemetryEvent("MANUAL_SUBMIT");
            telemetrySubmissionReasons.push("manual");
            clearSilenceTimer();
            finalizeUserTranscriptLine(text);
            transitionTo(State.PROCESSING);
            loadNextQuestion(text);
            accumulatedTranscript = '';
            currentUtterance = '';
            resetConfidenceStats();
        }

        function clearSpeechWatchdog() { if (speechWatchdog) { clearTimeout(speechWatchdog); speechWatchdog = null; } }

        let cachedVoice = null;
        function pickVoice() {
            // `voices` was loaded via onvoiceschanged but never applied — pick a
            // stable English voice once so pronunciation is consistent across chunks.
            if (cachedVoice) return cachedVoice;
            if (!voices || voices.length === 0) voices = synth.getVoices();
            if (!voices || voices.length === 0) return null;
            cachedVoice =
                voices.find(v => v.name.includes("Microsoft Jenny")) ||
                voices.find(v => v.lang === 'en-US' && /google|microsoft/i.test(v.name)) ||
                voices.find(v => v.lang === 'en-US') ||
                voices.find(v => v.lang && v.lang.startsWith('en')) ||
                null;
            return cachedVoice;
        }

        function speak(text) {
            if (!text || !String(text).trim()) {
                // Nothing to say — go straight to listening instead of hanging in AI_SPEAKING
                if (isSessionActive && currentState !== State.ENDED) transitionTo(State.WAITING);
                return;
            }
            if (synth.speaking) synth.cancel();
            speechQueue = String(text).match(/[^.!?]+[.!?]*|[^.!?]+/g) || [String(text)];
            transitionTo(State.AI_SPEAKING);
            processSpeechQueue();
        }

        function processSpeechQueue() {
            clearSpeechWatchdog();
            if (currentState !== State.AI_SPEAKING) { speechQueue = []; return; }
            if (speechQueue.length === 0) {
                if (isSessionActive) transitionTo(State.WAITING);
                return;
            }
            const chunk = speechQueue.shift();
            const utterance = new SpeechSynthesisUtterance(chunk);
            const voice = pickVoice();
            if (voice) utterance.voice = voice;
            let advanced = false;
            const advance = () => {
                if (advanced) return;
                advanced = true;
                clearSpeechWatchdog();
                setTimeout(processSpeechQueue, 100);
            };
            utterance.onend = advance;
            // Chrome can silently drop utterances (onend never fires) — without
            // these two guards the session hangs in AI_SPEAKING and the mic never opens.
            utterance.onerror = advance;
            speechWatchdog = setTimeout(() => {
                console.warn('Speech watchdog fired — utterance stalled, advancing queue.');
                try { synth.cancel(); } catch (e) { }
                advance();
            }, Math.max(8000, chunk.length * 150));
            synth.speak(utterance);
        }

        let reconnectAttempts = 0;
        let recoveryInProgress = false;
        const MAX_RECONNECT_ATTEMPTS = 4;
        function recoverRecognition() {
            if (recoveryInProgress || currentState === State.ENDED) return;
            if (micPermanentlyBlocked) {
                // Recovery is pointless without mic permission — move to WAITING,
                // whose blocked-mic branch surfaces the text-input fallback.
                transitionTo(State.WAITING);
                return;
            }
            if (reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
                // Previously recursed forever; now give up and hand the user the
                // text-input fallback so the interview can continue.
                showMicError('The microphone keeps disconnecting. You can type your answers below instead.');
                updateState("Mic unstable — type your answer", "neutral");
                transitionTo(State.WAITING);
                return;
            }
            recoveryInProgress = true;
            setTimeout(() => {
                if (currentState === State.ENDED || micPermanentlyBlocked) { recoveryInProgress = false; return; }
                try {
                    recognition.start();
                    transitionTo(State.LISTENING);
                    recoveryInProgress = false;
                } catch (e) {
                    recoveryInProgress = false;
                    reconnectAttempts++;
                    recoverRecognition();
                }
            }, Math.min(Math.pow(2, reconnectAttempts) * 1000, 16000));
        }

        function toggleMic() {
            if (currentState === State.PROCESSING || currentState === State.ENDED) return; // don't interrupt an in-flight question
            if (currentState === State.LISTENING) processUserAnswer(currentUtterance || document.getElementById('textInput')?.value || '');
            else transitionTo(State.WAITING);
        }

        function updateState(status, visualState) {
            document.getElementById('statusText').innerText = status;
            document.getElementById('avatar').className = 'avatar-container ' + (visualState === 'speaking' ? 'state-speaking' : visualState === 'listening' ? 'state-listening' : '');
        }

        function stopSpeaking() { if (synth.speaking) synth.cancel(); }

        function showCaption(text) { /* removed — right-side transcript panel handles real-time display */ }

        function appendToTranscript(who, text, isInterim) {
            const scroll = document.getElementById('transcriptScroll');
            const el = document.createElement('div');
            el.className = 'transcript-msg ' + who;
            el.innerHTML = `<b>${who === 'ai' ? 'AI' : 'You'}</b><div>${text}</div>`;
            scroll.appendChild(el);
            scroll.scrollTop = scroll.scrollHeight;
        }

        function addUserInterimLine(text) {
            const scroll = document.getElementById('transcriptScroll');
            userInterimEl = document.createElement('div');
            userInterimEl.className = 'transcript-msg user interim';
            userInterimEl.innerHTML = `<b>You</b><div class="text">${text}</div>`;
            scroll.appendChild(userInterimEl);
        }

        function updateUserInterimLine(text) { if (userInterimEl) userInterimEl.querySelector('.text').textContent = text; }

        function finalizeUserTranscriptLine(text) {
            if (userInterimEl) { userInterimEl.classList.remove('interim'); userInterimEl.querySelector('.text').textContent = text; userInterimEl = null; }
            else appendToTranscript('user', text, false);
        }

        let healthCheckInterval = null;
        function startHealthMonitor() {
            healthCheckInterval = setInterval(() => {
                if (currentState === State.LISTENING && recognition && !isListening) transitionTo(State.ERROR);
            }, 5000);
        }

        function stopHealthMonitor() { clearInterval(healthCheckInterval); }

        async function endSession() {
            if (!confirm("End Interview?")) return;
            // Do NOT enter the terminal ENDED state before the report succeeds —
            // a failure (e.g. minimum-duration check) must leave the session usable.
            transitionTo(State.PROCESSING);
            document.getElementById('loadingOverlay').classList.remove('hidden');
            const res = await apiCall({
                action: 'generate_report_data',
                session_id: sessionId,
                telemetry: getTelemetryPayload()
            });
            document.getElementById('loadingOverlay').classList.add('hidden');
            if (res.success) {
                isSessionActive = false;
                transitionTo(State.ENDED);
                document.getElementById('finalScoreNum').innerText = res.score;
                document.getElementById('scoreModal').classList.remove('hidden');
            } else {
                alert(res.message || 'Report generation failed. Please try ending the session again.');
                transitionTo(State.WAITING); // resume the interview
            }
        }

        async function apiCall(data) {
            try {
                const formData = new FormData();
                for (const k in data) formData.append(k, data[k]);
                const response = await fetch('ai_hr_handler.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN },
                    body: formData
                });
                try {
                    return await response.json();
                } catch (parseErr) {
                    console.error('ai_hr_handler returned non-JSON (HTTP ' + response.status + ')');
                    return { success: false, message: 'The server returned an invalid response (HTTP ' + response.status + ').' };
                }
            } catch (netErr) {
                console.error('ai_hr_handler network error:', netErr);
                return { success: false, message: 'Network error. Please check your connection.' };
            }
        }

        function closeSession() { window.location.href = 'dashboard.php'; }
    </script>
</body>

</html>