<?php

namespace App\Services;

use PDO;
use Exception;

class ProctoringService
{
    // Strike thresholds & penalties
    const PENALTY_STRIKE_1 = 5.0;   // 5% score deduction
    const PENALTY_STRIKE_2 = 10.0;  // 10% score deduction
    const MAX_STRIKES      = 3;     // 3rd strike = auto-termination

    // Persistence thresholds in milliseconds (anti-false-positive filtering)
    const THRESHOLD_MULTI_FACE_MS          = 2000; // 2.0s sustained multiple faces
    const THRESHOLD_NO_FACE_MS             = 5000; // 5.0s sustained face absence
    const THRESHOLD_LOOKING_AWAY_MS        = 6000; // 6.0s sustained head turn / gaze deviation
    const THRESHOLD_DOWNWARD_ATTN_MS       = 6000; // 6.0s sustained downward gaze/attention
    const THRESHOLD_SIDEWAYS_DEV_MS        = 6000; // 6.0s sustained sideways deviation
    const THRESHOLD_TAB_SWITCH_MS          = 1000; // 1.0s window switch
    const THRESHOLD_IDENTITY_MISMATCH_MS   = 5000; // 5.0s sustained identity mismatch
    const THRESHOLD_DEVICE_DETECTED_MS     = 3000; // 3.0s sustained prohibited device presence
    const THRESHOLD_EYE_GAZE_MS            = 5000; // 5.0s sustained eye gaze divergence outside screen covariance ellipse
    const THRESHOLD_FACE_OCCLUSION_MS      = 4000; // 4.0s sustained face landmark occlusion
    const THRESHOLD_HIGH_CONF_DEV_OCC_MS   = 3000; // 3.0s correlated device + face occlusion

    // Temporal continuity gap threshold (unambiguous constant)
    const TEMPORAL_CONTINUITY_GAP_MS = 4000; // 4.0s gap tolerance

    // Heartbeat timing thresholds in seconds
    const HEARTBEAT_NORMAL_WINDOW_SEC = 15;
    const HEARTBEAT_GRACE_WINDOW_SEC  = 30;
    const HEARTBEAT_RECONNECT_MAX_SEC = 120; // 120s reconnect window

    // Valid whitelist of telemetry observation/event types (Phase 1.1 -> Phase 4B)
    const ALLOWED_EVENTS = [
        'OBJECT_CANDIDATE',
        'PROHIBITED_DEVICE_DETECTED',
        'IDENTITY_SAMPLE',
        'PERSISTENT_IDENTITY_MISMATCH',
        'EYE_GAZE_SAMPLE',
        'SUSTAINED_EYE_GAZE_DIVERGENCE',
        'OCCLUSION_SAMPLE',
        'FACE_OCCLUSION',
        'HIGH_CONFIDENCE_DEVICE_OCCLUSION',
        'SUSTAINED_DOWNWARD_ATTENTION',
        'SUSTAINED_SIDEWAYS_DEVIATION',
        'MULTIPLE_PERSONS_PRESENT',
        'UNATTENDED_STATION',
        'CLEAN_FRAME',
        'NO_FACE',
        'MULTI_FACE',
        'LOOKING_AWAY',
        'TAB_SWITCH',
        'WINDOW_BLUR',
        'FULLSCREEN_EXIT',
        'DEVTOOLS_OPEN',
        'LOW_LIGHT',
        'NETWORK_INTERRUPTION',
        'CALIBRATION_ENROLLMENT'
    ];

    /**
     * Get authoritative server epoch milliseconds.
     */
    public static function getCurrentEpochMs()
    {
        return (int)round(microtime(true) * 1000);
    }

    /**
     * Two-Layer Rate Limiting (Zero DB writes on burst/DDoS).
     */
    public static function checkRateLimit($sessionToken, $ip)
    {
        $now = microtime(true);
        $tempDir = sys_get_temp_dir() . '/lakshya_ratelimit';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        // Layer 1: IP rate limit (60 requests per 10 seconds)
        $ipHash = md5('ip_' . $ip);
        $ipFile = $tempDir . '/' . $ipHash . '.json';
        $ipData = @file_get_contents($ipFile);
        $ipHits = $ipData ? json_decode($ipData, true) : [];
        $ipHits = array_filter($ipHits, fn($t) => ($now - $t) < 10.0);
        if (count($ipHits) >= 60) {
            return ['allowed' => false, 'error' => 'IP rate limit exceeded. Please wait.', 'retry_after' => 10];
        }
        $ipHits[] = $now;
        @file_put_contents($ipFile, json_encode($ipHits), LOCK_EX);

        // Layer 2: Per-session rate limit (10 requests per 2 seconds)
        if (!empty($sessionToken)) {
            $sessHash = md5('sess_' . $sessionToken);
            $sessFile = $tempDir . '/' . $sessHash . '.json';
            $sessData = @file_get_contents($sessFile);
            $sessHits = $sessData ? json_decode($sessData, true) : [];
            $sessHits = array_filter($sessHits, fn($t) => ($now - $t) < 2.0);
            if (count($sessHits) >= 10) {
                return ['allowed' => false, 'error' => 'Telemetry rate limit exceeded (session burst).', 'retry_after' => 2];
            }
            $sessHits[] = $now;
            @file_put_contents($sessFile, json_encode($sessHits), LOCK_EX);
        }

        return ['allowed' => true];
    }

