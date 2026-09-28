<?php
/**
 * Global Admin - AI Proctoring & Integrity Audit Vault
 * Authoritative viewer for all proctoring sessions, AI telemetry, and violation evidence.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(ROLE_ADMIN);

$db = getDB();
$fullName = getFullName();

// Session feedback alerts
$successMsg = Session::flash('success') ?: '';
$errorMsg   = Session::flash('error') ?: '';

// Handle POST actions (e.g. Review status updates)
if (isPost()) {
    $action = post('action');
    $csrfToken = post('csrf_token');

    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        Session::flash('error', 'Security token mismatch. Please try again.');
        redirect('proctoring.php');
        exit;
    }

    if ($action === 'update_review_status') {
        $eventId = (int)post('event_id');
        $newStatus = trim((string)post('review_status'));

        if ($eventId > 0 && in_array($newStatus, ['pending', 'verified_violation', 'dismissed'], true)) {
            try {
                $stmt = $db->prepare("UPDATE assessment_integrity_events SET review_status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $eventId]);
                Session::flash('success', "Event #{$eventId} review status updated to '" . str_replace('_', ' ', $newStatus) . "'.");
            } catch (Exception $e) {
                Session::flash('error', 'Failed to update review status: ' . $e->getMessage());
            }
        }
        redirect('proctoring.php?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'batch_update_review_status') {
        $eventIds = post('event_ids');
        $newStatus = trim((string)post('batch_status'));

        if (is_array($eventIds) && !empty($eventIds) && in_array($newStatus, ['pending', 'verified_violation', 'dismissed'], true)) {
            $sanitizedIds = array_filter(array_map('intval', $eventIds), fn($id) => $id > 0);
            if (!empty($sanitizedIds)) {
                $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));
                $params = array_merge([$newStatus], $sanitizedIds);
                try {
                    $stmt = $db->prepare("UPDATE assessment_integrity_events SET review_status = ? WHERE id IN ($placeholders)");
                    $stmt->execute($params);
                    $count = count($sanitizedIds);
                    Session::flash('success', "Successfully batch-updated {$count} integrity event(s) to '" . str_replace('_', ' ', $newStatus) . "'.");
                } catch (Exception $e) {
                    Session::flash('error', 'Batch update failed: ' . $e->getMessage());
                }
            }
        }
        redirect('proctoring.php?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'auto_classify_pending') {
        try {
            // High confidence violations -> verified_violation
            $stmt1 = $db->query("
                UPDATE assessment_integrity_events 
                SET review_status = 'verified_violation' 
                WHERE review_status = 'pending' 
                AND (is_authoritative_strike = 1 OR (confidence >= 0.85 AND severity = 'HIGH'))
            ");
            $verifiedCount = $stmt1->rowCount();

            // Low-severity transient events -> dismissed
            $stmt2 = $db->query("
                UPDATE assessment_integrity_events 
                SET review_status = 'dismissed' 
                WHERE review_status = 'pending' 
                AND severity = 'LOW' 
                AND (duration < 2.0 OR duration IS NULL)
            ");
            $dismissedCount = $stmt2->rowCount();

            Session::flash('success', "Auto-reconciled existing events: {$verifiedCount} marked as Verified Violations, {$dismissedCount} dismissed as Low-Risk False Positives.");
        } catch (Exception $e) {
            Session::flash('error', 'Auto-classification failed: ' . $e->getMessage());
        }
        redirect('proctoring.php?' . http_build_query($_GET));
        exit;
    }


    if ($action === 'export_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="proctoring_integrity_events_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Event ID', 'Student USN', 'Assessment Type', 'Event Type', 'Severity', 'Confidence (%)', 'Duration (s)', 'Review Status', 'Created At', 'Metadata / Reason']);

        $stmt = $db->query("SELECT id, student_id, assessment_type, event_type, severity, confidence, duration, review_status, created_at, metadata FROM assessment_integrity_events ORDER BY id DESC LIMIT 5000");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metaReason = '';
            if (!empty($row['metadata'])) {
                $metaArr = json_decode($row['metadata'], true);
                $metaReason = $metaArr['reason'] ?? $metaArr['note'] ?? $row['metadata'];
            }
            fputcsv($output, [
                $row['id'],
                $row['student_id'],
                $row['assessment_type'],
                $row['event_type'],
                $row['severity'],
                round(($row['confidence'] ?? 1) * 100, 1),
                $row['duration'] ?? 0,
                $row['review_status'],
                $row['created_at'],
                $metaReason
            ]);
        }
        fclose($output);
        exit;
    }
}

// ── GET Parameters & Filters ────────────────────────────────────────────────
$activeTab      = trim($_GET['tab'] ?? 'events');
$search         = trim($_GET['q'] ?? '');
$assessmentType = trim($_GET['assessment_type'] ?? '');
$eventType      = trim($_GET['event_type'] ?? '');
$severityFilter = trim($_GET['severity'] ?? '');
$reviewFilter   = trim($_GET['review_status'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$limit          = 25;
$offset         = ($page - 1) * $limit;

// ── Calculate Executive Statistics ───────────────────────────────────────────
$stats = [
    'total_sessions'    => 0,
    'active_sessions'   => 0,
    'total_events'      => 0,
    'high_severity'     => 0,
    'verified_violations'=> 0,
    'has_snapshots'     => 0,
];

try {
    $stats['total_sessions']     = (int)$db->query("SELECT COUNT(*) FROM proctor_sessions")->fetchColumn();
    $stats['active_sessions']    = (int)$db->query("SELECT COUNT(*) FROM proctor_sessions WHERE status = 'active'")->fetchColumn();
    $stats['total_events']       = (int)$db->query("SELECT COUNT(*) FROM assessment_integrity_events")->fetchColumn();
    $stats['high_severity']      = (int)$db->query("SELECT COUNT(*) FROM assessment_integrity_events WHERE severity = 'HIGH'")->fetchColumn();
    $stats['verified_violations']= (int)$db->query("SELECT COUNT(*) FROM assessment_integrity_events WHERE review_status = 'verified_violation'")->fetchColumn();
    $stats['has_snapshots']      = (int)$db->query("SELECT COUNT(*) FROM assessment_integrity_events WHERE snapshot_path IS NOT NULL AND snapshot_path != ''")->fetchColumn();
} catch (Exception $e) {
    error_log("Proctor stats query error: " . $e->getMessage());
}

// ── Fetch Unique Assessment & Event Types for Dropdowns ──────────────────────
$assessmentTypes = [];
$eventTypes      = [];
try {
    $assessmentTypes = $db->query("SELECT DISTINCT assessment_type FROM assessment_integrity_events WHERE assessment_type != '' ORDER BY assessment_type ASC")->fetchAll(PDO::FETCH_COLUMN);
    $eventTypes      = $db->query("SELECT DISTINCT event_type FROM assessment_integrity_events WHERE event_type != '' ORDER BY event_type ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// ── Query Data Based on Active Tab ──────────────────────────────────────────
$events      = [];
$sessions    = [];
$totalRows   = 0;
$totalPages  = 1;

// ── Query Data Based on Active Tab ──────────────────────────────────────────
$events      = [];
$sessions    = [];
$totalRows   = 0;
$totalPages  = 1;
$studentMap  = [];

if ($activeTab === 'sessions') {
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "ps.student_id LIKE ?";
        $params[] = "%{$search}%";
    }
    if ($assessmentType !== '') {
        $where[] = "ps.assessment_type = ?";
        $params[] = $assessmentType;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    try {
        $countSql = "SELECT COUNT(*) FROM proctor_sessions ps {$whereSql}";
        $stmtC = $db->prepare($countSql);
        $stmtC->execute($params);
        $totalRows = (int)$stmtC->fetchColumn();
        $totalPages = max(1, ceil($totalRows / $limit));

        $dataSql = "SELECT ps.* FROM proctor_sessions ps {$whereSql} ORDER BY ps.id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmtD = $db->prepare($dataSql);
        $stmtD->execute($params);
        $sessions = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching proctor sessions: " . $e->getMessage());
    }

    $studentIds = array_unique(array_filter(array_column($sessions, 'student_id')));
} else {
    // Active Tab = 'events'
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "e.student_id LIKE ?";
        $params[] = "%{$search}%";
    }
    if ($assessmentType !== '') {
        $where[] = "e.assessment_type = ?";
        $params[] = $assessmentType;
    }
    if ($eventType !== '') {
        $where[] = "e.event_type = ?";
        $params[] = $eventType;
    }
    if ($severityFilter !== '') {
        $where[] = "e.severity = ?";
        $params[] = $severityFilter;
    }
    if ($reviewFilter !== '') {
        $where[] = "e.review_status = ?";
        $params[] = $reviewFilter;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $countSql = "SELECT COUNT(*) FROM assessment_integrity_events e {$whereSql}";
        $stmtC = $db->prepare($countSql);
        $stmtC->execute($params);
        $totalRows = (int)$stmtC->fetchColumn();
        $totalPages = max(1, ceil($totalRows / $limit));

        $dataSql = "SELECT e.* FROM assessment_integrity_events e {$whereSql} ORDER BY e.id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmtD = $db->prepare($dataSql);
        $stmtD->execute($params);
        $events = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching integrity events: " . $e->getMessage());
    }

    $studentIds = array_unique(array_filter(array_column($events, 'student_id')));
}

// ── Multi-Source In-Memory Student Pre-fetch Map ─────────────────────────────
if (!empty($studentIds)) {
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $idValues = array_values($studentIds);

    // Source 1: users table
    try {
        $stmtU = $db->prepare("SELECT USER_NAME as usn, NAME as full_name, COLLEGE as institution FROM users WHERE USER_NAME IN ($placeholders)");
        $stmtU->execute($idValues);
        while ($u = $stmtU->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($u['full_name'])) {
                $studentMap[$u['usn']] = [
                    'name' => $u['full_name'],
                    'institution' => $u['institution'] ?? 'GMU'
                ];
            }
        }
    } catch (Exception $e) {}

    // Source 2: student_profiles table (for missing)
    $unresolved1 = array_diff($studentIds, array_keys($studentMap));
    if (!empty($unresolved1)) {
        $ph1 = implode(',', array_fill(0, count($unresolved1), '?'));
        try {
            $stmtP = $db->prepare("SELECT usn, name, department FROM student_profiles WHERE usn IN ($ph1)");
            $stmtP->execute(array_values($unresolved1));
            while ($p = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($p['name'])) {
                    $studentMap[$p['usn']] = [
                        'name' => $p['name'],
                        'institution' => $p['department'] ?? 'GMU'
                    ];
                }
            }
        } catch (Exception $e) {}
    }

    // Source 3: unified_ai_assessments table (for missing)
    $unresolved2 = array_diff($studentIds, array_keys($studentMap));
    if (!empty($unresolved2)) {
        $ph2 = implode(',', array_fill(0, count($unresolved2), '?'));
        try {
            $stmtA = $db->prepare("SELECT student_id as usn, student_name as name, institution FROM unified_ai_assessments WHERE student_id IN ($ph2) AND student_name != ''");
            $stmtA->execute(array_values($unresolved2));
            while ($a = $stmtA->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($a['name']) && empty($studentMap[$a['usn']])) {
                    $studentMap[$a['usn']] = [
                        'name' => $a['name'],
                        'institution' => $a['institution'] ?? 'GMU'
                    ];
                }
            }
        } catch (Exception $e) {}
    }
}

// Helper to format event types nicely
function formatEventType(string $type): array {
    $map = [
        // Face Absence
        'NO_FACE'                        => ['icon' => 'fa-user-slash',           'label' => 'No Face Detected',          'color' => '#dc2626', 'bg' => '#fef2f2'],
        'UNATTENDED_STATION'             => ['icon' => 'fa-user-clock',           'label' => 'Unattended Station',         'color' => '#dc2626', 'bg' => '#fef2f2'],
        // Multiple Persons
        'MULTI_FACE'                     => ['icon' => 'fa-users',                'label' => 'Multiple Persons',           'color' => '#ea580c', 'bg' => '#fff7ed'],
        'MULTIPLE_PERSONS_PRESENT'       => ['icon' => 'fa-users',                'label' => 'Multiple Persons',           'color' => '#ea580c', 'bg' => '#fff7ed'],
        // Gaze Deviation
        'LOOKING_AWAY'                   => ['icon' => 'fa-eye-slash',            'label' => 'Gaze Deviation',             'color' => '#d97706', 'bg' => '#fffbeb'],
        'EYE_GAZE_SAMPLE'               => ['icon' => 'fa-eye-slash',            'label' => 'Gaze Deviation',             'color' => '#d97706', 'bg' => '#fffbeb'],
        'SUSTAINED_EYE_GAZE_DIVERGENCE'  => ['icon' => 'fa-eye',                 'label' => 'Sustained Gaze Divergence',  'color' => '#d97706', 'bg' => '#fffbeb'],
        'SUSTAINED_DOWNWARD_ATTENTION'   => ['icon' => 'fa-eye-slash',            'label' => 'Downward Gaze Deviation',   'color' => '#d97706', 'bg' => '#fffbeb'],
        'SUSTAINED_SIDEWAYS_DEVIATION'   => ['icon' => 'fa-eye-slash',            'label' => 'Sideways Head Deviation',   'color' => '#d97706', 'bg' => '#fffbeb'],
        // Tab / Focus Loss
        'TAB_SWITCH'                     => ['icon' => 'fa-window-restore',       'label' => 'Tab Switch',                 'color' => '#2563eb', 'bg' => '#eff6ff'],
        'WINDOW_BLUR'                    => ['icon' => 'fa-window-minimize',      'label' => 'Window Focus Lost',          'color' => '#2563eb', 'bg' => '#eff6ff'],
        // Fullscreen
        'FULLSCREEN_EXIT'                => ['icon' => 'fa-compress',             'label' => 'Fullscreen Exit',            'color' => '#9333ea', 'bg' => '#faf5ff'],
        // Phone / Device
        'PROHIBITED_DEVICE_DETECTED'     => ['icon' => 'fa-mobile-screen-button', 'label' => 'Phone / Prohibited Device', 'color' => '#b91c1c', 'bg' => '#fef2f2'],
        'HIGH_CONFIDENCE_DEVICE_OCCLUSION'=> ['icon'=> 'fa-mobile-screen-button', 'label' => 'Device Blocking Face',      'color' => '#b91c1c', 'bg' => '#fef2f2'],
        // Face Occlusion
        'FACE_OCCLUSION'                 => ['icon' => 'fa-user-ninja',           'label' => 'Face Occluded / Covered',   'color' => '#c026d3', 'bg' => '#fdf4ff'],
        'OCCLUSION_SAMPLE'               => ['icon' => 'fa-user-ninja',           'label' => 'Face Partial Occlusion',    'color' => '#c026d3', 'bg' => '#fdf4ff'],
        // Identity
        'PERSISTENT_IDENTITY_MISMATCH'   => ['icon' => 'fa-user-secret',          'label' => 'Identity Mismatch',         'color' => '#7c3aed', 'bg' => '#f5f3ff'],
        'IDENTITY_SAMPLE'                => ['icon' => 'fa-user-secret',          'label' => 'Identity Check',             'color' => '#7c3aed', 'bg' => '#f5f3ff'],
        // DevTools
        'DEVTOOLS_OPEN'                  => ['icon' => 'fa-terminal',             'label' => 'DevTools Opened',            'color' => '#7c2d12', 'bg' => '#fef3c7'],
        // Clean / Normal
        'CLEAN_FRAME'                    => ['icon' => 'fa-check-circle',         'label' => 'Clean Frame',                'color' => '#16a34a', 'bg' => '#f0fdf4'],
        // Misc
        'LOW_LIGHT'                      => ['icon' => 'fa-lightbulb',            'label' => 'Low Lighting',               'color' => '#854d0e', 'bg' => '#fefce8'],
        'NETWORK_INTERRUPTION'           => ['icon' => 'fa-wifi',                 'label' => 'Network Interruption',       'color' => '#475569', 'bg' => '#f1f5f9'],
    ];
    return $map[$type] ?? ['icon' => 'fa-exclamation-triangle', 'label' => str_replace('_', ' ', $type), 'color' => '#475569', 'bg' => '#f1f5f9'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/img/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Proctoring & Integrity Audit - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800000;
            --primary-dark: #5b1f1f;
            --bg-color: #f4f7fe;
            --white: #ffffff;
            --text-dark: #2b3674;
            --text-muted: #a3aed1;
            --border-color: #e2e8f0;
            --shadow: 0 10px 25px rgba(0,0,0,0.03);
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-color); color: var(--text-dark); min-height: 100vh; }
        
        .main-content { flex: 1; padding: 30px; width: 100%; max-width: 1600px; margin: 0 auto; }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--white);
            padding: 24px 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title { display: flex; align-items: center; gap: 14px; }
        .header-title i { font-size: 28px; color: var(--primary-maroon); background: #fef2f2; padding: 12px; border-radius: 12px; }
        .header-title h1 { font-size: 24px; font-weight: 800; color: var(--text-dark); }
        .header-title p { font-size: 13px; color: #64748b; margin-top: 2px; }

        .header-actions { display: flex; gap: 12px; }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary-maroon); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid var(--border-color); }
        .btn-secondary:hover { background: #e2e8f0; }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .kpi-card {
            background: var(--white);
            padding: 22px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid rgba(0,0,0,0.03);
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-2px); }
        .kpi-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .kpi-info h3 { font-size: 26px; font-weight: 800; color: var(--text-dark); line-height: 1.1; }
        .kpi-info p { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Tabs & Filter Card */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 25px;
        }

        .tab-bar {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 20px;
            padding-bottom: 2px;
        }
        .tab-item {
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            margin-bottom: -4px;
        }
        .tab-item.active {
            color: var(--primary-maroon);
            border-bottom-color: var(--primary-maroon);
        }
        .tab-item:hover:not(.active) { color: var(--text-dark); }
        .tab-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .tab-item.active .tab-badge { background: #fee2e2; color: var(--primary-maroon); }

        /* Filter Form */
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            align-items: end;
        }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            font-family: inherit;
            font-size: 13px;
            background: #f8fafc;
            color: var(--text-dark);
            outline: none;
            transition: border 0.2s;
        }
        .form-control:focus { border-color: var(--primary-maroon); background: #fff; }

        /* Modern Data Table */
        .table-responsive { overflow-x: auto; margin-top: 10px; }
        .modern-table { width: 100%; border-collapse: collapse; text-align: left; }
        .modern-table th {
            padding: 14px 16px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
        }
        .modern-table td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .modern-table tr:hover td { background: #fafafa; }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-high { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .badge-med  { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .badge-low  { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        
        .badge-status-pending { background: #f1f5f9; color: #475569; }
        .badge-status-verified { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .badge-status-dismissed { background: #f8fafc; color: #94a3b8; text-decoration: line-through; }

        .usn-pill {
            font-family: 'Cascadia Code', monospace;
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 6px;
        }

        /* Snapshot Button */
        .btn-snapshot {
            background: #0284c7;
            color: #fff;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-snapshot:hover { background: #0369a1; }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 20px;
            max-width: 650px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalPop 0.2s ease-out;
        }
        @keyframes modalPop { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header {
            padding: 20px 24px;
            background: #0f172a;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; }
        .modal-close:hover { color: #fff; }

        .modal-body { padding: 24px; text-align: center; }
        .evidence-img {
            max-width: 100%;
            max-height: 380px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            object-fit: contain;
            background: #000;
        }
        .modal-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 18px;
            text-align: left;
            background: #f8fafc;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 12.5px;
        }

        /* Alert Toast */
        .alert-toast {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Pagination */
        .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #f1f5f9; }
        .page-link { padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color); color: #475569; font-size: 13px; font-weight: 600; text-decoration: none; }
        .page-link:hover { background: #f8fafc; }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/includes/navbar.php'; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-title">
            <i class="fas fa-shield-halved"></i>
            <div>
                <h1>AI Proctoring & Integrity Audit</h1>
                <p>Server-Authoritative monitoring telemetry, violation evidence, and strike records across all assessments.</p>
            </div>
        </div>
        <div class="header-actions" style="display:flex; gap:10px;">
            <form method="POST" style="display:inline;" onsubmit="return confirm('Auto-reconcile all pending integrity events? High-confidence violations will be marked Verified, and low-risk transient anomalies will be Dismissed.');">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="auto_classify_pending">
                <button type="submit" class="btn-action" style="background:#0284c7; color:#fff; border:1px solid #0369a1;">
                    <i class="fas fa-wand-magic-sparkles"></i> Auto-Reconcile Existing Events
                </button>
            </form>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="btn-action btn-secondary">
                    <i class="fas fa-download"></i> Export CSV Report
                </button>
            </form>
        </div>

    </div>

    <!-- Alert Messages -->
    <?php if ($successMsg): ?>
        <div class="alert-toast alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert-toast alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#eff6ff; color:#2563eb;">
                <i class="fas fa-laptop-code"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['total_sessions']); ?></h3>
                <p>Total Sessions</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f0fdf4; color:#16a34a;">
                <i class="fas fa-signal"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['active_sessions']); ?></h3>
                <p>Active Live Sessions</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fffbeb; color:#d97706;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['total_events']); ?></h3>
                <p>Integrity Events</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef2f2; color:#dc2626;">
                <i class="fas fa-biohazard"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['high_severity']); ?></h3>
                <p>High Severity Flagged</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f0fdf4; color:#059669;">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['verified_violations']); ?></h3>
                <p>Verified Violations</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fdf4ff; color:#c026d3;">
                <i class="fas fa-camera"></i>
            </div>
            <div class="kpi-info">
                <h3><?php echo number_format($stats['has_snapshots']); ?></h3>
                <p>Evidence Snapshots</p>
            </div>
        </div>
    </div>

    <!-- Filter & Tab Container -->
    <div class="card">
        <!-- Navigation Tabs -->
        <div class="tab-bar">
            <a href="?tab=events" class="tab-item <?php echo $activeTab === 'events' ? 'active' : ''; ?>">
                <i class="fas fa-list-check"></i> Integrity Events & Violations
                <span class="tab-badge"><?php echo number_format($stats['total_events']); ?></span>
            </a>
            <a href="?tab=sessions" class="tab-item <?php echo $activeTab === 'sessions' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> Proctoring Sessions Ledger
                <span class="tab-badge"><?php echo number_format($stats['total_sessions']); ?></span>
            </a>
        </div>

        <!-- Filter Form -->
        <form method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Search Student / USN</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter USN or Student name..." class="form-control">
                </div>
                <div class="form-group">
                    <label>Assessment Type</label>
                    <select name="assessment_type" class="form-control">
                        <option value="">All Assessment Types</option>
                        <?php foreach ($assessmentTypes as $at): ?>
                            <option value="<?php echo htmlspecialchars($at); ?>" <?php echo $assessmentType === $at ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $at))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($activeTab === 'events'): ?>
                <div class="form-group">
                    <label>Event / Violation Type</label>
                    <select name="event_type" class="form-control">
                        <option value="">All Event Types</option>
                        <?php foreach ($eventTypes as $et): ?>
                            <option value="<?php echo htmlspecialchars($et); ?>" <?php echo $eventType === $et ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(formatEventType($et)['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Severity</label>
                    <select name="severity" class="form-control">
                        <option value="">All Severities</option>
                        <option value="HIGH" <?php echo $severityFilter === 'HIGH' ? 'selected' : ''; ?>>HIGH (Critical)</option>
                        <option value="MEDIUM" <?php echo $severityFilter === 'MEDIUM' ? 'selected' : ''; ?>>MEDIUM (Warning)</option>
                        <option value="LOW" <?php echo $severityFilter === 'LOW' ? 'selected' : ''; ?>>LOW (Info)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Review Status</label>
                    <select name="review_status" class="form-control">
                        <option value="">All Review Statuses</option>
                        <option value="pending" <?php echo $reviewFilter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="verified_violation" <?php echo $reviewFilter === 'verified_violation' ? 'selected' : ''; ?>>Verified Violation</option>
                        <option value="dismissed" <?php echo $reviewFilter === 'dismissed' ? 'selected' : ''; ?>>Dismissed / False Positive</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group" style="display:flex; gap:8px;">
                    <button type="submit" class="btn-action btn-primary" style="flex:1;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="?tab=<?php echo htmlspecialchars($activeTab); ?>" class="btn-action btn-secondary">
                        <i class="fas fa-rotate-left"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Data Content -->
        <?php if ($activeTab === 'events'): ?>
            <!-- Integrity Events Table -->
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Student</th>
                            <th>Assessment</th>
                            <th>Event / Violation</th>
                            <th>Severity</th>
                            <th>AI Confidence</th>
                            <th>Timestamp</th>
                            <th>Review Status</th>
                            <th>Evidence & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px; display:block;"></i>
                                    No proctoring integrity events found matching the selected criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $e): 
                                $evInfo = formatEventType($e['event_type']);
                                $confPct = round(($e['confidence'] ?? 1.0) * 100, 0);
                                $hasSnapshot = !empty($e['snapshot_path']);
                                $metadata = [];
                                if (!empty($e['metadata'])) {
                                    $metadata = is_string($e['metadata']) ? json_decode($e['metadata'], true) : $e['metadata'];
                                }
                                $reason = $metadata['reason'] ?? '';
                                $sInfo = $studentMap[$e['student_id']] ?? null;
                                $sName = $sInfo['name'] ?? null;
                                $sInst = $sInfo['institution'] ?? '';
                            ?>
                            <tr>
                                <td><span style="font-family:monospace; color:#64748b;">#<?php echo $e['id']; ?></span></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars($sName ?: ('Candidate (' . $e['student_id'] . ')')); ?></div>
                                    <span class="usn-pill"><?php echo htmlspecialchars($e['student_id']); ?></span>
                                    <?php if ($sInst): ?>
                                        <span style="font-size:11px; color:#64748b; font-weight:600; margin-left:4px;"><?php echo htmlspecialchars($sInst); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color:#334155;"><?php echo htmlspecialchars($e['assessment_type']); ?></span>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <i class="fas <?php echo $evInfo['icon']; ?>" style="color:<?php echo $evInfo['color']; ?>; font-size:14px;"></i>
                                        <div>
                                            <div style="font-weight:700; color:<?php echo $evInfo['color']; ?>;"><?php echo htmlspecialchars($evInfo['label']); ?></div>
                                            <?php if ($reason): ?>
                                                <div style="font-size:11px; color:#64748b; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($reason); ?>">
                                                    <?php echo htmlspecialchars($reason); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $sev = strtoupper($e['severity']);
                                    if ($sev === 'HIGH') echo '<span class="badge badge-high"><i class="fas fa-circle-exclamation"></i> HIGH</span>';
                                    elseif ($sev === 'MEDIUM') echo '<span class="badge badge-med"><i class="fas fa-triangle-exclamation"></i> MEDIUM</span>';
                                    else echo '<span class="badge badge-low"><i class="fas fa-info-circle"></i> LOW</span>';
                                    ?>
                                </td>
                                <td>
                                    <div style="font-weight:700; font-family:monospace;"><?php echo $confPct; ?>%</div>
                                    <div style="width:60px; height:4px; background:#e2e8f0; border-radius:2px; margin-top:2px;">
                                        <div style="width:<?php echo $confPct; ?>%; height:100%; background:<?php echo $confPct > 80 ? '#16a34a' : '#d97706'; ?>; border-radius:2px;"></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:12.5px; color:#334155; font-weight:500;"><?php echo date('M d, Y H:i:s', strtotime($e['created_at'])); ?></div>
                                    <div style="font-size:11px; color:#94a3b8;"><?php echo date('h:i A', strtotime($e['created_at'])); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $status = $e['review_status'] ?? 'pending';
                                    if ($status === 'verified_violation') {
                                        echo '<span class="badge badge-status-verified"><i class="fas fa-circle-check"></i> Verified Violation</span>';
                                    } elseif ($status === 'dismissed') {
                                        echo '<span class="badge badge-status-dismissed"><i class="fas fa-ban"></i> Dismissed</span>';
                                    } else {
                                        echo '<span class="badge badge-status-pending"><i class="fas fa-clock"></i> Pending Review</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <?php if ($hasSnapshot): ?>
                                            <button type="button" class="btn-snapshot" 
                                                    onclick="openSnapshotModal(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars(addslashes($sName ?: $e['student_id'])); ?>', '<?php echo htmlspecialchars($evInfo['label']); ?>', '<?php echo htmlspecialchars(addslashes($reason)); ?>', '<?php echo $confPct; ?>%', '<?php echo date('M d, Y h:i:s A', strtotime($e['created_at'])); ?>', '<?php echo htmlspecialchars($e['sha256'] ?? ''); ?>', '<?php echo !empty($e['file_size']) ? round($e['file_size']/1024, 1).' KB' : ''; ?>')">
                                                <i class="fas fa-camera"></i> Evidence
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size:11px; color:#94a3b8; font-weight:600;"><i class="fas fa-camera-slash"></i> No Snapshot</span>
                                        <?php endif; ?>

                                        <!-- Review Status Quick Toggle -->
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                            <input type="hidden" name="action" value="update_review_status">
                                            <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
                                            
                                            <?php if ($status !== 'verified_violation'): ?>
                                                <button type="submit" name="review_status" value="verified_violation" title="Mark as Verified Violation" class="btn-action" style="padding:4px 8px; font-size:11px; background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0;">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($status !== 'dismissed'): ?>
                                                <button type="submit" name="review_status" value="dismissed" title="Dismiss Event (False Positive)" class="btn-action" style="padding:4px 8px; font-size:11px; background:#f8fafc; color:#64748b; border:1px solid #e2e8f0;">
                                                    <i class="fas fa-xmark"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- Proctoring Sessions Table -->
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Student</th>
                            <th>Assessment Type</th>
                            <th>Status</th>
                            <th>Strikes</th>
                            <th>Suspicion / Penalty</th>
                            <th>Last Heartbeat</th>
                            <th>Session Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding: 40px; color: #94a3b8;">
                                    <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px; display:block;"></i>
                                    No proctoring sessions recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sessions as $s): 
                                $sInfo = $studentMap[$s['student_id']] ?? null;
                                $sName = $sInfo['name'] ?? null;
                                $sInst = $sInfo['institution'] ?? '';
                            ?>
                            <tr>
                                <td><span style="font-family:monospace; color:#64748b;">#<?php echo $s['id']; ?></span></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-dark);"><?php echo htmlspecialchars($sName ?: ('Candidate (' . $s['student_id'] . ')')); ?></div>
                                    <span class="usn-pill"><?php echo htmlspecialchars($s['student_id']); ?></span>
                                    <?php if ($sInst): ?>
                                        <span style="font-size:11px; color:#64748b; font-weight:600; margin-left:4px;"><?php echo htmlspecialchars($sInst); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color:#334155;"><?php echo htmlspecialchars(ucwords($s['assessment_type'])); ?></span>
                                    <div style="font-size:11px; color:#94a3b8;">ID: #<?php echo $s['assessment_id']; ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $st = strtolower($s['status']);
                                    if ($st === 'active') echo '<span class="badge" style="background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0;"><i class="fas fa-circle-dot" style="color:#22c55e;"></i> Active Live</span>';
                                    elseif ($st === 'terminated') echo '<span class="badge badge-high"><i class="fas fa-ban"></i> Terminated</span>';
                                    elseif ($st === 'flagged') echo '<span class="badge badge-med"><i class="fas fa-flag"></i> Flagged</span>';
                                    elseif ($st === 'completed') echo '<span class="badge" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;"><i class="fas fa-circle-check"></i> Completed</span>';
                                    else echo '<span class="badge badge-status-pending">' . htmlspecialchars(ucfirst($st)) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $strikes = (int)($s['strike_count'] ?? 0);
                                    if ($strikes >= 3) {
                                        echo '<span class="badge badge-high"><i class="fas fa-skull"></i> 3/3 (Terminated)</span>';
                                    } elseif ($strikes > 0) {
                                        echo '<span class="badge badge-med"><i class="fas fa-triangle-exclamation"></i> ' . $strikes . '/3 Strikes</span>';
                                    } else {
                                        echo '<span class="badge badge-status-verified" style="background:#f8fafc; color:#64748b; border:1px solid #e2e8f0;">0 Strikes</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div style="font-size:12.5px; font-weight:600;">Suspicion: <?php echo (float)($s['suspicion_score'] ?? 0); ?>%</div>
                                    <div style="font-size:11px; color:#dc2626; font-weight:700;">Penalty: -<?php echo (float)($s['penalty_pct'] ?? 0); ?>%</div>
                                </td>
                                <td>
                                    <div style="font-size:12.5px; color:#334155; font-weight:500;">
                                        <?php echo !empty($s['last_heartbeat_at']) ? date('M d, H:i:s', strtotime($s['last_heartbeat_at'])) : '—'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:12.5px; color:#334155; font-weight:500;"><?php echo date('M d, Y H:i', strtotime($s['created_at'])); ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Pagination Bar -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div style="font-size: 13px; color: #64748b;">
                    Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> (<?php echo number_format($totalRows); ?> total records)
                </div>
                <div style="display:flex; gap:6px;">
                    <?php 
                    $queryParams = $_GET;
                    $queryParams['page'] = $page - 1;
                    $prevUrl = '?' . http_build_query($queryParams);
                    $queryParams['page'] = $page + 1;
                    $nextUrl = '?' . http_build_query($queryParams);
                    ?>
                    <a href="<?php echo $prevUrl; ?>" class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                    <a href="<?php echo $nextUrl; ?>" class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next <i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- Evidence Snapshot Modal -->
<div id="snapshotModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-shield-cat"></i> Proctored Evidence Vault Snapshot</h3>
            <button type="button" class="modal-close" onclick="closeSnapshotModal()">&times;</button>
        </div>
        <div class="modal-body">
            <img id="modalEvidenceImg" src="" alt="Violation Evidence Snapshot" class="evidence-img" srcset="">
            <div class="modal-meta-grid">
                <div><strong>Student Name:</strong> <span id="modalStudentName">—</span></div>
                <div><strong>Violation Event:</strong> <span id="modalEventLabel">—</span></div>
                <div><strong>AI Confidence:</strong> <span id="modalConfidence">—</span></div>
                <div><strong>Event ID:</strong> <span id="modalEventId">—</span></div>
                <div><strong>Snapshot Time:</strong> <span id="modalTimestamp">—</span></div>
                <div><strong>File Integrity Size:</strong> <span id="modalFileSize">—</span></div>
                <div style="grid-column: span 2;">
                    <strong>SHA-256 Hash Proof:</strong> 
                    <div id="modalSha256" style="font-family:monospace; font-size:11px; background:#e2e8f0; padding:4px 8px; border-radius:6px; margin-top:4px; word-break:break-all; color:#1e293b;">—</div>
                </div>
                <div style="grid-column: span 2;"><strong>Metadata / Reason:</strong> <span id="modalReason">—</span></div>
            </div>
        </div>
    </div>
</div>

<script>
function openSnapshotModal(eventId, studentName, eventLabel, reason, confidence, timestamp, sha256, fileSize) {
    document.getElementById('modalEventId').textContent = '#' + eventId;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('modalEventLabel').textContent = eventLabel;
    document.getElementById('modalReason').textContent = reason || 'N/A';
    document.getElementById('modalConfidence').textContent = confidence;
    document.getElementById('modalTimestamp').textContent = timestamp || 'N/A';
    document.getElementById('modalSha256').textContent = sha256 || 'Verified Integrity Hash Protected';
    document.getElementById('modalFileSize').textContent = fileSize || 'N/A';
    
    // Fetch snapshot securely through evidence viewer endpoint
    const imgEl = document.getElementById('modalEvidenceImg');
    imgEl.src = '<?php echo APP_URL; ?>/officer/proctor_evidence.php?event_id=' + eventId + '&t=' + Date.now();
    
    document.getElementById('snapshotModal').classList.add('active');
}

function closeSnapshotModal() {
    document.getElementById('snapshotModal').classList.remove('active');
    document.getElementById('modalEvidenceImg').src = '';
}

// Close modal on escape key or clicking backdrop
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSnapshotModal();
});
document.getElementById('snapshotModal').addEventListener('click', function(e) {
    if (e.target === this) closeSnapshotModal();
});
</script>

</body>
</html>
