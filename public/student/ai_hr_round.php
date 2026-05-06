<?php
require_once __DIR__ . '/../../config/bootstrap.php';
use App\Helpers\SessionFilterHelper;

requireLogin();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
            --primary: #c10505; /* Maroon */
            --accent: #ffd700; /* Gold */
            --glass: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
            --text: #ffffff;
        }

        * { box-sizing: border-box; }
        body { 
            margin: 0; padding: 0; 
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: var(--text); 
            font-family: 'Inter', sans-serif; 
            height: 100vh; 
            display: flex; flex-direction: column; overflow: hidden; 
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Fullscreen Overlay */
        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            z-index: 2000;
            display: flex; justify-content: center; align-items: center;
            flex-direction: column;
            transition: opacity 0.5s ease;
        }
        .hidden { display: none !important; opacity: 0; pointer-events: none; }

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
            background: rgba(0,0,0,0.3);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-shadow: 0 0 40px rgba(0,0,0,0.6);
            border: 2px solid var(--border);
            transition: all 0.3s;
            margin-bottom: 2rem;
        }

        .avatar-img {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            border: 3px solid rgba(255,255,255,0.1);
        }

        /* Pulse Animation for Speaking/Listening */
        @keyframes pulse-speak {
            0% { box-shadow: 0 0 0 0 rgba(233, 198, 111, 0.4); border-color: var(--accent); transform: scale(1); }
            50% { box-shadow: 0 0 0 40px rgba(233, 198, 111, 0); border-color: var(--accent); transform: scale(1.02); }
            100% { box-shadow: 0 0 0 0 rgba(233, 198, 111, 0); border-color: var(--accent); transform: scale(1); }
        }

        @keyframes pulse-listen {
            0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4); border-color: #4CAF50; transform: scale(1); }
            50% { box-shadow: 0 0 0 50px rgba(76, 175, 80, 0); border-color: #4CAF50; transform: scale(1.05); }
            100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); border-color: #4CAF50; transform: scale(1); }
        }

        .state-speaking { animation: pulse-speak 1.5s infinite; }
        .state-listening { animation: pulse-listen 1.5s infinite; }

        /* Controls */
        .controls {
            margin-top: 40px;
            display: flex;
            gap: 20px;
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
            display: flex; justify-content: center; align-items: center;
            backdrop-filter: blur(5px);
        }
        .btn-mic:hover { transform: scale(1.1); background: rgba(255,255,255,0.1); }
        .btn-mic:active { transform: scale(0.95); }
        .btn-mic.active { background: #4CAF50; box-shadow: 0 0 20px rgba(76, 175, 80, 0.4); }
        .btn-mic.disabled { opacity: 0.3; cursor: not-allowed; transform: none; }

        .btn-end { background: rgba(193, 5, 5, 0.8); }
        .btn-end:hover { background: #ff4444; box-shadow: 0 0 20px rgba(255, 68, 68, 0.4); }

        /* Status Text */
        .status-text {
            margin-top: 10px;
            font-size: 1.1rem;
            color: rgba(255,255,255,0.6);
            height: 30px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        /* Captions */
        .caption-box {
            position: absolute;
            bottom: 15vh;
            width: 70%;
            text-align: center;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(15px);
            padding: 20px 30px;
            border-radius: 16px;
            color: #fff;
            font-size: 1.3rem;
            min-height: 80px;
            display: none;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: opacity 0.3s;
        }

        /* Timer Styles */
        #sessionTimer {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.5);
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
        #sessionTimer.locked { color: #ff5555; }
        #sessionTimer.unlocked { color: #50fa7b; }

        .btn-end { border: 1px solid transparent; transition: all 0.3s; }
        .btn-end:disabled { opacity: 0.3; cursor: not-allowed; filter: grayscale(1); }
        .btn-end.unlocked { 
            background: var(--primary); 
            animation: pulse-end 2s infinite; 
            border-color: var(--accent);
        }
        @keyframes pulse-end {
            0% { box-shadow: 0 0 0 0 rgba(193, 5, 5, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(193, 5, 5, 0); }
            100% { box-shadow: 0 0 0 0 rgba(193, 5, 5, 0); }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(8px);
            z-index: 5000;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            color: white;
            transition: opacity 0.5s;
        }
        .loading-spinner {
            width: 80px; height: 80px;
            border: 5px solid rgba(255,255,255,0.1);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
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
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
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

        .transcript-scroll::-webkit-scrollbar { width: 4px; }
        .transcript-scroll::-webkit-scrollbar-track { background: transparent; }
        .transcript-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        .transcript-line { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .transcript-line b { color: var(--accent); font-size: 0.75rem; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .transcript-line.user b { color: #4CAF50; }
        .transcript-line.ai b { color: var(--accent); }

        @media (max-width: 1200px) {
            .transcript-panel { 
                position: relative; 
                right: auto; top: auto; 
                width: 90%; max-width: 600px; 
                margin: 20px 0;
                max-height: 200px;
            }
        }
    </style>
</head>
<body>

    <!-- Intro Overlay -->
    <div id="introOverlay" class="overlay">
        <div style="text-align: center; max-width: 600px; padding: 40px; background: #1e1e1e; border-radius: 16px; border: 1px solid #333;">
            <div style="font-size: 4rem; margin-bottom: 20px;">🤝</div>
            <h1 style="color: var(--accent);">HR Round</h1>
            <p>Role: <strong><?php echo htmlspecialchars($companyName); ?></strong></p>
            <p style="color: #aaa; margin: 20px 0;">
                This is a speech-to-speech behavioral interview.<br>
                The AI will assess your communication confidence, cultural fit, and problem-solving examples.<br>
                <strong>Please allow Microphone Access.</strong>
            </p>
            <input type="text" id="roleInput" placeholder="Specific Role (e.g. Manager)" value="Software Engineer" style="padding: 10px; width: 200px; text-align: center; margin-bottom: 20px;">
            <br>
            <button onclick="startSession()" style="padding: 15px 40px; font-size: 1.1rem; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer;">Start Interview</button>
        </div>
    </div>

    <div id="loadingOverlay" class="loading-overlay hidden">
        <div class="loading-spinner"></div>
        <h2 style="margin: 0; letter-spacing: 2px;">GENERATING REPORT</h2>
        <p style="color: rgba(255,255,255,0.6); margin-top: 10px;">Please wait while AI evaluates your behavioral performance...</p>
    </div>

    <!-- Security Warning Overlay -->
    <div id="warningOverlay" class="overlay hidden">
        <div style="text-align: center;">
            <i class="fas fa-exclamation-triangle" style="color: var(--primary); font-size: 4rem; margin-bottom: 20px;"></i>
            <h2 style="color: #fff;">Video/Audio Integrity Check</h2>
            <p style="color: #ccc;">Please return to full screen to continue the interview.</p>
            <button onclick="resumeFullscreen()" style="padding: 10px 30px; background: var(--primary); color: white; border: none; border-radius: 5px; margin-top: 20px; cursor: pointer;">RESUME</button>
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

        <div id="captions" class="caption-box"></div>

        <!-- Real-time transcript -->
        <div id="transcriptPanel" class="transcript-panel">
            <h3><i class="fas fa-align-left"></i> Live Transcript</h3>
            <div id="transcriptScroll" class="transcript-scroll"></div>
        </div>

        <div class="controls">
            <button id="micBtn" class="btn-mic disabled" onclick="toggleMic()"><i class="fas fa-microphone-slash"></i></button>
            <button id="endBtn" class="btn-mic btn-end" onclick="endSession()" disabled title="Minimum 20 minutes required for assigned tasks"><i class="fas fa-phone-slash"></i></button>
        </div>
    </div>

    <script>
        const SILENCE_MS = 3000; // Mic turns off after 3 sec of no speech

        let sessionId = null;
        let company = "<?php echo addslashes($companyName); ?>";
        let isSessionActive = false;
        let recognition;
        let synth = window.speechSynthesis;
        let isListening = false;
        let isSpeaking = false;
        let silenceTimer = null;
        let currentUtterance = '';      // Accumulated final text for current answer
        let userInterimEl = null;       // DOM element for live "You: ..." interim line
        
        let startTime = null;
        let isTaskId = <?php echo $taskId ? 'true' : 'false'; ?>;
        const MIN_REQUIRED_TIME = 20 * 60; // 20 minutes

        let voices = [];
        
        function loadVoices() {
            voices = synth.getVoices();
        }
        
        loadVoices();
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = loadVoices;
        }

        window.onload = function() {
            // Check for Speech API
            if (!('webkitSpeechRecognition' in window)) {
                alert("Your browser does not support Speech Recognition. Please use Chrome.");
                return;
            }

            recognition = new webkitSpeechRecognition();
            recognition.continuous = true;  // Keep listening until 3 sec silence
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = () => {
                isListening = true;
                currentUtterance = '';
                clearSilenceTimer();
                updateState("Listening... (stops after 3 sec silence)", "listening");
                document.getElementById('micBtn').innerHTML = '<i class="fas fa-microphone"></i>';
                document.getElementById('micBtn').classList.add('active');
                addUserInterimLine('');
            };

            recognition.onend = () => {
                isListening = false;
                document.getElementById('micBtn').innerHTML = '<i class="fas fa-microphone-slash"></i>';
                document.getElementById('micBtn').classList.remove('active');
                if (!isSpeaking && isSessionActive) updateState("Your turn...", "neutral");
                if (userInterimEl && !userInterimEl.classList.contains('final')) {
                    userInterimEl.remove();
                    userInterimEl = null;
                }
            };

            recognition.onresult = (event) => {
                let interim = '';
                let final = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        final += event.results[i][0].transcript;
                    } else {
                        interim += event.results[i][0].transcript;
                    }
                }
                if (final) {
                    currentUtterance = (currentUtterance + ' ' + final).trim();
                    updateUserInterimLine(currentUtterance);
                    showCaption(currentUtterance);
                    clearSilenceTimer();
                    silenceTimer = setTimeout(() => onSilenceComplete(), SILENCE_MS);
                }
                if (interim) {
                    const display = (currentUtterance + ' ' + interim).trim();
                    updateUserInterimLine(display);
                    showCaption(display);
                }
            };
            
            // Fullscreen Enforcement Listener
            document.addEventListener('fullscreenchange', () => {
                const warning = document.getElementById('warningOverlay');
                if (!document.fullscreenElement && isSessionActive) {
                    warning.classList.remove('hidden');
                    // Taunt user on violation
                    speak("Haha you are soo scared of the interview that you want to use chatgpt or other websites to answer the questions");
                } else {
                    // Automatically hide if they somehow re-entered (though button is preferred)
                    if(document.fullscreenElement) warning.classList.add('hidden');
                }
            });

            // Prevent ESC key default if possible (Best effort)
            document.addEventListener('keydown', (e) => {
                if (isSessionActive && e.key === 'Escape') {
                    e.preventDefault();
                    document.getElementById('warningOverlay').classList.remove('hidden');
                }
            });

            // STRICT SESSION LOCK
            // Stop everything if they leave the page (Reload/Back)
            window.addEventListener('beforeunload', () => {
                if (isSessionActive) {
                    stopSpeaking();
                    if (recognition) recognition.stop();
                    // We can't really "save" state reliably here for a "new start"
                    // But effectively, reloading will restart the JS state anyway.
                    // This ensures the VOICE stops immediately.
                    speechSynthesis.cancel();
                }
            });
            
            // For mobile/certain browsers
            window.addEventListener('pagehide', () => {
                stopSpeaking();
                speechSynthesis.cancel();
            });
        };

        function resumeFullscreen() {
            document.documentElement.requestFullscreen().then(() => {
                document.getElementById('warningOverlay').classList.add('hidden');
            });
        }

        async function startSession() {
            const roleInput = document.getElementById('roleInput').value;
            
            // Check for active session first for resumption
            const checkRes = await apiCall({ action: 'check_active_session', company: company });
            
            if (checkRes.success && checkRes.has_active) {
                if (confirm("You have an active session for this company. Would you like to resume?")) {
                    sessionId = checkRes.session_id;
                    isSessionActive = true;
                    // Sync start time from server using relative elapsed seconds (Skew-resistant)
                    if (checkRes.elapsed_seconds !== undefined) {
                        startTime = Date.now() - (checkRes.elapsed_seconds * 1000); 
                    } else {
                        startTime = Date.now(); 
                    }
                    
                    document.getElementById('introOverlay').classList.add('hidden');
                    if (document.documentElement.requestFullscreen) await document.documentElement.requestFullscreen().catch(e=>e);
                    
                    updateState("Resuming Session...", "neutral");
                    startTimer();
                    
                    // Re-render transcript if items exist
                    if (checkRes.history && checkRes.history.length > 0) {
                        checkRes.history.forEach(m => {
                            if (m.role === 'assistant') appendToTranscript('ai', m.content, false);
                            else if (m.role === 'user') appendToTranscript('user', m.content, false);
                        });
                        // Ask them to continue
                        speak("Resuming interview. Let's continue from where we left off.");
                    } else {
                        loadNextQuestion("");
                    }
                    return;
                }
            }

            const role = roleInput;
            document.getElementById('introOverlay').classList.add('hidden');
            
            if (document.documentElement.requestFullscreen) {
                await document.documentElement.requestFullscreen().catch(err=>console.log(err));
            }

            const res = await apiCall({ action: 'start_session', role: role, company: company, task_id: "<?php echo $taskId; ?>" });
            if (res.success) {
                sessionId = res.session_id;
                isSessionActive = true;
                startTime = Date.now();
                startTimer();
                updateState("Connecting...", "neutral");
                loadNextQuestion(""); // Start interaction
            } else {
                alert("Failed to start session: " + (res.message || "Unknown error"));
                document.getElementById('introOverlay').classList.remove('hidden');
            }
        }

        

        function startTimer() {
            const timerContainer = document.getElementById('sessionTimer');
            const timerText = document.getElementById('timerText');
            const endBtn = document.getElementById('endBtn');

            setInterval(() => {
                if (!isSessionActive) return;

                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                
                if (isTaskId && elapsed < MIN_REQUIRED_TIME) {
                    const remaining = MIN_REQUIRED_TIME - elapsed;
                    const mins = Math.floor(remaining / 60);
                    const secs = remaining % 60;
                    timerText.innerText = `Lock: ${mins}:${secs.toString().padStart(2, '0')}`;
                    timerContainer.className = 'locked';
                    endBtn.disabled = true;
                    endBtn.title = `Wait ${mins}m ${secs}s more for this assigned task.`;
                } else {
                    const mins = Math.floor(elapsed / 60);
                    const secs = elapsed % 60;
                    timerText.innerText = `Duration: ${mins}:${secs.toString().padStart(2, '0')}`;
                    timerContainer.className = 'unlocked';
                    endBtn.disabled = false;
                    endBtn.classList.add('unlocked');
                    endBtn.title = "";
                }
            }, 1000);
        }
        async function loadNextQuestion(userMsg) {
            updateState("AI Thinking...", "neutral");
            
            const res = await apiCall({ action: 'get_question', session_id: sessionId, message: userMsg });
            
            if (res.success && res.job_id) {
                // Poll for result
                const pollInterval = setInterval(async () => {
                    try {
                        const statusRes = await fetch(`ai_job_status.php?job_id=${res.job_id}`).then(r => r.json());
                        if (statusRes.success && statusRes.status === 'completed') {
                            clearInterval(pollInterval);
                            let data = statusRes.result;
                            
                            // Extract payload robustly
                            if (data && data.result && typeof data.result === 'object') {
                                data = data.result;
                            } else if (data && data.content && typeof data.content === 'string') {
                                try { data = JSON.parse(data.content); } catch(e) {}
                            }
                            
                            const textToSpeak = (data.feedback ? data.feedback + ". " : "") + data.question;
                            const questionText = data.question || '';
                            if (questionText) appendToTranscript('ai', questionText, false);
                            showCaption(questionText);
                            speak(textToSpeak);

                            // Save AI response to DB
                            apiCall({ action: 'append_ai_history', session_id: sessionId, message: JSON.stringify(data) });
                        } else if (statusRes.status === 'failed') {
                            clearInterval(pollInterval);
                            alert("AI generation failed: " + statusRes.error);
                            updateState("Error", "neutral");
                        }
                    } catch (e) {
                        console.error("Polling error:", e);
                    }
                }, 2000);
            } else if (res.success && res.data) {
                // Legacy fallback if not queued
                let data = res.data;
                if (data && data.result && typeof data.result === 'object') {
                    data = data.result;
                } else if (data && data.content && typeof data.content === 'string') {
                    try { data = JSON.parse(data.content); } catch(e) {}
                }

                const textToSpeak = (data.feedback ? data.feedback + ". " : "") + data.question;
                const questionText = data.question || '';
                if (questionText) appendToTranscript('ai', questionText, false);
                showCaption(questionText);
                speak(textToSpeak);

                apiCall({ action: 'append_ai_history', session_id: sessionId, message: JSON.stringify(data) });
            } else {
                alert("Failed to get question: " + (res.message || "Unknown error"));
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
            if (!isListening || !isSessionActive) return;
            const text = currentUtterance.trim();
            if (text) {
                recognition.stop();
                finalizeUserTranscriptLine(text);
                updateState("Processing...", "neutral");
                loadNextQuestion(text);
            }
        }

        function processUserAnswer(text) {
            clearSilenceTimer();
            recognition.stop();
            if (userInterimEl && !userInterimEl.classList.contains('final')) {
                finalizeUserTranscriptLine(text);
            }
            updateState("Processing...", "neutral");
            loadNextQuestion(text);
        }

        function speak(text) {
            if (synth.speaking) synth.cancel();
            
            const utterance = new SpeechSynthesisUtterance(text);
            
            // Prefer a female/natural sounding voice if available
            const preferredVoice = voices.find(v => v.name.includes("Google US English") || v.name.includes("Zira") || v.name.includes("Female"));
            if (preferredVoice) utterance.voice = preferredVoice;

            utterance.rate = 0.9;
            utterance.pitch = 1.0;

            utterance.onstart = () => {
                isSpeaking = true;
                updateState("AI Speaking...", "speaking");
                document.getElementById('micBtn').classList.add('disabled');
            };
            utterance.onend = () => {
                isSpeaking = false;
                updateState("Your turn...", "neutral");
                document.getElementById('micBtn').classList.remove('disabled');
                // Auto start listening after AI speaks
                startListening(); 
            };
            synth.speak(utterance);
        }

        function startListening() {
            try {
                recognition.start();
            } catch (e) { console.log("Mic already active"); }
        }

        function toggleMic() {
            if (isSpeaking) return;
            if (isListening) {
                clearSilenceTimer();
                const text = currentUtterance.trim();
                if (text) {
                    recognition.stop();
                    finalizeUserTranscriptLine(text);
                    updateState("Processing...", "neutral");
                    loadNextQuestion(text);
                } else {
                    recognition.stop();
                }
            } else {
                recognition.start();
            }
        }

        function updateState(status, visualState) {
            document.getElementById('statusText').innerText = status;
            const avatar = document.getElementById('avatar');
            avatar.className = 'avatar-container'; // Reset
            if (visualState === 'speaking') avatar.classList.add('state-speaking');
            if (visualState === 'listening') avatar.classList.add('state-listening');
        }

        function stopSpeaking() {
            if (synth.speaking) synth.cancel();
        }

        function showCaption(text) {
            const cap = document.getElementById('captions');
            cap.innerText = text;
            cap.style.display = text ? 'block' : 'none';
            if (text) setTimeout(() => cap.style.display = 'none', 8000);
        }

        function appendToTranscript(who, text, isInterim) {
            const scroll = document.getElementById('transcriptScroll');
            const el = document.createElement('div');
            el.className = 'transcript-msg ' + who + (isInterim ? ' interim' : '');
            el.innerHTML = '<div class="label">' + (who === 'ai' ? 'AI' : 'You') + '</div><div class="text">' + escapeHtml(text || '') + '</div>';
            scroll.appendChild(el);
            scroll.scrollTop = scroll.scrollHeight;
            return el;
        }

        function addUserInterimLine(text) {
            const scroll = document.getElementById('transcriptScroll');
            userInterimEl = document.createElement('div');
            userInterimEl.className = 'transcript-msg user interim';
            userInterimEl.innerHTML = '<div class="label">You (speaking…)</div><div class="text">' + escapeHtml(text) + '</div>';
            scroll.appendChild(userInterimEl);
            scroll.scrollTop = scroll.scrollHeight;
        }

        function updateUserInterimLine(text) {
            if (userInterimEl) {
                userInterimEl.querySelector('.text').textContent = text;
                const scroll = document.getElementById('transcriptScroll');
                scroll.scrollTop = scroll.scrollHeight;
            }
        }

        function finalizeUserTranscriptLine(text) {
            if (userInterimEl) {
                userInterimEl.classList.remove('interim');
                userInterimEl.classList.add('final');
                userInterimEl.querySelector('.label').textContent = 'You';
                userInterimEl.querySelector('.text').textContent = text;
                userInterimEl = null;
            } else if (text) {
                appendToTranscript('user', text, false);
            }
            const scroll = document.getElementById('transcriptScroll');
            if (scroll) scroll.scrollTop = scroll.scrollHeight;
        }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        async function endSession() {
            if (!confirm("End Interview & Generate Report?")) return;
            
            isSessionActive = false;
            stopSpeaking();
            document.getElementById('loadingOverlay').classList.remove('hidden');

            // 1. Get Data
            const res = await apiCall({ action: 'generate_report_data', session_id: sessionId });
            
            if (res.success) {
                // 2. Generate PDF
                const element = document.createElement('div');
                element.innerHTML = res.report_html;
                
                const opt = {
                    margin: 0.5,
                    filename: res.filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                };

                // FAST DOWNLOAD for User (Don't wait for upload)
                html2pdf().set(opt).from(element).save();

                // Background Upload
                html2pdf().set(opt).from(element).outputPdf('blob').then(async (pdfBlob) => {
                    const formData = new FormData();
                    formData.append('action', 'save_pdf_report');
                    formData.append('session_id', sessionId);
                    formData.append('pdf', pdfBlob, res.filename);
                    
                    // Upload silently
                    const uploadRes = await fetch('ai_hr_handler', { method: 'POST', body: formData });
                    console.log("Upload status:", await uploadRes.json());
                    
                    // Redirect after a short delay
                    setTimeout(() => window.location.href = 'dashboard.php', 2000);
                });
            } else {
                document.getElementById('loadingOverlay').classList.add('hidden');
                alert("Report Generation Failed: " + (res.message || "Unknown Error"));
                updateState("Error Generating Report", "neutral");
            }
        }

        async function apiCall(data) {
            try {
                const formData = new FormData();
                for (const k in data) formData.append(k, data[k]);
                const response = await fetch('ai_hr_handler', { 
                    method: 'POST', 
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                return await response.json();
            } catch (e) {
                console.error(e);
                alert("Error connecting to server. Check console.");
                return { success: false };
            }
        }
    </script>
</body>
</html>
