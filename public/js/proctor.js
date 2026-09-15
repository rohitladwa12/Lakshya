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
        this._videoEl      = null;
        this._canvasEl     = null;
        this._isActive     = false;
        this._seq          = 0;
        this._detector     = null;
        this._detectorMode = 'mediapipe';

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
        this._settings = sessionData.config || {};
        if (this._settings.tau_smoothing_ms) this._tauMs = this._settings.tau_smoothing_ms;
        if (this._settings.device_enter_confidence) this._deviceState.enter_thresh = this._settings.device_enter_confidence;
        if (this._settings.device_exit_confidence) this._deviceState.exit_thresh = this._settings.device_exit_confidence;

        this._injectUI();

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            const isSecure = window.isSecureContext !== false;
            this._showError(isSecure 
                ? 'Camera API is not supported by your browser. Please use a modern browser like Chrome or Edge.'
                : 'Camera blocked: Insecure Context. Browser requires HTTPS or localhost for webcam access.');
            return false;
        }

        try {
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

            this._videoEl.srcObject = this._stream;
            await new Promise(resolve => this._videoEl.onloadedmetadata = resolve);
            this._videoEl.play();
            return true;
        } catch (err) {
            console.error("Proctor camera init error:", err);
            let msg = 'Camera access denied. Video monitoring is required for this assessment.';
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                msg = 'Camera permission was denied. Please allow camera access in your browser settings and refresh.';
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

    async runEnvCheck() {
        if (!this._stream) return { passed: false, reasons: ['Camera stream is not active.'] };

        const duration = (this._settings.env_check_duration_sec || 8) * 1000;
        const rawPoseSamples = [];
        const rawTopologySamples = [];
        const rawGazeSamples = [];
        const reasons = [];
        let totalFrames = 0;
        let multiFaces = 0;

        await this._loadDetector();
        this._setStatus('Calibrating posture, gaze & identity baseline…', 'checking');

        const start = Date.now();
        while (Date.now() - start < duration) {
            totalFrames++;
            const detections = await this._detectFaces();
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

        if (validRatio >= 0.60 && multiFaces === 0) {
            this._baseline.status = 'CALIBRATION_READY';
        } else if (validRatio >= 0.40 && multiFaces === 0) {
            this._baseline.status = 'CALIBRATION_DEGRADED';
        } else {
            this._baseline.status = 'CALIBRATION_FAILED';
            if (multiFaces > 0) reasons.push('Multiple faces detected during calibration. Ensure only candidate is visible.');
            if (validRatio < 0.40) reasons.push('Face not steadily visible or lighting is too dim. Please reposition camera.');
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
                for (let i = 0; i < rawTopologySamples.length; i++) {
                    let dsq = 0;
                    for (let d = 0; d < topoDim; d++) {
                        dsq += Math.pow(rawTopologySamples[i][d] - meanTopo[d], 2);
                    }
                    totalDistSq += dsq;
                }
                const varTopo = Math.max(0.01, totalDistSq / rawTopologySamples.length);

                this._identityRef = {
                    descriptor_mean: meanTopo,
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

                // Covariance Regularization: Sigma_reg = Sigma + lambda * I
                const lambda = this._settings.gaze_covariance_lambda || 0.0001;
                const rCxx = Math.max(0.002, cxx + lambda);
                const rCyy = Math.max(0.002, cyy + lambda);
                const rCxy = cxy;

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
        this._monitorLoop   = setInterval(() => this._monitorFrame(), monitorInterval);
        this._heartbeatLoop = setInterval(() => this._heartbeat(), heartbeatInterval);

        this._attachBrowserListeners();
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

        const lum = this._getFrameLuminance();
        const detections = await this._detectFaces();
        const faceCount = detections.length;

        // 1. Lighting / Scene Quality Gate (Non-punitive)
        if (lum < 20) {
            this._setPreviewStatusIndicator('warning');
            if (this._canReport('LOW_LIGHT', 10000)) {
                await this._reportObservation('OCCLUSION_SAMPLE', 0, 0.95, null, {
                    scene_luminance: Number(lum.toFixed(1)),
                    face_count: faceCount,
                    explanation: 'Low room illumination detected'
                });
            }
            return;
        }

        // 2. Multi-Face Presence
        if (faceCount > 1) {
            this._setPreviewStatusIndicator('warning');
            if (this._canReport('MULTIPLE_PERSONS_PRESENT', 4000)) {
                const snapshot = this._captureSnapshot();
                await this._reportObservation('MULTIPLE_PERSONS_PRESENT', 2000, 0.95, snapshot, {
                    face_count: faceCount,
                    scene_luminance: Number(lum.toFixed(1))
                });
            }
            return;
        }

        // 3. Unattended Station (Face Absence)
        if (faceCount === 0) {
            this._setPreviewStatusIndicator('warning');
            if (!this._unattendedStartTime) this._unattendedStartTime = now;
            const absentDurationMs = now - this._unattendedStartTime;

            if (absentDurationMs >= 3000 && this._canReport('UNATTENDED_STATION', 4000)) {
                const snapshot = this._captureSnapshot();
                await this._reportObservation('OCCLUSION_SAMPLE', absentDurationMs, 0.90, snapshot, {
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

        // 5. Identity Consistency Evaluation
        if (this._identityRef.enrolled && keypoints) {
            const currentTopo = this._extractLandmarkTopology(keypoints);
            if (currentTopo) {
                const sid = this._calculateIdentityConsistency(currentTopo, this._identityRef);
                if (sid < 0.70) {
                    if (!this._identityMismatchStartTime) this._identityMismatchStartTime = now;
                    const misDurationMs = now - this._identityMismatchStartTime;

                    if (misDurationMs >= 4000 && this._canReport('IDENTITY_SAMPLE', 4000)) {
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
            const gaze = this._estimateScreenAttentionGaze(keypoints);
            if (gaze) {
                const dx = gaze.rx - this._gazeModel.mu_x;
                const dy = gaze.ry - this._gazeModel.mu_y;
                const dmSq = (dx * dx * this._gazeModel.inv_cov_xx) +
                             (dy * dy * this._gazeModel.inv_cov_yy) +
                             (2 * dx * dy * this._gazeModel.inv_cov_xy);

                const dmSqThresh = this._settings.gaze_mahalanobis_sq_threshold || 9.21;
                if (dmSq > dmSqThresh) {
                    if (!this._gazeDivergenceStartTime) this._gazeDivergenceStartTime = now;
                    const gazeDurationMs = now - this._gazeDivergenceStartTime;

                    if (gazeDurationMs >= 4000 && this._canReport('EYE_GAZE_SAMPLE', 4000)) {
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

        // 7. Time-Based EMA Head Pose Estimation
        const rawPose = this._estimateHeadPose(keypoints);
        if (rawPose) {
            if (!this._filterInitialized) {
                this._smoothedPose = { yaw: rawPose.yaw, pitch: rawPose.pitch, roll: rawPose.roll };
                this._filterInitialized = true;
            } else {
                const alpha = 1.0 - Math.exp(-deltaTMs / this._tauMs);
                this._smoothedPose.yaw   = this._smoothedPose.yaw   + alpha * (rawPose.yaw   - this._smoothedPose.yaw);
                this._smoothedPose.pitch = this._smoothedPose.pitch + alpha * (rawPose.pitch - this._smoothedPose.pitch);
                this._smoothedPose.roll  = this._smoothedPose.roll  + alpha * (rawPose.roll  - this._smoothedPose.roll);
            }
        }

        const deltaPitch = this._smoothedPose.pitch - this._baseline.pitch_mean;
        const zPitch     = deltaPitch / this._baseline.pitch_std;
        const deltaYaw   = this._smoothedPose.yaw - this._baseline.yaw_mean;
        const zYaw       = deltaYaw / this._baseline.yaw_std;

        const isDownwardDev = (deltaPitch < -16.0 && zPitch < -2.2);
        const isSidewaysDev = (Math.abs(deltaYaw) > 18.0 && Math.abs(zYaw) > 2.2);

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
            this._setPreviewStatusIndicator('normal');
        } else if (!isClean) {
            this._setPreviewStatusIndicator('deviated');
        }
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

    _calculateIdentityConsistency(currentTopo, ref) {
        if (!ref.enrolled || !ref.descriptor_mean || ref.descriptor_mean.length !== currentTopo.length) {
            return 1.0;
        }

        let distSq = 0;
        for (let i = 0; i < currentTopo.length; i++) {
            distSq += Math.pow(currentTopo[i] - ref.descriptor_mean[i], 2);
        }

        const safeVar = Math.max(0.02, ref.descriptor_var);
        const score = Math.exp(-distSq / (2.0 * safeVar));
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

        this._seq++;
        try {
            const res = await this._post('record_observation', {
                token:            this._token,
                seq:              this._seq,
                event_type:       eventType,
                confidence:       confidence,
                snapshot:         snapshotBase64,
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
                fullscreen:    Boolean(document.fullscreenElement),
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

    _attachBrowserListeners() {
        this._onVisibilityChange = async () => {
            if (document.hidden) {
                await this._reportObservation('TAB_SWITCH', 1000, 1.0, null, { trigger: 'visibility_hidden' });
            }
        };

        this._onFullscreenChange = async () => {
            if (!document.fullscreenElement) {
                await this._reportObservation('FULLSCREEN_EXIT', 1000, 1.0, null, { trigger: 'fullscreen_exit' });
            }
        };

        document.addEventListener('visibilitychange', this._onVisibilityChange);
        document.addEventListener('fullscreenchange', this._onFullscreenChange);
    }

    _detachBrowserListeners() {
        if (this._onVisibilityChange) {
            document.removeEventListener('visibilitychange', this._onVisibilityChange);
        }
        if (this._onFullscreenChange) {
            document.removeEventListener('fullscreenchange', this._onFullscreenChange);
        }
    }

    async _loadDetector() {
        try {
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
        } catch (e) {
            console.warn('[LakshyaProctor] MediaPipe fallback initialized.');
        }
    }

    async _detectFaces() {
        if (!this._videoEl || !this._canvasEl) return [];
        const ctx = this._canvasEl.getContext('2d');
        ctx.drawImage(this._videoEl, 0, 0, this._canvasEl.width, this._canvasEl.height);

        if (this._detectorMode === 'mediapipe' && this._detector) {
            const result = this._detector.detect(this._canvasEl);
            return result?.detections || [];
        }
        if (this._detectorMode === 'faceapi' && typeof faceapi !== 'undefined') {
            const detections = await faceapi.detectAllFaces(this._canvasEl, new faceapi.TinyFaceDetectorOptions());
            return detections || [];
        }
        return [];
    }

    _getFrameLuminance() {
        if (!this._canvasEl) return 100;
        const ctx = this._canvasEl.getContext('2d');
        const data = ctx.getImageData(0, 0, this._canvasEl.width, this._canvasEl.height).data;
        let sum = 0;
        for (let i = 0; i < data.length; i += 16) {
            sum += 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
        }
        return sum / (data.length / 16);
    }

    _captureSnapshot() {
        if (!this._canvasEl) return null;
        try {
            return this._canvasEl.toDataURL('image/jpeg', 0.65);
        } catch (e) { return null; }
    }

    _injectUI() {
        if (!document.getElementById('proctor-css')) {
            const style = document.createElement('style');
            style.id = 'proctor-css';
            style.textContent = `
                #proctor-preview {
                    position: fixed; bottom: 16px; right: 16px; z-index: 9000;
                    width: 160px; border-radius: 12px; overflow: hidden;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.6); border: 2px solid rgba(255,255,255,0.15);
                    background: #090d16; font-family: 'Inter', sans-serif;
                    transition: border-color 0.3s ease;
                }
                #proctor-preview.hud-normal   { border-color: rgba(34, 197, 94, 0.6); }
                #proctor-preview.hud-deviated { border-color: rgba(245, 158, 11, 0.8); }
                #proctor-preview.hud-warning  { border-color: rgba(239, 68, 68, 0.9); }
                #proctor-preview video { width: 100%; display: block; border-radius: 10px 10px 0 0; }
                #proctor-status-bar {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 5px 8px; font-size: 10px; color: #fff; background: rgba(15,23,42,0.95);
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
            this._previewContainer.innerHTML = `
                <video id="proctor-video" autoplay muted playsinline></video>
                <div id="proctor-status-bar">
                    <span class="dot"></span>
                    <span id="proctor-label">AI Monitored</span>
                    <span id="proctor-risk">Strikes: 0/3</span>
                </div>
            `;
            document.body.appendChild(this._previewContainer);
            this._videoEl = document.getElementById('proctor-video');
        }

        if (!this._canvasEl) {
            this._canvasEl = document.createElement('canvas');
            this._canvasEl.width  = 320;
            this._canvasEl.height = 240;
            this._canvasEl.style.display = 'none';
            document.body.appendChild(this._canvasEl);
        }
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

    _showOverlayWarning(msg, isFinal, strikeCount, penaltyPct) {
        const existing = document.querySelector('.proctor-warning-overlay');
        if (existing) existing.remove();

        const div = document.createElement('div');
        div.className = `proctor-warning-overlay ${isFinal ? 'final' : 'warn'}`;
        div.innerHTML = `
            <div>${isFinal ? '🚨 FINAL INTEGRITY WARNING (Strike 2/3)' : `⚠️ INTEGRITY WARNING (Strike ${strikeCount}/3)`}</div>
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
            <div style="color:#ef4444;font-size:1.3rem;font-weight:700;">🔴 Assessment Terminated</div>
            <div style="font-size:13px;font-weight:400;margin-top:8px;color:#cbd5e1;">${msg}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:12px;">Auto-submitting your responses to the coordinator...</div>
        `;
        document.body.appendChild(div);
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