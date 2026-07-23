/**
 * Lakshya Proctoring Engine — proctor.js
 * Phase 1: Laptop Webcam + MediaPipe Face Detection
 *
 * Usage:
 *   const proctor = new LakshyaProctor({
 *     assessmentId: 701,
 *     assessmentType: 'ai_hr',
 *     handlerUrl: '/student/proctor_handler.php',
 *     onAutoSubmit: () => { submitAssessment(); },
 *     onWarning: (msg, isFinal) => { showToast(msg, isFinal ? 'danger' : 'warning'); },
 *   });
 *   await proctor.init();           // runs env check, shows preview
 *   await proctor.startMonitoring(); // starts continuous AI monitoring
 *   proctor.stop();                  // call when assessment ends
 */

class LakshyaProctor {
    constructor(options = {}) {
        this.assessmentId    = options.assessmentId   || 0;
        this.assessmentType  = options.assessmentType || '';
        this.handlerUrl      = options.handlerUrl     || '/student/proctor_handler.php';
        this.onAutoSubmit    = options.onAutoSubmit   || (() => {});
        this.onWarning       = options.onWarning      || (() => {});
        this.onEnvCheckPass  = options.onEnvCheckPass || (() => {});
        this.onEnvCheckFail  = options.onEnvCheckFail || (() => {});

        // Internal state
        this._token          = null;
        this._stream         = null;
        this._detector       = null;
        this._monitorLoop    = null;
        this._heartbeatLoop  = null;
        this._isActive       = false;
        this._settings       = {};
        this._faceMissingTimer  = null;
        this._lookingAwayTimer  = null;
        this._lastFaceTime      = Date.now();
        this._tabSwitchReported = false;

        // DOM
        this._videoEl        = null;
        this._canvasEl       = null;
        this._previewContainer = null;
    }

    // ─────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────

