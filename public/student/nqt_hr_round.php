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
    <link rel='icon' type='image/png' href='/Lakshya/assets/img/favicon.png'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NQT HR Round - Lakshya</title>
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
    </style>
</head>
<body>

    <!-- Intro Overlay -->
    <div id="introOverlay" class="overlay">
        <div style="background: var(--dark); padding: 50px; border-radius: 30px; border: 1px solid var(--secondary); max-width: 500px; box-shadow: 0 0 100px rgba(128,0,0,0.5);">
            <div style="font-size: 5rem; margin-bottom: 20px;">�</div>
            <h2 style="color: var(--secondary); margin-bottom: 15px; font-size: 2.2rem;">HR Proficiency Round</h2>
            <p style="opacity: 0.8; line-height: 1.8; margin-bottom: 40px; font-size: 1.1rem;">
                This is a high-fidelity behavioral simulation. We will evaluate your communication clarity, situational judgment, and core values.<br><br>
                <span style="color: var(--secondary); font-weight: 600;">Secure testing environment enabled.</span>
            </p>
            <button onclick="startSession()" class="btn-finish" style="width: 100%; padding: 20px; font-size: 1.1rem;">START INTERVIEW</button>
        </div>
    </div>

    <!-- Security Warning Overlay -->
    <div id="securityOverlay" class="overlay hidden">
        <i class="fas fa-shield-virus" style="color: var(--secondary); font-size: 5rem; margin-bottom: 25px;"></i>
        <h2 style="font-size: 2.5rem; margin-bottom: 20px;">Assessment Interrupted</h2>
        <p style="font-size: 1.3rem; max-width: 600px; margin-bottom: 40px; opacity: 0.8; line-height: 1.6;">
            The secure environment was breached by exiting full-screen mode. <br>
            Please re-enter to resume your session.
        </p>
        <button onclick="requestFullScreen()" class="btn-finish" style="padding: 18px 50px; font-size: 1.1rem;">RESUME ASSESSMENT</button>
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
        <button onclick="finishInterview()" class="btn-finish" style="padding: 18px 50px; font-size: 1.1rem;">EXIT ASSESSMENT</button>
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
                <button class="btn-finish" onclick="finishInterview()" title="End Session">Finish</button>
            </div>
        </div>
    </div>

    <script>
        let sessionId = null;
        let recognition;
        let synth = window.speechSynthesis;
        let isListening = false;
        let isSpeaking = false;
        let voices = [];
        let silenceTimer;
        let currentUtterance = "";
        let timeRemaining = 3600;
        let timerInterval;

        function startTimer() {
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
            document.getElementById('timeUpOverlay').classList.remove('hidden');
            if (isListening && recognition) recognition.stop();
            if (isSpeaking && synth) synth.cancel();
        }

        // Security Features
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'v' || e.key === 'x')) e.preventDefault();
        });

        function loadVoices() {
            voices = synth.getVoices();
        }
        loadVoices();
        if (speechSynthesis.onvoiceschanged !== undefined) speechSynthesis.onvoiceschanged = loadVoices;

        window.onload = function() {
            if (!('webkitSpeechRecognition' in window)) {
                alert("Speech recognition not supported in this browser. Please use Chrome.");
                return;
            }

            recognition = new webkitSpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = () => {
                isListening = true;
                currentUtterance = "";
                updateState("Listening", "listening");
                document.getElementById('micBtn').classList.add('active');
            };

            recognition.onend = () => {
                isListening = false;
                document.getElementById('micBtn').classList.remove('active');
                if (!isSpeaking && sessionId) updateState("Ready", "neutral");
                document.getElementById('captionArea').style.display = 'none';
            };

            recognition.onresult = (event) => {
                let interim = "";
                let final = "";
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) final += event.results[i][0].transcript;
                    else interim += event.results[i][0].transcript;
                }
                
                if (final) {
                    currentUtterance = (currentUtterance + " " + final).trim();
                    showCaption(currentUtterance);
                    clearTimeout(silenceTimer);
                    silenceTimer = setTimeout(() => {
                        if (currentUtterance.trim()) sendAnswer(currentUtterance);
                    }, 2500);
                }
                if (interim) {
                    const display = (currentUtterance + " " + interim).trim();
                    showCaption(display);
                }
            };

            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement && sessionId) {
                    document.getElementById('securityOverlay').classList.remove('hidden');
                }
            });
        };

        async function requestFullScreen() {
            const el = document.documentElement;
            if (el.requestFullscreen) await el.requestFullscreen();
            document.getElementById('securityOverlay').classList.add('hidden');
        }

        async function startSession() {
            document.getElementById('introOverlay').classList.add('hidden');
            await requestFullScreen();

            const res = await fetch('nqt_hr_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=start_session'
            });
            const data = await res.json();
            if (data.success) {
                sessionId = data.session_id;
                startTimer();
                getQuestion();
            }
        }

        async function getQuestion(msg = '') {
            updateState("Thinking", "neutral");
            const res = await fetch('nqt_hr_handler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_question', session_id: sessionId, message: msg })
            });
            const data = await res.json();
            if (data.success) {
                const question = data.data.question;
                document.getElementById('aiText').innerText = question;
                appendToTranscript('ai', question);
                speak(question);
            }
        }

        function speak(text) {
            if (synth.speaking) synth.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            const preferredVoice = voices.find(v => v.name.includes("Google US English") || v.name.includes("Female"));
            if (preferredVoice) utterance.voice = preferredVoice;
            
            utterance.rate = 1.0;
            utterance.onstart = () => {
                isSpeaking = true;
                updateState("Speaking", "speaking");
                document.getElementById('micBtn').classList.add('disabled');
            };
            utterance.onend = () => {
                isSpeaking = false;
                updateState("Ready", "neutral");
                document.getElementById('micBtn').classList.remove('disabled');
                recognition.start(); // Auto-start listening
            };
            synth.speak(utterance);
        }

        function toggleMic() {
            if (isSpeaking) return;
            if (isListening) recognition.stop();
            else recognition.start();
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

        function sendAnswer(val = "") {
            if (!val) {
                const input = document.getElementById('userInput');
                val = input.value.trim();
                input.value = "";
            }
            if (!val) return;
            
            recognition.stop();
            appendToTranscript('user', val);
            document.getElementById('aiText').innerText = "Analyzing response...";
            getQuestion(val);
            currentUtterance = "";
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

        async function finishInterview() {
            if (!confirm("End NQT HR Session? Progress will be finalized and your report will be generated.")) return;
            
            showLoader("Finalizing Session...");
            const curSessionId = sessionId; // Capture it
            sessionId = null;
            
            if (document.fullscreenElement) document.exitFullscreen();
            if (synth.speaking) synth.cancel();

            // 1. Submit/Finalize
            await apiCall({ action: 'submit_interview', session_id: curSessionId });
            
            // 2. Generate Report Data
            showLoader("Analyzing Performance...");
            const res = await apiCall({ action: 'generate_report_data', session_id: curSessionId });
            
            if (res.success) {
                showLoader("Generating PDF...");
                const element = document.createElement('div');
                element.innerHTML = res.report_html;
                
                const opt = {
                    margin: 0.5,
                    filename: res.filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                };

                html2pdf().set(opt).from(element).outputPdf('blob').then(async (blob) => {
                    const formData = new FormData();
                    formData.append('action', 'save_pdf_report');
                    formData.append('session_id', curSessionId);
                    formData.append('pdf', blob, res.filename);
                    
                    const upload = await fetch('nqt_hr_handler', { method: 'POST', body: formData });
                    const uploadRes = await upload.json();
                    
                    hideLoader();
                    if (uploadRes.success) {
                        html2pdf().set(opt).from(element).save();
                        alert("HR Assessment Completed. Your behavioral report has been saved.");
                        setTimeout(() => { window.location.href = 'dashboard'; }, 1500);
                    } else {
                        alert("Failed to save report to server: " + (uploadRes.message || "Unknown error"));
                    }
                });
            } else {
                hideLoader();
                alert("Critical Failure: " + (res.message || "Report generation failed. Please try again."));
            }
        }
    </script>
</body>
</html>

