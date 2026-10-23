/**
 * Lakshya Proctoring Engine — proctor.js
 * Phase 4B: Advanced Proctoring Sensing, Multi-Signal Correlation & 4-Tier Auditability
 *
 * Enforces 4-Tier Architecture:
 * 1. Observation (Untrusted Candidate Samples)
 * 2. Event (Server Epoch Persistence & Continuity)
 * 3. Evidence (Cross-Signal Spatial/Landmark/Illumination Correlation)
 * 4. Policy Decision (Authoritative Disciplinary Outcomes)
 */

class ProctoringEngine {
    constructor(options = {}) {
        this.studentId     = options.studentId || null;
        this.assessmentId  = options.assessmentId || 0;
        this.assessmentType= options.assessmentType || 'general';
        this.apiEndpoint   = options.apiEndpoint || '/student/proctor_handler.php';

        // Directives & Lifecycle hooks
        this.onWarning     = options.onWarning    || (() => {});
        this.onAutoSubmit  = options.onAutoSubmit || (() => {});
        this.onEnvCheckPass= options.onEnvCheckPass|| (() => {});
        this.onEnvCheckFail= options.onEnvCheckFail|| (() => {});

        // Internal State
        this._token        = null;
        this._settings     = {};
        this._stream       = null;
        this._screenStream = options.screenStream || null;
        this._videoEl      = null;
        this._screenVideoEl= null;
        this._isActive     = false;
        this._seq          = 0;
        this._detector     = null;
        this._detectorMode = 'mediapipe';

        if (this._screenStream) {
            this.setScreenStream(this._screenStream);
        }


        // Phase 4B Baselines & Calibration Distributions
        this._baseline = {
            yaw_mean: 0, yaw_std: 2.5,
            pitch_mean: 0, pitch_std: 2.5,
            roll_mean: 0, roll_std: 2.5,
            status: 'UNINITIALIZED'
        };

        // Identity Reference Distribution
        this._identityRef = {
            descriptor_mean: [],
            descriptor_var: 0.04,
            quality: 0.90,
            frame_count: 0,
            enrolled: false
        };

        // Gaze Screen-Attention 2D Regularized Covariance Model
        this._gazeModel = {
            mu_x: 0.0, mu_y: 0.0,
            cov_xx: 0.01, cov_yy: 0.01, cov_xy: 0.0,
            inv_cov_xx: 100.0, inv_cov_yy: 100.0, inv_cov_xy: 0.0,
            lambda: 0.0001,
            calibrated: false
        };

        // Device Detector State & Hysteresis
        this._deviceState = {
            active: false,
            enter_thresh: 0.65,
            exit_thresh: 0.45,
            last_bbox: null,
            stable_frames: 0
        };

        // Time-Based EMA Pose Filter (tau = 250ms)
        this._tauMs = 250;
        this._lastFrameTime = 0;
        this._smoothedPose = { yaw: 0, pitch: 0, roll: 0 };
        this._filterInitialized = false;

        // Temporal candidate timers
        this._lookingAwayStartTime = null;
        this._downwardAttnStartTime = null;
        this._sidewaysDevStartTime = null;
        this._identityMismatchStartTime = null;
        this._gazeDivergenceStartTime = null;
        this._devicePresenceStartTime = null;
        this._occlusionStartTime = null;
        this._unattendedStartTime = null;
        this._lastCleanFrameTime = 0;

        // Throttling timers
        this._lastReportedTime = {};

        // Last few raw readings: the median of 3 frames removes single-frame landmark
        // jitter (common on low-resolution / blurry webcams)
        this._poseHistory = [];
        this._gazeHistory = [];
        this._topoHistory = [];
        this._lowQualityFrames = 0;
    }