    /**
     * Create a secure server-authoritative proctor session for a student.
     */
    public static function createSession($studentId, $assessmentId, $assessmentType = 'general')
    {
        $db = getDB();
        $sessionToken = bin2hex(random_bytes(32));

        $stmt = $db->prepare("
            INSERT INTO `proctor_sessions` 
            (`session_token`, `student_id`, `assessment_id`, `assessment_type`, `status`, `suspicion_score`, `warnings_issued`, `strike_count`, `penalty_pct`, `last_seq`, `last_heartbeat_at`, `created_at`)
            VALUES (?, ?, ?, ?, 'active', 0.00, 0, 0, 0.00, 0, NOW(), NOW())
        ");

        $stmt->execute([
            $sessionToken,
            (string)$studentId,
            (int)$assessmentId,
            $assessmentType
        ]);

        return [
            'success' => true,
            'token' => $sessionToken,
            'config' => self::getClientConfig($assessmentType)
        ];
    }

    /**
     * Validate session ownership, active state, and existence.
     */
    public static function validateSession($sessionToken, $studentId)
    {
        if (empty($sessionToken) || empty($studentId)) {
            return null;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM `proctor_sessions` WHERE `session_token` = ? LIMIT 1");
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session || (string)$session['student_id'] !== (string)$studentId) {
            return null;
        }

        return $session;
    }

    /**
     * Get secure proctoring configuration for client execution.
     */
    public static function getClientConfig($assessmentType = 'general')
    {
        return [
            'heartbeat_interval_ms'     => 15000,
            'monitor_interval_ms'       => 1500,
            'env_check_duration_sec'    => 8,
            'max_strikes'               => self::MAX_STRIKES,
            'penalty_strike_1'          => self::PENALTY_STRIKE_1,
            'penalty_strike_2'          => self::PENALTY_STRIKE_2,
            'continuity_gap_ms'         => self::TEMPORAL_CONTINUITY_GAP_MS,
            'multi_face_threshold_ms'   => self::THRESHOLD_MULTI_FACE_MS,
            'no_face_threshold_ms'      => self::THRESHOLD_NO_FACE_MS,
            'looking_away_threshold_ms' => self::THRESHOLD_LOOKING_AWAY_MS,
            'downward_attn_threshold_ms'=> self::THRESHOLD_DOWNWARD_ATTN_MS,
            'sideways_dev_threshold_ms' => self::THRESHOLD_SIDEWAYS_DEV_MS,
            'tab_switch_threshold_ms'   => self::THRESHOLD_TAB_SWITCH_MS,
            // Phase 4B Advanced Sensing Configurations
            'identity_mismatch_threshold_ms' => self::THRESHOLD_IDENTITY_MISMATCH_MS,
            'device_detected_threshold_ms'   => self::THRESHOLD_DEVICE_DETECTED_MS,
            'eye_gaze_threshold_ms'          => self::THRESHOLD_EYE_GAZE_MS,
            'face_occlusion_threshold_ms'    => self::THRESHOLD_FACE_OCCLUSION_MS,
            'identity_match_score'           => 0.85,
            'identity_mismatch_score'        => 0.70,
            'gaze_mahalanobis_sq_threshold'  => 9.21, // 99% coverage for 2-DOF Chi-Square
            'gaze_covariance_lambda'         => 0.0001, // Covariance regularization constant
            'device_enter_confidence'        => 0.65,
            'device_exit_confidence'         => 0.45,
            'occlusion_weighted_threshold'   => 0.40,
            'require_fullscreen'        => true,
            'require_webcam'            => true,
            'tau_smoothing_ms'          => 250,
            'min_sigma_deg'             => 2.5
        ];
    }

    /**
     * Process resilient heartbeat and handle network recovery states.
     */
    public static function processHeartbeat($sessionToken, $studentId, $seqOrData = 0, $clientData = [])
    {
        $db = getDB();
        $nowMs = self::getCurrentEpochMs();
        $nowTime = time();

        if (is_array($seqOrData)) {
            $clientData = $seqOrData;
            $seq = (int)($clientData['seq'] ?? 0);
        } else {
            $seq = (int)$seqOrData;
        }

        $db->beginTransaction();

        try {
            $stmt = $db->prepare("SELECT * FROM `proctor_sessions` WHERE `session_token` = ? FOR UPDATE");
            $stmt->execute([$sessionToken]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session || (string)$session['student_id'] !== (string)$studentId) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Invalid session or unauthorized student', 'action' => 'terminate'];
            }

            if ($session['status'] === 'terminated') {
                $db->rollBack();
                return ['success' => true, 'action' => 'terminate', 'status' => 'terminated', 'message' => 'Session terminated.'];
            }

            $lastSeq = (int)$session['last_seq'];
            if ($seq <= $lastSeq) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Duplicate or out-of-order sequence ID.'];
            }

            $lastHeartbeat = $session['last_heartbeat_at'] ? strtotime($session['last_heartbeat_at']) : $nowTime;
            $gapSec = max(0, $nowTime - $lastHeartbeat);

            $newStatus = $session['status'];
            $interruptedAt = $session['interrupted_at'];
            $reconnectDeadline = $session['reconnect_deadline_at'];

            if ($gapSec <= self::HEARTBEAT_NORMAL_WINDOW_SEC) {
                $newStatus = 'active';
                $interruptedAt = null;
                $reconnectDeadline = null;
            } elseif ($gapSec <= self::HEARTBEAT_GRACE_WINDOW_SEC) {
                $newStatus = 'grace';
            } else {
                if ($session['status'] !== 'interrupted') {
                    $newStatus = 'interrupted';
                    $interruptedAt = date('Y-m-d H:i:s', $nowTime);
                    $reconnectDeadline = date('Y-m-d H:i:s', $nowTime + self::HEARTBEAT_RECONNECT_MAX_SEC);

                    $stmtLog = $db->prepare("
                        INSERT INTO `assessment_integrity_events`
                        (`student_id`, `portfolio_id`, `session_token`, `assessment_type`, `event_type`, `sequence_id`, `duration`, `duration_ms`, `confidence`, `severity`, `metadata`, `review_status`, `is_authoritative_strike`, `created_at`)
                        VALUES (?, ?, ?, ?, 'NETWORK_INTERRUPTION', ?, ?, ?, 1.0, 'LOW', ?, 'pending', 0, NOW())
                    ");
                    $stmtLog->execute([
                        $studentId,
                        (int)$session['assessment_id'],
                        $sessionToken,
                        $session['assessment_type'],
                        $seq,
                        $gapSec,
                        $gapSec * 1000,
                        json_encode(['heartbeat_gap_sec' => $gapSec, 'reconnect_deadline' => $reconnectDeadline])
                    ]);
                } else {
                    if ($reconnectDeadline && strtotime($reconnectDeadline) >= $nowTime) {
                        $newStatus = 'active';
                        $interruptedAt = null;
                        $reconnectDeadline = null;
                    } else {
                        $newStatus = 'terminated';
                    }
                }
            }

            $stmtUpdate = $db->prepare("
                UPDATE `proctor_sessions`
                SET `last_seq` = ?,
                    `status` = ?,
                    `interrupted_at` = ?,
                    `reconnect_deadline_at` = ?,
                    `last_heartbeat_at` = NOW()
                WHERE `id` = ?
            ");
            $stmtUpdate->execute([$seq, $newStatus, $interruptedAt, $reconnectDeadline, $session['id']]);

            $db->commit();

            $action = ($newStatus === 'terminated') ? 'terminate' : 'continue';

            return [
                'success' => true,
                'status' => $newStatus,
                'action' => $action,
                'strike_count' => (int)$session['strike_count'],
                'penalty_pct' => (float)$session['penalty_pct'],
                'suspicion_score' => (float)$session['suspicion_score']
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Database error during heartbeat processing'];
        }
    }

    /**
     * Authoritatively evaluate and record a raw client observation (4-Tier Traceability).
     * Tier 1 (Observation) -> Tier 2 (Event) -> Tier 3 (Evidence) -> Tier 4 (Policy Decision).
     */
    public static function recordObservation($sessionToken, $studentId, $observation = [])
    {
        $db = getDB();

        // 1. Strict Schema & Input Validation
        $rawEvent = trim($observation['event_type'] ?? '');
        $eventType = strtoupper($rawEvent);

        if (!in_array($eventType, self::ALLOWED_EVENTS, true)) {
            return ['success' => false, 'error' => 'Invalid or unapproved event_type.'];
        }

        $seq = filter_var($observation['seq'] ?? 0, FILTER_VALIDATE_INT);
        if ($seq === false || $seq < 0) {
            return ['success' => false, 'error' => 'Invalid sequence ID.'];
        }

        $clientConfidence = $observation['model_confidence'] ?? $observation['confidence'] ?? 1.0;
        if (!is_numeric($clientConfidence) || is_nan((float)$clientConfidence) || is_infinite((float)$clientConfidence)) {
            $clientConfidence = 1.0;
        }
        $clientConfidence = max(0.0, min(1.0, (float)$clientConfidence));

        $snapshotBase64 = $observation['snapshot'] ?? null;
        $metadata = $observation['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }

        // 2. Begin Transaction & Pessimistic Row Lock on Session
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("SELECT * FROM `proctor_sessions` WHERE `session_token` = ? FOR UPDATE");
            $stmt->execute([$sessionToken]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session || (string)$session['student_id'] !== (string)$studentId) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Invalid or unauthorized proctor session', 'action' => 'terminate'];
            }

            if ($session['status'] === 'terminated') {
                $db->rollBack();
                return [
                    'success' => true,
                    'action' => 'terminate',
                    'message' => 'Assessment has already been terminated.',
                    'strike_count' => (int)$session['strike_count'],
                    'penalty_pct' => (float)$session['penalty_pct']
                ];
            }

            $lastSeq = (int)$session['last_seq'];
            if ($seq <= $lastSeq) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Sequence violation: duplicate or out-of-order telemetry rejected.'];
            }

            // 3. Server-Calculated Temporal State & Multi-Signal Cross-Correlation Engine
            $evalResult = self::evaluateCrossSignalTelemetryInternal($sessionToken, $eventType, $clientConfidence, $metadata, $db);
            $isStrike          = $evalResult['is_strike'];
            $serverElapsedMs   = $evalResult['server_elapsed_ms'];
            $suspicionDelta    = $evalResult['suspicion_delta'];
            $severity          = $evalResult['severity'];
            $reason            = $evalResult['reason'];
            $establishedEvent  = $evalResult['established_event'];
            $evidenceScore     = $evalResult['evidence_score'];
            $correlatedContext = $evalResult['correlated_context'];

            // 4. Secure Evidence Vault Storage (if strike & snapshot provided)
            $snapshotInfo = null;
            if ($isStrike && !empty($snapshotBase64)) {
                $snapshotInfo = self::saveSnapshotToVault($snapshotBase64, $sessionToken, $establishedEvent);
            }

            // 5. Immutable 4-Tier Audit Ledger Entry
            $stmtEvent = $db->prepare("
                INSERT INTO `assessment_integrity_events`
                (`student_id`, `portfolio_id`, `session_token`, `assessment_type`, `event_type`, `sequence_id`, `duration`, `duration_ms`, `confidence`, `severity`, `metadata`, `snapshot_path`, `file_size`, `sha256`, `mime_type`, `review_status`, `is_authoritative_strike`, `created_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");

            $durationSec = round($serverElapsedMs / 1000.0, 2);
            $auditMetadata = array_merge($metadata, [
                'raw_observation_type'  => $eventType,
                'established_event_type'=> $establishedEvent,
                'evidence_score'        => $evidenceScore,
                'server_elapsed_ms'     => $serverElapsedMs,
                'temporal_state'        => $evalResult['current_state'],
                'correlated_signals'    => $correlatedContext
            ]);

            $stmtEvent->execute([
                $studentId,
                (int)$session['assessment_id'],
                $sessionToken,
                $session['assessment_type'],
                $establishedEvent,
                $seq,
                $durationSec,
                $serverElapsedMs,
                $evidenceScore,
                $severity,
                json_encode($auditMetadata),
                $snapshotInfo['path'] ?? null,
                $snapshotInfo['size'] ?? null,
                $snapshotInfo['sha256'] ?? null,
                $snapshotInfo['mime'] ?? null,
                $isStrike ? 1 : 0
            ]);

            // 6. Suspicion Score & Progressive Strike Escalation
            $newSuspicion = min(100.0, (float)$session['suspicion_score'] + $suspicionDelta);
            $newStrikes   = (int)$session['strike_count'];
            $newWarnings  = (int)$session['warnings_issued'];
            $newPenalty   = (float)$session['penalty_pct'];
            $newStatus    = $session['status'];

            $action = 'continue';
            $message = '';

            if ($isStrike) {
                $newStrikes++;
                $newWarnings++;

                if ($newStrikes === 1) {
                    $newPenalty = self::PENALTY_STRIKE_1;
                    $action = 'warn';
                    $message = "Warning 1: {$reason}. A {$newPenalty}% penalty has been applied.";
                } elseif ($newStrikes === 2) {
                    $newPenalty = self::PENALTY_STRIKE_2;
                    $action = 'final_warn';
                    $message = "Final Warning: {$reason}. A {$newPenalty}% penalty has been applied. Next violation will terminate your assessment.";
                } else {
                    $newStrikes = self::MAX_STRIKES;
                    $newStatus = 'terminated';
                    $action = 'terminate';
                    $message = "Assessment auto-submitted: Maximum integrity violations (3 strikes) exceeded.";
                }
            }

            // 7. Update Session State Atomically
            $stmtUpdate = $db->prepare("
                UPDATE `proctor_sessions`
                SET `last_seq` = ?,
                    `suspicion_score` = ?,
                    `strike_count` = ?,
                    `warnings_issued` = ?,
                    `penalty_pct` = ?,
                    `status` = ?,
                    `last_heartbeat_at` = NOW()
                WHERE `id` = ?
            ");

            $stmtUpdate->execute([
                $seq,
                $newSuspicion,
                $newStrikes,
                $newWarnings,
                $newPenalty,
                $newStatus,
                $session['id']
            ]);

            $db->commit();

            return [
                'success' => true,
                'action' => $action,
                'is_strike' => $isStrike,
                'established_event' => $establishedEvent,
                'evidence_score' => $evidenceScore,
                'strike_count' => $newStrikes,
                'warnings_issued' => $newWarnings,
                'penalty_pct' => $newPenalty,
                'suspicion_score' => $newSuspicion,
                'server_elapsed_ms' => $serverElapsedMs,
                'message' => $message,
                'reason' => $reason
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Database error during observation processing: ' . $e->getMessage()];
        }
    }

    /**
     * Cross-Signal Telemetry Evaluation Engine.
     * Evaluates raw observation candidates, applies epoch-persistence, and correlates multi-source signals.
     */
    private static function evaluateCrossSignalTelemetryInternal($sessionToken, $rawEvent, $confidence, $metadata, PDO $db)
    {
        $nowMs = self::getCurrentEpochMs();

        // 1. Enrollment / Calibration
        if ($rawEvent === 'CALIBRATION_ENROLLMENT') {
            return [
                'is_strike'          => false,
                'server_elapsed_ms'  => 0,
                'suspicion_delta'    => 0.0,
                'severity'           => 'LOW',
                'current_state'      => 'ENROLLED',
                'reason'             => 'Baseline calibration established',
                'established_event'  => 'CALIBRATION_ENROLLED',
                'evidence_score'     => (float)($metadata['quality_score'] ?? 1.0),
                'correlated_context' => ['frame_count' => (int)($metadata['frame_count'] ?? 10)]
            ];
        }

        // 2. Clean Frame (Hysteresis Reset)
        if ($rawEvent === 'CLEAN_FRAME') {
            $stmtClean = $db->prepare("
                UPDATE `proctor_temporal_state`
                SET `clean_observations_count` = `clean_observations_count` + 1
                WHERE `session_token` = ?
            ");
            $stmtClean->execute([$sessionToken]);

            $stmtReset = $db->prepare("
                DELETE FROM `proctor_temporal_state`
                WHERE `session_token` = ? AND `clean_observations_count` >= 2 AND `strike_triggered` = 0
            ");
            $stmtReset->execute([$sessionToken]);

            return [
                'is_strike'          => false,
                'server_elapsed_ms'  => 0,
                'suspicion_delta'    => 0.0,
                'severity'           => 'LOW',
                'current_state'      => 'NORMAL',
                'reason'             => 'Candidate in valid framing',
                'established_event'  => 'CLEAN_FRAME',
                'evidence_score'     => 0.0,
                'correlated_context' => ['clean_pulse' => true]
            ];
        }

        // 3. Instant Policy Violations (Window blur, Tab switch, DevTools, Fullscreen)
        if (in_array($rawEvent, ['TAB_SWITCH', 'WINDOW_BLUR', 'FULLSCREEN_EXIT', 'DEVTOOLS_OPEN'], true)) {
            $severity = ($rawEvent === 'DEVTOOLS_OPEN') ? 'CRITICAL' : 'HIGH';
            $suspDelta = ($rawEvent === 'DEVTOOLS_OPEN') ? 50.0 : 30.0;
            return [
                'is_strike'          => true,
                'server_elapsed_ms'  => 1000,
                'suspicion_delta'    => $suspDelta,
                'severity'           => $severity,
                'current_state'      => 'STRIKE',
                'reason'             => "Environment focus loss ({$rawEvent})",
                'established_event'  => $rawEvent,
                'evidence_score'     => 1.0,
                'correlated_context' => ['instant_event' => true]
            ];
        }

        // 4. Cross-Signal Specific Handling
        // A. Prohibited Device / Phone Candidate
        if ($rawEvent === 'OBJECT_CANDIDATE' || $rawEvent === 'PROHIBITED_DEVICE_DETECTED') {
            $devClass   = strtolower($metadata['class'] ?? 'cell_phone');
            $devConf    = (float)($metadata['confidence'] ?? $confidence);
            $overlap    = (float)($metadata['spatial_overlap_pct'] ?? $metadata['face_overlap_pct'] ?? 0.0);
            $proximity  = (float)($metadata['spatial_proximity_score'] ?? ($overlap > 0 ? $overlap : 0.0));
            $loss       = (float)($metadata['landmark_loss_pct'] ?? 0.0);
            $stability  = (float)($metadata['bbox_stability'] ?? 0.80);
            $gazeDev    = (float)($metadata['gaze_divergence'] ?? (!empty($metadata['is_diverged']) ? 1.0 : 0.0));

            // Filter out low confidence candidates (< 0.50)
            if ($devConf < 0.50 && $rawEvent === 'OBJECT_CANDIDATE') {
                return [
                    'is_strike' => false, 'server_elapsed_ms' => 0, 'suspicion_delta' => 0.0, 'severity' => 'LOW',
                    'current_state' => 'NORMAL', 'reason' => 'Candidate filtered by confidence threshold',
                    'established_event' => 'OBJECT_FILTERED', 'evidence_score' => 0.0, 'correlated_context' => ['conf' => $devConf]
                ];
            }

            $temporal = self::updateTemporalStateRow($sessionToken, 'PROHIBITED_DEVICE', self::THRESHOLD_DEVICE_DETECTED_MS, $nowMs, $db);
            $isPersistent = $temporal['is_threshold_met'];
            $elapsedMs = $temporal['server_elapsed_ms'];

            // Composite Spatial-Landmark-Behavioral Relationship Calculation
            $spatialScore = max($overlap, $proximity);
            $persistenceRatio = min(1.0, $elapsedMs / (float)self::THRESHOLD_DEVICE_DETECTED_MS);
            $compositeEvidence = min(1.0, (0.35 * $devConf) + (0.20 * $spatialScore) + (0.20 * $loss) + (0.15 * $persistenceRatio) + (0.10 * $gazeDev));

            if ($isPersistent) {
                // Multi-signal: Direct Facial Occlusion / Spatial Interaction with Face
                // Triggered if overlap >= 0.30 OR (high proximity >= 0.70 + landmark loss >= 0.25)
                if (($overlap >= 0.30 || ($proximity >= 0.70 && $loss >= 0.25)) && $loss >= 0.30) {
                    $evScore = max(0.85, $compositeEvidence);
                    return [
                        'is_strike'          => true,
                        'server_elapsed_ms'  => $elapsedMs,
                        'suspicion_delta'    => 40.0,
                        'severity'           => 'CRITICAL',
                        'current_state'      => 'STRIKE',
                        'reason'             => 'Prohibited device detected interacting/obstructing face region',
                        'established_event'  => 'HIGH_CONFIDENCE_DEVICE_OCCLUSION',
                        'evidence_score'     => round($evScore, 2),
                        'correlated_context' => [
                            'overlap_pct'     => $overlap,
                            'proximity_score' => $proximity,
                            'loss_pct'        => $loss,
                            'dev_conf'        => $devConf,
                            'composite_score' => round($compositeEvidence, 2)
                        ]
                    ];
                }

                $evScore = max(round($devConf * 0.95, 2), round($compositeEvidence, 2));
                return [
                    'is_strike'          => true,
                    'server_elapsed_ms'  => $elapsedMs,
                    'suspicion_delta'    => 35.0,
                    'severity'           => 'HIGH',
                    'current_state'      => 'STRIKE',
                    'reason'             => 'Prohibited mobile/electronic device detected in camera frame',
                    'established_event'  => 'PROHIBITED_DEVICE_DETECTED',
                    'evidence_score'     => round($evScore, 2),
                    'correlated_context' => [
                        'class'           => $devClass,
                        'conf'            => $devConf,
                        'stability'       => $stability,
                        'composite_score' => round($compositeEvidence, 2)
                    ]
                ];
            }

            return [
                'is_strike'          => false,
                'server_elapsed_ms'  => $elapsedMs,
                'suspicion_delta'    => 5.0,
                'severity'           => 'LOW',
                'current_state'      => 'SUSPECTED',
                'reason'             => "Prohibited device candidate monitored ({$elapsedMs}ms)",
                'established_event'  => 'OBJECT_CANDIDATE_MONITORED',
                'evidence_score'     => round($devConf * 0.4, 2),
                'correlated_context' => ['class' => $devClass, 'conf' => $devConf]
            ];
        }

        // B. Identity Consistency
        if ($rawEvent === 'IDENTITY_SAMPLE' || $rawEvent === 'PERSISTENT_IDENTITY_MISMATCH') {
            $sid     = (float)($metadata['identity_score'] ?? $confidence);
            $lmQual  = (float)($metadata['landmark_quality'] ?? 0.85);

            // Mismatch candidate ($sid < 0.70 on quality frames)
            if ($sid < 0.70 && $lmQual >= 0.65) {
                $temporal = self::updateTemporalStateRow($sessionToken, 'IDENTITY_MISMATCH', self::THRESHOLD_IDENTITY_MISMATCH_MS, $nowMs, $db);
                $elapsedMs = $temporal['server_elapsed_ms'];

                if ($temporal['is_threshold_met']) {
                    $evScore = round(min(1.0, 1.0 - $sid), 2);
                    return [
                        'is_strike'          => true,
                        'server_elapsed_ms'  => $elapsedMs,
                        'suspicion_delta'    => 35.0,
                        'severity'           => 'HIGH',
                        'current_state'      => 'STRIKE',
                        'reason'             => 'Persistent facial geometry mismatch against enrolled student baseline',
                        'established_event'  => 'PERSISTENT_IDENTITY_MISMATCH',
                        'evidence_score'     => $evScore,
                        'correlated_context' => ['identity_score' => $sid, 'landmark_quality' => $lmQual]
                    ];
                }

                return [
                    'is_strike'          => false,
                    'server_elapsed_ms'  => $elapsedMs,
                    'suspicion_delta'    => 5.0,
                    'severity'           => 'LOW',
                    'current_state'      => 'SUSPECTED',
                    'reason'             => "Identity consistency mismatch suspected ({$elapsedMs}ms)",
                    'established_event'  => 'IDENTITY_MISMATCH_SUSPECTED',
                    'evidence_score'     => round(0.30 * (1.0 - $sid), 2),
                    'correlated_context' => ['identity_score' => $sid]
                ];
            }

            // Hysteresis Reset on Match
            if ($sid >= 0.85) {
                self::resetTemporalStateRow($sessionToken, 'IDENTITY_MISMATCH', $db);
                return [
                    'is_strike'          => false,
                    'server_elapsed_ms'  => 0,
                    'suspicion_delta'    => 0.0,
                    'severity'           => 'LOW',
                    'current_state'      => 'NORMAL',
                    'reason'             => 'Identity consistency verified',
                    'established_event'  => 'IDENTITY_MATCH',
                    'evidence_score'     => $sid,
                    'correlated_context' => ['identity_score' => $sid]
                ];
            }

            // Uncertain zone ($0.70 - $0.85)
            return [
                'is_strike'          => false,
                'server_elapsed_ms'  => 0,
                'suspicion_delta'    => 0.0,
                'severity'           => 'LOW',
                'current_state'      => 'NORMAL',
                'reason'             => 'Identity within acceptable tolerance',
                'established_event'  => 'IDENTITY_UNCERTAIN',
                'evidence_score'     => $sid,
                'correlated_context' => ['identity_score' => $sid]
            ];
        }

        // C. Screen-Attention Gaze Estimation (2D Covariance Ellipse Mahalanobis Distance Squared)
        if ($rawEvent === 'EYE_GAZE_SAMPLE' || $rawEvent === 'SUSTAINED_EYE_GAZE_DIVERGENCE') {
            $dmSq = (float)($metadata['mahalanobis_sq'] ?? 0.0);
            $isDiverged = ($dmSq > 9.21) || ($metadata['is_diverged'] ?? false);

            if ($isDiverged) {
                $temporal = self::updateTemporalStateRow($sessionToken, 'EYE_GAZE_DIVERGENCE', self::THRESHOLD_EYE_GAZE_MS, $nowMs, $db);
                $elapsedMs = $temporal['server_elapsed_ms'];

                if ($temporal['is_threshold_met']) {
                    $evScore = round(min(1.0, $dmSq / 25.0), 2);
                    return [
                        'is_strike'          => true,
                        'server_elapsed_ms'  => $elapsedMs,
                        'suspicion_delta'    => 20.0,
                        'severity'           => 'MEDIUM',
                        'current_state'      => 'STRIKE',
                        'reason'             => 'Sustained off-screen eye gaze divergence outside calibrated screen region',
                        'established_event'  => 'SUSTAINED_EYE_GAZE_DIVERGENCE',
                        'evidence_score'     => max(0.60, $evScore),
                        'correlated_context' => ['mahalanobis_sq' => $dmSq]
                    ];
                }

                return [
                    'is_strike'          => false,
                    'server_elapsed_ms'  => $elapsedMs,
                    'suspicion_delta'    => 0.0,
                    'severity'           => 'LOW',
                    'current_state'      => 'SUSPECTED',
                    'reason'             => "Eye gaze divergence outside screen region ({$elapsedMs}ms)",
                    'established_event'  => 'GAZE_DIVERGENCE_SUSPECTED',
                    'evidence_score'     => round(min(0.50, $dmSq / 40.0), 2),
                    'correlated_context' => ['mahalanobis_sq' => $dmSq]
                ];
            }

            self::resetTemporalStateRow($sessionToken, 'EYE_GAZE_DIVERGENCE', $db);
            return [
                'is_strike'          => false,
                'server_elapsed_ms'  => 0,
                'suspicion_delta'    => 0.0,
                'severity'           => 'LOW',
                'current_state'      => 'NORMAL',
                'reason'             => 'Eye gaze within calibrated screen region',
                'established_event'  => 'NORMAL_SCREEN_GAZE',
                'evidence_score'     => 0.0,
                'correlated_context' => ['mahalanobis_sq' => $dmSq]
            ];
        }

        // D. Occlusion & Absence Disambiguation (Geometry & Detector Behaviors)
        if ($rawEvent === 'OCCLUSION_SAMPLE' || $rawEvent === 'FACE_OCCLUSION' || $rawEvent === 'NO_FACE' || $rawEvent === 'UNATTENDED_STATION') {
            $lum        = (float)($metadata['scene_luminance'] ?? 50.0);
            $faceCount  = (int)($metadata['face_count'] ?? 0);
            $occRatio   = (float)($metadata['weighted_occlusion_ratio'] ?? $metadata['occlusion_ratio'] ?? 0.0);
            $camStable  = (bool)($metadata['camera_stable'] ?? true);

            // 1. Low quality / dark room (Non-punitive)
            if ($lum < 20.0) {
                return [
                    'is_strike'          => false,
                    'server_elapsed_ms'  => 0,
                    'suspicion_delta'    => 0.0,
                    'severity'           => 'LOW',
                    'current_state'      => 'LOW_QUALITY',
                    'reason'             => 'Low illumination / degraded lighting in room',
                    'established_event'  => 'LOW_LIGHT',
                    'evidence_score'     => 0.10,
                    'correlated_context' => ['scene_luminance' => $lum]
                ];
            }

            // 2. Unattended Station (No face + adequate illumination + stable camera)
            if ($faceCount === 0 && $lum >= 25.0) {
                $temporal = self::updateTemporalStateRow($sessionToken, 'UNATTENDED_STATION', self::THRESHOLD_NO_FACE_MS, $nowMs, $db);
                $elapsedMs = $temporal['server_elapsed_ms'];

                if ($temporal['is_threshold_met']) {
                    return [
                        'is_strike'          => true,
                        'server_elapsed_ms'  => $elapsedMs,
                        'suspicion_delta'    => 30.0,
                        'severity'           => 'HIGH',
                        'current_state'      => 'STRIKE',
                        'reason'             => 'Candidate station unattended for extended duration',
                        'established_event'  => 'UNATTENDED_STATION',
                        'evidence_score'     => 0.90,
                        'correlated_context' => ['scene_luminance' => $lum, 'camera_stable' => $camStable]
                    ];
                }

                return [
                    'is_strike'          => false,
                    'server_elapsed_ms'  => $elapsedMs,
                    'suspicion_delta'    => 0.0,
                    'severity'           => 'LOW',
                    'current_state'      => 'SUSPECTED',
                    'reason'             => "Candidate face absence monitored ({$elapsedMs}ms)",
                    'established_event'  => 'FACE_ABSENCE_SUSPECTED',
                    'evidence_score'     => 0.30,
                    'correlated_context' => ['scene_luminance' => $lum]
                ];
            }

            // 3. Face Occlusion (Significant landmark cluster degradation)
            if ($occRatio >= 0.40) {
                $temporal = self::updateTemporalStateRow($sessionToken, 'FACE_OCCLUSION', self::THRESHOLD_FACE_OCCLUSION_MS, $nowMs, $db);
                $elapsedMs = $temporal['server_elapsed_ms'];

                if ($temporal['is_threshold_met']) {
                    return [
                        'is_strike'          => true,
                        'server_elapsed_ms'  => $elapsedMs,
                        'suspicion_delta'    => 25.0,
                        'severity'           => 'MEDIUM',
                        'current_state'      => 'STRIKE',
                        'reason'             => 'Significant facial landmark occlusion sustained for extended duration',
                        'established_event'  => 'FACE_OCCLUSION',
                        'evidence_score'     => round($occRatio, 2),
                        'correlated_context' => ['weighted_occlusion_ratio' => $occRatio]
                    ];
                }

                return [
                    'is_strike'          => false,
                    'server_elapsed_ms'  => $elapsedMs,
                    'suspicion_delta'    => 0.0,
                    'severity'           => 'LOW',
                    'current_state'      => 'SUSPECTED',
                    'reason'             => "Partial facial occlusion monitored ({$elapsedMs}ms)",
                    'established_event'  => 'OCCLUSION_SUSPECTED',
                    'evidence_score'     => round($occRatio * 0.5, 2),
                    'correlated_context' => ['weighted_occlusion_ratio' => $occRatio]
                ];
            }

            self::resetTemporalStateRow($sessionToken, 'FACE_OCCLUSION', $db);
            self::resetTemporalStateRow($sessionToken, 'UNATTENDED_STATION', $db);
            return [
                'is_strike'          => false,
                'server_elapsed_ms'  => 0,
                'suspicion_delta'    => 0.0,
                'severity'           => 'LOW',
                'current_state'      => 'NORMAL',
                'reason'             => 'Face and scene metrics normal',
                'established_event'  => 'CLEAN_FRAME',
                'evidence_score'     => 0.0,
                'correlated_context' => []
            ];
        }

        // 5. Fallback Generic Continuous CV Evaluation (Legacy / Head Pose)
        return self::evaluateLegacyTemporalStateInternal($sessionToken, $rawEvent, $nowMs, $db);
    }

    /**
     * Helper: Update temporal state row in DB and return elapsed milliseconds + threshold check.
     */
    private static function updateTemporalStateRow($sessionToken, $eventType, $thresholdMs, $nowMs, PDO $db)
    {
        $stmt = $db->prepare("SELECT * FROM `proctor_temporal_state` WHERE `session_token` = ? AND `event_type` = ? LIMIT 1");
        $stmt->execute([$sessionToken, $eventType]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$state) {
            $stmtIns = $db->prepare("
                INSERT INTO `proctor_temporal_state`
                (`session_token`, `event_type`, `state`, `first_observed_ms`, `last_observed_ms`, `observation_count`, `clean_observations_count`, `strike_triggered`)
                VALUES (?, ?, 'SUSPECTED', ?, ?, 1, 0, 0)
            ");
            $stmtIns->execute([$sessionToken, $eventType, $nowMs, $nowMs]);
            return ['is_threshold_met' => false, 'server_elapsed_ms' => 0];
        }

        $gapMs = $nowMs - (int)$state['last_observed_ms'];
        if ($gapMs > self::TEMPORAL_CONTINUITY_GAP_MS) {
            $stmtUpdate = $db->prepare("
                UPDATE `proctor_temporal_state`
                SET `state` = 'SUSPECTED',
                    `first_observed_ms` = ?,
                    `last_observed_ms` = ?,
                    `observation_count` = 1,
                    `clean_observations_count` = 0,
                    `strike_triggered` = 0
                WHERE `session_token` = ? AND `event_type` = ?
            ");
            $stmtUpdate->execute([$nowMs, $nowMs, $sessionToken, $eventType]);
            return ['is_threshold_met' => false, 'server_elapsed_ms' => 0];
        }

        $firstObservedMs = (int)$state['first_observed_ms'];
        $serverElapsedMs = max(0, $nowMs - $firstObservedMs);
        $newCount = (int)$state['observation_count'] + 1;
        $strikeTriggered = (int)$state['strike_triggered'];

        $isThresholdMet = false;
        $newState = 'PERSISTENT';

        if ($serverElapsedMs >= $thresholdMs && !$strikeTriggered) {
            $isThresholdMet = true;
            $strikeTriggered = 1;
            $newState = 'STRIKE';
        }

        $stmtUpdate = $db->prepare("
            UPDATE `proctor_temporal_state`
            SET `state` = ?,
                `last_observed_ms` = ?,
                `observation_count` = ?,
                `clean_observations_count` = 0,
                `strike_triggered` = ?
            WHERE `session_token` = ? AND `event_type` = ?
        ");
        $stmtUpdate->execute([$newState, $nowMs, $newCount, $strikeTriggered, $sessionToken, $eventType]);

        return ['is_threshold_met' => $isThresholdMet, 'server_elapsed_ms' => $serverElapsedMs];
    }

    /**
     * Helper: Reset temporal state row in DB.
     */
    private static function resetTemporalStateRow($sessionToken, $eventType, PDO $db)
    {
        $stmt = $db->prepare("
            DELETE FROM `proctor_temporal_state`
            WHERE `session_token` = ? AND `event_type` = ? AND `strike_triggered` = 0
        ");
        $stmt->execute([$sessionToken, $eventType]);
    }

    /**
     * Legacy Temporal State Evaluator for Phase 1.1 / Phase 2 Head Pose Events.
     */
    private static function evaluateLegacyTemporalStateInternal($sessionToken, $eventType, $nowMs, PDO $db)
    {
        $thresholdMs = match ($eventType) {
            'MULTI_FACE', 'MULTIPLE_PERSONS_PRESENT' => self::THRESHOLD_MULTI_FACE_MS,
            'NO_FACE', 'UNATTENDED_STATION'          => self::THRESHOLD_NO_FACE_MS,
            'SUSTAINED_DOWNWARD_ATTENTION'           => self::THRESHOLD_DOWNWARD_ATTN_MS,
            'SUSTAINED_SIDEWAYS_DEVIATION', 'LOOKING_AWAY' => self::THRESHOLD_SIDEWAYS_DEV_MS,
            default => 5000
        };

        $temporal = self::updateTemporalStateRow($sessionToken, $eventType, $thresholdMs, $nowMs, $db);
        $isStrike = $temporal['is_threshold_met'];
        $serverElapsedMs = $temporal['server_elapsed_ms'];

        $severity = match ($eventType) {
            'MULTI_FACE', 'MULTIPLE_PERSONS_PRESENT', 'NO_FACE', 'UNATTENDED_STATION' => 'HIGH',
            'SUSTAINED_DOWNWARD_ATTENTION', 'SUSTAINED_SIDEWAYS_DEVIATION', 'LOOKING_AWAY' => 'MEDIUM',
            default => 'LOW'
        };

        $suspDelta = match ($eventType) {
            'MULTIPLE_PERSONS_PRESENT', 'MULTI_FACE' => ($isStrike ? 35.0 : 5.0),
            'UNATTENDED_STATION', 'NO_FACE'          => ($isStrike ? 30.0 : 0.0),
            'SUSTAINED_DOWNWARD_ATTENTION'           => ($isStrike ? 25.0 : 0.0),
            'SUSTAINED_SIDEWAYS_DEVIATION', 'LOOKING_AWAY' => ($isStrike ? 20.0 : 0.0),
            default => 0.0
        };

        $reason = match ($eventType) {
            'MULTIPLE_PERSONS_PRESENT', 'MULTI_FACE' => 'Multiple individuals present in camera frame',
            'UNATTENDED_STATION', 'NO_FACE'          => 'Candidate station unattended for extended duration',
            'SUSTAINED_DOWNWARD_ATTENTION'           => 'Sustained downward gaze/head orientation detected',
            'SUSTAINED_SIDEWAYS_DEVIATION', 'LOOKING_AWAY' => 'Extended sideways head turn detected',
            default => 'Proctoring signal accumulated'
        };

        return [
            'is_strike'          => $isStrike,
            'server_elapsed_ms'  => $serverElapsedMs,
            'suspicion_delta'    => $suspDelta,
            'severity'           => $severity,
            'current_state'      => $isStrike ? 'STRIKE' : ($serverElapsedMs > 0 ? 'PERSISTENT' : 'SUSPECTED'),
            'reason'             => $reason,
            'established_event'  => $eventType,
            'evidence_score'     => $isStrike ? 0.90 : 0.40,
            'correlated_context' => []
        ];
    }

    /**
     * Save snapshot base64 securely to the isolated evidence vault.
     */
    private static function saveSnapshotToVault($base64Data, $sessionToken, $eventType)
    {
        try {
            $vaultDir = __DIR__ . '/../../storage/proctor_vault/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionToken);
            if (!is_dir($vaultDir)) {
                @mkdir($vaultDir, 0750, true);
            }

            $denyFile = __DIR__ . '/../../storage/proctor_vault/.htaccess';
            if (!file_exists($denyFile)) {
                @file_put_contents($denyFile, "Deny from all\n");
            }

            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-]+);base64,(.+)$/', $base64Data, $matches)) {
                $mimeType = $matches[1];
                $data = base64_decode($matches[2]);
            } else {
                $mimeType = 'image/jpeg';
                $data = base64_decode($base64Data);
            }

            if (!$data) return null;

            if (strlen($data) > 1024 * 1024) {
                return null;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actualMime = finfo_buffer($finfo, $data);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($actualMime, $allowedMimes, true)) {
                return null;
            }

            $ext = match ($actualMime) {
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default      => 'jpg'
            };

            $epochMs = self::getCurrentEpochMs();
            $safeType = preg_replace('/[^a-zA-Z0-9_-]/', '', $eventType);
            $filename = "ev_{$safeType}_{$epochMs}_" . bin2hex(random_bytes(4)) . ".{$ext}";
            $fullPath = $vaultDir . '/' . $filename;

            @file_put_contents($fullPath, $data);

            return [
                'path' => 'storage/proctor_vault/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionToken) . '/' . $filename,
                'size' => strlen($data),
                'sha256' => hash('sha256', $data),
                'mime' => $actualMime
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Mark proctor session as finished/completed.
     */
    public static function endSession($sessionToken, $studentId)
    {
        $session = self::validateSession($sessionToken, $studentId);
        if (!$session) {
            return ['success' => false, 'error' => 'Invalid session'];
        }

        $db = getDB();
        if ($session['status'] === 'active' || $session['status'] === 'grace') {
            $stmt = $db->prepare("UPDATE `proctor_sessions` SET `status` = 'completed' WHERE `id` = ?");
            $stmt->execute([$session['id']]);
        }

        return [
            'success' => true,
            'status' => in_array($session['status'], ['active', 'grace'], true) ? 'completed' : $session['status'],
            'strike_count' => (int)$session['strike_count'],
            'warnings_issued' => (int)$session['warnings_issued'],
            'penalty_pct' => (float)$session['penalty_pct'],
            'suspicion_score' => (float)$session['suspicion_score']
        ];
    }
}