    /**
     * Step 1: Initialize — fetch settings, create proctor session, request camera.
     * Returns true if camera was granted, false otherwise.
     */
    async init() {
        this._settings = await this._fetchSettings();
        this._token    = await this._createSession();
        if (!this._token) throw new Error('Failed to create proctor session.');

        // Inject UI
        this._injectUI();

        // Request camera
        try {
            this._stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                audio: false
            });
            this._videoEl.srcObject = this._stream;
            await new Promise(resolve => this._videoEl.onloadedmetadata = resolve);
            this._videoEl.play();
            return true;
        } catch (err) {
            this._showError('Camera access denied or unavailable. Camera is required to start this assessment.');
            return false;
        }
    }

    /**
     * Step 2: Run pre-assessment environment check (10 seconds).
     * Returns {passed, reasons}
     */
    async runEnvCheck() {
        if (!this._stream) return { passed: false, reasons: ['Camera not available'] };

        const duration = (this._settings.env_check_duration_sec || 10) * 1000;
        const results  = { face_detected: false, one_face: false, lighting_ok: false, browser_ok: true };
        const reasons  = [];
        let   frames   = 0;
        let   faceFrames = 0;
        let   multiFaceFrames = 0;

        // Load MediaPipe
        await this._loadDetector();
        this._setStatus('Running environment check…', 'checking');

        const start = Date.now();
        while (Date.now() - start < duration) {
            const detections = await this._detectFaces();
            frames++;
            if (detections.length >= 1) faceFrames++;
            if (detections.length > 1)  multiFaceFrames++;

            // Check lighting (simple luminance of canvas)
            const lum = this._getFrameLuminance();
            if (lum > 40) results.lighting_ok = true;

            await this._sleep(500);
        }

        // Pass criteria: face visible >60% of frames, never >1 face, lighting OK
        results.face_detected = (faceFrames / frames) >= 0.6;
        results.one_face      = multiFaceFrames === 0;

        if (!results.face_detected)  reasons.push('Face not clearly visible — ensure you are in frame and well-lit.');
        if (!results.one_face)       reasons.push('Multiple faces detected — only the candidate should be visible.');
        if (!results.lighting_ok)    reasons.push('Lighting is too low — move to a brighter area.');
        if (!results.browser_ok)     reasons.push('Browser is not supported — use Google Chrome or Edge.');

        const passed = reasons.length === 0;

        if (passed) {
            // Activate session on server
            await this._post('activate_session', { token: this._token, env_data: JSON.stringify(results) });
            this._setStatus('✓ Environment check passed', 'ok');
            this.onEnvCheckPass();
        } else {
            this._setStatus('✗ Environment check failed', 'error');
            this.onEnvCheckFail(reasons);
        }

        return { passed, reasons, results };
    }

    /**
     * Step 3: Start continuous AI monitoring during the assessment.
     */
    startMonitoring() {
        if (!this._token || !this._stream) return;
        this._isActive = true;

        // AI face detection loop — every 1.5 seconds
        this._monitorLoop = setInterval(() => this._monitorFrame(), 1500);

        // Heartbeat to server — every 20 seconds
        this._heartbeatLoop = setInterval(() => this._heartbeat(), 20000);

        // Browser event listeners
        this._attachBrowserListeners();
    }

    /**
     * Stop proctoring (call when assessment ends normally).
     */
    stop() {
        this._isActive = false;
        clearInterval(this._monitorLoop);
        clearInterval(this._heartbeatLoop);
        clearTimeout(this._faceMissingTimer);
        clearTimeout(this._lookingAwayTimer);
        this._detachBrowserListeners();

        if (this._stream) {
            this._stream.getTracks().forEach(t => t.stop());
            this._stream = null;
        }

        this._post('end_session', { token: this._token }).catch(() => {});
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: MONITORING
    // ─────────────────────────────────────────────────────────

    async _monitorFrame() {
        if (!this._isActive || !this._videoEl) return;

        let detections = [];
        try {
            detections = await this._detectFaces();
        } catch (e) { return; }

        const faceCount = detections.length;

        if (faceCount === 0) {
            // Start face-missing timer
            if (!this._faceMissingTimer) {
                const timeout = (this._settings.face_missing_timeout_sec || 10) * 1000;
                this._faceMissingTimer = setTimeout(async () => {
                    const snapshot = this._captureSnapshot();
                    await this._reportEvent('NO_FACE', 0.95, snapshot, { timeout_sec: this._settings.face_missing_timeout_sec });
                    this._faceMissingTimer = null;
                }, timeout);
            }
        } else {
            // Face found — clear missing timer
            if (this._faceMissingTimer) {
                clearTimeout(this._faceMissingTimer);
                this._faceMissingTimer = null;
            }
            this._lastFaceTime = Date.now();

            // Multiple faces
            if (faceCount > 1) {
                const snapshot = this._captureSnapshot();
                await this._reportEvent('MULTI_FACE', 0.9, snapshot, { face_count: faceCount });
            }

            // Head pose — looking away (if landmarks available)
            if (detections[0]?.keypoints) {
                const lookingAway = this._isLookingAway(detections[0].keypoints);
                if (lookingAway) {
                    if (!this._lookingAwayTimer) {
                        const timeout = (this._settings.looking_away_timeout_sec || 8) * 1000;
                        this._lookingAwayTimer = setTimeout(async () => {
                            const snapshot = this._captureSnapshot();
                            await this._reportEvent('LOOKING_AWAY', 0.8, snapshot, {});
                            this._lookingAwayTimer = null;
                        }, timeout);
                    }
                } else {
                    if (this._lookingAwayTimer) {
                        clearTimeout(this._lookingAwayTimer);
                        this._lookingAwayTimer = null;
                    }
                }
            }

            // Low lighting check
            const lum = this._getFrameLuminance();
            if (lum < 30) {
                await this._reportEvent('LOW_LIGHT', 1.0, null, { luminance: lum });
            }
        }
    }

    async _reportEvent(eventType, confidence, snapshotBase64, details) {
        if (!this._isActive) return;
        try {
            const res = await this._post('report_event', {
                token:      this._token,
                event_type: eventType,
                confidence: confidence,
                snapshot:   snapshotBase64 || '',
                details:    JSON.stringify(details),
            });

            if (!res.success) return;

            // Server decides the action
            switch (res.action) {
                case 'warn':
                    this.onWarning(res.message || 'Warning: Suspicious activity detected.', false);
                    this._showOverlayWarning(res.message, false, res.risk_score);
                    break;
                case 'final_warn':
                    this.onWarning(res.message || 'Final Warning!', true);
                    this._showOverlayWarning(res.message, true, res.risk_score);
                    break;
                case 'auto_submit':
                    this._isActive = false;
                    this._showAutoSubmitOverlay(res.message);
                    this.stop();
                    setTimeout(() => this.onAutoSubmit(), 2500);
                    break;
            }

            this._updateRiskDisplay(res.risk_score, res.warning_count);
        } catch (e) {
            console.warn('[Proctor] reportEvent failed:', e);
        }
    }

    async _heartbeat() {
        if (!this._isActive) return;
        try {
            const res = await this._post('heartbeat', { token: this._token });
            if (res?.action === 'auto_submit') {
                this._isActive = false;
                this._showAutoSubmitOverlay(res.message);
                this.stop();
                setTimeout(() => this.onAutoSubmit(), 2500);
            }
        } catch (e) {}
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: BROWSER EVENTS
    // ─────────────────────────────────────────────────────────

    _attachBrowserListeners() {
        this._onVisibilityChange = async () => {
            if (document.hidden) {
                await this._reportEvent('TAB_SWITCH', 1.0, null, {});
            }
        };
        this._onFullscreenChange = async () => {
            if (!document.fullscreenElement) {
                await this._reportEvent('FULLSCREEN_EXIT', 1.0, null, {});
            }
        };
        this._onBlur = async () => {
            await this._reportEvent('TAB_SWITCH', 0.8, null, { trigger: 'window_blur' });
        };

        document.addEventListener('visibilitychange', this._onVisibilityChange);
        document.addEventListener('fullscreenchange', this._onFullscreenChange);
        window.addEventListener('blur', this._onBlur);
    }

    _detachBrowserListeners() {
        if (this._onVisibilityChange) document.removeEventListener('visibilitychange', this._onVisibilityChange);
        if (this._onFullscreenChange) document.removeEventListener('fullscreenchange',  this._onFullscreenChange);
        if (this._onBlur)             window.removeEventListener('blur', this._onBlur);
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: MEDIAPIPE FACE DETECTION
    // ─────────────────────────────────────────────────────────

    async _loadDetector() {
        if (this._detector) return;
        // Uses MediaPipe Tasks Vision (CDN loaded by the page)
        if (typeof FaceDetector === 'undefined' && typeof window.FaceDetector === 'undefined') {
            // Fallback: try face-api.js if MediaPipe not available
            if (typeof faceapi !== 'undefined') {
                await faceapi.nets.tinyFaceDetector.loadFromUri('/assets/models/face-api');
                this._detectorMode = 'faceapi';
                return;
            }
            throw new Error('No face detection library loaded. Include MediaPipe Tasks Vision or face-api.js.');
        }

        // MediaPipe Tasks Vision
        const { FaceDetector, FilesetResolver } = await import(
            'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/vision_bundle.mjs'
        );
        const vision = await FilesetResolver.forVisionTasks(
            'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/wasm'
        );
        this._detector = await FaceDetector.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite',
                delegate: 'GPU'
            },
            runningMode: 'IMAGE',
            minDetectionConfidence: 0.5,
        });
        this._detectorMode = 'mediapipe';
    }

    async _detectFaces() {
        if (!this._videoEl || !this._canvasEl) return [];
        const ctx = this._canvasEl.getContext('2d');
        ctx.drawImage(this._videoEl, 0, 0, this._canvasEl.width, this._canvasEl.height);

        if (this._detectorMode === 'mediapipe' && this._detector) {
            const result = this._detector.detect(this._canvasEl);
            return result?.detections || [];
        }
        if (this._detectorMode === 'faceapi') {
            const detections = await faceapi.detectAllFaces(this._canvasEl, new faceapi.TinyFaceDetectorOptions());
            return detections || [];
        }
        return [];
    }

    _isLookingAway(keypoints) {
        // MediaPipe returns: [rightEye, leftEye, noseTip, mouthCenter, rightEar, leftEar]
        if (!keypoints || keypoints.length < 6) return false;
        const [rightEye, leftEye, , , rightEar, leftEar] = keypoints;
        // If one ear is much more visible than the other, student is turned
        const eyeSpan  = Math.abs(rightEye.x - leftEye.x);
        const earSpan  = Math.abs(rightEar.x - leftEar.x);
        const ratio    = earSpan > 0 ? eyeSpan / earSpan : 1;
        return ratio < 0.4; // significant head rotation
    }

    _getFrameLuminance() {
        if (!this._canvasEl) return 100;
        const ctx = this._canvasEl.getContext('2d');
        const data = ctx.getImageData(0, 0, this._canvasEl.width, this._canvasEl.height).data;
        let sum = 0;
        for (let i = 0; i < data.length; i += 16) { // sample every 4th pixel
            sum += 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
        }
        return sum / (data.length / 16);
    }

    _captureSnapshot() {
        if (!this._canvasEl) return null;
        try {
            return this._canvasEl.toDataURL('image/jpeg', 0.7);
        } catch (e) { return null; }
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: UI
    // ─────────────────────────────────────────────────────────

    _injectUI() {
        // Inject CSS
        if (!document.getElementById('proctor-css')) {
            const style = document.createElement('style');
            style.id = 'proctor-css';
            style.textContent = `
                #proctor-preview {
                    position: fixed; bottom: 16px; right: 16px; z-index: 9000;
                    width: 160px; border-radius: 12px; overflow: hidden;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.5); border: 2px solid rgba(255,255,255,0.15);
                    background: #000; font-family: 'Inter', sans-serif;
                }
                #proctor-preview video { width: 100%; display: block; border-radius: 10px 10px 0 0; }
                #proctor-status-bar {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 4px 8px; font-size: 10px; color: #fff; background: rgba(0,0,0,0.8);
                }
                #proctor-status-bar .dot {
                    width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
                    animation: proctor-pulse 1.5s infinite;
                }
                @keyframes proctor-pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
                #proctor-risk { font-size: 10px; color: #fbbf24; font-weight: 600; }
                .proctor-warning-overlay {
                    position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
                    z-index: 9999; padding: 14px 24px; border-radius: 12px;
                    font-size: 14px; font-weight: 600; text-align: center;
                    animation: proctor-fadein 0.3s ease;
                    max-width: 90vw; box-shadow: 0 8px 32px rgba(0,0,0,0.4);
                }
                .proctor-warning-overlay.warn     { background: #f59e0b; color: #000; }
                .proctor-warning-overlay.final    { background: #ef4444; color: #fff; }
                .proctor-warning-overlay.submit   { background: #1e1b4b; color: #fff; border: 2px solid #ef4444; }
                @keyframes proctor-fadein { from{opacity:0;top:0} to{opacity:1;top:20px} }
                #proctor-env-modal {
                    position: fixed; inset: 0; z-index: 10000;
                    background: rgba(0,0,0,0.95); display: flex;
                    align-items: center; justify-content: center; flex-direction: column;
                    font-family: 'Inter', sans-serif; color: #fff;
                }
                #proctor-env-modal h2 { font-size: 1.4rem; margin-bottom: 8px; }
                #proctor-env-modal .checklist { list-style: none; padding: 0; margin: 20px 0; }
                #proctor-env-modal .checklist li { padding: 8px 0; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; }
                #proctor-env-modal .checklist li .icon { font-size: 1.2rem; width: 24px; text-align: center; }
                #proctor-env-video { width: 220px; border-radius: 12px; margin: 16px 0; border: 2px solid rgba(255,255,255,0.2); }
                #proctor-env-status { font-size: 0.9rem; color: #fbbf24; margin: 8px 0; min-height: 24px; }
                #proctor-start-btn {
                    margin-top: 16px; padding: 14px 40px; border-radius: 12px;
                    border: none; background: #22c55e; color: #000;
                    font-size: 1rem; font-weight: 700; cursor: pointer; display: none;
                }
                #proctor-retry-btn {
                    margin-top: 12px; padding: 10px 28px; border-radius: 12px;
                    border: 1px solid #f59e0b; background: transparent; color: #f59e0b;
                    font-size: 0.9rem; font-weight: 600; cursor: pointer; display: none;
                }
            `;
            document.head.appendChild(style);
        }

        // Floating preview
        this._previewContainer = document.createElement('div');
        this._previewContainer.id = 'proctor-preview';
        this._previewContainer.innerHTML = `
            <video id="proctor-video" autoplay muted playsinline></video>
            <div id="proctor-status-bar">
                <span class="dot"></span>
                <span id="proctor-label">Proctored</span>
                <span id="proctor-risk">Risk: 0</span>
            </div>
        `;
        document.body.appendChild(this._previewContainer);

        this._videoEl = document.getElementById('proctor-video');

        // Canvas for frame capture (hidden)
        this._canvasEl = document.createElement('canvas');
        this._canvasEl.width  = 320;
        this._canvasEl.height = 240;
        this._canvasEl.style.display = 'none';
        document.body.appendChild(this._canvasEl);
    }

    _showEnvCheckModal() {
        const modal = document.createElement('div');
        modal.id = 'proctor-env-modal';
        modal.innerHTML = `
            <h2>🔍 Pre-Assessment Environment Check</h2>
            <p style="color:#94a3b8;font-size:0.9rem;text-align:center">
                This assessment requires camera monitoring.<br>
                We'll verify your environment before you begin.
            </p>
            <video id="proctor-env-video" autoplay muted playsinline></video>
            <div id="proctor-env-status">Starting camera…</div>
            <ul class="checklist" id="proctor-checklist">
                <li><span class="icon" id="chk-camera">⏳</span> Camera accessible</li>
                <li><span class="icon" id="chk-face">⏳</span> Face clearly visible</li>
                <li><span class="icon" id="chk-single">⏳</span> Only one person in frame</li>
                <li><span class="icon" id="chk-light">⏳</span> Adequate lighting</li>
                <li><span class="icon" id="chk-browser">⏳</span> Browser compatible</li>
            </ul>
            <button id="proctor-start-btn" onclick="window._proctoringInstance._envCheckComplete()">
                ✓ Start Assessment
            </button>
            <button id="proctor-retry-btn" onclick="window._proctoringInstance.runEnvCheck()">
                ↻ Retry Check
            </button>
        `;
        document.body.appendChild(modal);
        return modal;
    }

    _setStatus(msg, type) {
        const el = document.getElementById('proctor-env-status');
        if (el) el.textContent = msg;
    }

    _updateRiskDisplay(riskScore, warnings) {
        const el = document.getElementById('proctor-risk');
        if (el) {
            el.textContent = `Risk: ${riskScore}`;
            el.style.color = riskScore >= 80 ? '#ef4444' : riskScore >= 40 ? '#f59e0b' : '#22c55e';
        }
    }

    _showOverlayWarning(msg, isFinal, riskScore) {
        const div = document.createElement('div');
        div.className = `proctor-warning-overlay ${isFinal ? 'final' : 'warn'}`;
        div.innerHTML = `
            <div>${isFinal ? '🚨 Final Warning' : '⚠️ Warning'}</div>
            <div style="font-size:12px;font-weight:400;margin-top:4px">${msg}</div>
        `;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 6000);
    }

    _showAutoSubmitOverlay(msg) {
        const div = document.createElement('div');
        div.className = 'proctor-warning-overlay submit';
        div.style.cssText += 'top:50%;transform:translate(-50%,-50%);padding:32px 48px;font-size:1.1rem;';
        div.innerHTML = `
            <div>🔴 Assessment Auto-Submitted</div>
            <div style="font-size:13px;font-weight:400;margin-top:8px">${msg}</div>
        `;
        document.body.appendChild(div);
    }

    _showError(msg) {
        const el = document.getElementById('proctor-env-status');
        if (el) { el.textContent = msg; el.style.color = '#ef4444'; }
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: HTTP
    // ─────────────────────────────────────────────────────────

    async _fetchSettings() {
        try {
            const res = await fetch(`${this.handlerUrl}?action=get_settings&assessment_type=${this.assessmentType}`);
            return await res.json();
        } catch (e) { return {}; }
    }

    async _createSession() {
        try {
            const fd = new FormData();
            fd.append('action', 'create_session');
            fd.append('assessment_id', this.assessmentId);
            fd.append('assessment_type', this.assessmentType);
            const res  = await fetch(this.handlerUrl, { method: 'POST', body: fd });
            const data = await res.json();
            return data.success ? data.token : null;
        } catch (e) { return null; }
    }

    async _post(action, params = {}) {
        const fd = new FormData();
        fd.append('action', action);
        Object.entries(params).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch(this.handlerUrl, { method: 'POST', body: fd });
        return await res.json();
    }

    _sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
}

// Make globally accessible
window.LakshyaProctor = LakshyaProctor;
