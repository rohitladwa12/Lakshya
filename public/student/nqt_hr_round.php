<?php
/**
 * NQT HR Round Interface
 * Specialized behavioral interview prep for TCS NQT
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireLogin();
$fullName = getFullName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel='icon' type='image/png' href='<?php echo APP_URL; ?>/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NQT HR Round - Lakshya</title>
    <!-- In-page dialogs (replace native alert/confirm popups) -->
    <script src="../js/lakshya_dialogs.js?v=<?php echo APP_VERSION; ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary: #800000;
            --secondary: #e9c66f;
            --dark: #070707;
            --panel: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.08);
            --accent-gold: #e9c66f;
        }

        body {
            background: #000;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .header {
            padding: 15px 40px;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .main-layout {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* Left side: AI Persona & Current Question */
        .interview-stage {
            flex: 1.2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            background: radial-gradient(circle at 30% 30%, #1a0000 0%, #000 100%);
        }

        /* Right side: Transcript & Controls */
        .sidebar-interaction {
            width: 450px;
            background: var(--panel);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .ai-avatar-wrapper {
            position: relative;
            margin-bottom: 40px;
        }

        .ai-avatar {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, var(--primary), #4a0000);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            box-shadow: 0 0 60px rgba(128, 0, 0, 0.4);
            border: 4px solid transparent;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes pulse-speak {
            0% { box-shadow: 0 0 0 0 rgba(233, 198, 111, 0.4); border-color: var(--secondary); transform: scale(1); }
            50% { box-shadow: 0 0 0 40px rgba(233, 198, 111, 0); border-color: var(--secondary); transform: scale(1.05); }
            100% { box-shadow: 0 0 0 0 rgba(233, 198, 111, 0); border-color: var(--secondary); transform: scale(1); }
        }

        @keyframes pulse-listen {
            0% { box-shadow: 0 0 0 0 rgba(81, 207, 102, 0.4); border-color: #51cf66; transform: scale(1); }
            50% { box-shadow: 0 0 0 50px rgba(81, 207, 102, 0); border-color: #51cf66; transform: scale(1.1); }
            100% { box-shadow: 0 0 0 0 rgba(81, 207, 102, 0); border-color: #51cf66; transform: scale(1); }
        }

        .state-speaking { animation: pulse-speak 1.5s infinite; }
        .state-listening { animation: pulse-listen 1.5s infinite; }

        .chat-bubble {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            padding: 40px;
            border-radius: 20px;
            max-width: 650px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(30px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .q-text { font-size: 1.8rem; font-weight: 500; line-height: 1.4; color: #fff; margin: 0; }

        .transcript-container {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .msg { padding: 12px 18px; border-radius: 12px; font-size: 0.95rem; line-height: 1.5; max-width: 85%; }
        .msg.ai { background: rgba(255,255,255,0.05); border: 1px solid var(--border); align-self: flex-start; border-bottom-left-radius: 2px; }
        .msg.user { background: var(--primary); color: #fff; align-self: flex-end; border-bottom-right-radius: 2px; }

        .caption-area {
            background: rgba(81, 207, 102, 0.1);
            border: 1px dashed #51cf66;
            padding: 15px;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #51cf66;
            margin-bottom: 20px;
            min-height: 50px;
            display: none;
        }

        .bottom-controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        input {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            padding: 15px 20px;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            outline: none;
        }

        input:focus { border-color: var(--secondary); background: rgba(255,255,255,0.1); }

        .btn-round {
            width: 50px; height: 50px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.3s;
            font-size: 1.2rem;
        }

        .btn-mic { background: var(--panel); border: 1px solid var(--border); color: #fff; }
        .btn-mic.active { background: #51cf66; color: #000; border-color: #51cf66; box-shadow: 0 0 15px rgba(81, 207, 102, 0.4); }
        .btn-mic.disabled { opacity: 0.2; pointer-events: none; }

        .btn-finish {
            background: var(--primary); color: #fff; border: 1px solid rgba(255,255,255,0.1); padding: 0 20px; height: 50px; border-radius: 12px;
            font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.8rem; cursor: pointer;
        }
        .btn-finish:hover { background: #a00000; }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.98);
            z-index: 2000;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center;
            padding: 40px;
        }
        .loader-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); backdrop-filter: blur(10px);
            z-index: 3000; display: none; flex-direction: column; align-items: center; justify-content: center;
        }
        .spinner {
            width: 50px; height: 50px; border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid var(--secondary); border-radius: 50%;
            animation: spin 1s linear infinite; margin-bottom: 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .hidden { display: none !important; }
    </style>
</head>
<body>

    <!-- Intro Overlay -->
    <div id="introOverlay" class="overlay">
        <div style="background: var(--dark); padding: 50px; border-radius: 30px; border: 1px solid var(--secondary); max-width: 500px; box-shadow: 0 0 100px rgba(128,0,0,0.5);">
            <div style="font-size: 5rem; margin-bottom: 20px; color: var(--secondary);"><i class="fas fa-user-tie"></i></div>
            <h2 style="color: var(--secondary); margin-bottom: 15px; font-size: 2.2rem;">HR Proficiency Round</h2>
            <p style="opacity: 0.8; line-height: 1.8; margin-bottom: 40px; font-size: 1.1rem;">
                This is a high-fidelity behavioral simulation. We will evaluate your communication clarity, situational judgment, and core values.<br><br>
                <span style="color: var(--secondary); font-weight: 600;">Secure testing environment enabled.</span>
            </p>
            <button id="btnStartNqt" onclick="startSession()" class="btn-finish" style="width: 100%; padding: 20px; font-size: 1.1rem;">START INTERVIEW</button>
        </div>
    </div>

    <!-- Security Warning Overlay -->
    <div id="securityOverlay" class="overlay hidden">
        <i class="fas fa-shield-virus" style="color: var(--secondary); font-size: 5rem; margin-bottom: 25px;"></i>
        <h2 id="securityTitle" style="font-size: 2.5rem; margin-bottom: 20px;">Assessment Interrupted</h2>
        <p id="securityMessage" style="font-size: 1.3rem; max-width: 600px; margin-bottom: 40px; opacity: 0.8; line-height: 1.6;">
            The secure environment was breached by exiting full-screen mode. <br>
            Please re-enter to resume your session.
        </p>
        <button id="securityBtn" onclick="requestFullScreen()" class="btn-finish" style="padding: 18px 50px; font-size: 1.1rem;">RESUME ASSESSMENT</button>
    </div>

    <!-- Loader Overlay -->
    <div id="loaderOverlay" class="loader-overlay">
        <div class="spinner"></div>
        <h3 id="loaderText" style="color: var(--secondary); margin: 0; letter-spacing: 2px; text-transform: uppercase; font-size: 0.9rem;">Synthesizing Report...</h3>
        <p style="opacity: 0.6; font-size: 0.8rem; margin-top: 10px;">This may take up to 30 seconds</p>
    </div>

    <!-- Time Up Overlay -->
    <div id="timeUpOverlay" class="overlay hidden" style="z-index: 4000;">
        <i class="fas fa-clock" style="color: var(--primary); font-size: 5rem; margin-bottom: 25px;"></i>
        <h2 style="font-size: 2.5rem; margin-bottom: 20px;">Time is over please exit</h2>
        <button onclick="finishInterview(true)" class="btn-finish" style="padding: 18px 50px; font-size: 1.1rem;">EXIT ASSESSMENT</button>
    </div>

    <div class="header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="background: var(--primary); color: #fff; padding: 5px 12px; border-radius: 6px; font-weight: 800; font-size: 0.8rem;">NQT</div>
            <h2 style="color: var(--secondary); margin: 0; font-size: 1.1rem; letter-spacing: 1px; text-transform: uppercase;">Behavioral Assessment</h2>
        </div>
        <div style="display: flex; align-items: center; gap: 25px;">
            <div id="timerDisplay" style="font-size: 1.1rem; color: var(--secondary); font-weight: 600;"><i class="fas fa-clock"></i> 60:00</div>
            <div id="statusLabel" style="font-size: 0.8rem; color: #51cf66; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Ready</div>
            <div style="font-weight: 600; font-size: 0.9rem; border-left: 1px solid var(--border); padding-left: 25px;"><?php echo htmlspecialchars($fullName); ?></div>
        </div>
    </div>

    <div class="main-layout">
        <div class="interview-stage">
            <div class="ai-avatar-wrapper">
                <div id="avatar" class="ai-avatar"><i class="fas fa-user-tie"></i></div>
            </div>

            <div class="chat-bubble">
                <p id="aiText" class="q-text">Connecting to NQT Evaluator...</p>
            </div>
        </div>

        <div class="sidebar-interaction">
            <div style="font-size: 0.8rem; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-list-ul"></i> Live Transcript
            </div>
            <div id="transcript" class="transcript-container">
                <div class="msg ai">Welcome. I'll be conducting your HR evaluation today.</div>
            </div>

            <div id="captionArea" class="caption-area"></div>

            <div class="bottom-controls">
                <input type="text" id="userInput" placeholder="Type or speak your answer..." onkeypress="if(event.key==='Enter') sendAnswer()">
                <button id="micBtn" class="btn-round btn-mic" onclick="toggleMic()" title="Voice Input"><i class="fas fa-microphone"></i></button>
                <button id="submitAnswerBtn" class="btn-round" onclick="sendAnswer()" title="Submit Answer" style="background:linear-gradient(135deg,#4CAF50,#2E7D32);color:white;font-weight:700;font-size:0.85rem;padding:0 16px;border:none;border-radius:25px;cursor:pointer;display:none;"><i class="fas fa-paper-plane"></i> Submit</button>
                <button class="btn-finish" onclick="finishInterview()" title="End Session">Finish</button>
            </div>
        </div>
    </div>

    <script>
        window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
    </script>
    <script src="<?php echo APP_URL; ?>/js/security_interceptor.js?v=<?php echo APP_VERSION; ?>"></script>
    <script>
        let sessionId = null;
        let recognition;
        let synth = window.speechSynthesis;

        const State = {
            IDLE: 'IDLE',
            AI_SPEAKING: 'AI_SPEAKING',
            WAITING: 'WAITING',
            LISTENING: 'LISTENING',
            PROCESSING: 'PROCESSING',
            ERROR: 'ERROR',
            ENDED: 'ENDED'
        };

        // IDLE->PROCESSING: first question load. WAITING/AI_SPEAKING/ERROR->PROCESSING: a typed
        // answer submitted while the mic is preparing / the AI is speaking / the mic is recovering.
        // PROCESSING->WAITING: recovery after a failed question load. ERROR->WAITING: give up on
        // mic recovery and fall back to typing (was blocked, leaving the session stuck in ERROR).
        const ValidTransitions = {
            [State.IDLE]: [State.AI_SPEAKING, State.PROCESSING, State.ENDED],
            [State.AI_SPEAKING]: [State.WAITING, State.PROCESSING, State.ERROR, State.ENDED],
            [State.WAITING]: [State.LISTENING, State.PROCESSING, State.ERROR, State.ENDED],
            [State.LISTENING]: [State.PROCESSING, State.ERROR, State.ENDED],
            [State.PROCESSING]: [State.AI_SPEAKING, State.WAITING, State.ERROR, State.ENDED],
            [State.ERROR]: [State.LISTENING, State.WAITING, State.PROCESSING, State.ENDED],
            [State.ENDED]: []
        };

        let currentState = State.IDLE;
        let isListening = false;
        let micStream = null;               // getUserMedia stream - its tracks must be stopped at the end
        let micPermanentlyBlocked = false;  // permission denied / no microphone
        let micAutoRetryDisabled = false;   // repeated mic drops; the mic button re-enables voice
        let isFinishing = false;
        let sessionStarting = false;
        const IS_MAC = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent || '');
        let silenceTimer = null;
        let currentUtterance = "";
        let accumulatedTranscript = "";
        let lastSpeechTimestamp = Date.now();
        let timeRemaining = 3600;
        let timerInterval = null;

        let voices = [];
        let speechQueue = [];
        let speechWatchdog = null;

        // Telemetry Statistics (Welford's Online Algorithm)
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

        let startTime = null; // Declare startTime for time differences

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

        // Web Audio VAD & Energy Analysis
        let audioCtx = null;
        let micSource = null;
        let analyser = null;
        let animationFrameId = null;

        let ambientNoiseFloor = 0.01;
        let dynamicThreshold = 0.02;
        const DYNAMIC_THRESHOLD_MULTIPLIER = 2.5;

        let lastFrameTime = 0;
        const FRAME_INTERVAL_MS = 66; // ~15 Hz

        // Safari only lets an AudioContext / speechSynthesis start from a user gesture.
        // Called synchronously from the Start click, before any await.
        function unlockAudioOnGesture() {
            try {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!audioCtx && AC) audioCtx = new AC();
                if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
            } catch (e) { console.warn('AudioContext unlock failed:', e); }
            try {
                if (synth && !synth.speaking) {
                    const u = new SpeechSynthesisUtterance(' ');
                    u.volume = 0;
                    synth.speak(u);
                }
            } catch (e) {}
        }
        document.addEventListener('click', () => {
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
        });

        function micErrorMessage(err) {
            const name = err && err.name;
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
                return 'Microphone permission was denied. Allow the microphone for this site (lock icon in the address bar → Microphone → Allow) and refresh.' +
                    (IS_MAC ? ' On a Mac, also enable your browser under System Settings → Privacy & Security → Microphone, then restart the browser.' : '') +
                    ' You can type your answers in the box meanwhile.';
            }
            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'No microphone was found. Connect a microphone and refresh, or type your answers in the box.';
            if (name === 'NotReadableError' || name === 'TrackStartError') return 'Your microphone is being used by another application. Close it and refresh, or type your answers in the box.';
            return 'The microphone is unavailable. You can type your answers in the box.';
        }

        let micWarningShown = false;
        function showMicUnavailable(msg) {
            updateState("Mic unavailable — type your answer", "neutral");
            if (micWarningShown) return;
            micWarningShown = true;
            LakshyaDialog.alert(msg, { title: 'Microphone Unavailable', type: 'warning' });
        }

        // Returns Promise<boolean>
        function initializeSessionAudio() {
            if (micStream && micStream.getAudioTracks().some(t => t.readyState === 'live')) return Promise.resolve(true);
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return Promise.resolve(false);

            return navigator.mediaDevices.getUserMedia({ audio: true })
                .then((stream) => {
                    micStream = stream;
                    try {
                        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
                        micSource = audioCtx.createMediaStreamSource(stream);
                        analyser = audioCtx.createAnalyser();
                        analyser.fftSize = 512;

                        micSource.connect(analyser);

                        setTimeout(() => calibrateNoiseFloor(), 1000);
                    } catch (e) {
                        // Analyser is telemetry only - never block the interview on it
                        console.warn('Audio analyser setup failed:', e);
                        analyser = null;
                    }
                    return true;
                })
                .catch((err) => {
                    console.error("Audio Context initialization failed:", err);
                    if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError' || err.name === 'NotFoundError' || err.name === 'NotReadableError')) {
                        micPermanentlyBlocked = true;
                    }
                    showMicUnavailable(micErrorMessage(err));
                    return false;
                });
        }

        function releaseAudioResources() {
            if (recognition) {
                try { recognition.abort(); } catch (e) {}
            }
            if (micStream) {
                try { micStream.getTracks().forEach(t => t.stop()); } catch (e) {}
                micStream = null;
            }
            if (audioCtx) {
                try { audioCtx.close(); } catch (e) {}
                audioCtx = null;
            }
            analyser = null;
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

        // FSM Transition Logic
        function transitionTo(newState) {
            if (currentState !== newState && !ValidTransitions[currentState].includes(newState)) {
                console.warn(`Blocked invalid FSM state transition: ${currentState} -> ${newState}`);
                return;
            }
            logTelemetryEvent(newState);

            console.log(`FSM Transition: ${currentState} -> ${newState}`);

            // 1. EXIT STATE (Cleanup)
            switch (currentState) {
                case State.LISTENING:
                    isListening = false;
                    if (recognition) {
                        try { recognition.abort(); } catch(e) {}
                    }
                    clearSilenceTimer();
                    if (animationFrameId) cancelAnimationFrame(animationFrameId);
                    break;

                case State.AI_SPEAKING:
                    stopSpeaking();
                    break;
            }

            currentState = newState;

            // 2. ENTER STATE (Setup)
            switch (newState) {
                case State.AI_SPEAKING:
                    updateState("Speaking", "speaking");
                    document.getElementById('micBtn').classList.add('disabled');
                    break;

                case State.WAITING:
                    updateState("Preparing...", "neutral");
                    document.getElementById('micBtn').classList.add('disabled');
                    timeStateTransitionToListening = Date.now();
                    if (!recognition || micPermanentlyBlocked || micAutoRetryDisabled) {
                        // Mic unusable - wait for a typed answer (sendAnswer accepts WAITING)
                        updateState(micAutoRetryDisabled && recognition && !micPermanentlyBlocked
                            ? "Mic unstable — type your answer or click the mic"
                            : "Type your answer", "neutral");
                        document.getElementById('micBtn').classList.toggle('disabled', !recognition || micPermanentlyBlocked);
                        break;
                    }
                    try { recognition.start(); } catch(e) {
                        // Usually "already started" right after an abort - retry once
                        setTimeout(() => {
                            if (currentState !== State.WAITING) return;
                            try { recognition.start(); } catch (e2) { console.warn('Recognition start failed:', e2); }
                        }, 500);
                    }
                    break;

                case State.LISTENING:
                    isListening = true;
                    updateState("Listening... Click Submit when done", "listening");
                    document.getElementById('micBtn').innerHTML = '<i class="fas fa-microphone"></i>';
                    document.getElementById('micBtn').classList.add('active');
                    document.getElementById('micBtn').classList.remove('disabled');

                    lastSpeechTimestamp = Date.now();
                    // Show submit button while listening
                    { const sb = document.getElementById('submitAnswerBtn'); if (sb) sb.style.display = 'inline-block'; }

                    if (analyser) {
                        animationFrameId = requestAnimationFrame(trackEnergyLoop);
                    }
                    break;

                case State.PROCESSING:
                    updateState("Thinking", "neutral");
                    document.getElementById('micBtn').classList.add('disabled');
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

                    clearInterval(timerInterval);
                    stopHealthMonitor();
                    stopSpeaking();
                    // Previously only the AudioContext was closed: the mic tracks stayed live
                    // (macOS kept showing the orange mic indicator) and recognition kept running.
                    releaseAudioResources();
                    break;
            }
        }

        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                timeRemaining--;
                let m = Math.floor(timeRemaining / 60).toString().padStart(2, '0');
                let s = (timeRemaining % 60).toString().padStart(2, '0');
                document.getElementById('timerDisplay').innerHTML = `<i class="fas fa-clock"></i> ${m}:${s}`;

                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    handleTimeUp();
                }
            }, 1000);
        }

        function handleTimeUp() {
            document.getElementById('securityOverlay').classList.add('hidden');
            document.getElementById('timeUpOverlay').classList.remove('hidden');
            transitionTo(State.ENDED);
        }

        // Security Features
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'v' || e.key === 'x')) e.preventDefault();
        });

        // Voices load asynchronously (Safari/Chrome). A missing speechSynthesis used to throw
        // a ReferenceError here and kill the whole script.
        function loadVoices() {
            if (synth) voices = synth.getVoices();
        }
        loadVoices();
        if (synth && synth.onvoiceschanged !== undefined) synth.onvoiceschanged = loadVoices;

        // --- Fullscreen helpers (Safari < 16.4 only has the webkit-prefixed API) ---
        function getFullscreenElement() {
            return document.fullscreenElement || document.webkitFullscreenElement || null;
        }
        function isFullscreenSupported() {
            const el = document.documentElement;
            return !!(el.requestFullscreen || el.webkitRequestFullscreen);
        }
        function requestFullscreenCompat() {
            const el = document.documentElement;
            try {
                if (el.requestFullscreen) return Promise.resolve(el.requestFullscreen());
                if (el.webkitRequestFullscreen) { el.webkitRequestFullscreen(); return Promise.resolve(); }
            } catch (e) { return Promise.reject(e); }
            return Promise.reject(new Error('Fullscreen API not supported'));
        }
        function exitFullscreenCompat() {
            try {
                if (document.exitFullscreen && document.fullscreenElement) return document.exitFullscreen().catch(() => {});
                if (document.webkitExitFullscreen && document.webkitFullscreenElement) document.webkitExitFullscreen();
            } catch (e) {}
            return Promise.resolve();
        }
        function showSecurityOverlay(title, message, btnText) {
            document.getElementById('securityTitle').textContent = title;
            document.getElementById('securityMessage').textContent = message;
            document.getElementById('securityBtn').textContent = btnText;
            document.getElementById('securityOverlay').classList.remove('hidden');
        }
        function onPageFullscreenChange() {
            if (!getFullscreenElement() && sessionId && !isFinishing) {
                showSecurityOverlay('Assessment Interrupted', 'The secure environment was breached by exiting full-screen mode. Please re-enter to resume your session.', 'RESUME ASSESSMENT');
            } else if (getFullscreenElement()) {
                document.getElementById('securityOverlay').classList.add('hidden');
            }
        }

        window.onload = function() {
            // Registered before the speech-recognition check, which used to return early and skip them
            document.addEventListener('fullscreenchange', onPageFullscreenChange);
            document.addEventListener('webkitfullscreenchange', onPageFullscreenChange);

            window.addEventListener('beforeunload', () => {
                // Cleanup only - never preventDefault/returnValue (that shows the native "Leave site?" popup)
                if (sessionId) {
                    transitionTo(State.ENDED);
                }
                releaseAudioResources();
            });

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                // The interview still works with typed answers - inform instead of dead-ending
                document.getElementById('micBtn').classList.add('disabled');
                LakshyaDialog.alert('Voice answers are not supported in this browser, so you will type your answers instead. For voice answers, use Google Chrome, Microsoft Edge or Safari.', { title: 'Voice Input Unavailable', type: 'warning' });
                return;
            }

            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = () => {
                logTelemetryEvent("MIC_OPEN");
                telemetryOnstartCount++;
                recoveryInProgress = false;
                timeMicOpened = Date.now();
                if (currentState === State.WAITING) {
                    transitionTo(State.LISTENING);
                }
            };

            recognition.onend = () => {
                logTelemetryEvent("MIC_CLOSE");
                document.getElementById('micBtn').innerHTML = '<i class="fas fa-microphone-slash"></i>';
                document.getElementById('micBtn').classList.remove('active');
                if (currentState === State.LISTENING) {
                    console.log("Mic unexpectedly stopped in LISTENING state. Recovering...");
                    transitionTo(State.ERROR);
                }
                document.getElementById('captionArea').style.display = 'none';
            };

            recognition.onresult = (event) => {
                if (currentState !== State.LISTENING) return;
                reconnectAttempts = 0; // Successful speech recognized! Reset error count.
                let interimTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        logTelemetryEvent("TRANSCRIPT_RECEIVED");
                        accumulatedTranscript += event.results[i][0].transcript + ' ';
                        if (event.results[i][0].confidence > 0) {
                            updateConfidenceStats(event.results[i][0].confidence);
                        }
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
                    silenceTimer = setTimeout(() => onSilenceComplete(), 45000); // 45s gentle reminder only
                }
                currentUtterance = (accumulatedTranscript + interimTranscript).trim();
                showCaption(currentUtterance);
            };

            recognition.onerror = (event) => {
                logTelemetryEvent("ERROR_" + event.error);
                telemetryOnerrorCount++;
                console.error("Speech Recognition Error:", event.error);
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed' || event.error === 'audio-capture') {
                    // Retrying is pointless without permission / a microphone - fall back to typing
                    micPermanentlyBlocked = true;
                    let msg = 'Microphone access is blocked or no microphone was found. Allow the microphone for this site (lock icon → Site settings → Microphone → Allow) and refresh, or type your answers in the box.';
                    if (event.error === 'service-not-allowed' && IS_MAC) {
                        msg = 'Voice recognition is turned off on this Mac. Allow speech recognition for this site, enable Dictation (System Settings → Keyboard → Dictation) and allow your browser under System Settings → Privacy & Security → Microphone / Speech Recognition, then restart the browser — or type your answers in the box.';
                    } else if (IS_MAC) {
                        msg += ' On a Mac, also enable your browser under System Settings → Privacy & Security → Microphone, then restart the browser.';
                    }
                    showMicUnavailable(msg);
                    return;
                }
                if (event.error === 'no-speech' || event.error === 'network') {
                    reconnectAttempts++;
                    transitionTo(State.ERROR);
                }
            };

            // Visibility lifecycle change
            document.addEventListener("visibilitychange", () => {
                if (document.visibilityState === "hidden") {
                    console.log("Tab hidden. Pausing session...");
                    if (currentState === State.LISTENING) {
                        if (recognition) {
                            try { recognition.abort(); } catch(e) {}
                        }
                    }
                } else {
                    console.log("Tab visible. Resuming session...");
                    if (currentState === State.LISTENING) {
                        recoverRecognition();
                    }
                }
            });

        };

        // Previously an unhandled requestFullscreen() rejection (Safari < 16.4 has only the
        // webkit API; Chrome rejects without a user gesture) aborted startSession entirely.
        async function requestFullScreen() {
            if (!isFullscreenSupported()) {
                document.getElementById('securityOverlay').classList.add('hidden');
                return true;
            }
            try {
                await requestFullscreenCompat();
                document.getElementById('securityOverlay').classList.add('hidden');
                return true;
            } catch (e) {
                console.warn('Fullscreen request failed:', e);
                const msgEl = document.getElementById('securityMessage');
                if (msgEl && !document.getElementById('securityOverlay').classList.contains('hidden') && !msgEl.querySelector('.fs-blocked-hint')) {
                    const hint = document.createElement('span');
                    hint.className = 'fs-blocked-hint';
                    hint.style.cssText = 'display:block;margin-top:14px;color:#f59e0b;font-size:1rem;';
                    hint.textContent = 'Your browser blocked full screen. Please click the button again, and allow full screen if your browser asks.';
                    msgEl.appendChild(hint);
                }
                return false;
            }
        }

        async function startSession() {
            if (sessionStarting || sessionId) return; // double-click guard
            sessionStarting = true;
            unlockAudioOnGesture(); // synchronous, inside the click (Safari)
            const startBtn = document.getElementById('btnStartNqt');
            if (startBtn) startBtn.disabled = true;

            document.getElementById('introOverlay').classList.add('hidden');
            const fsOk = await requestFullScreen();
            if (!fsOk) {
                // Not counted against the student - just ask them to click once more
                showSecurityOverlay('Full Screen Required', 'Click the button below to continue the assessment in full screen.', 'ENTER FULL SCREEN');
            }

            let data = null;
            try {
                const res = await fetch('nqt_hr_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=start_session'
                });
                data = await res.json();
            } catch (e) {
                console.error('start_session failed:', e);
            }
            if (data && data.success) {
                startTime = Date.now();
                sessionId = data.session_id;
                sessionStarting = false;
                initializeSessionAudio();
                startTimer();
                startHealthMonitor();
                getQuestion();
            } else {
                // Previously a failure left a black screen with no feedback
                sessionStarting = false;
                if (startBtn) startBtn.disabled = false;
                document.getElementById('securityOverlay').classList.add('hidden');
                document.getElementById('introOverlay').classList.remove('hidden');
                exitFullscreenCompat();
                await LakshyaDialog.alert('Could not start the interview: ' + ((data && data.message) || 'Network or server error') + '\nPlease try again.', { title: 'Interview Not Started', type: 'error' });
            }
        }

        async function getQuestion(msg = '') {
            if (!sessionId || currentState === State.ENDED) return;
            transitionTo(State.PROCESSING);
            const requestSessionId = sessionId;
            let data = null;
            try {
                const res = await fetch('nqt_hr_handler', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_question', session_id: requestSessionId, message: msg })
                });
                data = await res.json();
            } catch (e) {
                console.error('get_question failed:', e);
            }
            // Session finished / timed out while the request was in flight
            if (!sessionId || sessionId !== requestSessionId || currentState === State.ENDED) return;
            const question = data && data.success && data.data && data.data.question;
            if (question) {
                document.getElementById('aiText').innerText = question;
                appendToTranscript('ai', question);
                speak(question);
                return;
            }
            // Previously a failure left the session stuck in "Thinking" forever
            document.getElementById('aiText').innerText = 'The evaluator could not respond.';
            const retry = await LakshyaDialog.confirm('The AI evaluator could not generate the next question (' + ((data && data.message) || 'network or server error') + ').', {
                title: 'Question Not Loaded', type: 'error', okText: 'Retry', cancelText: 'Edit My Answer'
            });
            if (!sessionId || currentState === State.ENDED) return;
            if (retry) {
                getQuestion(msg);
            } else {
                // The answer was not saved server-side (history is stored only on success) - let them resend it
                const input = document.getElementById('userInput');
                if (input && msg) input.value = msg;
                transitionTo(State.WAITING);
            }
        }

        function clearSilenceTimer() {
            if (silenceTimer) {
                clearTimeout(silenceTimer);
                silenceTimer = null;
            }
        }

        function onSilenceComplete() {
            silenceTimer = null;
            if (currentState !== State.LISTENING) return;

            const text = currentUtterance.trim();
            if (text) {
                // Gentle reminder instead of auto-submit
                telemetrySilenceTimeoutCount++;
                updateState("Paused — Click Submit when you're done", "listening");
                const sb = document.getElementById('submitAnswerBtn');
                if (sb) sb.style.display = 'inline-block';
            }
        }

        function clearSpeechWatchdog() {
            if (speechWatchdog) {
                clearTimeout(speechWatchdog);
                speechWatchdog = null;
            }
        }

        function speak(text) {
            window.currentUtteranceObj = null;
            clearSpeechWatchdog();
            text = String(text || '');
            if (!synth) {
                // No speech synthesis - the question is on screen; go straight to answering
                transitionTo(State.AI_SPEAKING);
                speechQueue = [];
                processSpeechQueue();
                return;
            }
            if (synth.speaking) synth.cancel();

            if (recognition) {
                try { recognition.abort(); } catch(e) {}
            }

            speechQueue = [];

            let cleanText = text.replace(/\[END_INTERVIEW\]/g, '')
                                .replace(/\*\*/g, '')
                                .replace(/- /g, ', ')
                                .replace(/\n/g, '. ')
                                .replace(/=/g, ' equals ')
                                .replace(/\+/g, ' plus ')
                                .replace(/(\d+):(\d+)/g, '$1 $2');

            const chunks = cleanText.match(/[^.!?]+[.!?]*|[^.!?]+/g) || [cleanText];
            chunks.forEach(c => {
                const trimmed = c.trim();
                if (trimmed.length > 0) speechQueue.push(trimmed);
            });

            transitionTo(State.AI_SPEAKING);
            processSpeechQueue();
        }

        function processSpeechQueue() {
            if (speechQueue.length === 0) {
                clearSpeechWatchdog();
                currentUtterance = "";
                accumulatedTranscript = "";
                if (sessionId && currentState === State.AI_SPEAKING) {
                    transitionTo(State.WAITING);
                }
                return;
            }

            // Stop if the interview ended / the state moved on (e.g. a typed answer) mid-speech
            if (currentState !== State.AI_SPEAKING) { speechQueue = []; clearSpeechWatchdog(); return; }

            const text = speechQueue.shift();
            const utterance = new SpeechSynthesisUtterance(text);
            window.currentUtteranceObj = utterance; // also keeps Safari from garbage-collecting it
            utterance.lang = 'en-US';

            if (!voices || voices.length === 0) loadVoices();
            const preferredVoice =
                voices.find(v => v.name.includes("Microsoft Jenny")) ||
                voices.find(v => v.name.includes("Google US English") || v.name.includes("Female")) ||
                voices.find(v => v.lang === 'en-US') ||
                voices.find(v => v.lang && v.lang.startsWith('en'));
            if (preferredVoice) utterance.voice = preferredVoice;

            utterance.rate = 1.0;

            const safetyTimeout = (text.length * 150) + 5000;
            clearSpeechWatchdog();
            speechWatchdog = setTimeout(() => {
                console.warn("Speech Synthesis watchdog triggered. Force ending current chunk.");
                if (window.currentUtteranceObj === utterance) {
                    window.currentUtteranceObj = null;
                    synth.cancel();
                    processSpeechQueue();
                }
            }, safetyTimeout);

            utterance.onend = () => {
                if (window.currentUtteranceObj !== utterance) return;
                clearSpeechWatchdog();
                setTimeout(() => {
                    processSpeechQueue();
                }, 100);
            };

            utterance.onerror = (event) => {
                if (window.currentUtteranceObj !== utterance) return;
                clearSpeechWatchdog();
                console.error("Speech Synthesis error in chunk:", event);
                setTimeout(() => {
                    processSpeechQueue();
                }, 100);
            };

            synth.speak(utterance);
        }

        let reconnectAttempts = 0;
        let recoveryInProgress = false;
        const MAX_RECONNECT_ATTEMPTS = 4;

        function recoverRecognition() {
            if (currentState !== State.ERROR || recoveryInProgress) return;
            if (reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
                console.warn("Too many reconnect attempts. Switching to text-only mode.");
                // Stops WAITING from instantly re-starting the mic (endless start/end loop)
                micAutoRetryDisabled = true;
                transitionTo(State.WAITING);
                return;
            }

            recoveryInProgress = true;
            const delay = Math.min(Math.pow(2, reconnectAttempts) * 1000, 16000);
            console.log(`Scheduling recognition restart in ${delay}ms (Attempt #${reconnectAttempts + 1})`);

            setTimeout(() => {
                // A typed answer / end of session may have moved the state on meanwhile
                if (currentState !== State.ERROR || micPermanentlyBlocked) { recoveryInProgress = false; return; }
                try {
                    recognition.start();
                    transitionTo(State.LISTENING);
                } catch (e) {
                    console.warn("Failed to restart recognition:", e);
                    reconnectAttempts++;
                    recoveryInProgress = false;
                    recoverRecognition();
                } finally {
                    recoveryInProgress = false;
                }
            }, delay);
        }

        function toggleMic() {
            if (!sessionId || currentState === State.AI_SPEAKING || currentState === State.PROCESSING || currentState === State.ENDED) return;
            if (currentState === State.LISTENING) {
                sendAnswer(currentUtterance, true);
            } else {
                if (micAutoRetryDisabled) { micAutoRetryDisabled = false; reconnectAttempts = 0; }
                transitionTo(State.WAITING);
            }
        }

        function showCaption(text) {
            const cap = document.getElementById('captionArea');
            cap.innerText = text;
            cap.style.display = text ? 'block' : 'none';
        }

        function appendToTranscript(who, text) {
            const container = document.getElementById('transcript');
            const el = document.createElement('div');
            el.className = 'msg ' + who;
            el.innerText = text;
            container.appendChild(el);
            container.scrollTop = container.scrollHeight;
        }

        function sendAnswer(val = "", isManual = false) {
            // No session yet / already processing (double submit) / finished
            if (!sessionId || isFinishing || currentState === State.IDLE || currentState === State.PROCESSING || currentState === State.ENDED) return;
            if (!val) {
                const input = document.getElementById('userInput');
                val = input.value.trim();
                if (val) {
                    input.value = "";
                } else {
                    // The Submit button shown while listening used to read only the text box,
                    // silently dropping the spoken answer
                    val = (currentUtterance || accumulatedTranscript || '').trim();
                }
                isManual = true;
            }
            if (!val) return;

            if (isManual) {
                logTelemetryEvent("MANUAL_SUBMIT");
                telemetrySubmissionReasons.push("manual");
            } else {
                logTelemetryEvent("SILENCE_TIMEOUT");
                telemetrySubmissionReasons.push("silence");
            }
            appendToTranscript('user', val);
            document.getElementById('aiText').innerText = "Analyzing response...";
            getQuestion(val);
            currentUtterance = "";
            accumulatedTranscript = "";
            resetConfidenceStats();
        }

        function updateState(status, visualState) {
            document.getElementById('statusLabel').innerText = status;
            const avatar = document.getElementById('avatar');
            avatar.className = 'ai-avatar';
            if (visualState === 'speaking') avatar.classList.add('state-speaking');
            if (visualState === 'listening') avatar.classList.add('state-listening');
        }

        function showLoader(text = "Synthesizing Report...") {
            document.getElementById('loaderText').innerText = text;
            document.getElementById('loaderOverlay').style.display = 'flex';
        }

        function hideLoader() {
            document.getElementById('loaderOverlay').style.display = 'none';
        }

        async function apiCall(data) {
            try {
                const formData = new FormData();
                for (const k in data) formData.append(k, data[k]);
                const response = await fetch('nqt_hr_handler', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                return await response.json();
            } catch (e) {
                console.error(e);
                return { success: false, message: e.message };
            }
        }

        let healthCheckInterval = null;
        function startHealthMonitor() {
            if (healthCheckInterval) clearInterval(healthCheckInterval);
            healthCheckInterval = setInterval(() => {
                if (!sessionId) return;

                if (currentState === State.LISTENING) {
                    if (audioCtx && audioCtx.state === 'suspended') {
                        console.warn("AudioContext suspended during LISTENING. Resuming...");
                        audioCtx.resume();
                    }
                    if (recognition && !recoveryInProgress && !isListening) {
                        console.warn("Speech recognition lost in LISTENING state. Retrying...");
                        transitionTo(State.ERROR);
                    }
                }
            }, 5000);
        }

        function stopHealthMonitor() {
            if (healthCheckInterval) {
                clearInterval(healthCheckInterval);
                healthCheckInterval = null;
            }
        }

        function stopSpeaking() {
            window.currentUtteranceObj = null;
            clearSpeechWatchdog();
            speechQueue = [];
            if (synth && synth.speaking) synth.cancel();
        }

        // The report is server-built HTML that embeds AI output and the student's name unescaped.
        // Parse it inertly and drop scripts / event-handler attributes before it touches the page.
        function sanitizeReportHtml(html) {
            const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
            doc.querySelectorAll('script, iframe, object, embed, link, meta, base, form').forEach(n => n.remove());
            doc.querySelectorAll('*').forEach(el => {
                for (const attr of Array.from(el.attributes)) {
                    const name = attr.name.toLowerCase();
                    const val = String(attr.value || '').trim().toLowerCase();
                    if (name.startsWith('on') || ((name === 'href' || name === 'src' || name === 'xlink:href') && val.startsWith('javascript:'))) {
                        el.removeAttribute(attr.name);
                    }
                }
            });
            const styles = Array.from(doc.querySelectorAll('style')).map(s => s.outerHTML).join('');
            return styles + doc.body.innerHTML;
        }

        // isTimeUp: called from the "Time is over" overlay - no confirmation needed
        async function finishInterview(isTimeUp = false) {
            if (isFinishing) return;
            if (!sessionId) {
                if (currentState === State.IDLE) {
                    LakshyaDialog.alert('The interview has not started yet. Click "Start Interview" first.', { title: 'Interview Not Started', type: 'info' });
                }
                return;
            }
            if (isTimeUp !== true) {
                isFinishing = true; // blocks a second Finish click while the dialog is open
                const ok = await LakshyaDialog.confirm('Progress will be finalized and your report will be generated. You cannot continue the session after this.', {
                    title: 'End NQT HR Session?', type: 'warning', okText: 'End Session', cancelText: 'Continue', danger: true
                });
                isFinishing = false;
                if (!ok || !sessionId) return;
            }
            isFinishing = true;

            showLoader("Finalizing Session...");
            const curSessionId = sessionId; // Capture it
            sessionId = null;

            transitionTo(State.ENDED);

            const telemetryData = getTelemetryPayload();

            document.getElementById('securityOverlay').classList.add('hidden');
            exitFullscreenCompat();

            // 1. Submit/Finalize with Telemetry
            await apiCall({
                action: 'submit_interview',
                session_id: curSessionId,
                telemetry: telemetryData
            });

            await generateAndSaveReport(curSessionId);
        }

        async function generateAndSaveReport(curSessionId) {
            // 2. Generate Report Data
            showLoader("Analyzing Performance...");
            const res = await apiCall({ action: 'generate_report_data', session_id: curSessionId });

            if (!res.success) {
                hideLoader();
                const retry = await LakshyaDialog.confirm('Critical Failure: ' + (res.message || 'Report generation failed.') + '\nYour answers are saved. Retry generating the report?', {
                    title: 'Report Not Generated', type: 'error', okText: 'Retry', cancelText: 'Go to Dashboard'
                });
                if (retry) return generateAndSaveReport(curSessionId);
                window.location.href = 'dashboard';
                return;
            }

            showLoader("Generating PDF...");
            const element = document.createElement('div');
            element.innerHTML = sanitizeReportHtml(res.report_html);

            const opt = {
                margin: 0.5,
                filename: res.filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            try {
                const blob = await html2pdf().set(opt).from(element).outputPdf('blob');
                const formData = new FormData();
                formData.append('action', 'save_pdf_report');
                formData.append('session_id', curSessionId);
                formData.append('pdf', blob, res.filename);

                const upload = await fetch('nqt_hr_handler', { method: 'POST', body: formData });
                const uploadRes = await upload.json();

                if (uploadRes.success) {
                    showLoader("Downloading Report...");
                    // Await the download before navigating away (it used to race the redirect)
                    try { await html2pdf().set(opt).from(element).save(); } catch (e) { console.warn('PDF download failed:', e); }
                    hideLoader();
                    await LakshyaDialog.alert("HR Assessment Completed. Your behavioral report has been saved.", { title: 'Assessment Complete', type: 'success', okText: 'Go to Dashboard' });
                    window.location.href = 'dashboard';
                } else {
                    hideLoader();
                    const retry = await LakshyaDialog.confirm("Failed to save report to server: " + (uploadRes.message || "Unknown error"), {
                        title: 'Report Not Saved', type: 'error', okText: 'Retry', cancelText: 'Go to Dashboard'
                    });
                    if (retry) return generateAndSaveReport(curSessionId);
                    window.location.href = 'dashboard';
                }
            } catch (e) {
                // Previously an error here left the loader spinning forever
                console.error('PDF generation/upload failed:', e);
                hideLoader();
                const retry = await LakshyaDialog.confirm('The report PDF could not be created or uploaded. Check your connection and retry.', {
                    title: 'Report Not Saved', type: 'error', okText: 'Retry', cancelText: 'Go to Dashboard'
                });
                if (retry) return generateAndSaveReport(curSessionId);
                window.location.href = 'dashboard';
            }
        }
    </script>
</body>
</html>