    async init() {
        const sessionRes = await this._post('create_session', {
            student_id:      this.studentId,
            assessment_id:   this.assessmentId,
            assessment_type: this.assessmentType
        });

        const sessionData = sessionRes?.data || sessionRes;
        if (!sessionData || !sessionData.success) {
            throw new Error(sessionData?.error || 'Failed to establish proctor session with server.');
        }

        this._token    = sessionData.token;
        // A resumed session already has sequence numbers used; continue after them or the
        // server rejects every report as out-of-order
        this._seq      = Number(sessionData.last_seq) || 0;
        this._settings = sessionData.config || {};
        if (this._settings.tau_smoothing_ms) this._tauMs = this._settings.tau_smoothing_ms;
        if (this._settings.device_enter_confidence) this._deviceState.enter_thresh = this._settings.device_enter_confidence;
        if (this._settings.device_exit_confidence) this._deviceState.exit_thresh = this._settings.device_exit_confidence;

        this._injectUI();
        if (sessionData.resumed) {
            this._updateRiskDisplay(sessionData.suspicion_score || 0, sessionData.strike_count || 0, sessionData.penalty_pct || 0);
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            const isSecure = window.isSecureContext !== false;
            this._showError(isSecure 
                ? 'Camera API is not supported by your browser. Please use a modern browser like Chrome or Edge.'
                : 'Camera blocked: Insecure Context. Browser requires HTTPS or localhost for webcam access.');
            return false;
        }

        try {
            // A retry must not leave the previous camera stream running
            if (this._stream) {
                try { this._stream.getTracks().forEach(t => t.stop()); } catch (e) {}
                this._stream = null;
            }
            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                    audio: false
                });
            } catch (firstErr) {
                console.warn("Proctor primary video constraints failed, attempting fallback...", firstErr);
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: false
                });
            }

            const videoTrack = this._stream.getVideoTracks()[0];
            if (videoTrack) {
                // macOS briefly mutes the track while the camera warms up — that's not a lost camera
                videoTrack.onmute = () => console.warn('[LakshyaProctor] Camera track temporarily muted.');
                videoTrack.onended = () => {
                    this._showError('Camera disconnected. Please reconnect your camera.');
                    this._setPreviewStatusIndicator('warning');
                };
            }

            this._videoEl.srcObject = this._stream;
            // If metadata already loaded, onloadedmetadata never fires — don't hang forever
            if (this._videoEl.readyState < 1) {
                await new Promise(resolve => {
                    this._videoEl.onloadedmetadata = resolve;
                    setTimeout(resolve, 5000);
                });
            }
            await this._videoEl.play().catch(e => console.warn('[LakshyaProctor] Video play deferred:', e));
            // Warm the detector while the student reads the instructions
            this._loadDetector();
            return true;
        } catch (err) {
            console.error("Proctor camera init error:", err);
            let msg = 'Camera access denied. Video monitoring is required for this assessment.';
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                msg = 'Camera permission was denied. Please allow camera access in your browser settings and refresh. On a Mac, also enable your browser under System Settings → Privacy & Security → Camera, then restart the browser.';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                msg = 'No camera device found. Please connect a webcam to continue.';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                msg = 'Webcam is currently in use by another application. Please close other camera apps and retry.';
            } else if (err.name === 'SecurityError') {
                msg = 'Camera access blocked by security policy or insecure origin.';
            }
            this._showError(msg);
            return false;
        }
    }

    setScreenStream(stream) {
        if (!stream) return;
        this._screenStream = stream;
        if (!this._screenVideoEl) {
            this._screenVideoEl = document.createElement('video');
            this._screenVideoEl.autoplay = true;
            this._screenVideoEl.muted = true;
            this._screenVideoEl.playsInline = true;
            this._screenVideoEl.style.display = 'none';
            document.body.appendChild(this._screenVideoEl);
        }
        this._screenVideoEl.srcObject = stream;
        this._screenVideoEl.play().catch(() => {});
    }

    async initScreenShare() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) return false;
        try {
            const stream = await navigator.mediaDevices.getDisplayMedia({
                video: { displaySurface: 'monitor', cursor: 'always' },
                audio: false
            });
            this.setScreenStream(stream);
            return true;
        } catch (e) {
            console.warn('[Proctor] Screen share declined or failed:', e);
            return false;
        }
    }


    async runEnvCheck() {
        if (!this._stream) return { passed: false, reasons: ['Camera stream is not active.'] };

        const duration = (this._settings.env_check_duration_sec || 8) * 1000;
        const rawPoseSamples = [];
        const rawTopologySamples = [];
        const rawGazeSamples = [];
        const reasons = [];
        let totalFrames = 0;
        let multiFaces = 0;

        this._setStatus('Loading AI face detector…', 'checking');
        await this._loadDetector();
        if (!this._detector) {
            const reason = 'The AI face detector could not load. Check your internet connection, disable ad/content blockers for this site, and retry.';
            this._baseline.status = 'CALIBRATION_FAILED';
            this._setStatus('✗ Calibration failed', 'error');
            this.onEnvCheckFail([reason]);
            return { passed: false, reasons: [reason], baseline: this._baseline, identity: this._identityRef, gaze: this._gazeModel };
        }

        // macOS cameras can take a moment to deliver the first frame
        const frameWaitStart = Date.now();
        while ((this._videoEl.readyState < 2 || this._videoEl.videoWidth === 0) && Date.now() - frameWaitStart < 8000) {
            this._videoEl.play().catch(() => {});
            await this._sleep(250);
        }

        this._setStatus('Calibrating posture, gaze & identity baseline…', 'checking');

        const start = Date.now();
        while (Date.now() - start < duration) {
            const detections = await this._detectFaces();
            if (detections === null) {
                await this._sleep(350);
                continue;
            }
            totalFrames++;
            const lum = this._getFrameLuminance();

            if (detections.length === 1 && lum >= 25) {
                const keypoints = detections[0].keypoints;
                const quality = detections[0].score || 0.90;
                if (quality >= 0.40) {
                    const pose = this._estimateHeadPose(keypoints);
                    if (pose) {
                        rawPoseSamples.push(pose);
                        const topo = this._extractLandmarkTopology(keypoints);
                        if (topo) rawTopologySamples.push(topo);
                        const gaze = this._estimateScreenAttentionGaze(keypoints);
                        if (gaze) rawGazeSamples.push(gaze);
                    }
                }
            } else if (detections.length > 1) {
                multiFaces++;
            }

            await this._sleep(350);
        }

        const validRatio = rawPoseSamples.length / Math.max(totalFrames, 1);
        // A single stray multi-face frame (poster, reflection) shouldn't fail calibration
        const multiFaceRatio = multiFaces / Math.max(totalFrames, 1);

        if (totalFrames === 0) {
            this._baseline.status = 'CALIBRATION_FAILED';
            reasons.push('No picture is coming from your camera. Close other apps using the camera (FaceTime, Zoom, Teams); on a Mac check System Settings → Privacy & Security → Camera.');
        } else if (validRatio >= 0.60 && multiFaceRatio < 0.15) {
            this._baseline.status = 'CALIBRATION_READY';
        } else if (validRatio >= 0.40 && multiFaceRatio < 0.15) {
            this._baseline.status = 'CALIBRATION_DEGRADED';
        } else {
            this._baseline.status = 'CALIBRATION_FAILED';
            if (multiFaceRatio >= 0.15) reasons.push('Multiple faces detected during calibration. Ensure only candidate is visible.');
            if (validRatio < 0.40) reasons.push('Face not steadily visible or lighting is too dim. Sit facing the screen with your whole face in the preview, and avoid a bright window behind you.');
        }

        const passed = (this._baseline.status !== 'CALIBRATION_FAILED');

        if (passed) {
            const n = rawPoseSamples.length;
            const meanYaw = rawPoseSamples.reduce((a, s) => a + s.yaw, 0) / n;
            const meanPitch = rawPoseSamples.reduce((a, s) => a + s.pitch, 0) / n;
            const meanRoll = rawPoseSamples.reduce((a, s) => a + s.roll, 0) / n;

            const varYaw = rawPoseSamples.reduce((a, s) => a + Math.pow(s.yaw - meanYaw, 2), 0) / n;
            const varPitch = rawPoseSamples.reduce((a, s) => a + Math.pow(s.pitch - meanPitch, 2), 0) / n;
            const varRoll = rawPoseSamples.reduce((a, s) => a + Math.pow(s.roll - meanRoll, 2), 0) / n;

            this._baseline = {
                yaw_mean: Number(meanYaw.toFixed(2)),
                pitch_mean: Number(meanPitch.toFixed(2)),
                roll_mean: Number(meanRoll.toFixed(2)),
                yaw_std: Number(Math.max(2.5, Math.sqrt(varYaw)).toFixed(3)),
                pitch_std: Number(Math.max(2.5, Math.sqrt(varPitch)).toFixed(3)),
                roll_std: Number(Math.max(2.5, Math.sqrt(varRoll)).toFixed(3)),
                status: this._baseline.status,
                valid_samples: n
            };

            // 1. Establish Identity Multi-Sample Baseline
            if (rawTopologySamples.length >= 5) {
                const topoDim = rawTopologySamples[0].length;
                const meanTopo = new Array(topoDim).fill(0);
                for (let i = 0; i < rawTopologySamples.length; i++) {
                    for (let d = 0; d < topoDim; d++) {
                        meanTopo[d] += rawTopologySamples[i][d];
                    }
                }
                for (let d = 0; d < topoDim; d++) {
                    meanTopo[d] /= rawTopologySamples.length;
                }

                let totalDistSq = 0;
                const dimVar = new Array(topoDim).fill(0);
                for (let i = 0; i < rawTopologySamples.length; i++) {
                    let dsq = 0;
                    for (let d = 0; d < topoDim; d++) {
                        const sq = Math.pow(rawTopologySamples[i][d] - meanTopo[d], 2);
                        dsq += sq;
                        dimVar[d] += sq;
                    }
                    totalDistSq += dsq;
                }
                const varTopo = Math.max(0.01, totalDistSq / rawTopologySamples.length);

                this._identityRef = {
                    descriptor_mean: meanTopo,
                    // Per-measurement spread for this student on this camera (noisy webcams spread more)
                    descriptor_var_dims: dimVar.map(v => Number((v / rawTopologySamples.length).toFixed(5))),
                    descriptor_var: Number(varTopo.toFixed(4)),
                    quality: 0.95,
                    frame_count: rawTopologySamples.length,
                    enrolled: true
                };

                // Report Enrollment to Server
                this._reportObservation('CALIBRATION_ENROLLMENT', 0, 1.0, null, {
                    quality_score: 0.95,
                    frame_count: rawTopologySamples.length,
                    descriptor_dim: topoDim
                }).catch(() => {});
            }

            // 2. Establish 2D Covariance Screen-Attention Gaze Ellipse
            if (rawGazeSamples.length >= 5) {
                const nG = rawGazeSamples.length;
                const muX = rawGazeSamples.reduce((a, g) => a + g.rx, 0) / nG;
                const muY = rawGazeSamples.reduce((a, g) => a + g.ry, 0) / nG;

                let cxx = 0, cyy = 0, cxy = 0;
                for (let g of rawGazeSamples) {
                    const dx = g.rx - muX;
                    const dy = g.ry - muY;
                    cxx += dx * dx;
                    cyy += dy * dy;
                    cxy += dx * dy;
                }
                cxx /= nG; cyy /= nG; cxy /= nG;

                // Covariance Regularization: Sigma_reg = Sigma + lambda * I.
                // 8 s of sitting still gives an unrealistically tight spread; floor it at the
                // movement of normally reading the whole screen (and webcam landmark noise).
                const lambda = this._settings.gaze_covariance_lambda || 0.0001;
                const minStdX = this._settings.gaze_min_std_x || 0.10;
                const minStdY = this._settings.gaze_min_std_y || 0.15;
                const rCxx = Math.max(minStdX * minStdX, cxx + lambda);
                const rCyy = Math.max(minStdY * minStdY, cyy + lambda);
                // Keep the correlation term valid for the floored variances
                const rCxy = Math.max(-0.9 * Math.sqrt(rCxx * rCyy), Math.min(0.9 * Math.sqrt(rCxx * rCyy), cxy));

                // 2x2 Matrix Inversion: [a b; c d]^-1 = 1/(ad - bc) * [d -b; -c a]
                const det = (rCxx * rCyy) - (rCxy * rCxy);
                const safeDet = Math.max(0.00001, det);

                this._gazeModel = {
                    mu_x: Number(muX.toFixed(4)),
                    mu_y: Number(muY.toFixed(4)),
                    cov_xx: Number(rCxx.toFixed(6)),
                    cov_yy: Number(rCyy.toFixed(6)),
                    cov_xy: Number(rCxy.toFixed(6)),
                    inv_cov_xx: Number((rCyy / safeDet).toFixed(4)),
                    inv_cov_yy: Number((rCxx / safeDet).toFixed(4)),
                    inv_cov_xy: Number((-rCxy / safeDet).toFixed(4)),
                    lambda: lambda,
                    calibrated: true
                };
            }

            this._smoothedPose = { yaw: this._baseline.yaw_mean, pitch: this._baseline.pitch_mean, roll: this._baseline.roll_mean };
            this._filterInitialized = true;

            this._setStatus('✓ Baseline calibrated (' + this._baseline.status + ')', 'ok');
            this.onEnvCheckPass();
        } else {
            this._setStatus('✗ Calibration failed', 'error');
            this.onEnvCheckFail(reasons);
        }

        return { passed, reasons, baseline: this._baseline, identity: this._identityRef, gaze: this._gazeModel };
    }

    startMonitoring() {
        if (!this._token || !this._stream) return;
        this._isActive = true;

        const monitorInterval   = this._settings.monitor_interval_ms   || 1500;
        const heartbeatInterval = this._settings.heartbeat_interval_ms || 15000;

        this._lastFrameTime = Date.now();
        // Skip a tick if the previous frame is still processing (slow CPU-delegate machines)
        this._monitorLoop   = setInterval(() => {
            if (this._frameBusy) return;
            this._frameBusy = true;
            this._monitorFrame()
                .catch(e => console.warn('[LakshyaProctor] Frame check error:', e))
                .finally(() => { this._frameBusy = false; });
        }, monitorInterval);
        this._heartbeatLoop = setInterval(() => this._heartbeat(), heartbeatInterval);

        this._attachBrowserListeners();
    }

    // Alias so pages can call either proctorEngine.start() or proctorEngine.startMonitoring()
    start() {
        return this.startMonitoring();
    }


    stop() {
        this._isActive = false;
        clearInterval(this._monitorLoop);
        clearInterval(this._heartbeatLoop);
        this._detachBrowserListeners();

        if (this._stream) {
            this._stream.getTracks().forEach(t => t.stop());
            this._stream = null;
        }

        if (this._screenStream) {
            this._screenStream.getTracks().forEach(t => t.stop());
            this._screenStream = null;
        }

        if (this._screenVideoEl && this._screenVideoEl.parentNode) {
            this._screenVideoEl.parentNode.removeChild(this._screenVideoEl);
            this._screenVideoEl = null;
        }

        if (this._token) {
            this._post('end_session', { token: this._token }).catch(() => {});
        }
    }


    // ─────────────────────────────────────────────────────────
    // PRIVATE: SENSORS & 4-TIER EVIDENCE OBSERVATION PIPELINE
    // ─────────────────────────────────────────────────────────

    async _monitorFrame() {
        if (!this._isActive || !this._videoEl) return;

        const now = Date.now();
        const deltaTMs = this._lastFrameTime > 0 ? (now - this._lastFrameTime) : 1500;
        this._lastFrameTime = now;

        const rawDetections = await this._detectFaces();
        // No frame yet or detector still (re)loading — skip rather than report a false NO_FACE
        if (rawDetections === null) return;
        const lum = this._getFrameLuminance();
        // Drop weak / tiny / duplicate detections (posters, reflections, double boxes on one face)
        const detections = this._filterRealFaces(rawDetections);
        const faceCount = detections.length;

        // 1. Lighting / Scene Quality Gate (Non-punitive)
        if (lum < 20) {
            this._setPreviewStatusIndicator('warning');
            // Reported every ~4.5 s so the server can tell a covered lens (black for 10 s) from a dim room
            if (this._canReport('LOW_LIGHT', 4000)) {
                await this._reportObservation('OCCLUSION_SAMPLE', 0, 0.95, null, {
                    scene_luminance: Number(lum.toFixed(1)),
                    face_count: faceCount,
                    explanation: 'Low room illumination detected'
                });
            }
            return;
        }

        // 2. Multi-Face Presence → MULTI_FACE (matches admin dashboard label)
        if (faceCount > 1) {
            // Must be seen on two consecutive checks (~3 s) before it is reported
            this._multiFaceFrames = (this._multiFaceFrames || 0) + 1;
            if (this._multiFaceFrames < 2) return;
            this._setPreviewStatusIndicator('warning');
            if (this._canReport('MULTI_FACE', 4000)) {
                const snapshot = this._captureSnapshot();
                const scores = detections.map(d => Number((d.score || 0).toFixed(2)));
                await this._reportObservation('MULTI_FACE', 2000, Math.min(...scores), snapshot, {
                    face_count: faceCount,
                    face_scores: scores,
                    scene_luminance: Number(lum.toFixed(1))
                });
            }
            return;
        }
        this._multiFaceFrames = 0;

        // 3. Unattended Station (Face Absence) → NO_FACE/UNATTENDED_STATION
        if (faceCount === 0) {
            this._setPreviewStatusIndicator('warning');
            if (!this._unattendedStartTime) this._unattendedStartTime = now;
            const absentDurationMs = now - this._unattendedStartTime;

            if (absentDurationMs >= 3000 && this._canReport('NO_FACE', 4000)) {
                const snapshot = this._captureSnapshot();
                // Send NO_FACE first for immediate admin visibility
                await this._reportObservation('NO_FACE', absentDurationMs, 0.90, snapshot, {
                    face_count: 0,
                    scene_luminance: Number(lum.toFixed(1)),
                    camera_stable: true,
                    duration_ms: absentDurationMs
                });
            }
            return;
        } else {
            this._unattendedStartTime = null;
        }

        // 3b. Phone / Prohibited Device — geometric proxy heuristic
        // Detects a bright rectangular foreign object (phone screen glow) via canvas luminance
        // analysis. When a phone is held in view it creates a high-contrast bright rectangle
        // with a different colour temperature than a human face, detectable as a luminance spike
        // in the peripheral image zone vs. the face-centred zone.
        const phoneScore = this._estimatePhonePresence();
        if (phoneScore > 0.60) {
            if (!this._devicePresenceStartTime) this._devicePresenceStartTime = now;
            const devDurationMs = now - this._devicePresenceStartTime;

            // Heuristic only: logged for review (never a strike), so report rarely
            if (devDurationMs >= 3000 && this._canReport('PROHIBITED_DEVICE_DETECTED', 20000)) {
                const snapshot = this._captureSnapshot();
                await this._reportObservation('PROHIBITED_DEVICE_DETECTED', devDurationMs, phoneScore, snapshot, {
                    class: 'cell_phone',
                    confidence: Number(phoneScore.toFixed(2)),
                    duration_ms: devDurationMs,
                    detection_method: 'luminance_heuristic'
                });
            }
        } else {
            this._devicePresenceStartTime = null;
        }


        // ── SINGLE FACE DETECTED: EXTRACT DETAILED GEOMETRY ──
        const detection = detections[0];
        const keypoints = detection.keypoints;
        const faceScore = detection.score || 0.90;
        const faceBBox  = detection.box || { xMin: 0.2, yMin: 0.2, width: 0.6, height: 0.6 };

        // 4. Geometric Landmark-Group Weighted Occlusion
        const occAnalysis = this._analyzeLandmarkGroupOcclusion(keypoints);
        if (occAnalysis.weighted_loss >= 0.40) {
            this._setPreviewStatusIndicator('warning');
            if (!this._occlusionStartTime) this._occlusionStartTime = now;
            const occDurationMs = now - this._occlusionStartTime;

            if (occDurationMs >= 3000 && this._canReport('FACE_OCCLUSION', 4000)) {
                const snapshot = this._captureSnapshot();
                await this._reportObservation('OCCLUSION_SAMPLE', occDurationMs, 0.85, snapshot, {
                    face_count: 1,
                    scene_luminance: Number(lum.toFixed(1)),
                    weighted_occlusion_ratio: occAnalysis.weighted_loss,
                    landmark_groups: occAnalysis.groups
                });
            }
        } else {
            this._occlusionStartTime = null;
        }

        // 4b. Frame quality gate. A blurry, dark or far-away face on a poor webcam gives landmarks
        // that jump several pixels between frames, which used to raise false "deviation" flags.
        // Such frames are not judged at all: they neither count as a deviation nor clear one that
        // is in progress, so a real sustained look-down (e.g. at a phone) still accumulates.
        if (!this._isReliableFrame(faceScore, keypoints, lum)) {
            this._lowQualityFrames++;
            return;
        }
        this._lowQualityFrames = 0;

        // Head Pose Estimation (needed before the identity check). Frames are ~1.5 s apart, so
        // an EMA did no smoothing; the median of the last 3 frames removes single-frame jumps.
        const rawPose = this._estimateHeadPose(keypoints);
        if (rawPose) {
            this._smoothedPose = this._medianOf(this._poseHistory, rawPose, ['yaw', 'pitch', 'roll']);
            this._filterInitialized = true;
        }
        const poseDeltaPitch = this._smoothedPose.pitch - this._baseline.pitch_mean;
        const poseDeltaYaw   = this._smoothedPose.yaw - this._baseline.yaw_mean;
        // Vertical face ratios (eye-nose-mouth distances) change when the head tilts up/down, which
        // made simply reading the bottom of the screen look like "a different person". When the head
        // is tilted, identity is judged only on the side-to-side ear/eye proportions (topology
        // indices 2 and 3), which tilting doesn't change. A turned head distorts everything, so
        // identity is not judged then (the sideways-deviation check covers that).
        const yawNearCalibration   = Math.abs(poseDeltaYaw) <= 10.0;
        const pitchNearCalibration = Math.abs(poseDeltaPitch) <= 8.0;
        const identityDims = pitchNearCalibration ? null : [2, 3];

        // 5. Identity Consistency Evaluation
        if (this._identityRef.enrolled && keypoints && yawNearCalibration) {
            const rawTopo = this._extractLandmarkTopology(keypoints);
            // Median over 3 frames, like pose: one jittery frame must not look like a new face
            const currentTopo = rawTopo
                ? Object.values(this._medianOf(this._topoHistory, Object.assign({}, rawTopo), rawTopo.map((_, i) => i)))
                : null;
            if (currentTopo) {
                const sid = this._calculateIdentityConsistency(currentTopo, this._identityRef, identityDims);
                if (sid < 0.70) {
                    if (!this._identityMismatchStartTime) this._identityMismatchStartTime = now;
                    const misDurationMs = now - this._identityMismatchStartTime;

                    if (misDurationMs >= (this._settings.identity_sustain_ms || 8000) && this._canReport('IDENTITY_SAMPLE', 4000)) {
                        const snapshot = this._captureSnapshot();
                        await this._reportObservation('IDENTITY_SAMPLE', misDurationMs, sid, snapshot, {
                            identity_score: Number(sid.toFixed(3)),
                            landmark_quality: Number(faceScore.toFixed(2)),
                            duration_ms: misDurationMs
                        });
                    }
                } else {
                    this._identityMismatchStartTime = null;
                }
            }
        }

        // 6. 2D Covariance Ellipse Gaze Model Evaluation
        if (this._gazeModel.calibrated && keypoints) {
            const rawGaze = this._estimateScreenAttentionGaze(keypoints);
            const gaze = rawGaze ? this._medianOf(this._gazeHistory, rawGaze, ['rx', 'ry']) : null;
            if (gaze) {
                const dx = gaze.rx - this._gazeModel.mu_x;
                const dy = gaze.ry - this._gazeModel.mu_y;
                const dmSq = (dx * dx * this._gazeModel.inv_cov_xx) +
                             (dy * dy * this._gazeModel.inv_cov_yy) +
                             (2 * dx * dy * this._gazeModel.inv_cov_xy);

                const dmSqThresh = this._settings.gaze_mahalanobis_sq_threshold || 13.82;
                const gazeSustainMs = this._settings.gaze_sustain_ms || 8000;
                if (dmSq > dmSqThresh) {
                    if (!this._gazeDivergenceStartTime) this._gazeDivergenceStartTime = now;
                    const gazeDurationMs = now - this._gazeDivergenceStartTime;

                    if (gazeDurationMs >= gazeSustainMs && this._canReport('EYE_GAZE_SAMPLE', 4000)) {
                        const snapshot = this._captureSnapshot();
                        await this._reportObservation('EYE_GAZE_SAMPLE', gazeDurationMs, 0.85, snapshot, {
                            mahalanobis_sq: Number(dmSq.toFixed(2)),
                            is_diverged: true,
                            gaze_r: { rx: Number(gaze.rx.toFixed(3)), ry: Number(gaze.ry.toFixed(3)) }
                        });
                    }
                } else {
                    this._gazeDivergenceStartTime = null;
                }
            }
        }

        // 7. Head pose deviation (pose was smoothed above)
        const deltaPitch = this._smoothedPose.pitch - this._baseline.pitch_mean;
        const zPitch     = deltaPitch / this._baseline.pitch_std;
        const deltaYaw   = this._smoothedPose.yaw - this._baseline.yaw_mean;
        const zYaw       = deltaYaw / this._baseline.yaw_std;

        // Reading the bottom of the screen tilts the head ~10-15°; coding rounds also look at
        // the keyboard. Limits come from the server per assessment type.
        const downwardDeg = this._settings.downward_pitch_deg || 22.0;
        const sidewaysDeg = this._settings.sideways_yaw_deg || 22.0;
        const isDownwardDev = (deltaPitch < -downwardDeg && zPitch < -2.2);
        const isSidewaysDev = (Math.abs(deltaYaw) > sidewaysDeg && Math.abs(zYaw) > 2.2);

        // Downward Attention Accumulator
        if (isDownwardDev) {
            if (!this._downwardAttnStartTime) this._downwardAttnStartTime = now;
            const durationMs = now - this._downwardAttnStartTime;
            const thresholdMs = this._settings.downward_attn_threshold_ms || 6000;

            if (durationMs >= thresholdMs && this._canReport('SUSTAINED_DOWNWARD_ATTENTION', 5000)) {
                const snapshot = this._captureSnapshot();
                await this._reportObservation('SUSTAINED_DOWNWARD_ATTENTION', durationMs, 0.88, snapshot, {
                    observed: { delta_pitch: Number(deltaPitch.toFixed(2)), z_pitch: Number(zPitch.toFixed(2)) },
                    baseline: this._baseline
                });
            }
        } else {
            this._downwardAttnStartTime = null;
        }

        // Sideways Deviation Accumulator
        if (isSidewaysDev) {
            if (!this._sidewaysDevStartTime) this._sidewaysDevStartTime = now;
            const durationMs = now - this._sidewaysDevStartTime;
            const thresholdMs = this._settings.sideways_dev_threshold_ms || 6000;

            if (durationMs >= thresholdMs && this._canReport('SUSTAINED_SIDEWAYS_DEVIATION', 5000)) {
                const snapshot = this._captureSnapshot();
                await this._reportObservation('SUSTAINED_SIDEWAYS_DEVIATION', durationMs, 0.88, snapshot, {
                    observed: { delta_yaw: Number(deltaYaw.toFixed(2)), z_yaw: Number(zYaw.toFixed(2)) },
                    baseline: this._baseline
                });
            }
        } else {
            this._sidewaysDevStartTime = null;
        }

        // Periodic Clean Frame (Hysteresis Recovery)
        const isClean = (!isDownwardDev && !isSidewaysDev && occAnalysis.weighted_loss < 0.25);
        if (isClean && (now - this._lastCleanFrameTime > 8000)) {
            this._lastCleanFrameTime = now;
            await this._reportObservation('CLEAN_FRAME', 0, 1.0, null, {
                observed: { delta_pitch: Number(deltaPitch.toFixed(1)), delta_yaw: Number(deltaYaw.toFixed(1)) }
            });
        }

        // The preview only turns red once a deviation has lasted a few seconds (no flicker from a
        // brief glance), and returns to normal as soon as the student looks back at the screen
        const displayMs = this._settings.deviation_display_ms || 3000;
        const sustainedDeviation =
            (this._downwardAttnStartTime && now - this._downwardAttnStartTime >= displayMs) ||
            (this._sidewaysDevStartTime && now - this._sidewaysDevStartTime >= displayMs);
        this._setPreviewStatusIndicator(sustainedDeviation ? 'deviated' : 'normal');
    }

    /**
     * Keeps only plausible real faces: score >= 0.75, box at least 8% of frame width, and not
     * overlapping an already-kept (higher scoring) face by more than 30% (duplicate boxes).
     * The best face is always kept so a single weak detection still counts as "a face".
     */
    _filterRealFaces(detections) {
        if (!detections || detections.length <= 1) return detections;
        const boxOf = d => {
            const b = d.boundingBox;
            return b ? { x: b.originX, y: b.originY, w: b.width, h: b.height } : null;
        };
        const frameW = this._canvasEl ? this._canvasEl.width : 320;
        const sorted = [...detections].sort((a, b) => (b.score || 0) - (a.score || 0));
        const kept = [sorted[0]];
        for (let i = 1; i < sorted.length; i++) {
            const d = sorted[i];
            const box = boxOf(d);
            if ((d.score || 0) < 0.75) continue;
            if (box && box.w < frameW * 0.08) continue;
            let duplicate = false;
            for (const k of kept) {
                const kb = boxOf(k);
                if (!box || !kb) continue;
                const ix = Math.max(0, Math.min(box.x + box.w, kb.x + kb.w) - Math.max(box.x, kb.x));
                const iy = Math.max(0, Math.min(box.y + box.h, kb.y + kb.h) - Math.max(box.y, kb.y));
                const smaller = Math.min(box.w * box.h, kb.w * kb.h) || 1;
                if ((ix * iy) / smaller > 0.30) { duplicate = true; break; }
            }
            if (!duplicate) kept.push(d);
        }
        return kept;
    }

    /** Pushes a sample into a 3-frame history and returns the per-key median */
    _medianOf(history, sample, keys) {
        history.push(sample);
        if (history.length > 3) history.shift();
        const out = {};
        for (const k of keys) {
            const vals = history.map(s => s[k]).sort((a, b) => a - b);
            out[k] = vals[Math.floor(vals.length / 2)];
        }
        return out;
    }

    /** Is this frame clear enough to judge where the student is looking? */
    _isReliableFrame(faceScore, keypoints, lum) {
        if (!keypoints || keypoints.length < 6) return false;
        const minScore = this._settings.min_face_score || 0.80;
        const minLum   = this._settings.min_quality_luminance || 35;
        const minIo    = this._settings.min_interocular || 0.055;
        let interOcular = Math.hypot(keypoints[1].x - keypoints[0].x, keypoints[1].y - keypoints[0].y);
        // MediaPipe gives normalised (0-1) coordinates; the face-api fallback gives pixels
        if (interOcular > 1.5 && this._canvasEl) interOcular = interOcular / this._canvasEl.width;
        return faceScore >= minScore && lum >= minLum && interOcular >= minIo;
    }

    // ─────────────────────────────────────────────────────────
    // MATHEMATICAL VISION MODELS & SENSING UTILITIES
    // ─────────────────────────────────────────────────────────

    _extractLandmarkTopology(keypoints) {
        if (!keypoints || keypoints.length < 6) return null;

        const rightEye = keypoints[0];
        const leftEye  = keypoints[1];
        const nose     = keypoints[2];
        const mouth    = keypoints[3];
        const rightEar = keypoints[4];
        const leftEar  = keypoints[5];

        const interOcular = Math.max(1e-4, Math.hypot(leftEye.x - rightEye.x, leftEye.y - rightEye.y));
        const midEyeX = (leftEye.x + rightEye.x) / 2.0;
        const midEyeY = (leftEye.y + rightEye.y) / 2.0;

        // Normalized relative distances
        const r1 = Math.hypot(nose.x - midEyeX, nose.y - midEyeY) / interOcular;
        const r2 = Math.hypot(mouth.x - nose.x, mouth.y - nose.y) / interOcular;
        const r3 = Math.hypot(rightEar.x - rightEye.x, rightEar.y - rightEye.y) / interOcular;
        const r4 = Math.hypot(leftEar.x - leftEye.x, leftEar.y - leftEye.y) / interOcular;
        const r5 = (mouth.y - midEyeY) / interOcular;
        const r6 = (nose.y - midEyeY) / interOcular;

        return [r1, r2, r3, r4, r5, r6];
    }

    /**
     * 1.0 = same face shape as calibration, 0 = very different. Each measurement is compared
     * against its own calibration spread, so ordinary camera noise scores ~0.85+ (the previous
     * single pooled variance scored typical noise at ~0.6, below the 0.70 mismatch line), while
     * a different person's proportions score near 0. $dims limits the comparison to a subset.
     */
    _calculateIdentityConsistency(currentTopo, ref, dims = null) {
        if (!ref.enrolled || !ref.descriptor_mean || ref.descriptor_mean.length !== currentTopo.length) {
            return 1.0;
        }
        const idx = dims || currentTopo.map((_, i) => i);
        const pooled = ref.descriptor_var / currentTopo.length;

        let meanZSq = 0;
        for (const i of idx) {
            const v = Math.max(0.003, (ref.descriptor_var_dims && ref.descriptor_var_dims[i]) || pooled);
            meanZSq += Math.pow(currentTopo[i] - ref.descriptor_mean[i], 2) / v;
        }
        meanZSq /= idx.length;

        const score = Math.exp(-meanZSq / 8.0);
        return Math.max(0.0, Math.min(1.0, score));
    }

    _estimateScreenAttentionGaze(keypoints) {
        if (!keypoints || keypoints.length < 6) return null;
        const rightEye = keypoints[0];
        const leftEye  = keypoints[1];
        const nose     = keypoints[2];
        const mouth    = keypoints[3];

        const midEyeX = (leftEye.x + rightEye.x) / 2.0;
        const midEyeY = (leftEye.y + rightEye.y) / 2.0;
        const interOcular = Math.max(1e-4, Math.hypot(leftEye.x - rightEye.x, leftEye.y - rightEye.y));

        const rx = (nose.x - midEyeX) / interOcular;
        const ry = (nose.y - midEyeY) / interOcular;

        return { rx, ry };
    }

    _analyzeLandmarkGroupOcclusion(keypoints) {
        if (!keypoints) return { weighted_loss: 1.0, groups: {} };

        // Check availability of key clusters
        const hasEyes  = Boolean(keypoints[0] && keypoints[1]);
        const hasNose  = Boolean(keypoints[2]);
        const hasMouth = Boolean(keypoints[3]);
        const hasEars  = Boolean(keypoints[4] && keypoints[5]);

        let weightedLoss = 0.0;
        if (!hasEyes)  weightedLoss += 0.35;
        if (!hasNose)  weightedLoss += 0.30;
        if (!hasMouth) weightedLoss += 0.25;
        if (!hasEars)  weightedLoss += 0.10;

        return {
            weighted_loss: Number(weightedLoss.toFixed(2)),
            groups: { eyes: hasEyes ? 1.0 : 0.0, nose: hasNose ? 1.0 : 0.0, mouth: hasMouth ? 1.0 : 0.0, ears: hasEars ? 1.0 : 0.0 }
        };
    }

    _estimateHeadPose(keypoints) {
        if (!keypoints || keypoints.length < 6) return null;

        const rightEye = keypoints[0];
        const leftEye  = keypoints[1];
        const nose     = keypoints[2];
        const mouth    = keypoints[3];
        const rightEar = keypoints[4];
        const leftEar  = keypoints[5];

        const dX = leftEye.x - rightEye.x;
        const dY = leftEye.y - rightEye.y;
        const rollDeg = Math.atan2(dY, dX) * (180 / Math.PI);

        const distRightEarEye = Math.abs(nose.x - rightEar.x);
        const distLeftEarEye  = Math.abs(leftEar.x - nose.x);
        const totalSpan = distRightEarEye + distLeftEarEye;
        let yawDeg = 0;
        if (totalSpan > 0) {
            const asymmetry = (distLeftEarEye - distRightEarEye) / totalSpan;
            yawDeg = asymmetry * 90.0;
        }

        const eyeCenterY = (rightEye.y + leftEye.y) / 2;
        const eyeToNose = nose.y - eyeCenterY;
        const noseToMouth = mouth.y - nose.y;
        let pitchDeg = 0;
        if (noseToMouth > 0) {
            const verticalRatio = eyeToNose / noseToMouth;
            pitchDeg = (1.0 - verticalRatio) * 60.0;
        }

        return {
            yaw: Number(yawDeg.toFixed(2)),
            pitch: Number(pitchDeg.toFixed(2)),
            roll: Number(rollDeg.toFixed(2))
        };
    }

    async _reportObservation(eventType, durationMs, confidence, snapshotBase64, metadata) {
        if (!this._isActive || !this._token) return;

        // INSTANT 0ms BLINK-OF-AN-EYE SNAPSHOT CAPTURE
        // Capture snapshot frame synchronously in memory BEFORE network latency
        // Routine samples carry no evidence, so don't capture/upload a picture for them
        const noSnapshot = eventType === 'CLEAN_FRAME' || eventType === 'CALIBRATION_ENROLLMENT';
        const instantSnapshot = snapshotBase64 || (noSnapshot ? null : this._captureSnapshot());

        this._seq++;
        try {
            const res = await this._post('record_observation', {
                token:            this._token,
                seq:              this._seq,
                event_type:       eventType,
                confidence:       confidence,
                snapshot:         instantSnapshot,
                metadata:         metadata
            });

            this._handleDirective(res);
        } catch (e) {
            console.warn('[LakshyaProctor] Telemetry transmission error:', e);
        }
    }

    async _heartbeat() {
        if (!this._isActive || !this._token) return;

        this._seq++;
        try {
            const res = await this._post('heartbeat', {
                token:         this._token,
                seq:           this._seq,
                timestamp:     Date.now(),
                camera_active: Boolean(this._stream && this._stream.active),
                fullscreen:    Boolean(document.fullscreenElement || document.webkitFullscreenElement),
                tab_focused:   !document.hidden
            });

            this._handleDirective(res);
        } catch (e) {}
    }

    _handleDirective(res) {
        if (!res || !res.success) return;

        const action      = res.action || 'continue';
        const strikeCount = res.strike_count || 0;
        const penaltyPct  = res.penalty_pct || 0;
        const score       = res.suspicion_score || 0;
        const msg         = res.message || '';

        this._updateRiskDisplay(score, strikeCount, penaltyPct);

        switch (action) {
            case 'notice':
                // First focus loss: warning only, no strike or penalty
                this.onWarning(msg, false, penaltyPct);
                this._showOverlayWarning(msg, false, strikeCount, penaltyPct, true);
                break;

            case 'warn':
                this.onWarning(msg, false, penaltyPct);
                this._showOverlayWarning(msg, false, strikeCount, penaltyPct);
                break;

            case 'final_warn':
                this.onWarning(msg, true, penaltyPct);
                this._showOverlayWarning(msg, true, strikeCount, penaltyPct);
                break;

            case 'terminate':
            case 'auto_submit':
                this._isActive = false;
                this._showAutoSubmitOverlay(msg || 'Assessment auto-submitted due to integrity policy violations.');
                this.stop();
                setTimeout(() => this.onAutoSubmit(), 2500);
                break;

            case 'continue':
            default:
                break;
        }
    }

    _canReport(eventType, throttleMs) {
        const now = Date.now();
        const last = this._lastReportedTime[eventType] || 0;
        if (now - last > throttleMs) {
            this._lastReportedTime[eventType] = now;
            return true;
        }
        return false;
    }

    /**
     * Pages call this right before requestFullscreen(): the macOS fullscreen transition
     * can briefly blur the window, which must not count as a violation.
     */
    noteFullscreenRequest() {
        this._focusGraceUntil = Date.now() + 1500;
    }

    // The server gives an instant strike per focus event, and one action (Cmd+Tab, Alt+Tab)
    // fires blur + visibilitychange (+ fullscreenchange) together. Report it once.
    _claimFocusLossReport() {
        const now = Date.now();
        if (now < (this._focusGraceUntil || 0)) return false;
        if (now - (this._lastFocusLossReport || 0) < 3000) return false;
        this._lastFocusLossReport = now;
        return true;
    }

    _attachBrowserListeners() {
        // INSTANT CAPTURE ON TAB SWITCH (Prioritizing desktop screen stream if active)
        this._onVisibilityChange = async () => {
            if (document.hidden && this._isActive && this._claimFocusLossReport()) {
                // Brief 120ms pause allows OS window manager to bring switched tab/window to foreground on screen stream
                await this._sleep(120);
                const instantSnapshot = this._captureSnapshot(true);
                await this._reportObservation('TAB_SWITCH', 1000, 1.0, instantSnapshot, { trigger: 'visibility_hidden' });
            }
        };

        // INSTANT CAPTURE ON WINDOW BLUR (Focus Lost)
        this._onBlur = async () => {
            if (this._isActive && this._claimFocusLossReport()) {
                await this._sleep(120);
                const instantSnapshot = this._captureSnapshot(true);
                await this._reportObservation('WINDOW_BLUR', 1000, 1.0, instantSnapshot, { trigger: 'window_blur' });
            }
        };

        // INSTANT CAPTURE ON FULLSCREEN EXIT (webkit-prefixed for Safari < 16.4)
        this._onFullscreenChange = async () => {
            const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
            if (fsEl) {
                // Entering fullscreen — ignore the focus flicker of the macOS transition
                this.noteFullscreenRequest();
                return;
            }
            if (this._isActive && this._claimFocusLossReport()) {
                const instantSnapshot = this._captureSnapshot(true);
                await this._reportObservation('FULLSCREEN_EXIT', 1000, 1.0, instantSnapshot, { trigger: 'fullscreen_exit' });
            }
        };

        document.addEventListener('visibilitychange', this._onVisibilityChange);
        window.addEventListener('blur', this._onBlur);
        document.addEventListener('fullscreenchange', this._onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', this._onFullscreenChange);
    }


    _detachBrowserListeners() {
        if (this._onVisibilityChange) {
            document.removeEventListener('visibilitychange', this._onVisibilityChange);
        }
        if (this._onBlur) {
            window.removeEventListener('blur', this._onBlur);
        }
        if (this._onFullscreenChange) {
            document.removeEventListener('fullscreenchange', this._onFullscreenChange);
            document.removeEventListener('webkitfullscreenchange', this._onFullscreenChange);
        }
    }

    // init() warms the detector and runEnvCheck() awaits it — share one in-flight load
    _loadDetector(delegates = ['GPU', 'CPU']) {
        if (this._detector) return Promise.resolve();
        if (!this._detectorLoading) {
            this._detectorLoading = this._createDetector(delegates)
                .finally(() => { this._detectorLoading = null; });
        }
        return this._detectorLoading;
    }

    async _createDetector(delegates) {
        // Pinned so the JS bundle and the WASM files always come from the same release
        const MEDIAPIPE_VERSION = '0.10.14';
        try {
            const { FaceDetector, FilesetResolver } = await import(
                `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MEDIAPIPE_VERSION}/vision_bundle.mjs`
            );
            const vision = await FilesetResolver.forVisionTasks(
                `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${MEDIAPIPE_VERSION}/wasm`
            );
            // The GPU delegate fails on some Macs / Safari builds — without the CPU fallback
            // no detector loads and calibration always fails with "face not visible"
            for (const delegate of delegates) {
                try {
                    this._detector = await FaceDetector.createFromOptions(vision, {
                        baseOptions: {
                            modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite',
                            delegate: delegate
                        },
                        runningMode: 'IMAGE',
                        minDetectionConfidence: 0.5,
                    });
                    this._detectorMode = 'mediapipe';
                    console.log(`[LakshyaProctor] MediaPipe face detector ready (${delegate}).`);
                    return;
                } catch (delegateErr) {
                    console.warn(`[LakshyaProctor] MediaPipe ${delegate} delegate failed:`, delegateErr);
                }
            }
        } catch (e) {
            console.warn('[LakshyaProctor] MediaPipe failed to load:', e);
        }
    }

    async _detectFaces() {
        // null = "unknown" (no frame or no detector) — callers must not treat that as "no face"
        if (!this._videoEl || !this._canvasEl) return null;
        if (this._videoEl.readyState < 2 || this._videoEl.videoWidth === 0) return null;
        const ctx = this._canvasEl.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(this._videoEl, 0, 0, this._canvasEl.width, this._canvasEl.height);

        if (this._detectorMode === 'mediapipe' && this._detector) {
            try {
                const result = this._detector.detect(this._canvasEl);
                return (result?.detections || []).map(d => ({
                    ...d,
                    // tasks-vision puts the score in categories[0]
                    score: d.score ?? d.categories?.[0]?.score ?? 0.90
                }));
            } catch (e) {
                // GPU context can be lost at runtime (common on Safari) — rebuild on CPU
                console.warn('[LakshyaProctor] Face detection failed, switching to CPU:', e);
                try { this._detector.close(); } catch (closeErr) {}
                this._detector = null;
                this._loadDetector(['CPU']);
                return null;
            }
        }
        if (this._detectorMode === 'faceapi' && typeof faceapi !== 'undefined') {
            const detections = await faceapi.detectAllFaces(this._canvasEl, new faceapi.TinyFaceDetectorOptions());
            return detections || [];
        }
        return null;
    }

    _getFrameLuminance() {
        if (!this._canvasEl) return 100;
        const ctx = this._canvasEl.getContext('2d', { willReadFrequently: true });
        const data = ctx.getImageData(0, 0, this._canvasEl.width, this._canvasEl.height).data;
        let sum = 0;
        for (let i = 0; i < data.length; i += 16) {
            sum += 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
        }
        return sum / (data.length / 16);
    }

    _captureSnapshot(preferScreen = false) {
        try {
            // Own canvas: resizing the 320x240 analysis canvas to screen size (5K px on Retina Macs)
            // made every following detection/luminance pass huge and slow
            if (!this._snapshotCanvas) {
                this._snapshotCanvas = document.createElement('canvas');
            }
            const canvas = this._snapshotCanvas;
            const hasScreen = Boolean(this._screenVideoEl && this._screenVideoEl.videoWidth > 0);
            const hasWebcam = Boolean(this._videoEl && this._videoEl.videoWidth > 0);

            if (preferScreen && hasScreen) {
                const screenScale = Math.min(1, 1600 / (this._screenVideoEl.videoWidth || 1280));
                const sw = Math.round((this._screenVideoEl.videoWidth || 1280) * screenScale);
                const sh = Math.round((this._screenVideoEl.videoHeight || 720) * screenScale);
                canvas.width = sw;
                canvas.height = sh;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(this._screenVideoEl, 0, 0, sw, sh);

                // Overlay webcam PiP in bottom-right corner if available
                if (hasWebcam) {
                    const pipW = Math.min(320, Math.floor(sw * 0.25));
                    const pipH = Math.floor(pipW * ((this._videoEl.videoHeight || 480) / (this._videoEl.videoWidth || 640)));
                    const pipX = sw - pipW - 16;
                    const pipY = sh - pipH - 16;

                    ctx.fillStyle = '#000000';
                    ctx.fillRect(pipX - 3, pipY - 3, pipW + 6, pipH + 6);
                    ctx.drawImage(this._videoEl, pipX, pipY, pipW, pipH);

                    // Red badge on PiP
                    ctx.fillStyle = '#ef4444';
                    ctx.font = 'bold 13px sans-serif';
                    ctx.fillText('🔴 CAM + SCREEN EVIDENCE', pipX + 8, pipY + 20);
                }
                return canvas.toDataURL('image/jpeg', 0.80);
            }

            if (hasWebcam) {
                canvas.width = this._videoEl.videoWidth || 640;
                canvas.height = this._videoEl.videoHeight || 480;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(this._videoEl, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/jpeg', 0.80);
            }

            if (hasScreen) {
                const screenScale = Math.min(1, 1600 / (this._screenVideoEl.videoWidth || 1280));
                canvas.width = Math.round((this._screenVideoEl.videoWidth || 1280) * screenScale);
                canvas.height = Math.round((this._screenVideoEl.videoHeight || 720) * screenScale);
                const ctx = canvas.getContext('2d');
                ctx.drawImage(this._screenVideoEl, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/jpeg', 0.80);
            }

            return null;
        } catch (e) {
            console.error('[LakshyaProctor] Snapshot capture error:', e);
            return null;
        }
    }


    /**
     * Phone / Prohibited Device Heuristic Detector
     *
     * Strategy: A phone or tablet screen held in the frame produces a bright, high-saturation,
     * high-contrast rectangular glow that is:
     *   (a) significantly brighter than the face region in a localised area
     *   (b) has unusually high pixel variance (sharp edges of a screen vs. organic face texture)
     *   (c) contributes an abnormal saturation spike in the peripheral zones
     *
     * Returns a [0.0, 1.0] suspicion score. Above 0.60 = likely phone present.
     */
    _estimatePhonePresence() {
        if (!this._canvasEl || !this._videoEl || this._videoEl.videoWidth === 0) return 0.0;
        try {
            const ctx = this._canvasEl.getContext('2d');
            const w = this._canvasEl.width;
            const h = this._canvasEl.height;

            // Sample three zones at lap/desk level: left peripheral, bottom centre (lap/keyboard), right peripheral
            // A phone in use is held in hands resting on or near desk/lap level (lower half of frame),
            // NOT in the upper background wall/ceiling where lamps, fans, and switchboards reside.
            const deskY = Math.floor(h * 0.45);
            const deskH = Math.floor(h * 0.55);
            const zones = [
                { x: 0,                      y: deskY, zw: Math.floor(w * 0.30), zh: deskH, label: 'left'   },
                { x: Math.floor(w * 0.35),   y: Math.floor(h * 0.55), zw: Math.floor(w * 0.30), zh: Math.floor(h * 0.45), label: 'bottom_centre' },
                { x: Math.floor(w * 0.70),   y: deskY, zw: Math.floor(w * 0.30), zh: deskH, label: 'right'  },
            ];

            const centerX = Math.floor(w * 0.25); const centerY = Math.floor(h * 0.15);
            const centerW = Math.floor(w * 0.50); const centerH = Math.floor(h * 0.50);
            const centreData = ctx.getImageData(centerX, centerY, centerW, centerH).data;

            // Face region mean luminance
            let centreLumSum = 0;
            for (let i = 0; i < centreData.length; i += 16) {
                centreLumSum += 0.299 * centreData[i] + 0.587 * centreData[i+1] + 0.114 * centreData[i+2];
            }
            const centreLum = centreLumSum / (centreData.length / 16);

            let maxZoneScore = 0.0;
            for (const zone of zones) {
                if (zone.zw < 10 || zone.zh < 10) continue;
                const zData = ctx.getImageData(zone.x, zone.y, zone.zw, zone.zh).data;
                let lumSum = 0, satSum = 0, varianceAcc = 0;
                const samples = [];
                for (let i = 0; i < zData.length; i += 12) {
                    const r = zData[i], g = zData[i+1], b = zData[i+2];
                    const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                    const max = Math.max(r, g, b), min = Math.min(r, g, b);
                    const sat = max > 0 ? (max - min) / max : 0;
                    lumSum += lum;
                    satSum += sat;
                    samples.push(lum);
                }
                const n = samples.length;
                if (n === 0) continue;
                const meanLum = lumSum / n;
                const meanSat = satSum / n;

                // Variance (screen edges = sharp → high variance)
                for (const s of samples) {
                    const d = s - meanLum;
                    varianceAcc += d * d;
                }
                const variance = varianceAcc / n;
                const stdDev = Math.sqrt(variance);

                // Brightness excess: glowing screen must be noticeably brighter than the face area
                const brightnessExcess = Math.min(1.0, Math.max(0, (meanLum - centreLum - 30) / 120));

                // Saturation spike: clamped [0, 1]
                const satSpike = Math.min(1.0, Math.max(0, (meanSat - 0.15) / 0.35));

                // High variance = sharp rectangular edges characteristic of a screen
                const varianceScore = Math.min(1.0, stdDev / 55.0);

                // Gating: A screen MUST exhibit significant brightness excess over ambient face.
                // Painted walls, fans, switchboards, or furniture without screen emission cannot trigger.
                let zoneScore = 0.0;
                if (brightnessExcess >= 0.15) {
                    zoneScore = (0.50 * brightnessExcess) + (0.30 * varianceScore) + (0.20 * satSpike);
                }
                if (zoneScore > maxZoneScore) maxZoneScore = zoneScore;
            }

            return Math.min(1.0, maxZoneScore);
        } catch (e) {
            return 0.0;
        }
    }


    _injectUI() {
        if (!document.getElementById('proctor-css')) {
            const style = document.createElement('style');
            style.id = 'proctor-css';
            style.textContent = `
                #proctor-preview {
                    position: fixed; bottom: 16px; right: 16px; z-index: 9000;
                    width: 150px; border-radius: 12px; overflow: hidden;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.6); border: 2px solid rgba(255,255,255,0.15);
                    background: #090d16; font-family: 'Inter', sans-serif;
                    transition: border-color 0.3s ease;
                    user-select: none; -webkit-user-select: none;
                    cursor: grab;
                    touch-action: none;
                }
                #proctor-preview.is-dragging {
                    cursor: grabbing !important;
                    opacity: 0.88;
                    box-shadow: 0 16px 36px rgba(0,0,0,0.85);
                }
                #proctor-preview.hud-normal   { border-color: rgba(34, 197, 94, 0.6); }
                #proctor-preview.hud-deviated { border-color: rgba(245, 158, 11, 0.8); }
                #proctor-preview.hud-warning  { border-color: rgba(239, 68, 68, 0.9); }
                #proctor-preview video { width: 100%; display: block; border-radius: 10px 10px 0 0; pointer-events: none; transform: scaleX(-1); }
                #proctor-status-bar {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 5px 8px; font-size: 10px; color: #fff; background: rgba(15,23,42,0.95);
                    cursor: grab;
                }
                #proctor-status-bar .dot {
                    width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
                    animation: proctor-pulse 1.5s infinite;
                }
                @keyframes proctor-pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
                #proctor-risk { font-size: 10px; color: #38bdf8; font-weight: 600; }
                .proctor-warning-overlay {
                    position: fixed; top: 24px; left: 50%; transform: translateX(-50%);
                    z-index: 9999; padding: 14px 24px; border-radius: 12px;
                    font-size: 14px; font-weight: 600; text-align: center;
                    animation: proctor-fadein 0.3s ease; max-width: 90vw;
                    box-shadow: 0 12px 36px rgba(0,0,0,0.5); backdrop-filter: blur(8px);
                }
                .proctor-warning-overlay.warn   { background: rgba(245,158,11,0.95); color: #000; }
                .proctor-warning-overlay.final  { background: rgba(239,68,68,0.95); color: #fff; }
                .proctor-warning-overlay.submit { background: #0f172a; color: #fff; border: 2px solid #ef4444; }
                @keyframes proctor-fadein { from{opacity:0;top:0} to{opacity:1;top:24px} }
            `;
            document.head.appendChild(style);
        }

        if (!document.getElementById('proctor-preview')) {
            this._previewContainer = document.createElement('div');
            this._previewContainer.id = 'proctor-preview';
            this._previewContainer.className = 'hud-normal';
            this._previewContainer.title = 'Drag to reposition camera anywhere';
            this._previewContainer.innerHTML = `
                <video id="proctor-video" autoplay muted playsinline></video>
                <div id="proctor-status-bar">
                    <span class="dot"></span>
                    <span id="proctor-label" style="display:flex;align-items:center;gap:3px;"><i class="fas fa-arrows-alt" style="font-size:9px;opacity:0.7;"></i> Cam</span>
                    <span id="proctor-risk">Strikes: 0/3</span>
                </div>
            `;
            document.body.appendChild(this._previewContainer);
            this._videoEl = document.getElementById('proctor-video');
            this._makeDraggable(this._previewContainer);
        }

        if (!this._canvasEl) {
            this._canvasEl = document.createElement('canvas');
            this._canvasEl.width  = 320;
            this._canvasEl.height = 240;
            this._canvasEl.style.display = 'none';
            document.body.appendChild(this._canvasEl);
        }
    }

    _makeDraggable(el) {
        if (!el) return;
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

    _setPreviewStatusIndicator(state) {
        if (!this._previewContainer) return;
        this._previewContainer.className = (state === 'deviated') ? 'hud-deviated' : (state === 'warning' || state === 'absent') ? 'hud-warning' : 'hud-normal';
    }

    _setStatus(msg, type) {
        const el = document.getElementById('proctor-env-status');
        if (el) el.textContent = msg;
    }

    _updateRiskDisplay(suspicionScore, strikes, penaltyPct) {
        const el = document.getElementById('proctor-risk');
        if (el) {
            el.textContent = `Strikes: ${strikes}/3 (-${penaltyPct}%)`;
            el.style.color = strikes >= 2 ? '#ef4444' : strikes === 1 ? '#f59e0b' : '#38bdf8';
        }
    }

    _showOverlayWarning(msg, isFinal, strikeCount, penaltyPct, isNotice = false) {
        const existing = document.querySelector('.proctor-warning-overlay');
        if (existing) existing.remove();

        const title = isNotice
            ? '⚠️ WARNING — no penalty this time'
            : (isFinal ? '🚨 FINAL INTEGRITY WARNING (Strike 2/3)' : `⚠️ INTEGRITY WARNING (Strike ${strikeCount}/3)`);
        const div = document.createElement('div');
        div.className = `proctor-warning-overlay ${isFinal ? 'final' : 'warn'}`;
        div.innerHTML = `
            <div>${title}</div>
            <div style="font-size:12px;font-weight:400;margin-top:4px">${this._escapeHtml(msg)}</div>
        `;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 6000);
    }

    _showAutoSubmitOverlay(msg) {
        const div = document.createElement('div');
        div.className = 'proctor-warning-overlay submit';
        div.style.cssText += 'top:50%;transform:translate(-50%,-50%);padding:32px 48px;font-size:1.1rem;';
        div.innerHTML = `
            <div style="color:#ef4444;font-size:1.3rem;font-weight:700;">🔴 Assessment Terminated</div>
            <div style="font-size:13px;font-weight:400;margin-top:8px;color:#cbd5e1;">${this._escapeHtml(msg)}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:12px;">Auto-submitting your responses to the coordinator...</div>
        `;
        document.body.appendChild(div);
    }

    _escapeHtml(text) {
        return String(text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    _showError(msg) {
        this._setStatus(msg, 'error');
        console.error('[LakshyaProctor]', msg);
    }

    async _post(action, data = {}) {
        const form = new URLSearchParams();
        form.append('action', action);
        for (const [k, v] of Object.entries(data)) {
            if (typeof v === 'object' && v !== null) {
                form.append(k, JSON.stringify(v));
            } else {
                form.append(k, v ?? '');
            }
        }

        const res = await fetch(this.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: form.toString()
        });
        return res.json();
    }

    _sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

window.ProctoringEngine = ProctoringEngine;